<?php
/* ============================================================
   FV DIGITAL - Funciones auxiliares
   ============================================================ */

/*
 * Buffer de salida global: permite que los header('Location:...') y el JSON
 * de AJAX funcionen aunque una página ya haya emitido HTML (antes daba
 * "headers already sent" al superar los 4 KB de salida del buffer de PHP).
 * Se crea SIEMPRE (aunque ya exista un buffer del php.ini) para que ningún
 * script redirija "después de imprimir la cabecera".
 */
ob_start();

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/config.php';

/* ---------- Sanitización / escape ---------- */

function e(?string $valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/* ---------- CSRF ---------- */

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

/* ---------- Sesión / autenticación (panel admin) ---------- */

function iniciar_sesion_segura(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.gc_maxlifetime', (string)(SESION_INACTIVIDAD_MIN * 60));
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'lifetime' => SESION_INACTIVIDAD_MIN * 60,
    ]);
    session_start();

    /* Expira por inactividad: si pasaron SESION_INACTIVIDAD_MIN minutos sin
       actividad se limpia la sesion y se avisa al volver a ingresar. */
    if (isset($_SESSION['_ultimo_acceso']) && time() - (int)$_SESSION['_ultimo_acceso'] > SESION_INACTIVIDAD_MIN * 60) {
        session_regenerate_id(true);
        $_SESSION = [];
        flash('Tu sesion termino por inactividad. Vuelve a ingresar.', 'warning');
    }
    $_SESSION['_ultimo_acceso'] = time();
}

function sesion_restante_seg(): int {
    $ultimo = (int)($_SESSION['_ultimo_acceso'] ?? time());
    return max(0, SESION_INACTIVIDAD_MIN * 60 - (time() - $ultimo));
}

/*
 * Las sesiones de administrador y de cliente son INDEPENDIENTES: entrar al
 * panel no "convierte" tu perfil de cliente en admin ni al reves.
 *  - Cliente / publico  -> $_SESSION['usuario_id']
 *  - Administrador      -> $_SESSION['admin_id']
 */
function esta_admin(): bool {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0 && ($_SESSION['admin_rol'] ?? '') === 'admin';
}

function requiere_admin(): void {
    iniciar_sesion_segura();
    if (!esta_admin()) {
        header('Location: ' . RUTA_ADMIN_LOGIN);
        exit;
    }
}

function login_ok(array $usuario): void {
    session_regenerate_id(true);
    if (($usuario['rol'] ?? '') === 'admin') {
        login_ok_admin($usuario);
        return; // el admin no pisa la sesion de un cliente con la misma cuenta
    }
    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
}

function login_ok_admin(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$usuario['id'];
    $_SESSION['admin_nombre'] = $usuario['nombre'];
    $_SESSION['admin_rol'] = 'admin';
    /* OJO: no se tocan las claves de cliente ($_SESSION['usuario_id'], 'nombre',
       'rol'). Así, si el mismo usuario tenía una sesión de cliente abierta, entrar
       al panel NO lo convierte en admin: solo añade la sesión administrativa. */
}

/* ---------- URLs seguras (independientes de la carpeta del proyecto) ---------- */

function url_sitio(string $ruta = ''): string {
    static $base = null;
    if ($base === null) {
        $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $raiz = realpath(__DIR__) ?: '';
        $base = '';
        if ($doc !== '' && str_starts_with($raiz, $doc)) {
            $base = rtrim(str_replace('\\', '/', substr($raiz, strlen($doc))), '/');
        }
    }
    return $base . '/' . ltrim($ruta, '/');
}

function redirigir(string $ruta): void {
    header('Location: ' . url_sitio($ruta));
    exit;
}

/* ---------- Retorno (regresar a una página tras iniciar sesión) ----------
   Solicitudes de cotización: se pide iniciar sesión para poder darles
   seguimiento. Estos helpers guardan con seguridad la página a la que el
   usuario debe volver tras autenticarse (p. ej. el formulario de solicitud).
   Solo se aceptan rutas relativas internas; nunca URLs externas. */

function retorno_guardar(): void {
    $r = trim((string)($_POST['retorno'] ?? ($_GET['retorno'] ?? '')));
    if ($r === '' || str_contains($r, "\n") || str_contains($r, "\r") || preg_match('~^https?://~i', $r)) {
        unset($_SESSION['retorno']);
        return;
    }
    $_SESSION['retorno'] = '/' . ltrim($r, '/');
}

function retorno_usar(string $defecto = 'portal/index.php'): string {
    $r = (string)($_SESSION['retorno'] ?? '');
    unset($_SESSION['retorno']);
    if ($r === '' || str_contains($r, "\n") || str_contains($r, "\r") || preg_match('~^https?://~i', $r)) {
        return url_sitio($defecto);
    }
    return url_sitio(ltrim($r, '/'));
}

/* ---------- Sesión / autenticación (sitio público y clientes) ---------- */

function esta_logueado(): bool {
    return isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0;
}

function sesion_actual(): ?array {
    if (!esta_logueado()) {
        return null;
    }
    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function destino_segun_rol(): string {
    return esta_admin() ? 'admin/index.php' : 'portal/index.php';
}

function requiere_sesion(): void {
    iniciar_sesion_segura();
    if (!esta_logueado()) {
        redirigir('iniciar-sesion.php');
    }
}

/* ---------- Carrito ---------- */

/*
 * Renderiza el formulario "Agregar al carrito" para el sitio público.
 * $tipo: 'servicio' | 'producto' · $titulo: sólo informativo
 * Si el visitante no ha iniciado sesión lo lleva al login con retorno.
 */
function form_agregar_carrito(int $id, string $tipo, string $titulo = ''): string {
    $csrf = campo_csrf();
    if (!esta_logueado()) {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $base = url_sitio('');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = ltrim(substr($uri, strlen($base)), '/');
        }
        return '<form method="POST" action="' . e(url_sitio('iniciar-sesion.php')) . '">'
            . '<input type="hidden" name="retorno" value="' . e($uri !== '' ? $uri : 'index.php') . '">'
            . '<button type="submit" class="btn btn-outline-fv w-100 btn-agregar-carrito"><i class="bi bi-cart-plus me-1"></i>Inicia sesión para comprar</button></form>';
    }
    $campo = $tipo === 'producto' ? 'producto_id' : 'servicio_id';
    return '<form method="POST" action="' . e(url_sitio('portal/carrito.php')) . '" class="js-ajax form-agregar-carrito" data-titulo="' . e($titulo) . '">'
        . $csrf
        . '<input type="hidden" name="accion" value="agregar">'
        . '<input type="hidden" name="' . $campo . '" value="' . $id . '">'
        . '<input type="hidden" name="cantidad" value="1">'
        . '<button type="submit" class="btn btn-outline-fv w-100 btn-agregar-carrito" data-cargando="<span class=&quot;spinner-border spinner-border-sm&quot;></span>"><i class="bi bi-cart-plus me-1"></i>Agregar al carrito</button></form>';
}

