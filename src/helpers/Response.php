<?php
/**
 * src/helpers/Response.php
 * Estandariza las respuestas JSON de las llamadas AJAX.
 * Formato: { "success": bool, "message": string, "data": mixed }
 */

class Response
{
    public static function json(bool $success, string $message, $data = null, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(string $message = 'Operacion exitosa', $data = null): void
    {
        self::json(true, $message, $data, 200);
    }

    public static function error(string $message = 'Ocurrio un error', $data = null, int $httpCode = 400): void
    {
        self::json(false, $message, $data, $httpCode);
    }

    /**
     * Respuesta para errores de validacion: data contiene el arreglo
     * campo => mensaje generado por Validator.
     */
    public static function validationError(array $errores): void
    {
        self::json(false, 'Errores de validacion', $errores, 422);
    }

    public static function notFound(string $message = 'Registro no encontrado'): void
    {
        self::json(false, $message, null, 404);
    }
}