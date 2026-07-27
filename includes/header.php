<?php
/**
 * includes/header.php
 * Cabecera comun del panel. Antes de incluirlo, cada vista define:
 *   $titulo        - titulo de la pestana del navegador
 *   $rutaBase      - './' desde public/, '../' desde public/{entidad}/
 *   $paginaActiva  - clave para marcar el enlace activo del sidebar
 */
$titulo       = $titulo ?? 'Revistas a Domicilio';
$rutaBase     = $rutaBase ?? './';
$paginaActiva = $paginaActiva ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> — Revistas a Domicilio</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,550;9..144,600&family=Space+Grotesk:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= $rutaBase ?>assets/css/tokens.css">
  <link rel="stylesheet" href="<?= $rutaBase ?>assets/css/base.css">
  <link rel="stylesheet" href="<?= $rutaBase ?>assets/css/components.css">

  <script>const RUTA_BASE = '<?= $rutaBase ?>';</script>
</head>
<body>
<div class="layout">
