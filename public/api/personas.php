<?php
/**
 * public/api/personas.php
 * Punto de entrada AJAX. Delega toda la logica a PersonaController.
 */
require_once __DIR__ . '/../../src/controllers/PersonaController.php';

(new PersonaController())->manejar();
