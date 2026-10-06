<?php
$seccionPortal = 'configuracion';
$titulo = 'Configuración';
require_once __DIR__ . '/includes/cabecera.php';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'email' => '', 'telefono' => '', 'rfc' => '', 'direccion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }
    $accion = $_POST['accion'] ?? '';
    $id = (int)$usuario['id'];

    /* ---------- Datos personales ---------- */
    if ($accion === 'datos') {
        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        if ($nombre === '' || mb_strlen($telefono) < 10) {
            responder(['ok' => false, 'mensaje' => 'El nombre es obligatorio y el teléfono debe tener al menos 10 dígitos.', 'tipo' => 'danger']);
        }
        $GLOBALS['pdo']->prepare('UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?')
            ->execute([$nombre, $telefono, $id]);
        $_SESSION['nombre'] = $nombre;
        responder(['ok' => true, 'mensaje' => 'Tus datos se guardaron correctamente.', 'destino' => 'configuracion#datos']);
    }

    /* ---------- Datos fiscales ---------- */
    if ($accion === 'fiscales') {
        $rfc = strtoupper(trim($_POST['rfc'] ?? ''));
        $direccion = trim($_POST['direccion'] ?? '');
        if ($rfc !== '' && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{2,3}$/', $rfc)) {
            responder(['ok' => false, 'mensaje' => 'El RFC no tiene un formato válido.', 'tipo' => 'danger']);
        }
        $GLOBALS['pdo']->prepare('UPDATE usuarios SET rfc = ?, direccion = ? WHERE id = ?')
            ->execute([$rfc !== '' ? $rfc : null, $direccion !== '' ? $direccion : null, $id]);
        responder(['ok' => true, 'mensaje' => 'Tus datos fiscales se guardaron. Se usarán en tus facturas.', 'destino' => 'configuracion#fiscales']);
    }

    /* ---------- Cambiar contraseña ---------- */
    if ($accion === 'seguridad') {
        $actual = trim($_POST['password_actual'] ?? '');
        $nueva = trim($_POST['password_nueva'] ?? '');
        $confirm = trim($_POST['password_confirm'] ?? '');
        $st = $GLOBALS['pdo']->prepare('SELECT password FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $hash = $st->fetchColumn();
        if (!$hash || !password_verify($actual, $hash)) {
            responder(['ok' => false, 'mensaje' => 'La contraseña actual no es correcta.', 'tipo' => 'danger']);
        }
        if (strlen($nueva) < 6) {
            responder(['ok' => false, 'mensaje' => 'La nueva contraseña debe tener mínimo 6 caracteres.', 'tipo' => 'danger']);
        }
        if ($nueva !== $confirm) {
            responder(['ok' => false, 'mensaje' => 'La confirmación no coincide con la nueva contraseña.', 'tipo' => 'danger']);
        }
        $GLOBALS['pdo']->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_BCRYPT), $id]);
        responder(['ok' => true, 'mensaje' => 'Contraseña actualizada correctamente.', 'destino' => 'configuracion#seguridad']);
    }

    /* ---------- Preferencias / boletín ---------- */
    if ($accion === 'boletin') {
        $st = $GLOBALS['pdo']->prepare('SELECT activo FROM suscripciones WHERE email = ? LIMIT 1');
        $st->execute([$usuario['email']]);
        $existente = $st->fetch();
        if (($_POST['boletin_accion'] ?? '') === 'suscribir') {
            $GLOBALS['pdo']->prepare('INSERT INTO suscripciones (email) VALUES (?) ON DUPLICATE KEY UPDATE activo = 1')->execute([$usuario['email']]);
            $msj = 'Te suscribiste al boletín de novedades.';
        } else {
            if ($existente) {
                $GLOBALS['pdo']->prepare('UPDATE suscripciones SET activo = 0 WHERE email = ?')->execute([$usuario['email']]);
                $msj = 'Te diste de baja del boletín.';
            } else {
                $msj = 'No tenías una suscripción activa.';
            }
        }
        responder(['ok' => true, 'mensaje' => $msj, 'destino' => 'configuracion#preferencias']);
    }

    responder(['ok' => false, 'mensaje' => 'Acción no válida.', 'tipo' => 'danger']);
}

$stmtB = $GLOBALS['pdo']->prepare('SELECT activo FROM suscripciones WHERE email = ? LIMIT 1');
$stmtB->execute([$usuario['email']]);
$suscrito = (bool)(($stmtB->fetch()['activo'] ?? false));

