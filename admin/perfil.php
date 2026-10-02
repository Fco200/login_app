<?php
$titulo = 'Mi perfil';
$subtitulo = 'Actualiza tus datos de acceso';
$seccionAdmin = 'perfil.php';

require_once __DIR__ . '/includes/cabecera.php';

$miId = (string)($_SESSION['admin_id'] ?? $_SESSION['usuario_id'] ?? '');
$yo = usr_por_id($miId) ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $nombre = trim($_POST['nombre']);
    $passwordActual = trim($_POST['password_actual']);
    $passwordNueva = trim($_POST['password_nueva']);

    if ($nombre === '') {
        flash('El nombre no puede estar vacío.', 'danger');
    } elseif ($passwordActual !== '' || $passwordNueva !== '') {
        if (!$yo || !usr_verificar_password($yo, $passwordActual)) {
            flash('La contraseña actual no es correcta.', 'danger');
        } elseif (strlen($passwordNueva) < 6) {
            flash('La nueva contraseña debe tener mínimo 6 caracteres.', 'danger');
        } else {
            usr_actualizar($miId, ['nombre' => $nombre, 'password' => $passwordNueva]);
            $_SESSION['admin_nombre'] = $nombre;
            $_SESSION['nombre'] = $nombre;
            flash('Datos actualizados correctamente.');
        }
    } else {
        usr_actualizar($miId, ['nombre' => $nombre]);
        $_SESSION['admin_nombre'] = $nombre;
        $_SESSION['nombre'] = $nombre;
        flash('Nombre actualizado.');
    }
    header('Location: perfil.php');
    exit;
}
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><b>Mis datos</b></div>
            <div class="card-body p-4">
                <form method="POST" action="perfil.php">
                    <?= campo_csrf() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= e($yo['nombre']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo</label>
                        <input type="email" class="form-control" value="<?= e($yo['email']) ?>" disabled>
                        <small class="text-muted">El correo no se puede cambiar.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Rol</label>
                        <input type="text" class="form-control" value="<?= e($yo['rol']) ?>" disabled>
                    </div>
                    <button class="btn btn-fv">Guardar cambios</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><b>Cambiar contraseña</b></div>
            <div class="card-body p-4">
                <form method="POST" action="perfil.php">
                    <?= campo_csrf() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contraseña actual</label>
                        <input type="password" name="password_actual" class="form-control" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nueva contraseña (mín. 6)</label>
                        <input type="password" name="password_nueva" class="form-control" autocomplete="new-password" minlength="6">
                    </div>
                    <button class="btn btn-fv">Cambiar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>