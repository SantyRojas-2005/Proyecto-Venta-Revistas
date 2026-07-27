<?php
/**
 * public/envios/listar.php
 * Listado de envios con filtro por estado, modal de detalle
 * (lineas de detalle_envio) y cambio de estado segun el flujo:
 * pendiente -> en_transito -> entregado | devuelto.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Envíos';
$rutaBase     = '../';
$paginaActiva = 'envios';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Despachos y entregas</p>
    <h1>Envíos</h1>
  </div>
  <a href="crear.php" class="btn btn--primario">Registrar envío</a>
</header>

<div class="barra-filtros">
  <select id="filtro-estado" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <option value="pendiente">Pendientes</option>
    <option value="en_transito">En tránsito</option>
    <option value="entregado">Entregados</option>
    <option value="devuelto">Devueltos</option>
  </select>
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-envios">
    <thead>
      <tr>
        <th>#</th>
        <th>Destinatario</th>
        <th>Agencia</th>
        <th>Envío</th>
        <th>Entrega est.</th>
        <th>Estado</th>
        <th class="num">Costo</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="8" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal de detalle -->
<div class="modal-fondo" id="modal-detalle">
  <div class="modal" role="dialog" aria-labelledby="modal-detalle-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-detalle-titulo">Detalle del envío</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-detalle')" aria-label="Cerrar">×</button>
    </div>
    <div id="contenido-detalle">Cargando…</div>
    <div class="modal__acciones" id="acciones-detalle"></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla = document.querySelector('#tabla-envios tbody');

  /* ---------- Listado ---------- */
  async function cargarEnvios() {
    const filtros = {};
    const estado = document.getElementById('filtro-estado').value;
    if (estado) filtros.estado = estado;

    const respuesta = await api('envios', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="8" class="tabla-vacia">No se encontraron envíos.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((e) => `
      <tr>
        <td>${e.id_envio}</td>
        <td><strong>${escapeHtml(e.destinatario)}</strong></td>
        <td>${escapeHtml(e.nombre_agencia)}</td>
        <td>${escapeHtml(e.fecha_envio)}</td>
        <td>${e.fecha_entrega_estimada ? escapeHtml(e.fecha_entrega_estimada) : '—'}</td>
        <td>${badgeEstado(e.estado_envio)}</td>
        <td class="num">${formatearMoneda(e.costo_total)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="verDetalle(${e.id_envio})">Ver</button>
          ${botonesEstado(e)}
        </td>
      </tr>
    `).join('');
  }

  /**
   * Botones de transicion segun el estado actual:
   * pendiente   -> Despachar (en_transito)
   * en_transito -> Entregar | Devolver
   */
  function botonesEstado(envio) {
    switch (envio.estado_envio) {
      case 'pendiente':
        return `<button class="btn btn--pequeno" onclick="cambiarEstado(${envio.id_envio}, 'en_transito', '¿Marcar el envío como despachado (en tránsito)?')">Despachar</button>`;
      case 'en_transito':
        return `<button class="btn btn--pequeno" onclick="cambiarEstado(${envio.id_envio}, 'entregado', '¿Confirmar la entrega? Se registrará la fecha de hoy.')">Entregar</button>
                <button class="btn btn--pequeno btn--peligro" onclick="cambiarEstado(${envio.id_envio}, 'devuelto', '¿Marcar como devuelto? El stock de sus ejemplares será repuesto.')">Devolver</button>`;
      default:
        return '';
    }
  }

  window.cambiarEstado = async function (id, estado, mensaje) {
    if (!confirm(mensaje)) return;
    const respuesta = await api('envios', 'cambiar_estado', { id_envio: id, estado });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarEnvios();
  };

  /* ---------- Detalle ---------- */
  window.verDetalle = async function (id) {
    document.getElementById('contenido-detalle').innerHTML = 'Cargando…';
    abrirModal('modal-detalle');

    const respuesta = await api('envios', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      cerrarModal('modal-detalle');
      return;
    }

    const e = respuesta.data;
    document.getElementById('modal-detalle-titulo').textContent = `Envío #${e.id_envio}`;

    const filasDetalle = e.detalles.map((d) => `
      <tr>
        <td>${escapeHtml(d.nombre_revista)} #${d.numero_edicion}</td>
        <td class="num">${d.cantidad}</td>
        <td class="num">${formatearMoneda(d.precio_unitario)}</td>
        <td class="num">${formatearMoneda(d.subtotal)}</td>
      </tr>
    `).join('');

    const costoEjemplares = e.detalles.reduce((suma, d) => suma + Number(d.subtotal), 0);

    document.getElementById('contenido-detalle').innerHTML = `
      <p><span class="folio">Destinatario</span><br>
         <strong>${escapeHtml(e.destinatario)}</strong> · ${escapeHtml(e.cedula)} · ${escapeHtml(e.telefono)}</p>
      <p><span class="folio">Dirección de entrega</span><br>${escapeHtml(e.direccion_entrega)}</p>
      <p><span class="folio">Agencia</span><br>${escapeHtml(e.nombre_agencia)}</p>
      <p><span class="folio">Fechas</span><br>
         Envío: ${escapeHtml(e.fecha_envio)}
         · Estimada: ${e.fecha_entrega_estimada ?? '—'}
         · Real: ${e.fecha_entrega_real ?? '—'}</p>
      <p><span class="folio">Estado</span><br>${badgeEstado(e.estado_envio)}
         &nbsp;·&nbsp; registrado por ${escapeHtml(e.registrado_por)}</p>

      <table class="tabla" style="margin-top: 12px;">
        <thead>
          <tr><th>Ejemplar</th><th class="num">Cant.</th><th class="num">P. unit.</th><th class="num">Subtotal</th></tr>
        </thead>
        <tbody>${filasDetalle}</tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="num" style="border-top: 2px solid var(--color-tinta);">Ejemplares</td>
            <td class="num" style="border-top: 2px solid var(--color-tinta);">${formatearMoneda(costoEjemplares)}</td>
          </tr>
          <tr>
            <td colspan="3" class="num">Costo base de la agencia</td>
            <td class="num">${formatearMoneda(Number(e.costo_total) - costoEjemplares)}</td>
          </tr>
          <tr>
            <td colspan="3" class="num"><strong>Total</strong></td>
            <td class="num"><strong>${formatearMoneda(e.costo_total)}</strong></td>
          </tr>
        </tfoot>
      </table>
    `;

    document.getElementById('acciones-detalle').innerHTML =
      `<button class="btn" onclick="cerrarModal('modal-detalle')">Cerrar</button>`;
  };

  /* ---------- Filtro ---------- */
  document.getElementById('filtro-estado').addEventListener('change', cargarEnvios);

  cargarEnvios();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
