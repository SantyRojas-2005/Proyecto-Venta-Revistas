<?php
/**
 * src/controllers/SuscripcionController.php
 * Acciones: listar, obtener, crear, actualizar, cancelar, eliminar.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Suscripcion.php';
require_once __DIR__ . '/../models/Persona.php';
require_once __DIR__ . '/../models/Revista.php';

class SuscripcionController
{
    private Suscripcion $modelo;

    public function __construct()
    {
        $this->modelo = new Suscripcion();
    }

    public function manejar(): void
    {
        Auth::requerirLoginAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    $estado    = $_GET['estado'] ?? null;
                    $idPersona = isset($_GET['id_persona']) ? (int) $_GET['id_persona'] : null;
                    Response::ok('Suscripciones listadas.', $this->modelo->listar($estado, $idPersona));
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
                case 'cancelar':
                    $this->cancelar();
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
        $suscripcion = $this->modelo->obtenerPorId($id);
        if ($suscripcion === null) {
            Response::notFound('La suscripcion no existe.');
        }
        Response::ok('Suscripcion encontrada.', $suscripcion);
    }

    private function crear(): void
    {
        $idPersona = (int) ($_POST['id_persona'] ?? 0);
        $idRevista = (int) ($_POST['id_revista'] ?? 0);
        $errores = [];

        $personaModel = new Persona();
        $persona = $personaModel->obtenerPorId($idPersona);
        if ($persona === null) {
            $errores['id_persona'] = 'Debe seleccionar una persona valida.';
        } elseif ($persona['estado'] !== 'activo') {
            $errores['id_persona'] = 'La persona esta inactiva.';
        }

        $revistaModel = new Revista();
        $revista = $revistaModel->obtenerPorId($idRevista);
        if ($revista === null) {
            $errores['id_revista'] = 'Debe seleccionar una revista valida.';
        } elseif ($revista['estado'] !== 'activa') {
            $errores['id_revista'] = 'La revista esta descontinuada; no admite nuevas suscripciones.';
        }

        $fechaInicio = trim($_POST['fecha_inicio'] ?? date('Y-m-d'));
        if (!Validator::fecha($fechaInicio)) {
            $errores['fecha_inicio'] = 'La fecha de inicio no es valida.';
        }

        // Regla de negocio: no duplicar suscripcion activa
        if (empty($errores) && $this->modelo->existeActiva($idPersona, $idRevista)) {
            $errores['id_revista'] = 'La persona ya tiene una suscripcion activa a esta revista.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear([
            'id_persona'   => $idPersona,
            'id_revista'   => $idRevista,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => null,
        ]);
        Response::ok('Suscripcion registrada correctamente.', ['id_suscripcion' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_suscripcion'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La suscripcion no existe.');
        }

        $errores = [];
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $fechaFin    = trim($_POST['fecha_fin'] ?? '');
        $estado      = $_POST['estado'] ?? '';

        if (!Validator::fecha($fechaInicio)) {
            $errores['fecha_inicio'] = 'La fecha de inicio no es valida.';
        }
        if ($fechaFin !== '' && !Validator::fecha($fechaFin)) {
            $errores['fecha_fin'] = 'La fecha de fin no es valida.';
        }
        if ($fechaFin !== '' && empty($errores) && $fechaFin < $fechaInicio) {
            $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }
        if (!Validator::enLista($estado, ['activa', 'cancelada', 'vencida'])) {
            $errores['estado'] = 'Estado no valido.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, [
            'id_persona'   => (int) $_POST['id_persona'],
            'id_revista'   => (int) $_POST['id_revista'],
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin !== '' ? $fechaFin : null,
            'estado'       => $estado,
        ]);
        Response::ok('Suscripcion actualizada correctamente.');
    }

    private function cancelar(): void
    {
        $id = (int) ($_POST['id_suscripcion'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La suscripcion no existe.');
        }
        if (!$this->modelo->cancelar($id)) {
            Response::error('Solo se pueden cancelar suscripciones activas.', null, 409);
        }
        Response::ok('Suscripcion cancelada.');
    }

    private function eliminar(): void
    {
        $id = (int) ($_POST['id_suscripcion'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('La suscripcion no existe.');
        }
        $this->modelo->eliminar($id);
        Response::ok('Suscripcion eliminada.');
    }
}
