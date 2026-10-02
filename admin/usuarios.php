<?php
$titulo = 'Usuarios';
$subtitulo = 'Gestiona los accesos al panel de administración y sistemas';
$seccionAdmin = 'usuarios.php';

require_once __DIR__ . '/includes/cabecera.php';

$miId = (string)($_SESSION['admin_id'] ?? $_SESSION['usuario_id'] ?? '');

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';
    $id = trim((string)($_POST['id'] ?? ''));

    if ($accion === 'rol' && oid($id) !== null) {
        $objetivo = usr_por_id($id);
        if ($objetivo && (string)($objetivo['rol'] ?? '') === 'cliente') {
            flash('No se puede cambiar el rol de un cliente registrado en la web.', 'danger');
            header('Location: usuarios.php');
            exit;
        }
    }

    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $rol = ($_POST['rol'] ?? 'vendedor') === 'admin' ? 'admin' : 'vendedor';

        $res = usr_crear([
            'nombre'   => $nombre,
            'email'    => $email,
            'password' => $password,
            'rol'      => $rol,
            'activo'   => 1,
        ]);
        flash(!empty($res['ok']) ? 'Usuario creado correctamente.' : ($res['mensaje'] ?? 'No se pudo crear el usuario.'),
            !empty($res['ok']) ? 'success' : 'danger');
        header('Location: usuarios.php');
        exit;
    }

    if ($accion === 'rol' && oid($id) !== null && $id !== $miId) {
        $nuevo = ($_POST['rol'] ?? 'vendedor') === 'admin' ? 'admin' : 'vendedor';
        usr_actualizar($id, ['rol' => $nuevo]);
        flash('Rol actualizado.');
        header('Location: usuarios.php');
        exit;
    }

    if ($accion === 'password' && oid($id) !== null) {
        $password = trim($_POST['password']);
        if (strlen($password) >= 6) {
            usr_actualizar($id, ['password' => $password]);
            flash('Contraseña actualizada.');
        } else {
            flash('La contraseña debe tener mínimo 6 caracteres.', 'danger');
        }
        header('Location: usuarios.php');
        exit;
    }

    if ($accion === 'eliminar' && oid($id) !== null && $id !== $miId) {
        usr_eliminar($id);
        flash('Usuario eliminado.', 'warning');
        header('Location: usuarios.php');
        exit;
    }
}

$usuarios = usr_listar();
$conteoRoles = usr_conteo_por_rol();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($usuarios) ?></b> usuarios</p>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalUsuario"><i class="bi bi-person-plus me-1"></i>Nuevo usuario</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Usuario</th><th>Rol</th><th>Registro</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="ps-3">
                            <b><?= e($u['nombre']) ?></b>
                            <?php if ((string)$u['id'] === $miId): ?><span class="badge text-bg-primary ms-1">Tú</span><?php endif; ?>
                            <br><small class="text-muted"><?= e($u['email']) ?></small>
                        </td>
                        <td>
                            <?php if ($u['rol'] === 'cliente'): ?>
                                <span class="badge badge-estado text-bg-success text-uppercase">Cliente</span>
                            <?php elseif ((string)$u['id'] !== $miId): ?>
                                <form method="POST" class="d-inline-flex align-items-center gap-1">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="rol">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <select name="rol" class="form-select form-select-sm" style="width:118px;" onchange="this.form.submit()">
                                        <option value="admin" <?= $u['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="vendedor" <?= $u['rol'] === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-estado text-bg-primary text-uppercase"><?= e($u['rol']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= e(fecha_php($u['creado_en'] ?? '', 'd/m/Y')) ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPass<?= e($u['id']) ?>" title="Cambiar contraseña"><i class="bi bi-key"></i></button>
                            <?php if ((string)$u['id'] !== $miId): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario? No podrá entrar al panel.')">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal crear usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="usuarios.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Nuevo usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Contraseña (mín. 6)</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Rol</label>
                        <select name="rol" class="form-select">
                            <option value="vendedor">Vendedor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Crear usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modales contraseña -->
<?php foreach ($usuarios as $u): ?>
    <div class="modal fade" id="modalPass<?= e($u['id']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="usuarios.php">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="password">
                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Cambiar contraseña</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Usuario: <b><?= e($u['nombre']) ?></b> (<?= e($u['email']) ?>)</p>
                        <label class="form-label small fw-semibold">Nueva contraseña (mín. 6)</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-fv">Guardar contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>