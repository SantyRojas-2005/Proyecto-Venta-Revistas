<?php
/**
 * public/envios/crear.php
 * Formulario maestro-detalle: cabecera del envio (persona, agencia,
 * direccion, fecha estimada) + lineas dinamicas de ejemplares con
 * calculo de total en vivo. Los detalles viajan como JSON al backend,
 * que los procesa en una transaccion.
 */
require_once __DIR__ . '/../../src/helpers/Auth.php';
Auth::requerirLogin(1);

$titulo       = 'Registrar envío';
$rutaBase     = '../';
$paginaActiva = 'envios';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<header class="cabecera-pagina">
  <div>
    <p class="folio">Envíos</p>
    <h1>Registrar envío</h1>
  </div>
  <a href="listar.php" class="btn">← Volver al listado</a>
</header>

<form id="form-envio" novalidate>

  <section class="panel">
    <h3>Datos del envío</h3>

    <div class="form-fila">
      <div class="campo" data-campo="id_persona">
        <label for="env-persona">Destinatario</label>
        <select id="env-persona" name="id_persona" required>
          <option value="">Seleccionar…</option>
        </select>
        <p class="error-campo"></p>
      </div>
      <div class="campo" data-campo="id_agencia">
        <label for="env-agencia">Agencia de transporte</label>
        <select id="env-agencia" name="id_agencia" required>
          <option value="">Seleccionar…</option>
        </select>
        <p class="error-campo"></p>
      </div>
    </div>

    <div class="campo" data-campo="direccion_entrega">
      <label for="env-direccion">Dirección de entrega</label>
      <input type="text" id="env-direccion" name="direccion_entrega" maxlength="200"
             placeholder="Se completa con la dirección del destinatario al seleccionarlo">
      <p class="error-campo"></p>
    </div>

    <div class="form-fila">
      <div class="campo" data-campo="fecha_entrega_estimada">
        <label for="env-estimada">Fecha estimada de entrega (opcional)</label>
        <input type="date" id="env-estimada" name="fecha_entrega_estimada">
        <p class="error-campo"></p>
      </div>
      <div class="campo">
        <label>Costo base de la agencia</label>
        <input type="text" id="env-costo-base" value="—" disabled>
      </div>
    </div>
  </section>

  <section class="panel">
    <h3>Ejemplares del envío</h3>

    <div class="campo" data-campo="detalles">
      <p class="error-campo"></p>
    </div>

    <div class="tabla-envoltura" style="border: none;">
      <table class="tabla" id="tabla-lineas">
        <thead>
          <tr>
            <th style="width: 45%;">Ejemplar</th>
            <th class="num">Stock</th>
            <th class="num" style="width: 110px;">Cantidad</th>
            <th class="num">P. unitario</th>
            <th class="num">Subtotal</th>
            <th></th>
          </tr>
        </thead>
        <tbody><!-- lineas dinamicas --></tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="num" style="border-top: 2px solid var(--color-tinta);">Ejemplares</td>
            <td class="num" id="total-ejemplares" style="border-top: 2px solid var(--color-tinta);">$0.00</td>
            <td style="border-top: 2px solid var(--color-tinta);"></td>
          </tr>
          <tr>
            <td colspan="4" class="num">Costo base</td>
            <td class="num" id="total-base">$0.00</td>
            <td></td>
          </tr>
          <tr>
            <td colspan="4" class="num"><strong>Total del envío</strong></td>
            <td class="num"><strong id="total-envio">$0.00</strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <p style="margin-top: 16px;">
      <button type="button" class="btn btn--pequeno" onclick="agregarLinea()">+ Agregar ejemplar</button>
    </p>
  </section>

  <div style="display: flex; justify-content: flex-end; gap: 12px;">
    <a href="listar.php" class="btn">Cancelar</a>
    <button type="submit" class="btn btn--primario">Registrar envío</button>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const formulario   = document.getElementById('form-envio');
  const selPersona   = document.getElementById('env-persona');
  const selAgencia   = document.getElementById('env-agencia');
  const inpDireccion = document.getElementById('env-direccion');
  const cuerpoLineas = document.querySelector('#tabla-lineas tbody');

  let personas   = [];  // cache de datos para autocompletar direccion
  let agencias   = [];  // cache para costo base
  let ejemplares = [];  // ejemplares con stock disponible

  /* ---------- Carga inicial de catalogos ---------- */
  (async function inicializar() {
    const [rPersonas, rAgencias, rEjemplares] = await Promise.all([
      api('personas',   'listar', { estado: 'activo' }, 'GET'),
      api('agencias',   'listar', {}, 'GET'),
      api('ejemplares', 'listar_con_stock', {}, 'GET'),
    ]);

    if (rPersonas.success) {
      personas = rPersonas.data;
      selPersona.innerHTML = '<option value="">Seleccionar…</option>' +
        personas.map((p) =>
          `<option value="${p.id_persona}">${escapeHtml(p.apellidos + ' ' + p.nombres)} — ${escapeHtml(p.cedula)}</option>`
        ).join('');
    }

    if (rAgencias.success) {
      agencias = rAgencias.data;
      selAgencia.innerHTML = '<option value="">Seleccionar…</option>' +
        agencias.map((a) =>
          `<option value="${a.id_agencia}">${escapeHtml(a.nombre)} — ${escapeHtml(a.cobertura_zona)}</option>`
        ).join('');
    }

    if (rEjemplares.success) {
      ejemplares = rEjemplares.data;
    }

    agregarLinea(); // el envio arranca con una linea vacia
  })();

  /* ---------- Autocompletar direccion y costo base ---------- */
  selPersona.addEventListener('change', () => {
    const persona = personas.find((p) => p.id_persona == selPersona.value);
    if (persona) inpDireccion.value = persona.direccion;
  });

  selAgencia.addEventListener('change', () => {
    recalcularTotales();
  });

  /* ---------- Lineas dinamicas ---------- */
  window.agregarLinea = function () {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>
        <select class="linea-ejemplar" style="width: 100%; font-family: var(--fuente-cuerpo); font-size: 14px; padding: 6px 8px; background: var(--color-blanco); border: var(--borde-fino); border-radius: var(--radio);">
          <option value="">Seleccionar…</option>
          ${ejemplares.map((e) =>
            `<option value="${e.id_ejemplar}" data-stock="${e.stock_disponible}" data-precio="${e.precio_unitario}">
               ${escapeHtml(e.nombre_revista)} #${e.numero_edicion}
             </option>`).join('')}
        </select>
      </td>
      <td class="num linea-stock">—</td>
      <td class="num">
        <input type="number" class="linea-cantidad" min="1" step="1" value="1"
               style="width: 90px; text-align: right; font-family: var(--fuente-cuerpo); font-size: 14px; padding: 6px 8px; background: var(--color-blanco); border: var(--borde-fino); border-radius: var(--radio);">
      </td>
      <td class="num linea-precio">—</td>
      <td class="num linea-subtotal">$0.00</td>
      <td><button type="button" class="btn btn--pequeno btn--peligro" onclick="this.closest('tr').remove(); recalcularTotales();">Quitar</button></td>
    `;
    cuerpoLineas.appendChild(fila);

    fila.querySelector('.linea-ejemplar').addEventListener('change', () => actualizarLinea(fila));
    fila.querySelector('.linea-cantidad').addEventListener('input', () => actualizarLinea(fila));
  };

  function actualizarLinea(fila) {
    const select   = fila.querySelector('.linea-ejemplar');
    const opcion   = select.selectedOptions[0];
    const cantidad = parseInt(fila.querySelector('.linea-cantidad').value, 10) || 0;

    if (!opcion || !opcion.value) {
      fila.querySelector('.linea-stock').textContent    = '—';
      fila.querySelector('.linea-precio').textContent   = '—';
      fila.querySelector('.linea-subtotal').textContent = '$0.00';
      recalcularTotales();
      return;
    }

    const stock  = parseInt(opcion.dataset.stock, 10);
    const precio = parseFloat(opcion.dataset.precio);

    fila.querySelector('.linea-stock').textContent  = stock;
    fila.querySelector('.linea-precio').textContent = formatearMoneda(precio);

    // Aviso visual si pide mas de lo disponible (el backend igualmente lo bloquea)
    const inputCantidad = fila.querySelector('.linea-cantidad');
    inputCantidad.style.borderColor = (cantidad > stock) ? 'var(--color-error)' : '';

    fila.querySelector('.linea-subtotal').textContent =
      formatearMoneda(cantidad > 0 ? cantidad * precio : 0);

    recalcularTotales();
  }

  window.recalcularTotales = function () {
    let totalEjemplares = 0;
    cuerpoLineas.querySelectorAll('tr').forEach((fila) => {
      const opcion   = fila.querySelector('.linea-ejemplar').selectedOptions[0];
      const cantidad = parseInt(fila.querySelector('.linea-cantidad').value, 10) || 0;
      if (opcion && opcion.value && cantidad > 0) {
        totalEjemplares += cantidad * parseFloat(opcion.dataset.precio);
      }
    });

    const agencia   = agencias.find((a) => a.id_agencia == selAgencia.value);
    const costoBase = agencia ? parseFloat(agencia.costo_base) : 0;

    document.getElementById('env-costo-base').value =
      agencia ? formatearMoneda(costoBase) : '—';
    document.getElementById('total-ejemplares').textContent = formatearMoneda(totalEjemplares);
    document.getElementById('total-base').textContent       = formatearMoneda(costoBase);
    document.getElementById('total-envio').textContent      = formatearMoneda(totalEjemplares + costoBase);
  };

  /* ---------- Envio del formulario ---------- */
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErroresValidacion(formulario);

    // Recoger las lineas validas
    const detalles = [];
    let hayDuplicados = false;
    const vistos = new Set();

    cuerpoLineas.querySelectorAll('tr').forEach((fila) => {
      const idEjemplar = fila.querySelector('.linea-ejemplar').value;
      const cantidad   = parseInt(fila.querySelector('.linea-cantidad').value, 10) || 0;
      if (idEjemplar && cantidad > 0) {
        if (vistos.has(idEjemplar)) hayDuplicados = true;
        vistos.add(idEjemplar);
        detalles.push({ id_ejemplar: parseInt(idEjemplar, 10), cantidad });
      }
    });

    if (detalles.length === 0) {
      mostrarErroresValidacion(formulario,
        { detalles: 'Agregue al menos un ejemplar con cantidad válida.' });
      return;
    }
    if (hayDuplicados) {
      mostrarErroresValidacion(formulario,
        { detalles: 'Hay ejemplares repetidos: consolide las cantidades en una sola línea.' });
      return;
    }

    const datos = datosFormulario(formulario);
    datos.detalles = JSON.stringify(detalles);

    const boton = formulario.querySelector('button[type="submit"]');
    boton.disabled = true;

    const respuesta = await api('envios', 'crear', datos);

    if (respuesta.success) {
      toast(respuesta.message);
      window.location.href = 'listar.php';
      return;
    }

    boton.disabled = false;

    if (respuesta.status === 422) {
      mostrarErroresValidacion(formulario, respuesta.data);
    } else {
      // 409: stock insuficiente u otra regla de negocio de la transaccion
      toast(respuesta.message, 'error', 6000);
    }
  });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
