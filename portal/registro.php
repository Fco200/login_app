<?php
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (esta_logueado()) {
    header('Location: ' . destino_segun_rol());
    exit;
}

$error = '';
$valores = ['nombre' => '', 'email' => '', 'telefono' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $valores = [
            'nombre'   => trim($_POST['nombre'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
        ];
        $password = trim($_POST['password'] ?? '');
        $confirmar = trim($_POST['confirmar'] ?? '');

        $email = filter_var($valores['email'], FILTER_VALIDATE_EMAIL);

        if ($valores['nombre'] === '') {
            $error = 'El nombre es obligatorio.';
        } elseif (!$email) {
            $error = 'Ingresa un correo válido.';
        } elseif (mb_strlen($valores['telefono']) < 10) {
            $error = 'Ingresa un número de teléfono válido (10 dígitos).';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener mínimo 6 caracteres.';
        } elseif ($password !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            try {
                $pdo->prepare('INSERT INTO usuarios (nombre, email, telefono, password, rol, activo) VALUES (?, ?, ?, ?, ?, 1)')
                    ->execute([$valores['nombre'], $email, $valores['telefono'], password_hash($password, PASSWORD_BCRYPT), 'cliente']);
                $nuevo = [
                    'id'     => (int)$pdo->lastInsertId(),
                    'nombre' => $valores['nombre'],
                    'rol'    => 'cliente',
                ];
                login_ok($nuevo);
                notificar((int)$nuevo['id'], 'bienvenida_portal', '¡Bienvenido a tu portal!',
                    'Aquí puedes seguir tus solicitudes, chatear con nosotros y jugar mientras esperas.', 'index.php');
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Ese correo ya está registrado. Intenta iniciar sesión.';
                } else {
                    $error = 'No se pudo crear la cuenta. Intenta de nuevo.';
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
    <title>Crear cuenta — Portal <?= e(SITE_NOMBRE) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/estilos.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <style>
        body { background: linear-gradient(135deg,#071c3d 0%,#0a3d8f 55%,#0e5bd0 100%); min-height:100vh; }
        .card-gral { max-width:460px; border-radius:18px; border:0; }
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
                    <a href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    <a href="registro.php" class="act"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                </div>

                <h4 class="fw-bold mb-1">Crea tu cuenta gratis</h4>
                <p class="text-muted small mb-4">Da seguimiento a tus cotizaciones, chatea con nosotros y recibe beneficios personalizados.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="registro.php" novalidate>
                    <?= campo_csrf() ?>
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre completo *</label>
                        <input type="text" name="nombre" class="form-control" required value="<?= e($valores['nombre']) ?>" placeholder="Ej. Juan Pérez">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo electrónico *</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($valores['email']) ?>" placeholder="tucorreo@ejemplo.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Teléfono / WhatsApp *</label>
                        <input type="tel" name="telefono" class="form-control" required value="<?= e($valores['telefono']) ?>" placeholder="662 123 4567">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Contraseña *</label>
                            <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Mín. 6 caracteres">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-semibold">Confirmar *</label>
                            <input type="password" name="confirmar" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Repite la contraseña">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-person-check me-1"></i>Crear mi cuenta</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    ¿Ya tienes cuenta? <a href="login.php" class="fw-semibold" style="color:#0a3d8f;">Inicia sesión</a>
                </p>
            </div>
        </div>

        <a href="../index.php" class="text-white-50 text-decoration-none small mt-3"><i class="bi bi-arrow-left me-1"></i>Volver al sitio</a>
    </div>
</body>
</html>