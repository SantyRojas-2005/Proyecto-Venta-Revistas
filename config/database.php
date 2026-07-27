<?php
/**
 * config/database.php
 * Punto unico de conexion PDO.
 *
 * - En produccion (Render): lee las credenciales de variables de entorno
 *   (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) definidas en el panel.
 * - En local (XAMPP): si no existen esas variables, usa los valores por
 *   defecto de siempre (127.0.0.1, root, sin contrasena).
 */

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // getenv devuelve false si la variable no existe -> usar local.
            // No usar 'localhost' en local: en Windows intenta IPv6 primero
            // y demora ~1s por conexion.
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: 'revistas_domicilio';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // En produccion no conviene mostrar el detalle del error
                // (revela host/usuario); en local si ayuda a depurar.
                $esProduccion = getenv('DB_HOST') !== false;
                die($esProduccion
                    ? 'Error de conexion a la base de datos.'
                    : 'Error de conexion a la base de datos: ' . $e->getMessage());
            }
        }
        return self::$connection;
    }
}