function contar_carrito(int $usuarioId): int {
    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT COALESCE(SUM(cantidad),0) FROM carrito WHERE usuario_id = ?');
        $stmt->execute([$usuarioId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------- Utilidades generales ---------- */

function slugify(string $texto): string {
    $mapa = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
             'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^A-Za-z0-9\-]+/', '-', $texto);
    $texto = trim($texto, '-');
    $texto = preg_replace('/-{2,}/', '-', $texto);
    return strtolower($texto ?? '');
}

function formatear_precio(?float $cantidad): string {
    return $cantidad > 0 ? '$' . number_format($cantidad, 0) : 'A convenir';
}

function tiempo_relativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 3600) return 'hace poco';
    if ($diff < 86400) return 'hoy';
    return date('d/m/Y', strtotime($fecha));
}

/* ---------- Datos del sitio (tabla datos_sitio) ---------- */

function datos_sitio(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $stmt = $GLOBALS['pdo']->query('SELECT clave, valor FROM datos_sitio');
        foreach ($stmt as $fila) {
            $cache[$fila['clave']] = $fila['valor'];
        }
    } catch (Throwable $e) {
        // instalar aún no ha creado la tabla
    }
    return $cache;
}

function dato_sitio(string $clave, string $defecto = ''): string {
    $datos = datos_sitio();
    return isset($datos[$clave]) && $datos[$clave] !== '' ? $datos[$clave] : $defecto;
}

/* Ruta del logotipo de la marca: respeta la configuración guardada
   (logo_ruta), si existe un logo.png en la raíz lo usa y como último
   recurso el logo por defecto de assets/img/logo.png. */
function logo_sitio(): string {
    $custom = dato_sitio('logo_ruta');
    if ($custom !== '' && is_file(__DIR__ . '/' . ltrim($custom, '/'))) {
        return url_sitio(ltrim($custom, '/'));
    }
    foreach (['fv_digital_logo.png.png', 'logo.png'] as $logoRaiz) {
        if (is_file(__DIR__ . '/' . $logoRaiz)) {
            return url_sitio($logoRaiz);
        }
    }
    return url_sitio('assets/img/logo.png');
}

/* Porcentaje de IVA configurable (default 16). */
function factura_iva_rate(): float {
    $v = (float)dato_sitio('factura_iva', '16');
    return max(0.0, min(100.0, $v) / 100.0);
}

/* Ruta LOCAL del logotipo en disco (para incrustarlo en PDF vía GD). */
function logo_sitio_archivo(): string {
    $custom = dato_sitio('logo_ruta');
    if ($custom !== '' && is_file(__DIR__ . '/' . ltrim($custom, '/'))) {
        return __DIR__ . '/' . ltrim($custom, '/');
    }
    foreach (['fv_digital_logo.png.png', 'logo.png'] as $logoRaiz) {
        if (is_file(__DIR__ . '/' . $logoRaiz)) {
            return __DIR__ . '/' . $logoRaiz;
        }
    }
    $defecto = __DIR__ . '/assets/img/logo.png';
    return is_file($defecto) ? $defecto : '';
}

/* Datos del emisor para documentos (facturas, recibos, cartas). */
function datos_emisor(): array {
    return [
        'nombre'       => dato_sitio('nombre', SITE_NOMBRE),
        'eslogan'      => dato_sitio('eslogan', SITE_ESLOGAN),
        'razon_social' => dato_sitio('razon_social', ''),
        'rfc'          => dato_sitio('rfc_emisor', ''),
        'direccion'    => dato_sitio('direccion', SITE_DIRECCION),
        'telefono'     => dato_sitio('telefono', SITE_TELEFONO),
        'email'        => dato_sitio('email', SITE_EMAIL),
        'logo'         => logo_sitio_archivo(),
    ];
}

function telefono_marcar(): string {
    return 'tel:+52' . preg_replace('/\D/', '', SITE_TELEFONO);
}

function whatsapp_enlace(string $texto = ''): string {
    $mensaje = rawurlencode($texto !== '' ? $texto : 'Hola FV Digital, me interesa cotizar un servicio.');
    return 'https://wa.me/' . SITE_WHATSAPP . '?text=' . $mensaje;
}

/* ---------- Subida de archivos ---------- */

function subir_archivo(string $campo, string $carpeta, array $permitidos, int $maxMb = MAX_ARCHIVO_MB): array {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'No se recibió el archivo.'];
    }
    $archivo = $_FILES[$campo];
    $nombreOriginal = basename($archivo['name']);
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if (!in_array($ext, $permitidos, true)) {
        return ['ok' => false, 'error' => 'Tipo de archivo no permitido (.' . implode(', .', $permitidos) . ').'];
    }
    if ($archivo['size'] > $maxMb * 1024 * 1024) {
        return ['ok' => false, 'error' => "El archivo supera el límite de $maxMb MB."];
    }

    $dirBase = UPLOADS_DIR . '/' . trim($carpeta, '/');
    if (!is_dir($dirBase)) {
        @mkdir($dirBase, 0777, true);
    }
    $nombreNuevo = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

    if (!move_uploaded_file($archivo['tmp_name'], $dirBase . '/' . $nombreNuevo)) {
        return ['ok' => false, 'error' => 'No se pudo guardar el archivo.'];
    }
    return ['ok' => true, 'archivo' => 'assets/uploads/' . trim($carpeta, '/') . '/' . $nombreNuevo, 'original' => $nombreOriginal];
}

function eliminar_archivo(?string $ruta): void {
    if (!$ruta) {
        return;
    }
    $completa = realpath(__DIR__ . '/' . $ruta);
    if ($completa && str_starts_with($completa, realpath(UPLOADS_DIR)) && is_file($completa)) {
        @unlink($completa);
    }
}

/* ---------- Mensajes flash ---------- */

function flash(string $mensaje, string $tipo = 'success'): void {
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo];
}

