<?php
$titulo = 'Mensajes de contacto';
$subtitulo = 'Mensajes enviados desde la página de contacto';
$seccionAdmin = 'mensajes.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'leido' && $id > 0) {
        $pdo->prepare('UPDATE mensajes_contacto SET leido = 1 WHERE id = ?')->execute([$id]);
        flash('Marcado como leído.');
        header('Location: mensajes.php');
        exit;
    }
    if ($accion === 'todos') {
        $pdo->exec('UPDATE mensajes_contacto SET leido = 1 WHERE leido = 0');
        flash('Todos los mensajes marcados como leídos.');
        header('Location: mensajes.php');
        exit;
    }
    if ($accion === 'eliminar' && $id > 0) {
        $pdo->prepare('DELETE FROM mensajes_contacto WHERE id = ?')->execute([$id]);
        flash('Mensaje eliminado.', 'warning');
        header('Location: mensajes.php');
        exit;
    }
}

$mensajes = $pdo->query('SELECT * FROM mensajes_contacto ORDER BY leido ASC, creado_en DESC')->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($mensajes) ?></b> mensajes</p>
    <?php if (count(array_filter($mensajes, fn($msg) => !$msg['leido'])) > 0): ?>
        <form method="POST" action="mensajes.php" onsubmit="return confirm('¿Marcar TODOS los mensajes como leídos?')">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="todos">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-all me-1"></i>Marcar todos leídos</button>
        </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Estado</th><th>Remitente</th><th>Asunto</th><th>Mensaje</th><th>Fecha</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$mensajes): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay mensajes.</td></tr>
                <?php else: foreach ($mensajes as $m): ?>
                    <tr <?= $m['leido'] ? 'class="text-muted"' : 'style="font-weight:600;"' ?>>
                        <td class="ps-3"><?= $m['leido'] ? '<span class="badge badge-estado text-bg-success">Leído</span>' : '<span class="badge badge-estado text-bg-danger">Nuevo</span>' ?></td>
                        <td>
                            <b><?= e($m['nombre']) ?></b><br>
                            <small><?= e($m['email']) ?><?= $m['telefono'] ? ' · ' . e($m['telefono']) : '' ?></small>
                        </td>
                        <td class="small"><?= e($m['asunto'] ?: 'Sin asunto') ?></td>
                        <td class="small" style="max-width:320px;"><?= e(mb_strimwidth($m['mensaje'] ?? '', 0, 120, '…')) ?></td>
                        <td class="small"><?= e(date('d/m/Y H:i', strtotime($m['creado_en']))) ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#msg<?= (int)$m['id'] ?>"><i class="bi bi-eye"></i></button>
                            <a href="mailto:<?= e($m['email']) ?>?subject=Re: <?= e($m['asunto'] ?: 'Contacto') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-reply"></i></a>
                            <?php if (!$m['leido']): ?>
                                <form method="POST" class="d-inline">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="leido">
                                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                    <button class="btn btn-sm btn-outline-success" title="Marcar leído"><i class="bi bi-envelope-open"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($mensajes as $m): ?>
<div class="modal fade" id="msg<?= (int)$m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?= e($m['asunto'] ?: 'Mensaje sin asunto') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    De: <b><?= e($m['nombre']) ?></b> (<?= e($m['email']) ?><?= $m['telefono'] ? ' · ' . e($m['telefono']) : '' ?>)<br>
                    Fecha: <?= e(date('d/m/Y H:i', strtotime($m['creado_en']))) ?>
                </p>
                <p style="white-space:pre-line;"><?= e($m['mensaje']) ?></p>
            </div>
            <div class="modal-footer">
                <form method="POST" onsubmit="return confirm('¿Eliminar este mensaje?')" class="me-auto">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
                <?php if (!$m['leido']): ?>
                    <form method="POST">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="leido">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button class="btn btn-sm btn-outline-success">Marcar como leído</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>