<?php
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

if (esta_logueado()) {
    header('Location: ' . destino_segun_rol());
    exit;
}

/* ---------- CAPTCHA matemático sencillo (más fácil que la pregunta) ---------- */
function generar_captcha(): array {
    $a = random_int(3, 12);
    $b = random_int(2, 9);
    $_SESSION['recupera_captcha'] = $a + $b;
    return ['a' => $a, 'b' => $b];
}
if (!isset($_SESSION['recupera_captcha'])) {
    generar_captcha();
}
$captcha = ['a' => random_int(3, 12), 'b' => random_int(2, 9)];
$_SESSION['recupera_captcha'] = $captcha['a'] + $captcha['b'];

$error = '';
$ok = false;
$valores = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $valores['email'] = trim($_POST['email'] ?? '');
        $email = filter_var($valores['email'], FILTER_VALIDATE_EMAIL);
        $password = trim($_POST['password'] ?? '');
        $confirmar = trim($_POST['confirmar'] ?? '');
        $respuestaCap = trim($_POST['captcha'] ?? '');
        $esperado = (int)($_SESSION['recupera_captcha'] ?? -1);
        unset($_SESSION['recupera_captcha']);

        if (!$email) {
            $error = 'Ingresa un correo válido.';
        } elseif ((string)$respuestaCap === '' || (int)$respuestaCap !== $esperado) {
            $error = 'La respuesta del captcha no es correcta. Intenta de nuevo.';
        } elseif (strlen($password) < 6) {
            $error = 'La nueva contraseña debe tener mínimo 6 caracteres.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            if (!$usuario) {
                $error = 'No encontramos una cuenta con ese correo.';
            } else {
                $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$usuario['id']]);
                unset($_SESSION['recupera_captcha']);
                $ok = true;
                flash('Contraseña restablecida. Ahora inicia sesión con tu nueva contraseña.');
            }
        }
    }
}

/* Si falló el captcha (o cualquier error), regeneramos uno nuevo */
if (!isset($_SESSION['recupera_captcha'])) {
    $captcha = ['a' => random_int(3, 12), 'b' => random_int(2, 9)];
    $_SESSION['recupera_captcha'] = $captcha['a'] + $captcha['b'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — <?= e(SITE_NOMBRE) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <style>
        body { background: linear-gradient(135deg,#071c3d 0%,#0a3d8f 55%,#0e5bd0 100%); min-height:100vh; }
        .card-gral { max-width:460px; border-radius:18px; border:0; }
        .mini-nav { display:flex; background:#eef3fb; border-radius:12px; padding:4px; }
        .mini-nav a { flex:1; text-align:center; padding:.5rem .25rem; border-radius:9px; color:#4a5a78; text-decoration:none; font-weight:600; font-size:.85rem; }
        .mini-nav a.act { background:#fff; color:#0a3d8f; box-shadow:0 1px 3px rgba(7,28,61,.15); }
        .captcha-caja { background:#eef3fb; border:2px dashed #0a3d8f; border-radius:12px; padding:.85rem 1rem; display:flex; align-items:center; gap:.75rem; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="d-flex flex-column align-items-center w-100">
        <a href="index.php" class="d-flex align-items-center gap-2 text-white text-decoration-none mb-4">
            <img src="assets/img/logo.png" alt="" style="height:52px;width:auto;object-fit:contain;" class="rounded">
            <span class="fs-4 fw-bold"><?= e(SITE_NOMBRE) ?></span>
        </a>

        <div class="card card-gral shadow-lg w-100">
            <div class="card-body p-4 p-md-5">
                <div class="mini-nav mb-4">
                    <a href="iniciar-sesion.php"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    <a href="registro.php" class="act"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                </div>

                <?php if ($ok): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
                        <h4 class="fw-bold mb-2">¡Contraseña restablecida!</h4>
                        <p class="text-muted small mb-4">Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.</p>
                        <a href="iniciar-sesion.php" class="btn btn-fv w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    </div>
                <?php else: ?>
                    <h4 class="fw-bold mb-1">Restablece tu contraseña</h4>
                    <p class="text-muted small mb-4">Escribe tu nueva contraseña y resuelve el captcha para verificar que eres tú.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                    <?php else: ?>
                        <?php mostrar_flash(); ?>
                    <?php endif; ?>

                    <form method="POST" action="recuperar.php" novalidate autocomplete="off">
                        <?= campo_csrf() ?>
                        <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Correo electrónico</label>
                            <input type="email" name="email" class="form-control" required autofocus autocomplete="email" value="<?= e($valores['email']) ?>" placeholder="tucorreo@ejemplo.com">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-semibold">Nueva contraseña</label>
                                <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Mín. 6 caracteres">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small fw-semibold">Confirmar</label>
                                <input type="password" name="confirmar" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Repite la contraseña">
                            </div>
                        </div>
                        <div class="captcha-caja mb-3">
                            <i class="bi bi-shield-check text-primary fs-3"></i>
                            <div class="flex-grow-1">
                                <label class="form-label small fw-semibold mb-1">Captcha: ¿Cuánto es <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?</label>
                                <input type="number" name="captcha" class="form-control form-control-sm" required placeholder="Escribe solo el número" min="0" max="999">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-key me-1"></i>Restablecer contraseña</button>
                    </form>

                    <p class="text-center small text-muted mt-4 mb-0">
                        ¿Recordaste tu clave? <a href="iniciar-sesion.php" class="fw-semibold" style="color:#0a3d8f;">Inicia sesión</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <a href="iniciar-sesion.php" class="text-white-50 text-decoration-none small mt-3"><i class="bi bi-arrow-left me-1"></i>Volver al login</a>
    </div>
</body>
</html>