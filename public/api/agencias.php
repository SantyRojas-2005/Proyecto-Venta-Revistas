<?php
/**
 * public/api/agencias.php
 * Punto de entrada AJAX. Delega toda la logica a AgenciaController.
 */
require_once __DIR__ . '/../../src/controllers/AgenciaController.php';

(new AgenciaController())->manejar();
