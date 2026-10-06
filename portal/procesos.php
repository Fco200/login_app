<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'procesos';
$titulo = 'Mis procesos';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => ''];

/* ---------- Confirmar entrega (completar el proceso) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    if (($_POST['accion'] ?? '') === 'confirmar_entrega') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        if ($proyectoId > 0) {
            $st = $pdo->prepare('SELECT id FROM proyectos_inicio WHERE id = ? AND usuario_id = ? AND estado = "en_desarrollo"');
            $st->execute([$proyectoId, (int)$usuario['id']]);
            if ($st->fetch()) {
                $pdo->prepare('UPDATE proyectos_inicio SET estado = "completado" WHERE id = ?')->execute([$proyectoId]);
                $cartaUrl = url_sitio('portal/carta_agradecimiento.php?proyecto=' . $proyectoId);
                registrar_historial_proyecto($proyectoId, 'entrega_confirmada', 'El cliente confirmó la recepción del proyecto. ¡Proyecto completado!', (int)$usuario['id']);
                notificar((int)$usuario['id'], 'exito', '¡Proyecto completado!', 'Confirmaste la entrega. Descarga tu carta de agradecimiento y los entregables finales desde tu proceso.', $cartaUrl);
                notificar_admins('proyecto', 'Cliente confirmó la entrega', ($usuario['nombre'] ?? 'Cliente') . ' confirmó la entrega del proyecto #' . $proyectoId . '.', url_sitio('admin/procesos.php'));
                responder(['ok' => true, 'mensaje' => '¡Felicidades por tu proyecto! Tu carta de agradecimiento está lista. Descárgala desde el botón "Carta de agradecimiento".', 'tipo' => 'success', 'destino' => url_sitio('portal/procesos.php?tab=entregados')]);
            }
            responder(['ok' => false, 'mensaje' => 'El proyecto no está listo para confirmar entrega.', 'tipo' => 'warning']);
        }
        responder(['ok' => false, 'mensaje' => 'Datos inválidos.', 'tipo' => 'danger']);
    }
}

date_default_timezone_set('America/Hermosillo');

$tab = (string)($_GET['tab'] ?? 'proceso');
if (!in_array($tab, ['proceso', 'entregados', 'historial'], true)) {
    $tab = 'proceso';
}

/* Estado del proyecto + color/icono */
$estadoProyecto = [
    'documentacion'      => ['badge text-bg-info',      'bi-file-earmark-text', 'Documentación',         20, 'info'],
    'anticipo_pendiente' => ['badge text-bg-warning',   'bi-hourglass-split',   'Anticipo pendiente',    40, 'warning'],
    'en_desarrollo'      => ['badge text-bg-primary',   'bi-code-slash',        'En desarrollo',         70, 'primary'],
    'liquidado'          => ['badge text-bg-success',   'bi-receipt-cutoff',    'Liquidado / Finalizado',90, 'success'],
    'completado'         => ['badge text-bg-success',   'bi-check-circle',      'Completado',            100, 'success'],
];

$estadosFiltro = $tab === 'entregados' ? ['completado'] : ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado'];
$inEst = implode(',', array_map(fn($e) => $pdo->quote($e), $estadosFiltro));

