<?php
$titulo = 'Mensajes de contacto';
$subtitulo = 'Mensajes enviados desde la pÃ¡gina de contacto';
$seccionAdmin = 'mensajes.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';
    $id = trim((string)($_POST['id'] ?? ''));

    if ($accion === 'leido' && oid($id) !== null) {
        contacto_marcar_leido($id);
        flash('Marcado como leÃ­do.');
        header('Location: mensajes.php');
        exit;
    }
    if ($accion === 'todos') {
        contacto_marcar_todos_leidos();
        flash('Todos los mensajes marcados como leÃ­dos.');
        header('Location: mensajes.php');
        exit;
    }
    if ($accion === 'eliminar' && oid($id) !== null) {
        contacto_eliminar($id);
        flash('Mensaje eliminado.', 'warning');
        header('Location: mensajes.php');
        exit;
    }
}

$mensajes = contacto_listar();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($mensajes) ?></b> mensajes</p>
    <?php if (count(array_filter($mensajes, fn($msg) => !$msg['leido'])) > 0): ?>
        <form method="POST" action="mensajes.php" onsubmit="return confirm('Â¿Marcar TODOS los mensajes como leÃ­dos?')">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="todos">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-all me-1"></i>Marcar todos leÃ­dos</button>
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
                        <td class="ps-3"><?= $m['leido'] ? '<span class="badge badge-estado text-bg-success">LeÃ­do</span>' : '<span class="badge badge-estado text-bg-danger">Nuevo</span>' ?></td>
                        <td>
                            <b><?= e($m['nombre']) ?></b><br>
                            <small><?= e($m['email']) ?><?= $m['telefono'] ? ' Â· ' . e($m['telefono']) : '' ?></small>
                        </td>
                        <td class="small"><?= e($m['asunto'] ?: 'Sin asunto') ?></td>
                        <td class="small" style="max-width:320px;"><?= e(mb_strimwidth($m['mensaje'] ?? '', 0, 120, 'â€¦')) ?></td>
                        <td class="small"><?= e(fecha_php($m['creado_en'], 'd/m/Y H:i')) ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#msg<?= (string)$m['id'] ?>"><i class="bi bi-eye"></i></button>
                            <a href="mailto:<?= e($m['email']) ?>?subject=Re: <?= e($m['asunto'] ?: 'Contacto') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-reply"></i></a>
                            <?php if (!$m['leido']): ?>
                                <form method="POST" class="d-inline">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="leido">
                                    <input type="hidden" name="id" value="<?= (string)$m['id'] ?>">
                                    <button class="btn btn-sm btn-outline-success" title="Marcar leÃ­do"><i class="bi bi-envelope-open"></i></button>
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
<div class="modal fade" id="msg<?= (string)$m['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?= e($m['asunto'] ?: 'Mensaje sin asunto') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    De: <b><?= e($m['nombre']) ?></b> (<?= e($m['email']) ?><?= $m['telefono'] ? ' Â· ' . e($m['telefono']) : '' ?>)<br>
                    Fecha: <?= e(fecha_php($m['creado_en'], 'd/m/Y H:i')) ?>
                </p>
                <p style="white-space:pre-line;"><?= e($m['mensaje']) ?></p>
            </div>
            <div class="modal-footer">
                <form method="POST" onsubmit="return confirm('Â¿Eliminar este mensaje?')" class="me-auto">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (string)$m['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
                <?php if (!$m['leido']): ?>
                    <form method="POST">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="leido">
                        <input type="hidden" name="id" value="<?= (string)$m['id'] ?>">
                        <button class="btn btn-sm btn-outline-success">Marcar como leÃ­do</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/pie.php'; ?>