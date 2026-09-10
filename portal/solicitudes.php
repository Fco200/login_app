<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'solicitudes';
$titulo = 'Mis solicitudes';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => ''];

$stmt = $pdo->prepare('SELECT * FROM solicitudes WHERE usuario_id = ? OR LOWER(email) = LOWER(?) ORDER BY creado_en DESC');
$stmt->execute([(int)$usuario['id'], $usuario['email']]);
$solicitudes = $stmt->fetchAll();

$estados = [
    'nueva'       => ['badge text-bg-danger', 'bi-file-earmark-plus', 'Nueva'],
    'en_proceso'  => ['badge text-bg-warning', 'bi-gear', 'En proceso'],
    'completada'  => ['badge text-bg-success', 'bi-check-circle', 'Completada'],
    'rechazada'   => ['badge text-bg-secondary', 'bi-x-circle', 'Rechazada'],
];
$estado = fn($s) => $estados[$s['estado']] ?? ['badge text-bg-light', 'bi-question-circle', ucfirst($s['estado'])];

/* Pasos del proceso: el cliente ve SIEMPRE el avance de su solicitud */
$pasosProceso = [
    'nueva'      => ['Recibida',      'bg-danger text-white'],
    'en_proceso' => ['En proceso',    'bg-warning text-dark'],
    'completada' => ['Completada',    'bg-success text-white'],
];

