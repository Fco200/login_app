<?php
/* ============================================================
   FV DIGITAL - Migración: precio fijo en servicios
   Agrega la columna `precio_fijo` a la tabla servicios y marca
   el servicio de mantenimiento con precio fijo (no cotizado).
   Ejecutar una vez desde el navegador.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

/* 1. Agregar la columna precio_fijo si no existe */
$existe = $pdo->query("SHOW COLUMNS FROM servicios LIKE 'precio_fijo'")->fetch();
if (!$existe) {
    try {
        $pdo->exec("ALTER TABLE servicios ADD COLUMN precio_fijo TINYINT(1) DEFAULT 0 AFTER precio_desde");
    } catch (PDOException $e) {
        $errores[] = $e->getMessage();
    }
}

/* 2. Marcar los servicios de mantenimiento / soporte con precio fijo */
try {
    $pdo->exec("UPDATE servicios SET precio_fijo = 1 WHERE slug LIKE 'mantenimiento%'");
} catch (PDOException $e) {
    $errores[] = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 560px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Precio fijo en servicios</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted">
            <li>Columna <code>precio_fijo</code> disponible en la tabla servicios.</li>
            <li>Servicios de mantenimiento marcados con precio fijo.</li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/servicios.php" class="btn btn-primary">Ir a servicios</a>
        <a href="solicitud.php" class="btn btn-outline-primary">Probar formulario</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>
