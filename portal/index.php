<?php
require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'inicio';
$titulo = 'Panel de cliente';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'email' => ''];

$stmt = $pdo->prepare('SELECT * FROM solicitudes WHERE usuario_id = ? OR email = ? ORDER BY creado_en DESC LIMIT 20');
$stmt->execute([(int)$usuario['id'], $usuario['email']]);
$solicitudes = $stmt->fetchAll();

$stmtMsg = $pdo->prepare('SELECT * FROM mensajes_portal WHERE usuario_id = ? ORDER BY creado_en ASC');
$stmtMsg->execute([(int)$usuario['id']]);
$mensajes = $stmtMsg->fetchAll();

$stmtNot = $pdo->prepare('SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5');
$stmtNot->execute([(int)$usuario['id']]);
$notificaciones = $stmtNot->fetchAll();

$estados = [
    'nueva'       => ['badge text-bg-danger', 'bi-file-earmark-plus', 'Nueva'],
    'en_proceso'  => ['badge text-bg-warning', 'bi-gear', 'En proceso'],
    'completada'  => ['badge text-bg-success', 'bi-check-circle', 'Completada'],
    'rechazada'   => ['badge text-bg-secondary', 'bi-x-circle', 'Rechazada'],
];
$estado = fn($s) => $estados[$s['estado']] ?? ['badge text-bg-light', 'bi-question-circle', ucfirst($s['estado'])];

$enCurso = count(array_filter($solicitudes, fn($s) => in_array($s['estado'], ['nueva', 'en_proceso'], true)));
$noLeidos = count(array_filter($mensajes, fn($m) => (int)$m['leido'] === 0 && $m['remitente'] === 'negocio'));
$noNot = count($notificaciones);
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="mb-1">¡Hola, <?= e($usuario['nombre']) ?>!</h4>
        <p class="text-muted mb-0">Aquí llevas el control de tus cotizaciones, chateas con nosotros y juegas mientras esperas.</p>
    </div>
    <a href="../index.php" class="btn btn-outline-fv btn-sm"><i class="bi bi-globe2 me-1"></i>Ver sitio público</a>
</div>

