<?php
/**
 * public/api/ejemplares.php
 * Punto de entrada AJAX. Delega toda la logica a EjemplarController.
 */
require_once __DIR__ . '/../../src/controllers/EjemplarController.php';

(new EjemplarController())->manejar();
