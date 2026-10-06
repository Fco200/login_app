<?php
$titulo = 'Solicitudes';
$subtitulo = 'Presupuestos y solicitudes enviadas desde el sitio público';
$seccionAdmin = 'solicitudes.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if (($accion === 'estado' || $accion === 'eliminar') && $id > 0) {
        if ($accion === 'estado') {
            $estado = in_array($_POST['estado'] ?? '', ['nueva', 'en_proceso', 'completada', 'rechazada'], true)
                ? $_POST['estado'] : 'nueva';
            $nota = trim((string)($_POST['nota'] ?? ''));
            $estadoAnterior = $pdo->prepare('SELECT estado, usuario_id, tipo_servicio, nombre, email FROM solicitudes WHERE id = ?');
            $estadoAnterior->execute([$id]);
            $datosSol = $estadoAnterior->fetch();

            $pdo->prepare('UPDATE solicitudes SET estado = ? WHERE id = ?')->execute([$estado, $id]);

            registrar_historial($id, $estado, $nota !== '' ? $nota : 'Estado actualizado por el administrador.');

            if ($datosSol && !empty($datosSol['usuario_id'])) {
                $notas = [
                    'en_proceso'  => 'Estamos trabajando en tu solicitud. Pronto tendrás novedades.',
                    'completada'  => '¡Tu solicitud fue completada! Revisa los detalles en tu portal.',
                    'rechazada'   => 'Tu solicitud no pudo ser procesada. Chatea con nosotros para más detalles.',
                    'nueva'       => 'Recibimos tu solicitud y estamos por revisarla.',
                ];
                notificar(
                    (int)$datosSol['usuario_id'],
                    'estado',
                    "Tu solicitud de {$datosSol['tipo_servicio']} ahora está: " . ucfirst(str_replace('_', ' ', $estado)),
                    $nota !== '' ? $nota : ($notas[$estado] ?? ''),
                    url_sitio('portal/solicitudes.php')
                );

                /* Aviso por correo al cliente */
                if (!empty($datosSol['email'])) {
                    $textoEstado = [
                        'nueva'       => 'Recibimos tu solicitud y estamos por revisarla.',
                        'en_proceso'  => 'Estamos trabajando en tu solicitud. Pronto tendrás novedades.',
                        'completada'  => '¡Tu solicitud fue completada! Revisa los detalles en tu portal.',
                        'rechazada'   => 'Tu solicitud no pudo ser procesada. Conversa con nosotros para más detalles.',
                    ];
                    enviar_correo(
                        $datosSol['email'],
                        'Actualización de tu solicitud — ' . SITE_NOMBRE,
                        correo_plantilla(
                            'Tu solicitud ahora está: ' . ucfirst(str_replace('_', ' ', $estado)),
                            '<p>Hola <b>' . e($datosSol['nombre'] ?: '') . '</b>,</p>'
                            . '<p><b>Servicio:</b> ' . e($datosSol['tipo_servicio'] ?: '—') . '</p>'
                            . '<p>' . e($nota !== '' ? $nota : ($textoEstado[$estado] ?? '')) . '</p>'
                            . '<p><a href="' . e(url_sitio('portal/solicitudes.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mi solicitud</a></p>'
                        )
                    );
                }
            }

            responder([
                'ok'           => true,
                'mensaje'      => 'Estado actualizado y cliente notificado.',
                'tipo'         => 'success',
                'accion'       => 'estado_solicitud',
                'solicitud_id' => $id,
                'estado'       => $estado,
                'nota'         => $nota,
            ]);
        } else {
            $pdo->prepare('DELETE FROM solicitudes WHERE id = ?')->execute([$id]);
            flash('Solicitud eliminada.', 'warning');
            header('Location: solicitudes.php');
            exit;
        }
    }
}

$filtro = $_GET['estado'] ?? '';
$tipoFiltro = ($_GET['tipo'] ?? '') === 'empresa' || ($_GET['tipo'] ?? '') === 'individual' ? $_GET['tipo'] : '';
if (!in_array($filtro, ['nueva', 'en_proceso', 'completada', 'rechazada'], true)) {
    $filtro = '';
}
$condiciones = [];
$params = [];
if ($filtro !== '') {
    $condiciones[] = 'estado = ?';
    $params[] = $filtro;
}
if ($tipoFiltro !== '') {
    $condiciones[] = 'tipo_solicitud = ?';
    $params[] = $tipoFiltro;
}
$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$stmt = $pdo->prepare("SELECT * FROM solicitudes $where ORDER BY creado_en DESC");
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

