<?php
/**
 * src/models/Ejemplar.php
 * Modelo de la tabla ejemplar. Incluye control de stock,
 * usado por Envio al despachar ejemplares.
 */

require_once __DIR__ . '/../../config/database.php';

class Ejemplar
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista ejemplares con el nombre de su revista (JOIN).
     */
    public function listar(?int $idRevista = null): array
    {
        $sql = 'SELECT e.*, r.nombre AS nombre_revista, r.periodicidad
                FROM ejemplar e
                INNER JOIN revista r ON r.id_revista = e.id_revista';
        $params = [];

        if ($idRevista !== null) {
            $sql .= ' WHERE e.id_revista = :id_revista';
            $params[':id_revista'] = $idRevista;
        }

        $sql .= ' ORDER BY r.nombre, e.numero_edicion DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejemplares con stock disponible (> 0), para el formulario de envios.
     */
    public function listarConStock(): array
    {
        $stmt = $this->db->query(
            'SELECT e.*, r.nombre AS nombre_revista
             FROM ejemplar e
             INNER JOIN revista r ON r.id_revista = e.id_revista
             WHERE e.stock_disponible > 0
             ORDER BY r.nombre, e.numero_edicion DESC'
        );
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT e.*, r.nombre AS nombre_revista
             FROM ejemplar e
             INNER JOIN revista r ON r.id_revista = e.id_revista
             WHERE e.id_ejemplar = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    /**
     * Evita duplicar el numero de edicion dentro de la misma revista
     * (respalda la restriccion UNIQUE(id_revista, numero_edicion)).
     */
    public function existeEdicion(int $idRevista, int $numeroEdicion, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ejemplar
                WHERE id_revista = :id_revista AND numero_edicion = :numero';
        if ($exceptoId !== null) {
            $sql .= ' AND id_ejemplar <> :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_revista', $idRevista, PDO::PARAM_INT);
        $stmt->bindValue(':numero', $numeroEdicion, PDO::PARAM_INT);
        if ($exceptoId !== null) {
            $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO ejemplar
                    (id_revista, numero_edicion, fecha_publicacion, stock_disponible, precio_unitario)
                VALUES
                    (:id_revista, :numero_edicion, :fecha_publicacion, :stock, :precio)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_revista',        $datos['id_revista'], PDO::PARAM_INT);
        $stmt->bindValue(':numero_edicion',    $datos['numero_edicion'], PDO::PARAM_INT);
        $stmt->bindValue(':fecha_publicacion', $datos['fecha_publicacion']);
        $stmt->bindValue(':stock',             $datos['stock_disponible'] ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':precio',            $datos['precio_unitario']);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE ejemplar
                SET id_revista = :id_revista, numero_edicion = :numero_edicion,
                    fecha_publicacion = :fecha_publicacion,
                    stock_disponible = :stock, precio_unitario = :precio
                WHERE id_ejemplar = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_revista',        $datos['id_revista'], PDO::PARAM_INT);
        $stmt->bindValue(':numero_edicion',    $datos['numero_edicion'], PDO::PARAM_INT);
        $stmt->bindValue(':fecha_publicacion', $datos['fecha_publicacion']);
        $stmt->bindValue(':stock',             $datos['stock_disponible'], PDO::PARAM_INT);
        $stmt->bindValue(':precio',            $datos['precio_unitario']);
        $stmt->bindValue(':id',                $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ejemplar WHERE id_ejemplar = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Descuenta stock de forma segura: la condicion
     * stock_disponible >= :cantidad garantiza que nunca quede negativo.
     * Devuelve false si no habia stock suficiente (0 filas afectadas).
     * Pensado para llamarse DENTRO de la transaccion de Envio.
     */
    public function descontarStock(int $id, int $cantidad): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ejemplar
             SET stock_disponible = stock_disponible - :cantidad
             WHERE id_ejemplar = :id AND stock_disponible >= :minimo'
        );
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':minimo', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    /**
     * Repone stock (por ejemplo al anular/devolver un envio).
     */
    public function reponerStock(int $id, int $cantidad): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ejemplar
             SET stock_disponible = stock_disponible + :cantidad
             WHERE id_ejemplar = :id'
        );
        $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}