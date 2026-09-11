<?php
$titulo = 'Procesos';
$subtitulo = 'El ciclo completo del proyecto: en proceso, entregados e historial';
$seccionAdmin = 'procesos.php';

require_once __DIR__ . '/includes/cabecera.php';

/* ---------- Acciones ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_estado_proyecto') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        $estado = trim((string)($_POST['estado'] ?? ''));
        $nota = trim((string)($_POST['nota'] ?? ''));
        if ($proyectoId > 0) {
            $aplicado = proyecto_cambiar_estado($proyectoId, $estado);
            if ($aplicado !== '') {
                $d = proyecto_datos($proyectoId);
                registrar_historial_proyecto($proyectoId, 'estado_cambiado', ($nota !== '' ? $nota . ' ' : '') . 'Estado cambiado a "' . ucfirst(str_replace('_', ' ', $aplicado)) . '".', (int)($_SESSION['admin_id'] ?? null));
                $textos = [
                    'documentacion'      => 'Regresamos tu proyecto a la fase de documentación.',
                    'anticipo_pendiente' => 'Tu proyecto quedó en espera de que cubras el anticipo mínimo.',
                    'en_desarrollo'      => '¡Tu proyecto está en desarrollo! Sigue su avance y entregables en tu portal.',
                    'liquidado'          => 'Tu proyecto está liquidado. Puedes solicitar tu factura desde tu portal.',
                    'completado'         => '¡Tu proyecto fue completado! Ya puedes descargar tus entregables finales desde tu portal.',
                ];
                $mensaje = $nota !== '' ? $nota : ($textos[$aplicado] ?? '');
                notificar(
                    (int)($d['cliente_id'] ?? 0),
                    $aplicado === 'completado' ? 'exito' : 'proyecto',
                    'Tu proyecto ahora está: ' . ucfirst(str_replace('_', ' ', $aplicado)),
                    $mensaje,
                    url_sitio('portal/procesos.php')
                );
                if (!empty($d['cliente_email'])) {
                    enviar_correo(
                        $d['cliente_email'],
                        'Actualización de tu proyecto — ' . SITE_NOMBRE,
                        correo_plantilla(
                            'Tu proyecto ahora está: ' . ucfirst(str_replace('_', ' ', $aplicado)),
                            '<p>Hola <b>' . e($d['cliente_nombre'] ?: '') . '</b>,</p>'
                            . '<p>' . e($mensaje) . '</p>'
                            . '<p><a href="' . e(url_sitio('portal/procesos.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mi proceso</a></p>'
                        )
                    );
                }
                responder([
                    'ok'          => true,
                    'mensaje'     => 'Proyecto actualizado a "' . ucfirst(str_replace('_', ' ', $aplicado)) . '" y cliente notificado.',
                    'accion'      => 'proyecto_estado_cambiado',
                    'proyecto_id' => $proyectoId,
                    'estado'      => $aplicado,
                    'destino'     => url_sitio('admin/procesos.php?' . ($tab = $_GET['tab'] ?? 'proceso')),
                ]);
            }
            responder(['ok' => false, 'mensaje' => 'Estado no válido.', 'tipo' => 'danger']);
        }
        responder(['ok' => false, 'mensaje' => 'Proyecto inválido.', 'tipo' => 'danger']);
    }
}

/* ---------- Datos ---------- */
$tab = (string)($_GET['tab'] ?? 'proceso');
if (!in_array($tab, ['proceso', 'completados', 'historial'], true)) {
    $tab = 'proceso';
}

$estadosActivos = ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado'];
$proyectos = [];
$entregablesPorProyecto = [];
$historial = [];

