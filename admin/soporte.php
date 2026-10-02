<?php
$titulo = 'Soporte y reportes';
$subtitulo = 'Problemas, dudas y sugerencias reportadas desde el portal de clientes';
$seccionAdmin = 'soporte.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Responder / cambiar estado ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim((string)($_POST['id'] ?? ''));
    $row = oid($id) !== null ? sop_por_id($id) : null;

    if ($row) {
        $estado = in_array($_POST['estado'] ?? '', ['nuevo', 'atendido', 'cerrado'], true) ? $_POST['estado'] : 'atendido';
        $respuesta = mb_substr(trim($_POST['respuesta'] ?? ''), 0, 2000);
        sop_responder($id, $respuesta, $estado);

        if (!empty($row['usuario_id'])) {
            $catTxt = match ($row['categoria']) {
                'problema'   => 'Problema o error',
                'duda'       => 'Duda',
                'sugerencia' => 'Sugerencia',
                default      => 'Reporte',
            };
            $resumen = $respuesta !== '' ? $respuesta : 'Revisamos tu reporte de soporte.';
            notificar($row['usuario_id'], 'soporte', "Reporte de soporte: " . ucfirst(str_replace('_', ' ', $estado)),
                mb_strimwidth($resumen, 0, 90, 'â€¦'), url_sitio('portal/soporte'));
        }
        flash('Reporte actualizado y cliente notificado.');
    } else {
        flash('Reporte no encontrado.', 'danger');
    }
    header('Location: soporte.php');
    exit;
}

/* ---------- Filtros ---------- */
$filtro = $_GET['estado'] ?? '';
if (!in_array($filtro, ['nuevo', 'atendido', 'cerrado'], true)) $filtro = '';
$reportes = sop_listar((string)$filtro);

$estadosS = ['nuevo', 'atendido', 'cerrado'];
$nuevos = sop_no_leidos();
?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="soporte.php" class="btn btn-sm <?= $filtro === '' ? 'btn-fv' : 'btn-outline-fv' ?>">Todas (<?= contar_registros('soporte') ?>)</a>
    <?php foreach ($estadosS as $est): $n = contar_registros('soporte', ['estado' => $est]); ?>
        <a href="soporte.php?estado=<?= $est ?>" class="btn btn-sm <?= $filtro === $est ? 'btn-fv' : 'btn-outline-fv' ?>">
            <?= e(ucfirst($est)) ?> (<?= $n ?>)
        </a>
    <?php endforeach; ?>
    <?php if ($nuevos > 0): ?><span class="btn btn-sm disabled text-danger"><i class="bi bi-exclamation-circle me-1"></i><?= $nuevos ?> por atender</span><?php endif; ?>
</div>

<?php if (!$reportes): ?>
    <div class="card border-0 shadow-sm p-5 text-center text-muted">
        <i class="bi bi-patch-check fs-1 d-block mb-2 text-success"></i>
        <b>Sin reportes en esta vista</b><br><small>Los problemas que reporten tus clientes aparecerÃ¡n aquÃ­.</small>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Reporte</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Recibido</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $r): [$bgR, $icR, $txtR] = match ($r['estado']) {
                        'nuevo'     => ['badge text-bg-danger', 'bi-exclamation-circle', 'Nuevo'],
                        'atendido'  => ['badge text-bg-success', 'bi-check-circle', 'Atendido'],
                        default     => ['badge text-bg-secondary', 'bi-check2-circle', 'Cerrado'],
                    }; $catBadge = $r['categoria'] === 'problema' ? 'text-bg-danger' : ($r['categoria'] === 'sugerencia' ? 'text-bg-warning' : 'text-bg-info'); ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= (string)$r['id'] ?></td>
                            <td class="ps-3">
                                <span class="badge <?= $catBadge ?> text-uppercase"><?= e($r['categoria']) ?></span>
                                <div class="small text-muted mt-1" style="max-width:380px;"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $r['descripcion']), 0, 120, 'â€¦')) ?></div>
                                <?php if ($r['pagina']): ?><small class="text-muted d-block"><i class="bi bi-globe2 me-1"></i><?= e($r['pagina']) ?></small><?php endif; ?>
                                <?php if ($r['respuesta']): ?><small class="text-success d-block"><i class="bi bi-reply me-1"></i><?= e(mb_strimwidth($r['respuesta'], 0, 50, 'â€¦')) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <b><?= e($r['nombre']) ?></b><br>
                                <small class="text-muted"><?= e($r['email']) ?></small>
                            </td>
                            <td><span class="<?= $bgR ?>"><i class="bi <?= $icR ?> me-1"></i><?= $txtR ?></span></td>
                            <td class="small text-muted"><?= e(fecha_php($r['creado_en'], 'd/m/Y H:i')) ?></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-fv" data-bs-toggle="modal" data-bs-target="#verSoporte<?= (string)$r['id'] ?>"><i class="bi bi-eye me-1"></i>Ver / responder</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($reportes as $r): ?>
    <div class="modal fade" id="verSoporte<?= (string)$r['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Reporte #<?= (string)$r['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><small class="text-muted d-block">Cliente</small><b><?= e($r['nombre']) ?></b></div>
                        <div class="col-md-6"><small class="text-muted d-block">Correo</small><b><?= e($r['email']) ?></b></div>
                        <div class="col-md-6"><small class="text-muted d-block">CategorÃ­a</small><b><?= e($r['categoria']) ?></b></div>
                        <div class="col-md-6"><small class="text-muted d-block">PÃ¡gina</small><b><?= e($r['pagina'] ?: 'â€”') ?></b></div>
                        <div class="col-12">
                            <small class="text-muted d-block">DescripciÃ³n del cliente</small>
                            <p class="mb-0" style="white-space:pre-line;"><?= e($r['descripcion']) ?></p>
                        </div>
                        <div class="col-12"><small class="text-muted">Recibido: <?= e(fecha_php($r['creado_en'], 'd/m/Y H:i')) ?><?= $r['respuesta'] ? ' Â· Respondido: ' . e(fecha_php($r['atendido_en'] ?? '', 'd/m/Y H:i')) : '' ?></small></div>
                    </div>
                    <form method="POST" action="soporte.php" class="js-ajax-none">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id" value="<?= (string)$r['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Respuesta para el cliente</label>
                            <textarea name="respuesta" class="form-control" rows="3" maxlength="2000"
                                placeholder="Ej. Hola, ya revisamos el problema, era un error del navegador. QuedÃ³ solucionado."><?= e($r['respuesta'] ?? '') ?></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <select name="estado" class="form-select form-select-sm" style="max-width:180px;">
                                <option value="nuevo" <?= $r['estado'] === 'nuevo' ? 'selected' : '' ?>>Nuevo</option>
                                <option value="atendido" <?= $r['estado'] === 'atendido' ? 'selected' : '' ?>>Atendido</option>
                                <option value="cerrado" <?= $r['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                            </select>
                            <button class="btn btn-fv btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar y notificar al cliente</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>