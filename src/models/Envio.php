<?php
/**
 * src/models/Envio.php
 * Modelo de la tabla envio. La operacion clave es crearConDetalles():
 * inserta el envio, sus lineas de detalle y descuenta stock dentro de
 * UNA transaccion, garantizando atomicidad (todo o nada).
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Ejemplar.php';

class Envio
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista envios con destinatario, agencia y usuario que lo registro.
     */
    public function listar(?string $estado = null): array
    {
        $sql = "SELECT e.*,
                       CONCAT(p.nombres, ' ', p.apellidos) AS destinatario,
                       a.nombre  AS nombre_agencia,
                       u.nombre_completo AS registrado_por
                FROM envio e
                INNER JOIN persona            p ON p.id_persona = e.id_persona
                INNER JOIN agencia_transporte a ON a.id_agencia = e.id_agencia
                INNER JOIN usuario            u ON u.id_usuario = e.id_usuario
                WHERE 1=1";
        $params = [];

        if ($estado !== null
            && in_array($estado, ['pendiente', 'en_transito', 'entregado', 'devuelto'], true)) {
            $sql .= ' AND e.estado_envio = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY e.fecha_envio DESC, e.id_envio DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*,
                    CONCAT(p.nombres, ' ', p.apellidos) AS destinatario,
                    p.cedula, p.telefono,
                    a.nombre AS nombre_agencia, a.costo_base,
                    u.nombre_completo AS registrado_por
             FROM envio e
             INNER JOIN persona            p ON p.id_persona = e.id_persona
             INNER JOIN agencia_transporte a ON a.id_agencia = e.id_agencia
             INNER JOIN usuario            u ON u.id_usuario = e.id_usuario
             WHERE e.id_envio = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    /**
     * Crea un envio con sus detalles de forma ATOMICA.
     *
     * $datos    = [id_persona, id_agencia, id_usuario, direccion_entrega,
     *              fecha_entrega_estimada (opcional)]
     * $detalles = [ [id_ejemplar, cantidad], ... ]
     *
     * Flujo dentro de la transaccion:
     *   1. Calcula subtotales leyendo el precio actual de cada ejemplar.
     *   2. Descuenta stock (si algun ejemplar no tiene stock -> rollback).
     *   3. Inserta envio con costo_total = costo_base agencia + subtotales.
     *   4. Inserta cada linea en detalle_envio.
     *
     * Devuelve el id del envio creado.
     * Lanza Exception con mensaje claro si algo falla (el controlador
     * la captura y responde con Response::error).
     */
    public function crearConDetalles(array $datos, array $detalles): int
    {
        if (empty($detalles)) {
            throw new Exception('El envio debe incluir al menos un ejemplar.');
        }

        $ejemplarModel = new Ejemplar();

        try {
            $this->db->beginTransaction();

            // 1) Costo base de la agencia
            $stmt = $this->db->prepare(
                'SELECT costo_base FROM agencia_transporte WHERE id_agencia = :id'
            );
            $stmt->bindValue(':id', $datos['id_agencia'], PDO::PARAM_INT);
            $stmt->execute();
            $costoBase = $stmt->fetchColumn();
            if ($costoBase === false) {
                throw new Exception('La agencia de transporte no existe.');
            }

            // 2) Procesar detalles: validar, calcular subtotal, descontar stock
            $lineas = [];
            $totalEjemplares = 0.0;

            foreach ($detalles as $detalle) {
                $idEjemplar = (int) $detalle['id_ejemplar'];
                $cantidad   = (int) $detalle['cantidad'];

                if ($cantidad <= 0) {
                    throw new Exception('La cantidad debe ser mayor a 0.');
                }

                $ejemplar = $ejemplarModel->obtenerPorId($idEjemplar);
                if ($ejemplar === null) {
                    throw new Exception("El ejemplar #{$idEjemplar} no existe.");
                }

                if (!$ejemplarModel->descontarStock($idEjemplar, $cantidad)) {
                    throw new Exception(
                        "Stock insuficiente para {$ejemplar['nombre_revista']} " .
                        "edicion {$ejemplar['numero_edicion']} " .
                        "(disponible: {$ejemplar['stock_disponible']}, solicitado: {$cantidad})."
                    );
                }

                $subtotal = round($cantidad * (float) $ejemplar['precio_unitario'], 2);
                $totalEjemplares += $subtotal;

                $lineas[] = [
                    'id_ejemplar' => $idEjemplar,
                    'cantidad'    => $cantidad,
                    'subtotal'    => $subtotal,
                ];
            }

            $costoTotal = round((float) $costoBase + $totalEjemplares, 2);

            // 3) Insertar el envio
            $sql = 'INSERT INTO envio
                        (id_persona, id_agencia, id_usuario,
                         fecha_entrega_estimada, direccion_entrega, costo_total)
                    VALUES
                        (:persona, :agencia, :usuario, :estimada, :direccion, :total)';
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':persona',   $datos['id_persona'], PDO::PARAM_INT);
            $stmt->bindValue(':agencia',   $datos['id_agencia'], PDO::PARAM_INT);
            $stmt->bindValue(':usuario',   $datos['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':estimada',  $datos['fecha_entrega_estimada'] ?? null);
            $stmt->bindValue(':direccion', $datos['direccion_entrega']);
            $stmt->bindValue(':total',     $costoTotal);
            $stmt->execute();
            $idEnvio = (int) $this->db->lastInsertId();

            // 4) Insertar detalles
            $sqlDetalle = 'INSERT INTO detalle_envio (id_envio, id_ejemplar, cantidad, subtotal)
                           VALUES (:envio, :ejemplar, :cantidad, :subtotal)';
            $stmtDetalle = $this->db->prepare($sqlDetalle);
            foreach ($lineas as $linea) {
                $stmtDetalle->bindValue(':envio',    $idEnvio, PDO::PARAM_INT);
                $stmtDetalle->bindValue(':ejemplar', $linea['id_ejemplar'], PDO::PARAM_INT);
                $stmtDetalle->bindValue(':cantidad', $linea['cantidad'], PDO::PARAM_INT);
                $stmtDetalle->bindValue(':subtotal', $linea['subtotal']);
                $stmtDetalle->execute();
            }

            $this->db->commit();
            return $idEnvio;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e; // el controlador decide como responder
        }
    }

    /**
     * Cambia el estado del envio respetando el flujo:
     * pendiente -> en_transito -> entregado | devuelto.
     * Al marcar 'entregado' registra la fecha_entrega_real.
     * Al marcar 'devuelto' repone el stock de sus ejemplares.
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $estadosValidos = ['pendiente', 'en_transito', 'entregado', 'devuelto'];
        if (!in_array($nuevoEstado, $estadosValidos, true)) {
            throw new Exception('Estado de envio no valido.');
        }

        try {
            $this->db->beginTransaction();

            if ($nuevoEstado === 'entregado') {
                $stmt = $this->db->prepare(
                    "UPDATE envio
                     SET estado_envio = 'entregado', fecha_entrega_real = CURRENT_DATE
                     WHERE id_envio = :id"
                );
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE envio SET estado_envio = :estado WHERE id_envio = :id'
                );
                $stmt->bindValue(':estado', $nuevoEstado);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Si se devuelve, reponer stock de todos sus detalles
            if ($nuevoEstado === 'devuelto') {
                $ejemplarModel = new Ejemplar();
                foreach ($this->obtenerDetalles($id) as $detalle) {
                    $ejemplarModel->reponerStock(
                        (int) $detalle['id_ejemplar'],
                        (int) $detalle['cantidad']
                    );
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Detalles de un envio con datos del ejemplar y la revista.
     */
    public function obtenerDetalles(int $idEnvio): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*,
                    ej.numero_edicion, ej.precio_unitario,
                    r.nombre AS nombre_revista
             FROM detalle_envio d
             INNER JOIN ejemplar ej ON ej.id_ejemplar = d.id_ejemplar
             INNER JOIN revista  r  ON r.id_revista = ej.id_revista
             WHERE d.id_envio = :id
             ORDER BY r.nombre'
        );
        $stmt->bindValue(':id', $idEnvio, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Estadisticas rapidas para el dashboard.
     */
    public function contarPorEstado(): array
    {
        $stmt = $this->db->query(
            'SELECT estado_envio, COUNT(*) AS total
             FROM envio GROUP BY estado_envio'
        );
        $resultado = ['pendiente' => 0, 'en_transito' => 0, 'entregado' => 0, 'devuelto' => 0];
        foreach ($stmt->fetchAll() as $fila) {
            $resultado[$fila['estado_envio']] = (int) $fila['total'];
        }
        return $resultado;
    }
}