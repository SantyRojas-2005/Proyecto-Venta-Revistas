<?php
/**
 * src/models/Usuario.php
 * Modelo de la tabla usuario: cuentas del panel administrativo.
 * La autenticacion usa password_hash / password_verify de PHP.
 */

require_once __DIR__ . '/../../config/database.php';

class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Autentica un usuario. Devuelve el registro (sin el hash) si las
     * credenciales son correctas y la cuenta esta activa; null si no.
     */
    public function autenticar(string $nombreUsuario, string $password): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuario
             WHERE nombre_usuario = :usuario AND estado = 'activo'"
        );
        $stmt->bindValue(':usuario', $nombreUsuario);
        $stmt->execute();
        $usuario = $stmt->fetch();

        if ($usuario === false || !password_verify($password, $usuario['password_hash'])) {
            return null;
        }

        unset($usuario['password_hash']); // nunca exponer el hash fuera del modelo
        return $usuario;
    }

    public function listar(): array
    {
        $stmt = $this->db->query(
            'SELECT id_usuario, nombre_usuario, nombre_completo, rol, estado
             FROM usuario ORDER BY nombre_completo'
        );
        return $stmt->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id_usuario, nombre_usuario, nombre_completo, rol, estado
             FROM usuario WHERE id_usuario = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    public function existeNombreUsuario(string $nombreUsuario, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuario WHERE nombre_usuario = :usuario';
        if ($exceptoId !== null) {
            $sql .= ' AND id_usuario <> :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario', $nombreUsuario);
        if ($exceptoId !== null) {
            $stmt->bindValue(':id', $exceptoId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crea un usuario. Recibe la contrasena en claro y la cifra aqui,
     * de modo que el hash nunca se maneja fuera del modelo.
     */
    public function crear(array $datos): int
    {
        $sql = 'INSERT INTO usuario (nombre_usuario, password_hash, nombre_completo, rol)
                VALUES (:usuario, :hash, :nombre, :rol)';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario', $datos['nombre_usuario']);
        $stmt->bindValue(':hash',    password_hash($datos['password'], PASSWORD_DEFAULT));
        $stmt->bindValue(':nombre',  $datos['nombre_completo']);
        $stmt->bindValue(':rol',     $datos['rol'] ?? 'operador');
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza datos generales (sin tocar la contrasena).
     */
    public function actualizar(int $id, array $datos): bool
    {
        $sql = 'UPDATE usuario
                SET nombre_usuario = :usuario, nombre_completo = :nombre,
                    rol = :rol, estado = :estado
                WHERE id_usuario = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario', $datos['nombre_usuario']);
        $stmt->bindValue(':nombre',  $datos['nombre_completo']);
        $stmt->bindValue(':rol',     $datos['rol']);
        $stmt->bindValue(':estado',  $datos['estado'] ?? 'activo');
        $stmt->bindValue(':id',      $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function cambiarPassword(int $id, string $passwordNueva): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuario SET password_hash = :hash WHERE id_usuario = :id'
        );
        $stmt->bindValue(':hash', password_hash($passwordNueva, PASSWORD_DEFAULT));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function desactivar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario SET estado = 'inactivo' WHERE id_usuario = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}