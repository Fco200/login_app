<?php
$titulo = 'Publicaciones';
$subtitulo = 'Datos e información que FV Digital publica en el sitio';
$seccionAdmin = 'publicaciones.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $tituloP = trim($_POST['titulo']);
        $resumen = trim($_POST['resumen']);
        $contenido = trim($_POST['contenido']);
        $categoria = trim($_POST['categoria'] ?: 'Noticias');
        $autor = trim($_POST['autor'] ?: 'FV Digital');
        $destacado = isset($_POST['destaque']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $slugBase = slugify($tituloP) ?: ('publicacion-' . date('YmdHis'));

        $imagen = null;
        $r = subir_archivo('imagen', 'publicaciones', ['png', 'jpg', 'jpeg', 'webp', 'gif']);
        if (!$r['ok'] && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
            flash($r['error'], 'danger'); header('Location: publicaciones.php'); exit;
        }
        if ($r['ok']) $imagen = $r['archivo'];

        if ($id > 0) {
            $sql = "UPDATE publicaciones SET titulo=?, resumen=?, contenido=?, categoria=?, autor=?, destaque=?, activo=? WHERE id=?";
            $pdo->prepare($sql)->execute([$tituloP, $resumen, $contenido, $categoria, $autor, $destacado, $activo, $id]);
            if ($imagen !== null) {
                $prev = $pdo->prepare('SELECT imagen FROM publicaciones WHERE id=?'); $prev->execute([$id]); $prevR = $prev->fetch();
                eliminar_archivo($prevR['imagen'] ?? null);
                $pdo->prepare('UPDATE publicaciones SET imagen=? WHERE id=?')->execute([$imagen, $id]);
            }
            flash('Publicación actualizada.');
        } else {
            $sql = "INSERT INTO publicaciones (titulo, slug, resumen, contenido, categoria, imagen, autor, destaque, activo) VALUES (?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([$tituloP, $slugBase, $resumen, $contenido, $categoria, $imagen, $autor, $destacado, $activo]);
            flash('Publicación creada.');
        }
        header('Location: publicaciones.php');
        exit;
    }

    if ($accion === 'eliminar' && isset($_POST['id'])) {
        $stmt = $pdo->prepare('SELECT imagen FROM publicaciones WHERE id=?'); $stmt->execute([(int)$_POST['id']]);
        $filas = $stmt->fetch();
        if ($filas) eliminar_archivo($filas['imagen']);
        $pdo->prepare('DELETE FROM publicaciones WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Publicación eliminada.', 'warning');
        header('Location: publicaciones.php');
        exit;
    }
}

$publicaciones = $pdo->query('SELECT * FROM publicaciones ORDER BY destaque DESC, creado_en DESC')->fetchAll();
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM publicaciones WHERE id = ?');
    $stmt->execute([(int)$_GET['editar']]);
    $editar = $stmt->fetch();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($publicaciones) ?></b> publicaciones</p>
    <button class="btn btn-fv" data-bs-toggle="modal" data-bs-target="#modalPublicacion" onclick="limpiarFormulario(false)"><i class="bi bi-plus-lg me-1"></i>Nueva publicación</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Publicación</th><th>Categoría</th><th>Visitas</th><th>Destacado</th><th>Estado</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($publicaciones as $pub): ?>
                    <tr>
                        <td class="ps-3">
                            <b><?= e($pub['titulo']) ?></b><br>
                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= e(date('d/m/Y H:i', strtotime($pub['creado_en']))) ?></small>
                        </td>
                        <td class="small"><?= e($pub['categoria']) ?></td>
                        <td class="small"><i class="bi bi-eye me-1"></i><?= number_format((int)$pub['visitas']) ?></td>
                        <td><?= $pub['destaque'] ? '<i class="bi bi-star-fill text-warning"></i>' : '—' ?></td>
                        <td><?= $pub['activo'] ? '<span class="badge badge-estado text-bg-success">Activo</span>' : '<span class="badge badge-estado text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end pe-3">
                            <a href="../publicacion.php?slug=<?= e($pub['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver en el sitio"><i class="bi bi-eye"></i></a>
                            <button class="btn btn-sm btn-outline-primary" title="Editar" onclick='editarPublicacion(<?= json_encode($pub, JSON_HEX_APOS) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta publicación?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$pub['id'] ?>">
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
<div class="modal fade" id="modalPublicacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="publicaciones.php" enctype="multipart/form-data">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="pub_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModal">Nueva publicación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold">Título *</label>
                        <input type="text" name="titulo" id="pub_titulo" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Categoría</label>
                        <input type="text" name="categoria" id="pub_categoria" class="form-control" placeholder="Noticias, eventos...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Autor</label>
                        <input type="text" name="autor" id="pub_autor" class="form-control" value="FV Digital">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Imagen (opcional)</label>
                        <input type="file" name="imagen" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-4 pb-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="destaque" id="pub_destaque" value="1">
                            <label class="form-check-label small" for="pub_destaque">Destacar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" id="pub_activo" value="1" checked>
                            <label class="form-check-label small" for="pub_activo">Visible</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Resumen</label>
                        <input type="text" name="resumen" id="pub_resumen" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Contenido</label>
                        <textarea name="contenido" id="pub_contenido" class="form-control" rows="8"></textarea>
                        <small class="text-muted">El texto se muestra en párrafos. Deja una línea en blanco para separar párrafos.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-fv">Guardar publicación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editar): ?>
<script>window.addEventListener('DOMContentLoaded', function () { editarPublicacion(<?= json_encode($editar, JSON_HEX_APOS) ?>); });</script>
<?php endif; ?>

<script>
function limpiarFormulario(mostrar) {
    document.getElementById('pub_id').value = 0;
    document.getElementById('pub_titulo').value = '';
    document.getElementById('pub_categoria').value = '';
    document.getElementById('pub_autor').value = 'FV Digital';
    document.getElementById('pub_resumen').value = '';
    document.getElementById('pub_contenido').value = '';
    document.getElementById('pub_destaque').checked = false;
    document.getElementById('pub_activo').checked = true;
    document.getElementById('tituloModal').textContent = 'Nueva publicación';
}
function editarPublicacion(p) {
    document.getElementById('pub_id').value = p.id;
    document.getElementById('pub_titulo').value = p.titulo;
    document.getElementById('pub_categoria').value = p.categoria || '';
    document.getElementById('pub_autor').value = p.autor || 'FV Digital';
    document.getElementById('pub_resumen').value = p.resumen || '';
    document.getElementById('pub_contenido').value = p.contenido || '';
    document.getElementById('pub_destaque').checked = p.destaque == 1;
    document.getElementById('pub_activo').checked = p.activo == 1;
    document.getElementById('tituloModal').textContent = 'Editar publicación';
    new bootstrap.Modal(document.getElementById('modalPublicacion')).show();
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>