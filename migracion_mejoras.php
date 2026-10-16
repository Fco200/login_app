<?php
/* ============================================================
   FV DIGITAL - Migración: mejoras de productos y procesos
   - productos.referencia (clave/SKU del producto)
   - proyectos_inicio.folio (folio único del proceso/proyecto)
   Ejecutar una vez desde el navegador con ?confirmar=1.
   Es idempotente: puede ejecutarse varias veces sin daño.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$confirmado = ($_GET['confirmar'] ?? '') === '1';
$errores = [];
$pasos = [];

if ($confirmado) {
    /* ---------- Helpers idempotentes ---------- */
    $mf_col_existe = function (string $tabla, string $col) use ($pdo): bool {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $st->execute([$tabla, $col]);
            return (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    };
    $mf_idx_existe = function (string $tabla, string $idx) use ($pdo): bool {
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS
                                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $st->execute([$tabla, $idx]);
            return (int)$st->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    };

    /* ---------- 1) productos.referencia ---------- */
    try {
        if (!$mf_col_existe('productos', 'referencia')) {
            $pdo->exec('ALTER TABLE productos ADD referencia VARCHAR(60) NULL AFTER categoria');
            $pasos[] = 'productos.referencia agregada';
        } else {
            $pasos[] = 'productos.referencia ya existía';
        }
    } catch (Throwable $e) {
        $errores[] = 'productos.referencia -> ' . $e->getMessage();
    }

    /* ---------- 2) proyectos_inicio.folio ---------- */
    try {
        if (!$mf_col_existe('proyectos_inicio', 'folio')) {
            $pdo->exec('ALTER TABLE proyectos_inicio ADD folio VARCHAR(20) NULL AFTER id');
            $pasos[] = 'proyectos_inicio.folio agregado';
        } else {
            $pasos[] = 'proyectos_inicio.folio ya existía';
        }
        if (!$mf_idx_existe('proyectos_inicio', 'uq_proyectos_folio')) {
            $pdo->exec('ALTER TABLE proyectos_inicio ADD UNIQUE KEY uq_proyectos_folio (folio)');
            $pasos[] = 'índice único de folio agregado';
        }
    } catch (Throwable $e) {
        $errores[] = 'proyectos_inicio.folio -> ' . $e->getMessage();
    }

    /* ---------- 3) Backfill de folios existentes ---------- */
    try {
        $upd = $pdo->prepare('UPDATE proyectos_inicio SET folio = ? WHERE id = ? AND (folio IS NULL OR folio = "")');
        $rows = $pdo->query('SELECT id FROM proyectos_inicio')->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            $folio = 'PRY-' . str_pad((string)(int)$r['id'], 4, '0', STR_PAD_LEFT);
            $upd->execute([$folio, (int)$r['id']]);
            $n++;
        }
        $pasos[] = $n . ' folio(s) asignados';
    } catch (Throwable $e) {
        $errores[] = 'backfill folios -> ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración: mejoras de productos y procesos - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 620px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: mejoras de productos y procesos</h3>
    <?php if (!$confirmado): ?>
        <div class="alert alert-info">
            Este script agrega la columna <b>referencia</b> a los productos y el <b>folio</b> a los procesos.
            Es <b>idempotente</b> y reejecutable.
        </div>
        <a class="btn btn-primary" href="migracion_mejoras.php?confirmar=1"><i class="bi bi-play-fill me-1"></i>Ejecutar la migración</a>
    <?php elseif (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <div class="alert alert-light border small mb-3">
            <b><i class="bi bi-list-check me-1"></i>Pasos aplicados:</b>
            <ul class="mb-0 ps-3">
                <?php foreach ($pasos as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores (el script es reejecutable):</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/productos.php" class="btn btn-primary">Ir a Productos</a>
        <a href="admin/procesos.php" class="btn btn-outline-primary">Ir a Procesos</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>
