<?php
/**
 * public/suscripciones/listar.php
 * Suscripciones (N:M persona-revista): tabla filtrable por estado
 * + modal de creacion con selects poblados desde la API.
 * La edicion se limita a cancelar (regla de negocio) o eliminar.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Suscripciones';
$rutaBase     = '../';
$paginaActiva = 'suscripciones';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Persona · Revista</p>
    <h1>Suscripciones</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Nueva suscripción</button>
</header>

<div class="barra-filtros">
  <select id="filtro-estado" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <option value="activa">Activas</option>
    <option value="cancelada">Canceladas</option>
    <option value="vencida">Vencidas</option>
  </select>
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-suscripciones">
    <thead>
      <tr>
        <th>Suscriptor</th>
        <th>Revista</th>
        <th>Periodicidad</th>
        <th>Inicio</th>
        <th>Fin</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="7" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal de creacion -->
<div class="modal-fondo" id="modal-suscripcion">
  <div class="modal" role="dialog" aria-labelledby="modal-suscripcion-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-suscripcion-titulo">Nueva suscripción</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-suscripcion')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-suscripcion" novalidate>
      <div class="campo" data-campo="id_persona">
        <label for="s-persona">Persona</label>
        <select id="s-persona" name="id_persona" required>
          <option value="">Seleccionar…</option>
        </select>
        <p class="error-campo"></p>
      </div>

      <div class="campo" data-campo="id_revista">
        <label for="s-revista">Revista (solo activas)</label>
        <select id="s-revista" name="id_revista" required>
          <option value="">Seleccionar…</option>
        </select>
        <p class="error-campo"></p>
      </div>

      <div class="campo" data-campo="fecha_inicio">
        <label for="s-inicio">Fecha de inicio</label>
        <input type="date" id="s-inicio" name="fecha_inicio"
               value="<?= date('Y-m-d') ?>" required>
        <p class="error-campo"></p>
      </div>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-suscripcion')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Registrar suscripción</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla = document.querySelector('#tabla-suscripciones tbody');
  const formulario  = document.getElementById('form-suscripcion');

  const etiquetasPeriodicidad = {
    semanal: 'Semanal', quincenal: 'Quincenal', mensual: 'Mensual',
  };

  /* ---------- Poblar selects del modal ---------- */
  async function cargarSelects() {
    const [personas, revistas] = await Promise.all([
      api('personas', 'listar', { estado: 'activo' }, 'GET'),
      api('revistas', 'listar_activas', {}, 'GET'),
    ]);

    if (personas.success) {
      document.getElementById('s-persona').innerHTML =
        '<option value="">Seleccionar…</option>' +
        personas.data.map((p) =>
          `<option value="${p.id_persona}">${escapeHtml(p.apellidos + ' ' + p.nombres)} — ${escapeHtml(p.cedula)}</option>`
        ).join('');
    }

    if (revistas.success) {
      document.getElementById('s-revista').innerHTML =
        '<option value="">Seleccionar…</option>' +
        revistas.data.map((r) =>
          `<option value="${r.id_revista}">${escapeHtml(r.nombre)} (${etiquetasPeriodicidad[r.periodicidad] ?? r.periodicidad})</option>`
        ).join('');
    }
  }

  /* ---------- Listado ---------- */
  async function cargarSuscripciones() {
    const filtros = {};
    const estado = document.getElementById('filtro-estado').value;
    if (estado) filtros.estado = estado;

    const respuesta = await api('suscripciones', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="7" class="tabla-vacia">No se encontraron suscripciones.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((s) => `
      <tr>
        <td><strong>${escapeHtml(s.apellidos + ' ' + s.nombres)}</strong><br>
            <span class="texto-secundario">${escapeHtml(s.cedula)}</span></td>
        <td>${escapeHtml(s.nombre_revista)}</td>
        <td>${etiquetasPeriodicidad[s.periodicidad] ?? escapeHtml(s.periodicidad)}</td>
        <td>${escapeHtml(s.fecha_inicio)}</td>
        <td>${s.fecha_fin ? escapeHtml(s.fecha_fin) : '—'}</td>
        <td>${badgeEstado(s.estado)}</td>
        <td class="celda-acciones">
          ${s.estado === 'activa'
            ? `<button class="btn btn--pequeno btn--peligro" onclick="cancelarSuscripcion(${s.id_suscripcion})">Cancelar</button>`
            : `<button class="btn btn--pequeno btn--peligro" onclick="eliminarSuscripcion(${s.id_suscripcion})">Eliminar</button>`}
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.fecha_inicio.value = new Date().toISOString().slice(0, 10);
    limpiarErroresValidacion(formulario);
    abrirModal('modal-suscripcion');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const respuesta = await api('suscripciones', 'crear', datosFormulario(formulario));

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-suscripcion');
      cargarSuscripciones();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Cancelar / Eliminar ---------- */
  window.cancelarSuscripcion = async function (id) {
    if (!confirm('¿Cancelar esta suscripción? Se registrará la fecha de hoy como fin.')) return;
    const respuesta = await api('suscripciones', 'cancelar', { id_suscripcion: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarSuscripciones();
  };

  window.eliminarSuscripcion = async function (id) {
    if (!confirm('¿Eliminar definitivamente esta suscripción del historial?')) return;
    const respuesta = await api('suscripciones', 'eliminar', { id_suscripcion: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarSuscripciones();
  };

  /* ---------- Filtro ---------- */
  document.getElementById('filtro-estado').addEventListener('change', cargarSuscripciones);

  cargarSelects();
  cargarSuscripciones();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>