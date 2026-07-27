<?php
/**
 * src/controllers/EjemplarController.php
 * Acciones: listar, listar_con_stock, obtener, crear, actualizar, eliminar.
 */

require_once __DIR__ . '/../helpers/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../models/Ejemplar.php';
require_once __DIR__ . '/../models/Revista.php';

class EjemplarController
{
    private Ejemplar $modelo;

    public function __construct()
    {
        $this->modelo = new Ejemplar();
    }

    public function manejar(): void
    {
        Auth::requerirLoginAjax();
        $accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

        try {
            switch ($accion) {
                case 'listar':
                    $idRevista = isset($_GET['id_revista']) ? (int) $_GET['id_revista'] : null;
                    Response::ok('Ejemplares listados.', $this->modelo->listar($idRevista));
                    break;
                case 'listar_con_stock':
                    Response::ok('Ejemplares con stock.', $this->modelo->listarConStock());
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
        $ejemplar = $this->modelo->obtenerPorId($id);
        if ($ejemplar === null) {
            Response::notFound('El ejemplar no existe.');
        }
        Response::ok('Ejemplar encontrado.', $ejemplar);
    }

    private function validarDatos(array $datos): array
    {
        $errores = [];

        if (!Validator::enteroPositivo($datos['id_revista'])) {
            $errores['id_revista'] = 'Debe seleccionar una revista.';
        } else {
            $revistaModel = new Revista();
            if ($revistaModel->obtenerPorId((int) $datos['id_revista']) === null) {
                $errores['id_revista'] = 'La revista seleccionada no existe.';
            }
        }

        if (!Validator::enteroPositivo($datos['numero_edicion'])) {
            $errores['numero_edicion'] = 'El numero de edicion debe ser un entero mayor a 0.';
        }

        if (!Validator::requerido($datos['fecha_publicacion'])
            || !Validator::fecha($datos['fecha_publicacion'])) {
            $errores['fecha_publicacion'] = 'La fecha de publicacion no es valida (YYYY-MM-DD).';
        }

        if (!Validator::enteroNoNegativo($datos['stock_disponible'])) {
            $errores['stock_disponible'] = 'El stock debe ser un entero mayor o igual a 0.';
        }

        if (!Validator::decimalPositivo($datos['precio_unitario'])) {
            $errores['precio_unitario'] = 'El precio unitario debe ser mayor a 0.';
        }

        return $errores;
    }

    private function crear(): void
    {
        $datos = [
            'id_revista'        => $_POST['id_revista'] ?? null,
            'numero_edicion'    => $_POST['numero_edicion'] ?? null,
            'fecha_publicacion' => trim($_POST['fecha_publicacion'] ?? ''),
            'stock_disponible'  => $_POST['stock_disponible'] ?? 0,
            'precio_unitario'   => $_POST['precio_unitario'] ?? null,
        ];

        $errores = $this->validarDatos($datos);

        if (empty($errores['numero_edicion']) && empty($errores['id_revista'])
            && $this->modelo->existeEdicion((int) $datos['id_revista'], (int) $datos['numero_edicion'])) {
            $errores['numero_edicion'] = 'Esa edicion ya esta registrada para esta revista.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $id = $this->modelo->crear($datos);
        Response::ok('Ejemplar registrado correctamente.', ['id_ejemplar' => $id]);
    }

    private function actualizar(): void
    {
        $id = (int) ($_POST['id_ejemplar'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El ejemplar no existe.');
        }

        $datos = [
            'id_revista'        => $_POST['id_revista'] ?? null,
            'numero_edicion'    => $_POST['numero_edicion'] ?? null,
            'fecha_publicacion' => trim($_POST['fecha_publicacion'] ?? ''),
            'stock_disponible'  => $_POST['stock_disponible'] ?? 0,
            'precio_unitario'   => $_POST['precio_unitario'] ?? null,
        ];

        $errores = $this->validarDatos($datos);

        if (empty($errores['numero_edicion']) && empty($errores['id_revista'])
            && $this->modelo->existeEdicion((int) $datos['id_revista'], (int) $datos['numero_edicion'], $id)) {
            $errores['numero_edicion'] = 'Esa edicion ya esta registrada para esta revista.';
        }

        if (!empty($errores)) {
            Response::validationError($errores);
        }

        $this->modelo->actualizar($id, $datos);
        Response::ok('Ejemplar actualizado correctamente.');
    }

    private function eliminar(): void
    {
        $id = (int) ($_POST['id_ejemplar'] ?? 0);
        if ($this->modelo->obtenerPorId($id) === null) {
            Response::notFound('El ejemplar no existe.');
        }
        try {
            $this->modelo->eliminar($id);
            Response::ok('Ejemplar eliminado.');
        } catch (PDOException $e) {
            Response::error(
                'No se puede eliminar: el ejemplar aparece en envios registrados.',
                null,
                409
            );
        }
    }
}