<!-- Tarjetas de resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="mis-solicitudes" class="text-decoration-none">
            <div class="card portal-card h-100 p-3 text-center border-0 shadow-sm">
                <div class="portal-icon pb-2"><i class="bi bi-inbox text-primary"></i></div>
                <b class="fs-4"><?= count($solicitudes) ?></b>
                <small class="text-muted">Solicitudes</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="mis-solicitudes" class="text-decoration-none">
            <div class="card portal-card h-100 p-3 text-center border-0 shadow-sm">
                <div class="portal-icon pb-2"><i class="bi bi-gear text-warning"></i></div>
                <b class="fs-4"><?= $enCurso ?></b>
                <small class="text-muted">En curso</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="chat" class="text-decoration-none">
            <div class="card portal-card h-100 p-3 text-center border-0 shadow-sm">
                <div class="portal-icon pb-2"><i class="bi bi-chat-dots text-success"></i></div>
                <b class="fs-4"><?= $noLeidos ?></b>
                <small class="text-muted">Mensajes nuevos</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="notificaciones" class="text-decoration-none">
            <div class="card portal-card h-100 p-3 text-center border-0 shadow-sm">
                <div class="portal-icon pb-2"><i class="bi bi-bell text-danger"></i></div>
                <b class="fs-4"><?= $noNot ?></b>
                <small class="text-muted">Notificaciones</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Solicitudes recientes -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm portal-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-clock-history me-1 text-primary"></i>Mis solicitudes recientes</b>
                <a href="mis-solicitudes" class="btn btn-sm btn-outline-fv">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$solicitudes): ?>
                    <div class="text-center p-4">
                        <i class="bi bi-inbox fs-1 text-primary d-block mb-2"></i>
                        <p class="text-muted mb-3">Aún no tienes solicitudes.</p>
                        <a href="nueva-solicitud" class="btn btn-fv btn-sm"><i class="bi bi-send me-1"></i>Enviar mi primera solicitud</a>
                    </div>
                <?php else: ?>
                    <table class="table table-hover tabla-admin mb-0">
                        <thead class="table-light">
                            <tr><th>Servicio</th><th>Fecha</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($solicitudes, 0, 5) as $s): [$bg, $ic, $txt] = $estado($s); ?>
                                <tr>
                                    <td><a class="text-decoration-none" href="mis-solicitudes#sol-<?= (int)$s['id'] ?>"><b><?= e($s['tipo_servicio']) ?></b></a><?= ($s['tipo_solicitud'] ?? '') === 'empresa' ? ' <i class="bi bi-buildings text-primary small"></i>' : '' ?></td>
                                    <td class="small text-muted"><?= e(date('d/m/Y', strtotime($s['creado_en']))) ?></td>
                                    <td><span class="<?= $bg ?>"><i class="bi <?= $ic ?> me-1"></i><?= $txt ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notificaciones -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm portal-card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-bell me-1 text-danger"></i>Notificaciones</b>
                <a href="notificaciones" class="btn btn-sm btn-outline-fv">Ver todas</a>
            </div>
            <div class="card-body p-3">
                <?php if (!$notificaciones): ?>
                    <p class="text-muted small text-center mb-0 py-3">No tienes notificaciones por ahora.</p>
                <?php else: ?>
                    <ul class="list-unstyled portal-notif mb-0">
                        <?php foreach ($notificaciones as $n): ?>
                            <li class="<?= (int)$n['leida'] ? '' : 'no-leida' ?>">
                                <i class="bi bi-<?= match ($n['tipo']) { 'exito' => 'check-circle', 'mensaje' => 'chat-dots', 'soporte' => 'headset', 'estado' => 'arrow-repeat', default => 'info-circle' } ?> me-2"></i>
                                <div>
                                    <b class="d-block small"><?= e($n['titulo']) ?></b>
                                    <?php if ($n['mensaje']): ?><span class="small text-muted"><?= e(mb_strimwidth($n['mensaje'], 0, 70, '…')) ?></span><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Accesos rápidos -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm portal-card h-100">
            <div class="card-header bg-white"><b><i class="bi bi-lightning-charge me-1 text-primary"></i>Accesos rápidos</b></div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="nueva-solicitud" class="btn btn-fv"><i class="bi bi-send me-1"></i>Nueva solicitud / cotización</a>
                    <a href="chat" class="btn btn-outline-fv"><i class="bi bi-chat-dots me-1"></i>Abrir el chat con el negocio</a>
                    <a href="juegos" class="btn btn-outline-fv"><i class="bi bi-controller me-1"></i>Jugar mientras esperas</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cómo funciona -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm portal-card h-100">
            <div class="card-header bg-white"><b><i class="bi bi-arrow-repeat me-1 text-success"></i>¿Cómo funciona la atención?</b></div>
            <div class="card-body">
                <ol class="small text-muted ps-3 mb-0" style="line-height:2;">
                    <li>Envías tu solicitud desde el portal.</li>
                    <li>El equipo de FV Digital la revisa y actualiza su estado.</li>
                    <li>Recibes una notificación y ves el avance en la solicitud.</li>
                    <li>Chatea con nosotros para concretar detalles.</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Chat previo -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm portal-card h-100">
            <div class="card-header bg-white"><b><i class="bi bi-chat-dots me-1 text-success"></i>Conversación reciente</b></div>
            <div class="card-body p-3">
                <?php $ultimos = array_slice(array_reverse($mensajes), 0, 3); ?>
                <?php if (!$ultimos): ?>
                    <p class="text-muted small text-center mb-0 py-3">Sin conversación todavía. Escríbenos desde la pestaña Mensajes.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($ultimos as $m): ?>
                            <div class="burbuja <?= $m['remitente'] === 'cliente' ? 'mia' : 'suya' ?>">
                                <?= e(mb_strimwidth($m['mensaje'], 0, 90, '…')) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="chat" class="btn btn-sm btn-outline-fv w-100 mt-3">Abrir conversación</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>