<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'solicitudes';
$titulo = 'Mis solicitudes';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => '', 'nombre' => $_SESSION['nombre'] ?? 'Cliente'];

/* ---------- Manejar POST: iniciar proyecto ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'iniciar_proyecto') {
        $solicitudId = (int)($_POST['solicitud_id'] ?? 0);
        if ($solicitudId > 0) {
            $stmtChk = $pdo->prepare('SELECT id, estado, usuario_id, presupuesto FROM solicitudes WHERE id = ? AND (usuario_id = ? OR LOWER(email) = LOWER(?))');
            $stmtChk->execute([$solicitudId, (int)$usuario['id'], $usuario['email']]);
            $solChk = $stmtChk->fetch();

            if ($solChk && $solChk['estado'] === 'completada') {
                $existeProj = $pdo->prepare('SELECT id FROM proyectos_inicio WHERE solicitud_id = ? AND usuario_id = ?');
                $existeProj->execute([$solicitudId, (int)$usuario['id']]);
                if (!$existeProj->fetch()) {
                    /* Total del proyecto desde el presupuesto de la solicitud. */
                    $totalProyecto = proyecto_total_parsear($solChk['presupuesto'] ?? '');
                    $pdo->prepare('INSERT INTO proyectos_inicio (solicitud_id, usuario_id, descripcion_proyecto, requisitos, objetivos, alcance, cronograma, entregables, total_proyecto, saldo_restante) VALUES (?,?,?,?,?,?,?,?,?,?)')
                        ->execute([
                            $solicitudId,
                            (int)$usuario['id'],
                            trim($_POST['descripcion_proyecto'] ?? ''),
                            trim($_POST['requisitos'] ?? ''),
                            trim($_POST['objetivos'] ?? ''),
                            trim($_POST['alcance'] ?? ''),
                            trim($_POST['cronograma'] ?? ''),
                            trim($_POST['entregables'] ?? ''),
                            $totalProyecto > 0 ? $totalProyecto : 0,
                            $totalProyecto > 0 ? $totalProyecto : 0,
                        ]);
                    notificar_admins('proyecto', 'Documentación de proyecto recibida', $usuario['nombre'] . ' inició la documentación para la solicitud #' . $solicitudId . '.', url_sitio('admin/solicitudes.php'));
                    responder(['ok' => true, 'mensaje' => 'Documentación del proyecto enviada correctamente. Ahora realiza el anticipo para iniciar.', 'tipo' => 'success', 'destino' => url_sitio('portal/solicitudes.php')]);
                } else {
                    responder(['ok' => false, 'mensaje' => 'Ya iniciaste el proyecto para esta solicitud.', 'tipo' => 'warning']);
                }
            } else {
                responder(['ok' => false, 'mensaje' => 'Solicitud no válida.', 'tipo' => 'danger']);
            }
        }
        responder(['ok' => false, 'mensaje' => 'Datos inválidos.', 'tipo' => 'danger']);
    }

    if ($accion === 'pagar_anticipo') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $metodoId = (int)($_POST['metodo_pago_id'] ?? 0);
        if ($proyectoId <= 0 || $monto <= 0) {
            responder(['ok' => false, 'mensaje' => 'Datos inválidos.', 'tipo' => 'danger']);
        }
        $stmtProj = $pdo->prepare('SELECT id, estado FROM proyectos_inicio WHERE id = ? AND usuario_id = ?');
        $stmtProj->execute([$proyectoId, (int)$usuario['id']]);
        if (!$stmtProj->fetch()) {
            responder(['ok' => false, 'mensaje' => 'El proyecto no existe o no te pertenece.', 'tipo' => 'danger']);
        }
        if (empty($_FILES['comprobante']['name']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            responder(['ok' => false, 'mensaje' => 'Es obligatorio subir el comprobante de pago del anticipo.', 'tipo' => 'warning']);
        }
        $comprobanteRuta = null;
        if (!empty($_FILES['comprobante']['name'])) {
            $res = subir_archivo('comprobante', 'comprobantes', ['jpg','jpeg','png','pdf','webp'], 8);
            if ($res['ok']) {
                $comprobanteRuta = $res['archivo'];
            } else {
                responder(['ok' => false, 'mensaje' => 'Error con el comprobante: ' . $res['error'], 'tipo' => 'danger']);
            }
        }
        /* Registro central con transacción, idempotencia y bitácora. */
        $r = pago_registrar([
            'usuario_id'     => (int)$usuario['id'],
            'proyecto_id'    => $proyectoId,
            'tipo_pago'      => 'anticipo',
            'monto'          => $monto,
            'metodo_pago_id' => $metodoId,
            'comprobante'    => $comprobanteRuta,
            'clave_unica'    => trim((string)($_POST['clave_unica'] ?? '')),
            'notas'          => 'Anticipo para proyecto #' . $proyectoId,
        ]);
        if ($r['ok']) {
            responder([
                'ok'      => true,
                'mensaje' => !empty($r['ya_existia'])
                    ? 'Tu anticipo ya había sido registrado; no se duplicó.'
                    : 'Pago de anticipo registrado. Será revisado por nuestro equipo y tu proyecto avanzará a desarrollo cuando lo aprobemos.',
                'tipo'    => !empty($r['ya_existia']) ? 'info' : 'success',
                'destino' => url_sitio('portal/procesos.php'),
            ]);
        }
        responder(['ok' => false, 'mensaje' => $r['mensaje'], 'tipo' => 'danger']);
    }
}

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

