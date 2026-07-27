<?php
/**
 * public/index.php
 * Dashboard: resumen de envios por estado y ultimos envios registrados.
 */
require_once __DIR__ . '/../src/helpers/Auth.php';
Auth::requerirLogin(0);

$titulo       = 'Inicio';
$rutaBase     = './';
$paginaActiva = 'dashboard';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Panel de control</p>
    <h1>Resumen del sistema</h1>
  </div>
  <a href="envios/crear.php" class="btn btn--primario">Registrar envío</a>
</header>

<section class="grid-stats" aria-label="Envíos por estado">
  <div class="stat stat--acento">
    <p class="folio">Pendientes</p>
    <p class="stat__cifra" id="stat-pendiente">—</p>
  </div>
  <div class="stat">
    <p class="folio">En tránsito</p>
    <p class="stat__cifra" id="stat-en_transito">—</p>
  </div>
  <div class="stat">
    <p class="folio">Entregados</p>
    <p class="stat__cifra" id="stat-entregado">—</p>
  </div>
  <div class="stat">
    <p class="folio">Devueltos</p>
    <p class="stat__cifra" id="stat-devuelto">—</p>
  </div>
</section>

<section class="panel">
  <h2>Últimos envíos</h2>
  <div class="tabla-envoltura" style="border: none;">
    <table class="tabla" id="tabla-ultimos-envios">
      <thead>
        <tr>
          <th>#</th>
          <th>Destinatario</th>
          <th>Agencia</th>
          <th>Fecha de envío</th>
          <th>Estado</th>
          <th class="num">Costo</th>
        </tr>
      </thead>
      <tbody>
        <tr><td colspan="6" class="tabla-vacia">Cargando…</td></tr>
      </tbody>
    </table>
  </div>
  <p style="margin-top: 12px;">
    <a href="envios/listar.php">Ver todos los envíos →</a>
  </p>
</section>

<script>
document.addEventListener('DOMContentLoaded', async function cargarDashboard() {
  // 1) Estadisticas por estado
  const stats = await api('envios', 'estadisticas', {}, 'GET');
  if (stats.success) {
    for (const [estado, total] of Object.entries(stats.data)) {
      const celda = document.getElementById('stat-' + estado);
      if (celda) celda.textContent = total;
    }
  }

  // 2) Ultimos 5 envios
  const envios = await api('envios', 'listar', {}, 'GET');
  const cuerpo = document.querySelector('#tabla-ultimos-envios tbody');

  if (!envios.success || envios.data.length === 0) {
    cuerpo.innerHTML =
      '<tr><td colspan="6" class="tabla-vacia">Aún no hay envíos registrados. ' +
      '<a href="envios/crear.php">Registra el primero</a>.</td></tr>';
    return;
  }

  cuerpo.innerHTML = envios.data.slice(0, 5).map((envio) => `
    <tr>
      <td>${envio.id_envio}</td>
      <td>${escapeHtml(envio.destinatario)}</td>
      <td>${escapeHtml(envio.nombre_agencia)}</td>
      <td>${escapeHtml(envio.fecha_envio)}</td>
      <td>${badgeEstado(envio.estado_envio)}</td>
      <td class="num">${formatearMoneda(envio.costo_total)}</td>
    </tr>
  `).join('');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