/* ---------- Historial por solicitud ---------- */
$historiales = [];
if ($solicitudes) {
    $ids = array_map(fn($s) => (int)$s['id'], $solicitudes);
    $in = implode(',', $ids);
    $stmtH = $pdo->query("SELECT * FROM solicitud_historial WHERE solicitud_id IN ($in) ORDER BY creado_en ASC");
    foreach ($stmtH as $h) {
        $historiales[(int)$h['solicitud_id']][] = $h;
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Mis solicitudes</h4>
        <p class="text-muted mb-0">Da seguimiento al estado de cada cotización y mira su historial.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="nueva-solicitud-empresa" class="btn btn-outline-fv btn-sm"><i class="bi bi-buildings me-1"></i>Para empresas</a>
        <a href="nueva-solicitud" class="btn btn-fv btn-sm"><i class="bi bi-plus-lg me-1"></i>Nueva solicitud</a>
    </div>
</div>

<?php if (!$solicitudes): ?>
    <div class="card portal-card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-inbox fs-1 text-primary d-block mb-3"></i>
        <h5>Aún no tienes solicitudes</h5>
        <p class="text-muted">Cuéntanos qué necesitas y comienza tu proyecto con nosotros.</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="nueva-solicitud" class="btn btn-fv"><i class="bi bi-send me-1"></i>Enviar mi primera solicitud</a>
            <a href="nueva-solicitud-empresa" class="btn btn-outline-fv"><i class="bi bi-buildings me-1"></i>¿Es para tu empresa?</a>
            <a href="../servicios.php" class="btn btn-outline-fv"><i class="bi bi-grid me-1"></i>Ver servicios</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($solicitudes as $s): [$bg, $ic, $txt] = $estado($s); $h = $historiales[(int)$s['id']] ?? [];
            $reuso = $s['tipo_solicitud'] ?? 'individual';
            $rutaReuso = $reuso === 'empresa' ? 'nueva-solicitud-empresa' : 'nueva-solicitud'; ?>
            <div class="col-12">
                <div class="card portal-card border-0 shadow-sm" id="sol-<?= (int)$s['id'] ?>">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <h5 class="mb-0">
                                    <?= e($s['tipo_servicio']) ?>
                                    <?php if (($s['tipo_solicitud'] ?? '') === 'empresa'): ?>
                                        <span class="badge text-bg-primary align-middle ms-1"><i class="bi bi-buildings me-1"></i>Empresa</span>
                                    <?php endif; ?>
                                </h5>
                                <small class="text-muted">
                                    <?php if (($s['tipo_solicitud'] ?? '') === 'empresa' && $s['empresa']): ?>
                                        <i class="bi bi-briefcase me-1"></i><?= e($s['empresa']) ?><?= $s['cargo'] ? ' · ' . e($s['cargo']) : '' ?> ·
                                    <?php endif; ?>
                                    <i class="bi bi-calendar3 me-1"></i><?= e(date('d/m/Y H:i', strtotime($s['creado_en']))) ?>
                                </small>
                            </div>
                            <span class="<?= $bg ?>"><i class="bi <?= $ic ?> me-1"></i><?= $txt ?></span>
                        </div>
                        <?php if ($s['mensaje']): ?>
                            <p class="text-muted small mb-3"><?= e($s['mensaje']) ?></p>
                        <?php endif; ?>

                        <?php if ($h): ?>
                            <div class="timeline mt-3 mb-3">
                                <?php
                                $ultimo = (int)count($h);
                                foreach ($h as $i => $hito):
                                    $cli = $i === $ultimo - 1 ? 'actual' : '';
                                    $ico = match ($hito['estado']) {
                                        'nueva'      => 'file-earmark-plus',
                                        'en_proceso' => 'gear',
                                        'completada' => 'check-circle',
                                        'rechazada'  => 'x-circle',
                                        default      => 'dot',
                                    };
                                ?>
                                    <div class="hito <?= $cli ?>">
                                        <div class="hito-punto"><i class="bi bi-<?= $ico ?>"></i></div>
                                        <div class="hito-cuerpo">
                                            <b><?= e(ucfirst(str_replace('_', ' ', $hito['estado']))) ?></b>
                                            <?php if ($hito['nota']): ?><span class="d-block small text-muted"><?= e($hito['nota']) ?></span><?php endif; ?>
                                            <small class="text-muted"><?= e(date('d/m/Y H:i', strtotime($hito['creado_en']))) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-fv-light small py-2 mb-3">
                                <i class="bi bi-info-circle me-1"></i>Solicitud registrada. La actualizaremos con cada avance.
                            </div>
                        <?php endif; ?>

                        <?php
                        $ordenPasos = ['nueva', 'en_proceso', 'completada'];
                        $idxActual = array_search($s['estado'], $ordenPasos, true);
                        $rechazada = $s['estado'] === 'rechazada';
                        $numPaso = $idxActual === false ? 0 : $idxActual + 1;
                        ?>
                        <div class="proceso-pasos d-flex align-items-center mt-3 mb-3">
                            <?php foreach ($pasosProceso as $clave => $paso): [$nombrePaso, $color] = $paso;
                                $pos = array_search($clave, $ordenPasos, true);
                                $completado = !$rechazada && $idxActual !== false && $pos <= $idxActual;
                                $esActual = !$rechazada && $clave === $s['estado'];
                            ?>
                                <div class="flex-grow-1">
                                    <div class="text-center small rounded-pill py-1 px-2 <?= $completado ? $color : 'bg-light text-muted' ?> <?= $esActual ? 'border border-dark-subtle fw-bold' : '' ?>">
                                        <?= $completado ? '<i class="bi bi-check-lg me-1"></i>' : '' ?><?= $nombrePaso ?>
                                    </div>
                                </div>
                                <?php if ($clave !== 'completada'): ?><div class="flex-shrink-0" style="width:14px;height:2px;background:#c9d6ea;"></div><?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($rechazada): ?>
                            <div class="alert alert-danger small py-2 mb-3"><i class="bi bi-x-circle me-1"></i><b>Solicitud rechazada.</b> Conversa con nosotros para conocer los detalles o ajustarla.</div>
                        <?php elseif ($idxActual !== false): ?>
                            <small class="text-muted d-block mb-1">Paso <?= $numPaso ?> de <?= count($pasosProceso) ?>: <b><?= $pasosProceso[$s['estado']][0] ?></b></small>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= $rutaReuso ?>?reutilizar=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-fv" title="Copia los datos de esta solicitud para enviarla de nuevo"><i class="bi bi-arrow-repeat me-1"></i>Reutilizar</a>
                            <a href="chat?solicitud=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-chat-dots me-1"></i>Chat sobre esta solicitud</a>
                            <?php if (in_array($s['estado'], ['nueva', 'en_proceso'], true)): ?>
                                <a href="juegos" class="btn btn-sm btn-outline-fv"><i class="bi bi-controller me-1"></i>Juega mientras esperamos</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>