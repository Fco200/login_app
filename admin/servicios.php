<?php
$titulo = 'Servicios';
$subtitulo = 'Administra los servicios que se muestran en el sitio público';
$seccionAdmin = 'servicios.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id    = (int)($_POST['id'] ?? 0);
        $tituloS = trim($_POST['titulo']);
        $corta = trim($_POST['descripcion_corta']);
        $desc  = trim($_POST['descripcion']);
        $icono = trim($_POST['icono'] ?: 'bi-code-slash');
        $categoria = trim($_POST['categoria'] ?: 'desarrollo-web');
        $precio = ($_POST['precio_desde'] ?? '') !== '' ? (float)$_POST['precio_desde'] : null;
        $destaque = isset($_POST['destaque']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;

        $slugBase = slugify($tituloS) ?: ('servicio-' . date('YmdHis'));

        if ($id > 0) {
            $sql = "UPDATE servicios SET titulo=?, descripcion_corta=?, descripcion=?, icono=?, categoria=?, precio_desde=?, destaque=?, activo=? WHERE id=?";
            $pdo->prepare($sql)->execute([$tituloS, $corta, $desc, $icono, $categoria, $precio, $destaque, $activo, $id]);
            flash('Servicio actualizado correctamente.');
        } else {
            $sql = "INSERT INTO servicios (titulo, slug, descripcion_corta, descripcion, icono, categoria, precio_desde, destaque, activo) VALUES (?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([$tituloS, $slugBase, $corta, $desc, $icono, $categoria, $precio, $destaque, $activo]);
            flash('Servicio creado correctamente.');
        }
        header('Location: servicios.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM servicios WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Servicio eliminado.', 'warning');
        header('Location: servicios.php');
        exit;
    }
}

$servicios = $pdo->query('SELECT * FROM servicios ORDER BY destaque DESC, id ASC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM servicios WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0">Total: <b><?= count($servicios) ?></b> servicios</p>
    </div>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalServicio" onclick="limpiarFormulario(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo servicio</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Servicio</th><th>Categoría</th><th>Precio desde</th><th>Texto</th><th>Destacado</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($servicios as $s): ?>
                    <tr>
                        <td class="ps-3"><i class="bi <?= e($s['icono']) ?> me-2 text-primary"></i><b><?= e($s['titulo']) ?></b></td>
                        <td class="small"><?= e($s['categoria']) ?></td>
                        <td><?= e(formatear_precio((float)$s['precio_desde'])) ?></td>
                        <td class="small text-muted" style="max-width:260px;"><?= e(mb_strimwidth($s['descripcion_corta'], 0, 70, '…')) ?></td>
                        <td><?= $s['destaque'] ? '<i class="bi bi-star-fill text-warning"></i>' : '—' ?></td>
                        <td><?= $s['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarServicio(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este servicio?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalServicio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="servicios.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="s_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModal">Nuevo servicio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Título</label>
                        <input type="text" name="titulo" id="s_titulo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Categoría</label>
                        <select name="categoria" id="s_categoria" class="form-select">
                            <option value="desarrollo-web">Desarrollo web</option>
                            <option value="diseno-branding">Diseño y branding</option>
                            <option value="apps-plantillas">Apps y plantillas</option>
                            <option value="soporte">Soporte técnico</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Descripción corta</label>
                        <input type="text" name="descripcion_corta" id="s_corta" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Precio desde (MXN)</label>
                        <input type="number" step="0.01" min="0" name="precio_desde" id="s_precio" class="form-control">
                        <small class="text-muted">Vacío = "A convenir"</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Icono (Bootstrap Icons)</label>
                        <input type="text" name="icono" id="s_icono" class="form-control" value="bi-code-slash" placeholder="bi-rocket-takeoff">
                    </div>
                    <div class="col-md-8 d-flex align-items-end gap-4 pb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="destaque" id="s_destaque" value="1">
                            <label class="form-check-label small" for="s_destaque">Destacar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="s_activo" value="1" checked>
                            <label class="form-check-label small" for="s_activo">Visible en el sitio</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción completa (opcional)</label>
                        <textarea name="descripcion" id="s_desc" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar servicio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editar): ?>
<script>
    window.addEventListener('DOMContentLoaded', function () {
        editarServicio(<?= htmlspecialchars(json_encode($editar), ENT_QUOTES) ?>);
    });
</script>
<?php endif; ?>

<script>
function limpiarFormulario(mostrar) {
    document.getElementById('s_id').value = 0;
    document.getElementById('s_titulo').value = '';
    document.getElementById('s_corta').value = '';
    document.getElementById('s_precio').value = '';
    document.getElementById('s_icono').value = 'bi-code-slash';
    document.getElementById('s_categoria').value = 'desarrollo-web';
    document.getElementById('s_desc').value = '';
    document.getElementById('s_destaque').checked = false;
    document.getElementById('s_activo').checked = true;
    document.getElementById('tituloModal').textContent = 'Nuevo servicio';
}
function editarServicio(s) {
    document.getElementById('s_id').value = s.id;
    document.getElementById('s_titulo').value = s.titulo;
    document.getElementById('s_corta').value = s.descripcion_corta;
    document.getElementById('s_precio').value = s.precio_desde;
    document.getElementById('s_icono').value = s.icono;
    document.getElementById('s_categoria').value = s.categoria;
    document.getElementById('s_desc').value = s.descripcion || '';
    document.getElementById('s_destaque').checked = s.destaque == 1;
    document.getElementById('s_activo').checked = s.activo == 1;
    document.getElementById('tituloModal').textContent = 'Editar servicio';
    new bootstrap.Modal(document.getElementById('modalServicio')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>