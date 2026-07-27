<?php
/**
 * public/api/revistas.php
 * Punto de entrada AJAX. Delega toda la logica a RevistaController.
 */
require_once __DIR__ . '/../../src/controllers/RevistaController.php';

(new RevistaController())->manejar();
