<?php
$titulo = 'Mensajes del portal';
$subtitulo = 'Chat interno entre el negocio y los clientes registrados';
$seccionAdmin = 'mensajes_portal.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Responder ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $texto = trim($_POST['mensaje'] ?? '');
    $usuarioId = (int)($_POST['usuario_id'] ?? 0);

    if ($texto !== '' && $usuarioId > 0) {
        $pdo->prepare('INSERT INTO mensajes_portal (usuario_id, remitente, mensaje) VALUES (?, ?, ?)')
            ->execute([$usuarioId, 'negocio', mb_substr($texto, 0, 2000)]);
        notificar($usuarioId, 'mensaje', 'Tienes un mensaje nuevo',
            mb_strimwidth($texto, 0, 90, '…'), url_sitio('portal/mensajes.php'));
        flash('Respuesta enviada al cliente.');
    } else {
        flash('Escribe un mensaje válido.', 'danger');
    }
    header('Location: mensajes_portal.php?usuario_id=' . $usuarioId);
    exit;
}

/* ---------- Marcar conversación como atendida (leída) ---------- */
$selUsuario = (int)($_GET['usuario_id'] ?? 0);
if ($selUsuario > 0) {
    $pdo->prepare("UPDATE mensajes_portal SET leido = 1 WHERE usuario_id = ? AND remitente = 'cliente'")
        ->execute([$selUsuario]);
}

$stmtC = $pdo->query("SELECT m.usuario_id, u.nombre, u.email,
                     MAX(m.creado_en) AS ultimo,
                     SUM(CASE WHEN m.remitente = 'cliente' AND m.leido = 0 THEN 1 ELSE 0 END) AS pendientes,
                     SUBSTRING((SELECT mensaje FROM mensajes_portal p WHERE p.usuario_id = m.usuario_id ORDER BY p.id DESC LIMIT 1), 1, 60) AS ultimo_mensaje
                     FROM mensajes_portal m
                     JOIN usuarios u ON u.id = m.usuario_id
                     GROUP BY m.usuario_id, u.nombre, u.email
                     ORDER BY pendientes DESC, ultimo DESC");
$conversaciones = $stmtC->fetchAll();

/* Mensajes de la conversación seleccionada */
$conversacion = [];
if ($selUsuario > 0) {
    $stmt = $pdo->prepare('SELECT m.*, u.nombre AS cliente FROM mensajes_portal m JOIN usuarios u ON u.id = m.usuario_id WHERE m.usuario_id = ? ORDER BY m.creado_en ASC');
    $stmt->execute([$selUsuario]);
    $conversacion = $stmt->fetchAll();
    $stmtU = $pdo->prepare('SELECT nombre, email FROM usuarios WHERE id = ?');
    $stmtU->execute([$selUsuario]);
    $datosCliente = $stmtU->fetch();
}
?>

<div class="row g-3">
    <!-- Lista de conversaciones -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><b>Conversaciones (<?= count($conversaciones) ?>)</b></div>
            <div class="card-body p-0">
                <?php if (!$conversaciones): ?>
                    <p class="text-center text-muted small py-4 mb-0">Aún no hay mensajes del portal.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($conversaciones as $c): $pend = (int)$c['pendientes']; ?>
                            <a href="mensajes_portal.php?usuario_id=<?= (int)$c['usuario_id'] ?>"
                               class="list-group-item list-group-item-action <?= $selUsuario === (int)$c['usuario_id'] ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <b class="small"><?= e($c['nombre']) ?></b>
                                    <?php if ($pend > 0): ?><span class="badge text-bg-danger"><?= $pend ?></span><?php endif; ?>
                                </div>
                                <small class="d-block text-muted"><?= e($c['email']) ?></small>
                                <small class="d-block text-muted"><?= e(mb_strimwidth($c['ultimo_mensaje'] ?: '', 0, 45, '…')) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Conversación -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-chat-dots me-1 text-primary"></i>
                    <?= $selUsuario > 0 && !empty($datosCliente) ? e($datosCliente['nombre']) . ' — ' . e($datosCliente['email']) : 'Selecciona una conversación' ?>
                </b>
                <?php if ($selUsuario > 0 && $conversacion): ?>
                    <a href="mailto:<?= e($datosCliente['email']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope me-1"></i>Correo</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($selUsuario > 0 && $conversacion): ?>
                    <div class="chat-admin-caja mb-3">
                        <?php foreach ($conversacion as $m): ?>
                            <div class="burbuja <?= $m['remitente'] === 'negocio' ? 'mia' : 'suya' ?>">
                                <?= e($m['mensaje']) ?>
                                <small class="d-block text-muted mt-1"><?= e(date('d/m/Y H:i', strtotime($m['creado_en']))) ?> · <?= $m['remitente'] === 'negocio' ? 'Tú (FV Digital)' : 'Cliente' ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="POST" action="mensajes_portal.php" class="d-flex gap-2">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="usuario_id" value="<?= $selUsuario ?>">
                        <textarea name="mensaje" class="form-control" rows="2" maxlength="2000" required placeholder="Escribe tu respuesta…"></textarea>
                        <button class="btn btn-fv flex-shrink-0"><i class="bi bi-send me-1"></i>Responder</button>
                    </form>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
                        <p class="mb-0">Elige una conversación del lado izquierdo para responder al cliente.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>