$tab = '';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['datos', 'fiscales', 'seguridad', 'preferencias'], true)) {
    $tab = $_GET['tab'];
}
if ($tab === '') {
    $tab = (string)($_SESSION['cfg_tab'] ?? 'datos');
}
$_SESSION['cfg_tab'] = $tab;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="mb-1">Configuración</h4>
        <p class="text-muted mb-0 small">Tus datos, contraseña y preferencias, todo en un solo lugar.</p>
    </div>
    <a href="panel" class="btn btn-outline-fv btn-sm"><i class="bi bi-speedometer2 me-1"></i>Volver al panel</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card portal-card border-0 shadow-sm">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs nav-tabs-fv border-0" role="tablist">
                    <?php
                    $pestanias = [
                        'datos'       => ['bi-person-circle', 'Mis datos'],
                        'fiscales'    => ['bi-receipt', 'Datos fiscales'],
                        'seguridad'   => ['bi-shield-lock', 'Seguridad'],
                        'preferencias'=> ['bi-toggle-on', 'Preferencias'],
                    ];
                    foreach ($pestanias as $claveTab => $infoTab):
                        [$icoTab, $txtTab] = $infoTab;
                    ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $tab === $claveTab ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $claveTab ?>" type="button" role="tab">
                                <i class="bi <?= $icoTab ?> me-1"></i><?= $txtTab ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-body p-4">
                <div class="tab-content" id="configTabs">

                    <!-- Mis datos -->
                    <div class="tab-pane fade <?= $tab === 'datos' ? 'show active' : '' ?>" id="tab-datos" role="tabpanel">
                        <p class="small text-muted">Información principal de tu cuenta. El correo no se puede cambiar porque es tu identificador.</p>
                        <form method="POST" action="configuracion.php" class="js-ajax" novalidate>
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="datos">
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nombre completo</label>
                                    <input type="text" name="nombre" class="form-control" required value="<?= e($usuario['nombre']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Correo</label>
                                    <input type="email" class="form-control" value="<?= e($usuario['email']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Teléfono / WhatsApp</label>
                                    <input type="tel" name="telefono" class="form-control" required value="<?= e($usuario['telefono']) ?>">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv"><i class="bi bi-check-lg me-1"></i>Guardar datos</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Datos fiscales -->
                    <div class="tab-pane fade <?= $tab === 'fiscales' ? 'show active' : '' ?>" id="tab-fiscales" role="tabpanel">
                        <p class="small text-muted">Estos datos se usan automáticamente en tus facturas y carta de agradecimiento.</p>
                        <form method="POST" action="configuracion.php" class="js-ajax" novalidate>
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="fiscales">
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">RFC</label>
                                    <input type="text" name="rfc" class="form-control text-uppercase" maxlength="20" value="<?= e($usuario['rfc']) ?>" placeholder="AAA010101AAA">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small fw-semibold">Dirección fiscal</label>
                                    <input type="text" name="direccion" class="form-control" maxlength="255" value="<?= e($usuario['direccion']) ?>" placeholder="Calle, número, colonia, CP, ciudad">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv"><i class="bi bi-check-lg me-1"></i>Guardar datos fiscales</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Seguridad -->
                    <div class="tab-pane fade <?= $tab === 'seguridad' ? 'show active' : '' ?>" id="tab-seguridad" role="tabpanel">
                        <p class="small text-muted">Cámbiala con precaución; la usarás para iniciar sesión.</p>
                        <form method="POST" action="configuracion.php" class="js-ajax" novalidate>
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="seguridad">
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Contraseña actual</label>
                                    <input type="password" name="password_actual" class="form-control" autocomplete="current-password" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Nueva contraseña (mín. 6)</label>
                                    <input type="password" name="password_nueva" class="form-control" autocomplete="new-password" minlength="6" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Confirmar nueva</label>
                                    <input type="password" name="password_confirm" class="form-control" autocomplete="new-password" minlength="6" required>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv"><i class="bi bi-shield-lock me-1"></i>Cambiar contraseña</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Preferencias -->
                    <div class="tab-pane fade <?= $tab === 'preferencias' ? 'show active' : '' ?>" id="tab-preferencias" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                            <div>
                                <b>Boletín de novedades</b>
                                <p class="small text-muted mb-0">Tips digitales, promociones y nuevas plantillas en tu correo <?= e($usuario['email']) ?>.</p>
                            </div>
                            <form method="POST" action="configuracion.php" class="js-ajax">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="boletin">
                                <input type="hidden" name="boletin_accion" value="<?= $suscrito ? 'baja' : 'suscribir' ?>">
                                <button type="submit" class="btn btn-sm <?= $suscrito ? 'btn-success' : 'btn-outline-fv' ?>">
                                    <i class="bi <?= $suscrito ? 'bi-check-circle' : 'bi-megaphone' ?> me-1"></i>
                                    <?= $suscrito ? 'Suscrito' : 'Suscribirme' ?>
                                </button>
                            </form>
                        </div>
                        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Las notificaciones de tus proyectos llegan al correo registrado y al campanario de tu portal.</p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>