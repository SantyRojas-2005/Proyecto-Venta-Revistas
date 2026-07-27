<?php
/**
 * src/controllers/PersonaController.php
 * Acciones: listar, obtener, crear, actualizar, desactivar, eliminar.
 * Todas requieren sesion iniciada.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Persona.php';

class PersonaController
{
    private Persona $modelo;

    public function __construct()
    {
        $this->modelo = new Persona();
    }

    public function manejar(): void
    {
        Auth::requerirLoginAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    $this->listar();
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
                case 'desactivar':
                    $this->desactivar();
                    break;
                case 'eliminar':
                    $this->eliminar();
                    break;
                default:
                    Response::error('Accion no reconocida.', null, 400);
            }
        } catch (PDOException $e) {
            // Violacion de FK al eliminar, UNIQUE duplicado, etc.
            Response::error('Error de base de datos: ' . $e->getMessage(), null, 500);
        }
    }

    private function listar(): void
    {
        $busqueda = $_GET['busqueda'] ?? null;
        $estado   = $_GET['estado'] ?? null;
        Response::ok('Personas listadas.', $this->modelo->listar($busqueda, $estado));
    }

    private function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $persona = $this->modelo->obtenerPorId($id);
        if ($persona === null) {
            Response::notFound('La persona no existe.');
        }
        Response::ok('Persona encontrada.', $persona);
    }

    private function crear(): void
    {
        $datos = [
            'cedula'    => trim($_POST['cedula'] ?? ''),
            'nombres'   => trim($_POST['nombres'] ?? ''),
            'apellidos' => trim($_POST['apellidos'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'telefono'  => trim($_POST['telefono'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
        ];

        $errores = Validator::validarPersona($datos);

        if (empty($errores['cedula']) && $this->modelo->existeCedula($datos['cedula'])) {
            $errores['cedula'] = 'Ya existe una persona con esa cedula.';
        }
        if (empty($errores['email']) && $this->modelo->existeEmail($datos['email'])) {
            $errores['email'] = 'Ya existe una persona con ese email.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear($datos);
        Response::ok('Persona registrada correctamente.', ['id_persona' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_persona'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La persona no existe.');
        }

        $datos = [
            'cedula'    => trim($_POST['cedula'] ?? ''),
            'nombres'   => trim($_POST['nombres'] ?? ''),
            'apellidos' => trim($_POST['apellidos'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'telefono'  => trim($_POST['telefono'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'estado'    => $_POST['estado'] ?? 'activo',
        ];

        $errores = Validator::validarPersona($datos);

        if (!Validator::enLista($datos['estado'], ['activo', 'inactivo'])) {
            $errores['estado'] = 'Estado no valido.';
        }
        if (empty($errores['cedula']) && $this->modelo->existeCedula($datos['cedula'], $id)) {
            $errores['cedula'] = 'Otra persona ya tiene esa cedula.';
        }
        if (empty($errores['email']) && $this->modelo->existeEmail($datos['email'], $id)) {
            $errores['email'] = 'Otra persona ya tiene ese email.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, $datos);
        Response::ok('Persona actualizada correctamente.');
    }

    private function desactivar(): void
    {
        $id = (int) ($_POST['id_persona'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La persona no existe.');
        }
        $this->modelo->desactivar($id);
        Response::ok('Persona desactivada.');
    }

    private function eliminar(): void
    {
        $id = (int) ($_POST['id_persona'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La persona no existe.');
        }
        try {
            $this->modelo->eliminar($id);
            Response::ok('Persona eliminada definitivamente.');
        } catch (PDOException $e) {
            Response::error(
                'No se puede eliminar: la persona tiene suscripciones o envios asociados. ' .
                'Use la opcion Desactivar en su lugar.',
                null,
                409
            );
        }
    }
}
