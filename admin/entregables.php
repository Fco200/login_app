<?php
$titulo = 'Entregables';
$subtitulo = 'Archivos de entrega para los proyectos de clientes';
$seccionAdmin = 'entregables.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'subir_entregable') {
        $proyectoId = trim((string)($_POST['proyecto_id'] ?? ''));
        $tituloE = trim($_POST['titulo'] ?? '');
        if (oid($proyectoId) === null) {
            responder(['ok' => false, 'mensaje' => 'Proyecto invÃ¡lido.', 'tipo' => 'danger']);
        }
        if ($tituloE === '') {
            responder(['ok' => false, 'mensaje' => 'Escribe un tÃ­tulo para el entregable.', 'tipo' => 'warning']);
        }
        if (empty($_FILES['archivo']['name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            responder(['ok' => false, 'mensaje' => 'Selecciona el archivo a subir.', 'tipo' => 'warning']);
        }
        $permitidos = ['pdf', 'zip', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'sql'];
        $res = subir_archivo('archivo', 'entregables', $permitidos, 15);
        if (!$res['ok']) {
            responder(['ok' => false, 'mensaje' => 'Error al subir: ' . $res['error'], 'tipo' => 'danger']);
        }
        $notasE = trim($_POST['notas'] ?? '');
        $nuevo = ent_crear($proyectoId, [
            'titulo' => $tituloE,
            'archivo'=> $res['archivo'],
            'original' => $res['original'] ?? null,
            'notas'  => $notasE,
        ]);
        if (empty($nuevo['ok'])) {
            responder(['ok' => false, 'mensaje' => $nuevo['mensaje'] ?? 'No se pudo registrar el entregable.', 'tipo' => 'danger']);
        }
        $idE = $nuevo['id'];

        /* Notificar al cliente dueÃ±o del proyecto */
        $projN = proy_por_id($proyectoId);
        if ($projN && !empty($projN['usuario_id'])) {
            $estadoN = (string)($projN['estado'] ?? '');
            if ($estadoN === 'completado') {
                notificar(
                    $projN['usuario_id'],
                    'entregable',
                    'Nuevo entregable disponible',
                    'Subimos "' . $tituloE . '" para tu proyecto. Ya puedes descargarlo desde tus procesos.',
                    url_sitio('portal/procesos.php')
                );
            } else {
                notificar(
                    $projN['usuario_id'],
                    'info',
                    'Avance de tu proyecto',
                    'El equipo subiÃ³ "' . $tituloE . '" como entregable de avance.',
                    url_sitio('portal/procesos.php')
                );
            }
        }

        responder([
            'ok'           => true,
            'mensaje'      => 'Entregable "' . $tituloE . '" subido.',
            'accion'       => 'entregable_subido',
            'entregable_id'=> $idE,
            'titulo'       => $tituloE,
            'notas'        => $notasE,
            'fecha'        => date('d/m/Y H:i'),
            'tamano'       => $res['original'],
            'proyecto'     => $proyectoId,
        ]);
    }

    if ($accion === 'eliminar_entregable' && isset($_POST['id'])) {
        $idE = (string)$_POST['id'];
        $ent = ent_por_id($idE);
        if ($ent) {
            eliminar_archivo($ent['archivo'] ?? null);
            ent_eliminar($idE);
        }
        responder(['ok' => true, 'mensaje' => 'Entregable eliminado.', 'accion' => 'entregable_eliminado', 'entregable_id' => $idE, 'tipo' => 'warning']);
    }
}