if ($tab === 'historial') {
    $historial = $pdo->query('SELECT h.*, u.nombre AS usuario_nombre, pi.id AS proy_id, s.tipo_servicio, ue.nombre AS cliente_nombre
                              FROM proyecto_historial h
                              LEFT JOIN usuarios u ON h.usuario_id = u.id
                              LEFT JOIN proyectos_inicio pi ON h.proyecto_id = pi.id
                              LEFT JOIN usuarios ue ON pi.usuario_id = ue.id
                              LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                              ORDER BY h.creado_en DESC LIMIT 300')->fetchAll();
} else {
    $estadosFiltro = $tab === 'completados' ? ['completado'] : $estadosActivos;
    $in = implode(',', array_map(fn($e) => $pdo->quote($e), $estadosFiltro));
    $stmt = $pdo->query("SELECT pi.*, s.tipo_servicio, s.presupuesto, u.nombre AS cliente_nombre, u.email AS cliente_email
                         FROM proyectos_inicio pi
                         LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                         LEFT JOIN usuarios u ON pi.usuario_id = u.id
                         WHERE pi.estado IN ($in)
                         ORDER BY pi.creado_en DESC");
    $proyectos = $stmt->fetchAll();
    $ids = array_map(fn($p) => (int)$p['id'], $proyectos);
    if ($ids) {
        $inP = implode(',', $ids);
        foreach ($pdo->query("SELECT proyecto_id, COUNT(*) AS c FROM entregables WHERE proyecto_id IN ($inP) GROUP BY proyecto_id") as $e) {
            $entregablesPorProyecto[(int)$e['proyecto_id']] = (int)$e['c'];
        }
    }
}

$estadoBadge = [
    'documentacion'      => 'text-bg-info',
    'anticipo_pendiente' => 'text-bg-warning',
    'en_desarrollo'      => 'text-bg-primary',
    'liquidado'          => 'text-bg-success',
    'completado'         => 'text-bg-success',
];
$contadores = [
    'proceso'     => (int)contar_registros('proyectos_inicio', "estado IN ('documentacion','anticipo_pendiente','en_desarrollo','liquidado')"),
    'completados' => (int)contar_registros('proyectos_inicio', "estado = 'completado'"),
    'historial'   => (int)contar_registros('proyecto_historial', '1 = 1'),
];
?>
<div class="nav nav-tabs mb-3" role="tablist">
    <a class="nav-link <?= $tab === 'proceso' ? 'active' : '' ?>" href="procesos.php?tab=proceso"><i class="bi bi-bezier2 me-1"></i>En proceso (<?= $contadores['proceso'] ?>)</a>
    <a class="nav-link <?= $tab === 'completados' ? 'active' : '' ?>" href="procesos.php?tab=completados"><i class="bi bi-check-circle me-1"></i>Completados (<?= $contadores['completados'] ?>)</a>
    <a class="nav-link <?= $tab === 'historial' ? 'active' : '' ?>" href="procesos.php?tab=historial"><i class="bi bi-journal-text me-1"></i>Historial (<?= $contadores['historial'] ?>)</a>
</div>

<div class="alert alert-fv-light small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Al aprobar el anticipo de un cliente (en <b>Pagos</b>), el proyecto avanza solo a <b>En desarrollo</b>. Puedes ajustar estados manualmente; cuando el proyecto se entrega pasa a <b>Completados</b>, se guarda en el <b>Historial</b> y el cliente puede descargar su carta de agradecimiento y factura.
</div>

<?php if ($tab === 'historial'): ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Fecha</th>
                        <th>Proyecto</th>
                        <th>Cliente</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th class="text-end pe-3">Quién</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$historial): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-journal-x me-1"></i>Aún no hay movimientos registrados.</td></tr>
                    <?php else: foreach ($historial as $h): ?>
                        <tr>
                            <td class="ps-3 small text-muted text-nowrap"><?= e(date('d/m/Y H:i', strtotime($h['creado_en']))) ?></td>
                            <td class="small">
                                #<?= (int)$h['proyecto_id'] ?>
                                <?php if ($h['tipo_servicio']): ?><br><small class="text-muted"><?= e($h['tipo_servicio']) ?></small><?php endif; ?>
                            </td>
                            <td class="small"><?= e($h['cliente_nombre'] ?: '—') ?></td>
                            <td><span class="badge text-bg-light text-capitalize"><?= e(str_replace('_', ' ', $h['accion'])) ?></span></td>
                            <td class="small"><?= e($h['detalle'] ?: '—') ?></td>
                            <td class="text-end pe-3 small text-muted"><?= e($h['usuario_nombre'] ?? 'Sistema') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Cliente</th>
                        <th>Proyecto</th>
                        <th>Anticipo</th>
                        <th>Entregables</th>
                        <th>Estado / Acción</th>
                        <th class="text-end pe-3">Iniciado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$proyectos): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-box-seam me-1"></i><?= $tab === 'completados' ? 'Aún no hay proyectos entregados.' : 'No hay proyectos en proceso.' ?></td></tr>
                    <?php else: foreach ($proyectos as $p):
                        $badge = $estadoBadge[$p['estado']] ?? 'text-bg-secondary';
                        $pct = min(100, round((float)$p['anticipo_pagado'] / max(1, (float)$p['anticipo_minimo']) * 100));
                        $cubierto = (float)$p['anticipo_pagado'] >= (float)$p['anticipo_minimo'];
                        $saldoRest = max(0, (float)($p['saldo_restante'] ?? 0));
                        $liquidado = ($p['estado'] ?? '') === 'liquidado' || ((float)($p['total_proyecto'] ?? 0) > 0 && $saldoRest <= 0.01);
                    ?>
                        <tr data-proyecto-row="<?= (int)$p['id'] ?>">
                            <td class="ps-3">
                                <b><?= e($p['cliente_nombre'] ?: 'Cliente #' . (int)$p['usuario_id']) ?></b>
                                <br><small class="text-muted"><?= e($p['cliente_email'] ?: '—') ?></small>
                            </td>
                            <td class="small">
                                <b><?= e($p['tipo_servicio'] ?: 'Proyecto #' . (int)$p['id']) ?></b>
                                <?php if ($p['presupuesto']): ?><br><small class="text-muted">Cotización: <?= e($p['presupuesto']) ?></small><?php endif; ?>
                            </td>
                            <td class="small" style="min-width:150px;">
                                <span class="fw-semibold <?= $liquidado ? 'text-success' : ($cubierto ? 'text-success' : 'text-warning') ?>">Pagado: $<?= number_format((float)($p['pagado_total'] ?? $p['anticipo_pagado']), 0) ?></span>
                                <br><span class="text-muted">Saldo: <b class="<?= $liquidado ? 'text-success' : 'text-danger' ?>">$<?= number_format($saldoRest, 0) ?></b> MXN</span>
                                <?php if ($liquidado): ?>
                                    <br><span class="small text-success"><i class="bi bi-receipt-cutoff me-1"></i>Liquidado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-light border"><?= (int)($entregablesPorProyecto[(int)$p['id']] ?? 0) ?></span>
                            </td>
                            <td class="small">
                                <span class="badge <?= $p['estado'] === 'completado' ? 'text-bg-success' : 'text-bg-primary' ?> text-uppercase" style="color:#fff;" data-proy-estado="<?= (int)$p['id'] ?>">
                                    <?= e(ucfirst(str_replace('_', ' ', $p['estado']))) ?>
                                </span>
                                <form method="POST" class="js-ajax mt-2" style="max-width:230px;">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="cambiar_estado_proyecto">
                                    <input type="hidden" name="proyecto_id" value="<?= (int)$p['id'] ?>">
                                    <select name="estado" class="form-select form-select-sm mb-1" data-proy-select="<?= (int)$p['id'] ?>">
                                        <?php foreach ($estadoBadge as $clave => $badgeSel): ?>
                                            <option value="<?= $clave ?>" <?= $p['estado'] === $clave ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $clave))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="nota" class="form-control form-control-sm mb-1" placeholder="Nota al cliente (opcional)">
                                    <button class="btn btn-sm btn-fv w-100"><i class="bi bi-check-lg me-1"></i>Aplicar</button>
                                </form>
                                <?php if ($p['estado'] === 'completado'): ?>
                                    <a href="../portal/carta_agradecimiento.php?proyecto=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success w-100 mt-1" style="max-width:230px;"><i class="bi bi-envelope-heart me-1"></i>Carta de agradecimiento</a>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 small text-muted"><?= e(date('d/m/Y', strtotime($p['creado_en']))) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<script>
document.addEventListener('fv:ajaxok', function (e) {
    var d = (e && e.detail) || {};
    if (d.accion !== 'proyecto_estado_cambiado') return;
    var id = d.proyecto_id;
    var badge = document.querySelector('[data-proy-estado="' + id + '"]');
    var sel = document.querySelector('[data-proy-select="' + id + '"]');
    if (sel) sel.value = d.estado;
    var color = {
        'documentacion': 'text-bg-info',
        'anticipo_pendiente': 'text-bg-warning',
        'en_desarrollo': 'text-bg-primary',
        'liquidado': 'text-bg-success',
        'completado': 'text-bg-success'
    }[d.estado] || 'text-bg-secondary';
    if (badge) {
        badge.textContent = d.estado.replace(/_/g, ' ').charAt(0).toUpperCase() + d.estado.replace(/_/g, ' ').slice(1);
        badge.className = 'badge badge-estado text-uppercase ' + color;
    }
    if (d.destino) { setTimeout(function () { location.href = d.destino; }, 450); }
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>