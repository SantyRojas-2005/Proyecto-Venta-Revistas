<?php
/**
 * includes/sidebar.php
 * Barra lateral del panel. Usa $rutaBase y $paginaActiva definidos
 * por la vista, y Auth::usuario() para mostrar la sesion actual.
 */
$usuarioSesion = Auth::usuario();

$enlaces = [
    'dashboard'     => ['Inicio',        'index.php'],
    'personas'      => ['Personas',      'personas/listar.php'],
    'revistas'      => ['Revistas',      'revistas/listar.php'],
    'ejemplares'    => ['Ejemplares',    'ejemplares/listar.php'],
    'suscripciones' => ['Suscripciones', 'suscripciones/listar.php'],
    'agencias'      => ['Agencias',      'agencias/listar.php'],
    'envios'        => ['Envíos',        'envios/listar.php'],
];

// La gestion de cuentas solo es visible para administradores
if (Auth::esAdministrador()) {
    $enlaces['usuarios'] = ['Usuarios', 'usuarios/listar.php'];
}
?>
<aside class="sidebar">
  <div class="sidebar__marca">
    Revistas<br>a <span>Domicilio</span>
  </div>

  <nav class="sidebar__nav" aria-label="Navegación principal">
    <?php foreach ($enlaces as $clave => [$etiqueta, $ruta]): ?>
      <a href="<?= $rutaBase . $ruta ?>"
         class="<?= $paginaActiva === $clave ? 'activo' : '' ?>">
        <?= $etiqueta ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__usuario">
    <strong><?= htmlspecialchars($usuarioSesion['nombre_completo'] ?? '') ?></strong>
    <?= htmlspecialchars($usuarioSesion['rol'] ?? '') ?>
    <p style="margin-top: 8px;">
      <a href="<?= $rutaBase ?>logout.php">Cerrar sesión</a>
    </p>
  </div>
</aside>

<main class="contenido">