function mostrar_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $tipo = in_array($f['tipo'], ['success', 'danger', 'warning', 'info'], true) ? $f['tipo'] : 'success';
        echo '<div class="alert alert-' . e($tipo) . ' alert-dismissible fade show shadow-sm" role="alert" data-alerta="' . e($tipo) . '">'
           . e($f['mensaje'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
}

/* ---------- AJAX / Respuestas de acciones ---------- */

function es_ajax(): bool {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/*
 * Responder() unifica el flujo de una acción:
 *  - Si la petición es AJAX devuelve JSON (la capa JS muestra el overlay animado).
 *  - Si es una petición normal hace redirect con mensaje flash.
 * $datos: ['ok', 'mensaje', 'tipo', 'destino', 'titulo']
 */
function responder(array $datos): void {
    $datos += [
        'ok'      => true,
        'mensaje' => '',
        'tipo'    => 'success',
        'destino' => null,
        'titulo'  => null,
    ];

    if (es_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($datos['ok']) {
        flash($datos['mensaje'], $datos['tipo']);
    } elseif ($datos['mensaje'] !== '') {
        flash($datos['mensaje'], $datos['tipo'] === 'success' ? 'danger' : $datos['tipo']);
    }

    if ($datos['destino']) {
        header('Location: ' . $datos['destino']);
        exit;
    }

    /*
     * Muy importante: si esto no es AJAX y no hay destino (por ejemplo el
     * chat o el guardado de puntajes), NUNCA salimos con una página en blanco
     * que deja al usuario atascado en la ruta POST. Redirigimos a un lugar
     * seguro: la página desde donde vino o el índice correspondiente.
     */
    $origen = filtra_url_interna((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $enPortal = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/portal/');
    $destinoFinal = $origen !== '' ? $origen : url_sitio($enPortal ? 'portal/index.php' : 'index.php');
    header('Location: ' . $destinoFinal);
    exit;
}

/*
 * Acepta solo URLs internas (mismo host) o rutas relativas; evita
 * open-redirect y cabeceras mal formadas.
 */
function filtra_url_interna(string $url): string {
    $url = trim($url);
    if ($url === '' || str_contains($url, "\n") || str_contains($url, "\r")) {
        return '';
    }
    if (preg_match('~^https?://~i', $url)) {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $esquema = strtolower(substr($url, 0, strpos($url, '://') + 3));
        $resto = substr($url, strpos($url, '://') + 3);
        if (str_starts_with(strtolower($resto), $host)) {
            return $url; // mismo host
        }
        return ''; // host externo → no se permite
    }
    // Ruta relativa o que inicia con '/' (no http)
    return str_starts_with($url, '/') ? $url : '/' . $url;
}

/* ---------- Notificaciones y seguimiento ---------- */

function notificar(int $usuarioId, string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): void {
    $stmt = $GLOBALS['pdo']->prepare('INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$usuarioId, $tipo, $titulo, $mensaje, $enlace]);
}

/* Notifica a TODOS los administradores activos del sistema (por ej. cuando un
   cliente inicia un proyecto o registra un pago). */
function notificar_admins(string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): int {
    $avisados = 0;
    try {
        $stmt = $GLOBALS['pdo']->query("SELECT id FROM usuarios WHERE rol = 'admin' AND activo = 1");
        foreach ($stmt as $admin) {
            notificar((int)$admin['id'], $tipo, $titulo, $mensaje, $enlace);
            $avisados++;
        }
    } catch (Throwable $e) {
        $avisados = 0;
    }
    return $avisados;
}

function registrar_historial(int $solicitudId, string $estado, string $nota = ''): void {
    $stmt = $GLOBALS['pdo']->prepare('INSERT INTO solicitud_historial (solicitud_id, estado, nota) VALUES (?, ?, ?)');
    $stmt->execute([$solicitudId, $estado, $nota]);
}

/* ---------- Flujo de proyectos de clientes (proyectos_inicio) ----------
   Estados: documentacion → anticipo_pendiente → en_desarrollo → completado.
   El anticipo se aplica cuando el cliente lo envía (estado pasa a
   'anticipo_pendiente') y el proyecto sólo avanza a 'en_desarrollo' cuando el
   administrador aprueba el pago y el mínimo está cubierto. */

/** Suma un anticipo pagado. Deja el proyecto en 'anticipo_pendiente'
 *  (a la espera de aprobación) si estaba en 'documentacion'; nunca
 *  retrocede desde 'en_desarrollo' o 'completado'. */
function proyecto_aplicar_anticipo(int $proyectoId, float $monto): void
{
    $pdo = $GLOBALS['pdo'];
    $pdo->prepare('UPDATE proyectos_inicio
                   SET anticipo_pagado = anticipo_pagado + ?,
                       estado = CASE
                           WHEN estado IN ("documentacion", "anticipo_pendiente") THEN "anticipo_pendiente"
                           ELSE estado END
                   WHERE id = ?')
        ->execute([$monto, (int)$proyectoId]);
}

/** Recalcula el estado tras aprobar/rechazar un anticipo: si el mínimo ya
 *  está cubierto pasa a 'en_desarrollo'; si no, queda en 'anticipo_pendiente'. */
function proyecto_recalcular_estado_anticipo(int $proyectoId): void
{
    $pdo = $GLOBALS['pdo'];
    $pdo->prepare("UPDATE proyectos_inicio
                   SET estado = IF(anticipo_pagado >= anticipo_minimo, 'en_desarrollo', 'anticipo_pendiente')
                   WHERE id = ? AND estado IN ('documentacion','anticipo_pendiente')")
        ->execute([(int)$proyectoId]);
}

/** Resta un monto (anticipo rechazado) y recalcula el estado del proyecto. */
function proyecto_descontar_anticipo(int $proyectoId, float $monto): void
{
    $pdo = $GLOBALS['pdo'];
    $pdo->prepare('UPDATE proyectos_inicio SET anticipo_pagado = GREATEST(0, anticipo_pagado - ?) WHERE id = ?')
        ->execute([$monto, (int)$proyectoId]);
    proyecto_recalcular_estado_anticipo($proyectoId);
}

/** Cambio manual de estado (panel admin). 'en_desarrollo' sólo aplica si el
 *  anticipo mínimo ya está cubierto. Devuelve el estado final aplicado o
 *  '' si el proyecto no existe / el estado no es válido. */
function proyecto_cambiar_estado(int $proyectoId, string $estado): string
{
    $pdo = $GLOBALS['pdo'];
    $permitidos = ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'];
    if (!in_array($estado, $permitidos, true)) return '';
    $st = $pdo->prepare('SELECT anticipo_pagado, anticipo_minimo, usuario_id FROM proyectos_inicio WHERE id = ?');
    $st->execute([(int)$proyectoId]);
    $proy = $st->fetch();
    if (!$proy) return '';
    if ($estado === 'en_desarrollo' && (float)$proy['anticipo_pagado'] < (float)$proy['anticipo_minimo']) {
        $estado = 'anticipo_pendiente';
    }
    $pdo->prepare('UPDATE proyectos_inicio SET estado = ? WHERE id = ?')->execute([$estado, (int)$proyectoId]);
    return $estado;
}

/** Datos de un proyecto con su cliente y solicitud (para notificaciones/correos). */
function proyecto_datos(int $proyectoId)
{
    $pdo = $GLOBALS['pdo'];
    $st = $pdo->prepare('SELECT pi.*, u.id AS cliente_id, u.nombre AS cliente_nombre, u.email AS cliente_email,
                                s.tipo_servicio, s.presupuesto, s.id AS sol_id
                         FROM proyectos_inicio pi
                         JOIN usuarios u ON pi.usuario_id = u.id
                         LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                         WHERE pi.id = ?');
    $st->execute([(int)$proyectoId]);
    return $st->fetch();
}

/* ============================================================
   MÓDULO DE PAGOS ROBUSTO + FACTURACIÓN
   Pagos con transacciones PDO, idempotencia por clave_unica
   (anti doble-POST), saldo_restante recalculado desde pagos
   APROBADOS y bitácora de proyectos (proyecto_historial).
   ============================================================ */

/** Parser numérico del texto de presupuesto: "$50,000 MXN" -> 50000.
 *  Si el valor es "A convenir" o no contiene números devuelve 0. */
function proyecto_total_parsear(?string $presupuesto): float {
    $texto = trim((string)$presupuesto);
    if ($texto === '') {
        return 0.0;
    }
    $nums = [];
    preg_match_all('/\d+(?:\.\d+)?/', $texto, $nums);
    if (empty($nums[0])) {
        return 0.0;
    }
    /* Si el presupuesto es un rango ("10,000 - 15,000") tomamos el tope
       superior como referencia del valor total del proyecto. */
    return (float)max($nums[0]);
}

/** Bitácora de un proyecto (traza de estados, pagos y facturas). */
function registrar_historial_proyecto(int $proyectoId, string $accion, string $detalle = '', ?int $usuarioId = null): void {
    $pdo = $GLOBALS['pdo'];
    $uid = $usuarioId !== null
        ? (int)$usuarioId
        : (int)($_SESSION['admin_id'] ?? ($_SESSION['usuario_id'] ?? 0));
    $pdo->prepare('INSERT INTO proyecto_historial (proyecto_id, usuario_id, accion, detalle) VALUES (?,?,?,?)')
        ->execute([$proyectoId, $uid > 0 ? $uid : null, $accion, $detalle]);
}

/** Token de idempotencia para un pago: único por contexto de envío. */
function pago_generar_clave(array $ctx): string {
    $base = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash_hmac('sha256', $base, bin2hex(random_bytes(16)));
}

/** Suma de pagos APROBADOS ligados a un proyecto (por proyecto_id o,
 *  para registros históricos, por solicitud_id del proyecto). */
function pago_sumar_aprobados(int $proyectoId, int $solicitudId, PDO $pdo): float {
    $st = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos
                         WHERE estado = 'aprobado'
                           AND tipo_pago IN ('anticipo','restante','completo')
                           AND (proyecto_id = ? OR (proyecto_id IS NULL AND solicitud_id = ?))");
    $st->execute([$proyectoId, $solicitudId]);
    return (float)$st->fetchColumn();
}

/** Central del ciclo financiero: recalcula pagado_total y saldo_restante
 *  y lleva el proyecto a 'liquidado' cuando el saldo se cubrió (nunca toca
 *  un proyecto 'completado'). Acepta $pdo para ejecutarse dentro de una
 *  transacción padre; si recibe null abre/cierra su propia transacción.
 *  Devuelve ['ok','proyecto_id','total','pagado','saldo','estado']. */
function proyecto_recalcular_saldo(int $proyectoId, ?PDO $pdo = null): array {
    $propio = $pdo === null;
    $pdo = $pdo ?: $GLOBALS['pdo'];
    if ($propio) {
        $pdo->beginTransaction();
    }
    try {
        $st = $pdo->prepare('SELECT id, solicitud_id, total_proyecto, pagado_total, saldo_restante, estado
                             FROM proyectos_inicio WHERE id = ? FOR UPDATE');
        $st->execute([$proyectoId]);
        $proy = $st->fetch();
        if (!$proy) {
            if ($propio) { $pdo->rollBack(); }
            return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.', 'proyecto_id' => $proyectoId];
        }
        $total = max(0, (float)$proy['total_proyecto']);
        $pagado = pago_sumar_aprobados($proyectoId, (int)$proy['solicitud_id'], $pdo);
        $saldo = max(0, round($total - $pagado, 2));
        $estado = $proy['estado'];
        /* Liquidación: total definido y saldo cubierto (tolera 0.01). */
        if ($total > 0 && $saldo <= 0.01 && $estado !== 'completado') {
            if (in_array($estado, ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'], true)) {
                $estado = 'liquidado';
            }
        }
        $pdo->prepare('UPDATE proyectos_inicio SET pagado_total = ?, saldo_restante = ?, estado = ? WHERE id = ?')
            ->execute([$pagado, $saldo, $estado, $proyectoId]);
        if ($propio) { $pdo->commit(); }
        return [
            'ok'          => true,
            'proyecto_id' => $proyectoId,
            'total'       => $total,
            'pagado'      => $pagado,
            'saldo'       => $saldo,
            'estado'      => $estado,
        ];
    } catch (Throwable $e) {
        if ($propio) { $pdo->rollBack(); }
        return ['ok' => false, 'mensaje' => $e->getMessage(), 'proyecto_id' => $proyectoId];
    }
}

/** Registro central de un pago ligado a un proyecto, con:
 *  - transacción PDO,
 *  - idempotencia por clave_unica (doble POST / doble clic / F5),
 *  - historial y notificaciones.
 *  $d: usuario_id, proyecto_id, tipo_pago (anticipo|restante|completo),
 *      monto, metodo_pago_id, comprobante, notas, clave_unica.
 *  Devuelve ['ok','ya_existia','pago_id','proyecto_id','mensaje']. */
function pago_registrar(array $d): array {
    $pdo = $GLOBALS['pdo'];
    $usuarioId = (int)($d['usuario_id'] ?? 0);
    $proyectoId = (int)($d['proyecto_id'] ?? 0);
    $tipo = (string)($d['tipo_pago'] ?? '');
    $monto = (float)($d['monto'] ?? 0);
    $metodoId = (int)($d['metodo_pago_id'] ?? 0);
    $clave = trim((string)($d['clave_unica'] ?? ''));
    $comprobante = $d['comprobante'] ?? null;
    $notas = trim((string)($d['notas'] ?? ''));

    if ($usuarioId <= 0 || $proyectoId <= 0 || $monto <= 0 || !in_array($tipo, ['anticipo', 'restante', 'completo'], true)) {
        return ['ok' => false, 'mensaje' => 'Datos inválidos para registrar el pago.'];
    }
    if ($clave === '') {
        $clave = pago_generar_clave($d);
    }

    $pdo->beginTransaction();
    try {
        /* Idempotencia: el mismo token (F5 / doble clic) nunca duplica. */
        $stK = $pdo->prepare('SELECT id FROM pagos WHERE clave_unica = ?');
        $stK->execute([$clave]);
        $ya = $stK->fetch();
        if ($ya) {
            $pdo->commit();
            return ['ok' => true, 'ya_existia' => true, 'pago_id' => (int)$ya['id'], 'mensaje' => 'Tu pago ya había sido registrado; no se duplicó.'];
        }

        /* Bloqueo del proyecto y validación de pertenencia. */
        $stP = $pdo->prepare('SELECT pi.id, pi.solicitud_id, pi.anticipo_minimo, pi.saldo_restante, pi.total_proyecto
                              FROM proyectos_inicio pi
                              WHERE pi.id = ? AND pi.usuario_id = ? FOR UPDATE');
        $stP->execute([$proyectoId, $usuarioId]);
        $proy = $stP->fetch();
        if (!$proy) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'El proyecto no existe o no te pertenece.'];
        }
        if ($tipo === 'anticipo' && $monto < (float)$proy['anticipo_minimo']) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'El anticipo mínimo es $' . number_format((float)$proy['anticipo_minimo'], 0) . ' MXN.'];
        }
        $total = (float)$proy['total_proyecto'];
        $saldoActual = (float)$proy['saldo_restante'];
        if ($total > 0 && in_array($tipo, ['restante', 'completo'], true) && $monto > $saldoActual + 0.01) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'El monto supera el saldo restante del proyecto ($' . number_format($saldoActual, 0) . ' MXN).'];
        }

        $pdo->prepare('INSERT INTO pagos (usuario_id, solicitud_id, proyecto_id, metodo_pago_id, monto, tipo_pago, comprobante, estado, notas, clave_unica)
                       VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $usuarioId,
                (int)$proy['solicitud_id'],
                $proyectoId,
                $metodoId > 0 ? $metodoId : null,
                $monto,
                $tipo,
                $comprobante,
                'pendiente',
                $notas,
                $clave,
            ]);
        $pagoId = (int)$pdo->lastInsertId();

        /* Compatibilidad legada: el anticipo enviado se refleja de inmediato
           en anticipo_pagado para el avance visual del cliente. */
        if ($tipo === 'anticipo') {
            proyecto_aplicar_anticipo($proyectoId, $monto);
        }
        registrar_historial_proyecto($proyectoId, 'pago_solicitado', 'Pago de $' . number_format($monto, 2) . ' MXN (' . $tipo . ') registrado, pendiente de aprobación.', $usuarioId);
        proyecto_recalcular_saldo($proyectoId, $pdo);

        $pdo->commit();

        /* Notificaciones (después de confirmar la escritura). */
        $cliente = sesion_actual() ?: ['id' => $usuarioId, 'nombre' => 'Cliente'];
        $nombreCliente = (string)($cliente['nombre'] ?? 'Cliente');
        notificar($usuarioId, 'pago', 'Pago de saldo registrado', 'Tu pago de $' . number_format($monto, 0) . ' MXN (' . $tipo . ') quedó registrado y pendiente de aprobación.', url_sitio('portal/pagos.php'));
        notificar_admins('pago', 'Pago pendiente de aprobación', $nombreCliente . ' registró un pago de $' . number_format($monto, 0) . ' MXN para el proyecto #' . $proyectoId . '.', url_sitio('admin/pagos.php'));

        return ['ok' => true, 'pago_id' => $pagoId, 'proyecto_id' => $proyectoId, 'ya_existia' => false, 'mensaje' => 'Pago registrado correctamente.'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        /* Carrera de doble envío: la clave única se insertó mientras tanto. */
        if ((string)$e->getCode() === '23000') {
            $stK = $pdo->prepare('SELECT id FROM pagos WHERE clave_unica = ?');
            $stK->execute([$clave]);
            $ya = $stK->fetch();
            if ($ya) {
                return ['ok' => true, 'ya_existia' => true, 'pago_id' => (int)$ya['id'], 'mensaje' => 'Tu pago ya había sido registrado; no se duplicó.'];
            }
        }
        return ['ok' => false, 'mensaje' => 'No se pudo registrar el pago: ' . $e->getMessage()];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'mensaje' => 'No se pudo registrar el pago: ' . $e->getMessage()];
    }
}

/** Notifica al cliente y envía correo sobre aprobación/rechazo de un pago. */
function notificacion_pago_correo(array $pago, string $estado, string $notasAdmin = ''): void {
    $montoTxt = '$' . number_format((float)($pago['monto'] ?? 0), 0) . ' MXN';
    $clienteId = (int)($pago['usuario_id'] ?? 0);
    $folio = 'PAG-' . str_pad((string)($pago['id'] ?? 0), 5, '0', STR_PAD_LEFT);
    $nombre = (string)($pago['cliente_nombre'] ?? ($pago['nombre'] ?? ''));
    $email = (string)($pago['cliente_email'] ?? ($pago['email'] ?? ''));

    if ($estado === 'aprobado') {
        if (($pago['tipo_pago'] ?? '') === 'producto') {
            $mensaje = 'Tu pedido por ' . $montoTxt . ' fue confirmado. Pronto procesaremos tus productos.';
            $titulo = 'Pedido confirmado';
        } elseif (($pago['tipo_pago'] ?? '') === 'anticipo') {
            $mensaje = 'Tu anticipo de ' . $montoTxt . ' fue aprobado. ¡Tu proyecto está en desarrollo!';
            $titulo = 'Pago aprobado';
        } else {
            $mensaje = 'Tu pago de ' . $montoTxt . ' fue aprobado.';
            $titulo = 'Pago aprobado';
        }
        if ($notasAdmin !== '') { $mensaje .= ' Nota del equipo: ' . $notasAdmin; }
        notificar($clienteId, 'exito', $titulo, $mensaje, url_sitio('portal/recibo.php?id=' . (int)$pago['id']));
        if ($email !== '') {
            enviar_correo(
                $email,
                $titulo . ' — ' . SITE_NOMBRE,
                correo_plantilla(
                    ($pago['tipo_pago'] ?? '') === 'producto' ? '¡Pedido confirmado!' : '¡Pago aprobado!',
                    '<p>Hola <b>' . e($nombre) . '</b>,</p>'
                    . '<p>' . e($mensaje) . '</p>'
                    . '<p>Folio: <b>' . e($folio) . '</b> · Monto: <b>' . e($montoTxt) . '</b> · Fecha: ' . e(date('d/m/Y H:i')) . '</p>'
                    . '<p style="margin:22px 0;"><a href="' . e(url_sitio('portal/recibo.php?id=' . (int)$pago['id'])) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Descargar recibo</a></p>'
                    . '<p class="small" style="color:#8a97ad;">Puedes consultar tu historial de pagos desde tu portal.</p>'
                )
            );
        }
    } else {
        $mensaje = 'Tu pago de ' . $montoTxt . ' no fue aprobado. Contáctanos para resolverlo.';
        if (($pago['tipo_pago'] ?? '') === 'anticipo') {
            $mensaje .= ' El anticipo se descontó de tu proyecto y podrás rehacer el pago desde tu portal.';
        }
        if ($notasAdmin !== '') { $mensaje .= ' Motivo: ' . $notasAdmin; }
        notificar($clienteId, 'info', 'Pago rechazado', $mensaje, url_sitio('portal/pagos.php'));
        if ($email !== '') {
            enviar_correo(
                $email,
                'Pago rechazado — ' . SITE_NOMBRE,
                correo_plantilla(
                    'Tu pago fue rechazado',
                    '<p>Hola <b>' . e($nombre) . '</b>,</p>'
                    . '<p>' . e($mensaje) . '</p>'
                    . '<p>Folio: <b>' . e($folio) . '</b></p>'
                    . '<p><a href="' . e(url_sitio('portal/pagos.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mis pagos</a></p>'
                )
            );
        }
    }
}

/** Aprueba o rechaza un pago dentro de una transacción. Recalcula el saldo
 *  del proyecto ligado (aprobación o rechazo) y deja la bitácora al día.
 *  Devuelve ['ok','ya_estaba','pago','proyecto_id','recalculo','mensaje']. */
function pago_aprobar_o_rechazar(int $pagoId, string $estado, string $notasAdmin = ''): array {
    if (!in_array($estado, ['aprobado', 'rechazado'], true)) {
        return ['ok' => false, 'mensaje' => 'Estado de pago no válido.'];
    }
    $pdo = $GLOBALS['pdo'];
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT p.*, u.email AS cliente_email, u.nombre AS cliente_nombre
                             FROM pagos p JOIN usuarios u ON p.usuario_id = u.id
                             WHERE p.id = ? FOR UPDATE');
        $st->execute([$pagoId]);
        $pago = $st->fetch();
        if (!$pago) {
            $pdo->rollBack();
            return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
        }
        if ($pago['estado'] === $estado) {
            $pdo->commit();
            return ['ok' => true, 'ya_estaba' => true, 'pago' => $pago, 'proyecto_id' => (int)($pago['proyecto_id'] ?? 0), 'mensaje' => 'El pago ya estaba en "' . $estado . '".'];
        }

        $pdo->prepare('UPDATE pagos SET estado = ?, notas = ?, aplicado = ? WHERE id = ?')
            ->execute([$estado, $notasAdmin, $estado === 'aprobado' ? 1 : 0, $pagoId]);

        /* Vincular el proyecto: los pagos nuevos ya traen proyecto_id; los
           históricos se empatan por solicitud_id del proyecto del cliente. */
        $proyectoId = (int)($pago['proyecto_id'] ?? 0);
        if ($proyectoId <= 0 && !empty($pago['solicitud_id'])) {
            $stPi = $pdo->prepare('SELECT id FROM proyectos_inicio WHERE solicitud_id = ? AND usuario_id = ?');
            $stPi->execute([(int)$pago['solicitud_id'], (int)$pago['usuario_id']]);
            $pi = $stPi->fetch();
            $proyectoId = $pi ? (int)$pi['id'] : 0;
            if ($proyectoId > 0) {
                $pdo->prepare('UPDATE pagos SET proyecto_id = ? WHERE id = ?')->execute([$proyectoId, $pagoId]);
            }
        }

        $recalculo = ['ok' => false];
        if ($proyectoId > 0) {
            /* Compatibilidad legada con el avance de anticipos. */
            if (($pago['tipo_pago'] ?? '') === 'anticipo') {
                if ($estado === 'aprobado') {
                    proyecto_recalcular_estado_anticipo($proyectoId);
                } else {
                    proyecto_descontar_anticipo($proyectoId, (float)$pago['monto']);
                }
            }
            $recalculo = proyecto_recalcular_saldo($proyectoId, $pdo);
            $saldoTxt = number_format((float)($recalculo['saldo'] ?? 0), 2);
            registrar_historial_proyecto(
                $proyectoId,
                $estado === 'aprobado' ? 'pago_aprobado' : 'pago_rechazado',
                ($estado === 'aprobado' ? 'Pago aprobado' : 'Pago rechazado') . ': ' . folio_pago_txt($pago) . ' de ' . monto_pago_txt($pago) . '. Saldo restante: $' . $saldoTxt . '.',
                (int)($_SESSION['admin_id'] ?? null)
            );
        }

        $pdo->commit();

        /* Notificaciones y correo tras confirmar la escritura. */
        notificacion_pago_correo($pago, $estado, $notasAdmin);

        return [
            'ok'          => true,
            'pago'        => $pago,
            'proyecto_id' => $proyectoId,
            'recalculo'   => $recalculo,
            'mensaje'     => 'Pago actualizado a "' . $estado . '".',
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'mensaje' => 'No se pudo actualizar el pago: ' . $e->getMessage()];
    }
}

/** Folio PAG-00001 para el texto de bitácoras. */
function folio_pago_txt(array $pago): string {
    return 'PAG-' . str_pad((string)($pago['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}

/** Monto formateado para textos de bitácoras. */
function monto_pago_txt(array $pago): string {
    return '$' . number_format((float)($pago['monto'] ?? 0), 2) . ' MXN';
}

/** Pagos aprobados y desglosados de un proyecto (para facturas y API). */
function proyecto_pagos_aprobados_detalle(int $proyectoId): array {
    $pdo = $GLOBALS['pdo'];
    $st = $pdo->prepare('SELECT pi.solicitud_id FROM proyectos_inicio pi WHERE pi.id = ?');
    $st->execute([$proyectoId]);
    $solicitudId = (int)($st->fetchColumn() ?: 0);
    $stP = $pdo->prepare("SELECT id, monto, tipo_pago, creado_en FROM pagos
                          WHERE estado = 'aprobado'
                            AND tipo_pago IN ('anticipo','restante','completo')
                            AND (proyecto_id = ? OR (proyecto_id IS NULL AND solicitud_id = ?))
                          ORDER BY creado_en ASC");
    $stP->execute([$proyectoId, $solicitudId]);
    $pagos = [];
    foreach ($stP as $p) {
        $pagos[] = [
            'id'    => (int)$p['id'],
            'folio' => 'PAG-' . str_pad((string)$p['id'], 5, '0', STR_PAD_LEFT),
            'fecha' => date('d/m/Y', strtotime($p['creado_en'])),
            'monto' => (float)$p['monto'],
            'tipo'  => $p['tipo_pago'],
        ];
    }
    return $pagos;
}

/* ---------- Facturación ---------- */

/** Siguiente folio disponible con formato FV-0001. */
function factura_folio(PDO $pdo): string {
    $n = (int)$pdo->query('SELECT COALESCE(MAX(CAST(SUBSTRING(folio, 4) AS UNSIGNED)), 0) FROM facturas')->fetchColumn();
    return 'FV-' . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
}

/** Crea una factura con transacción: insert, bitácora, notificaciones y
 *  correo al cliente. $d: proyecto_id, usuario_id, concepto, rfc_cliente,
 *  emitida_por, pagos (array de pagos aprobados). */
function factura_crear(array $d): array {
    $pdo = $GLOBALS['pdo'];
    $proyectoId = (int)($d['proyecto_id'] ?? 0);
    $usuarioId = (int)($d['usuario_id'] ?? 0);
    if ($proyectoId <= 0 || $usuarioId <= 0) {
        return ['ok' => false, 'mensaje' => 'Datos de factura inválidos.'];
    }
    $concepto = trim((string)($d['concepto'] ?? ''));
    if ($concepto === '') {
        $concepto = 'Desarrollo de proyecto';
    }
    $rfc = trim((string)($d['rfc_cliente'] ?? ''));
    $emitidaPor = trim((string)($d['emitida_por'] ?? ($_SESSION['admin_nombre'] ?? ($_SESSION['nombre'] ?? 'Portal'))));

    /* Desglose y cálculo de IVA 16% sobre los pagos aprobados. */
    $pagos = is_array($d['pagos'] ?? null) ? $d['pagos'] : [];
    $subtotal = 0.0;
    $snapshot = [];
    foreach ($pagos as $p) {
        $monto = (float)($p['monto'] ?? 0);
        $subtotal += $monto;
        $snapshot[] = [
            'id'    => (int)($p['id'] ?? 0),
            'folio' => (string)($p['folio'] ?? ('PAG-' . str_pad((string)($p['id'] ?? 0), 5, '0', STR_PAD_LEFT))),
            'fecha' => (string)($p['fecha'] ?? ''),
            'monto' => $monto,
            'tipo'  => (string)($p['tipo'] ?? 'anticipo'),
        ];
    }
    $subtotal = round($subtotal, 2);
    $iva = round($subtotal * factura_iva_rate(), 2);
    $total = round($subtotal + $iva, 2);

    $pdo->beginTransaction();
    try {
        $folio = factura_folio($pdo);
        $pdo->prepare('INSERT INTO facturas (folio, proyecto_id, usuario_id, concepto, rfq_cliente, subtotal, iva, total, pagos_json, estado, emitida_por)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $folio,
                $proyectoId,
                $usuarioId,
                $concepto,
                $rfc !== '' ? $rfc : null,
                $subtotal,
                $iva,
                $total,
                json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'emitida',
                $emitidaPor,
            ]);
        $facturaId = (int)$pdo->lastInsertId();

        $dEt = proyecto_datos($proyectoId);
        registrar_historial_proyecto($proyectoId, 'factura_emitida', 'Factura ' . $folio . ' emitida por $' . number_format($total, 2) . ' MXN.', $usuarioId);
        notificar($usuarioId, 'factura', 'Factura ' . $folio . ' emitida', 'Tu factura por $' . number_format($total, 0) . ' MXN está lista para descargar.', url_sitio('portal/facturacion.php'));
        notificar_admins('factura', 'Factura emitida', $emitidaPor . ' emitió la factura ' . $folio . ' por $' . number_format($total, 0) . ' MXN del proyecto #' . $proyectoId . '.', url_sitio('admin/facturacion.php'));
        if (!empty($dEt['cliente_email'])) {
            enviar_correo(
                $dEt['cliente_email'],
                'Factura ' . $folio . ' — ' . SITE_NOMBRE,
                correo_plantilla(
                    'Tu factura está lista',
                    '<p>Hola <b>' . e($dEt['cliente_nombre'] ?? '') . '</b>,</p>'
                    . '<p>Emitimos tu factura <b>' . e($folio) . '</b> por un total de <b>$' . number_format($total, 2) . ' MXN</b>.</p>'
                    . '<p><a href="' . e(url_sitio('portal/facturacion.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mi factura</a></p>'
                )
            );
        }

        $pdo->commit();
        return ['ok' => true, 'factura_id' => $facturaId, 'folio' => $folio, 'subtotal' => $subtotal, 'iva' => $iva, 'total' => $total];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'mensaje' => 'No se pudo emitir la factura: ' . $e->getMessage()];
    }
}

/** Datos de una factura (con cliente y proyecto). */
function factura_datos(int $id): ?array {
    $st = $GLOBALS['pdo']->prepare('SELECT f.*, u.nombre AS cliente_nombre, u.email AS cliente_email,
                                           pi.solicitud_id, s.tipo_servicio
                                    FROM facturas f
                                    LEFT JOIN usuarios u ON f.usuario_id = u.id
                                    LEFT JOIN proyectos_inicio pi ON f.proyecto_id = pi.id
                                    LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                                    WHERE f.id = ?');
    $st->execute([$id]);
    $f = $st->fetch();
    return $f ?: null;
}

/** Facturas emitidas de un proyecto (más recientes primero). */
function facturas_por_proyecto(int $proyectoId): array {
    $st = $GLOBALS['pdo']->prepare('SELECT * FROM facturas WHERE proyecto_id = ? ORDER BY creado_en DESC');
    $st->execute([$proyectoId]);
    return $st->fetchAll();
}

function contar_no_leidas(string $tabla, int $usuarioId): int {
    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT COUNT(*) FROM $tabla WHERE leida = 0 AND usuario_id = ?");
        $stmt->execute([$usuarioId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* ---------- Correo electrónico (SMTP) ----------
   La configuración se guarda en la tabla datos_sitio (pestaña
   "Correo (SMTP)" del panel) y se envía por el servidor de correo
   indicado (no requiere librerías externas). */

function smtp_config(): array {
    $d = datos_sitio();
    return [
        'host' => trim((string)($d['smtp_host'] ?? '')),
        'port' => (int)($d['smtp_port'] ?? 587),
        'user' => trim((string)($d['smtp_usuario'] ?? '')),
        'pass' => (string)($d['smtp_clave'] ?? ''),
        'from' => trim((string)($d['smtp_de'] ?? SITE_EMAIL)),
        'name' => trim((string)($d['smtp_nombre'] ?? SITE_NOMBRE)),
    ];
}

function smtp_habilitado(): bool {
    $c = smtp_config();
    return $c['host'] !== '' && $c['from'] !== '';
}

function smtp_cmd($con, string $cmd): string {
    fwrite($con, $cmd . "\r\n");
    $resp = '';
    while (($line = fgets($con, 515)) !== false) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
        if (strlen($resp) > 4096) {
            break;
        }
    }
    return $resp;
}

/*
 * Envía un correo por SMTP (soporta TLS implícito en el puerto 465 y
 * STARTTLS en el 587/25). Devuelve ['ok'=>bool, 'error'=>string].
 */
function enviar_correo(string $para, string $asunto, string $cuerpoHtml, string $textoPlano = ''): array {
    $c = smtp_config();
    if (!$c['host'] || !$c['from']) {
        return ['ok' => false, 'error' => 'SMTP no configurado.'];
    }
    $para = trim($para);
    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Destinatario inválido.'];
    }

    $asuntoEnc = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $textoPlano = $textoPlano !== '' ? $textoPlano : strip_tags(preg_replace('/<br[^>]*>/i', "\n", $cuerpoHtml));
    $nombreRemit = $c['name'] !== '' ? '=?UTF-8?B?' . base64_encode($c['name']) . '?=' . ' <' . $c['from'] . '>' : $c['from'];

    $bound = 'fv_' . bin2hex(random_bytes(8));
    $cabeceras = "To: " . $para . "\r\n"
        . "From: " . $nombreRemit . "\r\n"
        . "Reply-To: " . $c['from'] . "\r\n"
        . "Subject: " . $asuntoEnc . "\r\n"
        . "Date: " . date('r') . "\r\n"
        . "X-Mailer: FV Digital Portal\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"" . $bound . "\"\r\n";

    $cuerpo = "--" . $bound . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($textoPlano)) . "\r\n"
        . "--" . $bound . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($cuerpoHtml)) . "\r\n"
        . "--" . $bound . "--\r\n";

    $host = $c['host'];
    $port = $c['port'] > 0 ? (int)$c['port'] : 587;
    $usaTLS = (int)$port === 465;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $errno = 0; $errstr = '';
    $con = @stream_socket_client(
        ($usaTLS ? 'ssl://' : 'tcp://') . $host . ':' . $port,
        $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$con) {
        return ['ok' => false, 'error' => "No se pudo conectar con $host:$port ($errstr)."];
    }
    stream_set_timeout($con, 30);

    $nombreH = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
    $ok = true;
    $error = '';

    try {
        $resp = smtp_cmd($con, 'EHLO ' . $nombreH);
        if (!str_starts_with($resp, '220') && !str_starts_with($resp, '250')) { throw new Exception('Error en saludo: ' . $resp); }

        if (!$usaTLS && (int)$port !== 25) {
            $resp = smtp_cmd($con, 'STARTTLS');
            if (str_starts_with($resp, '220')) {
                @stream_socket_enable_crypto($con, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                smtp_cmd($con, 'EHLO ' . $nombreH);
            }
        }

        if ($c['user'] !== '') {
            $resp = smtp_cmd($con, 'AUTH LOGIN');
            if (!str_starts_with($resp, '334')) { throw new Exception('Autenticación rechazada.'); }
            smtp_cmd($con, base64_encode($c['user']));
            $resp = smtp_cmd($con, base64_encode($c['pass']));
            if (!str_starts_with($resp, '235')) { throw new Exception('Usuario o contraseña SMTP incorrectos.'); }
        }

        $resp = smtp_cmd($con, 'MAIL FROM:<' . $c['from'] . '>');
        if (!str_starts_with($resp, '250') && !str_starts_with($resp, '251')) { throw new Exception('Origen rechazado: ' . $resp); }
        $resp = smtp_cmd($con, 'RCPT TO:<' . $para . '>');
        if (!str_starts_with($resp, '250') && !str_starts_with($resp, '251')) { throw new Exception('Destinatario rechazado: ' . $resp); }

        $resp = smtp_cmd($con, 'DATA');
        if (!str_starts_with($resp, '354')) { throw new Exception('DATA rechazado: ' . $resp); }
        fwrite($con, $cabeceras . "\r\n" . $cuerpo . "\r\n.\r\n");
        $resp = smtp_cmd($con, '');
        if (strpos($resp, '550') !== false) { throw new Exception('Bandeja del servidor rechazó el correo.'); }
    } catch (Exception $e) {
        $ok = false;
        $error = $e->getMessage();
    }

    try { smtp_cmd($con, 'QUIT'); } catch (Throwable $e) {}
    fclose($con);
    return $ok ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => $error];
}

/* Cuerpo HTML estándar para los avisos por correo */
function correo_plantilla(string $titulo, string $contenidoHtml): string {
    $nombre = e(SITE_NOMBRE);
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;background:#eef3fb;font-family:Arial,Helvetica,sans-serif;color:#1c2b45;">'
        . '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #dbe4f2;">'
        . '<div style="background:#071c3d;padding:22px 28px;">'
        . '<span style="color:#fff;font-weight:bold;font-size:18px;">' . $nombre . '</span>'
        . '<span style="color:#9db8e8;font-size:13px;display:block;">Portal de clientes</span></div>'
        . '<div style="padding:28px;">'
        . '<h2 style="margin:0 0 14px;color:#0a3d8f;">' . e($titulo) . '</h2>'
        . $contenidoHtml
        . '<p style="color:#8a97ad;font-size:12px;margin-top:24px;">Este mensaje fue enviado automáticamente desde el portal de ' . $nombre . '. No respondas a este correo.</p>'
        . '</div></div></body></html>';
}

/* ---------- Estadísticas reutilizables ---------- */

function contar_registros(string $tabla, string $condicion = '1=1'): int {
    try {
        $stmt = $GLOBALS['pdo']->query("SELECT COUNT(*) FROM $tabla WHERE $condicion");
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/* Inicia la sesión de forma segura en todas las páginas del sitio
   (necesaria para CSRF en formularios públicos y mensajes flash). */
iniciar_sesion_segura();