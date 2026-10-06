<?php
/* Recuperación de contraseña del panel administrativo.
   El admin solicita restablecer su clave y se notifica a los
   DEMÁS administradores para que gestionen el cambio desde
   Usuarios (la contraseña nunca se expone por correo). */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (esta_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$valores = ['email' => ''];
$enviada = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $valores['email'] = trim($_POST['email'] ?? '');
        $email = filter_var($valores['email'], FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $error = 'Ingresa un correo válido.';
        } else {
            $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE LOWER(email) = LOWER(?) AND rol = 'admin' LIMIT 1");
            $stmt->execute([$email]);
            $solicitante = $stmt->fetch();

            if (!$solicitante) {
                $error = 'No encontramos un administrador con ese correo.';
            } else {
                /* Notificar a los demás admins para que gestionen el restablecimiento */
                $stmtOtros = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'admin' AND id != " . (int)$solicitante['id'] . " AND activo = 1");
                $otros = $stmtOtros->fetchAll();

                if (!$otros) {
                    $error = 'No hay otro administrador disponible para gestionar la solicitud. Contacta al soporte del sistema.';
                } else {
                    $mensajePredeterminado = 'El administrador ' . $solicitante['nombre'] . ' (' . $solicitante['email'] . ') solicitó restablecer su contraseña. Por favor ingresa a Usuarios y asígnale una nueva clave con su autorización.';
                    foreach ($otros as $otro) {
                        notificar((int)$otro['id'], 'seguridad', 'Solicitud de restablecimiento de contraseña', $mensajePredeterminado, url_sitio('admin/usuarios.php'));
                    }
                    /* También avisamos al propio solicitante para que sepa que fue enviado */
                    notificar((int)$solicitante['id'], 'info', 'Solicitud de recuperación enviada', 'Tu solicitud fue enviada a los demás administradores. Ellos gestionarán el restablecimiento de tu contraseña.', url_sitio('admin/login.php'));
                    $enviada = true;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña — FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css">
    <style>
        body { background: linear-gradient(135deg,#071c3d,#0a3d8f); min-height:100vh; }
        .card-login { max-width:420px; border-radius:16px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="card card-login shadow-lg border-0 w-100">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="../assets/img/logo.png" alt="FV Digital" style="height:70px;width:auto;object-fit:contain;">
                <h4 class="fw-bold mt-3 mb-0">Recuperar contraseña</h4>
                <p class="text-muted small mb-0">Pedimos restablecer la clave de tu cuenta admin.</p>
            </div>

            <?php if ($enviada): ?>
                <div class="alert alert-success py-3">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <b>Solicitud enviada.</b>
                    <p class="small mb-0 mt-1">Se notificó a los demás administradores del sistema para que gestionen el restablecimiento de tu contraseña. Ellos te avisarán cuando esté lista.</p>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>
                <?php mostrar_flash(); ?>

                <form method="POST" action="recuperar.php" novalidate>
                    <?= campo_csrf() ?>
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo del administrador</label>
                        <input type="email" name="email" class="form-control" required autofocus autocomplete="username" value="<?= e($valores['email']) ?>" placeholder="admin@correo.com">
                    </div>
                    <div class="alert alert-info small py-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Al enviar la solicitud, se notificará a <b>los demás administradores</b> con un mensaje predeterminado para que gestionen el restablecimiento de tu contraseña.
                    </div>
                    <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-send me-1"></i>Enviar solicitud</button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3 small">
                <a href="login.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Volver al acceso</a>
            </div>
        </div>
    </div>
</body>
</html>