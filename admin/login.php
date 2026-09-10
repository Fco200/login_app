<?php
/* Login del panel administrativo (solo rol admin) */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (esta_admin()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $error = 'Completa tu correo y contraseña.';
        } else {
            $stmt = $pdo->prepare('SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            if ($usuario && $usuario['rol'] === 'admin' && password_verify($password, $usuario['password'])) {
                login_ok_admin($usuario);
                header('Location: index.php');
                exit;
            }
            $error = 'Credenciales inválidas o sin permisos de administrador.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso admin — FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css">
    <style>
        body { background: linear-gradient(135deg,#071c3d,#0a3d8f); min-height:100vh; }
        .card-login { max-width:400px; border-radius:16px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="card card-login shadow-lg border-0 w-100">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="../assets/img/logo.png" alt="FV Digital" style="height:70px;width:auto;object-fit:contain;">
                <h4 class="fw-bold mt-3 mb-0">Panel de administración</h4>
                <p class="text-muted small mb-0">FV Digital — Soluciones Digitales y Desarrollo</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>
            <?php mostrar_flash(); ?>
            <form method="POST" action="login.php" novalidate>
                <?= campo_csrf() ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" required autofocus autocomplete="username">
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Contraseña</label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-shield-lock me-1"></i>Entrar al panel</button>
            </form>
            <div class="text-center mt-3 small">
                <a href="../index.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i>Volver al sitio</a>
            </div>
        </div>
    </div>
</body>
</html>