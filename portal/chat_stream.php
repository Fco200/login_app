<?php
/* Chat del portal en tiempo real (Server-Sent Events).
   El navegador se conecta una sola vez y el servidor avisa al instante
   cada vez que hay un mensaje nuevo: no hay que estar recargando nada. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (!esta_logueado()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada.']);
    exit;
}

$usuarioId = (int)$_SESSION['usuario_id'];
$csrf = csrf_token();

/* Modo respaldo: ?json=1 devuelve un listado único (para navegadores sin
   EventSource, se consulta con polling desde portal.js). */
if (isset($_GET['json'])) {
    $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'negocio'")
        ->execute([$usuarioId]);
    $stmt = $pdo->prepare('SELECT m.*, u.nombre AS tu_nombre FROM mensajes_portal m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.usuario_id = ? ORDER BY m.creado_en ASC');
    $stmt->execute([$usuarioId]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'mensajes' => $stmt->fetchAll(), 'csrf' => $csrf], JSON_UNESCAPED_UNICODE);
    exit;
}

sse_iniciar();
session_write_close(); // libero la sesión: el resto de peticiones no se bloquean

$stmt = $pdo->prepare('SELECT m.*, u.nombre AS tu_nombre FROM mensajes_portal m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.usuario_id = ? ORDER BY m.creado_en ASC');
$marcar = $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'negocio'");

$ultimoId = 0;
$inicio = time();
$ultimoPing = $inicio;
$ultimoKeep = $inicio;

while (true) {
    if (connection_aborted()) {
        break;
    }
    $ahora = time();
    /* Cierro el stream a los 4 minutos; el navegador lo reabre solo y sin
       que se note (EventSource se reconecta automáticamente). */
    if ($ahora - $inicio >= 240) {
        break;
    }

    try {
        $stmt->execute([$usuarioId]);
        $mensajes = $stmt->fetchAll();
    } catch (Throwable $e) {
        break;
    }

    $maxId = 0;
    foreach ($mensajes as $m) {
        $maxId = max($maxId, (int)$m['id']);
    }

    if ($maxId !== $ultimoId) {
        try { $marcar->execute([$usuarioId]); } catch (Throwable $e) {}
        sse_enviar('chat', ['ok' => true, 'mensajes' => $mensajes, 'csrf' => $csrf]);
        $ultimoId = $maxId;
    }

    if ($ahora - $ultimoKeep >= 60) {
        sesion_tocar();
        $ultimoKeep = $ahora;
    }
    if ($ahora - $ultimoPing >= 15) {
        sse_ping();
        $ultimoPing = $ahora;
    }

    sleep(1);
}

sse_ping();
exit;