$estados = ['nueva', 'en_proceso', 'completada', 'rechazada'];
$nEmpresas = contar_registros("solicitudes", "tipo_solicitud = 'empresa'");
$nIndividuales = contar_registros("solicitudes", "tipo_solicitud = 'individual'");
?>
<div class="d-flex flex-wrap gap-2 mb-2">
    <a href="solicitudes.php" class="btn btn-sm <?= $filtro === '' && $tipoFiltro === '' ? 'btn-fv' : 'btn-outline-fv' ?>">Todas</a>
    <a href="solicitudes.php?tipo=empresa" class="btn btn-sm <?= $tipoFiltro === 'empresa' && $filtro === '' ? 'btn-fv' : 'btn-outline-fv' ?>"><i class="bi bi-buildings me-1"></i>Empresas (<?= $nEmpresas ?>)</a>
    <a href="solicitudes.php?tipo=individual" class="btn btn-sm <?= $tipoFiltro === 'individual' && $filtro === '' ? 'btn-fv' : 'btn-outline-fv' ?>"><i class="bi bi-person me-1"></i>Personas (<?= $nIndividuales ?>)</a>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="solicitudes.php<?= $tipoFiltro ? '?tipo=' . urlencode($tipoFiltro) : '' ?>" class="btn btn-sm <?= $filtro === '' ? 'btn-fv' : 'btn-outline-fv' ?>">Todos los estados</a>
    <?php foreach ($estados as $est): $n = contar_registros('solicitudes', "estado = '$est'" . ($tipoFiltro ? " AND tipo_solicitud = '$tipoFiltro'" : '')); ?>
        <a href="solicitudes.php?estado=<?= $est ?><?= $tipoFiltro ? '&tipo=' . urlencode($tipoFiltro) : '' ?>" class="btn btn-sm <?= $filtro === $est ? 'btn-fv' : 'btn-outline-fv' ?>">
            <?= e(ucfirst(str_replace('_', ' ', $est))) ?> (<?= $n ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover tabla-admin mb-0">
            <thead class="table-light">
                <tr><th class="ps-3">Cliente</th><th>Servicio</th><th>Presupuesto</th><th>Mensaje</th><th>Estado</th><th>Fecha</th><th class="text-end pe-3">Acciones</th></tr>
            </thead>
            <tbody>
                <?php if (!$solicitudes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay solicitudes en esta vista.</td></tr>
                <?php else: foreach ($solicitudes as $s): ?>
                    <tr>
                        <td class="ps-3">
                            <b><?= e($s['nombre']) ?></b>
                            <?php if (($s['tipo_solicitud'] ?? '') === 'empresa'): ?>
                                <span class="badge text-bg-primary ms-1" title="Solicitud empresarial"><i class="bi bi-buildings"></i></span>
                                <?php if ($s['empresa']): ?><br><small class="text-primary"><?= e($s['empresa']) ?><?= $s['cargo'] ? ' · ' . e($s['cargo']) : '' ?></small><?php endif; ?>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?= e($s['email']) ?><?= $s['telefono'] ? ' · ' . e($s['telefono']) : '' ?></small>
                        </td>
                        <td class="small"><?= e($s['tipo_servicio'] ?: '—') ?></td>
                        <td class="small"><?= e($s['presupuesto'] ?: '—') ?></td>
                        <td class="small text-muted" style="max-width:260px;"><?= e(mb_strimwidth($s['mensaje'] ?? '', 0, 90, '…')) ?></td>
                        <td>
                            <span class="badge badge-estado text-uppercase text-bg-<?= match($s['estado']) { 'nueva' => 'danger', 'en_proceso' => 'warning', 'completada' => 'success', 'rechazada' => 'secondary', default => 'light' } ?>" data-estado-sol="<?= (int)$s['id'] ?>">
                                <?= e(str_replace('_', ' ', $s['estado'])) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($s['creado_en']))) ?></td>
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#verSolicitud<?= (int)$s['id'] ?>" title="Ver detalles"><i class="bi bi-eye"></i></button>
                            <a href="mailto:<?= e($s['email']) ?>?subject=Respuesta a tu solicitud de presupuesto con FV Digital" class="btn btn-sm btn-outline-secondary" title="Responder por correo"><i class="bi bi-envelope"></i></a>
                            <?php if ($s['telefono']): ?>
                                <a href="https://wa.me/52<?= e(preg_replace('/\D/', '', $s['telefono'])) ?>" target="_blank" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$estadoBadge = [
    'nueva'      => 'bg-danger',
    'en_proceso' => 'bg-warning text-dark',
    'completada' => 'bg-success',
    'rechazada'  => 'bg-secondary',
];
foreach ($solicitudes as $s): $histSol = $pdo->prepare('SELECT * FROM solicitud_historial WHERE solicitud_id = ? ORDER BY creado_en ASC'); $histSol->execute([(int)$s['id']]); $histSol = $histSol->fetchAll(); ?>
<div class="modal fade" id="verSolicitud<?= (int)$s['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><?= e($s['nombre']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><small class="text-muted d-block">Correo</small><b><?= e($s['email']) ?></b></div>
                    <div class="col-md-6"><small class="text-muted d-block">Teléfono</small><b><?= e($s['telefono'] ?: '—') ?></b></div>
                    <?php if (($s['tipo_solicitud'] ?? '') === 'empresa'): ?>
                        <div class="col-md-6"><small class="text-muted d-block">Empresa</small><b><?= e($s['empresa'] ?: '—') ?></b></div>
                        <div class="col-md-6"><small class="text-muted d-block">Cargo</small><b><?= e($s['cargo'] ?: '—') ?></b></div>
                        <div class="col-md-6"><small class="text-muted d-block">Tamaño de la empresa</small><b><?= e($s['empleados'] ?: '—') ?></b></div>
                        <?php if ($s['rango']): ?><div class="col-md-6"><small class="text-muted d-block">Rango de inversión</small><b><?= e($s['rango']) ?></b></div><?php endif; ?>
                    <?php endif; ?>
                    <div class="col-md-6"><small class="text-muted d-block">Servicio</small><b><?= e($s['tipo_servicio'] ?: '—') ?></b></div>
                    <div class="col-md-6"><small class="text-muted d-block">Presupuesto</small><b><?= e($s['presupuesto'] ?: '—') ?></b></div>
                    <div class="col-12">
                        <small class="text-muted d-block">Mensaje</small>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($s['mensaje']) ?></p>
                    </div>
                    <div class="col-12"><small class="text-muted">Recibido: <?= e(date('d/m/Y H:i', strtotime($s['creado_en']))) ?></small></div>
                    <div class="col-12">
                        <small class="text-muted d-block mb-1">Historial / avances del proceso</small>
                        <?php if (!$histSol): ?>
                            <p class="small text-muted mb-0">Sin avances registrados todavía.</p>
                        <?php else: ?>
                            <ul class="timeline portal-timeline small" id="historial-<?= (int)$s['id'] ?>">
                                <?php foreach ($histSol as $hito): ?>
                                    <li class="d-flex gap-2">
                                        <span class="badge <?= $estadoBadge[$hito['estado']] ?? 'bg-light text-dark' ?> text-uppercase"><?= e(str_replace('_', ' ', $hito['estado'])) ?></span>
                                        <div>
                                            <?php if ($hito['nota']): ?><span class="d-block text-muted"><?= e($hito['nota']) ?></span><?php endif; ?>
                                            <small class="text-muted"><?= e(date('d/m/Y H:i', strtotime($hito['creado_en']))) ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="solicitudes.php" class="js-ajax border rounded p-2 mt-2" id="formEstado-<?= (int)$s['id'] ?>">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="estado">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select name="estado" id="selEstado-<?= (int)$s['id'] ?>" class="form-select form-select-sm" style="max-width:200px;">
                                <?php foreach ($estados as $est): ?>
                                    <option value="<?= $est ?>" <?= $s['estado'] === $est ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $est))) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-fv"><i class="bi bi-check2 me-1"></i>Actualizar estado</button>
                        </div>
                        <input type="text" name="nota" maxlength="300" class="form-control form-control-sm mt-2" placeholder="Nota visible para el cliente (opcional)…" aria-label="Nota para el cliente">
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" onsubmit="return confirm('¿Eliminar esta solicitud?')">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Actualización en vivo del estado de la solicitud (sin recargar la página)
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (d.accion !== 'estado_solicitud' || !d.solicitud_id) return;

    var id = d.solicitud_id;
    var clases = { nueva: 'text-bg-danger', en_proceso: 'text-bg-warning', completada: 'text-bg-success', rechazada: 'text-bg-secondary' };
    var texto = String(d.estado).replace(/_/g, ' ');
    var mayus = texto.charAt(0).toUpperCase() + texto.slice(1);

    var sel = document.getElementById('selEstado-' + id);
    if (sel) sel.value = d.estado;

    var badge = document.querySelector('[data-estado-sol="' + id + '"]');
    if (badge) {
        badge.className = 'badge badge-estado text-uppercase ' + (clases[d.estado] || 'text-bg-light');
        badge.textContent = texto;
    }

    var historial = document.getElementById('historial-' + id);
    if (historial && d.nota) {
        var fecha = new Date();
        var ahora = ('0' + fecha.getDate()).slice(-2) + '/' + ('0' + (fecha.getMonth() + 1)).slice(-2) + '/' + fecha.getFullYear()
                    + ' ' + ('0' + fecha.getHours()).slice(-2) + ':' + ('0' + fecha.getMinutes()).slice(-2);
        var li = document.createElement('li');
        li.className = 'd-flex gap-2';
        var nota = document.createElement('span');
        nota.className = 'badge text-uppercase ' + (clases[d.estado] || 'bg-light text-dark');
        nota.textContent = texto;
        var div = document.createElement('div');
        var txt = document.createElement('span');
        txt.className = 'd-block text-muted';
        txt.textContent = d.nota;
        var sm = document.createElement('small');
        sm.className = 'text-muted';
        sm.textContent = ahora;
        div.appendChild(txt);
        div.appendChild(sm);
        li.appendChild(nota);
        li.appendChild(div);
        historial.appendChild(li);
    }
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>