/* ---------- Datos ---------- */
$filtroEstado = $_GET['estado'] ?? '';
if (!in_array($filtroEstado, ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'], true)) {
    $filtroEstado = '';
}

$proyectos = proy_panel_procesos($filtroEstado !== '' ? [$filtroEstado] : []);
$entregablesPorProyecto = $proyectos
    ? ent_por_proyectos(array_map(static fn($p) => $p['id'], $proyectos))
    : [];

$estadoProy = [
    'documentacion'      => 'text-bg-info',
    'anticipo_pendiente' => 'text-bg-warning',
    'en_desarrollo'      => 'text-bg-primary',
    'liquidado'          => 'text-bg-success',
    'completado'         => 'text-bg-success',
];
?>
<form method="GET" action="entregables.php" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3 d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small fw-semibold mb-1">Estado del proyecto</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="documentacion" <?= $filtroEstado === 'documentacion' ? 'selected' : '' ?>>DocumentaciÃ³n</option>
                <option value="anticipo_pendiente" <?= $filtroEstado === 'anticipo_pendiente' ? 'selected' : '' ?>>Anticipo pendiente</option>
                <option value="en_desarrollo" <?= $filtroEstado === 'en_desarrollo' ? 'selected' : '' ?>>En desarrollo</option>
                <option value="liquidado" <?= $filtroEstado === 'liquidado' ? 'selected' : '' ?>>Liquidado / Finalizado</option>
                <option value="completado" <?= $filtroEstado === 'completado' ? 'selected' : '' ?>>Completado</option>
            </select>
        </div>
        <button class="btn btn-sm btn-fv"><i class="bi bi-funnel me-1"></i>Filtrar</button>
        <a href="entregables.php" class="btn btn-sm btn-outline-fv"><i class="bi bi-x-lg me-1"></i>Limpiar</a>
    </div>
</form>

<?php if (!$proyectos): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-folder2-open fs-1 text-primary d-block mb-3"></i>
        <h5>No hay proyectos en esta vista</h5>
        <p class="text-muted mb-0">Crea los proyectos desde las solicitudes aprobadas para poder subir entregables.</p>
    </div>
<?php else: ?>
    <?php foreach ($proyectos as $proy):
        $entregas = $entregablesPorProyecto[(string)$proy['id']] ?? [];
        $bgEst = $estadoProy[$proy['estado']] ?? 'text-bg-light';
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <b><i class="bi bi-bezier2 me-1 text-primary"></i><?= e($proy['tipo_servicio'] ?: 'Proyecto #' . e((string)$proy['id'])) ?></b>
                    <small class="text-muted d-block">
                        <?= e($proy['cliente_nombre'] ?: 'Cliente #' . e((string)$proy['usuario_id'])) ?> Â·
                        <a href="<?= e($proy['cliente_email'] ? 'mailto:' . $proy['cliente_email'] : '') ?>"><?= e($proy['cliente_email'] ?: '') ?></a>
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-estado text-uppercase <?= $bgEst ?>"><?= e(str_replace('_', ' ', $proy['estado'])) ?></span>
                    <span class="badge text-bg-light"><?= count($entregas) ?> entregable(s)</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="small fw-bold text-uppercase text-muted mb-3"><i class="bi bi-paperclip me-1"></i>Subir entregable</h6>
                        <form method="POST" enctype="multipart/form-data" class="js-ajax">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="subir_entregable">
                            <input type="hidden" name="proyecto_id" value="<?= e((string)$proy['id']) ?>">
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" name="titulo" class="form-control" placeholder="TÃ­tulo (p. ej. 'Web final - archivo ZIP')" required>
                                </div>
                                <div class="col-12">
                                    <input type="file" name="archivo" class="form-control" required>
                                    <small class="text-muted">PDF, ZIP, RAR, 7z, Office, imÃ¡genesâ€¦ hasta 15 MB.</small>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="notas" class="form-control" placeholder="Nota breve (opcional)">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-fv btn-sm"><i class="bi bi-upload me-1"></i>Subir entregable</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="small fw-bold text-uppercase text-muted mb-3"><i class="bi bi-archive me-1"></i>Entregables del proyecto</h6>
                        <div id="entregablesLista-<?= e((string)$proy['id']) ?>">
                            <?php if (!$entregas): ?>
                                <p class="text-muted small mb-0">Sin entregables por ahora.</p>
                            <?php else: foreach ($entregas as $ent): ?>
                                <div class="d-flex justify-content-between align-items-center gap-2 border rounded p-2 mb-2" id="entregable-<?= e((string)$ent['id']) ?>">
                                    <div class="min-w-0">
                                        <b class="d-block text-truncate" style="max-width:260px;"><i class="bi bi-file-earmark-arrow-down me-1 text-success"></i><?= e($ent['titulo']) ?></b>
                                        <small class="text-muted"><?= e($ent['notas'] ?: fecha_php($ent['creado_en'], 'd/m/Y H:i')) ?></small>
                                    </div>
                                    <form method="POST" class="d-inline"><?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar_entregable">
                                        <input type="hidden" name="id" value="<?= e((string)$ent['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('Â¿Eliminar este entregable?')"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (d.accion !== 'entregable_subido') return;
    var lista = document.getElementById('entregablesLista-' + d.proyecto);
    if (!lista) return;
    var vacio = lista.querySelector('p.text-muted');
    if (vacio) vacio.remove();
    var div = document.createElement('div');
    div.className = 'd-flex justify-content-between align-items-center gap-2 border rounded p-2 mb-2';
    div.id = 'entregable-' + d.entregable_id;
    div.innerHTML =
        '<div class="min-w-0">'
        + '<b class="d-block text-truncate" style="max-width:260px;"><i class="bi bi-file-earmark-arrow-down me-1 text-success"></i>' + (d.titulo ? d.titulo.replace(/[<>&"']/g, '') : '') + '</b>'
        + '<small class="text-muted">' + (d.notas || d.fecha || '') + '</small></div>'
        + '<button class="btn btn-sm btn-outline-danger" title="Eliminar" type="button" onclick="return false;"><i class="bi bi-trash"></i></button>';
    lista.appendChild(div);
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>