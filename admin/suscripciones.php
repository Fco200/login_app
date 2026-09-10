<?php
$titulo = 'Suscripciones';
$subtitulo = 'Correos suscritos al boletín de FV Digital';
$seccionAdmin = 'suscripciones.php';

require_once __DIR__ . '/includes/cabecera.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'altbaja' && $id > 0) {
        $pdo->prepare('UPDATE suscripciones SET activo = 1 - activo WHERE id = ?')->execute([$id]);
        flash('Suscripción actualizada.');
        header('Location: suscripciones.php');
        exit;
    }
    if ($accion === 'eliminar' && $id > 0) {
        $pdo->prepare('DELETE FROM suscripciones WHERE id = ?')->execute([$id]);
        flash('Suscripción eliminada.', 'warning');
        header('Location: suscripciones.php');
        exit;
    }
}

$suscripciones = $pdo->query('SELECT * FROM suscripciones ORDER BY creado_en DESC')->fetchAll();
$activas = count(array_filter($suscripciones, fn($s) => $s['activo']));
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($suscripciones) ?></b> · Activas: <b><?= $activas ?></b></p>
    <a href="suscripciones.php?exportar=csv" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Exportar CSV</a>
</div>

<?php
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $archivo = fopen('php://output', 'w');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="suscripciones-fv-digital.csv"');
    fwrite($archivo, "\xEF\xBB\xBF"); // BOM para Excel
    fputcsv($archivo, ['Email', 'Activo', 'Fecha']);
    foreach ($suscripciones as $s) {
        fputcsv($archivo, [$s['email'], $s['activo'] ? 'Si' : 'No', $s['creado_en']]);
    }
    fclose($archivo);
    exit;
}
?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Correo electrónico</th><th>Estado</th><th>Fecha</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$suscripciones): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Aún no hay suscripciones.</td></tr>
                <?php else: foreach ($suscripciones as $s): ?>
                    <tr>
                        <td class="ps-3"><b><?= e($s['email']) ?></b></td>
                        <td><?= $s['activo'] ? '<span class="badge badge-estado text-bg-success">Activa</span>' : '<span class="badge badge-estado text-bg-secondary">Inactiva</span>' ?></td>
                        <td class="small text-muted"><?= e(date('d/m/Y', strtotime($s['creado_en']))) ?></td>
                        <td class="text-end pe-3">
                            <form method="POST" class="d-inline">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="altbaja">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="Activar/Desactivar"><i class="bi bi-pause"></i></button>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta suscripción?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 alert alert-info small">
    <i class="bi bi-info-circle me-1"></i> El formulario de suscripción está en la sección "Boletín" del pie de página público (antes del footer).
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>