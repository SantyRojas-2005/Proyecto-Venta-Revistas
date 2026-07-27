# Sistema de Envío de Revistas a Domicilio — Guía para la defensa

Proyecto académico en PHP nativo + PDO sobre XAMPP (Apache + MySQL/MariaDB).
Sin frameworks. Base de datos `revistas_domicilio` con 8 tablas en 3FN.

---

## 1. Arquitectura en tres capas

```
Navegador (public/)  →  API (public/api/)  →  Controlador (src/controllers/)
                                              →  Modelo (src/models/)  →  PDO  →  MySQL
```

- **public/** es lo único expuesto al navegador. Las vistas solo pintan HTML
  y hacen fetch a `public/api/*.php`.
- Cada archivo de `api/` tiene 3 líneas: incluye e instancia su controlador.
- **Controladores**: reciben `$_POST/$_GET`, validan con `Validator`,
  llaman al modelo y responden JSON uniforme con `Response`
  (`{success, message, data}` + código HTTP semántico).
- **Modelos**: una clase por tabla; toda consulta usa sentencias preparadas.
- `src/`, `config/`, `database/` e `includes/` tienen `.htaccess` con
  `Require all denied`: si alguien pide `http://.../src/models/Persona.php`
  recibe 403.

## 2. Decisiones de diseño defendibles

| Decisión | Justificación |
|---|---|
| 8 tablas (5 del diagrama + SUSCRIPCION, DETALLE_ENVIO, USUARIO) | SUSCRIPCION y DETALLE_ENVIO resuelven las relaciones N:M en 3FN; USUARIO da acceso y trazabilidad |
| `envio.direccion_entrega` propia (no la de persona) | Un envío puede ir a otra dirección; además congela el dato histórico aunque la persona se mude |
| Borrado lógico (estados) en persona/revista/usuario | Son referenciadas con FK `ON DELETE RESTRICT`; borrar físicamente destruiría el historial |
| `detalle_envio` con `ON DELETE CASCADE` desde envio | Las líneas no tienen sentido sin su envío |
| `UNIQUE(id_revista, numero_edicion)` y `UNIQUE(id_envio, id_ejemplar)` | Integridad: no repetir ediciones ni líneas |
| `id_usuario` del envío sale de la SESIÓN, no del formulario | Trazabilidad no falsificable |
| Una vista `listar.php` por entidad con modal (en vez de 4 archivos) | Cero duplicación de formularios; la separación de capas se mantiene porque la lógica vive en api/controlador/modelo |

## 3. La transacción de envíos (pregunta casi segura)

`Envio::crearConDetalles()` — dentro de `beginTransaction()`:

1. Lee el `costo_base` de la agencia.
2. Por cada línea: valida el ejemplar y **descuenta stock** con
   `UPDATE ... WHERE stock_disponible >= :minimo` (un solo UPDATE atómico;
   si afecta 0 filas → no había stock → excepción).
3. Inserta `envio` con `costo_total = costo_base + Σ subtotales`.
4. Inserta cada línea en `detalle_envio`.
5. `commit()`. Cualquier fallo → `rollBack()`: nada queda a medias.

Al marcar un envío como **devuelto**, otra transacción repone el stock de
todas sus líneas. Al marcarlo **entregado**, registra `fecha_entrega_real`.

## 4. Seguridad implementada

- **Inyección SQL**: sentencias preparadas *nativas*
  (`PDO::ATTR_EMULATE_PREPARES => false`); la consulta se compila en el
  servidor y los datos viajan por separado. Nunca concatenación.
- **Contraseñas**: `password_hash()` / `password_verify()` (bcrypt);
  el hash nunca sale del modelo (`unset` antes de devolver).
- **Sesiones**: `session_regenerate_id(true)` al iniciar sesión
  (previene fijación de sesión); guardias distintas para vistas
  (redirección) y AJAX (401 JSON).
- **XSS**: `htmlspecialchars()` en PHP y `escapeHtml()` en JS para todo
  dato que viene de la BD.
- **Autorización por rol**: gestión de usuarios solo para administradores
  (verificado en la vista Y en el controlador; ocultar el enlace no basta).
- **Login con mensaje genérico**: no revela si falló usuario o clave.
- Un admin no puede degradarse ni desactivarse a sí mismo.

## 5. Validaciones

- **Servidor** (la definitiva): `Validator.php` — cédula ecuatoriana con
  algoritmo módulo 10 (provincia 01–24, tercer dígito ≤ 5, coeficientes
  2-1-2-1-2-1-2-1-2, dígito verificador), RUC (13 dígitos, termina 001),
  email, fechas, ENUMs, duplicados (cédula/email/RUC/edición).
- **Cliente** (experiencia): required, tipos numéricos, aviso de stock,
  líneas duplicadas. El backend revalida todo.
- **Base de datos** (última barrera): NOT NULL, UNIQUE, CHECK, FK, ENUM.

## 6. Guion de demostración (5 min)

1. Login como `admin` (mencionar bcrypt y sesión).
2. Personas: crear una con cédula inválida → error del algoritmo;
   corregir → se crea. Buscar con el filtro.
3. Suscripciones: crear; intentar duplicarla → regla de negocio la bloquea.
4. **Envíos (la estrella)**: crear con 2 ejemplares, mostrar el total en
   vivo; registrar; mostrar en Ejemplares que el stock bajó;
   Despachar → Entregar (o Devolver para mostrar la reposición de stock).
5. Ver detalle del envío: desglose y "registrado por" (trazabilidad).
6. Abrir `http://localhost/revistas-domicilio/src/` → 403 (protección).
7. Cerrar sesión; entrar como operador → no ve "Usuarios".

## 7. Preguntas probables y respuestas cortas

- **¿Por qué PDO y no mysqli?** Interfaz orientada a objetos, sentencias
  preparadas con parámetros nombrados, excepciones, y portable entre
  motores cambiando solo el DSN.
- **¿Qué es 3FN?** Sin atributos multivaluados (1FN), sin dependencias
  parciales de la PK (2FN), sin dependencias transitivas (3FN). Ejemplo:
  el nombre de la revista no se repite en ejemplar; se referencia por FK.
- **¿Por qué transacciones?** Operaciones de varias escrituras que deben
  ser atómicas: envío + detalles + stock. Propiedades ACID.
- **¿Diferencia borrado lógico/físico?** Lógico = cambiar estado, conserva
  historial y respeta FK; físico = DELETE, solo posible sin referencias.
- **¿Cómo evitas inyección SQL?** Preparadas nativas: el plan de la
  consulta se fija antes de recibir los datos; un `' OR 1=1` llega como
  literal, jamás como código.
- **¿Qué pasa si dos operadores despachan el último ejemplar a la vez?**
  El UPDATE condicional (`stock >= cantidad`) es atómico en InnoDB: uno
  gana, el otro recibe "stock insuficiente" y su transacción se revierte.

## 8. Credenciales y URLs

- Panel: `http://localhost/revistas-domicilio/public/login.php`
- `admin` / `admin123` (administrador) · `operador` / `operador123`
- BD: `revistas_domicilio` en `127.0.0.1` (root, sin contraseña — XAMPP)
