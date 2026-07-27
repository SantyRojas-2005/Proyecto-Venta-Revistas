<?php
/**
 * public/api/auth.php
 * Punto de entrada AJAX. Delega toda la logica a AuthController.
 */
require_once __DIR__ . '/../../src/controllers/AuthController.php';

(new AuthController())->manejar();
