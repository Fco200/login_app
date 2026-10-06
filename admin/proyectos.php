<?php
$titulo = 'Proyectos / Plantillas';
$subtitulo = 'Publica los proyectos y plantillas creados por la marca';
$seccionAdmin = 'proyectos.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $tituloP = trim($_POST['titulo']);
        $desc = trim($_POST['descripcion']);
        $categoria = trim($_POST['categoria'] ?: 'General');
        $cliente = trim($_POST['cliente']);
        $anio = ($_POST['anio'] ?? '') !== '' ? (int)$_POST['anio'] : null;
        $url = trim($_POST['url']);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $destacado = isset($_POST['destaque']) ? 1 : 0;

        $imagen = null; $archivo = null; $archivoNombre = null;

        $r = subir_archivo('imagen', 'proyectos', ['png', 'jpg', 'jpeg', 'webp', 'gif']);
        if (!$r['ok'] && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            flash($r['error'], 'danger'); header('Location: proyectos.php'); exit;
        }
        if ($r['ok']) $imagen = $r['archivo'];

        $f = subir_archivo('archivo', 'proyectos', ['zip', 'pdf', 'docx', 'pptx', 'xlsx', 'psd', 'txt', 'html']);
        if (!$f['ok'] && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
            flash($f['error'], 'danger'); header('Location: proyectos.php'); exit;
        }
        if ($f['ok']) { $archivo = $f['archivo']; $archivoNombre = $f['original']; }

        $slugBase = slugify($tituloP) ?: ('proyecto-' . date('YmdHis'));

        if ($id > 0) {
            $sql = "UPDATE proyectos SET titulo=?, descripcion=?, categoria=?, cliente=?, anio=?, url=?, destaque=?, activo=?
                    WHERE id=?";
            $pdo->prepare($sql)->execute([$tituloP, $desc, $categoria, $cliente, $anio, $url, $destacado, $activo, $id]);
            if ($imagen !== null) {
                $prev = $pdo->prepare('SELECT imagen FROM proyectos WHERE id=?'); $prev->execute([$id]); $prevR = $prev->fetch();
                eliminar_archivo($prevR['imagen'] ?? null);
                $pdo->prepare('UPDATE proyectos SET imagen=? WHERE id=?')->execute([$imagen, $id]);
            }
            if ($archivo !== null) {
                $prev = $pdo->prepare('SELECT archivo FROM proyectos WHERE id=?'); $prev->execute([$id]); $prevR = $prev->fetch();
                eliminar_archivo($prevR['archivo'] ?? null);
                $pdo->prepare('UPDATE proyectos SET archivo=?, archivo_nombre=? WHERE id=?')->execute([$archivo, $archivoNombre, $id]);
            }
            flash('Proyecto actualizado correctamente.');
        } else {
            $sql = "INSERT INTO proyectos (titulo, slug, descripcion, categoria, cliente, anio, url, imagen, archivo, archivo_nombre, destaque, activo)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([$tituloP, $slugBase, $desc, $categoria, $cliente, $anio, $url, $imagen, $archivo, $archivoNombre, $destacado, $activo]);
            flash('Proyecto creado correctamente.');
        }
        header('Location: proyectos.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('SELECT imagen, archivo FROM proyectos WHERE id=?');
        $stmt->execute([(int)$_POST['id']]);
        $filas = $stmt->fetch();
        if ($filas) {
            eliminar_archivo($filas['imagen']);
            eliminar_archivo($filas['archivo']);
        }
        $pdo->prepare('DELETE FROM proyectos WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Proyecto eliminado.', 'warning');
        header('Location: proyectos.php');
        exit;
    }
}

