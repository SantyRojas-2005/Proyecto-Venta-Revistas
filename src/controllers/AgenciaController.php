<?php
/**
 * src/controllers/AgenciaController.php
 * Acciones: listar, obtener, crear, actualizar, eliminar.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/AgenciaTransporte.php';

class AgenciaController
{
    private AgenciaTransporte $modelo;

    public function __construct()
    {
        $this->modelo = new AgenciaTransporte();
    }

    public function manejar(): void
    {
        Auth::requerirLoginAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    Response::ok('Agencias listadas.', $this->modelo->listar($_GET['busqueda'] ?? null));
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

    private function obtener(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $agencia = $this->modelo->obtenerPorId($id);
        if ($agencia === null) {
            Response::notFound('La agencia no existe.');
        }
        Response::ok('Agencia encontrada.', $agencia);
    }

    private function validarDatos(array $datos): array
    {
        $errores = [];

        if (!Validator::requerido($datos['nombre'])) {
            $errores['nombre'] = 'El nombre es obligatorio.';
        }
        if (!Validator::requerido($datos['ruc'])) {
            $errores['ruc'] = 'El RUC es obligatorio.';
        } elseif (!Validator::ruc($datos['ruc'])) {
            $errores['ruc'] = 'El RUC debe tener 13 digitos y terminar en 001.';
        }
        if (!Validator::requerido($datos['telefono'])) {
            $errores['telefono'] = 'El telefono es obligatorio.';
        } elseif (!Validator::telefono($datos['telefono'])) {
            $errores['telefono'] = 'El telefono debe tener entre 7 y 10 digitos.';
        }
        if (!Validator::requerido($datos['cobertura_zona'])) {
            $errores['cobertura_zona'] = 'La zona de cobertura es obligatoria.';
        }
        if (!Validator::decimalPositivo($datos['costo_base'])) {
            $errores['costo_base'] = 'El costo base debe ser mayor a 0.';
        }

        return $errores;
    }

    private function crear(): void
    {
        $datos = [
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'ruc'            => trim($_POST['ruc'] ?? ''),
            'telefono'       => trim($_POST['telefono'] ?? ''),
            'cobertura_zona' => trim($_POST['cobertura_zona'] ?? ''),
            'costo_base'     => $_POST['costo_base'] ?? null,
        ];

        $errores = $this->validarDatos($datos);
        if (empty($errores['ruc']) && $this->modelo->existeRuc($datos['ruc'])) {
            $errores['ruc'] = 'Ya existe una agencia con ese RUC.';
        }
        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear($datos);
        Response::ok('Agencia registrada correctamente.', ['id_agencia' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_agencia'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La agencia no existe.');
        }

        $datos = [
            'nombre'         => trim($_POST['nombre'] ?? ''),
            'ruc'            => trim($_POST['ruc'] ?? ''),
            'telefono'       => trim($_POST['telefono'] ?? ''),
            'cobertura_zona' => trim($_POST['cobertura_zona'] ?? ''),
            'costo_base'     => $_POST['costo_base'] ?? null,
        ];

        $errores = $this->validarDatos($datos);
        if (empty($errores['ruc']) && $this->modelo->existeRuc($datos['ruc'], $id)) {
            $errores['ruc'] = 'Otra agencia ya tiene ese RUC.';
        }
        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, $datos);
        Response::ok('Agencia actualizada correctamente.');
    }

    private function eliminar(): void
    {
        $id = (int) ($_POST['id_agencia'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La agencia no existe.');
        }
        try {
            $this->modelo->eliminar($id);
            Response::ok('Agencia eliminada.');
        } catch (PDOException $e) {
            Response::error(
                'No se puede eliminar: la agencia tiene envios registrados.',
                null,
                409
            );
        }
    }
}
