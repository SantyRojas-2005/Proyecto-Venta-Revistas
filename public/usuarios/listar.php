<?php
/**
 * public/usuarios/listar.php
 * Gestion de cuentas del panel. Solo accesible para administradores:
 * los operadores son redirigidos al inicio.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

if (!Auth::esAdministrador()) {
    header('Location: ../index.php');
    exit;
}

$titulo       = 'Usuarios';
$rutaBase     = '../';
$paginaActiva = 'usuarios';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Cuentas del panel</p>
    <h1>Usuarios</h1>
  </div>
  <button class="btn btn--primario" onclick="abrirFormularioCrear()">Crear usuario</button>
</header>

<div class="tabla-envoltura">
  <table class="tabla" id="tabla-usuarios">
    <thead>
      <tr>
        <th>Usuario</th>
        <th>Nombre completo</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="5" class="tabla-vacia">Cargando…</td></tr>
    </tbody>
  </table>
</div>

<!-- Modal crear / editar -->
<div class="modal-fondo" id="modal-usuario">
  <div class="modal" role="dialog" aria-labelledby="modal-usuario-titulo">
    <div class="modal__cabecera">
      <h3 id="modal-usuario-titulo">Crear usuario</h3>
      <button class="modal__cerrar" onclick="cerrarModal('modal-usuario')" aria-label="Cerrar">×</button>
    </div>

    <form id="form-usuario" novalidate>
      <input type="hidden" name="id_usuario" value="">

      <div class="form-fila">
        <div class="campo" data-campo="nombre_usuario">
          <label for="u-usuario">Nombre de usuario</label>
          <input type="text" id="u-usuario" name="nombre_usuario" maxlength="50"
                 autocomplete="off" required>
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="rol">
          <label for="u-rol">Rol</label>
          <select id="u-rol" name="rol" required>
            <option value="operador">Operador</option>
            <option value="administrador">Administrador</option>
          </select>
          <p class="error-campo"></p>
        </div>
      </div>

      <div class="campo" data-campo="nombre_completo">
        <label for="u-nombre">Nombre completo</label>
        <input type="text" id="u-nombre" name="nombre_completo" maxlength="100" required>
        <p class="error-campo"></p>
      </div>

      <div class="form-fila">
        <div class="campo" data-campo="password" id="campo-password">
          <label for="u-password">Contraseña (mín. 8 caracteres)</label>
          <input type="password" id="u-password" name="password"
                 autocomplete="new-password">
          <p class="error-campo"></p>
        </div>
        <div class="campo" data-campo="estado" id="campo-estado" style="display:none;">
          <label for="u-estado">Estado</label>
          <select id="u-estado" name="estado">
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>
          <p class="error-campo"></p>
        </div>
      </div>

      <p class="texto-secundario" id="nota-password" style="display:none;">
        Deje la contraseña vacía para no cambiarla.
      </p>

      <div class="modal__acciones">
        <button type="button" class="btn" onclick="cerrarModal('modal-usuario')">Cancelar</button>
        <button type="submit" class="btn btn--primario">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const cuerpoTabla  = document.querySelector('#tabla-usuarios tbody');
  const formulario   = document.getElementById('form-usuario');
  const tituloModal  = document.getElementById('modal-usuario-titulo');
  const campoEstado  = document.getElementById('campo-estado');
  const notaPassword = document.getElementById('nota-password');

  /* ---------- Listado ---------- */
  async function cargarUsuarios() {
    const respuesta = await api('usuarios', 'listar', {}, 'GET');

    if (!respuesta.success || respuesta.data.length === 0) {
      cuerpoTabla.innerHTML =
        '<tr><td colspan="5" class="tabla-vacia">No hay usuarios registrados.</td></tr>';
      return;
    }

    cuerpoTabla.innerHTML = respuesta.data.map((u) => `
      <tr>
        <td><strong>${escapeHtml(u.nombre_usuario)}</strong></td>
        <td>${escapeHtml(u.nombre_completo)}</td>
        <td>${escapeHtml(u.rol)}</td>
        <td>${badgeEstado(u.estado)}</td>
        <td class="celda-acciones">
          <button class="btn btn--pequeno" onclick="abrirFormularioEditar(${u.id_usuario})">Editar</button>
          ${u.estado === 'activo'
            ? `<button class="btn btn--pequeno btn--peligro" onclick="desactivarUsuario(${u.id_usuario})">Desactivar</button>`
            : ''}
        </td>
      </tr>
    `).join('');
  }

  /* ---------- Crear / Editar ---------- */
  window.abrirFormularioCrear = function () {
    formulario.reset();
    formulario.id_usuario.value = '';
    limpiarErroresValidacion(formulario);
    tituloModal.textContent = 'Crear usuario';
    campoEstado.style.display = 'none';
    notaPassword.style.display = 'none';
    abrirModal('modal-usuario');
  };

  window.abrirFormularioEditar = async function (id) {
    const respuesta = await api('usuarios', 'obtener', { id }, 'GET');
    if (!respuesta.success) {
      toast(respuesta.message, 'error');
      return;
    }

    const u = respuesta.data;
    formulario.reset();
    limpiarErroresValidacion(formulario);
    formulario.id_usuario.value      = u.id_usuario;
    formulario.nombre_usuario.value  = u.nombre_usuario;
    formulario.nombre_completo.value = u.nombre_completo;
    formulario.rol.value             = u.rol;
    formulario.estado.value          = u.estado;

    tituloModal.textContent = 'Editar usuario';
    campoEstado.style.display = '';
    notaPassword.style.display = '';
    abrirModal('modal-usuario');
  };

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    const datos     = datosFormulario(formulario);
    const esEdicion = datos.id_usuario !== '';
    const password  = datos.password;
    delete datos.password;

    let respuesta;

    if (esEdicion) {
      respuesta = await api('usuarios', 'actualizar', datos);

      // Si escribio una contrasena nueva, cambiarla en una segunda llamada
      if (respuesta.success && password !== '') {
        const rPass = await api('usuarios', 'cambiar_password',
          { id_usuario: datos.id_usuario, password });
        if (!rPass.success) {
          if (rPass.status === 422) mostrarErroresValidacion(formulario, rPass.data);
          else toast(rPass.message, 'error');
          return;
        }
      }
    } else {
      delete datos.id_usuario;
      delete datos.estado;
      datos.password = password;
      respuesta = await api('usuarios', 'crear', datos);
    }

    if (respuesta.success) {
      toast(respuesta.message);
      cerrarModal('modal-usuario');
      cargarUsuarios();
    } else if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      toast(respuesta.message, 'error');
    }
  });

  /* ---------- Desactivar ---------- */
  window.desactivarUsuario = async function (id) {
    if (!confirm('¿Desactivar esta cuenta? El usuario no podrá iniciar sesión.')) return;
    const respuesta = await api('usuarios', 'desactivar', { id_usuario: id });
    respuesta.success ? toast(respuesta.message) : toast(respuesta.message, 'error');
    cargarUsuarios();
  };

  cargarUsuarios();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
