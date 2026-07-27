<?php
/**
 * src/models/Revista.php
 * Modelo de la tabla revista.
 */

require_once __DIR__ . '/../../config/database.php';

class Revista
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listar(?string $busqueda = null, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM revista WHERE 1=1';
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' AND (nombre LIKE :busqueda1 OR categoria LIKE :busqueda2)';
            $termino = '%' . trim($busqueda) . '%';
            $params[':busqueda1'] = $termino;
            $params[':busqueda2'] = $termino;
        }

        if ($estado !== null && in_array($estado, ['activa', 'descontinuada'], true)) {
            $sql .= ' AND estado = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY nombre';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Solo revistas activas (para selects de suscripcion, por ejemplo).
     */
    public function listarActivas(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM revista WHERE estado = 'activa' ORDER BY nombre"
        );
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM revista WHERE id_revista = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO revista (nombre, categoria, periodicidad, precio_suscripcion)
                VALUES (:nombre, :categoria, :periodicidad, :precio)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',       $datos['nombre']);
        $stmt->bindValue(':categoria',    $datos['categoria']);
        $stmt->bindValue(':periodicidad', $datos['periodicidad']);
        $stmt->bindValue(':precio',       $datos['precio_suscripcion']);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE revista
                SET nombre = :nombre, categoria = :categoria,
                    periodicidad = :periodicidad, precio_suscripcion = :precio,
                    estado = :estado
                WHERE id_revista = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',       $datos['nombre']);
        $stmt->bindValue(':categoria',    $datos['categoria']);
        $stmt->bindValue(':periodicidad', $datos['periodicidad']);
        $stmt->bindValue(':precio',       $datos['precio_suscripcion']);
        $stmt->bindValue(':estado',       $datos['estado'] ?? 'activa');
        $stmt->bindValue(':id',           $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminacion logica: marca la revista como descontinuada.
     */
    public function descontinuar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE revista SET estado = 'descontinuada' WHERE id_revista = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM revista WHERE id_revista = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Numero de suscriptores activos por revista (util para el dashboard
     * y para justificar la relacion N:M en la defensa).
     */
    public function contarSuscriptoresActivos(int $idRevista): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM suscripcion
             WHERE id_revista = :id AND estado = 'activa'"
        );
        $stmt->bindValue(':id', $idRevista, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}