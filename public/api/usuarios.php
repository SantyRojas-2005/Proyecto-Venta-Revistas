<?php
/**
 * public/api/usuarios.php
 * Punto de entrada AJAX. Delega toda la logica a UsuarioController.
 */
require_once __DIR__ . '/../../src/controllers/UsuarioController.php';

(new UsuarioController())->manejar();
