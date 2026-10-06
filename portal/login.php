<?php
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (esta_logueado()) {
    header('Location: ' . destino_segun_rol());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $error = 'Completa tu correo y contraseña.';
        } else {
            $stmt = $pdo->prepare('SELECT id, nombre, email, password, rol, activo FROM usuarios WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            if (!$usuario || !password_verify($password, $usuario['password'])) {
                $error = 'Correo o contraseña incorrectos.';
            } elseif ((int)($usuario['activo'] ?? 1) !== 1) {
                $error = 'Tu cuenta está desactivada. Contacta al administrador.';
            } else {
                login_ok($usuario);
                header('Location: index.php');
                exit;
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
    <title>Portal de clientes — <?= e(SITE_NOMBRE) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <style>
        body { background: linear-gradient(135deg,#071c3d 0%,#0a3d8f 55%,#0e5bd0 100%); min-height:100vh; }
        .card-gral { max-width:430px; border-radius:18px; border:0; }
        .mini-nav { display:flex; background:#eef3fb; border-radius:12px; padding:4px; }
        .mini-nav a { flex:1; text-align:center; padding:.5rem .25rem; border-radius:9px; color:#4a5a78; text-decoration:none; font-weight:600; font-size:.85rem; }
        .mini-nav a.act { background:#fff; color:#0a3d8f; box-shadow:0 1px 3px rgba(7,28,61,.15); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="d-flex flex-column align-items-center w-100">
        <a href="../index.php" class="d-flex align-items-center gap-2 text-white text-decoration-none mb-4">
            <img src="../assets/img/logo.png" alt="" style="height:52px;width:auto;object-fit:contain;" class="rounded">
            <span class="fs-4 fw-bold"><?= e(SITE_NOMBRE) ?></span>
            <span class="badge bg-white text-primary">Portal</span>
        </a>

        <div class="card card-gral shadow-lg w-100">
            <div class="card-body p-4 p-md-5">
                <div class="mini-nav mb-4">
                    <a href="login.php" class="act"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    <a href="registro.php"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                </div>

                <h4 class="fw-bold mb-1">¡Hola de nuevo!</h4>
                <p class="text-muted small mb-4">Accede a tu portal para dar seguimiento a tus solicitudes, chatear y más.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php" novalidate>
                    <?= campo_csrf() ?>
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-envelope text-secondary"></i></span>
                            <input type="email" name="email" class="form-control" required autofocus autocomplete="email" placeholder="tucorreo@ejemplo.com">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-key text-secondary"></i></span>
                            <input type="password" name="password" id="pass" class="form-control" required autocomplete="current-password" placeholder="••••••••">
                            <button type="button" class="btn btn-outline-secondary" onclick="tooglePass()" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Entrar al portal</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    ¿Aún no tienes cuenta? <a href="registro.php" class="fw-semibold" style="color:#0a3d8f;">Regístrate gratis</a>
                </p>
            </div>
        </div>

        <a href="../index.php" class="text-white-50 text-decoration-none small mt-3"><i class="bi bi-arrow-left me-1"></i>Volver al sitio</a>
    </div>

    <script>
        function tooglePass() {
            var p = document.getElementById('pass');
            p.type = p.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>