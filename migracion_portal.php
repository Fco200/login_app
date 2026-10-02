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

/* Índices de las colecciones del portal (sustituye los CREATE TABLE). */
$indices = [
    ['mensajes_portal',     [['usuario_id' => 1, 'creado_en' => 1], 'ix_mensajes_usuario_fecha']],
    ['mensajes_portal',     [['usuario_id' => 1, 'remitente' => 1, 'leido' => 1], 'ix_mensajes_pendientes']],
    ['notificaciones',       [['usuario_id' => 1, 'creado_en' => -1], 'ix_notificaciones_usuario_fecha']],
    ['solicitud_historial', [['solicitud_id' => 1, 'creado_en' => -1], 'ix_solhist_solicitud_fecha']],
    ['juego_puntajes',      [['juego' => 1, 'puntaje' => -1], 'ix_juego_tabla']],
    ['juego_puntajes',      [['usuario_id' => 1, 'juego' => 1, 'puntaje' => -1], 'ix_juego_usuario']],
];

foreach ($indices as [$coleccion, $claves, $nombre]) {
    try {
        col($coleccion)->createIndex($claves, ['name' => $nombre]);
    } catch (\Throwable $e) {
        $errores[] = $coleccion . '/' . $nombre . ': ' . $e->getMessage();
    }
}

/* ---------- Notificación de bienvenida para clientes existentes ---------- */
try {
    foreach (usr_listar('cliente') as $u) {
        $yaTiene = col_contar('notificaciones', [
            'usuario_id' => oid($u['id']),
            'tipo'      => 'bienvenida_portal',
        ]);
        if ($yaTiene > 0) {
            continue;
        }
        col_agregar('notificaciones', [
            'usuario_id' => oid($u['id']),
            'tipo'       => 'bienvenida_portal',
            'titulo'     => '¡Tu portal de clientes está listo!',
            'mensaje'    => 'Ahora puedes chatear con nosotros, dar seguimiento a tus solicitudes y jugar mientras esperas.',
            'enlace'     => 'portal/index.php',
            'leida'      => false,
            'creado_en'  => ahora_utc(),
        ]);
    }
} catch (\Throwable $e) {
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
        <div class="alert alert-success">Las colecciones del portal quedaron listas.</div>
        <ul class="small text-muted">
            <li><b>mensajes_portal</b> — chat interno entre clientes y negocio.</li>
            <li><b>notificaciones</b> — avisos personalizados para cada cliente.</li>
            <li><b>solicitud_historial</b> — línea de tiempo del estado de cada solicitud.</li>
            <li><b>juego_puntajes</b> — mejores puntajes de los mini-juegos.</li>
            <li>Índices creados y notificaciones de bienvenida generadas para los clientes existentes.</li>
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