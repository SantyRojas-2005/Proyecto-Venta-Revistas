<?php
/**
 * src/controllers/AuthController.php
 * Punto de entrada AJAX/POST: public/api/auth.php lo incluye y ejecuta.
 * Acciones: login, logout.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Usuario.php';

class AuthController
{
    public function manejar(): void
    {
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        switch ($accion) {
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            default:
                Response::error('Accion no reconocida.', null, 400);
        }
    }

    private function login(): void
    {
        $nombreUsuario = trim($_POST['nombre_usuario'] ?? '');
        $password      = $_POST['password'] ?? '';

        if (!Validator::requerido($nombreUsuario) || !Validator::requerido($password)) {
            Response::validationError([
                'credenciales' => 'Usuario y contrasena son obligatorios.',
            ]);
        }

        $modelo  = new Usuario();
        $usuario = $modelo->autenticar($nombreUsuario, $password);

        if ($usuario === null) {
            // Mensaje generico: no revelar si fallo el usuario o la clave
            Response::error('Credenciales incorrectas.', null, 401);
        }

        Auth::login($usuario);
        Response::ok('Bienvenido, ' . $usuario['nombre_completo'], [
            'nombre_completo' => $usuario['nombre_completo'],
            'rol'             => $usuario['rol'],
        ]);
    }

    private function logout(): void
    {
        Auth::logout();
        Response::ok('Sesion cerrada.');
    }
}
