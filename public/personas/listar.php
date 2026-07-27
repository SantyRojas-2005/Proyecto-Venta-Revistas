<?php
/**
 * public/personas/listar.php
 * CRUD completo de personas en una sola vista:
 * tabla con filtros + modal para crear/editar + acciones.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Personas';
$rutaBase     = '../';
$paginaActiva = 'personas';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Suscriptores y destinatarios</p>
    <h1>Personas</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Registrar persona</button>
</header>

<div class="barra-filtros">
  <input type="search" id="filtro-busqueda"
         placeholder="Buscar por cédula, nombres o apellidos…"
         aria-label="Buscar personas">
  <select id="filtro-estado" aria-label="Filtrar por estado">
    <option value="">Todos los estados</option>
    <option value="activo">Activos</option>
    <option value="inactivo">Inactivos</option>
  </select>
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-personas">
    <thead>
      <tr>
        <th>Cédula</th>
        <th>Nombre completo</th>
        <th>Dirección</th>
        <th>Teléfono</th>
        <th>Email</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="7" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal crear / editar -->
<div class="modal-fondo" id="modal-persona">
  <div class="modal" role="dialog" aria-labelledby="modal-persona-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-persona-titulo">Registrar persona</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-persona')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-persona" novalidate>
      <input type="hidden" name="id_persona" value="">

      <div class="form-fila">
        <div class="campo" data-campo="cedula">
          <label for="p-cedula">Cédula</label>
          <input type="text" id="p-cedula" name="cedula" maxlength="10" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="telefono">
          <label for="p-telefono">Teléfono</label>
          <input type="text" id="p-telefono" name="telefono" maxlength="15" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="nombres">
          <label for="p-nombres">Nombres</label>
          <input type="text" id="p-nombres" name="nombres" maxlength="100" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="apellidos">
          <label for="p-apellidos">Apellidos</label>
          <input type="text" id="p-apellidos" name="apellidos" maxlength="100" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="campo" data-campo="direccion">
        <label for="p-direccion">Dirección de entrega</label>
        <input type="text" id="p-direccion" name="direccion" maxlength="200" required>
        <p class="error-campo"></p>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="email">
          <label for="p-email">Email</label>
          <input type="email" id="p-email" name="email" maxlength="100" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="estado" id="campo-estado" style="display:none;">
          <label for="p-estado">Estado</label>
          <select id="p-estado" name="estado">
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-persona')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla = document.querySelector('#tabla-personas tbody');
  const formulario  = document.getElementById('form-persona');
  const tituloModal = document.getElementById('modal-persona-titulo');
  const campoEstado = document.getElementById('campo-estado');

  /* ---------- Listado ---------- */
  async function cargarPersonas() {
    const filtros = {};
    const busqueda = document.getElementById('filtro-busqueda').value.trim();
    const estado   = document.getElementById('filtro-estado').value;
    if (busqueda) filtros.busqueda = busqueda;
    if (estado)   filtros.estado   = estado;

    const respuesta = await api('personas', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="7" class="tabla-vacia">No se encontraron personas.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((p) => `
      <tr>
        <td>${escapeHtml(p.cedula)}</td>
        <td>${escapeHtml(p.nombres + ' ' + p.apellidos)}</td>
        <td>${escapeHtml(p.direccion)}</td>
        <td>${escapeHtml(p.telefono)}</td>
        <td>${escapeHtml(p.email)}</td>
        <td>${badgeEstado(p.estado)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="abrirFormularioEditar(${p.id_persona})">Editar</button>
          ${p.estado === 'activo'
            ? `<button class="btn btn--pequeno btn--peligro" onclick="desactivarPersona(${p.id_persona})">Desactivar</button>`
            : `<button class="btn btn--pequeno btn--peligro" onclick="eliminarPersona(${p.id_persona})">Eliminar</button>`}
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear / Editar ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.id_persona.value = '';
    limpiarErroresValidacion(formulario);
    tituloModal.textContent = 'Registrar persona';
    campoEstado.style.display = 'none'; // el estado solo se edita en actualizacion
    abrirModal('modal-persona');
  };

  window.abrirFormularioEditar = async function (id) {
    const respuesta = await api('personas', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      return;
    }

    const p = respuesta.data;
    formulario.reset();
    limpiarErroresValidacion(formulario);
    formulario.id_persona.value = p.id_persona;
    formulario.cedula.value     = p.cedula;
    formulario.nombres.value    = p.nombres;
    formulario.apellidos.value  = p.apellidos;
    formulario.direccion.value  = p.direccion;
    formulario.telefono.value   = p.telefono;
    formulario.email.value      = p.email;
    formulario.estado.value     = p.estado;

    tituloModal.textContent = 'Editar persona';
    campoEstado.style.display = '';
    abrirModal('modal-persona');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const datos    = datosFormulario(formulario);
    const esEdicion = datos.id_persona !== '';
    const accion   = esEdicion ? 'actualizar' : 'crear';
    if (!esEdicion) {
      delete datos.id_persona;
      delete datos.estado;
    }

    const respuesta = await api('personas', accion, datos);

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-persona');
      cargarPersonas();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Desactivar / Eliminar ---------- */
  window.desactivarPersona = async function (id) {
    if (!confirm('¿Desactivar esta persona? No podrá recibir nuevas suscripciones ni envíos.')) return;
    const respuesta = await api('personas', 'desactivar', { id_persona: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarPersonas();
  };

  window.eliminarPersona = async function (id) {
    if (!confirm('¿Eliminar definitivamente esta persona? Esta acción no se puede deshacer.')) return;
    const respuesta = await api('personas', 'eliminar', { id_persona: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarPersonas();
  };

  /* ---------- Filtros ---------- */
  let temporizador;
  document.getElementById('filtro-busqueda').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(cargarPersonas, 300); // debounce
  });
  document.getElementById('filtro-estado').addEventListener('change', cargarPersonas);

  cargarPersonas();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
