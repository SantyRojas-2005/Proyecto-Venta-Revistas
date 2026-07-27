<?php
/**
 * src/controllers/RevistaController.php
 * Acciones: listar, listar_activas, obtener, crear, actualizar,
 * descontinuar, eliminar.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Revista.php';

class RevistaController
{
    private Revista $modelo;

    public function __construct()
    {
        $this->modelo = new Revista();
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
                case 'listar_activas':
                    Response::ok('Revistas activas.', $this->modelo->listarActivas());
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
                case 'descontinuar':
                    $this->descontinuar();
                    break;
                case 'eliminar':
                    $this->eliminar();
                    break;
                default:
                    Response::error('Accion no reconocida.', null, 400);
            }
        } catch (PDOException $e) {
            Response::error('Error de base de datos: ' . $e->getMessage(), null, 500);
        }
    }

    private function listar(): void
    {
        $busqueda = $_GET['busqueda'] ?? null;
        $estado   = $_GET['estado'] ?? null;
        Response::ok('Revistas listadas.', $this->modelo->listar($busqueda, $estado));
    }

    private function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $revista = $this->modelo->obtenerPorId($id);
        if ($revista === null) {
            Response::notFound('La revista no existe.');
        }
        // Info extra util para la vista de detalle
        $revista['suscriptores_activos'] = $this->modelo->contarSuscriptoresActivos($id);
        Response::ok('Revista encontrada.', $revista);
    }

    private function crear(): void
    {
        $datos = [
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'categoria'          => trim($_POST['categoria'] ?? ''),
            'periodicidad'       => $_POST['periodicidad'] ?? '',
            'precio_suscripcion' => $_POST['precio_suscripcion'] ?? null,
        ];

        $errores = Validator::validarRevista($datos);
        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear($datos);
        Response::ok('Revista registrada correctamente.', ['id_revista' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_revista'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La revista no existe.');
        }

        $datos = [
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'categoria'          => trim($_POST['categoria'] ?? ''),
            'periodicidad'       => $_POST['periodicidad'] ?? '',
            'precio_suscripcion' => $_POST['precio_suscripcion'] ?? null,
            'estado'             => $_POST['estado'] ?? 'activa',
        ];

        $errores = Validator::validarRevista($datos);
        if (!Validator::enLista($datos['estado'], ['activa', 'descontinuada'])) {
            $errores['estado'] = 'Estado no valido.';
        }
        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, $datos);
        Response::ok('Revista actualizada correctamente.');
    }

    private function descontinuar(): void
    {
        $id = (int) ($_POST['id_revista'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La revista no existe.');
        }
        $this->modelo->descontinuar($id);
        Response::ok('Revista marcada como descontinuada.');
    }

    private function eliminar(): void
    {
        $id = (int) ($_POST['id_revista'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La revista no existe.');
        }
        try {
            $this->modelo->eliminar($id);
            Response::ok('Revista eliminada definitivamente.');
        } catch (PDOException $e) {
            Response::error(
                'No se puede eliminar: la revista tiene ejemplares o suscripciones asociadas. ' .
                'Use la opcion Descontinuar en su lugar.',
                null,
                409
            );
        }
    }
}