$proyectos = $pdo->query('SELECT * FROM proyectos ORDER BY destaque DESC, creado_en DESC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($proyectos) ?></b> proyectos/plantillas</p>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalProyecto" onclick="limpiarFormulario(false)"><i class="bi bi-plus-lg me-1"></i>Nuevo proyecto</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Imagen</th><th>Proyecto</th><th>Categoría</th><th>Archivo</th><th>Destacado</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($proyectos as $p): ?>
                    <tr>
                        <td class="ps-3">
                            <?php if ($p['imagen']): ?>
                                <img src="../<?= e($p['imagen']) ?>" class="miniatura" alt="">
                            <?php else: ?>
                                <div class="miniatura d-flex align-items-center justify-content-center text-primary"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><b><?= e($p['titulo']) ?></b><br><small class="text-muted"><?= e($p['cliente']) ?> <?= $p['anio'] ? '· ' . (int)$p['anio'] : '' ?></small></td>
                        <td class="small"><?= e($p['categoria']) ?></td>
                        <td class="small"><?= $p['archivo'] ? '<i class="bi bi-file-earmark-arrow-down text-success me-1"></i>' . e($p['archivo_nombre'] ?: 'Plantilla') : '—' ?></td>
                        <td><?= $p['destaque'] ? '<i class="bi bi-star-fill text-warning"></i>' : '—' ?></td>
                        <td><?= $p['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarProyecto(<?= json_encode($p, JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este proyecto y sus archivos?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
<div class="modal fade" id="modalProyecto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="proyectos.php" enctype="multipart/form-data">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="p_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModal">Nuevo proyecto / plantilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Título *</label>
                        <input type="text" name="titulo" id="p_titulo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Categoría</label>
                        <input type="text" name="categoria" id="p_categoria" class="form-control" placeholder="Ej. Web, Branding">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cliente</label>
                        <input type="text" name="cliente" id="p_cliente" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Año</label>
                        <input type="number" name="anio" id="p_anio" class="form-control" min="2000" max="2100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">URL del proyecto</label>
                        <input type="text" name="url" id="p_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <textarea name="descripcion" id="p_desc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Imagen del proyecto</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                        <small class="text-muted">PNG/JPG/WebP. Déjalo vacío si no cambias la imagen.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Archivo / plantilla</label>
                        <input type="file" name="archivo" class="form-control" accept=".zip,.pdf,.docx,.pptx,.xlsx,.psd,.html">
                        <small class="text-muted">ZIP, PDF, DOCX, PPTX, etc. Se descarga desde el sitio.</small>
                    </div>
                    <div class="col-12 d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="destaque" id="p_destaque" value="1">
                            <label class="form-check-label small" for="p_destaque">Destacar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="p_activo" value="1" checked>
                            <label class="form-check-label small" for="p_activo">Visible en el sitio</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar proyecto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editar): ?>
<script>window.addEventListener('DOMContentLoaded', function () { editarProyecto(<?= json_encode($editar, JSON_HEX_APOS) ?>); });</script>
<?php endif; ?>

<script>
function limpiarFormulario(mostrar) {
    document.getElementById('p_id').value = 0;
    document.getElementById('p_titulo').value = '';
    document.getElementById('p_categoria').value = '';
    document.getElementById('p_cliente').value = '';
    document.getElementById('p_anio').value = '';
    document.getElementById('p_url').value = '';
    document.getElementById('p_desc').value = '';
    document.getElementById('p_destaque').checked = false;
    document.getElementById('p_activo').checked = true;
    document.getElementById('tituloModal').textContent = 'Nuevo proyecto / plantilla';
}
function editarProyecto(p) {
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_titulo').value = p.titulo;
    document.getElementById('p_categoria').value = p.categoria || '';
    document.getElementById('p_cliente').value = p.cliente || '';
    document.getElementById('p_anio').value = p.anio || '';
    document.getElementById('p_url').value = p.url || '';
    document.getElementById('p_desc').value = p.descripcion || '';
    document.getElementById('p_destaque').checked = p.destaque == 1;
    document.getElementById('p_activo').checked = p.activo == 1;
    document.getElementById('tituloModal').textContent = 'Editar proyecto';
    new bootstrap.Modal(document.getElementById('modalProyecto')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>