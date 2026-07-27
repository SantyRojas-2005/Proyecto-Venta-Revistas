<?php
/**
 * src/models/AgenciaTransporte.php
 * Modelo de la tabla agencia_transporte.
 */

require_once __DIR__ . '/../../config/database.php';

class AgenciaTransporte
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listar(?string $busqueda = null): array
    {
        $sql = 'SELECT * FROM agencia_transporte';
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' WHERE nombre LIKE :busqueda1 OR cobertura_zona LIKE :busqueda2';
            $termino = '%' . trim($busqueda) . '%';
            $params[':busqueda1'] = $termino;
            $params[':busqueda2'] = $termino;
        }

        $sql .= ' ORDER BY nombre';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM agencia_transporte WHERE id_agencia = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    public function existeRuc(string $ruc, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM agencia_transporte WHERE ruc = :ruc';
        if ($exceptoId !== null) {
            $sql .= ' AND id_agencia <> :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ruc', $ruc);
        if ($exceptoId !== null) {
            $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO agencia_transporte (nombre, ruc, telefono, cobertura_zona, costo_base)
                VALUES (:nombre, :ruc, :telefono, :cobertura, :costo)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',    $datos['nombre']);
        $stmt->bindValue(':ruc',       $datos['ruc']);
        $stmt->bindValue(':telefono',  $datos['telefono']);
        $stmt->bindValue(':cobertura', $datos['cobertura_zona']);
        $stmt->bindValue(':costo',     $datos['costo_base']);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE agencia_transporte
                SET nombre = :nombre, ruc = :ruc, telefono = :telefono,
                    cobertura_zona = :cobertura, costo_base = :costo
                WHERE id_agencia = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',    $datos['nombre']);
        $stmt->bindValue(':ruc',       $datos['ruc']);
        $stmt->bindValue(':telefono',  $datos['telefono']);
        $stmt->bindValue(':cobertura', $datos['cobertura_zona']);
        $stmt->bindValue(':costo',     $datos['costo_base']);
        $stmt->bindValue(':id',        $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM agencia_transporte WHERE id_agencia = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}