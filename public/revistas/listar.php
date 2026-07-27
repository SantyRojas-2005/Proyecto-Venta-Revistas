<?php
/**
 * public/revistas/listar.php
 * CRUD de revistas: tabla con filtros + modal crear/editar.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Revistas';
$rutaBase     = '../';
$paginaActiva = 'revistas';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Catálogo de publicaciones</p>
    <h1>Revistas</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Registrar revista</button>
</header>

<div class="barra-filtros">
  <input type="search" id="filtro-busqueda"
         placeholder="Buscar por nombre o categoría…"
         aria-label="Buscar revistas">
  <select id="filtro-estado" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <option value="activa">Activas</option>
    <option value="descontinuada">Descontinuadas</option>
  </select>
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-revistas">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Categoría</th>
        <th>Periodicidad</th>
        <th class="num">Precio suscripción</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="6" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal crear / editar -->
<div class="modal-fondo" id="modal-revista">
  <div class="modal" role="dialog" aria-labelledby="modal-revista-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-revista-titulo">Registrar revista</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-revista')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-revista" novalidate>
      <input type="hidden" name="id_revista" value="">

      <div class="campo" data-campo="nombre">
        <label for="r-nombre">Nombre</label>
        <input type="text" id="r-nombre" name="nombre" maxlength="100" required>
        <p class="error-campo"></p>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="categoria">
          <label for="r-categoria">Categoría</label>
          <input type="text" id="r-categoria" name="categoria" maxlength="50"
                 placeholder="Ej: Tecnología, Deportes…" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="periodicidad">
          <label for="r-periodicidad">Periodicidad</label>
          <select id="r-periodicidad" name="periodicidad" required>
            <option value="">Seleccionar…</option>
            <option value="semanal">Semanal</option>
            <option value="quincenal">Quincenal</option>
            <option value="mensual">Mensual</option>
          </select>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="precio_suscripcion">
          <label for="r-precio">Precio de suscripción (USD)</label>
          <input type="number" id="r-precio" name="precio_suscripcion"
                 min="0.01" step="0.01" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="estado" id="campo-estado" style="display:none;">
          <label for="r-estado">Estado</label>
          <select id="r-estado" name="estado">
            <option value="activa">Activa</option>
            <option value="descontinuada">Descontinuada</option>
          </select>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-revista')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla = document.querySelector('#tabla-revistas tbody');
  const formulario  = document.getElementById('form-revista');
  const tituloModal = document.getElementById('modal-revista-titulo');
  const campoEstado = document.getElementById('campo-estado');

  const etiquetasPeriodicidad = {
    semanal: 'Semanal', quincenal: 'Quincenal', mensual: 'Mensual',
  };

  /* ---------- Listado ---------- */
  async function cargarRevistas() {
    const filtros = {};
    const busqueda = document.getElementById('filtro-busqueda').value.trim();
    const estado   = document.getElementById('filtro-estado').value;
    if (busqueda) filtros.busqueda = busqueda;
    if (estado)   filtros.estado   = estado;

    const respuesta = await api('revistas', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="6" class="tabla-vacia">No se encontraron revistas.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((r) => `
      <tr>
        <td><strong>${escapeHtml(r.nombre)}</strong></td>
        <td>${escapeHtml(r.categoria)}</td>
        <td>${etiquetasPeriodicidad[r.periodicidad] ?? escapeHtml(r.periodicidad)}</td>
        <td class="num">${formatearMoneda(r.precio_suscripcion)}</td>
        <td>${badgeEstado(r.estado)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="abrirFormularioEditar(${r.id_revista})">Editar</button>
          ${r.estado === 'activa'
            ? `<button class="btn btn--pequeno btn--peligro" onclick="descontinuarRevista(${r.id_revista})">Descontinuar</button>`
            : `<button class="btn btn--pequeno btn--peligro" onclick="eliminarRevista(${r.id_revista})">Eliminar</button>`}
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear / Editar ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.id_revista.value = '';
    limpiarErroresValidacion(formulario);
    tituloModal.textContent = 'Registrar revista';
    campoEstado.style.display = 'none';
    abrirModal('modal-revista');
  };

  window.abrirFormularioEditar = async function (id) {
    const respuesta = await api('revistas', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      return;
    }

    const r = respuesta.data;
    formulario.reset();
    limpiarErroresValidacion(formulario);
    formulario.id_revista.value         = r.id_revista;
    formulario.nombre.value             = r.nombre;
    formulario.categoria.value          = r.categoria;
    formulario.periodicidad.value       = r.periodicidad;
    formulario.precio_suscripcion.value = r.precio_suscripcion;
    formulario.estado.value             = r.estado;

    tituloModal.textContent =
      `Editar revista · ${r.suscriptores_activos} suscriptor(es) activo(s)`;
    campoEstado.style.display = '';
    abrirModal('modal-revista');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const datos     = datosFormulario(formulario);
    const esEdicion = datos.id_revista !== '';
    const accion    = esEdicion ? 'actualizar' : 'crear';
    if (!esEdicion) {
      delete datos.id_revista;
      delete datos.estado;
    }

    const respuesta = await api('revistas', accion, datos);

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-revista');
      cargarRevistas();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Descontinuar / Eliminar ---------- */
  window.descontinuarRevista = async function (id) {
    if (!confirm('¿Marcar esta revista como descontinuada? No admitirá nuevas suscripciones.')) return;
    const respuesta = await api('revistas', 'descontinuar', { id_revista: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarRevistas();
  };

  window.eliminarRevista = async function (id) {
    if (!confirm('¿Eliminar definitivamente esta revista? Esta acción no se puede deshacer.')) return;
    const respuesta = await api('revistas', 'eliminar', { id_revista: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarRevistas();
  };

  /* ---------- Filtros ---------- */
  let temporizador;
  document.getElementById('filtro-busqueda').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(cargarRevistas, 300);
  });
  document.getElementById('filtro-estado').addEventListener('change', cargarRevistas);

  cargarRevistas();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
