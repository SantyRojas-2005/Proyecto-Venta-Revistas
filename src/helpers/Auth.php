<?php
/**
 * src/helpers/Auth.php
 * Manejo de sesion y autorizacion del panel administrativo.
 * Se incluye al inicio de cada vista protegida y de cada controlador.
 */

class Auth
{
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Guarda al usuario autenticado en la sesion (tras login exitoso).
     */
    public static function login(array $usuario): void
    {
        self::iniciarSesion();
        session_regenerate_id(true); // previene fijacion de sesion
        $_SESSION['usuario'] = [
            'id_usuario'      => (int) $usuario['id_usuario'],
            'nombre_usuario'  => $usuario['nombre_usuario'],
            'nombre_completo' => $usuario['nombre_completo'],
            'rol'             => $usuario['rol'],
        ];
    }

    public static function logout(): void
    {
        self::iniciarSesion();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function estaAutenticado(): bool
    {
        self::iniciarSesion();
        return isset($_SESSION['usuario']);
    }

    public static function usuario(): ?array
    {
        self::iniciarSesion();
        return $_SESSION['usuario'] ?? null;
    }

    public static function esAdministrador(): bool
    {
        $usuario = self::usuario();
        return $usuario !== null && $usuario['rol'] === 'administrador';
    }

    /**
     * Para VISTAS: redirige al login si no hay sesion.
     * $nivel indica cuantos directorios subir hasta public/
     * (0 si la vista esta en public/, 1 si esta en public/personas/, etc.)
     */
    public static function requerirLogin(int $nivel = 1): void
    {
        if (!self::estaAutenticado()) {
            $prefijo = str_repeat('../', $nivel);
            header('Location: ' . $prefijo . 'login.php');
            exit;
        }
    }

    /**
     * Para CONTROLADORES (AJAX): responde 401 JSON si no hay sesion.
     */
    public static function requerirLoginAjax(): void
    {
        if (!self::estaAutenticado()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Sesion no iniciada o expirada.',
                'data'    => null,
            ]);
            exit;
        }
    }

    public static function requerirAdministradorAjax(): void
    {
        self::requerirLoginAjax();
        if (!self::esAdministrador()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Se requiere rol de administrador.',
                'data'    => null,
            ]);
            exit;
        }
    }
}
