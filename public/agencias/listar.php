<?php
/**
 * public/agencias/listar.php
 * CRUD de agencias de transporte.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Agencias';
$rutaBase     = '../';
$paginaActiva = 'agencias';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Empresas de entrega</p>
    <h1>Agencias de transporte</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Registrar agencia</button>
</header>

<div class="barra-filtros">
  <input type="search" id="filtro-busqueda"
         placeholder="Buscar por nombre o zona de cobertura…"
         aria-label="Buscar agencias">
</div>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-agencias">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>RUC</th>
        <th>Teléfono</th>
        <th>Zona de cobertura</th>
        <th class="num">Costo base</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="6" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal crear / editar -->
<div class="modal-fondo" id="modal-agencia">
  <div class="modal" role="dialog" aria-labelledby="modal-agencia-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-agencia-titulo">Registrar agencia</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-agencia')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-agencia" novalidate>
      <input type="hidden" name="id_agencia" value="">

      <div class="campo" data-campo="nombre">
        <label for="a-nombre">Nombre</label>
        <input type="text" id="a-nombre" name="nombre" maxlength="100" required>
        <p class="error-campo"></p>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="ruc">
          <label for="a-ruc">RUC</label>
          <input type="text" id="a-ruc" name="ruc" maxlength="13"
                 placeholder="13 dígitos, termina en 001" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="telefono">
          <label for="a-telefono">Teléfono</label>
          <input type="text" id="a-telefono" name="telefono" maxlength="15" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="cobertura_zona">
          <label for="a-cobertura">Zona de cobertura</label>
          <input type="text" id="a-cobertura" name="cobertura_zona" maxlength="100"
                 placeholder="Ej: Quito urbano, Nacional…" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="costo_base">
          <label for="a-costo">Costo base (USD)</label>
          <input type="number" id="a-costo" name="costo_base" min="0.01" step="0.01" required>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-agencia')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla = document.querySelector('#tabla-agencias tbody');
  const formulario  = document.getElementById('form-agencia');
  const tituloModal = document.getElementById('modal-agencia-titulo');

  /* ---------- Listado ---------- */
  async function cargarAgencias() {
    const filtros = {};
    const busqueda = document.getElementById('filtro-busqueda').value.trim();
    if (busqueda) filtros.busqueda = busqueda;

    const respuesta = await api('agencias', 'listar', filtros, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="6" class="tabla-vacia">No se encontraron agencias.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((a) => `
      <tr>
        <td><strong>${escapeHtml(a.nombre)}</strong></td>
        <td>${escapeHtml(a.ruc)}</td>
        <td>${escapeHtml(a.telefono)}</td>
        <td>${escapeHtml(a.cobertura_zona)}</td>
        <td class="num">${formatearMoneda(a.costo_base)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="abrirFormularioEditar(${a.id_agencia})">Editar</button>
          <button class="btn btn--pequeno btn--peligro" onclick="eliminarAgencia(${a.id_agencia})">Eliminar</button>
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear / Editar ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.id_agencia.value = '';
    limpiarErroresValidacion(formulario);
    tituloModal.textContent = 'Registrar agencia';
    abrirModal('modal-agencia');
  };

  window.abrirFormularioEditar = async function (id) {
    const respuesta = await api('agencias', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      return;
    }

    const a = respuesta.data;
    formulario.reset();
    limpiarErroresValidacion(formulario);
    formulario.id_agencia.value     = a.id_agencia;
    formulario.nombre.value         = a.nombre;
    formulario.ruc.value            = a.ruc;
    formulario.telefono.value       = a.telefono;
    formulario.cobertura_zona.value = a.cobertura_zona;
    formulario.costo_base.value     = a.costo_base;

    tituloModal.textContent = 'Editar agencia';
    abrirModal('modal-agencia');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const datos     = datosFormulario(formulario);
    const esEdicion = datos.id_agencia !== '';
    const accion    = esEdicion ? 'actualizar' : 'crear';
    if (!esEdicion) delete datos.id_agencia;

    const respuesta = await api('agencias', accion, datos);

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-agencia');
      cargarAgencias();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Eliminar ---------- */
  window.eliminarAgencia = async function (id) {
    if (!confirm('¿Eliminar esta agencia? Solo es posible si no tiene envíos registrados.')) return;
    const respuesta = await api('agencias', 'eliminar', { id_agencia: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarAgencias();
  };

  /* ---------- Filtro ---------- */
  let temporizador;
  document.getElementById('filtro-busqueda').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(cargarAgencias, 300);
  });

  cargarAgencias();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>