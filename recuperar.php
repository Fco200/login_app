<?php
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

if (esta_logueado()) {
    header('Location: ' . destino_segun_rol());
    exit;
}

/* ---------- Tabla de códigos de recuperación ---------- */
function asegurar_tabla_reset(PDO $pdo): void {
    static $listo = false;
    if ($listo) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reseteos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            email VARCHAR(190) NOT NULL,
            codigo_hash CHAR(64) NOT NULL,
            intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
            usado TINYINT(1) NOT NULL DEFAULT 0,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY usuario_id (usuario_id),
            KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
    }
    $listo = true;
}
asegurar_tabla_reset($pdo);

function email_enmascarado(string $email): string {
    $partes = array_pad(explode('@', $email, 2), 2, '');
    $local = $partes[0];
    $l = mb_strlen($local);
    $visible = $l <= 2
        ? $local
        : mb_substr($local, 0, 1) . str_repeat('*', max(1, $l - 2)) . mb_substr($local, -1, 1);
    return $visible . '@' . $partes[1];
}

function codigo_valido_reciente(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT * FROM password_reseteos WHERE email = ? AND usado = 0 AND creado_en >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

$error = '';
$ok = false;
$paso = ((int)($_GET['paso'] ?? $_POST['paso'] ?? 1)) === 2 ? 2 : 1;
$valores = ['email' => '', 'codigo' => ''];
$emailSesion = trim((string)($_SESSION['reset_email'] ?? ''));

/* Si intenta entrar al paso 2 sin haber pedido código, volvemos al paso 1 */
if ($paso === 2 && $emailSesion === '') {
    $paso = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $accion = (string)($_POST['accion'] ?? '');

        /* ----- Paso 1: pedir código ----- */
        if ($accion === 'enviar') {
            $valores['email'] = trim($_POST['email'] ?? '');
            $email = filter_var($valores['email'], FILTER_VALIDATE_EMAIL);

            if (!$email) {
                $error = 'Ingresa un correo válido.';
            } else {
                $stmt = $pdo->prepare('SELECT id, email, activo FROM usuarios WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if (!$usuario) {
                    $error = 'No encontramos una cuenta con ese correo.';
                } elseif ((int)($usuario['activo'] ?? 1) !== 1) {
                    $error = 'Tu cuenta está desactivada. Contacta al administrador.';
                } else {
                    $ultimo = $pdo->prepare('SELECT COUNT(*) FROM password_reseteos WHERE usuario_id = ? AND creado_en >= DATE_SUB(NOW(), INTERVAL 60 SECOND)');
                    $ultimo->execute([(int)$usuario['id']]);
                    $recientes = (int)$ultimo->fetchColumn();

                    if ($recientes > 0) {
                        $error = 'Ya enviamos un código. Espera un minuto antes de pedir otro.';
                    } else {
                        $codigo = (string)random_int(100000, 999999);
                        $pdo->prepare('UPDATE password_reseteos SET usado = 1 WHERE usuario_id = ?')
                            ->execute([(int)$usuario['id']]);
                        $pdo->prepare('INSERT INTO password_reseteos (usuario_id, email, codigo_hash) VALUES (?, ?, ?)')
                            ->execute([(int)$usuario['id'], $email, hash('sha256', $codigo)]);

                        $html = '<p style="margin:0 0 12px;">Hola, recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>'
                            . '<div style="background:#eef3fb;border-radius:12px;text-align:center;padding:18px;margin:8px 0 16px;">'
                            . '<div style="font-size:32px;font-weight:bold;letter-spacing:10px;color:#0a3d8f;">' . $codigo . '</div>'
                            . '</div>'
                            . '<p style="margin:0 0 6px;">Escribe este código en el formulario para continuar. <b>Vence en 15 minutos.</b></p>'
                            . '<p style="margin:0;">Si no fuiste tú, ignora este correo: tu contraseña no cambia.</p>';
                        $envio = enviar_correo(
                            $email,
                            'Tu código para restablecer tu contraseña',
                            $html,
                            "Tu código de recuperación es: $codigo\nVence en 15 minutos.\nSi no fuiste tú, ignora este correo."
                        );

                        if (!$envio['ok']) {
                            error_log('Recuperar contraseña: ' . $envio['error']);
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_codigo_visible'] = $codigo;
                            $_SESSION['reset_sin_correo'] = true;
                            flash('No pudimos enviarte el código por correo, así que te lo mostramos aquí.', 'warning');
                        } else {
                            $_SESSION['reset_email'] = $email;
                            unset($_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
                            flash('Te enviamos un código de 6 dígitos a tu correo.');
                        }
                        header('Location: recuperar.php?paso=2');
                        exit;
                    }
                }
            }
            $paso = 1;
        }

        /* ----- Paso 2: verificar código y guardar contraseña ----- */
        if ($accion === 'verificar') {
            $email = $emailSesion;
            $valores['codigo'] = trim($_POST['codigo'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $confirmar = trim($_POST['confirmar'] ?? '');

            if ($email === '') {
                $error = 'La sesión expiró, solicita un código nuevo.';
                $paso = 1;
            } elseif (!preg_match('/^\d{6}$/', $valores['codigo'])) {
                $error = 'El código tiene 6 dígitos.';
                $paso = 2;
            } elseif (strlen($password) < 6) {
                $error = 'La nueva contraseña debe tener mínimo 6 caracteres.';
                $paso = 2;
            } elseif ($password !== $confirmar) {
                $error = 'Las contraseñas no coinciden.';
                $paso = 2;
            } else {
                $fila = codigo_valido_reciente($pdo, $email);
                if (!$fila) {
                    $error = 'El código expiró o ya no es válido. Pide uno nuevo.';
                    $paso = 2;
                } elseif ((int)$fila['intentos'] >= 5) {
                    $pdo->prepare('UPDATE password_reseteos SET usado = 1 WHERE id = ?')->execute([(int)$fila['id']]);
                    $error = 'Demasiados intentos. Solicita un código nuevo.';
                    $paso = 2;
                } elseif (!hash_equals((string)$fila['codigo_hash'], hash('sha256', $valores['codigo']))) {
                    $pdo->prepare('UPDATE password_reseteos SET intentos = intentos + 1 WHERE id = ?')->execute([(int)$fila['id']]);
                    $error = 'El código no es correcto. Revisa tu correo.';
                    $paso = 2;
                } else {
                    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $usuario = $stmt->fetch();

                    if (!$usuario) {
                        $error = 'No encontramos la cuenta. Solicita un código nuevo.';
                        $paso = 1;
                        unset($_SESSION['reset_email'], $_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
                    } else {
                        $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
                            ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$usuario['id']]);
                        $pdo->prepare('UPDATE password_reseteos SET usado = 1 WHERE usuario_id = ?')
                            ->execute([(int)$usuario['id']]);
                        unset($_SESSION['reset_email'], $_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
                        $ok = true;
                    }
                }
            }
        }

        /* ----- Reenviar el mismo código ----- */
        if ($accion === 'reenviar') {
            $email = $emailSesion;
            $fila = $email !== '' ? codigo_valido_reciente($pdo, $email) : null;
            if (!$fila) {
                $error = 'El código expiró. Vuelve al paso anterior y pide uno nuevo.';
                $paso = 1;
                unset($_SESSION['reset_email'], $_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
            } else {
                $reciente = $pdo->prepare('SELECT COUNT(*) FROM password_reseteos WHERE id = ? AND creado_en >= DATE_SUB(NOW(), INTERVAL 60 SECOND)');
                $reciente->execute([(int)$fila['id']]);
                if ((int)$reciente->fetchColumn() > 0) {
                    $error = 'Espera un minuto antes de reenviar el código.';
                    $paso = 2;
                } else {
                    $codigo = (string)random_int(100000, 999999);
                    $pdo->prepare('UPDATE password_reseteos SET codigo_hash = ?, intentos = 0, creado_en = NOW() WHERE id = ?')
                        ->execute([hash('sha256', $codigo), (int)$fila['id']]);

                    $html = '<p style="margin:0 0 12px;">Aquí tienes un código nuevo para restablecer tu contraseña:</p>'
                        . '<div style="background:#eef3fb;border-radius:12px;text-align:center;padding:18px;margin:8px 0 16px;">'
                        . '<div style="font-size:32px;font-weight:bold;letter-spacing:10px;color:#0a3d8f;">' . $codigo . '</div>'
                        . '</div>'
                        . '<p style="margin:0 0 6px;"><b>Vence en 15 minutos.</b></p>'
                        . '<p style="margin:0;">Si no fuiste tú, ignora este correo: tu contraseña no cambia.</p>';
                    $envio = enviar_correo($email, 'Tu código para restablecer tu contraseña', $html,
                        "Tu código de recuperación es: $codigo\nVence en 15 minutos.");

                    if (!$envio['ok']) {
                        error_log('Recuperar contraseña (reenvío): ' . $envio['error']);
                        $_SESSION['reset_codigo_visible'] = $codigo;
                        $_SESSION['reset_sin_correo'] = true;
                        flash('No pudimos reenviar el correo, así que te mostramos tu código aquí.', 'warning');
                    } else {
                        unset($_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
                        flash('Te enviamos un código nuevo a tu correo.');
                    }
                    header('Location: recuperar.php?paso=2');
                    exit;
                }
            }
        }
    }
}

/* Al volver al paso 1 (o pedir otro correo) ya no mostramos un código anterior */
if ($paso === 1) {
    unset($_SESSION['reset_codigo_visible'], $_SESSION['reset_sin_correo']);
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
        .pasos { display:flex; gap:.4rem; }
        .pasos .paso { flex:1; text-align:center; font-size:.72rem; font-weight:700; color:#8a97ad; background:#eef3fb; border-radius:9px; padding:.45rem .2rem; }
        .pasos .paso.act { background:#0a3d8f; color:#fff; }
        .pasos .paso.ok { background:#d9f2e6; color:#087f5b; }
        .codigo-caja { background:#eef3fb; border:2px dashed #0a3d8f; border-radius:12px; padding:.9rem 1rem; text-align:center; }
        .codigo-caja input { font-size:1.5rem; letter-spacing:.6rem; text-align:center; font-weight:700; }
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
                    <a href="registro.php"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                </div>

                <?php if ($ok): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
                        <h4 class="fw-bold mb-2">¡Contraseña restablecida!</h4>
                        <p class="text-muted small mb-4">Tu contraseña fue actualizada correctamente. Ya puedes iniciar sesión.</p>
                        <a href="iniciar-sesion.php" class="btn btn-fv w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    </div>
                <?php else: ?>
                    <div class="pasos mb-4">
                        <span class="paso <?= $paso === 1 ? 'act' : 'ok' ?>"><i class="bi bi-envelope me-1"></i>1. Tu correo</span>
                        <span class="paso <?= $paso === 2 ? 'act' : '' ?>"><i class="bi bi-shield-check me-1"></i>2. Código</span>
                        <span class="paso"><i class="bi bi-key me-1"></i>3. Nueva clave</span>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                    <?php else: ?>
                        <?php mostrar_flash(); ?>
                    <?php endif; ?>

                    <?php if ($paso === 1): ?>
                        <h4 class="fw-bold mb-1">Recupera tu contraseña</h4>
                        <p class="text-muted small mb-4">Escribe el correo de tu cuenta y te enviaremos un <b>código de 6 dígitos</b>. Sin preguntas ni captchas.</p>

                        <form method="POST" action="recuperar.php" novalidate autocomplete="off">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="paso" value="1">
                            <input type="hidden" name="accion" value="enviar">
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Correo electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-envelope text-secondary"></i></span>
                                    <input type="email" name="email" class="form-control" required autofocus autocomplete="email" value="<?= e($valores['email']) ?>" placeholder="tucorreo@ejemplo.com">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-send me-1"></i>Enviar código a mi correo</button>
                        </form>
                    <?php else: ?>
                        <h4 class="fw-bold mb-1">Escribe el código</h4>
                        <?php if (!empty($_SESSION['reset_codigo_visible'])): ?>
                            <p class="text-muted small mb-2">El correo no está disponible en este momento, así que tu código de recuperación es:</p>
                            <div class="alert py-3 text-center" style="background:#d9f2e6;border:2px dashed #087f5b;border-radius:12px;">
                                <div style="font-size:2rem;font-weight:800;letter-spacing:.5rem;color:#087f5b;font-family:monospace;" id="codigoVisible"><?= e((string)$_SESSION['reset_codigo_visible']) ?></div>
                                <small class="text-muted d-block mt-1">Vence en 15 minutos ·
                                    <a href="#" onclick="copiarCodigo(event);return false;" class="fw-semibold text-decoration-none" style="color:#087f5b;"><i class="bi bi-clipboard me-1"></i>Copiar</a>
                                </small>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-4">
                                Enviamos un código de 6 dígitos a <b><?= e(email_enmascarado($emailSesion)) ?></b>.
                                Revéalo en tu correo y escríbelo junto con tu nueva contraseña.
                            </p>
                        <?php endif; ?>

                        <form method="POST" action="recuperar.php?paso=2" novalidate autocomplete="off">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="paso" value="2">
                            <input type="hidden" name="accion" value="verificar">
                            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                            <div class="codigo-caja mb-3">
                                <label class="form-label small fw-semibold mb-2"><i class="bi bi-shield-check me-1"></i>Código de 6 dígitos</label>
                                <input type="text" inputmode="numeric" pattern="\d*" maxlength="6" name="codigo" class="form-control form-control-lg" required autofocus placeholder="000000" value="<?= e($valores['codigo']) ?>">
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
                            <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-key me-1"></i>Restablecer contraseña</button>
                        </form>

                        <div class="d-flex justify-content-between align-items-center mt-3 small">
                            <form method="POST" action="recuperar.php?paso=2" class="m-0">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="paso" value="2">
                                <input type="hidden" name="accion" value="reenviar">
                                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none" style="color:#0a3d8f;"><i class="bi bi-arrow-clockwise me-1"></i>Reenviar código</button>
                            </form>
                            <a href="recuperar.php" class="text-decoration-none" style="color:#0a3d8f;"><i class="bi bi-envelope me-1"></i>Usar otro correo</a>
                        </div>
                    <?php endif; ?>

                    <p class="text-center small text-muted mt-4 mb-0">
                        ¿Recordaste tu clave? <a href="iniciar-sesion.php" class="fw-semibold" style="color:#0a3d8f;">Inicia sesión</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <a href="iniciar-sesion.php" class="text-white-50 text-decoration-none small mt-3"><i class="bi bi-arrow-left me-1"></i>Volver al login</a>
    </div>
    <script>
    function copiarCodigo(ev) {
        var el = document.getElementById('codigoVisible');
        if (!el || !navigator.clipboard) return;
        navigator.clipboard.writeText(el.textContent.trim()).then(function () {
            if (ev && ev.currentTarget) {
                var a = ev.currentTarget;
                a.textContent = '¡Copiado!';
                setTimeout(function () { a.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copiar'; }, 1500);
            }
        }).catch(function () {});
    }
    </script>
</body>
</html>
