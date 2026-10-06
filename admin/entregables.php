<?php
$titulo = 'Entregables';
$subtitulo = 'Archivos de entrega para los proyectos de clientes';
$seccionAdmin = 'entregables.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'subir_entregable') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        $tituloE = trim($_POST['titulo'] ?? '');
        if ($proyectoId <= 0) {
            responder(['ok' => false, 'mensaje' => 'Proyecto inválido.', 'tipo' => 'danger']);
        }
        if ($tituloE === '') {
            responder(['ok' => false, 'mensaje' => 'Escribe un título para el entregable.', 'tipo' => 'warning']);
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
        $pdo->prepare('INSERT INTO entregables (proyecto_id, titulo, archivo, notas) VALUES (?,?,?,?)')
            ->execute([$proyectoId, $tituloE, $res['archivo'], $notasE]);
        $idE = (int)$pdo->lastInsertId();

        /* Notificar al cliente dueño del proyecto */
        $stmtN = $pdo->prepare('SELECT usuario_id, estado FROM proyectos_inicio WHERE id = ?');
        $stmtN->execute([$proyectoId]);
        $projN = $stmtN->fetch();
        if ($projN) {
            $estadoN = $projN['estado'];
            if ($estadoN === 'completado') {
                notificar(
                    (int)$projN['usuario_id'],
                    'entregable',
                    'Nuevo entregable disponible',
                    'Subimos "' . $tituloE . '" para tu proyecto. Ya puedes descargarlo desde tus procesos.',
                    url_sitio('portal/procesos.php')
                );
            } else {
                notificar(
                    (int)$projN['usuario_id'],
                    'info',
                    'Avance de tu proyecto',
                    'El equipo subió "' . $tituloE . '" como entregable de avance.',
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
        $idE = (int)$_POST['id'];
        $stmtE = $pdo->prepare('SELECT archivo FROM entregables WHERE id = ?');
        $stmtE->execute([$idE]);
        $ent = $stmtE->fetch();
        if ($ent) {
            eliminar_archivo($ent['archivo']);
            $pdo->prepare('DELETE FROM entregables WHERE id = ?')->execute([$idE]);
        }
        responder(['ok' => true, 'mensaje' => 'Entregable eliminado.', 'accion' => 'entregable_eliminado', 'entregable_id' => $idE, 'tipo' => 'warning']);
    }
}

/* ---------- Datos ---------- */
$filtroEstado = $_GET['estado'] ?? '';
if (!in_array($filtroEstado, ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'], true)) {
    $filtroEstado = '';
}

$sqlProy = 'SELECT pi.*, s.tipo_servicio, u.nombre AS cliente_nombre, u.email AS cliente_email
            FROM proyectos_inicio pi
            LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
            LEFT JOIN usuarios u ON pi.usuario_id = u.id';
if ($filtroEstado !== '') {
    $sqlProy .= ' WHERE pi.estado = ?';
}
$sqlProy .= ' ORDER BY pi.creado_en DESC';
$stmtProy = $pdo->prepare($sqlProy);
$stmtProy->execute($filtroEstado !== '' ? [$filtroEstado] : []);
$proyectos = $stmtProy->fetchAll();

$entregablesPorProyecto = [];
if ($proyectos) {
    $ids = array_map(fn($p) => (int)$p['id'], $proyectos);
    $in = implode(',', $ids);
    foreach ($pdo->query("SELECT * FROM entregables WHERE proyecto_id IN ($in) ORDER BY creado_en DESC") as $ent) {
        $entregablesPorProyecto[(int)$ent['proyecto_id']][] = $ent;
    }
}

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
                <option value="documentacion" <?= $filtroEstado === 'documentacion' ? 'selected' : '' ?>>Documentación</option>
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
        $entregas = $entregablesPorProyecto[(int)$proy['id']] ?? [];
        $bgEst = $estadoProy[$proy['estado']] ?? 'text-bg-light';
        ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <b><i class="bi bi-bezier2 me-1 text-primary"></i><?= e($proy['tipo_servicio'] ?: 'Proyecto #' . (int)$proy['id']) ?></b>
                    <small class="text-muted d-block">
                        <?= e($proy['cliente_nombre'] ?: 'Cliente #' . (int)$proy['usuario_id']) ?> ·
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
                            <input type="hidden" name="proyecto_id" value="<?= (int)$proy['id'] ?>">
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" name="titulo" class="form-control" placeholder="Título (p. ej. 'Web final - archivo ZIP')" required>
                                </div>
                                <div class="col-12">
                                    <input type="file" name="archivo" class="form-control" required>
                                    <small class="text-muted">PDF, ZIP, RAR, 7z, Office, imágenes… hasta 15 MB.</small>
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
                        <div id="entregablesLista-<?= (int)$proy['id'] ?>">
                            <?php if (!$entregas): ?>
                                <p class="text-muted small mb-0">Sin entregables por ahora.</p>
                            <?php else: foreach ($entregas as $ent): ?>
                                <div class="d-flex justify-content-between align-items-center gap-2 border rounded p-2 mb-2" id="entregable-<?= (int)$ent['id'] ?>">
                                    <div class="min-w-0">
                                        <b class="d-block text-truncate" style="max-width:260px;"><i class="bi bi-file-earmark-arrow-down me-1 text-success"></i><?= e($ent['titulo']) ?></b>
                                        <small class="text-muted"><?= e($ent['notas'] ?: date('d/m/Y H:i', strtotime($ent['creado_en']))) ?></small>
                                    </div>
                                    <form method="POST" class="d-inline"><?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar_entregable">
                                        <input type="hidden" name="id" value="<?= (int)$ent['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Eliminar este entregable?')"><i class="bi bi-trash"></i></button>
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