<?php
require_once __DIR__ . '/../funciones.php';
requiere_sesion();

$seccionPortal = 'mensajes';
$titulo = 'Mensajes con FV Digital';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? ''];

/* ---------- Solicitud en contexto (chat?solicitud=ID) ---------- */
$solicitudCtx = null;
if (!es_ajax()) {
    $solId = (int)($_GET['solicitud'] ?? 0);
    if ($solId > 0) {
        $sc = $pdo->prepare('SELECT * FROM solicitudes WHERE id = ? AND (usuario_id = ? OR LOWER(email) = LOWER(?))');
        $sc->execute([$solId, (int)$usuario['id'], $usuario['email']]);
        $solicitudCtx = $sc->fetch() ?: null;
    }
}

/* ---------- Envío de mensaje ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf() || !empty($_POST['empresa'])) {
        responder(['ok' => false, 'mensaje' => 'La sesión expiró, intenta de nuevo.', 'tipo' => 'danger']);
    }

    $texto = trim($_POST['mensaje'] ?? '');
    if ($texto === '') {
        responder(['ok' => false, 'mensaje' => 'Escribe un mensaje antes de enviar.', 'tipo' => 'danger']);
    }

    $pdo->prepare('INSERT INTO mensajes_portal (usuario_id, remitente, mensaje) VALUES (?, ?, ?)')
        ->execute([(int)$usuario['id'], 'cliente', mb_substr($texto, 0, 2000)]);

    notificar_admins('mensaje', 'Nuevo mensaje de cliente', ($usuario['nombre'] ?? 'Cliente') . ' envió un mensaje al chat.', url_sitio('admin/mensajes_portal.php?usuario_id=' . (int)$usuario['id']));

    responder([
        'ok'      => true,
        'titulo'  => '¡Mensaje enviado!',
        'mensaje' => 'Tu mensaje llegó al equipo de FV Digital. Te responderemos lo antes posible.',
        'destino' => null,
    ]);
}

/* ---------- Consulta AJAX (lista de mensajes) ---------- */
if (es_ajax()) {
    // Marcar como leídos los mensajes del negocio (ya los está viendo el cliente)
    $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'negocio'")
        ->execute([(int)$usuario['id']]);

    $stmt = $pdo->prepare('SELECT m.*, u.nombre AS tu_nombre FROM mensajes_portal m LEFT JOIN usuarios u ON u.id = m.usuario_id WHERE m.usuario_id = ? ORDER BY m.creado_en ASC');
    $stmt->execute([(int)$usuario['id']]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'mensajes' => $stmt->fetchAll(), 'csrf' => csrf_token()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Marcamos leídos al cargar la página normalmente */
$pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'negocio'")
    ->execute([(int)$usuario['id']]);

require_once __DIR__ . '/includes/cabecera.php';
?>

<div class="mb-4">
    <h4 class="mb-1">Chat con FV Digital</h4>
    <p class="text-muted mb-0">Escríbenos dudas, avances o detalles de tus solicitudes. Te responderemos en horario <b>Lun a Vie 9:00 - 18:00</b>.</p>
</div>

<div class="card portal-card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <div class="avatar-circulo" style="width:38px;height:38px;font-size:.95rem;"><?= e(mb_strtoupper(mb_substr(SITE_NOMBRE, 0, 1))) ?></div>
        <div>
            <b class="d-block small"><?= e(SITE_NOMBRE) ?> — Atención a clientes</b>
            <small class="text-success"><i class="bi bi-circle-fill me-1" style="font-size:.6rem;"></i>En línea</small>
        </div>
    </div>

    <?php if ($solicitudCtx): ?>
        <div class="alert alert-fv-light small py-2 px-3 rounded-0 mb-0 d-flex justify-content-between align-items-center gap-2 border-bottom">
            <span><i class="bi bi-inbox me-1"></i>Estás preguntando sobre tu solicitud de <b><?= e($solicitudCtx['tipo_servicio']) ?></b> del <?= e(date('d/m/Y', strtotime($solicitudCtx['creado_en']))) ?>.</span>
            <a href="chat" class="btn btn-sm btn-outline-fv flex-shrink-0"><i class="bi bi-x-lg"></i></a>
        </div>
    <?php endif; ?>

    <div class="chat-caja" id="chatCaja">
        <div class="text-center text-muted py-4" id="chatCargando">
            <div class="spinner-border spinner-border-sm me-1"></div>Cargando conversación…
        </div>
    </div>

    <div class="card-footer bg-white">
        <form method="POST" action="mensajes.php" class="js-ajax js-chat d-flex gap-2" id="formChat">
            <?= campo_csrf() ?>
            <input type="text" name="empresa" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="text" name="mensaje" id="mensajeInput" class="form-control" maxlength="2000"
                   placeholder="Escribe tu mensaje…" autocomplete="off" required aria-label="Mensaje"
                   value="<?= e($solicitudCtx ? 'Hola, quiero dar seguimiento a mi solicitud de ' . $solicitudCtx['tipo_servicio'] . ' del ' . date('d/m/Y', strtotime($solicitudCtx['creado_en'])) . '. ¿Tienen novedades?' : '') ?>">
            <button type="submit" class="btn btn-fv flex-shrink-0" data-cargando="Enviando…">
                <i class="bi bi-send me-1"></i>Enviar
            </button>
        </form>
        <small class="text-muted d-block mt-2">
            <i class="bi bi-shield-lock me-1"></i>Tu conversación es privada y solo la ve el equipo de FV Digital.
        </small>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>