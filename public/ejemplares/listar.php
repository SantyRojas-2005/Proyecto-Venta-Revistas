<?php
/**
 * public/ejemplares/listar.php
 * CRUD de ejemplares: tabla filtrable por revista + modal crear/editar.
 * El select de revistas se llena desde la API.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Ejemplares';
$rutaBase     = '../';
$paginaActiva = 'ejemplares';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Ediciones publicadas</p>
    <h1>Ejemplares</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Registrar ejemplar</button>
</header>

<div class="barra-filtros">
  <select id="filtro-revista" aria-label="Filtrar por revista">
    <option value="">Todas las revistas</option>
  </select>
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-ejemplares">
    <thead>
      <tr>
        <th>Revista</th>
        <th class="num">Edición</th>
        <th>Fecha de publicación</th>
        <th class="num">Stock</th>
        <th class="num">Precio unitario</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="6" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal crear / editar -->
<div class="modal-fondo" id="modal-ejemplar">
  <div class="modal" role="dialog" aria-labelledby="modal-ejemplar-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-ejemplar-titulo">Registrar ejemplar</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-ejemplar')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-ejemplar" novalidate>
      <input type="hidden" name="id_ejemplar" value="">

      <div class="campo" data-campo="id_revista">
        <label for="e-revista">Revista</label>
        <select id="e-revista" name="id_revista" required>
          <option value="">Seleccionar…</option>
        </select>
        <p class="error-campo"></p>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="numero_edicion">
          <label for="e-edicion">Número de edición</label>
          <input type="number" id="e-edicion" name="numero_edicion" min="1" step="1" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="fecha_publicacion">
          <label for="e-fecha">Fecha de publicación</label>
          <input type="date" id="e-fecha" name="fecha_publicacion" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="stock_disponible">
          <label for="e-stock">Stock disponible</label>
          <input type="number" id="e-stock" name="stock_disponible" min="0" step="1" value="0" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="precio_unitario">
          <label for="e-precio">Precio unitario (USD)</label>
          <input type="number" id="e-precio" name="precio_unitario" min="0.01" step="0.01" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-ejemplar')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla   = document.querySelector('#tabla-ejemplares tbody');
  const formulario    = document.getElementById('form-ejemplar');
  const tituloModal   = document.getElementById('modal-ejemplar-titulo');
  const filtroRevista = document.getElementById('filtro-revista');
  const selectRevista = document.getElementById('e-revista');

  /* ---------- Poblar selects de revistas ---------- */
  async function cargarRevistasEnSelects() {
    const respuesta = await api('revistas', 'listar', {}, 'GET');
    if (!respuesta.success) return;

    const opciones = respuesta.data.map((r) =>
      `<option value="${r.id_revista}">${escapeHtml(r.nombre)}</option>`
    ).join('');

    filtroRevista.innerHTML = '<option value="">Todas las revistas</option>' + opciones;
    selectRevista.innerHTML = '<option value="">Seleccionar…</option>' + opciones;
  }

  /* ---------- Listado ---------- */
  async function cargarEjemplares() {
    const filtros = {};
    if (filtroRevista.value) filtros.id_revista = filtroRevista.value;

    const respuesta = await api('ejemplares', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="6" class="tabla-vacia">No se encontraron ejemplares.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((e) => `
      <tr>
        <td><strong>${escapeHtml(e.nombre_revista)}</strong></td>
        <td class="num">#${e.numero_edicion}</td>
        <td>${escapeHtml(e.fecha_publicacion)}</td>
        <td class="num">${Number(e.stock_disponible) === 0
          ? '<span class="badge badge--pendiente">agotado</span>'
          : e.stock_disponible}</td>
        <td class="num">${formatearMoneda(e.precio_unitario)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="abrirFormularioEditar(${e.id_ejemplar})">Editar</button>
          <button class="btn btn--pequeno btn--peligro" onclick="eliminarEjemplar(${e.id_ejemplar})">Eliminar</button>
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear / Editar ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.id_ejemplar.value = '';
    limpiarErroresValidacion(formulario);
    tituloModal.textContent = 'Registrar ejemplar';
    abrirModal('modal-ejemplar');
  };

  window.abrirFormularioEditar = async function (id) {
    const respuesta = await api('ejemplares', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      return;
    }

    const e = respuesta.data;
    formulario.reset();
    limpiarErroresValidacion(formulario);
    formulario.id_ejemplar.value       = e.id_ejemplar;
    formulario.id_revista.value        = e.id_revista;
    formulario.numero_edicion.value    = e.numero_edicion;
    formulario.fecha_publicacion.value = e.fecha_publicacion;
    formulario.stock_disponible.value  = e.stock_disponible;
    formulario.precio_unitario.value   = e.precio_unitario;

    tituloModal.textContent = 'Editar ejemplar';
    abrirModal('modal-ejemplar');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const datos     = datosFormulario(formulario);
    const esEdicion = datos.id_ejemplar !== '';
    const accion    = esEdicion ? 'actualizar' : 'crear';
    if (!esEdicion) delete datos.id_ejemplar;

    const respuesta = await api('ejemplares', accion, datos);

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-ejemplar');
      cargarEjemplares();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Eliminar ---------- */
  window.eliminarEjemplar = async function (id) {
    if (!confirm('¿Eliminar este ejemplar? Solo es posible si no aparece en envíos.')) return;
    const respuesta = await api('ejemplares', 'eliminar', { id_ejemplar: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarEjemplares();
  };

  /* ---------- Filtro ---------- */
  filtroRevista.addEventListener('change', cargarEjemplares);

  cargarRevistasEnSelects();
  cargarEjemplares();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>