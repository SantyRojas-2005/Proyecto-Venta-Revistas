<?php
/**
 * src/models/DetalleEnvio.php
 * Modelo de la tabla detalle_envio.
 *
 * NOTA de diseno: la insercion de detalles se hace dentro de la
 * transaccion de Envio::crearConDetalles() para garantizar atomicidad.
 * Esta clase ofrece consultas puntuales sobre la tabla, utiles para
 * reportes y para la vista de detalle de un envio.
 */

require_once __DIR__ . '/../../config/database.php';

class DetalleEnvio
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*,
                    ej.numero_edicion,
                    r.nombre AS nombre_revista
             FROM detalle_envio d
             INNER JOIN ejemplar ej ON ej.id_ejemplar = d.id_ejemplar
             INNER JOIN revista  r  ON r.id_revista = ej.id_revista
             WHERE d.id_detalle = :id'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return $fila !== false ? $fila : null;
    }

    public function listarPorEnvio(int $idEnvio): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*,
                    ej.numero_edicion, ej.precio_unitario,
                    r.nombre AS nombre_revista
             FROM detalle_envio d
             INNER JOIN ejemplar ej ON ej.id_ejemplar = d.id_ejemplar
             INNER JOIN revista  r  ON r.id_revista = ej.id_revista
             WHERE d.id_envio = :id
             ORDER BY r.nombre, ej.numero_edicion'
        );
        $stmt->bindValue(':id', $idEnvio, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Reporte: ejemplares mas enviados (para dashboard o defensa).
     */
    public function ejemplaresMasEnviados(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.nombre AS nombre_revista, ej.numero_edicion,
                    SUM(d.cantidad) AS total_enviado
             FROM detalle_envio d
             INNER JOIN ejemplar ej ON ej.id_ejemplar = d.id_ejemplar
             INNER JOIN revista  r  ON r.id_revista = ej.id_revista
             GROUP BY d.id_ejemplar, r.nombre, ej.numero_edicion
             ORDER BY total_enviado DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}