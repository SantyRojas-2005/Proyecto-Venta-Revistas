<?php
/**
 * public/logout.php
 * Cierra la sesion y vuelve al login.
 */
require_once __DIR__ . '/../src/helpers/Auth.php';

Auth::logout();
header('Location: login.php');
exit;
