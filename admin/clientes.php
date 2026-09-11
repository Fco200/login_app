<?php
$titulo = 'Clientes';
$subtitulo = 'Directorio de clientes: datos, credenciales de acceso y contacto directo';
$seccionAdmin = 'clientes.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf()) {
        responder(['ok' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.', 'tipo' => 'danger']);
    }
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($accion === 'password' && $id > 0) {
        $password = trim($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            responder(['ok' => false, 'mensaje' => 'La contraseña debe tener mínimo 6 caracteres.', 'tipo' => 'danger']);
        }
        $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ? AND rol = "cliente"')
            ->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
        responder(['ok' => true, 'mensaje' => 'Contraseña restablecida. El cliente ya puede entrar con su nueva clave.', 'tipo' => 'success']);
    }

    if ($accion === 'activo' && $id > 0) {
        $stmt = $pdo->prepare('SELECT activo FROM usuarios WHERE id = ? AND rol = "cliente"');
        $stmt->execute([$id]);
        $activo = (int)$stmt->fetchColumn();
        if ($activo === 0 && $stmt->rowCount() === 0) {
            responder(['ok' => false, 'mensaje' => 'Cliente no encontrado.', 'tipo' => 'danger']);
        }
        $nuevo = $activo ? 0 : 1;
        $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$nuevo, $id]);
        responder([
            'ok'         => true,
            'mensaje'    => $nuevo ? 'Cliente activado. Ya puede entrar al portal.' : 'Cliente desactivado (no podrá entrar al portal).',
            'tipo'       => 'success',
            'accion'     => 'activo_cliente',
            'usuario_id' => $id,
            'activo'     => $nuevo,
        ]);
    }

    if ($accion === 'contactar' && $id > 0) {
        $texto = trim($_POST['mensaje'] ?? '');
        if ($texto === '') {
            responder(['ok' => false, 'mensaje' => 'Escribe un mensaje para el cliente.', 'tipo' => 'warning']);
        }
        $stmtC = $pdo->prepare('SELECT nombre, email FROM usuarios WHERE id = ?');
        $stmtC->execute([$id]);
        $clienteC = $stmtC->fetch();
        $pdo->prepare('INSERT INTO mensajes_portal (usuario_id, remitente, mensaje, leido) VALUES (?, ?, ?, 1)')
            ->execute([$id, 'negocio', mb_substr($texto, 0, 2000)]);
        notificar($id, 'mensaje', 'Tienes un mensaje nuevo de FV Digital',
            mb_strimwidth($texto, 0, 90, '…'), url_sitio('portal/mensajes.php'));
        if ($clienteC && !empty($clienteC['email'])) {
            enviar_correo(
                $clienteC['email'],
                'Nuevo mensaje de ' . SITE_NOMBRE,
                correo_plantilla(
                    'Te escribimos desde tu portal',
                    '<p>Hola <b>' . e($clienteC['nombre'] ?: '') . '</b>,</p>'
                    . '<p>' . nl2br(e(mb_substr($texto, 0, 2000))) . '</p>'
                    . '<p>Puedes responder directamente desde el chat de tu portal:</p>'
                    . '<p><a href="' . e(url_sitio('portal/mensajes.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Abrir el chat</a></p>'
                )
            );
        }
        responder([
            'ok'              => true,
            'mensaje'         => 'Mensaje enviado al cliente por el chat del portal.',
            'tipo'            => 'success',
            'accion'          => 'contacto_cliente',
            'usuario_id'      => $id,
            'mensaje_escrito' => $texto,
        ]);
    }
}

/* ---------- Listado ---------- */
$q = trim($_GET['q'] ?? '');
$params = [];
$where = "u.rol = 'cliente'";
if ($q !== '') {
    $where .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR u.telefono LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like);
}
$stmt = $pdo->prepare("SELECT u.*,
          (SELECT COUNT(*) FROM solicitudes s WHERE s.usuario_id = u.id) AS n_solicitudes,
          (SELECT COUNT(*) FROM pagos p WHERE p.usuario_id = u.id AND p.estado = 'aprobado') AS n_pagos,
          (SELECT COUNT(*) FROM proyectos_inicio pi WHERE pi.usuario_id = u.id) AS n_proyectos
          FROM usuarios u WHERE $where ORDER BY u.creado_en DESC");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$nActivos = 0;
foreach ($clientes as $c) {
    if ((int)$c['activo'] === 1) $nActivos++;
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Total: <b><?= count($clientes) ?></b> clientes · <?= $nActivos ?> activos</p>
    <form method="GET" action="clientes.php" class="d-flex gap-2">
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" style="width:240px;" placeholder="Buscar por nombre, correo o teléfono…">
        <button class="btn btn-sm btn-fv"><i class="bi bi-search me-1"></i>Buscar</button>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">Cliente</th>
                    <th>Contacto</th>
                    <th>Solicitudes</th>
                    <th class="text-center">Pagos</th>
                    <th class="text-center">Proyectos</th>
                    <th>Registro</th>
                    <th>Estado</th>
                    <th class="text-end pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$clientes): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay clientes registrados.</td></tr>
                <?php else: foreach ($clientes as $u): ?>
                    <tr id="filaCliente-<?= (int)$u['id'] ?>">
                        <td class="ps-3">
                            <b><?= e($u['nombre']) ?></b>
                            <br><small class="text-muted"><?= e($u['email']) ?></small>
                        </td>
                        <td class="small">
                            <?= e($u['telefono'] ?: '—') ?>
                            <?php if ($u['telefono']): ?>
                                <br><a class="small" style="color:#128c4b;" href="https://wa.me/52<?= e(preg_replace('/\D/', '', $u['telefono'])) ?>" target="_blank"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge text-bg-light border"><?= (int)$u['n_solicitudes'] ?></span></td>
                        <td class="text-center"><span class="badge text-bg-success"><?= (int)$u['n_pagos'] ?></span></td>
                        <td class="text-center"><span class="badge text-bg-primary"><?= (int)$u['n_proyectos'] ?></span></td>
                        <td class="small text-muted"><?= e(date('d/m/Y', strtotime($u['creado_en']))) ?></td>
                        <td>
                            <span class="badge <?= (int)$u['activo'] === 1 ? 'badge-estado text-bg-success' : 'badge-estado text-bg-secondary' ?>" data-activo="<?= (int)$u['id'] ?>">
                                <?= (int)$u['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#verCliente<?= (int)$u['id'] ?>" title="Ver ficha y contactar"><i class="bi bi-eye"></i></button>
                            <a href="mailto:<?= e($u['email']) ?>" class="btn btn-sm btn-outline-secondary" title="Enviar correo"><i class="bi bi-envelope"></i></a>
                            <a href="mensajes_portal.php?usuario_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-fv" title="Abrir chat del portal"><i class="bi bi-chat-dots"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($clientes as $u):
    $stmtS = $pdo->prepare('SELECT id, tipo_servicio, estado, creado_en FROM solicitudes WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5');
    $stmtS->execute([(int)$u['id']]);
    $solicitudesU = $stmtS->fetchAll();
    $stmtM = $pdo->prepare('SELECT mensaje, remitente, creado_en FROM mensajes_portal WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 5');
    $stmtM->execute([(int)$u['id']]);
    $mensajesU = array_reverse($stmtM->fetchAll());
    $estadoBadge = ['nueva' => 'bg-danger', 'en_proceso' => 'bg-warning text-dark', 'completada' => 'bg-success', 'rechazada' => 'bg-secondary'];
    $act = (int)$u['activo'] === 1;
    $whatsapp = $u['telefono'] ? 'https://wa.me/52' . preg_replace('/\D/', '', $u['telefono']) : null;
?>
<div class="modal fade" id="verCliente<?= (int)$u['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-circle me-1 text-primary"></i><?= e($u['nombre']) ?></h5>
                <span class="badge <?= $act ? 'badge-estado text-bg-success' : 'badge-estado text-bg-secondary' ?>" data-activo="<?= (int)$u['id'] ?>"><?= $act ? 'Activo' : 'Inactivo' ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Datos de la cuenta -->
                    <div class="col-md-5">
                        <div class="card border-0 bg-light">
                            <div class="card-body small">
                                <h6 class="fw-bold mb-3"><i class="bi bi-card-heading me-1"></i>Datos de la cuenta</h6>
                                <div class="mb-2"><span class="text-muted d-block">Correo / usuario del portal</span><b><?= e($u['email']) ?></b></div>
                                <div class="mb-2"><span class="text-muted d-block">Teléfono</span><b><?= e($u['telefono'] ?: '—') ?></b></div>
                                <div class="mb-2"><span class="text-muted d-block">Registrado</span><b><?= e(date('d/m/Y H:i', strtotime($u['creado_en']))) ?></b></div>
                                <div class="mb-3"><span class="text-muted d-block">Acceso al portal</span>
                                    <?= $act ? '<span class="badge text-bg-success">Habilitado</span>' : '<span class="badge text-bg-secondary">Deshabilitado</span>' ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="mensajes_portal.php?usuario_id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-fv"><i class="bi bi-chat-dots me-1"></i>Chat del portal</a>
                                    <?php if ($whatsapp): ?>
                                        <a href="<?= e($whatsapp) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Credenciales -->
                    <div class="col-md-7">
                        <div class="card border-0 bg-light">
                            <div class="card-body small">
                                <h6 class="fw-bold mb-3"><i class="bi bi-key me-1"></i>Credenciales de acceso</h6>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-shield-lock me-1"></i>La contraseña está cifrada y <b>no se muestra por seguridad</b>.
                                    Usa <b>Restablecer contraseña</b> para asignar una nueva clave al cliente.
                                </p>
                                <form method="POST" action="clientes.php" class="js-ajax d-flex gap-2 align-items-center mb-3">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="password">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Nueva contraseña (mín. 6)" minlength="6" required>
                                    <button class="btn btn-sm btn-fv flex-shrink-0" data-cargando="<span class=&quot;spinner-border spinner-border-sm&quot;></span>"><i class="bi bi-key me-1"></i>Restablecer</button>
                                </form>
                                <form method="POST" action="clientes.php" class="js-ajax d-inline">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="activo">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-sm <?= $act ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $act ? '<i class="bi bi-slash-circle me-1"></i>Desactivar cliente' : '<i class="bi bi-check-circle me-1"></i>Activar cliente' ?>
                                    </button>
                                </form>
                                <hr>
                                <h6 class="fw-bold mb-2"><i class="bi bi-credit-card me-1"></i>Actividad</h6>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge text-bg-light border">Solicitudes: <b><?= (int)$u['n_solicitudes'] ?></b></span>
                                    <span class="badge text-bg-light border">Pagos aprobados: <b><?= (int)$u['n_pagos'] ?></b></span>
                                    <span class="badge text-bg-light border">Proyectos: <b><?= (int)$u['n_proyectos'] ?></b></span>
                                </div>
                                <?php if ($solicitudesU): ?>
                                    <span class="text-muted d-block mb-1">Últimas solicitudes:</span>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($solicitudesU as $sol): ?>
                                            <li class="d-flex gap-2 align-items-center py-1 border-bottom">
                                                <span class="small flex-grow-1"><?= e($sol['tipo_servicio'] ?: 'Solicitud general') ?></span>
                                                <span class="badge <?= $estadoBadge[$sol['estado']] ?? 'bg-light text-dark' ?>"><?= e(str_replace('_', ' ', $sol['estado'])) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Contacto directo -->
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body small">
                                <h6 class="fw-bold mb-3"><i class="bi bi-send me-1"></i>Contactar al cliente</h6>
                                <div class="chat-admin-caja mb-3" id="conversacionCliente-<?= (int)$u['id'] ?>" style="max-height:220px;overflow:auto;">
                                    <?php if (!$mensajesU): ?>
                                        <p class="text-center text-muted small py-3 mb-0">Aún no hay mensajes con este cliente.</p>
                                    <?php else: foreach ($mensajesU as $m): ?>
                                        <div class="burbuja <?= $m['remitente'] === 'negocio' ? 'mia' : 'suya' ?>">
                                            <?= e($m['mensaje']) ?>
                                            <small class="d-block text-muted mt-1"><?= e(date('d/m/Y H:i', strtotime($m['creado_en']))) ?> · <?= $m['remitente'] === 'negocio' ? 'Tú (FV Digital)' : 'Cliente' ?></small>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <form method="POST" action="clientes.php" class="js-ajax d-flex gap-2">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="contactar">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <textarea name="mensaje" class="form-control" rows="2" maxlength="2000" required placeholder="Escribe una nota, aviso o mensaje para el cliente…"></textarea>
                                    <button class="btn btn-fv flex-shrink-0"><i class="bi bi-send me-1"></i>Enviar</button>
                                </form>
                                <p class="text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>El mensaje llega al chat del portal y el cliente recibe una notificación.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Actualizaciones en vivo de la ficha del cliente (sin recargar)
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};

    if (d.accion === 'activo_cliente') {
        var id = d.usuario_id;
        var badges = document.querySelectorAll('[data-activo="' + id + '"]');
        var activo = d.activo === 1;
        for (var i = 0; i < badges.length; i++) {
            badges[i].className = 'badge badge-estado ' + (activo ? 'text-bg-success' : 'text-bg-secondary');
            badges[i].textContent = activo ? 'Activo' : 'Inactivo';
        }
        var fila = document.getElementById('filaCliente-' + id);
        if (fila) {
            var filaBadge = fila.querySelector('[data-activo="' + id + '"]');
            filaBadge.className = 'badge badge-estado ' + (activo ? 'text-bg-success' : 'text-bg-secondary');
            filaBadge.textContent = activo ? 'Activo' : 'Inactivo';
        }
    }

    if (d.accion === 'contacto_cliente') {
        var caja = document.getElementById('conversacionCliente-' + d.usuario_id);
        if (caja) {
            var vacio = caja.querySelector('p.text-center');
            if (vacio) vacio.parentNode.removeChild(vacio);
            var now = new Date();
            var fmt = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
            var div = document.createElement('div');
            div.className = 'burbuja mia';
            var texto = document.createElement('span');
            texto.textContent = ((e && e.detail.mensaje_escrito) || '').replace(/&amp;/g, '&');
            var sm = document.createElement('small');
            sm.className = 'd-block text-muted mt-1';
            sm.textContent = 'Ahora · Tú (FV Digital)';
            div.appendChild(texto);
            div.appendChild(sm);
            caja.appendChild(div);
            caja.scrollTop = caja.scrollHeight;
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>