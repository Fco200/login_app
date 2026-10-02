<?php
$titulo = 'Mensajes del portal';
$subtitulo = 'Chat interno entre el negocio y los clientes registrados';
$seccionAdmin = 'mensajes_portal.php';

require_once __DIR__ . '/includes/cabecera.php';

/* Mensajes predeterminados para responder rÃ¡pido a los clientes */
$MENSAJES_RAPIDOS = [
    ['text' => 'Â¡Hola! Te saluda el equipo de FV Digital. Â¿En quÃ© podemos ayudarte?', 'icono' => 'bi-emoji-smile', 'label' => 'Saludo inicial'],
    ['text' => 'Gracias por tu mensaje. Estamos revisando tu solicitud y te responderemos a la brevedad posible.', 'icono' => 'bi-hourglass-split', 'label' => 'Solicitud en revisiÃ³n'],
    ['text' => 'Tu solicitud ya fue registrada correctamente. Puedes darle seguimiento desde la secciÃ³n "Mis solicitudes" en tu portal.', 'icono' => 'bi-check2-circle', 'label' => 'Solicitud registrada'],
    ['text' => 'Recibimos tu comprobante de pago. Lo estamos verificando; en breve te confirmamos la aprobaciÃ³n.', 'icono' => 'bi-credit-card', 'label' => 'Comprobante recibido'],
    ['text' => 'Tu pago fue aprobado. Â¡Gracias por tu confianza! El proyecto continÃºa su proceso normalmente.', 'icono' => 'bi-cash-coin', 'label' => 'Pago aprobado'],
    ['text' => 'Nos falta informaciÃ³n para avanzar con tu proyecto. Por favor comparte mÃ¡s detalles (alcance, fechas, requerimientos).', 'icono' => 'bi-question-circle', 'label' => 'Falta informaciÃ³n'],
    ['text' => 'Tu proyecto ya estÃ¡ en desarrollo. Te avisaremos de cada avance y de cualquier duda que surja.', 'icono' => 'bi-code-slash', 'label' => 'Proyecto en desarrollo'],
    ['text' => 'Tu proyecto fue completado. Puedes revisar los entregables y, si tienes dudas, con gusto te apoyamos.', 'icono' => 'bi-check-circle-fill', 'label' => 'Proyecto completado'],
    ['text' => 'Para iniciar el proyecto se requiere un anticipo mÃ­nimo de $2,500 MXN, que se descuenta del total de tu cotizaciÃ³n. Â¿Deseas proceder con el pago?', 'icono' => 'bi-cash-stack', 'label' => 'Recordatorio de anticipo'],
    ['text' => 'Estamos preparando tu cotizaciÃ³n completa. En cuanto estÃ© lista te la enviamos por este medio.', 'icono' => 'bi-file-earmark-text', 'label' => 'CotizaciÃ³n en proceso'],
];

/* ---------- Responder ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $texto = trim($_POST['mensaje'] ?? '');
    $usuarioId = trim((string)($_POST['usuario_id'] ?? ''));

    if ($texto !== '' && oid($usuarioId) !== null) {
        $envio = mp_enviar($usuarioId, 'negocio', mb_substr($texto, 0, 2000));
        if ($envio['ok']) {
            notificar($usuarioId, 'mensaje', 'Tienes un mensaje nuevo',
                mb_strimwidth($texto, 0, 90, 'â€¦'), url_sitio('portal/mensajes.php'));
            flash('Respuesta enviada al cliente.');
        } else {
            flash($envio['mensaje'] ?? 'No se pudo enviar el mensaje.', 'danger');
        }
    } else {
        flash('Escribe un mensaje vÃ¡lido.', 'danger');
    }
    header('Location: mensajes_portal.php?usuario_id=' . urlencode($usuarioId));
    exit;
}

/* ---------- Marcar conversaciÃ³n como atendida (leÃ­da) ---------- */
$selUsuario = trim((string)($_GET['usuario_id'] ?? ''));
if (oid($selUsuario) !== null) {
    mp_marcar_leidos_cliente($selUsuario);
} else {
    $selUsuario = '';
}

