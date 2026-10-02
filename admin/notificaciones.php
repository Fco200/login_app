<?php
$titulo = 'Notificaciones';
$subtitulo = 'Avisos de los clientes y del sistema';
$seccionAdmin = 'notificaciones.php';

require_once __DIR__ . '/includes/cabecera.php';

$miId = (string)($_SESSION['admin_id'] ?? '');

/* Marcar una notificación como leída */
$marcar = trim((string)($_GET['marcar'] ?? ''));
if ($marcar !== '' && oid($marcar) !== null) {
    notif_marcar_leida($miId, $marcar);
    header('Location: notificaciones.php');
    exit;
}

/* Marcar todas como leídas */
if (isset($_GET['todas'])) {
    notif_marcar_leidas($miId);
    header('Location: notificaciones.php');
    exit;
}

$notificaciones = notif_de_usuario($miId, 80);

$iconos = [
    'exito'            => ['check-circle', 'text-success'],
    'mensaje'          => ['chat-dots', 'text-primary'],
    'soporte'          => ['headset', 'text-primary'],
    'estado'           => ['arrow-repeat', 'text-warning'],
    'pago'             => ['credit-card', 'text-success'],
    'proyecto'         => ['rocket-takeoff', 'text-primary'],
    'seguridad'        => ['shield-lock', 'text-danger'],
    'info'             => ['info-circle', 'text-info'],
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Notificaciones</h4>
        <p class="text-muted mb-0">Avisos de actividad de clientes, pagos y solicitudes.</p>
    </div>
    <?php if (count(array_filter($notificaciones, fn($n) => !$n['leida'])) > 0): ?>
        <a href="notificaciones.php?todas=1" class="btn btn-sm btn-outline-fv"><i class="bi bi-check-all me-1"></i>Marcar todas como leídas</a>
    <?php endif; ?>
</div>

<?php if (!$notificaciones): ?>
    <div class="card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-bell-slash fs-1 text-primary d-block mb-3"></i>
        <h5>No tienes notificaciones</h5>
        <p class="text-muted">Te avisaremos aquí cuando un cliente inicie un proyecto, registre un pago o envíe una solicitud.</p>
    </div>
<?php else: ?>
    <ul class="list-unstyled portal-notif portal-notif-grande mb-0">
        <?php foreach ($notificaciones as $n): [$ico, $color] = $iconos[$n['tipo']] ?? ['info-circle', 'text-info']; ?>
            <li class="card border-0 shadow-sm mb-2 <?= empty($n['leida']) ? 'no-leida' : '' ?>">
                <div class="d-flex align-items-start gap-3 w-100 p-3">
                    <div class="flex-shrink-0"><i class="bi bi-<?= $ico ?> fs-4 <?= $color ?>"></i></div>
                    <div class="flex-grow-1">
                        <b class="d-block"><?= e($n['titulo']) ?></b>
                        <?php if ($n['mensaje']): ?><p class="text-muted small mb-1" style="white-space:pre-line;"><?= e($n['mensaje']) ?></p><?php endif; ?>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e(tiempo_relativo($n['creado_en'])) ?></small>
                    </div>
                    <div class="flex-shrink-0 text-end">
                        <?php if ($n['enlace']): ?>
                            <a href="<?= e($n['enlace']) ?>" class="btn btn-sm btn-outline-fv"><i class="bi bi-box-arrow-up-right"></i></a>
                        <?php endif; ?>
                        <?php if (!$n['leida']): ?>
                            <a href="notificaciones.php?marcar=<?= e(urlencode((string)$n['id'])) ?>" class="btn btn-sm btn-outline-success ms-1" title="Marcar como leída"><i class="bi bi-check-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>