<?php
$titulo = 'Testimonios';
$subtitulo = 'Opiniones de clientes que aparecen en la página de inicio';
$seccionAdmin = 'testimonios.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre']);
        $cargo = trim($_POST['cargo']);
        $mensaje = trim($_POST['mensaje']);
        $valoracion = (int)($_POST['valoracion'] ?? 5);
        if ($valoracion < 1 || $valoracion > 5) $valoracion = 5;
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($id > 0) {
            $pdo->prepare("UPDATE testimonios SET nombre=?, cargo=?, mensaje=?, valoracion=?, activo=? WHERE id=?")
                ->execute([$nombre, $cargo, $mensaje, $valoracion, $activo, $id]);
            flash('Testimonio actualizado.');
        } else {
            $pdo->prepare("INSERT INTO testimonios (nombre, cargo, mensaje, valoracion, activo) VALUES (?,?,?,?,?)")
                ->execute([$nombre, $cargo, $mensaje, $valoracion, $activo]);
            flash('Testimonio creado.');
        }
        header('Location: testimonios.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM testimonios WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Testimonio eliminado.', 'warning');
        header('Location: testimonios.php');
        exit;
    }
}

$testimonios = $pdo->query('SELECT * FROM testimonios ORDER BY id DESC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM testimonios WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($testimonios) ?></b> testimonios</p>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalTestimonio" onclick="limpiarFormulario(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo testimonio</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Cliente</th><th>Mensaje</th><th>Valoración</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($testimonios as $t): ?>
                    <tr>
                        <td class="ps-3"><b><?= e($t['nombre']) ?></b><br><small class="text-muted"><?= e($t['cargo']) ?></small></td>
                        <td class="small text-muted" style="max-width:320px;"><?= e(mb_strimwidth($t['mensaje'], 0, 100, '…')) ?></td>
                        <td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= (int)$t['valoracion'] ? '-fill text-warning' : '' ?>"></i>
                            <?php endfor; ?>
                        </td>
                        <td><?= $t['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarTestimonio(<?= json_encode($t, JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este testimonio?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalTestimonio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="testimonios.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="t_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModal">Nuevo testimonio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" id="t_nombre" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cargo o empresa</label>
                        <input type="text" name="cargo" id="t_cargo" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Mensaje *</label>
                        <textarea name="mensaje" id="t_mensaje" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Valoración</label>
                        <select name="valoracion" id="t_valoracion" class="form-select">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> estrella<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="t_activo" value="1" checked>
                            <label class="form-check-label small" for="t_activo">Visible en el sitio</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar testimonio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editar): ?>
<script>window.addEventListener('DOMContentLoaded', function () { editarTestimonio(<?= json_encode($editar, JSON_HEX_APOS) ?>); });</script>
<?php endif; ?>

<script>
function limpiarFormulario(mostrar) {
    document.getElementById('t_id').value = 0;
    document.getElementById('t_nombre').value = '';
    document.getElementById('t_cargo').value = '';
    document.getElementById('t_mensaje').value = '';
    document.getElementById('t_valoracion').value = '5';
    document.getElementById('t_activo').checked = true;
    document.getElementById('tituloModal').textContent = 'Nuevo testimonio';
}
function editarTestimonio(t) {
    document.getElementById('t_id').value = t.id;
    document.getElementById('t_nombre').value = t.nombre;
    document.getElementById('t_cargo').value = t.cargo || '';
    document.getElementById('t_mensaje').value = t.mensaje;
    document.getElementById('t_valoracion').value = t.valoracion;
    document.getElementById('t_activo').checked = t.activo == 1;
    document.getElementById('tituloModal').textContent = 'Editar testimonio';
    new bootstrap.Modal(document.getElementById('modalTestimonio')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>