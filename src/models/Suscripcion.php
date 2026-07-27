<?php
/**
 * src/models/Suscripcion.php
 * Modelo de la tabla suscripcion (relacion N:M entre persona y revista).
 */

require_once __DIR__ . '/../../config/database.php';

class Suscripcion
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista suscripciones con datos de la persona y la revista (JOINs).
     */
    public function listar(?string $estado = null, ?int $idPersona = null): array
    {
        $sql = 'SELECT s.*,
                       p.nombres, p.apellidos, p.cedula,
                       r.nombre AS nombre_revista, r.periodicidad, r.precio_suscripcion
                FROM suscripcion s
                INNER JOIN persona p ON p.id_persona = s.id_persona
                INNER JOIN revista r ON r.id_revista = s.id_revista
                WHERE 1=1';
        $params = [];

        if ($estado !== null && in_array($estado, ['activa', 'cancelada', 'vencida'], true)) {
            $sql .= ' AND s.estado = :estado';
            $params[':estado'] = $estado;
        }

        if ($idPersona !== null) {
            $sql .= ' AND s.id_persona = :id_persona';
            $params[':id_persona'] = $idPersona;
        }

        $sql .= ' ORDER BY s.fecha_inicio DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*,
                    p.nombres, p.apellidos,
                    r.nombre AS nombre_revista
             FROM suscripcion s
             INNER JOIN persona p ON p.id_persona = s.id_persona
             INNER JOIN revista r ON r.id_revista = s.id_revista
             WHERE s.id_suscripcion = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    /**
     * Regla de negocio: una persona no puede tener dos suscripciones
     * ACTIVAS a la misma revista al mismo tiempo.
     */
    public function existeActiva(int $idPersona, int $idRevista): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM suscripcion
             WHERE id_persona = :persona AND id_revista = :revista
               AND estado = 'activa'"
        );
        $stmt->bindValue(':persona', $idPersona, PDO::PARAM_INT);
        $stmt->bindValue(':revista', $idRevista, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO suscripcion (id_persona, id_revista, fecha_inicio, fecha_fin)
                VALUES (:persona, :revista, :inicio, :fin)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':persona', $datos['id_persona'], PDO::PARAM_INT);
        $stmt->bindValue(':revista', $datos['id_revista'], PDO::PARAM_INT);
        $stmt->bindValue(':inicio',  $datos['fecha_inicio'] ?? date('Y-m-d'));
        $stmt->bindValue(':fin',     $datos['fecha_fin'] ?? null);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE suscripcion
                SET id_persona = :persona, id_revista = :revista,
                    fecha_inicio = :inicio, fecha_fin = :fin, estado = :estado
                WHERE id_suscripcion = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':persona', $datos['id_persona'], PDO::PARAM_INT);
        $stmt->bindValue(':revista', $datos['id_revista'], PDO::PARAM_INT);
        $stmt->bindValue(':inicio',  $datos['fecha_inicio']);
        $stmt->bindValue(':fin',     $datos['fecha_fin'] ?? null);
        $stmt->bindValue(':estado',  $datos['estado']);
        $stmt->bindValue(':id',      $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Cancela la suscripcion: estado = cancelada y fecha_fin = hoy.
     */
    public function cancelar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE suscripcion
             SET estado = 'cancelada', fecha_fin = CURRENT_DATE
             WHERE id_suscripcion = :id AND estado = 'activa'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM suscripcion WHERE id_suscripcion = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}