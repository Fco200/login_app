<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'perfil';
$titulo = 'Mi perfil';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $nombre = trim($_POST['nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $passActual = trim($_POST['password_actual'] ?? '');
        $passNueva = trim($_POST['password_nueva'] ?? '');

        if ($nombre === '' || mb_strlen($telefono) < 10) {
            responder(['ok' => false, 'mensaje' => 'El nombre es obligatorio y el teléfono debe tener al menos 10 dígitos.', 'tipo' => 'danger']);
        }
        if ($passActual !== '' || $passNueva !== '') {
            if (!password_verify($passActual, $usuario['password'])) {
                responder(['ok' => false, 'mensaje' => 'La contraseña actual no es correcta.', 'tipo' => 'danger']);
            }
            if (strlen($passNueva) < 6) {
                responder(['ok' => false, 'mensaje' => 'La nueva contraseña debe tener mínimo 6 caracteres.', 'tipo' => 'danger']);
            }
            $pdo->prepare('UPDATE usuarios SET nombre = ?, telefono = ?, password = ? WHERE id = ?')
                ->execute([$nombre, $telefono, password_hash($passNueva, PASSWORD_BCRYPT), (int)$usuario['id']]);
        } else {
            $pdo->prepare('UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?')
                ->execute([$nombre, $telefono, (int)$usuario['id']]);
        }
        $_SESSION['nombre'] = $nombre;
        responder([
            'ok'      => true,
            'titulo'  => '¡Datos actualizados!',
            'mensaje' => 'Tu información se guardó correctamente.',
            'destino' => 'perfil.php',
        ]);
    }

    if ($accion === 'boletin') {
        $stmt = $pdo->prepare('SELECT activo FROM suscripciones WHERE email = ? LIMIT 1');
        $stmt->execute([$usuario['email']]);
        $existente = $stmt->fetch();

        if (($_POST['boletin_accion'] ?? '') === 'suscribir') {
            $pdo->prepare('INSERT INTO suscripciones (email) VALUES (?) ON DUPLICATE KEY UPDATE activo = 1')->execute([$usuario['email']]);
            $msj = 'Te suscribiste al boletín de novedades.';
        } else {
            if ($existente) {
                $pdo->prepare('UPDATE suscripciones SET activo = 0 WHERE email = ?')->execute([$usuario['email']]);
                $msj = 'Te diste de baja del boletín.';
            } else {
                $msj = 'No tenías una suscripción activa.';
            }
        }
        responder(['ok' => true, 'titulo' => 'Boletín', 'mensaje' => $msj, 'destino' => 'perfil.php#boletin']);
    }
}

require_once __DIR__ . '/includes/cabecera.php';

$stmtB = $pdo->prepare('SELECT activo FROM suscripciones WHERE email = ? LIMIT 1');
$stmtB->execute([$usuario['email']]);
$suscrito = (bool)(($stmtB->fetch()['activo'] ?? false));
?>

<div class="mb-4">
    <h4 class="mb-1">Mi perfil</h4>
    <p class="text-muted mb-0">Administra tu información, contraseña y suscripción al boletín.</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card portal-card border-0 shadow-sm">
            <div class="card-header bg-white"><b><i class="bi bi-person-circle me-1 text-primary"></i>Mis datos</b></div>
            <div class="card-body p-4">
                <p class="small text-muted">El correo no se puede cambiar porque es tu identificador de cuenta.</p>
                <form method="POST" action="perfil.php" class="js-ajax" novalidate>
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="datos">
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre completo</label>
                        <input type="text" name="nombre" class="form-control" required value="<?= e($usuario['nombre']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" class="form-control" value="<?= e($usuario['email']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Teléfono / WhatsApp</label>
                        <input type="tel" name="telefono" class="form-control" required value="<?= e($usuario['telefono']) ?>">
                    </div>
                    <button class="btn btn-fv"><i class="bi bi-check-lg me-1"></i>Guardar datos</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card portal-card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><b><i class="bi bi-key me-1 text-warning"></i>Cambiar contraseña</b></div>
            <div class="card-body p-4">
                <form method="POST" action="perfil.php" class="js-ajax" novalidate>
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="datos">
                    <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contraseña actual</label>
                        <input type="password" name="password_actual" class="form-control" autocomplete="current-password" placeholder="Si no cambias de contraseña, déjalo vacío">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nueva contraseña (mín. 6)</label>
                        <input type="password" name="password_nueva" class="form-control" autocomplete="new-password" minlength="6">
                    </div>
                    <button class="btn btn-fv"><i class="bi bi-key me-1"></i>Cambiar contraseña</button>
                </form>
            </div>
        </div>

        <div class="card portal-card border-0 shadow-sm" id="boletin">
            <div class="card-header bg-white"><b><i class="bi bi-megaphone me-1 text-danger"></i>Boletín de novedades</b></div>
            <div class="card-body p-4 text-center">
                <div class="icono-caja <?= $suscrito ? 'destacado' : '' ?> mx-auto mb-3" style="width:56px;height:56px;font-size:1.3rem;"><i class="bi bi-megaphone"></i></div>
                <h6 class="mb-1"><?= $suscrito ? 'Estás suscrito al boletín' : 'No estás suscrito al boletín' ?></h6>
                <p class="text-muted small mb-3">Tips digitales, promociones y nuevas plantillas en tu correo <?= e($usuario['email']) ?>.</p>
                <form method="POST" action="perfil.php" class="js-ajax">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="boletin">
                    <input type="hidden" name="boletin_accion" value="<?= $suscrito ? 'baja' : 'suscribir' ?>">
                    <button type="submit" class="btn btn-<?= $suscrito ? 'outline-danger' : 'fv' ?>">
                        <i class="bi <?= $suscrito ? 'bi-dash-circle' : 'bi-megaphone' ?> me-1"></i>
                        <?= $suscrito ? 'Darme de baja' : 'Suscribirme al boletín' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>