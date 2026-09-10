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

function registrar_historial(int $solicitudId, string $estado, string $nota = ''): void {
    $stmt = $GLOBALS['pdo']->prepare('INSERT INTO solicitud_historial (solicitud_id, estado, nota) VALUES (?, ?, ?)');
    $stmt->execute([$solicitudId, $estado, $nota]);
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