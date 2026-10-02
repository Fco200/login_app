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
require_once __DIR__ . '/includes/repos.php';

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
 *  - Cliente / publico  -> $_SESSION['usuario_id']  (hex de 24)
 *  - Administrador      -> $_SESSION['admin_id']
 */
function esta_admin(): bool {
    return isset($_SESSION['admin_id']) && (string)$_SESSION['admin_id'] !== '' && ($_SESSION['admin_rol'] ?? '') === 'admin';
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
    $_SESSION['usuario_id'] = (string)$usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
}

function login_ok_admin(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (string)$usuario['id'];
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
    return isset($_SESSION['usuario_id']) && oid($_SESSION['usuario_id']) !== null;
}

/** Cliente de la sesión actual (MongoDB) o null. */
function sesion_actual(): ?array {
    if (!esta_logueado()) {
        return null;
    }
    try {
        return usr_por_id($_SESSION['usuario_id']);
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
function form_agregar_carrito($id, string $tipo, string $titulo = ''): string {
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

/* ---------- Datos del sitio (colección datos_sitio) ---------- */

function datos_sitio(): array {
    return sitio_todas();
}

function dato_sitio(string $clave, string $defecto = ''): string {
    return sitio_valor($clave, $defecto);
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

/* ---------- Alias de compatibilidad ----------
   La lógica de negocio (notificaciones, historial, proyectos, pagos y
   facturación) vive ahora en includes/repos/*.php sobre MongoDB. Estos
   alias existen únicamente para que las vistas que todavía no se han
   migrado sigan funcionando; se eliminan conforme se migra cada página. */

function registrar_historial($solicitudId, string $estado, string $nota = ''): void {
    sol_registrar_historial($solicitudId, $estado, $nota);
}

function factura_datos($id): ?array {
    return factura_por_id($id);
}

/* ---------- Correo electrónico (SMTP) ----------
   La configuración se guarda en la colección `datos_sitio` (pestaña
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

/* Las estadísticas y conteos viven en los repositorios:
   contar_registros($coleccion, $filtroMongo) está en includes/repos/crud.php. */

/* Inicia la sesión de forma segura en todas las páginas del sitio
   (necesaria para CSRF en formularios públicos y mensajes flash). */
iniciar_sesion_segura();
