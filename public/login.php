<?php
/**
 * public/login.php
 * Pagina de acceso al panel. Si ya hay sesion, va directo al inicio.
 */
require_once __DIR__ . '/../src/helpers/Auth.php';

if (Auth::estaAutenticado()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — Revistas a Domicilio</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,550;9..144,600&family=Space+Grotesk:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/tokens.css">
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/components.css">

  <script>const RUTA_BASE = './';</script>
</head>
<body>

<div class="login-fondo">
  <div class="login-caja">
    <p class="folio">Panel administrativo</p>
    <h1>Revistas a <span>Domicilio</span></h1>

    <form id="form-login" novalidate>
      <div class="campo" data-campo="nombre_usuario">
        <label for="nombre_usuario">Usuario</label>
        <input type="text" id="nombre_usuario" name="nombre_usuario"
               autocomplete="username" required>
        <p class="error-campo"></p>
      </div>

      <div class="campo" data-campo="password">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
        <p class="error-campo"></p>
      </div>

      <div class="campo" data-campo="credenciales">
        <p class="error-campo"></p>
      </div>

      <button type="submit" class="btn btn--primario" style="width: 100%;">
        Iniciar sesión
      </button>
    </form>
  </div>
</div>

<script src="assets/js/main.js"></script>
<script>
document.getElementById('form-login').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const formulario = evento.target;
  limpiarErroresValidacion(formulario);

  const boton = formulario.querySelector('button[type="submit"]');
  boton.disabled = true;

  const respuesta = await api('auth', 'login', datosFormulario(formulario));

  if (respuesta.success) {
    window.location.href = 'index.php';
    return;
  }

  boton.disabled = false;

  if (respuesta.status === 422) {
    mostrarErroresValidacion(formulario, respuesta.data);
  } else {
    mostrarErroresValidacion(formulario, { credenciales: respuesta.message });
  }
});
</script>
</body>
</html>
