<?php
require_once __DIR__ . '/funciones.php';
iniciar_sesion_segura();

if (esta_logueado()) {
    header('Location: ' . destino_segun_rol());
    exit;
}

$PREGUNTAS = [
    ['p' => '¿Cuál es el planeta más cercano al Sol?',
     'o' => ['Venus', 'Mercurio', 'Marte', 'Urano'],
     'r' => 1],
    ['p' => '¿Quién pintó la Mona Lisa?',
     'o' => ['Miguel Ángel', 'Pablo Picasso', 'Leonardo da Vinci', 'Vincent van Gogh'],
     'r' => 2],
    ['p' => '¿Cuál es el océano más grande del mundo?',
     'o' => ['Atlántico', 'Índico', 'Pacífico', 'Ártico'],
     'r' => 2],
    ['p' => '¿En qué año llegó Cristóbal Colón a América?',
     'o' => [1492, 1521, 1453, 1776],
     'r' => 0],
    ['p' => '¿Cuál es el país más poblado del mundo?',
     'o' => ['China', 'India', 'Estados Unidos', 'Brasil'],
     'r' => 1],
    ['p' => '¿Cuál es el símbolo químico del oro?',
     'o' => ['Ag', 'Au', 'Fe', 'Or'],
     'r' => 1],
];

if (!isset($_SESSION['recupera_q']) || !isset($PREGUNTAS[$_SESSION['recupera_q']])) {
    $_SESSION['recupera_q'] = array_rand($PREGUNTAS);
}
$qIdx = (int)$_SESSION['recupera_q'];

$error = '';
$valores = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        $error = 'La sesión expiró, intenta de nuevo.';
    } else {
        $valores['email'] = trim($_POST['email'] ?? '');
        $email = filter_var($valores['email'], FILTER_VALIDATE_EMAIL);
        $password = trim($_POST['password'] ?? '');
        $confirmar = trim($_POST['confirmar'] ?? '');
        $respuesta = (int)($_POST['respuesta'] ?? -1);

        if (!$email) {
            $error = 'Ingresa un correo válido.';
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
            } elseif (!isset($PREGUNTAS[$qIdx]) || $respuesta !== (int)$PREGUNTAS[$qIdx]['r']) {
                $error = 'La respuesta a la pregunta no es correcta.';
            } else {
                $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_BCRYPT), (int)$usuario['id']]);
                unset($_SESSION['recupera_q']);
                flash('Contraseña restablecida. Ahora inicia sesión con tu nueva contraseña.');
                header('Location: iniciar-sesion.php');
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
                    <a href="iniciar-sesion.php" class="act"><i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión</a>
                    <a href="registro.php"><i class="bi bi-person-plus me-1"></i>Crear cuenta</a>
                </div>

                <h4 class="fw-bold mb-1">Restablece tu contraseña</h4>
                <p class="text-muted small mb-4">Escribe tu nueva contraseña y responde la pregunta para verificar que eres tú.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="recuperar.php" novalidate>
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
                    <div class="alert alert-light border small p-3 mb-4">
                        <p class="mb-2 fw-semibold"><i class="bi bi-question-circle me-1 text-primary"></i><?= e($PREGUNTAS[$qIdx]['p']) ?></p>
                        <?php foreach ($PREGUNTAS[$qIdx]['o'] as $i => $opcion): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="respuesta" id="respuesta<?= $i ?>" value="<?= $i ?>" required>
                                <label class="form-check-label" for="respuesta<?= $i ?>"><?= e((string)$opcion) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-fv w-100 py-2"><i class="bi bi-key me-1"></i>Restablecer contraseña</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    ¿Recordaste tu clave? <a href="iniciar-sesion.php" class="fw-semibold" style="color:#0a3d8f;">Inicia sesión</a>
                </p>
            </div>
        </div>

        <a href="iniciar-sesion.php" class="text-white-50 text-decoration-none small mt-3"><i class="bi bi-arrow-left me-1"></i>Volver al login</a>
    </div>
</body>
</html>