$pasosProceso = [
    'nueva'      => ['Recibida',      'bg-danger text-white'],
    'en_proceso' => ['En proceso',    'bg-warning text-dark'],
    'completada' => ['Completada',    'bg-success text-white'],
];

/* Proyectos iniciados por el usuario */
$proyectosMap = [];
$stmtProj = $pdo->prepare('SELECT pi.*, s.presupuesto FROM proyectos_inicio pi LEFT JOIN solicitudes s ON pi.solicitud_id = s.id WHERE pi.usuario_id = ?');
$stmtProj->execute([(int)$usuario['id']]);
foreach ($stmtProj->fetchAll() as $p) {
    $proyectosMap[(int)$p['solicitud_id']] = $p;
}

/* Métodos de pago activos */
$metodosPago = $pdo->query('SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY nombre ASC')->fetchAll();

/* Historial por solicitud */
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
            $rutaReuso = $reuso === 'empresa' ? 'nueva-solicitud-empresa' : 'nueva-solicitud';
            $proj = $proyectosMap[(int)$s['id']] ?? null; ?>
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

                        <?php if ($s['estado'] === 'completada'): ?>
                            <?php if ($proj): ?>
                                <!-- Proyecto ya iniciado: mostrar estado -->
                                <div class="mt-3">
                                    <?php
                                    $projEstados = [
                                        'documentacion'       => ['badge text-bg-info', 'bi-file-text', 'Documentación'],
                                        'anticipo_pendiente'  => ['badge text-bg-warning', 'bi-clock', 'Anticipo pendiente'],
                                        'en_desarrollo'       => ['badge text-bg-primary', 'bi-code-slash', 'En desarrollo'],
                                        'completado'          => ['badge text-bg-success', 'bi-check-circle', 'Completado'],
                                    ];
                                    [$pBg, $pIco, $pTxt] = $projEstados[$proj['estado']] ?? ['badge text-bg-light', 'bi-question', ucfirst($proj['estado'])];
                                    $mostrarPago = (float)$proj['anticipo_pagado'] < (float)$proj['anticipo_minimo']
                                        && in_array($proj['estado'], ['documentacion', 'anticipo_pendiente'], true);
                                    ?>
                                    <div class="card border-0 shadow-sm bg-light">
                                        <div class="card-body py-3">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                <h6 class="mb-0"><i class="bi bi-rocket-takeoff me-1 text-primary"></i><b>Proyecto iniciado</b></h6>
                                                <span class="<?= $pBg ?>"><i class="bi <?= $pIco ?> me-1"></i><?= $pTxt ?></span>
                                            </div>
                                            <div class="row g-3 small text-muted mb-3">
                                                <?php if ($proj['descripcion_proyecto']): ?>
                                                    <div class="col-md-6"><b>Descripción:</b> <?= e(mb_strimwidth($proj['descripcion_proyecto'], 0, 150, '…')) ?></div>
                                                <?php endif; ?>
                                                <?php if ($proj['objetivos']): ?>
                                                    <div class="col-md-6"><b>Objetivos:</b> <?= e(mb_strimwidth($proj['objetivos'], 0, 150, '…')) ?></div>
                                                <?php endif; ?>
                                                <div class="col-12">
                                                    <b>Anticipo:</b>
                                                    <span class="text-success fw-bold">$<?= number_format((float)$proj['anticipo_pagado'], 0) ?> MXN</span>
                                                    / $<?= number_format((float)$proj['anticipo_minimo'], 0) ?> MXN mínimo
                                                    <?php if ((float)$proj['anticipo_pagado'] < (float)$proj['anticipo_minimo']): ?>
                                                        <span class="badge text-bg-warning ms-1">Falta $<?= number_format((float)$proj['anticipo_minimo'] - (float)$proj['anticipo_pagado'], 0) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge text-bg-success ms-1"><i class="bi bi-check-lg me-1"></i>Anticipo cubierto</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($mostrarPago): ?>
                                                <!-- Botón para pagar anticipo -->
                                                <button class="btn btn-sm btn-fv" data-bs-toggle="modal" data-bs-target="#modalAnticipo<?= (int)$s['id'] ?>"><i class="bi bi-credit-card me-1"></i><?= $proj['estado'] === 'documentacion' ? 'Pagar anticipo para iniciar' : 'Pagar anticipo' ?></button>
                                            <?php elseif (in_array($proj['estado'], ['en_desarrollo', 'completado'], true)): ?>
                                                <a href="procesos" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver progreso del proyecto</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal de anticipo -->
                                <?php if ($mostrarPago): ?>
                                <div class="modal fade" id="modalAnticipo<?= (int)$s['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" class="js-ajax" action="solicitudes.php" enctype="multipart/form-data">
                                                <?= campo_csrf() ?>
                                                <input type="hidden" name="accion" value="pagar_anticipo">
                                                <input type="hidden" name="proyecto_id" value="<?= (int)$proj['id'] ?>">
                                                <input type="hidden" name="clave_unica" value="<?= e(pago_generar_clave(['proyecto' => (int)$proj['id'], 'usuario' => (int)$usuario['id'], 'accion' => 'pagar_anticipo', 's' => session_id()])) ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-credit-card me-2 text-primary"></i>Pago de anticipo</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-danger d-flex align-items-start gap-2 mb-3">
                                                        <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 mt-1"></i>
                                                        <div>
                                                            <b>AVISO:</b> El proyecto NO se iniciará sin un anticipo mínimo de <b>$2,500 MXN</b>. Este anticipo se descontará del total de la cotización.
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold">Monto del anticipo (mínimo $2,500 MXN)</label>
                                                            <input type="number" name="monto" class="form-control" min="<?= (float)$proj['anticipo_minimo'] ?>" step="0.01" value="<?= (float)$proj['anticipo_minimo'] ?>" required>
                                                            <small class="text-muted">Saldo restante después del anticipo: $<?= number_format(max(0, ((float)($proj['presupuesto'] ?? 5000)) - (float)$proj['anticipo_minimo']), 0) ?> MXN (aprox.)</small>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold">Método de pago</label>
                                                            <select name="metodo_pago_id" class="form-select metodo-pago-anticipo" required>
                                                                <option value="">Selecciona un método...</option>
                                                                <?php foreach ($metodosPago as $mp): ?>
                                                                    <option value="<?= (int)$mp['id'] ?>" data-nombre="<?= e($mp['nombre']) ?>" data-descripcion="<?= e($mp['descripcion'] ?? '') ?>" data-detalles="<?= e($mp['detalles_cuenta'] ?? '') ?>" data-instrucciones="<?= e($mp['instrucciones'] ?? '') ?>" data-icono="<?= e($mp['icono'] ?? 'bi-credit-card') ?>"><?= e($mp['nombre']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <div class="metodo-info-anticipo d-none mt-2">
                                                                <div class="border rounded p-2 bg-light small">
                                                                    <b class="d-block m-info-n"></b>
                                                                    <span class="text-muted fw-semibold d-block m-info-d"></span>
                                                                    <span class="text-muted d-block m-info-i"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold">Comprobante de pago (obligatorio) *</label>
                                                            <input type="file" name="comprobante" class="form-control" accept="image/*,.pdf" required>
                                                            <small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Tú anticipo no será aprobado hasta que subas el comprobante.</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-fv"><i class="bi bi-send me-1"></i>Enviar pago</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- No hay proyecto: botón para iniciar -->
                                <div class="mt-3">
                                    <button class="btn btn-fv btn-sm" data-bs-toggle="modal" data-bs-target="#modalIniciar<?= (int)$s['id'] ?>"><i class="bi bi-rocket-takeoff me-1"></i>Iniciar Proyecto</button>
                                </div>

                                <!-- Modal para iniciar proyecto -->
                                <div class="modal fade" id="modalIniciar<?= (int)$s['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="POST" class="js-ajax" action="solicitudes.php">
                                                <?= campo_csrf() ?>
                                                <input type="hidden" name="accion" value="iniciar_proyecto">
                                                <input type="hidden" name="solicitud_id" value="<?= (int)$s['id'] ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="bi bi-rocket-takeoff me-2 text-primary"></i>Iniciar Proyecto</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
                                                        <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 mt-1"></i>
                                                        <div>
                                                            <b>AVISO:</b> El proyecto NO se iniciará sin un anticipo mínimo de <b>$2,500 MXN</b>. Este anticipo se descontará del total de la cotización.
                                                        </div>
                                                    </div>

                                                    <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-text me-1 text-primary"></i>Documentación del proyecto</h6>
                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold">Descripción del proyecto *</label>
                                                            <textarea name="descripcion_proyecto" class="form-control" rows="3" required placeholder="Describe brevemente tu proyecto..."></textarea>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Requisitos</label>
                                                            <textarea name="requisitos" class="form-control" rows="3" placeholder="Qué necesita tu proyecto (tecnologías, plataformas, etc.)"></textarea>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Objetivos</label>
                                                            <textarea name="objetivos" class="form-control" rows="3" placeholder="Qué esperas lograr con este proyecto..."></textarea>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Alcance</label>
                                                            <textarea name="alcance" class="form-control" rows="3" placeholder="Qué incluye y qué no incluye el proyecto..."></textarea>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label small fw-semibold">Cronograma deseado</label>
                                                            <textarea name="cronograma" class="form-control" rows="3" placeholder="Plazos y fechas importantes..."></textarea>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small fw-semibold">Entregables esperados</label>
                                                            <textarea name="entregables" class="form-control" rows="3" placeholder="Lista de entregables (archivos, acceso, etc.)"></textarea>
                                                        </div>
                                                    </div>

                                                    <hr class="my-4">

                                                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                                                        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
                                                        <div>
                                                            <b>Anticipo mínimo:</b> $2,500 MXN. Este monto se descuenta del total de la cotización. Después del anticipo, podrás completar el pago restante desde la sección "Mis Pagos".
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-fv"><i class="bi bi-check-lg me-1"></i>Enviar documentación</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Mostrar los datos de pago al elegir método en el modal de anticipo
document.querySelectorAll('.metodo-pago-anticipo')?.forEach(function (sel) {
    sel.addEventListener('change', function () {
        var info = this.closest('.col-12').querySelector('.metodo-info-anticipo');
        if (!info) return;
        var opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) { info.classList.add('d-none'); return; }
        info.querySelector('.m-info-n').textContent = opt.dataset.nombre || '';
        info.querySelector('.m-info-d').textContent = opt.dataset.detalles || '';
        info.querySelector('.m-info-i').textContent = opt.dataset.instrucciones || '';
        info.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>
