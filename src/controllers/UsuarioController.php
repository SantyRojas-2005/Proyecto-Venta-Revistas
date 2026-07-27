<?php
/**
 * src/controllers/UsuarioController.php
 * Gestion de cuentas del panel. TODAS las acciones exigen rol
 * de administrador (Auth::requerirAdministradorAjax).
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Usuario.php';

class UsuarioController
{
    private Usuario $modelo;

    public function __construct()
    {
        $this->modelo = new Usuario();
    }

    public function manejar(): void
    {
        Auth::requerirAdministradorAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    Response::ok('Usuarios listados.', $this->modelo->listar());
                    break;
                case 'obtener':
                    $this->obtener();
                    break;
                case 'crear':
                    $this->crear();
                    break;
                case 'actualizar':
                    $this->actualizar();
                    break;
                case 'cambiar_password':
                    $this->cambiarPassword();
                    break;
                case 'desactivar':
                    $this->desactivar();
                    break;
                default:
                    Response::error('Accion no reconocida.', null, 400);
            }
        } catch (PDOException $e) {
            Response::error('Error de base de datos: ' . $e->getMessage(), null, 500);
        }
    }

    private function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $usuario = $this->modelo->obtenerPorId($id);
        if ($usuario === null) {
            Response::notFound('El usuario no existe.');
        }
        Response::ok('Usuario encontrado.', $usuario);
    }

    private function validarDatos(array $datos): array
    {
        $errores = [];

        if (!Validator::requerido($datos['nombre_usuario'])) {
            $errores['nombre_usuario'] = 'El nombre de usuario es obligatorio.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $datos['nombre_usuario'])) {
            $errores['nombre_usuario'] = 'De 3 a 50 caracteres: letras, numeros, punto, guion o guion bajo.';
        }

        if (!Validator::requerido($datos['nombre_completo'])) {
            $errores['nombre_completo'] = 'El nombre completo es obligatorio.';
        }

        if (!Validator::enLista($datos['rol'], ['administrador', 'operador'])) {
            $errores['rol'] = 'El rol debe ser administrador u operador.';
        }

        return $errores;
    }

    private function crear(): void
    {
        $datos = [
            'nombre_usuario'  => trim($_POST['nombre_usuario'] ?? ''),
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'rol'             => $_POST['rol'] ?? '',
            'password'        => $_POST['password'] ?? '',
        ];

        $errores = $this->validarDatos($datos);

        if (mb_strlen($datos['password']) < 8) {
            $errores['password'] = 'La contrasena debe tener al menos 8 caracteres.';
        }
        if (empty($errores['nombre_usuario'])
            && $this->modelo->existeNombreUsuario($datos['nombre_usuario'])) {
            $errores['nombre_usuario'] = 'Ese nombre de usuario ya existe.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear($datos);
        Response::ok('Usuario creado correctamente.', ['id_usuario' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_usuario'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El usuario no existe.');
        }

        $datos = [
            'nombre_usuario'  => trim($_POST['nombre_usuario'] ?? ''),
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'rol'             => $_POST['rol'] ?? '',
            'estado'          => $_POST['estado'] ?? 'activo',
        ];

        $errores = $this->validarDatos($datos);
        if (!Validator::enLista($datos['estado'], ['activo', 'inactivo'])) {
            $errores['estado'] = 'Estado no valido.';
        }
        if (empty($errores['nombre_usuario'])
            && $this->modelo->existeNombreUsuario($datos['nombre_usuario'], $id)) {
            $errores['nombre_usuario'] = 'Otro usuario ya tiene ese nombre.';
        }

        // Evitar que el administrador se degrade o desactive a si mismo
        $sesion = Auth::usuario();
        if ($id === $sesion['id_usuario']
            && ($datos['rol'] !== 'administrador' || $datos['estado'] !== 'activo')) {
            $errores['rol'] = 'No puede quitarse a si mismo el rol de administrador ni desactivarse.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, $datos);
        Response::ok('Usuario actualizado correctamente.');
    }

    private function cambiarPassword(): void
    {
        $id = (int) ($_POST['id_usuario'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El usuario no existe.');
        }
        if (mb_strlen($password) < 8) {
            Response::validationError(['password' => 'La contrasena debe tener al menos 8 caracteres.']);
        }

        $this->modelo->cambiarPassword($id, $password);
        Response::ok('Contrasena actualizada correctamente.');
    }

    private function desactivar(): void
    {
        $id = (int) ($_POST['id_usuario'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El usuario no existe.');
        }

        $sesion = Auth::usuario();
        if ($id === $sesion['id_usuario']) {
            Response::error('No puede desactivar su propia cuenta.', null, 409);
        }

        $this->modelo->desactivar($id);
        Response::ok('Usuario desactivado.');
    }
}
