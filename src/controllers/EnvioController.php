<?php
/**
 * src/controllers/EnvioController.php
 * Acciones: listar, obtener (con detalles), crear (con detalles en
 * transaccion), cambiar_estado, estadisticas.
 *
 * El formulario de creacion envia los detalles como JSON en el campo
 * 'detalles': [{"id_ejemplar": 2, "cantidad": 1}, ...]
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Envio.php';
require_once __DIR__ . '/../models/Persona.php';
require_once __DIR__ . '/../models/AgenciaTransporte.php';

class EnvioController
{
    private Envio $modelo;

    public function __construct()
    {
        $this->modelo = new Envio();
    }

    public function manejar(): void
    {
        Auth::requerirLoginAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    Response::ok('Envios listados.', $this->modelo->listar($_GET['estado'] ?? null));
                    break;
                case 'obtener':
                    $this->obtener();
                    break;
                case 'crear':
                    $this->crear();
                    break;
                case 'cambiar_estado':
                    $this->cambiarEstado();
                    break;
                case 'estadisticas':
                    Response::ok('Estadisticas de envios.', $this->modelo->contarPorEstado());
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
        $envio = $this->modelo->obtenerPorId($id);
        if ($envio === null) {
            Response::notFound('El envio no existe.');
        }
        $envio['detalles'] = $this->modelo->obtenerDetalles($id);
        Response::ok('Envio encontrado.', $envio);
    }

    private function crear(): void
    {
        $errores = [];

        // --- Validar destinatario ---
        $idPersona = (int) ($_POST['id_persona'] ?? 0);
        $personaModel = new Persona();
        $persona = $personaModel->obtenerPorId($idPersona);
        if ($persona === null) {
            $errores['id_persona'] = 'Debe seleccionar un destinatario valido.';
        } elseif ($persona['estado'] !== 'activo') {
            $errores['id_persona'] = 'El destinatario esta inactivo.';
        }

        // --- Validar agencia ---
        $idAgencia = (int) ($_POST['id_agencia'] ?? 0);
        $agenciaModel = new AgenciaTransporte();
        if ($agenciaModel->obtenerPorId($idAgencia) === null) {
            $errores['id_agencia'] = 'Debe seleccionar una agencia valida.';
        }

        // --- Direccion de entrega (por defecto la de la persona) ---
        $direccion = trim($_POST['direccion_entrega'] ?? '');
        if ($direccion === '' && $persona !== null) {
            $direccion = $persona['direccion'];
        }
        if (!Validator::requerido($direccion)) {
            $errores['direccion_entrega'] = 'La direccion de entrega es obligatoria.';
        }

        // --- Fecha estimada (opcional) ---
        $fechaEstimada = trim($_POST['fecha_entrega_estimada'] ?? '');
        if ($fechaEstimada !== '' && !Validator::fecha($fechaEstimada)) {
            $errores['fecha_entrega_estimada'] = 'La fecha estimada no es valida.';
        }

        // --- Detalles (JSON) ---
        $detallesJson = $_POST['detalles'] ?? '[]';
        $detalles = json_decode($detallesJson, true);
        if (!is_array($detalles) || empty($detalles)) {
            $errores['detalles'] = 'Debe agregar al menos un ejemplar al envio.';
        } else {
            foreach ($detalles as $i => $d) {
                if (!isset($d['id_ejemplar'], $d['cantidad'])
                    || !Validator::enteroPositivo($d['id_ejemplar'])
                    || !Validator::enteroPositivo($d['cantidad'])) {
                    $errores['detalles'] = 'Los detalles del envio tienen un formato invalido (linea ' . ($i + 1) . ').';
                    break;
                }
            }
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        // --- Crear en transaccion; el id_usuario sale de la SESION,
        //     nunca del formulario (trazabilidad confiable) ---
        try {
            $idEnvio = $this->modelo->crearConDetalles([
                'id_persona'             => $idPersona,
                'id_agencia'             => $idAgencia,
                'id_usuario'             => Auth::usuario()['id_usuario'],
                'direccion_entrega'      => $direccion,
                'fecha_entrega_estimada' => $fechaEstimada !== '' ? $fechaEstimada : null,
            ], $detalles);

            Response::ok('Envio registrado correctamente.', ['id_envio' => $idEnvio]);
        } catch (Exception $e) {
            // Mensajes de negocio: stock insuficiente, ejemplar inexistente...
            Response::error($e->getMessage(), null, 409);
        }
    }

    private function cambiarEstado(): void
    {
        $id     = (int) ($_POST['id_envio'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El envio no existe.');
        }

        try {
            $this->modelo->cambiarEstado($id, $estado);
            Response::ok('Estado del envio actualizado a "' . $estado . '".');
        } catch (Exception $e) {
            Response::error($e->getMessage(), null, 409);
        }
    }
}
