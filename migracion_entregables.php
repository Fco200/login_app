<?php
/* ============================================================
   FV DIGITAL - Migración: tabla de entregables
   Ejecutar una vez desde el navegador (o `php migracion_entregables.php`).
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

$sql = [
"CREATE TABLE IF NOT EXISTS entregables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proyecto_id INT NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  archivo VARCHAR(255) NOT NULL,
  notas VARCHAR(255) NOT NULL DEFAULT '',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (proyecto_id) REFERENCES proyectos_inicio(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($sql as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        $errores[] = $e->getMessage();
    }
}

/* Carpeta de almacenamiento de entregables (protegida contra descarga directa) */
$dirEntregables = UPLOADS_DIR . '/entregables';
if (!is_dir($dirEntregables)) {
    @mkdir($dirEntregables, 0777, true);
}
$ht = $dirEntregables . '/.htaccess';
if (!file_exists($ht)) {
    @file_put_contents($ht, "Require all denied\nDeny from all\n");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración entregables - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 560px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Entregables</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted">
            <li>Tabla creada: entregables.</li>
            <li>Carpeta protegida: assets/uploads/entregables/.</li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/entregables.php" class="btn btn-primary">Ir a entregables</a>
        <a href="admin/index.php" class="btn btn-outline-primary">Panel</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>