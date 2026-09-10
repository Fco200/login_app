<?php
$titulo = 'Cartas de presentación';
$subtitulo = 'Documentos oficiales visibles para el público';
$seccionAdmin = 'cartas.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $tituloC = trim($_POST['titulo']);
        $destinatario = trim($_POST['destinatario']);
        $contenido = trim($_POST['contenido']);
        $firmadoPor = trim($_POST['firmado_por']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $slugBase = slugify($tituloC) ?: ('carta-' . date('YmdHis'));

        if ($id > 0) {
            $pdo->prepare("UPDATE cartas SET titulo=?, destinatario=?, contenido=?, firmado_por=?, activo=? WHERE id=?")
                ->execute([$tituloC, $destinatario, $contenido, $firmadoPor, $activo, $id]);
            flash('Carta actualizada.');
        } else {
            $pdo->prepare("INSERT INTO cartas (titulo, slug, destinatario, contenido, firmado_por, activo) VALUES (?,?,?,?,?,?)")
                ->execute([$tituloC, $slugBase, $destinatario, $contenido, $firmadoPor, $activo]);
            flash('Carta creada.');
        }
        header('Location: cartas.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM cartas WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Carta eliminada.', 'warning');
        header('Location: cartas.php');
        exit;
    }
}

$cartas = $pdo->query('SELECT * FROM cartas ORDER BY creado_en DESC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM cartas WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($cartas) ?></b> cartas</p>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalCarta" onclick="limpiarFormulario(false)"><i class="bi bi-plus-lg me-1"></i>Nueva carta</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Título</th><th>Destinatario</th><th>Firmado por</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($cartas as $c): ?>
                    <tr>
                        <td class="ps-3"><b><?= e($c['titulo']) ?></b></td>
                        <td class="small"><?= e($c['destinatario'] ?: '—') ?></td>
                        <td class="small"><?= e($c['firmado_por'] ?: '—') ?></td>
                        <td><?= $c['activo'] ? '<span class="badge badge-estado text-bg-success">Activa</span>' : '<span class="badge badge-estado text-bg-secondary">Oculta</span>' ?></td>
                        <td class="text-end pe-3">
                            <a href="../carta.php?slug=<?= e($c['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarCarta(<?= json_encode($c, JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta carta?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
<div class="modal fade" id="modalCarta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="cartas.php">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="c_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModal">Nueva carta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Título *</label>
                        <input type="text" name="titulo" id="c_titulo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Destinatario</label>
                        <input type="text" name="destinatario" id="c_destinatario" class="form-control" placeholder="A quien corresponda">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Contenido *</label>
                        <textarea name="contenido" id="c_contenido" class="form-control" rows="12" required></textarea>
                        <small class="text-muted">Saludos, desarrollo de la carta y despedida. Una línea en blanco separa párrafos.</small>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Firmado por</label>
                        <input type="text" name="firmado_por" id="c_firmado" class="form-control" placeholder="Nombre y cargo">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="c_activo" value="1" checked>
                            <label class="form-check-label small" for="c_activo">Visible en el sitio</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar carta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editar): ?>
<script>window.addEventListener('DOMContentLoaded', function () { editarCarta(<?= json_encode($editar, JSON_HEX_APOS) ?>); });</script>
<?php endif; ?>

<script>
function limpiarFormulario(mostrar) {
    document.getElementById('c_id').value = 0;
    document.getElementById('c_titulo').value = '';
    document.getElementById('c_destinatario').value = '';
    document.getElementById('c_contenido').value = '';
    document.getElementById('c_firmado').value = '';
    document.getElementById('c_activo').checked = true;
    document.getElementById('tituloModal').textContent = 'Nueva carta';
}
function editarCarta(c) {
    document.getElementById('c_id').value = c.id;
    document.getElementById('c_titulo').value = c.titulo;
    document.getElementById('c_destinatario').value = c.destinatario || '';
    document.getElementById('c_contenido').value = c.contenido || '';
    document.getElementById('c_firmado').value = c.firmado_por || '';
    document.getElementById('c_activo').checked = c.activo == 1;
    document.getElementById('tituloModal').textContent = 'Editar carta';
    new bootstrap.Modal(document.getElementById('modalCarta')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>