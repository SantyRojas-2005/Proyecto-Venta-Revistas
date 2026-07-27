<?php
/**
 * public/api/suscripciones.php
 * Punto de entrada AJAX. Delega toda la logica a SuscripcionController.
 */
require_once __DIR__ . '/../../src/controllers/SuscripcionController.php';

(new SuscripcionController())->manejar();
