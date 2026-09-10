<?php
/* ============================================================
   FV DIGITAL - Migración del Portal de Clientes
   Agrega las tablas nuevas (mensajería, notificaciones,
   historial de solicitudes y puntajes de juegos).
   Es seguro ejecutarlo varias veces (usa CREATE IF NOT EXISTS).
   Ejecutar UNA vez desde el navegador y después ELIMINAR por seguridad.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

$sql = [
"CREATE TABLE IF NOT EXISTS mensajes_portal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  remitente ENUM('cliente','negocio') NOT NULL DEFAULT 'cliente',
  mensaje TEXT NOT NULL,
  leido TINYINT(1) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS notificaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo VARCHAR(30) DEFAULT 'info',
  titulo VARCHAR(180) NOT NULL,
  mensaje TEXT,
  enlace VARCHAR(255) DEFAULT NULL,
  leida TINYINT(1) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS solicitud_historial (
  id INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id INT NOT NULL,
  estado VARCHAR(30) NOT NULL,
  nota VARCHAR(255) DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY solicitud_id (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS juego_puntajes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  juego VARCHAR(40) NOT NULL,
  puntaje INT NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($sql as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        $errores[] = $e->getMessage();
    }
}

/* ---------- Notificación de bienvenida para clientes existentes ---------- */
try {
    $stmt = $pdo->query("SELECT id FROM usuarios WHERE rol = 'cliente'
                         AND id NOT IN (SELECT usuario_id FROM notificaciones WHERE tipo = 'bienvenida_portal')");
    $insert = $pdo->prepare('INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, enlace)
                             VALUES (?, ?, ?, ?, ?)');
    foreach ($stmt as $u) {
        $insert->execute([
            (int)$u['id'],
            'bienvenida_portal',
            '¡Tu portal de clientes está listo!',
            'Ahora puedes chatear con nosotros, dar seguimiento a tus solicitudes y jugar mientras esperas.',
            'portal/index.php',
        ]);
    }
} catch (PDOException $e) {
    $errores[] = 'Notificaciones: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración Portal - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; }
        .card { max-width: 560px; margin: auto; border-radius: 14px; }
    </style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración del Portal de Clientes</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">Las tablas del portal se crearon correctamente.</div>
        <ul class="small text-muted">
            <li><b>mensajes_portal</b> — chat interno entre clientes y negocio.</li>
            <li><b>notificaciones</b> — avisos personalizados para cada cliente.</li>
            <li><b>solicitud_historial</b> — línea de tiempo del estado de cada solicitud.</li>
            <li><b>juego_puntajes</b> — mejores puntajes de los mini-juegos.</li>
            <li>Notificaciones de bienvenida generadas para los clientes existentes.</li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="portal/index.php" class="btn btn-primary">Ir al portal de clientes</a>
        <a href="admin/index.php" class="btn btn-outline-primary">Ir al panel admin</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo (<code>migracion_portal.php</code>) del servidor.</p>
</div>
</body>
</html>