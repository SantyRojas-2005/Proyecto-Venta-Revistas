<?php
/**
 * src/models/Persona.php
 * Modelo de la tabla persona. Todas las consultas usan sentencias
 * preparadas de PDO; nunca se concatena SQL con datos del usuario.
 */

require_once __DIR__ . '/../../config/database.php';

class Persona
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Lista todas las personas, opcionalmente filtradas por estado
     * o por un termino de busqueda (cedula, nombres o apellidos).
     */
    public function listar(?string $busqueda = null, ?string $estado = null): array
    {
        $sql = 'SELECT * FROM persona WHERE 1=1';
        $params = [];

        if ($busqueda !== null && trim($busqueda) !== '') {
            $sql .= ' AND (cedula LIKE :busqueda1
                       OR nombres LIKE :busqueda2
                       OR apellidos LIKE :busqueda3)';
            $termino = '%' . trim($busqueda) . '%';
            $params[':busqueda1'] = $termino;
            $params[':busqueda2'] = $termino;
            $params[':busqueda3'] = $termino;
        }

        if ($estado !== null && in_array($estado, ['activo', 'inactivo'], true)) {
            $sql .= ' AND estado = :estado';
            $params[':estado'] = $estado;
        }

        $sql .= ' ORDER BY apellidos, nombres';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM persona WHERE id_persona = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    /**
     * Verifica si ya existe una cedula o email (para evitar duplicados
     * antes del INSERT/UPDATE). $exceptoId excluye al propio registro
     * cuando se esta editando.
     */
    public function existeCedula(string $cedula, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM persona WHERE cedula = :cedula';
        if ($exceptoId !== null) {
            $sql .= ' AND id_persona <> :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cedula', $cedula);
        if ($exceptoId !== null) {
            $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    public function existeEmail(string $email, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM persona WHERE email = :email';
        if ($exceptoId !== null) {
            $sql .= ' AND id_persona <> :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':email', $email);
        if ($exceptoId !== null) {
            $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea una persona y devuelve el id generado.
     */
    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO persona (cedula, nombres, apellidos, direccion, telefono, email)
                VALUES (:cedula, :nombres, :apellidos, :direccion, :telefono, :email)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cedula',    $datos['cedula']);
        $stmt->bindValue(':nombres',   $datos['nombres']);
        $stmt->bindValue(':apellidos', $datos['apellidos']);
        $stmt->bindValue(':direccion', $datos['direccion']);
        $stmt->bindValue(':telefono',  $datos['telefono']);
        $stmt->bindValue(':email',     $datos['email']);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE persona
                SET cedula = :cedula, nombres = :nombres, apellidos = :apellidos,
                    direccion = :direccion, telefono = :telefono, email = :email,
                    estado = :estado
                WHERE id_persona = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cedula',    $datos['cedula']);
        $stmt->bindValue(':nombres',   $datos['nombres']);
        $stmt->bindValue(':apellidos', $datos['apellidos']);
        $stmt->bindValue(':direccion', $datos['direccion']);
        $stmt->bindValue(':telefono',  $datos['telefono']);
        $stmt->bindValue(':email',     $datos['email']);
        $stmt->bindValue(':estado',    $datos['estado'] ?? 'activo');
        $stmt->bindValue(':id',        $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminacion logica: cambia el estado a 'inactivo'.
     * Se prefiere sobre DELETE fisico porque persona es referenciada
     * por suscripcion y envio (FK RESTRICT).
     */
    public function desactivar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE persona SET estado = 'inactivo' WHERE id_persona = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminacion fisica (solo funciona si no tiene suscripciones ni
     * envios asociados; de lo contrario PDO lanza excepcion por la FK).
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM persona WHERE id_persona = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}