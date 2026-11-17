# FV DIGITAL — Documentación Técnica del Sistema Web

> **Proyecto:** Sistema Web de FV Digital (sitio corporativo + portal de clientes + panel de administración)  
> **Ubicación del código:** `C:\xampp\Escritorio\htdocs\login_app`  
> **Versión de la documentación:** 1.0  
> **Fecha de emisión:** 17 de noviembre de 2026  
> **Público objetivo:** equipo técnico y área de sistemas de la empresa receptora

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Alcance y exclusiones](#2-alcance-y-exclusiones)
3. [Historial de creación del proyecto](#3-historial-de-creación-del-proyecto)
4. [Requisitos e instalación](#4-requisitos-e-instalación)
5. [Arquitectura general](#5-arquitectura-general)
6. [Base de datos](#6-base-de-datos)
7. [Catálogo completo de funciones](#7-catálogo-completo-de-funciones)
8. [Fragmentos de código y dónde encontrarlos](#8-fragmentos-de-código-y-dónde-encontrarlos)
9. [Módulo 1: Sitio público](#9-módulo-1-sitio-público)
10. [Módulo 2: Portal del cliente](#10-módulo-2-portal-del-cliente)
11. [Módulo 3: Panel de administración](#11-módulo-3-panel-de-administración)
12. [Puntos importantes y decisiones técnicas](#12-puntos-importantes-y-decisiones-técnicas)
13. [Seguridad: controles implementados y recomendaciones](#13-seguridad-controles-implementados-y-recomendaciones)
14. [Operación, mantenimiento y pruebas](#14-operación-mantenimiento-y-pruebas)
15. [Apéndices](#15-apéndices)

---

## 1. Resumen ejecutivo

FV Digital es una aplicación web **procedural en PHP 8.x** con tres áreas diferenciadas:

| Área | Carpetas | Función de negocio |
|---|---|---|
| **Sitio público** | raíz (`index.php`, `servicios.php`, `productos.php`, …), `includes/` | Captación: catálogo de servicios, portafolio, blog, cotizaciones, contacto, boletín, registro y acceso. |
| **Portal del cliente** | `portal/` | Área autenticada: solicitudes, seguimiento de proyectos, carrito de productos, pagos, facturación, entregables, chat y soporte. |
| **Panel de administración** | `admin/` | Gestión integral: usuarios, catálogo, cotizaciones, proyectos, pagos, facturación, entregables, contenidos y mensajería. |

Datos del inventario (excluyendo dependencias y módulos heredados):

- **89 archivos PHP propios**, aproximadamente **15,500 líneas**.
- **78 funciones globales** centralizadas en `funciones.php` (1,484 líneas), además de la clase `FV_PDF` en `lib/pdf.php`.
- **24 tablas** MySQL/MariaDB creadas por `instalador.php` y los seis scripts `migracion_*.php`.
- Persistencia **MySQL vía PDO**; sesiones PHP con inactividad de **60 minutos** y keepalive automático.
- Interfaz con **Bootstrap 5.3.3** y **Bootstrap Icons 1.11.3** vía CDN; JavaScript propio sin frameworks.
- Generación de PDF **sin librerías externas** (`lib/pdf.php`) y correo transaccional SMTP implementado directamente.

La última entrega incluye una revisión completa del carrito y del checkout: controles de cantidad, tope estricto de stock, recálculo de precios en servidor, resumen de costos antes de pagar y validación server-side del monto.

---

## 2. Alcance y exclusiones

### Incluido en esta documentación

- Núcleo: `config.php`, `conexion.php`, `funciones.php`, `includes/`, `instalador.php`, migraciones, `lib/pdf.php`, `composer.json`, `Dockerfile`, `.htaccess`.
- Sitio público: todas las páginas de la raíz.
- Portal del cliente: todos los archivos de `portal/` y `portal/includes/`.
- Panel de administración: todos los archivos de `admin/` y `admin/includes/`.
- Recursos: `assets/css/`, `assets/js/`, `assets/uploads/`, `logo.png.webp`.

### Excluido deliberadamente

- **`concesionaria/`**: módulo heredado ajeno al sistema FV Digital. No se documenta, no se entrega como parte funcional y no se describen sus tablas ni flujos.
- **`vendor/`**: dependencias instaladas por Composer (`mongodb/mongodb`, polyfills de Symfony, PSR log). Se menciona únicamente su existencia y su impacto en requisitos.
- Archivos binarios de `assets/uploads/` (comprobantes, entregables y publicaciones): son datos de prueba/demo, no código.

> **Nota:** queda registrada como deuda técnica una métrica heredada en `admin/index.php:22` (`vehiculos`) que no pertenece al dominio del sistema. `contar_registros()` devuelve `0` si la tabla no existe, por lo que no detiene el dashboard; se recomienda retirarla.

---

## 3. Historial de creación del proyecto

La evolución se reconstruye a partir del historial de Git (fechas de commit):

| Fecha | Hito (mensajes de commit) | Descripción |
|---|---|---|
| 2026-09-08 | `hola login`, `COMENTADO CODIGO` | Prototipo inicial de autenticación y estructura PHP. |
| 2026-09-10 | `SISTEMA WEB DE FV DIGITAL, CON PERFILES USUARIO Y ADMM CON PHP` | Se definen los perfiles de usuario y administrador y el sitio corporativo. |
| 2026-09-11 | `SISTEMA WEB DE FV DIGITAl con mejoras` | Ampliación de contenidos y flujos del sitio. |
| 2026-09-25 | `Subir proyecto lIMPIO` | Limpieza y consolidación de la base de código. |
| 2026-10-01 | `Primer commit` (1–3), `Migracion completa a MongoDB y optimizacion para produccion`, `Agregar Dockerfile para PHP 8.2 y MongoDB en Render`, `Ocultar errores en pantalla para produccion` | Intento de migración a MongoDB y contenedorización para Render. **La aplicación finalmente no usa MongoDB**: la persistencia real sigue siendo MySQL/PDO y la dependencia de Composer quedó latente. Se ocultan errores en pantalla y se instrumenta logging. |
| 2026-10-06 | `mej`, `mejoras` | Mejoras funcionales generales (portal y panel). |
| 2026-10-15 / 10-16 | `correccion`, `correccion1`, `correccion3` | Correcciones finales previas a la entrega. |
| 2026-11-17 | `MEJORAS` (commit `5c5ab89`) | Revisión del carrito y la sesión: `config.php`, `funciones.php`, `productos.php`, `portal/carrito.php`, `portal/pagos.php`, `assets/js/avisos.js`. |

### Decisiones derivadas de esa evolución

1. **Se conserva PHP procedural** con un único archivo de utilidades (`funciones.php`) y `$GLOBALS['pdo']`, en lugar de migrar a un framework MVC.
2. **Se descarta MongoDB** como almacenamiento principal; `composer.json` y el Dockerfile aún lo declaran por el intento de migración de octubre de 2026.
3. **Se separan las sesiones** de cliente y administrador para que un mismo navegador pueda mantener ambos contextos sin mezclarlos.
4. **Se centralizan respuestas** (JSON/redirect) y errores en `funciones.php` para uniformizar el comportamiento AJAX y ocultar detalles técnicos al usuario.
5. **La última iteración refuerza el carrito**: precio y stock se releen de la base de datos, el monto del checkout no se confía al cliente y la sesión se extiende a 60 minutos.

---

## 4. Requisitos e instalación

### 4.1 Requisitos

| Componente | Versión / detalle |
|---|---|
| PHP | **8.1 o superior** (se usan `str_starts_with`, `?array`, `never`, etc.). Referencia: `php:8.2-apache`. |
| Extensiones PHP | `pdo_mysql` (obligatoria), `openssl` (SMTP TLS), `zlib` (PDF), `mbstring` (recomendada), `gd` (opcional, logo en PDF). |
| Base de datos | **MySQL o MariaDB** con base `sistema_login`, `utf8mb4` / `utf8mb4_unicode_ci`. |
| Servidor web | Apache con `mod_rewrite` y `AllowOverride All` para los `.htaccess` de raíz y `portal/`. |
| Composer | Solo para instalar dependencias declaradas (`composer install`). No es necesario para el funcionamiento actual. |
| CDN | Bootstrap 5.3.3, Bootstrap Icons 1.11.3 y jQuery (según plantillas). Requieren Internet o mirror local. |
| Permisos de escritura | `assets/uploads/` (y subcarpetas `publicaciones`, `comprobantes`, `entregables`) y `logs/`. |

### 4.2 Estructura de carpetas

```
login_app/
├── admin/                  Panel de administración (28 PHP + includes)
├── assets/
│   ├── css/                estilos.css, portal.css, admin.css
│   ├── js/                 avisos.js, main.js, portal.js, factura-pdf.js, juegos.js
│   ├── img/                logo.png
│   └── uploads/            publicaciones/, comprobantes/, entregables/
├── includes/               Cabecera y pie del sitio público
├── lib/pdf.php             Generador PDF propio (clase FV_PDF)
├── logs/                   errores.log (registro de excepciones)
├── portal/                 Portal del cliente (31 PHP + includes + .htaccess)
├── vendor/                 Dependencias Composer (no documentadas)
├── config.php              Constantes globales, duración de sesión
├── conexion.php            Conexión PDO a MySQL
├── funciones.php           78 funciones globales + arranque de sesión
├── instalador.php          Esquema base y datos semilla
├── migracion_*.php         Seis scripts de migración
├── .htaccess               URLs amigables del sitio público
├── Dockerfile              Imagen PHP 8.2 + Apache + MongoDB (legado)
└── README.md               Esta documentación
```

### 4.3 Instalación paso a paso

1. Crear la base de datos `sistema_login` en MySQL/MariaDB con collation `utf8mb4_unicode_ci`.
2. Ajustar las credenciales en **`conexion.php:2-7`**:

```php
$host = '127.0.0.1';
$port = '3306';
$db   = 'sistema_login';
$user = 'root';
$pass = '';
```

3. Verificar permisos de escritura en `assets/uploads/` y `logs/`.
4. Ejecutar los scripts **en este orden** (vía navegador, una sola vez):

| # | Script | Efecto |
|---|---|---|
| 1 | `instalador.php` | 16 tablas base, datos semilla (`datos_sitio`, servicios, testimonio, carta, publicación) y administrador `admin@correo.com` / `pass123`. |
| 2 | `migracion_portal.php` | Tablas de portal y notificaciones de bienvenida a clientes existentes. Reejecutable; omisible en instalación limpia. |
| 3 | `migracion_pagos.php` | `metodos_pago`, `productos`, `carrito`, `pagos`, `proyectos_inicio` + 4 métodos de pago. |
| 4 | `migracion_precio_fijo.php` | Añade `servicios.precio_fijo` y marca los servicios `mantenimiento%`. |
| 5 | `migracion_mejoras.php?confirmar=1` | Añade `productos.referencia`, `proyectos_inicio.folio` e índice único; backfill `PRY-0001`. Idempotente. |
| 6 | `migracion_facturacion.php?confirmar=1` | Columnas de saldo, estado `liquidado`, `clave_unica`, `proyecto_historial`, `facturas` y backfill financiero. Idempotente. |
| 7 | `migracion_entregables.php` | Tabla `entregables` y carpeta protegida con `.htaccess` de denegación total. |

5. **Cierre obligatorio de instalación:**
   - Cambiar la contraseña del administrador desde `admin/usuarios.php`.
   - Configurar SMTP e IVA desde `admin/config.php` (tabla `datos_sitio`).
   - **Eliminar del servidor** `instalador.php`, `crear_usuario.php` y todos los `migracion_*.php`.

> `crear_usuario.php` es una utilidad de rescate que **borra y reinserta** el administrador con contraseña fija y la muestra en pantalla. Solo debe usarse en desarrollo local y nunca dejarse accesible en producción.

### 4.4 Despliegue con Docker

`Dockerfile` usa `php:8.2-apache`, habilita `rewrite`, instala la extensión `mongodb` (legado) y ejecuta `composer install --no-dev --optimize-autoloader`. La imagen **no incluye MySQL**: debe proporcionarse externamente y cambiar `$host` en `conexion.php` por el nombre del servicio contenedor.

### 4.5 Verificación post-instalación

```bash
php -l funciones.php
php -l config.php
php -l portal/carrito.php
php -l portal/pagos.php
node --check assets/js/avisos.js
```

Comprobar además: acceso a `/admin/login.php`, carga del catálogo `/productos.php`, alta de un producto con stock desde `admin/productos.php` y compra de prueba completa desde el portal.

---

## 5. Arquitectura general

### 5.1 Flujo de una petición

```
Navegador
   │  (formulario .js-ajax o fetch con X-Requested-With / Accept: application/json)
   ▼
Página PHP (raíz / portal / admin)
   │  require_once funciones.php  →  config.php + conexion.php + sesión segura + ob_start()
   ▼
Validación: verificar_csrf() + honeypot + permisos (requiere_sesion / requiere_admin)
   ▼
Consulta PDO (sentencias preparadas, EMULATE_PREPARES=false)
   ▼
responder($datos)
   ├── AJAX     → JSON (Content-Type: application/json)
   └── no AJAX  → flash + redirección interna validada
   ▼
JavaScript own (avisos.js / portal.js) actualiza badge, totales y DOM sin recargar
```

Puntos de entrada globales definidos en `funciones.php`:

- `funciones.php:14` → `ob_start()` para poder redirigir o responder JSON aunque ya se haya impreso HTML.
- `funciones.php:16-17` → carga de `conexion.php` y `config.php`.
- `funciones.php:1484` → llamada automática a `iniciar_sesion_segura()` en toda página que incluya el archivo.

### 5.2 Sesiones y control de inactividad

Duración configurada en **`config.php:30`**:

```php
define('RUTA_ADMIN_LOGIN', 'login.php');
define('SESION_INACTIVIDAD_MIN', 60);
```

`iniciar_sesion_segura()` (`funciones.php:44-64`) configura la cookie con `HttpOnly` y `SameSite=Lax`, fija `gc_maxlifetime` en 60 minutos y comprueba `$_SESSION['_ultimo_acceso']`; si expira, vacía la sesión, regenera el identificador y muestra un aviso.

Mantenimiento de la sesión:

- `portal/includes/pie.php` y `admin/includes/pie.php` llaman a `keepalive.php` cada **60 segundos** y al reactivar la pestaña (`visibilitychange`).
- `portal/keepalive.php` / `admin/keepalive.php` responden `{ ok, sesion, restante }`.
- `sesion_tocar()` (`funciones.php:1460`) renueva el tiempo y cierra la sesión con `session_write_close()` para no bloquear el stream SSE del chat.

**Dos contextos aislados:**

| Contexto | Claves de sesión | Asignadas por |
|---|---|---|
| Cliente | `usuario_id`, `nombre`, `rol` | `login_ok()` (`funciones.php:89`) |
| Administrador | `admin_id`, `admin_nombre`, `admin_rol` | `login_ok_admin()` (`funciones.php:100`) |

`cerrar-sesion.php` y `admin/cerrar.php` limpian **únicamente** su contexto. Esto es intencional y debe tenerse en cuenta al probar ambos roles en el mismo navegador.

### 5.3 Seguridad base

- **CSRF:** `csrf_token()` (`funciones.php:27`) genera un token con `random_bytes(32)`; `campo_csrf()` (`:34`) lo inyecta en los formularios; `verificar_csrf()` (`:38`) lo compara con `hash_equals` en POST.
- **Honeypots:** campo oculto `empresa` en login, registro, contacto, boletín, recuperación y soporte; campo `web` en solicitud empresarial.
- **Escape de salida:** `e()` (`funciones.php:21`) usa `htmlspecialchars(ENT_QUOTES, 'UTF-8')`.
- **Anti-open-redirect parcial:** `filtra_url_interna()` (`:638`) y `retorno_guardar()/retorno_usar()` (`:136-152`) aceptan solo rutas relativas o mismo host y rechazan CRLF (ver recomendación en §13).
- **Subidas:** `subir_archivo()` (`:493`) valida error de upload, extensión en lista blanca y tamaño máximo de 8 MB; genera nombres `YYYYMMDDHHMMSS_hex.ext`; `eliminar_archivo()` (`:520`) confina borrados a `UPLOADS_DIR` usando `realpath()`.
- **Errores:** `registrar_error()` (`:536`) escribe en `logs/errores.log`; al usuario se le muestra `MSG_ERROR_GENERICO` (`:534`). Las excepciones PDO ya no se exponen en el checkout.
- **Acceso:** `requiere_sesion()` (`:178`) y `requiere_admin()` (`:81`) protegen portal y panel respectivamente.

### 5.4 Respuestas AJAX uniformes

`responder()` (`funciones.php:593-635`) es el punto único de respuesta:

```php
function responder(array $datos): void {
    $datos += [
        'ok'      => true,
        'mensaje' => '',
        'tipo'    => 'success',
        'destino' => null,
        'titulo'  => null,
    ];
    descartar_buffers();

    if (es_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    // En modo normal: flash + redirección interna.
}
```

`es_ajax()` (`:572`) detecta `X-Requested-With: XMLHttpRequest` o `Accept: application/json`. El `fetch` del botón «Proceder al pago» envía ambas cabeceras (`portal/carrito.php:572-575`).

`avisos.js` intercepta los formularios `.js-ajax`, muestra un overlay de carga, dispara el evento `fv:ajaxok` con la respuesta y actualiza el badge del carrito (`[data-cart-count]`), el resumen (`FV.actualizarResumen`) y los mensajes flotantes.

### 5.5 URLs amigables

**`.htaccess` (raíz):** `clientes` → `portal/index.php`, `clientes/<ruta>` → `portal/<ruta>`, `acceso` → `iniciar-sesion.php`, `crear-cuenta` → `registro.php`, `recuperar-password` → `recuperar.php`, `solicitud-empresa` → `solicitud-empresa.php`, `productos` → `productos.php`.

**`portal/.htaccess`:** `panel`, `inicio`, `mis-solicitudes`, `procesos`, `pagos`, `facturacion`, `facturas-api`, `carrito`, `nueva-solicitud`, `nueva-solicitud-empresa`, `chat`, `juegos`, `notificaciones`, `soporte`, `mi-perfil`, `configuracion`, `salir`. Los archivos `.php` originales siguen funcionando como respaldo.

---

## 6. Base de datos

### 6.1 Resumen por módulo (24 tablas)

**Base (`instalador.php`):**

| Tabla | Contenido |
|---|---|
| `usuarios` | Clientes y administradores; `rol` ENUM(`admin`,`vendedor`,`cliente`), `activo`, `password` bcrypt. Migraciones posteriores añaden `rfc` y `direccion`. |
| `servicios` | Catálogo de servicios con `precio_desde`, `activo`, `precio_fijo` (flag TINYINT, **no** es un precio). |
| `proyectos` | Portafolio público del sitio (distinto de `proyectos_inicio`). |
| `publicaciones` | Blog con `contenido` HTML, `visitas`, `activo`. |
| `testimonios`, `cartas` | Contenido de la portada y cartas de presentación. |
| `solicitudes` | Cotizaciones; `tipo_solicitud` (`individual`/`empresa`), `estado`, datos de contacto y presupuesto textual. |
| `soporte` | Tickets del portal con `estado` y `respuesta`. |
| `mensajes_contacto`, `suscripciones` | Formulario de contacto y boletín. |
| `datos_sitio` | Configuración clave→valor: identidad, SMTP, IVA, logo, WhatsApp. |
| `mensajes_portal`, `notificaciones` | Chat y avisos por usuario. |
| `solicitud_historial` | Bitácora de cambios de estado de solicitudes. |
| `juego_puntajes` | Puntajes de los mini-juegos. |
| `password_reseteos` | Códigos de recuperación hasheados, intentos y vigencia. |

**Portal/negocio (`migracion_portal.php`):** recrea de forma idempotente `mensajes_portal`, `notificaciones`, `solicitud_historial` y `juego_puntajes`; añade notificaciones de bienvenida.

**Comercio (`migracion_pagos.php`):**

| Tabla | Columnas relevantes |
|---|---|
| `metodos_pago` | `nombre`, `descripcion`, `detalles_cuenta`, `instrucciones`, `icono`, `activo`. Semilla: transferencia, PayPal, OXXO y efectivo. |
| **`productos`** | `titulo`, `slug` UNIQUE, `descripcion`, `precio` DECIMAL(10,2), `imagen`, `categoria`, `referencia`, **`stock` INT**, `activo`. |
| **`carrito`** | `usuario_id` (FK → `usuarios` CASCADE), `servicio_id`, `producto_id`, `cantidad`, `precio_unitario`, `creado_en`. |
| **`pagos`** | `usuario_id`, `solicitud_id`, `metodo_pago_id`, `monto`, `tipo_pago` ENUM(`anticipo`,`restante`,`completo`,`producto`), `comprobante`, `estado` ENUM(`pendiente`,`aprobado`,`rechazado`), `notas`, `creado_en`. Ampliada con `proyecto_id`, `clave_unica` UNIQUE, `aplicado` e índices. |
| `proyectos_inicio` | Datos del proyecto, `anticipo_minimo`, `anticipo_pagado`, `folio` UNIQUE, `total_proyecto`, `pagado_total`, `saldo_restante`, `estado` (incluye `liquidado`). |

**Facturación y entregables:** `proyecto_historial` (bitácora), `facturas` (folio `FV-0001`, `rfq_cliente`, importes, `pagos_json`, `estado`) y `entregables` (archivo por proyecto).

### 6.2 Esquemas clave con su ubicación

**`productos` y `carrito` — `migracion_pagos.php:23-45`:**

```sql
CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(200) UNIQUE NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255),
  categoria VARCHAR(100),
  stock INT DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

**`pagos` — `migracion_pagos.php:47-61` y ampliaciones de `migracion_facturacion.php:86-110`:**

```sql
CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  solicitud_id INT NULL,
  metodo_pago_id INT NULL,
  monto DECIMAL(10,2) NOT NULL,
  tipo_pago ENUM('anticipo','restante','completo','producto') DEFAULT 'anticipo',
  comprobante VARCHAR(255),
  estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
-- Añadido después: proyecto_id, clave_unica VARCHAR(64) UNIQUE, aplicado TINYINT(1)
```

**`facturas` — `migracion_facturacion.php:133-152`:** incluye `folio` único, `subtotal`, `iva`, `total`, `pagos_json` (instantánea) y `rfq_cliente` (errata histórica: en esa columna se guarda el RFC).

### 6.3 Decisiones de integridad

1. `carrito` **no tiene FK a `productos`/`servicios` ni UNIQUE compuesto**. La integridad se resuelve en aplicación: `carrito_calcular()` hace `LEFT JOIN`, descarta ítems inactivos y ajusta cantidades (`funciones.php:201-299`).
2. `pagos.clave_unica` es UNIQUE y se usa como token de idempotencia para evitar duplicados por doble clic o recarga.
3. `proyectos_inicio` se bloquea con `SELECT ... FOR UPDATE` en operaciones financieras (`funciones.php:814-865`, `:1028-1101`).
4. `facturas` guarda el detalle en `pagos_json`; los cambios posteriores de IVA no alteran facturas ya emitidas.
5. `productos.precio_fijo` en `servicios` es un **indicador**, no un importe; el precio real está en `precio_desde`.

---

## 7. Catálogo completo de funciones

Todas las funciones siguientes están en **`funciones.php`**. La numeración es correlativa y los números de línea corresponden al archivo actual.

### 7.1 Seguridad, sesión y enrutado (1–18)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 1 | `e(?string $valor): string` | 21 | Escapa HTML con `htmlspecialchars(ENT_QUOTES, 'UTF-8')`. |
| 2 | `csrf_token(): string` | 27 | Crea o reutiliza el token CSRF en sesión (`random_bytes(32)`). |
| 3 | `campo_csrf(): string` | 34 | Devuelve el `<input hidden name="csrf">` escapado. |
| 4 | `verificar_csrf(): bool` | 38 | Compara el token en POST mediante `hash_equals`. |
| 5 | `iniciar_sesion_segura(): void` | 44 | Cookie HttpOnly/SameSite, `gc_maxlifetime`, expira inactividad y regenera ID. |
| 6 | `sesion_restante_seg(): int` | 66 | Segundos restantes antes de la expiración por inactividad. |
| 7 | `esta_admin(): bool` | 77 | Verifica `admin_id > 0` y `admin_rol === 'admin'`. |
| 8 | `requiere_admin(): void` | 81 | Exige sesión admin o redirige a `login.php`. |
| 9 | `login_ok(array $usuario): void` | 89 | Regenera ID y fija sesión de cliente (`usuario_id`, `nombre`, `rol`). |
| 10 | `login_ok_admin(array $usuario): void` | 100 | Fija sesión admin sin pisar la de cliente. |
| 11 | `url_sitio(string $ruta = ''): string` | 112 | Prefija el subdirectorio del proyecto calculado desde `DOCUMENT_ROOT`. |
| 12 | `redirigir(string $ruta): void` | 125 | `header('Location: ...')` con `url_sitio()` y `exit`. |
| 13 | `retorno_guardar(): void` | 136 | Guarda una ruta interna de retorno; rechaza CRLF y `http(s)://`. |
| 14 | `retorno_usar(string $defecto = 'portal/index.php'): string` | 145 | Consume el retorno validado o devuelve el valor por defecto. |
| 15 | `esta_logueado(): bool` | 156 | `usuario_id > 0`. |
| 16 | `sesion_actual(): ?array` | 160 | Consulta `usuarios` por `usuario_id`; `null` si no hay sesión o falla. |
| 17 | `destino_segun_rol(): string` | 174 | Devuelve `admin/index.php` o `portal/index.php`. |
| 18 | `requiere_sesion(): void` | 178 | Exige sesión de cliente o redirige al login. |

### 7.2 Carrito (19–22) — núcleo de la última entrega

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 19 | **`carrito_monto(float $cantidad, bool $conMoneda = true): string`** | 189 | Formato monetario `$1,234.56` con dos decimales; añade `" MXN"` salvo que se indique lo contrario. |
| 20 | **`carrito_calcular(int $usuarioId, bool $sincronizar = false): array`** | 201 | Recalcula carrito desde BD con precio y stock vigentes; aplica tope de stock, descarta inactivos/agotados y devuelve `items`, `item_count`, `unidades`, `count`, `subtotal`, `avisos`, `descartados`. Con `$sincronizar=true` actualiza o borra filas. |
| 21 | **`form_agregar_carrito(int $id, string $tipo, string $titulo = '', ?int $stock = null): string`** | 308 | Genera el formulario AJAX de alta (CSRF + selector de cantidad con `max=stock`). Si no hay sesión, genera un formulario de login con `retorno`. Con `stock=0` devuelve «Sin existencias». |
| 22 | `contar_carrito(int $usuarioId): int` | 347 | Suma `cantidad` del usuario para el badge de la cabecera. |

### 7.3 Utilidades de contenido y configuración (23–34)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 23 | `slugify(string $texto): string` | 359 | Translitera acentos y ñ a ASCII y genera un slug limpio. |
| 24 | `slug_unico(string $titulo, string $tabla, ?int $excluirId = null): string` | 375 | Slug único con sufijos `-2`, `-3`… |
| 25 | `formatear_precio(?float $cantidad): string` | 396 | `"$5000"` o `"A convenir"` si es nulo/≤0. |
| 26 | `tiempo_relativo(string $fecha): string` | 400 | «hace poco», «hoy» o `d/m/Y`. |
| 27 | `datos_sitio(): array` | 409 | Caché estática de la tabla `datos_sitio` (clave → valor). |
| 28 | `dato_sitio(string $clave, string $defecto = ''): string` | 426 | Valor configurable o por defecto. |
| 29 | `logo_sitio(): string` | 434 | URL del logo configurado o `assets/img/logo.png`. |
| 30 | `factura_iva_rate(): float` | 448 | Tasa de IVA de `datos_sitio` (16 % por defecto) convertida a factor y limitada a 0–100. |
| 31 | `logo_sitio_archivo(): string` | 454 | Ruta local del logo para incrustarlo en PDF. |
| 32 | `datos_emisor(): array` | 469 | Nombre, eslogan, razón social, RFC, dirección, teléfono, email y logo para documentos. |
| 33 | `telefono_marcar(): string` | 482 | Enlace `tel:+52...`. |
| 34 | `whatsapp_enlace(string $texto = ''): string` | 486 | Enlace `wa.me` con texto codificado. |

### 7.4 Archivos, errores, respuestas y mensajes (35–46)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 35 | `subir_archivo(string $campo, string $carpeta, array $permitidos, int $maxMb = MAX_ARCHIVO_MB): array` | 493 | Valida upload, extensión y tamaño; guarda con nombre aleatorio en `assets/uploads/<carpeta>`. Devuelve `ok`, `archivo`, `original` o `error`. |
| 36 | `eliminar_archivo(?string $ruta): void` | 520 | Borra el archivo solo si `realpath()` queda dentro de `UPLOADS_DIR`. |
| 37 | `registrar_error(string $contexto, Throwable $e, array $datos = []): void` | 536 | Log detallado en `logs/errores.log` y `error_log()`. |
| 38 | `flash(string $mensaje, string $tipo = 'success'): void` | 555 | Guarda un mensaje en `$_SESSION['flash']`. |
| 39 | `mostrar_flash(): void` | 559 | Imprime y consume una alerta Bootstrap. **La cabecera pública no la invoca**: cada página debe imprimirla si la necesita. |
| 40 | `es_ajax(): bool` | 572 | Detecta peticiones AJAX por cabecera. |
| 41 | `descartar_buffers(): void` | 587 | Cierra todos los buffers descartando su contenido. |
| 42 | `responder(array $datos): void` | 593 | JSON si AJAX; si no, flash + redirección interna. |
| 43 | `filtra_url_interna(string $url): string` | 638 | Bloquea CRLF y redirecciones externas (ver observación §13). |
| 44 | `notificar(int $usuarioId, string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): void` | 658 | Inserta un aviso en `notificaciones`. |
| 45 | `notificar_admins(string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): int` | 665 | Notifica a todos los administradores activos y devuelve cuántos. |
| 46 | `registrar_historial(int $solicitudId, string $estado, string $nota = ''): void` | 679 | Inserta un evento en `solicitud_historial`. |

### 7.5 Proyectos y anticipos (47–53)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 47 | `proyecto_aplicar_anticipo(int $proyectoId, float $monto): void` | 693 | Suma `anticipo_pagado` sin retroceder de estado. |
| 48 | `proyecto_recalcular_estado_anticipo(int $proyectoId): void` | 707 | Pasa a `en_desarrollo` si cubre el mínimo; si no, `anticipo_pendiente`. |
| 49 | `proyecto_descontar_anticipo(int $proyectoId, float $monto): void` | 717 | Resta con `GREATEST(0, ...)` y recalcula estado. |
| 50 | `proyecto_cambiar_estado(int $proyectoId, string $estado): string` | 728 | Cambio manual validado (requiere anticipo cubierto para `en_desarrollo`). |
| 51 | `proyecto_datos(int $proyectoId)` | 745 | Proyecto + cliente + solicitud para avisos/correos. |
| 52 | `proyecto_total_parsear(?string $presupuesto): float` | 767 | Extrae el mayor número de un presupuesto textual o rango. |
| 53 | `registrar_historial_proyecto(int $proyectoId, string $accion, string $detalle = '', ?int $usuarioId = null): void` | 783 | Escribe en `proyecto_historial`. |

### 7.6 Pagos y conciliación (54–63)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 54 | `pago_generar_clave(array $ctx): string` | 793 | HMAC-SHA256 con salt aleatorio: token de idempotencia. |
| 55 | `pago_sumar_aprobados(int $proyectoId, int $solicitudId, PDO $pdo): float` | 800 | Suma pagos aprobados por proyecto o solicitud. |
| 56 | `proyecto_recalcular_saldo(int $proyectoId, ?PDO $pdo = null): array` | 814 | Con `FOR UPDATE`, recalcula `pagado_total` y `saldo_restante`; marca `liquidado`. |
| 57 | `pago_registrar(array $d): array` | 866 | Alta transaccional de pago: idempotencia, validaciones, historial, recálculo y notificaciones. |
| 58 | `notificacion_pago_correo(array $pago, string $estado, string $notasAdmin = ''): void` | 968 | Aviso al cliente por aprobación/rechazo con plantilla de correo. |
| 59 | `pago_aprobar_o_rechazar(int $pagoId, string $estado, string $notasAdmin = ''): array` | 1028 | Aprueba/rechaza en transacción, recalcula saldo, escribe bitácora y notifica. |
| 60 | `folio_pago_txt(array $pago): string` | 1104 | `PAG-00001` para bitácoras. |
| 61 | `folio_proyecto_txt(array $proyecto): string` | 1110 | `folio` o `PRY-0001` derivado del ID. |
| 62 | `monto_pago_txt(array $pago): string` | 1119 | `"$1,234.56 MXN"`. |
| 63 | `proyecto_pagos_aprobados_detalle(int $proyectoId): array` | 1124 | Detalle de pagos aprobados para facturas y APIs. |

### 7.7 Facturación, correo y tiempo real (64–78)

| # | Función (firma) | Línea | Descripción |
|---|---|---|---|
| 64 | `factura_folio(PDO $pdo): string` | 1151 | Siguiente folio `FV-0001`. |
| 65 | `factura_crear(array $d): array` | 1159 | Emite factura en transacción: instantánea de pagos, IVA, bitácora, notificación y correo. |
| 66 | `factura_datos(int $id): ?array` | 1239 | Factura con cliente, proyecto y solicitud. |
| 67 | `facturas_por_proyecto(int $proyectoId): array` | 1253 | Facturas de un proyecto, más recientes primero. |
| 68 | `contar_no_leidas(string $tabla, int $usuarioId, string $columna = 'leida'): int` | 1259 | Cuenta avisos/mensajes sin leer para badges. |
| 69 | `smtp_config(): array` | 1275 | Lee host, puerto, usuario, clave, remitente y nombre desde `datos_sitio`. |
| 70 | `smtp_habilitado(): bool` | 1287 | `host` y `from` no vacíos. |
| 71 | `smtp_cmd($con, string $cmd): string` | 1292 | Envía un comando SMTP y lee la respuesta. |
| 72 | `enviar_correo(string $para, string $asunto, string $cuerpoHtml, string $textoPlano = ''): array` | 1311 | SMTP propio con SSL/465 o STARTTLS/587, AUTH LOGIN y MIME alternativo. |
| 73 | `correo_plantilla(string $titulo, string $contenidoHtml): string` | 1404 | Plantilla HTML de correos transaccionales. |
| 74 | `sse_iniciar(): void` | 1424 | Cabeceras `text/event-stream`, sin buffers ni compresión. |
| 75 | `sse_enviar(string $evento, array $datos): void` | 1446 | Emite `event:` + `data:` JSON y hace `flush()`. |
| 76 | `sse_ping(): void` | 1452 | Comentario `: ping` de keep-alive. |
| 77 | `sesion_tocar(): void` | 1460 | Renueva inactividad y cierra la sesión para SSE. |
| 78 | `contar_registros(string $tabla, string $condicion = '1=1'): int` | 1473 | `COUNT(*)` genérico; devuelve 0 si falla. |

### 7.8 Funciones auxiliares fuera de `funciones.php`

| Archivo | Auxiliares |
|---|---|
| `migracion_facturacion.php:21-61` | `mf_tabla_existe`, `mf_col_existe`, `mf_idx_existe`, `mf_fk_existe`, `mf_ejecutar` (migración idempotente). |
| `migracion_mejoras.php:18-37` | Closures `$mf_col_existe` y `$mf_idx_existe`. |
| `lib/pdf.php` | Métodos de `FV_PDF`: `limpiar`, `anchoCaracter`, `anchoTexto`, `ajustarTexto`, `nuevaPagina`, `fuente`, `lineaT`, `rectT`, `textoT`, `imagenT`, `imagenDatos`, `pngRGB`, `bytes`, `descargar`. |

---

## 8. Fragmentos de código y dónde encontrarlos

Cada bloque indica la ruta relativa y las líneas exactas del archivo.

### 8.1 Conexión a la base de datos

📄 **`conexion.php:9-17`**

```php
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
```

### 8.2 Configuración global y duración de sesión

📄 **`config.php:26-30`**

```php
define('SITE_FOOTER', 'TV Digital — Soluciones Digitales y Desarrollo.');
define('UPLOADS_DIR', __DIR__ . '/assets/uploads');
define('MAX_ARCHIVO_MB', 8);
define('RUTA_ADMIN_LOGIN', 'login.php');
define('SESION_INACTIVIDAD_MIN', 60);
```

### 8.3 Token CSRF y verificación

📄 **`funciones.php:27-40`**

```php
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function campo_csrf(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function verificar_csrf(): bool {
    return ($_SERVER['REQUEST_METHOD'] !== 'POST') || (isset($_POST['csrf']) && hash_equals(csrf_token(), $_POST['csrf']));
}
```

### 8.4 Subida segura de archivos

📄 **`funciones.php:493-517`**

```php
function subir_archivo(string $campo, string $carpeta, array $permitidos, int $maxMb = MAX_ARCHIVO_MB): array {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No se recibió el archivo.'];
    }
    // ... validación de extensión y tamaño ...
    $nombreNuevo = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], $dirBase . '/' . $nombreNuevo)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
    }
    return ['ok' => true, 'archivo' => 'assets/uploads/' . $carpeta . '/' . $nombreNuevo, 'original' => $nombreOriginal];
}
```

### 8.5 Recálculo autoritativo del carrito

📄 **`funciones.php:204-213` y `funciones.php:237-268`**

```php
$stmt = $pdo->prepare('SELECT c.*,
                              sv.precio_desde AS servicio_precio, sv.activo AS servicio_activo,
                              pr.precio AS producto_precio, pr.stock AS producto_stock,
                              pr.activo AS producto_activo
                       FROM carrito c
                       LEFT JOIN servicios sv ON c.servicio_id = sv.id
                       LEFT JOIN productos pr ON c.producto_id = pr.id
                       WHERE c.usuario_id = ?');
```

```php
if ($cantidad > $stock) {
    $cantidad = $stock; // Tope estricto: no se puede comprar más de lo que hay.
    $avisos[] = $titulo . ' se ajustó a ' . $stock . ' unidad(es) disponibles.';
}
// Si $sincronizar es true se actualizan precio/cantidad y se borran descartados.
```

### 8.6 Formulario de alta con selector de cantidad

📄 **`funciones.php:326-338`**

```php
$html = '<form method="POST" action="' . e(url_sitio('portal/carrito.php')) . '" class="js-ajax form-agregar-carrito" data-titulo="' . e($titulo) . '">'
    . $csrf
    . '<input type="hidden" name="accion" value="agregar">'
    . '<input type="hidden" name="' . $campo . '" value="' . $id . '">';
if ($tipo === 'producto' && $stock !== null) {
    $html .= '<div class="d-flex justify-content-end mb-2">'
        . '<button type="button" data-cant-menos>−</button>'
        . '<input type="number" name="cantidad" value="1" min="1" max="' . $stock . '">'
        . '<button type="button" data-cant-mas>+</button></div>';
}
```

### 8.7 Aviso de existencias en el catálogo

📄 **`productos.php:46-58`**

```php
<?php $stockProducto = (int)$p['stock']; ?>
<small class="d-block mb-2 <?= $stockProducto <= 0 ? 'text-danger' : ($stockProducto <= 3 ? 'text-warning' : 'text-success') ?>">
    <?php if ($stockProducto <= 0): ?> Agotado
    <?php elseif ($stockProducto <= 3): ?> Quedan solo <?= $stockProducto ?> disponibles
    <?php else: ?> Quedan <?= $stockProducto ?> disponibles <?php endif; ?>
</small>
<?= form_agregar_carrito((int)$p['id'], 'producto', $p['titulo'], $stockProducto) ?>
```

### 8.8 Tope de stock al agregar unidades

📄 **`portal/carrito.php:76-88`**

```php
$existente   = $stmtChk->fetch();
$enCarrito   = $existente ? max(0, (int)$existente['cantidad']) : 0;
$disponible  = $stock - $enCarrito;
if ($disponible <= 0) {
    responder([
        'ok'      => false,
        'mensaje' => 'Ya tienes las ' . $stock . ' unidad(es) disponibles de ' . $tituloItem . ' en tu carrito.',
        'tipo'    => 'warning',
    ]);
}
$final = min($cantidad, $disponible);
```

### 8.9 Acción `resumen` previa al checkout

📄 **`portal/carrito.php:34-37`**

```php
/* Refrescar precios/stock y totales antes de abrir el checkout. */
if ($accion === 'resumen') {
    responder(datos_carrito($usuarioId));
}
```

`datos_carrito()` (`portal/carrito.php:13-26`) construye todas las respuestas del carrito con `carrito_calcular($usuarioId, true)`, de modo que badge, totales y avisos siempre salen de la BD.

### 8.10 Refresco del modal desde JavaScript

📄 **`portal/carrito.php:559-575` y `:548-557`**

```js
var btnProceder = document.getElementById('btnProcederPago');
btnProceder.addEventListener('click', function () {
    var fd = new FormData();
    fd.append('accion', 'resumen');
    var csrf = document.querySelector('[name="csrf"]');
    if (csrf) fd.append('csrf', csrf.value);
    fetch('carrito.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
```

```js
function refrescarCheckout(d) {
    ponerTexto('[data-checkout-items]', d.cart_items);
    ponerTexto('[data-checkout-unidades]', d.cart_units);
    ponerTexto('[data-checkout-subtotal]', d.cart_subtotal);
    ponerTexto('[data-checkout-total]', d.cart_total);
    document.querySelector('[data-checkout-monto]').value = Number(d.cart_total_num).toFixed(2);
}
```

### 8.11 Monto calculado solo en servidor

📄 **`portal/pagos.php:136-153`**

```php
/* El monto NUNCA se toma del formulario: se recalcula desde la BD
   usando precio vigente y cantidades topeadas por stock. */
$carrito = carrito_calcular((int)$usuario['id'], true);
if ($carrito['item_count'] === 0) { /* carrito vacío */ }
if ($carrito['descartados'] > 0) { /* artículos sin existencias */ }
$monto = $carrito['subtotal'];
if ($monto <= 0) {
    responder(['ok' => false, 'mensaje' => 'No se pudo calcular el total de tu pedido.', 'tipo' => 'danger']);
}
```

### 8.12 Instantánea del pedido en `pagos.notas`

📄 **`portal/pagos.php:172-181`**

```php
/* Snapshot del pedido en notas (no hay tabla de detalle de pagos). */
$detalle = [];
foreach ($carrito['items'] as $it) {
    $detalle[] = $it['titulo'] . ' x' . $it['cantidad'] . ' @ '
        . carrito_monto($it['precio_unitario'], false)
        . ' = ' . carrito_monto($it['subtotal'], false);
}
$notas = 'Compra desde el carrito (monto recalculado por el servidor). ' . implode(' | ', $detalle);
```

### 8.13 Emisión de factura

📄 **`funciones.php:1192-1209`**

```php
$pdo->beginTransaction();
$folio = factura_folio($pdo);
$pdo->prepare('INSERT INTO facturas (folio, proyecto_id, usuario_id, concepto, rfq_cliente,
                                    subtotal, iva, total, pagos_json, estado, emitida_por)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$folio, $proyectoId, $usuarioId, $concepto, $rfc, $subtotal,
               $iva, $total, json_encode($snapshot, JSON_UNESCAPED_UNICODE),
               'emitida', $emitidaPor]);
```

### 8.14 Respuesta uniforme y notificaciones

📄 **`funciones.php:593-608` y `:658-665`**

```php
function responder(array $datos): void {
    $datos += ['ok' => true, 'mensaje' => '', 'tipo' => 'success', 'destino' => null, 'titulo' => null];
    descartar_buffers();
    if (es_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
function notificar(int $usuarioId, string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): void {
    $stmt = $GLOBALS['pdo']->prepare('INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$usuarioId, $tipo, $titulo, $mensaje, $enlace]);
}
```

### 8.15 Tiempo real (SSE) y keepalive

📄 **`funciones.php:1424-1452`**, **`portal/chat_stream.php`**, **`portal/includes/pie.php:23-41`**

```php
function sse_iniciar(): void { /* cabeceras text/event-stream, sin buffers */ }
function sse_enviar(string $evento, array $datos): void { /* event: + data: JSON + flush */ }
function sse_ping(): void { /* ": ping" cada 15 s */ }
```

```js
// portal/includes/pie.php: ping cada 60 s y al volver a la pestaña
fetch('keepalive.php', { cache: 'no-store' }).then(r => r.json()).then(d => {
    if (!d.ok || !d.sesion) window.location.href = 'salir';
});
```

### 8.16 Generación de PDF sin librerías

📄 **`lib/pdf.php` (clase `FV_PDF`)** — A4, PDF 1.4, fuentes Helvetica con `WinAnsiEncoding`, conversión UTF-8 → CP1252, streams `FlateDecode` y decodificador PNG propio. Se usa desde `portal/factura_pdf.php`, `portal/recibo_pdf.php` y `portal/carta_pdf.php`.

---

## 9. Módulo 1: Sitio público

### 9.1 Mapa de páginas

| Archivo | Propósito | Sesión | Parámetros / acciones |
|---|---|---|---|
| `index.php` | Portada: servicios, proyectos, testimonios, publicaciones y CTAs. | Pública | Sin parámetros propios. |
| `servicios.php` | Catálogo de servicios; genera enlaces `?servicio=<slug>` a `solicitud.php`. | Pública | Sin POST. |
| `productos.php` | Catálogo de productos con aviso de stock y botón de compra. | Pública (comprar exige sesión) | El formulario envía POST a `portal/carrito.php`. |
| `publicaciones.php` / `publicacion.php` | Blog y detalle por slug. | Pública | `GET slug`. |
| `proyectos.php` | Portafolio con filtro `cat` (whitelist). | Pública | `GET cat`. |
| `solicitud.php` | Cotización individual. | Ver pública / **enviar exige sesión** | `GET servicio`; POST con datos + CSRF + honeypot. |
| `solicitud-empresa.php` | Cotización corporativa. | Igual | POST con empresa, cargo, empleados, rango + honeypot `web`. |
| `contacto.php` | Formulario de contacto y datos de la empresa. | Pública | POST: nombre, email, asunto, mensaje + CSRF. |
| `boletin.php` | Alta al boletín desde el pie (sin HTML propio). | Pública | POST: email, retorno, honeypot (sin CSRF). |
| `cartas.php` / `carta.php` | Cartas de presentación y vista imprimible. | Pública | `GET slug`. |
| `registro.php` | Alta de cliente con auto-login. | Redirige si ya hay sesión | `GET retorno`; POST con CSRF. |
| `iniciar-sesion.php` | Login de clientes y admins del sitio. | Redirige si ya hay sesión | `GET retorno`; POST con CSRF. |
| `recuperar.php` | Recuperación en dos pasos por código de 6 dígitos. | Redirige si ya hay sesión | `GET paso`; POST `accion=enviar|verificar|reenviar`. |
| `cerrar-sesion.php` | Cierra solo la sesión de cliente. | Requiere sesión | Sin parámetros. |
| `cuenta.php` | Alias de compatibilidad → `portal/index.php`. | Requiere sesión | Sin parámetros. |
| `admin/recuperar.php` | Recuperación del panel avisando a otros administradores. | Pública | POST con email + CSRF. |

Todas las páginas cargan `funciones.php` directa o indirectamente (`includes/cabecera.php` → `funciones.php`), por lo que la sesión se inicia automáticamente.

### 9.2 Flujo de autenticación

**Registro (`registro.php:40-49`):** valida CSRF, honeypot, email, teléfono (≥10 caracteres) y contraseña (≥6 con confirmación); inserta con `password_hash(..., PASSWORD_BCRYPT)` y `rol='cliente'`; llama a `login_ok()` y redirige a `retorno_usar('portal/index.php')`.

**Login (`iniciar-sesion.php:21-29`):**

```php
$stmt = $pdo->prepare('SELECT id, nombre, email, password, rol, activo FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$usuario = $stmt->fetch();
if (!$usuario || !password_verify($password, $usuario['password'])) {
    $error = 'Correo o contraseña incorrectos.';
} elseif ((int)($usuario['activo'] ?? 1) !== 1) {
    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
} else {
    login_ok($usuario);
}
```

**Recuperación (`recuperar.php:92-96`, `:150-159`):** genera `random_int(100000,999999)`, guarda `hash('sha256', $codigo)` en `password_reseteos`, limita reenvíos a 60 s, exige expiración < 15 min, máximo 5 intentos y comparación con `hash_equals`. Si el SMTP falla, el código se muestra en pantalla como modo degradado (riesgo documentado en §13).

### 9.3 Solicitudes, contacto y boletín

- `solicitud.php:66-73` inserta en `solicitudes`, llama a `registrar_historial()` y `notificar()`, y redirige a `portal/solicitudes.php`. Los servicios con `precio_fijo` asignan automáticamente el importe.
- `solicitud-empresa.php:53-54` añade `tipo_solicitud='empresa'`, empresa, cargo, empleados y rango.
- `contacto.php:21-28` valida campos y guarda en `mensajes_contacto`; el administrador lo ve en `admin/mensajes.php`. No envía correo.
- `boletin.php:5-8` hace `INSERT ... ON DUPLICATE KEY UPDATE activo=1` en `suscripciones` y muestra el aviso desde el pie.

### 9.4 Catálogo y carrito

`productos.php:18` consulta `SELECT * FROM productos WHERE activo = 1 ORDER BY id DESC`: los agotados **se muestran** con etiqueta y botón deshabilitado (decisión de negocio: no se filtran por stock en SQL). El botón de compra se genera con `form_agregar_carrito(..., $stockProducto)` (`productos.php:58`). Si el visitante no tiene sesión, el formulario apunta a `iniciar-sesion.php` con `retorno` a la página actual; si la tiene, envía `accion=agregar` por AJAX a `portal/carrito.php`.

---

## 10. Módulo 2: Portal del cliente

### 10.1 Mapa de archivos

| Archivo | Propósito | Parámetros / acciones principales |
|---|---|---|
| `index.php` | Panel/resumen del cliente. | Sin parámetros. |
| `login.php`, `registro.php`, `cerrar.php` | Autenticación propia del portal. | POST email/password/datos + CSRF. |
| `solicitud.php`, `solicitud-empresa.php` | Nuevas cotizaciones dentro del portal. | `GET servicio`, `reutilizar`; POST + CSRF. |
| `solicitudes.php` | Listado, inicio de proyecto y pago de anticipo. | `accion=iniciar_proyecto|pagar_anticipo`, `clave_unica`, comprobante. |
| `procesos.php` | Seguimiento, historial y confirmación de entregas. | `accion=confirmar_entrega`. |
| **`carrito.php`** | Resumen y gestión del carrito. | `accion=resumen|agregar|actualizar|eliminar|vaciar`. |
| **`pagos.php`** | Pagos de saldo, compra de carrito, borrado y CSV. | `accion=pagar_saldo|pagar_producto|eliminar_pago`. |
| `facturacion.php` | Solicitud de factura. | `accion=solicitar_factura`, RFC, dirección. |
| `facturas_api.php` | Autocompletado y detalle JSON de facturas. | `GET proyecto_id` o `id`. |
| `factura_pdf.php`, `factura_print.php` | Factura PDF e imprimible. | `GET id`. |
| `recibo.php`, `recibo_pdf.php` | Recibo HTML y PDF. | `GET id`. |
| `descargar-entregable.php` | Descarga controlada de archivos. | `GET id`. |
| `carta_api.php`, `carta_agradecimiento.php`, `carta_pdf.php` | Carta de agradecimiento. | `GET proyecto_id` / `proyecto`. |
| `mensajes.php` | Chat con el negocio. | `accion=eliminar_conversacion`, envío de mensaje. |
| `chat_stream.php` | Tiempo real SSE con fallback `?json=1`. | `GET usuario_id`. |
| `notificaciones.php` | Bandeja de avisos (mutaciones por GET). | `marcar`, `todas`, `eliminar`, `eliminar_todas`. |
| `keepalive.php` | Renueva la sesión. | Devuelve `ok`, `sesion`, `restante`. |
| `soporte.php` | Tickets de soporte. | POST + CSRF + honeypot. |
| `juego.php` | Memorama y serpiente con puntajes. | POST juego + puntaje. |
| `perfil.php`, `configuracion.php` | Datos personales, fiscales, seguridad y preferencias. | `accion=datos|fiscales|seguridad|boletin`. |
| `includes/cabecera.php` | Layout protegido con `requiere_sesion()` y contadores. | Variable PHP `$seccionPortal`. |
| `includes/pie.php` | Librerías JS y keepalive global. | Variable PHP `$seccionPortal`. |

### 10.2 Flujo completo del carrito y el pago

1. **Alta:** `productos.php` envía `accion=agregar`, `producto_id` y `cantidad`. `portal/carrito.php:56-110` valida `activo`, `precio > 0` y `stock > 0`, calcula `disponible = stock - enCarrito`, recorta la cantidad y avisa si hubo ajuste.
2. **Edición en vivo:** cada línea del carrito tiene botones `−`/`+`, `max = stock` y un `setTimeout` de 350 ms que envía `accion=actualizar` una sola vez (`portal/carrito.php:498-546`). El subtotal se previsualiza en cliente con `fmtMXN()` y se confirma con el valor devuelto por el servidor.
3. **Sincronización:** al cargar la página se ejecuta `carrito_calcular($usuarioId, true)` (`portal/carrito.php:223`), que actualiza precios y cantidades y elimina artículos inactivos o sin existencias.
4. **Checkout:** «Proceder al pago» envía `accion=resumen`, actualiza items/unidades/subtotal/total del modal y el campo oculto `monto`, y abre `#modalCheckout`.
5. **Confirmación:** el formulario del modal envía `multipart/form-data` a `portal/pagos.php` con `accion=pagar_producto`, `clave_unica`, `metodo_pago_id` y comprobante obligatorio.
6. **Validación server-side:** `portal/pagos.php:123-209` ignora el `monto` recibido, recalcula desde la BD, valida método activo y comprobante, bloquea descartados por stock, guarda el snapshot en `notas`, inserta el pago, vacía el carrito y notifica al cliente y a los administradores. La doble inserción se controla por `clave_unica` y capturando SQLSTATE `23000`.

> **Regla de negocio importante:** el stock actúa como **tope de compra**, pero no se descuenta automáticamente al pagar ni al aprobar el pago. La baja de existencias se gestiona manualmente desde `admin/productos.php` (ver §12).

### 10.3 Pagos de proyectos y facturación

- `pago_registrar()` (`funciones.php:866-965`) gestiona anticipo, restante y completo: posee `FOR UPDATE`, verifica propiedad, anticipo mínimo, tope contra saldo, idempotencia, historial y notificaciones tras el `commit`.
- `facturacion.php` exige proyecto `liquidado`/`completado` o saldo ≤ 0.01 y pagos aprobados; `factura_crear()` (`funciones.php:1159-1236`) emite con folio `FV-0001`, IVA configurable y `pagos_json`.
- `facturas_api.php`, `factura_pdf.php`, `factura_print.php`, `recibo.php`, `recibo_pdf.php`, `carta_*.php` y `descargar-entregable.php` complementan la documentación descargable. Los PDF de factura, impresión y descargas permiten administrador **o** usuario dueño.

### 10.4 Chat, notificaciones y sesión

- `mensajes.php` valida sesión, CSRF y honeypot; los mensajes se envían por formulario AJAX y se listan en tiempo real con `chat_stream.php` (SSE, bucle de hasta 240 s, `sse_ping()` cada 15 s y `sesion_tocar()` cada 60 s).
- `notificaciones.php` filtra siempre por `usuario_id`, aunque las mutaciones se hacen por GET.
- `portal/includes/pie.php` ejecuta `keepalive.php` cada 60 s y al reactivar la pestaña; si la sesión expiró, redirige a `salir`.

---

## 11. Módulo 3: Panel de administración

### 11.1 Inventario

| Archivo | Propósito | Acciones POST / permisos |
|---|---|---|
| `index.php` | Dashboard: métricas, ingresos, pendientes, actividad reciente. | Solo lectura. Protegido por cabecera. |
| `login.php` | Acceso exclusivo al panel. | POST email/password/CSRF. Público; valida `rol='admin'`. |
| `recuperar.php` | Aviso a otros administradores; nunca envía la clave. | POST email + CSRF. Público. |
| `cerrar.php`, `keepalive.php` | Cierre de sesión admin y renovación de inactividad. | Sin POST / GET AJAX. |
| `includes/cabecera.php`, `includes/pie.php` | Protección, navegación, badges, modal de expiración y keepalive. | `requiere_admin()` en la cabecera. |
| `config.php` | Identidad, contacto, hero, SMTP e IVA (`cfg_*` en `datos_sitio`). | Guardado genérico y `probar_correo`. |
| `usuarios.php` | Alta de accesos, rol, password y borrado. | `crear`, `rol`, `password`, `eliminar`. Bloquea auto-modificación. |
| `clientes.php` | Directorio de clientes: reset, activación, contacto por chat. | `password`, `activo`, `contactar`. |
| `perfil.php` | Nombre y contraseña propios. | POST con validación de clave actual. |
| `notificaciones.php` | Bandeja de avisos. | Mutaciones por GET: `marcar`, `todas`, `eliminar`. |
| `mensajes.php` | Mensajes del formulario de contacto. | `leido`, `todos`, `eliminar`. |
| `mensajes_portal.php`, `chat_stream.php` | Chat negocio ↔ clientes y stream SSE. | `eliminar_conversacion`, respuesta; `esta_admin()` directo. |
| `soporte.php` | Tickets del portal: responder, estado y borrado. | `eliminar`, actualización. **Sin `verificar_csrf()`**. |
| `servicios.php` | Servicios: precios, icono, destacado y visibilidad. | `guardar`, `eliminar`. |
| **`productos.php`** | Catálogo canónico de productos y stock. | `guardar`, `eliminar`. |
| **`pagos.php`** | Métodos de pago, pagos, comprobantes y gestión duplicada de productos. | `guardar_metodo`, `estado_pago`, `guardar_producto`, etc. |
| `facturacion.php` | Consulta de facturas, recibos, cartas y archivos; cancelación. | `cancelar_factura`. |
| `entregables.php` | Subida y borrado de entregables (≤15 MB). | `subir_entregable`, `eliminar_entregable`. |
| `procesos.php` | Ciclo de proyectos e historial. | `cambiar_estado_proyecto`. |
| `solicitudes.php` | Cotizaciones; cambio de estado con nota. | `estado`, `eliminar`. |
| `cartas.php`, `publicaciones.php`, `proyectos.php`, `testimonios.php` | Contenido público. | `guardar`, `eliminar`. |
| `suscripciones.php` | Boletín; alta/baja y exportación CSV. | `altbaja`, `eliminar`; `GET exportar=csv`. |

### 11.2 Acceso y permisos

`admin/login.php:21-29` exige `rol === 'admin'` además de `password_verify`; tras validar llama a `login_ok_admin()`. `requiere_admin()` (`funciones.php:81`) protege el resto del panel a través de `admin/includes/cabecera.php:3-4`; `admin/keepalive.php`, `admin/mensajes_portal.php` y `admin/chat_stream.php` validan por su cuenta.

No existen permisos granulares: **cualquier administrador tiene acceso total**. El rol `vendedor` queda fuera del panel. `admin/usuarios.php:15-23` impide promover a cliente a administrador y `:49-74` evita auto-modificarse o auto-eliminarse.

### 11.3 Gestión de productos y stock

📄 **`admin/productos.php:29-33` y `:58-64`**

```php
$precioP  = (float)($_POST['precio'] ?? 0);
$stockP   = (int)($_POST['stock'] ?? 0);
$activoP  = isset($_POST['activo']) ? 1 : 0;

$pdo->prepare('UPDATE productos SET titulo=?, slug=?, descripcion=?, precio=?, imagen=?, categoria=?, referencia=?, stock=?, activo=? WHERE id=?')
    ->execute([...]);
```

La eliminación limpia la imagen y las filas de `carrito` del producto. **Este archivo es la fuente única recomendada** para el catálogo: `admin/pagos.php:113-172` duplica la gestión con `slugify()` en lugar de `slug_unico()` y sin `try/catch` (deuda técnica documentada).

### 11.4 Gestión de pagos y el snapshot del carrito

- Consulta principal con joins a método, cliente, solicitud y proyecto en `admin/pagos.php:178-255`; filtros por estado, tipo y fecha.
- La tabla muestra las notas truncadas a 40 caracteres (`admin/pagos.php:281-285`) y el modal las muestra completas (`admin/pagos.php:538`).
- Aprobación/rechazo en `admin/pagos.php:55-76`, que delega en `pago_aprobar_o_rechazar()` (`funciones.php:1028-1101`): transacción con `FOR UPDATE`, vinculación de proyecto histórico, recálculo de saldo, bitácora y notificación/correo posteriores al `commit`.
- ⚠️ Si el administrador modifica el campo `notas` al aprobar, `funciones.php:1049-1050` **sobrescribe el snapshot del carrito**. Debe conservarse el valor prellenado o migrar la nota de administrador a otra columna.

### 11.5 Facturación y entregables

- El administrador **no emite** facturas: solo las consulta y cancela en `admin/facturacion.php`. La emisión la inicia el cliente desde `portal/facturacion.php`.
- La cancelación registra `factura_cancelada` en `proyecto_historial` (`admin/facturacion.php:11-20`).
- Los entregables se suben en `admin/entregables.php:12-82`, notifican al cliente y se descargan por `portal/descargar-entregable.php` con verificación de propiedad y `realpath()`.

---

## 12. Puntos importantes y decisiones técnicas

1. **Sesión de 60 minutos.** `SESION_INACTIVIDAD_MIN = 60` (`config.php:30`) con keepalive cada 60 s en portal y panel. Se eligió este valor porque la configuración previa (10 minutos, reportada por el usuario como 3 minutos en la práctica) cerraba sesiones durante la captura de comprobantes.
2. **Precio y stock siempre vigentes.** `carrito_calcular()` ignora `carrito.precio_unitario` como fuente de verdad: relee `productos.precio` y `productos.stock` y sincroniza la fila. Un cambio de precio del administrador se refleja antes de pagar.
3. **Tope exacto de inventario.** Si hay 10 unidades, el carrito nunca supera 10. Al agregar se calcula `stock - enCarrito`; al editar se aplica `min(cantidad, stock)`; al cargar o pagar se vuelve a validar.
4. **El monto no se confía al navegador.** `portal/pagos.php` recalcula el total y valida método de pago activo; un `monto` manipulado en el formulario se ignora.
5. **Costos visibles antes de pagar.** «Proceder al pago» consulta `accion=resumen` y actualiza ítems, unidades, subtotal, total y el campo `monto` del modal.
6. **Snapshot sin cambio de esquema.** No hay tabla de detalle de pedidos: el desglose se guarda como texto en `pagos.notas` y se visualiza en `admin/pagos.php`.
7. **`precio_fijo` es un flag.** En `servicios` indica modalidad de precio; el importe real está en `precio_desde`.
8. **Stock no se descuenta automáticamente.** Es una decisión actual de negocio: el inventario se ajusta manualmente en el panel. Si se requiere descuento automático, debe implementarse de forma transaccional al aprobar el pago.
9. **Dos sesiones independientes.** Cliente y administrador pueden coexistir en el mismo navegador; cerrar una no cierra la otra.
10. **Sesiones separadas y regeneración de ID.** `login_ok()` y `login_ok_admin()` regeneran `session_id()` al autenticar y al expirar por inactividad.
11. **Sin framework MVC.** Arquitectura procedural con un archivo central de utilidades; facilita el despliegue en hosting compartido pero concentra la lógica en `funciones.php`.
12. **Correo y PDF propios.** No se usa PHPMailer ni TCPDF: `enviar_correo()` implementa SMTP y `FV_PDF` genera PDF A4; la configuración SMTP vive en la base de datos.
13. **Dependencia MongoDB latente.** `composer.json` y el Dockerfile la declaran por un intento de migración en 2026-10-01, pero el código de la aplicación no la utiliza. La base real es MySQL.
14. **Errata histórica.** `facturas.rfq_cliente` guarda el RFC; se conserva por compatibilidad.
15. **Contenido HTML de confianza.** `publicacion.php` y `carta.php` imprimen el contenido WYSIWYG administrativo sin escapar; solo debe editarlo personal autorizado.

---

## 13. Seguridad: controles implementados y recomendaciones

### 13.1 Controles ya implementados

| Control | Dónde está | Detalle |
|---|---|---|
| Contraseñas con hash | `registro.php:40`, `funciones.php:89` | `password_hash(..., PASSWORD_BCRYPT)` y verificación con `password_verify()`. |
| CSRF | `funciones.php:27-40` | Token de 32 bytes aleatorios, campo oculto y comparación con `hash_equals()` en POST. Presente en registro, login, recuperación, contacto, solicitudes, carrito, pagos, facturación, soporte y chat (ver excepciones en 13.2). |
| Honeypots | Formularios públicos | Campo `empresa` (y `web` en solicitud empresarial) para descartar bots. |
| Consultas parametrizadas | Todo el código de negocio | `prepare()`/`execute()` con `PDO::ATTR_EMULATE_PREPARES=false`. |
| Escape de salida | `funciones.php:21` | `e()` para texto y atributos HTML. |
| Protección de rutas | `funciones.php:81`, `:178` | `requiere_admin()` y `requiere_sesion()` según el área. |
| Idempotencia de pagos | `funciones.php:793`, `:866` | `clave_unica` UNIQUE + captura de SQLSTATE `23000`. |
| Concurrencia financiera | `funciones.php:814`, `:1028` | `SELECT ... FOR UPDATE` y transacciones PDO. |
| Recuperación reforzada | `recuperar.php:92-159` | Código de 6 dígitos hasheado, expiración de 15 min, 5 intentos, `hash_equals` y throttle de 60 s. |
| Subidas controladas | `funciones.php:493-517` | Lista blanca de extensiones, límite de 8 MB, nombre aleatorio y borrado confinado a `UPLOADS_DIR`. |
| Entregables protegidos | `migracion_entregables.php`, `portal/descargar-entregable.php:16-33` | `.htaccess` con denegación total y verificación de propiedad + `realpath()`. |
| Errores sin exponer | `funciones.php:534-554` | `registrar_error()` escribe en `logs/errores.log`; al usuario se le muestra un mensaje genérico. |
| Sesiones | `funciones.php:44-64`, `:1484` | Cookie HttpOnly/SameSite=Lax, inactividad de 60 min, regeneración de ID y sesiones cliente/admin separadas. |
| Anti-open-redirect parcial | `funciones.php:136-152`, `:638` | Solo rutas internas y rechazo de CRLF (ver limitación en 13.2). |

### 13.2 Hallazgos y recomendaciones antes de producción

> **Prioridad CRÍTICA — eliminar `crear_usuario.php` de la raíz.** El archivo borra y reinserta un administrador con credenciales fijas (`admin@correo.com` / `pass123`) y las muestra en pantalla sin autenticación. Debe borrarse del servidor junto con `instalador.php` y los `migracion_*.php` al finalizar la instalación (§4.3).

| # | Hallazgo | Ubicación | Recomendación |
|---|---|---|---|
| 1 | `boletin.php` no verifica CSRF y usa una regex de retorno débil | `boletin.php:5-8`, `:29-31` | Añadir `verificar_csrf()` y sustituir la validación por `filtra_url_interna()`/`retorno_usar()` para evitar *open redirect* con `//dominio.com`. |
| 2 | Mutaciones por GET sin CSRF en notificaciones | `admin/notificaciones.php:11-40`, `portal/notificaciones.php`, `admin/mensajes_portal.php:65-68` | Convertir `marcar`, `todas`, `eliminar` y `eliminar_todas` a POST con `campo_csrf()` + `verificar_csrf()`. |
| 3 | `admin/soporte.php` procesa POST sin `verificar_csrf()` (aunque el formulario sí incluye el token) | `admin/soporte.php:9-52`, `:116`, `:151` | Añadir la comprobación igual que en el resto del panel. |
| 4 | Comparación de host débil en `filtra_url_interna()` | `funciones.php:638-654` (especialmente `:647`) | Usar `parse_url()` y comparar el host de forma exacta: con host `localhost`, `str_starts_with` acepta `localhost.evil.com`. |
| 5 | SMTP con verificación de certificado desactivada | `funciones.php:1348` | Activar `verify_peer` y `allow_self_signed=false` cuando se use un certificado válido. |
| 6 | `conexion.php` muestra el mensaje de error de BD con `die()` | `conexion.php:18-19` | Registrar el detalle en `logs/errores.log` y mostrar un mensaje genérico. |
| 7 | HTML WYSIWYG sin escapar | `publicacion.php:30`, `carta.php:45` | Sanitizar el contenido con una librería HTML o restringir la edición a personal de confianza. |
| 8 | Cookie de sesión sin flag `secure` | `funciones.php:44-64` | Añadir `secure => true` cuando el sitio se sirva por HTTPS. |
| 9 | Sin límite de intentos en login, registro, contacto ni boletín | `iniciar-sesion.php`, `registro.php`, `contacto.php`, `boletin.php` | Implementar *rate limiting* por IP (la recuperación y el carrito ya tienen controles parciales). |
| 10 | `pagos.notas` puede perder el snapshot del carrito | `funciones.php:1049-1050` | No sobrescribir `notas` al aprobar; migrar la nota del administrador a una columna `notas_admin`. |
| 11 | El stock no se descuenta al pagar ni al aprobar | `portal/pagos.php:183-200`, `funciones.php:1028-1101` | Definir la política de inventario y, si aplica, descontar de forma transaccional con control de concurrencia. |
| 12 | Gestión de productos duplicada | `admin/productos.php` vs `admin/pagos.php:113-172` | Conservar `admin/productos.php` como fuente única y retirar o redirigir la gestión en `admin/pagos.php`. |
| 13 | Vista previa de factura con IVA fijo `1.16` | `portal/facturacion.php:122` | Usar `factura_iva_rate()` también en la vista previa. |
| 14 | Sin control de factura duplicada por proyecto | `portal/facturacion.php`, `funciones.php:1159` | Añadir restricción de unicidad o validación previa antes de emitir. |
| 15 | `emitida_por` se toma de POST | `portal/facturacion.php:43` | Derivar el valor de la sesión autenticada. |
| 16 | Posible IDOR en la carta HTML | `portal/carta_agradecimiento.php?proyecto=` | Validar propiedad del proyecto antes de renderizar (la API `carta_api.php` sí lo hace). |
| 17 | El cliente puede eliminar pagos aprobados | `portal/pagos.php?accion=eliminar_pago` | Restringir el borrado a pagos pendientes o a administración, con auditoría. |
| 18 | `facturas_api.php?id=` usa datos fiscales del admin | `portal/facturas_api.php` | Tomar los datos de `facturas.usuario_id` o del snapshot guardado. |
| 19 | Validación de subidas solo por extensión y tamaño | `funciones.php:493-517` | Comprobar el MIME real con `finfo`/`getimagesize` y almacenar los archivos fuera del árbol público. |
| 20 | Secreto SMTP visible en el panel | `admin/config.php:77-84`, `:106`, `:30-35` | No devolver la clave guardada al formulario y validar una lista blanca de claves `cfg_*`. |
| 21 | Fallback de recuperación expone el código en pantalla | `recuperar.php:111-116`, `:211-215` | Usar solo en desarrollo; en producción, mostrar un mensaje genérico y enviar el código por correo. |
| 22 | Registro público con auto-login y sin verificación de correo | `registro.php:40-49` | Valorar verificación por correo y limitación de intentos. |
| 23 | Datos personales sin política de retención | `mensajes_contacto`, `suscripciones`, `solicitudes` | Documentar una política de retención y eliminación; considerar doble *opt-in* en el boletín. |
| 24 | Métrica heredada `vehiculos` en el dashboard | `admin/index.php:22` | Retirar el contador o confirmar su conservación como dato legacy. |

### 13.3 Credenciales por defecto (solo desarrollo)

| Archivo | Valor | Acción obligatoria |
|---|---|---|
| `conexion.php:2-7` | MySQL `root` sin contraseña en `127.0.0.1` | Crear un usuario de BD con privilegios mínimos y contraseña. |
| `instalador.php`, `crear_usuario.php` | `admin@correo.com` / `pass123` | Cambiar la contraseña desde `admin/usuarios.php` y eliminar ambos archivos del servidor. |

---

## 14. Operación, mantenimiento y pruebas

### 14.1 Tareas periódicas recomendadas

| Frecuencia | Tarea |
|---|---|
| Diaria | Revisar `logs/errores.log` y los pagos pendientes en `admin/index.php`. |
| Semanal | Verificar el tamaño de `assets/uploads/comprobantes/` y `entregables/`, y revisar la lista de suscriptores. |
| Mensual | Copia de seguridad de la base de datos **y** de `assets/uploads/` (los comprobantes y entregables no están en la BD). |
| Tras cada cambio | `php -l` en los archivos PHP modificados y `node --check` en los JS modificados. |
| Tras actualizar credenciales | Confirmar envío de correo desde `admin/config.php` → «Probar correo». |

### 14.2 Puntos de supervisión

- **Stock:** revisar `productos.stock` en `admin/productos.php`; el sistema avisa en el catálogo cuando quedan ≤3 unidades y marca «Agotado» cuando llega a 0.
- **Carrito huérfano:** si un producto se elimina, `admin/productos.php:78-93` limpia las filas de `carrito`; con MySQL sin FK a catálogo, conviene ejecutar una limpieza periódica de ítems cuyo `producto_id` ya no exista.
- **Sesiones:** el timeout es de 60 minutos. Si un usuario reporta cierres anticipados, comprobar que `portal/includes/pie.php` / `admin/includes/pie.php` cargan correctamente y que el proxy no bloquea `keepalive.php`.
- **Facturación:** verificar que `datos_sitio.factura_iva` corresponda al valor vigente antes de emitir facturas.
- **Correo:** si falla el SMTP, `recuperar.php` muestra el código en pantalla solo como modo degradado; en producción debe corregirse la configuración.

### 14.3 Pruebas manuales pendientes en navegador

Checklist para validar la última entrega del carrito y la sesión:

1. **Agregar dos veces el mismo producto:** el badge debe sumar las unidades sin exceder el stock; al llegar al límite debe aparecer el aviso «Ya tienes las N unidad(es) disponibles…».
2. **Controles `+` / `−`:** el botón `+` no debe superar `max=stock`; el `−` no debe bajar de 1; el subtotal debe actualizarse con el valor devuelto por el servidor.
3. **Tope de stock al pagar:** manipular `cantidad` directamente en la petición; `carrito_calcular()` debe recortarla al stock disponible y avisar.
4. **Recálculo al pagar:** cambiar el precio del producto en `admin/productos.php` con un artículo ya en el carrito; al pulsar «Proceder al pago» el modal debe mostrar el precio nuevo.
5. **Monto manipulado:** enviar `monto=1` en el formulario del checkout; el pago debe registrarse con el total recalculado, no con 1.
6. **Carrito vacío y sin stock:** intentar el checkout con el carrito vacío y con un producto agotado; deben mostrarse los mensajes de bloqueo.
7. **Idempotencia:** pulsar «Pagar» dos veces rápido o recargar la respuesta; debe existir un solo pago (`clave_unica` UNIQUE).
8. **Snapshot:** verificar el desglose completo en `admin/pagos.php` → modal del pago, sin que el campo `notas` sobrescriba el texto al aprobar.
9. **Sesión:** permanecer inactivo 60 minutos y confirmar el modal de expiración; comprobar que cerrar el portal no cierra el panel y viceversa.
10. **Roles:** iniciar sesión como cliente y como administrador en el mismo navegador; confirmar que `esta_admin()` y `esta_logueado()` son independientes.

### 14.4 Comandos de verificación

```bash
# Sintaxis de PHP (ejemplos principales)
php -l config.php
php -l funciones.php
php -l productos.php
php -l portal/carrito.php
php -l portal/pagos.php
php -l admin/productos.php

# Sintaxis de JavaScript
node --check assets/js/avisos.js

# Comprobar que no quedan utilidades de instalación expuestas
#Los siguientes archivos NO deben estar accesibles en producción:
# instalador.php, crear_usuario.php, migracion_*.php
```

---

## 15. Apéndices

### 15.1 Mapa rápido de rutas amigables

**Sitio público (`.htaccess`):**

| Ruta corta | Archivo real |
|---|---|
| `/clientes` | `portal/index.php` |
| `/clientes/<sección>` | `portal/<sección>` |
| `/acceso` | `iniciar-sesion.php` |
| `/crear-cuenta` | `registro.php` |
| `/recuperar-password` | `recuperar.php` |
| `/solicitud-empresa` | `solicitud-empresa.php` |
| `/productos` | `productos.php` |

**Portal (`portal/.htaccess`):** `panel`, `inicio`, `mis-solicitudes`, `procesos`, `pagos`, `facturacion`, `facturas-api`, `carrito`, `nueva-solicitud`, `nueva-solicitud-empresa`, `chat`, `juegos`, `notificaciones`, `soporte`, `mi-perfil`, `configuracion`, `salir`.

### 15.2 Glosario

| Término | Significado en el proyecto |
|---|---|
| `carrito_calcular()` | Función que recalcula ítems, cantidades y totales desde la base de datos. |
| `clave_unica` | Token de idempotencia de `pagos` que evita duplicados por doble envío. |
| `snapshot` | Desglose legible del pedido guardado como texto en `pagos.notas`. |
| `keepalive` | Petición periódica que renueva el tiempo de inactividad de la sesión. |
| `SSE` | *Server-Sent Events*: tecnología usada para el chat en tiempo real sin WebSockets. |
| `precio_fijo` | Indicador booleano de `servicios`; el importe real está en `precio_desde`. |
| `rfq_cliente` | Columna histórica de `facturas` donde se guarda el RFC del cliente. |
| `FV_PDF` | Clase propia de `lib/pdf.php` que genera los PDF sin librerías externas. |
| `datos_sitio` | Tabla de configuración clave→valor que prevalece sobre las constantes de `config.php`. |

### 15.3 Archivos de la última entrega

Estos seis archivos contienen los cambios de carrito y sesión descritos en este documento y fueron confirmados en el commit `5c5ab89` («MEJORAS»):

- `config.php` — duración de sesión de 60 minutos.
- `funciones.php` — `carrito_monto()`, `carrito_calcular()`, `form_agregar_carrito()`.
- `productos.php` — avisos de stock y formulario de compra.
- `portal/carrito.php` — acciones AJAX, resumen de checkout y steppers.
- `portal/pagos.php` — validación server-side del monto y snapshot del pedido.
- `assets/js/avisos.js` — AJAX global, eventos `fv:ajaxok`, steppers y reset del selector de cantidad.

### 15.4 Nota final de entrega

La documentación cubre el sistema FV Digital en su estado actual: sitio público, portal del cliente, panel de administración, núcleo compartido, base de datos, funciones, fragmentos de código con su ubicación exacta, decisiones técnicas y recomendaciones de seguridad. El módulo `concesionaria/` queda **fuera del alcance**; únicamente se registra la deuda técnica puntual de `admin/index.php:22` por ser una referencia inactiva dentro del panel.

**Fin de la documentación.**