$conversaciones = mp_conversaciones_panel();

/* Mensajes de la conversaciÃ³n seleccionada */
$conversacion = [];
$datosCliente = null;
if ($selUsuario !== '') {
    $conversacion = mp_conversacion($selUsuario);
    $datosCliente  = usr_por_id($selUsuario);
}
?>

<div class="row g-3">
    <!-- Lista de conversaciones -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><b>Conversaciones (<?= count($conversaciones) ?>)</b></div>
            <div class="card-body p-0">
                <?php if (!$conversaciones): ?>
                    <p class="text-center text-muted small py-4 mb-0">AÃºn no hay mensajes del portal.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($conversaciones as $c): $pend = (int)$c['pendientes']; ?>
                            <a href="mensajes_portal.php?usuario_id=<?= e((string)$c['usuario_id']) ?>"
                               class="list-group-item list-group-item-action <?= $selUsuario !== '' && $selUsuario === (string)$c['usuario_id'] ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <b class="small"><?= e($c['nombre']) ?></b>
                                    <?php if ($pend > 0): ?><span class="badge text-bg-danger"><?= $pend ?></span><?php endif; ?>
                                </div>
                                <small class="d-block text-muted"><?= e($c['email']) ?></small>
                                <small class="d-block text-muted"><?= e(mb_strimwidth($c['ultimo_mensaje'] ?: '', 0, 45, 'â€¦')) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ConversaciÃ³n -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-chat-dots me-1 text-primary"></i>
                    <?= $selUsuario !== '' && !empty($datosCliente) ? e($datosCliente['nombre']) . ' â€” ' . e($datosCliente['email']) : 'Selecciona una conversaciÃ³n' ?>
                </b>
                <?php if ($selUsuario !== '' && $conversacion): ?>
                    <a href="mailto:<?= e($datosCliente['email']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-envelope me-1"></i>Correo</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($selUsuario !== '' && $conversacion): ?>
                    <div class="chat-admin-caja mb-3">
                        <?php foreach ($conversacion as $m): ?>
                            <div class="burbuja <?= $m['remitente'] === 'negocio' ? 'mia' : 'suya' ?>">
                                <?= e($m['mensaje']) ?>
                                <small class="d-block text-muted mt-1"><?= e(fecha_php($m['creado_en'], 'd/m/Y H:i')) ?> Â· <?= $m['remitente'] === 'negocio' ? 'TÃº (FV Digital)' : 'Cliente' ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="small text-muted align-self-center me-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Respuestas rÃ¡pidas:</span>
                        <?php foreach ($MENSAJES_RAPIDOS as $mr): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-mensaje-rapido" title="<?= e($mr['label']) ?>" data-mensaje="<?= e($mr['text']) ?>">
                                <i class="bi <?= e($mr['icono']) ?> me-1"></i><?= e($mr['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" action="mensajes_portal.php" class="d-flex gap-2">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="usuario_id" value="<?= e($selUsuario) ?>">
                        <textarea name="mensaje" id="txtRespuesta" class="form-control" rows="2" maxlength="2000" required placeholder="Escribe tu respuestaâ€¦"></textarea>
                        <button class="btn btn-fv flex-shrink-0"><i class="bi bi-send me-1"></i>Responder</button>
                    </form>
                    <script>
                    document.querySelectorAll('.btn-mensaje-rapido').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var ta = document.getElementById('txtRespuesta');
                            if (ta) { ta.value = this.dataset.mensaje; ta.focus(); }
                        });
                    });
                    </script>
                <?php else: ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
                        <p class="mb-0">Elige una conversaciÃ³n del lado izquierdo para responder al cliente.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/pie.php'; ?>