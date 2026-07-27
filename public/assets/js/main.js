/* ============================================================
   main.js - Utilidades compartidas del panel
   - api(): llamadas fetch a public/api/ con manejo uniforme
   - toast(): notificaciones
   - modal: abrir/cerrar
   - mostrarErroresValidacion(): pinta errores 422 en el formulario
   ============================================================ */

/**
 * Llama a la API del sistema.
 * @param {string} entidad  personas | revistas | ejemplares | agencias |
 *                          suscripciones | envios | auth
 * @param {string} accion   listar | crear | actualizar | ...
 * @param {Object} datos    Pares campo:valor. Con metodo GET van como
 *                          querystring; con POST como FormData.
 * @param {string} metodo   'GET' o 'POST' (por defecto POST)
 * @returns {Promise<{success:boolean, message:string, data:any, status:number}>}
 */
async function api(entidad, accion, datos = {}, metodo = 'POST') {
  // RUTA_BASE la define cada vista antes de cargar main.js
  const base = (typeof RUTA_BASE !== 'undefined' ? RUTA_BASE : './');
  let url = `${base}api/${entidad}.php`;
  const opciones = { method: metodo };

  if (metodo === 'GET') {
    const params = new URLSearchParams({ accion, ...datos });
    url += '?' + params.toString();
  } else {
    const formData = new FormData();
    formData.append('accion', accion);
    for (const [clave, valor] of Object.entries(datos)) {
      formData.append(clave, valor);
    }
    opciones.body = formData;
  }

  try {
    const respuesta = await fetch(url, opciones);

    // Sesion expirada: volver al login.
    // Excepcion: las llamadas de 'auth' tambien responden 401 cuando las
    // credenciales son incorrectas, y ahi queremos MOSTRAR el error,
    // no recargar la pagina.
    if (respuesta.status === 401 && entidad !== 'auth') {
      window.location.href = base + 'login.php';
      return { success: false, message: 'Sesion expirada', data: null, status: 401 };
    }

    const json = await respuesta.json();
    return { ...json, status: respuesta.status };
  } catch (e) {
    toast('No se pudo conectar con el servidor.', 'error');
    return { success: false, message: 'Error de conexion', data: null, status: 0 };
  }
}

/* ------------------------------------------------------------
   Toasts
   ------------------------------------------------------------ */
function toast(mensaje, tipo = 'exito', duracion = 3500) {
  let zona = document.getElementById('zona-toasts');
  if (!zona) {
    zona = document.createElement('div');
    zona.id = 'zona-toasts';
    document.body.appendChild(zona);
  }

  const elemento = document.createElement('div');
  elemento.className = `toast toast--${tipo}`;
  elemento.textContent = mensaje;
  zona.appendChild(elemento);

  setTimeout(() => elemento.remove(), duracion);
}

/* ------------------------------------------------------------
   Modal generico (un solo .modal-fondo por pagina)
   ------------------------------------------------------------ */
function abrirModal(idModal) {
  const modal = document.getElementById(idModal);
  if (modal) modal.classList.add('abierto');
}

function cerrarModal(idModal) {
  const modal = document.getElementById(idModal);
  if (modal) modal.classList.remove('abierto');
}

// Cerrar al hacer clic fuera de la caja del modal
document.addEventListener('click', (evento) => {
  if (evento.target.classList.contains('modal-fondo')) {
    evento.target.classList.remove('abierto');
  }
});

// Cerrar con Escape
document.addEventListener('keydown', (evento) => {
  if (evento.key === 'Escape') {
    document.querySelectorAll('.modal-fondo.abierto')
      .forEach((m) => m.classList.remove('abierto'));
  }
});

/* ------------------------------------------------------------
   Errores de validacion (respuesta 422 del backend)
   ------------------------------------------------------------
   Espera que cada campo del formulario este envuelto en:
     <div class="campo" data-campo="cedula">
       <label>...</label>
       <input ...>
       <p class="error-campo"></p>
     </div>
   ------------------------------------------------------------ */
function limpiarErroresValidacion(formulario) {
  formulario.querySelectorAll('.campo.invalido').forEach((campo) => {
    campo.classList.remove('invalido');
    const error = campo.querySelector('.error-campo');
    if (error) error.textContent = '';
  });
}

function mostrarErroresValidacion(formulario, errores) {
  limpiarErroresValidacion(formulario);
  for (const [nombre, mensaje] of Object.entries(errores)) {
    const campo = formulario.querySelector(`.campo[data-campo="${nombre}"]`);
    if (campo) {
      campo.classList.add('invalido');
      const error = campo.querySelector('.error-campo');
      if (error) error.textContent = mensaje;
    } else {
      toast(mensaje, 'error'); // errores sin campo asociado
    }
  }
}

/* ------------------------------------------------------------
   Utilidades varias
   ------------------------------------------------------------ */
function escapeHtml(texto) {
  const div = document.createElement('div');
  div.textContent = String(texto ?? '');
  return div.innerHTML;
}

function formatearMoneda(valor) {
  return '$' + Number(valor).toFixed(2);
}

function badgeEstado(estado) {
  const etiquetas = {
    en_transito: 'en tránsito',
  };
  const texto = etiquetas[estado] ?? estado;
  return `<span class="badge badge--${escapeHtml(estado)}">${escapeHtml(texto)}</span>`;
}

/**
 * Recoge los valores de un formulario como objeto {name: value}.
 */
function datosFormulario(formulario) {
  const datos = {};
  new FormData(formulario).forEach((valor, clave) => {
    datos[clave] = valor;
  });
  delete datos.accion; // la accion se pasa aparte en api()
  return datos;
}