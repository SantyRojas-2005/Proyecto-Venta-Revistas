<?php
/**
 * src/helpers/Validator.php
 * Validaciones reutilizables del lado del servidor.
 * Todos los metodos son estaticos y devuelven true/false,
 * salvo los que arman listas de errores.
 */

class Validator
{
    /**
     * Valida una cedula ecuatoriana (10 digitos, algoritmo modulo 10).
     */
    public static function cedulaEcuatoriana(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) {
            return false;
        }

        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) {
            return false; // 30 = ecuatorianos registrados en el exterior
        }

        $tercerDigito = (int) $cedula[2];
        if ($tercerDigito > 5) {
            return false; // 6 en adelante corresponde a RUC juridicos/publicos
        }

        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $cedula[$i] * $coeficientes[$i];
            if ($valor >= 10) {
                $valor -= 9;
            }
            $suma += $valor;
        }

        $verificador = (10 - ($suma % 10)) % 10;
        return $verificador === (int) $cedula[9];
    }

    /**
     * Valida un RUC ecuatoriano basico (13 digitos, termina en 001).
     */
    public static function ruc(string $ruc): bool
    {
        return preg_match('/^\d{13}$/', $ruc) === 1
            && substr($ruc, 10, 3) === '001';
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Telefono ecuatoriano: fijo (0 + 8 digitos) o celular (09 + 8 digitos).
     * Acepta entre 7 y 10 digitos para flexibilidad academica.
     */
    public static function telefono(string $telefono): bool
    {
        return preg_match('/^\d{7,10}$/', $telefono) === 1;
    }

    public static function requerido(?string $valor): bool
    {
        return $valor !== null && trim($valor) !== '';
    }

    public static function longitudMaxima(string $valor, int $max): bool
    {
        return mb_strlen($valor) <= $max;
    }

    public static function decimalPositivo($valor): bool
    {
        return is_numeric($valor) && (float) $valor > 0;
    }

    public static function enteroNoNegativo($valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_INT) !== false && (int) $valor >= 0;
    }

    public static function enteroPositivo($valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_INT) !== false && (int) $valor > 0;
    }

    /**
     * Fecha en formato YYYY-MM-DD valida.
     */
    public static function fecha(string $fecha): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Verifica que un valor este dentro de un conjunto permitido (ENUMs).
     */
    public static function enLista($valor, array $permitidos): bool
    {
        return in_array($valor, $permitidos, true);
    }

    /**
     * Valida los campos de una persona y devuelve un arreglo de errores
     * (vacio si todo es valido).
     */
    public static function validarPersona(array $datos): array
    {
        $errores = [];

        if (!self::requerido($datos['cedula'] ?? null)) {
            $errores['cedula'] = 'La cedula es obligatoria.';
        } elseif (!self::cedulaEcuatoriana($datos['cedula'])) {
            $errores['cedula'] = 'La cedula no es valida.';
        }

        if (!self::requerido($datos['nombres'] ?? null)) {
            $errores['nombres'] = 'Los nombres son obligatorios.';
        }

        if (!self::requerido($datos['apellidos'] ?? null)) {
            $errores['apellidos'] = 'Los apellidos son obligatorios.';
        }

        if (!self::requerido($datos['direccion'] ?? null)) {
            $errores['direccion'] = 'La direccion es obligatoria.';
        }

        if (!self::requerido($datos['telefono'] ?? null)) {
            $errores['telefono'] = 'El telefono es obligatorio.';
        } elseif (!self::telefono($datos['telefono'])) {
            $errores['telefono'] = 'El telefono debe tener entre 7 y 10 digitos.';
        }

        if (!self::requerido($datos['email'] ?? null)) {
            $errores['email'] = 'El email es obligatorio.';
        } elseif (!self::email($datos['email'])) {
            $errores['email'] = 'El email no es valido.';
        }

        return $errores;
    }

    /**
     * Valida los campos de una revista.
     */
    public static function validarRevista(array $datos): array
    {
        $errores = [];

        if (!self::requerido($datos['nombre'] ?? null)) {
            $errores['nombre'] = 'El nombre es obligatorio.';
        }

        if (!self::requerido($datos['categoria'] ?? null)) {
            $errores['categoria'] = 'La categoria es obligatoria.';
        }

        if (!self::enLista($datos['periodicidad'] ?? '', ['semanal', 'quincenal', 'mensual'])) {
            $errores['periodicidad'] = 'La periodicidad debe ser semanal, quincenal o mensual.';
        }

        if (!self::decimalPositivo($datos['precio_suscripcion'] ?? null)) {
            $errores['precio_suscripcion'] = 'El precio debe ser un numero mayor a 0.';
        }

        return $errores;
    }
}