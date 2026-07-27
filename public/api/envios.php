<?php
/**
 * public/api/envios.php
 * Punto de entrada AJAX. Delega toda la logica a EnvioController.
 */
require_once __DIR__ . '/../../src/controllers/EnvioController.php';

(new EnvioController())->manejar();