$stmt = $pdo->prepare("SELECT pi.*, s.tipo_servicio, s.presupuesto, s.creado_en AS solicitud_creada
                       FROM proyectos_inicio pi
                       LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                       WHERE pi.usuario_id = ? AND pi.estado IN ($inEst)
                       ORDER BY pi.creado_en DESC");
$stmt->execute([(int)$usuario['id']]);
$proyectos = $stmt->fetchAll();

/* Pagos del usuario vinculados por proyecto o solicitud, agrupados por proyecto */
$pagosMap = [];
$idsProy = array_map(fn($p) => (int)$p['id'], $proyectos);
$idsSol = array_map(fn($p) => (int)$p['solicitud_id'], $proyectos);
if ($idsProy) {
    $inProy = implode(',', $idsProy);
    $inSol = $idsSol ? implode(',', $idsSol) : '0';
    $stmtP = $pdo->query("SELECT p.*, mp.nombre AS metodo_nombre FROM pagos p LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                          WHERE p.usuario_id = " . (int)$usuario['id'] . " AND (p.proyecto_id IN ($inProy) OR p.solicitud_id IN ($inSol))
                          ORDER BY p.creado_en DESC");
    foreach ($stmtP as $p) {
        $clave = (int)($p['proyecto_id'] ?: $p['solicitud_id']);
        $pagosMap[$clave][] = $p;
    }
}

/* Entregables subidos por el equipo, agrupados por proyecto */
$entregablesMap = [];
if ($idsProy) {
    $inProy = implode(',', $idsProy);
    $stmtE = $pdo->query("SELECT * FROM entregables WHERE proyecto_id IN ($inProy) ORDER BY creado_en DESC");
    foreach ($stmtE as $en) {
        $entregablesMap[(int)$en['proyecto_id']][] = $en;
    }
}

$contEspera = (int)contar_registros('proyectos_inicio', "usuario_id = " . (int)$usuario['id'] . " AND estado IN ('documentacion','anticipo_pendiente','en_desarrollo','liquidado')");
$contEntregados = (int)contar_registros('proyectos_inicio', "usuario_id = " . (int)$usuario['id'] . " AND estado = 'completado'");
$contHist = (int)contar_registros('proyecto_historial', "proyecto_id IN (SELECT id FROM proyectos_inicio WHERE usuario_id = " . (int)$usuario['id'] . ")");
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="mb-1">Mis procesos</h4>
        <p class="text-muted mb-0 small">El avance de tus proyectos y su documentación.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="pagos" class="btn btn-outline-fv btn-sm"><i class="bi bi-credit-card me-1"></i>Mis pagos</a>
        <a href="mis-solicitudes" class="btn btn-fv btn-sm"><i class="bi bi-inbox me-1"></i>Mis solicitudes</a>
    </div>
</div>

<div class="nav nav-tabs nav-tabs-fv mb-4" role="tablist">
    <a class="nav-link <?= $tab === 'proceso' ? 'active' : '' ?>" href="procesos?tab=proceso"><i class="bi bi-bezier2 me-1"></i>En proceso (<?= $contEspera ?>)</a>
    <a class="nav-link <?= $tab === 'entregados' ? 'active' : '' ?>" href="procesos?tab=entregados"><i class="bi bi-trophy me-1"></i>Entregados (<?= $contEntregados ?>)</a>
    <a class="nav-link <?= $tab === 'historial' ? 'active' : '' ?>" href="procesos?tab=historial"><i class="bi bi-journal-text me-1"></i>Historial (<?= $contHist ?>)</a>
</div>

<?php if ($tab === 'historial'): ?>
    <?php
    $historial = $pdo->query('SELECT h.*, pi.id AS proy_id, s.tipo_servicio, u.nombre AS usuario_nombre
                              FROM proyecto_historial h
                              LEFT JOIN proyectos_inicio pi ON h.proyecto_id = pi.id
                              LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                              LEFT JOIN usuarios u ON h.usuario_id = u.id
                              WHERE pi.usuario_id = ' . (int)$usuario['id'] . '
                              ORDER BY h.creado_en DESC LIMIT 120')->fetchAll();
    ?>
    <div class="card portal-card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (!$historial): ?>
                <p class="text-center text-muted py-5 mb-0"><i class="bi bi-journal-x d-block fs-2 mb-2"></i>Tu historial aparecerá aquí cuando tengas movimientos.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm tabla-admin mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-4">Fecha</th><th>Proyecto</th><th>Acción</th><th class="pe-4">Detalle</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr>
                                    <td class="ps-4 small text-muted text-nowrap"><?= e(date('d/m/Y H:i', strtotime($h['creado_en']))) ?></td>
                                    <td class="small">#<?= (int)$h['proy_id'] ?><?php if ($h['tipo_servicio']): ?> · <?= e($h['tipo_servicio']) ?><?php endif; ?></td>
                                    <td><span class="badge text-bg-light text-capitalize"><?= e(str_replace('_', ' ', $h['accion'])) ?></span></td>
                                    <td class="pe-4 small"><?= e($h['detalle'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif (!$proyectos): ?>
    <div class="card portal-card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-box-seam fs-1 text-primary d-block mb-3"></i>
        <h5><?= $tab === 'entregados' ? 'Aún no has recibido entregas' : 'No tienes procesos activos' ?></h5>
        <p class="text-muted"><?= $tab === 'entregados' ? 'Los proyectos que se entreguen aparecerán aquí con su carta de agradecimiento.' : 'Cuando una solicitud sea completada podrás iniciar tu proyecto y seguir su avance aquí.' ?></p>
        <a href="mis-solicitudes" class="btn btn-fv mx-auto"><i class="bi bi-inbox me-1"></i>Ver mis solicitudes</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($proyectos as $i => $p):
            [$pBg, $pIco, $pTxt, $pct, $pColorVar] = $estadoProyecto[$p['estado']] ?? ['badge text-bg-light', 'bi-dot', ucfirst($p['estado']), 10, 'secondary'];
            $pendientePago = (float)$p['anticipo_pagado'] < (float)$p['anticipo_minimo'];
            $saldoRestante = max(0, (float)($p['saldo_restante'] ?? 0));
            $totalProyecto = max(0, (float)($p['total_proyecto'] ?? 0));
            $pagadoTotal   = max(0, (float)($p['pagado_total'] ?? 0));
            $esLiquidado   = ($p['estado'] ?? '') === 'liquidado'
                || ($totalProyecto > 0 && $saldoRestante <= 0.01);
            $pagos = $pagosMap[(int)$p['id']] ?? ($pagosMap[(int)$p['solicitud_id']] ?? []);
            $entregables = $entregablesMap[(int)$p['id']] ?? [];
            ?>
            <div class="col-12">
                <div class="card portal-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <i class="bi <?= $pIco ?> me-2 text-primary"></i><?= e($p['tipo_servicio'] ?: 'Proyecto #' . (int)$p['id']) ?>
                                </h5>
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>Iniciado: <?= e(date('d/m/Y', strtotime($p['creado_en']))) ?>
                                    <?php if ($p['presupuesto']): ?> · <i class="bi bi-cash me-1"></i>Cotización: <?= e($p['presupuesto']) ?><?php endif; ?>
                                </small>
                            </div>
                            <span class="<?= $pBg ?>"><i class="bi <?= $pIco ?> me-1"></i><?= $pTxt ?></span>
                        </div>

                        <?php if ($p['estado'] === 'completado'): ?>
                            <div class="alert alert-success small py-2 mb-3">
                                <i class="bi bi-trophy me-1"></i><b>¡Gracias por confiar en <?= e(SITE_NOMBRE) ?>!</b> Tu proyecto fue entregado con éxito. Descarga tu carta de agradecimiento, tus entregables finales y, si necesitas algo más, habla con nosotros.
                            </div>
                        <?php elseif ($p['estado'] === 'liquidado' || $esLiquidado): ?>
                            <div class="alert alert-success small py-2 mb-3">
                                <i class="bi bi-receipt-cutoff me-1"></i><b>Proyecto liquidado.</b> Saldo cubierto en su totalidad. Total de tu proyecto: <b>$<?= number_format($totalProyecto, 0) ?> MXN</b>. Puedes solicitar tu factura en la sección de Facturación.
                            </div>
                        <?php elseif ($p['estado'] === 'en_desarrollo'): ?>
                            <div class="alert alert-primary small py-2 mb-3">
                                <i class="bi bi-code-slash me-1"></i><b>Tu proyecto está en desarrollo.</b> Todo lo que el equipo te entregue aparecerá aquí.
                            </div>
                        <?php elseif ($p['estado'] === 'documentacion'): ?>
                            <div class="alert alert-info small py-2 mb-3">
                                <i class="bi bi-hourglass-split me-1"></i><b>Paso 1/4 completado.</b> Tu documentación fue enviada. <b>Paso 2:</b> paga tu anticipo para que el equipo lo apruebe y arranque tu proyecto.
                            </div>
                        <?php endif; ?>

                        <!-- Barra de progreso visual -->
                        <div class="mb-2 d-flex justify-content-between align-items-center small">
                            <span class="fw-semibold">Avance del proceso</span>
                            <span class="text-muted"><?= $pct ?>%</span>
                        </div>
                        <div class="progress mb-4" style="height:10px;">
                            <div class="progress-bar progress-bar-striped <?= $pct >= 70 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : ($pct >= 20 ? 'bg-info' : 'bg-danger')) ?>"
                                 role="progressbar" style="width:<?= $pct ?>%;" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Documentación -->
                            <div class="col-lg-7">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body py-3">
                                        <h6 class="fw-bold"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Documentación del proyecto</h6>
                                        <dl class="row small mb-0">
                                            <?php if ($p['descripcion_proyecto']): ?>
                                                <dt class="col-sm-4 text-muted">Descripción</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['descripcion_proyecto']) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($p['requisitos']): ?>
                                                <dt class="col-sm-4 text-muted">Requisitos</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['requisitos']) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($p['objetivos']): ?>
                                                <dt class="col-sm-4 text-muted">Objetivos</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['objetivos']) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($p['alcance']): ?>
                                                <dt class="col-sm-4 text-muted">Alcance</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['alcance']) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($p['cronograma']): ?>
                                                <dt class="col-sm-4 text-muted">Cronograma</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['cronograma']) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($p['entregables']): ?>
                                                <dt class="col-sm-4 text-muted">Entregables</dt>
                                                <dd class="col-sm-8" style="white-space:pre-line;"><?= e($p['entregables']) ?></dd>
                                            <?php endif; ?>
                                            <?php if (!$p['descripcion_proyecto'] && !$p['requisitos'] && !$p['objetivos'] && !$p['alcance'] && !$p['cronograma'] && !$p['entregables']): ?>
                                                <dd class="col-12 text-muted">Sin documentación registrada todavía.</dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagos / avance financiero -->
                            <div class="col-lg-5">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body py-3">
                                        <h6 class="fw-bold"><i class="bi bi-credit-card me-1 text-success"></i>Estado de pagos</h6>
                                        <div class="small mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Total de tu proyecto</span>
                                                <b><?= $totalProyecto > 0 ? '$' . number_format($totalProyecto, 0) . ' MXN' : e($p['presupuesto'] ?: 'A convenir') ?></b>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Pagado aprobado</span>
                                                <b class="<?= $esLiquidado ? 'text-success' : 'text-warning' ?>">$<?= number_format($pagadoTotal, 0) ?> MXN</b>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Saldo restante</span>
                                                <b class="<?= $esLiquidado ? 'text-success' : 'text-danger' ?>">$<?= number_format($saldoRestante, 0) ?> MXN</b>
                                            </div>
                                            <div class="progress mt-2 mb-2" style="height:8px;">
                                                <div class="progress-bar <?= $esLiquidado ? 'bg-success' : ($pendientePago ? 'bg-warning' : 'bg-success') ?>"
                                                     style="width:<?= $totalProyecto > 0 ? min(100, round($pagadoTotal / $totalProyecto * 100)) : 0 ?>%;"></div>
                                            </div>
                                            <?php if ($esLiquidado): ?>
                                                <div class="text-success fw-semibold"><i class="bi bi-receipt-cutoff me-1"></i>Proyecto liquidado: saldo cubierto</div>
                                            <?php elseif ($pendientePago): ?>
                                                <div class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Anticipo cubierto</div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($pagos): ?>
                                            <hr>
                                            <h6 class="small fw-bold mb-2"><i class="bi bi-clock-history me-1"></i>Historial de pagos</h6>
                                            <ul class="list-unstyled small mb-2" style="max-height:140px;overflow-y:auto;">
                                                <?php foreach (array_slice($pagos, 0, 5) as $pg): ?>
                                                    <li class="mb-1 d-flex justify-content-between gap-2">
                                                        <span>
                                                            <i class="bi bi-check-circle-fill text-success me-1"></i>$<?= number_format((float)$pg['monto'], 0) ?> MXN
                                                            <small class="text-muted d-block ms-4"><?= e(date('d/m/Y', strtotime($pg['creado_en']))) ?> · <?= e(ucfirst($pg['tipo_pago'])) ?><?= $pg['estado'] ? ' · ' . e(ucfirst($pg['estado'])) : '' ?></small>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php if ($entregables && in_array($p['estado'], ['en_desarrollo', 'completado'], true)): ?>
                                            <hr>
                                            <h6 class="small fw-bold mb-2">
                                                <?php if ($p['estado'] === 'completado'): ?>
                                                    <i class="bi bi-trophy me-1 text-success"></i>Entregables listos para descargar
                                                <?php else: ?>
                                                    <i class="bi bi-paperclip me-1 text-primary"></i>Entregables de avance
                                                <?php endif; ?>
                                            </h6>
                                            <ul class="list-unstyled small mb-0">
                                                <?php foreach ($entregables as $en): ?>
                                                    <li class="mb-1">
                                                        <a href="descargar-entregable.php?id=<?= (int)$en['id'] ?>" class="btn btn-sm <?= $p['estado'] === 'completado' ? 'btn-success' : 'btn-outline-secondary' ?> w-100 text-start">
                                                            <i class="bi bi-download me-1"></i><?= e($en['titulo']) ?>
                                                            <?php if ($en['notas']): ?><small class="d-block text-muted ms-4"><?= e($en['notas']) ?></small><?php endif; ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif ($entregables): ?>
                                            <hr>
                                            <h6 class="small fw-bold mb-2 text-muted"><i class="bi bi-paperclip me-1"></i>Entregables</h6>
                                            <p class="small text-muted mb-0"><?= count($entregables) ?> archivo(s) subidos por el equipo. Se habilitan para descarga cuando el proyecto esté en desarrollo o completado.</p>
                                        <?php endif; ?>

                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <?php if (in_array($p['estado'], ['documentacion', 'anticipo_pendiente'], true) && $pendientePago): ?>
                                                <a href="mis-solicitudes#sol-<?= (int)$p['solicitud_id'] ?>" class="btn btn-sm btn-fv"><i class="bi bi-credit-card me-1"></i><?= $p['estado'] === 'documentacion' ? 'Pagar anticipo' : 'Completar anticipo' ?></a>
                                            <?php endif; ?>
                                            <?php if (!$esLiquidado && in_array($p['estado'], ['en_desarrollo', 'anticipo_pendiente'], true)): ?>
                                                <a href="pagos" class="btn btn-sm btn-outline-success"><i class="bi bi-receipt me-1"></i>Ver saldo y pagos</a>
                                            <?php endif; ?>
                                            <?php if ($esLiquidado || $saldoRestante <= 0): ?>
                                                <a href="facturacion" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt-cutoff me-1"></i>Solicitar factura</a>
                                            <?php endif; ?>
                                            <?php if ($p['estado'] === 'en_desarrollo' && $entregables): ?>
                                                <form method="POST" class="js-ajax d-inline" onsubmit="return confirm('¿Confirmas que recibiste el proyecto y marcarlo como completado? Esta acción cierra el proceso.');">
                                                    <?= campo_csrf() ?>
                                                    <input type="hidden" name="accion" value="confirmar_entrega">
                                                    <input type="hidden" name="proyecto_id" value="<?= (int)$p['id'] ?>">
                                                    <button class="btn btn-sm btn-success"><i class="bi bi-trophy me-1"></i>Confirmar entrega</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($p['estado'] === 'completado'): ?>
                                                <a href="carta_agradecimiento.php?proyecto=<?= (int)$p['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-envelope-heart me-1"></i>Carta de agradecimiento</a>
                                            <?php endif; ?>
                                            <a href="chat?solicitud=<?= (int)$p['solicitud_id'] ?>" class="btn btn-sm btn-outline-fv"><i class="bi bi-chat-dots me-1"></i>Hablar del proyecto</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline del proyecto -->
                        <div class="mt-4">
                            <h6 class="small fw-bold text-uppercase text-muted mb-3"><i class="bi bi-bezier2 me-1"></i>Línea de tiempo</h6>
                            <div class="timeline">
                                <?php
                                $hitosProyecto = [
                                    'documentacion'      => ['Documentación enviada', 'bi-file-earmark-check', 'Recibimos tus datos del proyecto.'],
                                    'anticipo_pendiente' => ['Anticipo requerido', 'bi-credit-card', 'Se requiere el anticipo mínimo de $2,500 MXN para arrancar.'],
                                    'en_desarrollo'      => ['En desarrollo', 'bi-code-slash', 'El equipo está construyendo tu proyecto.'],
                                    'liquidado'          => ['Liquidado / Finalizado', 'bi-receipt-cutoff', 'Saldo cubierto en su totalidad: $' . number_format($totalProyecto, 0) . ' MXN.'],
                                    'completado'         => ['Proyecto entregado', 'bi-trophy', '¡Felicidades por tu entrega! Gracias por confiar en nosotros: tu carta de agradecimiento está lista.'],
                                ];
                                $ordenHitos = ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'];
                                $posActual = array_search($p['estado'], $ordenHitos, true) ?: 0;
                                foreach ($ordenHitos as $j => $claveHito):
                                    [$txtH, $icoH, $descH] = $hitosProyecto[$claveHito];
                                    $completadoH = $j <= $posActual;
                                ?>
                                    <div class="hito <?= $j === $posActual ? 'actual' : '' ?>">
                                        <div class="hito-punto"><i class="bi bi-<?= $completadoH || $j === $posActual ? $icoH : 'circle' ?>"></i></div>
                                        <div class="hito-cuerpo">
                                            <b class="<?= $completadoH || $j === $posActual ? '' : 'text-muted' ?>"><?= e($txtH) ?></b>
                                            <span class="d-block small text-muted"><?= e($descH) ?></span>
                                            <?php if ($completadoH): ?>
                                                <small class="text-success"><i class="bi bi-check-circle me-1"></i><?= $j === $posActual ? 'En curso ahora' : 'Completado' ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Bitácora del proyecto -->
                        <?php
                        $historial = [];
                        $stH = $pdo->prepare('SELECT h.*, u.nombre AS usuario_nombre FROM proyecto_historial h LEFT JOIN usuarios u ON h.usuario_id = u.id WHERE h.proyecto_id = ? ORDER BY h.creado_en DESC LIMIT 12');
                        $stH->execute([(int)$p['id']]);
                        $historial = $stH->fetchAll();
                        ?>
                        <?php if ($historial): ?>
                        <div class="mt-4">
                            <h6 class="small fw-bold text-uppercase text-muted mb-3"><i class="bi bi-journal-text me-1"></i>Bitácora del proyecto</h6>
                            <div class="table-responsive">
                                <table class="table table-sm small mb-0">
                                    <thead class="table-light">
                                        <tr><th>Fecha</th><th>Acción</th><th>Detalle</th><th>Quién</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historial as $h): ?>
                                            <tr>
                                                <td class="text-muted text-nowrap"><?= e(date('d/m/Y H:i', strtotime($h['creado_en']))) ?></td>
                                                <td><span class="badge text-bg-light text-capitalize"><?= e(str_replace('_', ' ', $h['accion'])) ?></span></td>
                                                <td><?= e($h['detalle']) ?></td>
                                                <td class="text-muted"><?= e($h['usuario_nombre'] ?? 'Sistema') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>