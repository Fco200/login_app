<?php
/* Chat del portal (lado administrativo) en tiempo real (Server-Sent Events).
   Mantiene viva la conversación abierta y la lista de conversaciones:
   los mensajes de los clientes aparecen al instante, sin recargar. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

if (!esta_admin()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada.']);
    exit;
}

$usuarioId = (int)($_GET['usuario_id'] ?? 0);

function admin_chat_mensajes(PDO $pdo, int $usuarioId): array {
    if ($usuarioId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT m.id, m.remitente, m.mensaje, m.creado_en FROM mensajes_portal m WHERE m.usuario_id = ? ORDER BY m.creado_en ASC, m.id ASC');
    $stmt->execute([$usuarioId]);
    $mensajes = $stmt->fetchAll();
    foreach ($mensajes as $i => $m) {
        $mensajes[$i]['fecha'] = date('d/m/Y H:i', strtotime((string)$m['creado_en']));
    }
    return $mensajes;
}

function admin_chat_conversaciones(PDO $pdo): array {
    $stmtC = $pdo->query("SELECT m.usuario_id, u.nombre, u.email,
                     MAX(m.creado_en) AS ultimo,
                     SUM(CASE WHEN m.remitente = 'cliente' AND m.leido = 0 THEN 1 ELSE 0 END) AS pendientes,
                     SUBSTRING((SELECT mensaje FROM mensajes_portal p WHERE p.usuario_id = m.usuario_id ORDER BY p.id DESC LIMIT 1), 1, 60) AS ultimo_mensaje
                     FROM mensajes_portal m
                     JOIN usuarios u ON u.id = m.usuario_id
                     GROUP BY m.usuario_id, u.nombre, u.email
                     ORDER BY pendientes DESC, ultimo DESC");
    return $stmtC->fetchAll();
}

/* Modo respaldo: ?json=1 devuelve un único listado (polling sin EventSource). */
if (isset($_GET['json'])) {
    if ($usuarioId > 0) {
        $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'cliente'")
            ->execute([$usuarioId]);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'            => true,
        'mensajes'      => admin_chat_mensajes($pdo, $usuarioId),
        'conversaciones' => admin_chat_conversaciones($pdo),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

sse_iniciar();
session_write_close(); // libero la sesión: el resto de peticiones no se bloquean

$marcar = null;
if ($usuarioId > 0) {
    $marcar = $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'cliente'");
}

$ultimoMensajes = '';
$ultimoConvs = '';
$inicio = time();
$ultimoPing = $inicio;
$ultimoKeep = $inicio;

while (true) {
    if (connection_aborted()) {
        break;
    }
    $ahora = time();
    if ($ahora - $inicio >= 240) {
        break;
    }

    try {
        $mensajes = admin_chat_mensajes($pdo, $usuarioId);
    } catch (Throwable $e) {
        break;
    }

    $firmaMensajes = md5((string)json_encode($mensajes));
    if ($firmaMensajes !== $ultimoMensajes) {
        if ($marcar) {
            try { $marcar->execute([$usuarioId]); } catch (Throwable $e) {}
        }
        sse_enviar('chat', ['ok' => true, 'usuario_id' => $usuarioId, 'mensajes' => $mensajes]);
        $ultimoMensajes = $firmaMensajes;
    }

    try {
        $convs = admin_chat_conversaciones($pdo);
    } catch (Throwable $e) {
        $convs = [];
    }
    $firmaConvs = md5((string)json_encode($convs));
    if ($firmaConvs !== $ultimoConvs) {
        sse_enviar('conversaciones', ['ok' => true, 'conversaciones' => $convs]);
        $ultimoConvs = $firmaConvs;
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
