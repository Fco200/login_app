<?php
$titulo = 'Panel principal';
$subtitulo = 'Resumen general de tu sistema';
$seccionAdmin = 'index.php';

require_once __DIR__ . '/includes/cabecera.php';

$estadisticas = [
    'servicios'     => contar_registros('servicios'),
    'proyectos'     => contar_registros('proyectos'),
    'publicaciones' => contar_registros('publicaciones'),
    'cartas'        => contar_registros('cartas'),
    'testimonios'   => contar_registros('testimonios'),
    'solicitudes'   => contar_registros('solicitudes'),
    'solicitudes_nuevas' => contar_registros('solicitudes', "estado = 'nueva'"),
    'mensajes'      => contar_registros('mensajes_contacto'),
    'mensajes_no_leidos' => contar_registros('mensajes_contacto', 'leido = 0'),
    'portal_mensajes' => contar_registros('mensajes_portal'),
    'portal_sin_leer' => (int)$pdo->query("SELECT COUNT(*) FROM mensajes_portal WHERE remitente = 'cliente' AND leido = 0")->fetchColumn(),
    'suscripciones' => contar_registros('suscripciones'),
    'usuarios'      => contar_registros('usuarios'),
    'vehiculos'     => contar_registros('vehiculos'),
];

$visitas = (int)$pdo->query('SELECT COALESCE(SUM(visitas),0) FROM publicaciones')->fetchColumn();

$recientesSolicitudes = $pdo->query("SELECT * FROM solicitudes ORDER BY creado_en DESC LIMIT 5")->fetchAll();
$recientesMensajes = $pdo->query("SELECT * FROM mensajes_contacto ORDER BY creado_en DESC LIMIT 5")->fetchAll();
$recientesPortal = $pdo->query("SELECT m.*, u.nombre AS cliente FROM mensajes_portal m JOIN usuarios u ON u.id = m.usuario_id ORDER BY m.creado_en DESC LIMIT 5")->fetchAll();
$pagosPendientes = $pdo->query("SELECT p.*, mp.nombre AS metodo_nombre, u.nombre AS cliente_nombre FROM pagos p LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id LEFT JOIN usuarios u ON p.usuario_id = u.id WHERE p.estado = 'pendiente' ORDER BY p.creado_en DESC LIMIT 8")->fetchAll();

/* ---------- Métricas financieras ---------- */
$metricas = [
    'ingresos_total'    => (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado='aprobado'")->fetchColumn(),
    'ingresos_mes'      => (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado='aprobado' AND DATE_FORMAT(creado_en,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn(),
    'pendiente_monto'   => (float)$pdo->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado='pendiente'")->fetchColumn(),
    'ticket_promedio'   => (float)$pdo->query("SELECT COALESCE(AVG(monto),0) FROM pagos WHERE estado='aprobado'")->fetchColumn(),
    'clientes_activos'  => (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='cliente' AND activo=1")->fetchColumn(),
    'solicitudes_mes'   => (int)$pdo->query("SELECT COUNT(*) FROM solicitudes WHERE DATE_FORMAT(creado_en,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')")->fetchColumn(),
    'pagos_aprobados'   => (int)$pdo->query("SELECT COUNT(*) FROM pagos WHERE estado='aprobado'")->fetchColumn(),
];

/* Ingresos mensuales: últimos 6 meses */
$etiquetasMeses = [];
$montosMeses = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-{$i} months"));
    $etiquetasMeses[] = date('M Y', strtotime($mes . '-01'));
    $stmtMes = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE estado='aprobado' AND DATE_FORMAT(creado_en,'%Y-%m') = ?");
    $stmtMes->execute([$mes]);
    $montosMeses[] = (float)$stmtMes->fetchColumn();
}

/* Clientes con más facturación */
$topClientes = $pdo->query("SELECT u.id, u.nombre, u.email, COALESCE(SUM(p.monto),0) AS total, COUNT(p.id) AS n
                            FROM pagos p JOIN usuarios u ON u.id = p.usuario_id
                            WHERE p.estado='aprobado'
                            GROUP BY p.usuario_id ORDER BY total DESC LIMIT 5")->fetchAll();
?>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Servicios', $estadisticas['servicios'], 'bi-grid', 'text-bg-primary', 'servicios.php'],
        ['Proyectos / Plantillas', $estadisticas['proyectos'], 'bi-briefcase', 'text-bg-info', 'proyectos.php'],
        ['Publicaciones', $estadisticas['publicaciones'], 'bi-journal-text', 'text-bg-secondary', 'publicaciones.php'],
        ['Cartas', $estadisticas['cartas'], 'bi-envelope-paper', 'text-bg-dark', 'cartas.php'],
        ['Solicitudes', $estadisticas['solicitudes'], 'bi-inbox', 'text-bg-success', 'solicitudes.php'],
        ['Mensajes', $estadisticas['mensajes'], 'bi-envelope', 'text-bg-warning', 'mensajes.php'],
        ['Chat del portal', $estadisticas['portal_mensajes'], 'bi-chat-dots', 'text-bg-dark', 'mensajes_portal.php'],
        ['Suscripciones', $estadisticas['suscripciones'], 'bi-envelope-heart', 'text-bg-danger', 'suscripciones.php'],
        ['Usuarios', $estadisticas['usuarios'], 'bi-people', 'text-bg-primary', 'usuarios.php'],
    ];
    foreach ($cards as $c): ?>
        <div class="col-6 col-md-4 col-xxl-3">
            <a href="<?= $c[4] ?>" class="text-decoration-none">
                <div class="card estadistica border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="icono text-white <?= $c[4] ?>"><i class="bi <?= $c[2] ?>"></i></span>
                        <div><b class="fs-4 d-block"><?= $c[1] ?></b><small class="text-muted"><?= $c[0] ?></small></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card estadistica border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="icono text-white text-bg-primary"><i class="bi bi-eye"></i></span>
                <div><b class="fs-4"><?= number_format($visitas) ?></b><small class="text-muted d-block">Visitas totales a publicaciones</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card estadistica border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="icono text-white text-bg-danger"><i class="bi bi-inbox"></i></span>
                <div><b class="fs-4"><?= $estadisticas['solicitudes_nuevas'] ?></b><small class="text-muted d-block">Solicitudes por atender</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card estadistica border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="icono text-white text-bg-warning"><i class="bi bi-envelope"></i></span>
                <div><b class="fs-4"><?= $estadisticas['mensajes_no_leidos'] ?></b><small class="text-muted d-block">Mensajes sin leer</small></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card estadistica border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="icono text-white text-bg-dark"><i class="bi bi-chat-dots"></i></span>
                <div><b class="fs-4"><?= $estadisticas['portal_sin_leer'] ?></b><small class="text-muted d-block">Mensajes del portal sin contestar</small></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-6 col-md-3">
        <div class="card estadistica border-0 shadow-sm h-100">
            <div class="card-body">
                <span class="icono text-white text-bg-success mb-2"><i class="bi bi-cash-stack"></i></span>
                <b class="fs-4 d-block">$<?= number_format($metricas['ingresos_mes'], 0) ?></b>
                <small class="text-muted">Ingresos del mes (aprobados)</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card estadistica border-0 shadow-sm h-100">
            <div class="card-body">
                <span class="icono text-white text-bg-primary mb-2"><i class="bi bi-graph-up-arrow"></i></span>
                <b class="fs-4 d-block">$<?= number_format($metricas['ingresos_total'], 0) ?></b>
                <small class="text-muted">Ingresos totales · <?= $metricas['pagos_aprobados'] ?> pagos</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card estadistica border-0 shadow-sm h-100">
            <div class="card-body">
                <span class="icono text-white text-bg-warning mb-2"><i class="bi bi-receipt"></i></span>
                <b class="fs-4 d-block">$<?= number_format($metricas['ticket_promedio'], 0) ?></b>
                <small class="text-muted">Ticket promedio</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card estadistica border-0 shadow-sm h-100">
            <div class="card-body">
                <span class="icono text-white text-bg-dark mb-2"><i class="bi bi-people"></i></span>
                <b class="fs-4 d-block"><?= $metricas['clientes_activos'] ?></b>
                <small class="text-muted">Clientes activos · <?= $metricas['solicitudes_mes'] ?> solicitudes <span class="text-lowercase">en el mes</span></small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-bar-chart-fill me-1 text-primary"></i>Ingresos por mes (últimos 6 meses)</b>
                <span class="text-muted small">Pagos aprobados</span>
            </div>
            <div class="card-body">
                <canvas id="graficaIngresos" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-trophy me-1 text-warning"></i>Clientes con mayor facturación</b>
                <a href="clientes.php" class="btn btn-sm btn-outline-primary">Clientes</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$topClientes): ?>
                    <p class="text-center text-muted py-4 mb-0">Sin pagos aprobados todavía.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topClientes as $tc): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                <div class="min-w-0">
                                    <b class="d-block text-truncate" style="max-width:180px;"><?= e($tc['nombre']) ?></b>
                                    <small class="text-muted"><?= (int)$tc['n'] ?> pago(s) aprobado(s)</small>
                                </div>
                                <span class="fw-semibold text-success">$<?= number_format((float)$tc['total'], 0) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Pagos por aprobar -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b><i class="bi bi-credit-card-2-front me-1 text-warning"></i>Pagos por aprobar</b>
                <a href="pagos.php?estado=pendiente" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light"><tr><th>Cliente</th><th>Tipo</th><th>Método</th><th>Monto</th><th>Comprobante</th><th class="text-end pe-3">Acción</th></tr></thead>
                    <tbody>
                        <?php if (!$pagosPendientes): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-check2-all me-1 text-success"></i>No hay pagos pendientes de aprobación.</td></tr>
                        <?php else: foreach ($pagosPendientes as $pp): ?>
                            <tr>
                                <td><b><?= e($pp['cliente_nombre'] ?: 'Cliente #' . (int)$pp['usuario_id']) ?></b></td>
                                <td><span class="badge badge-estado text-uppercase text-capitalize text-bg-info"><?= e($pp['tipo_pago']) ?></span></td>
                                <td class="small"><?= e($pp['metodo_nombre'] ?: '—') ?></td>
                                <td class="fw-semibold">$<?= number_format((float)$pp['monto'], 0) ?> MXN</td>
                                <td>
                                    <?php if ($pp['comprobante']): ?>
                                        <a href="../<?= e($pp['comprobante']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver comprobante"><i class="bi bi-file-earmark-arrow-down"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="pagos.php" class="btn btn-sm btn-outline-primary" title="Gestionar pago"><i class="bi bi-gear"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Solicitudes recientes -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b>Solicitudes recientes</b>
                <a href="solicitudes.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light"><tr><th>Cliente</th><th>Servicio</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php if (!$recientesSolicitudes): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin solicitudes todavía.</td></tr>
                        <?php else: foreach ($recientesSolicitudes as $s): ?>
                            <tr>
                                <td><b><?= e($s['nombre']) ?></b><br><small class="text-muted"><?= e($s['email']) ?></small></td>
                                <td class="small"><?= e($s['tipo_servicio'] ?: '—') ?></td>
                                <td>
                                    <?php
                                    $badge = match($s['estado']) {
                                        'nueva' => 'text-bg-danger',
                                        'en_proceso' => 'text-bg-warning',
                                        'completada' => 'text-bg-success',
                                        'rechazada' => 'text-bg-secondary',
                                        default => 'text-bg-light'
                                    };
                                    ?>
                                    <span class="badge badge-estado text-uppercase <?= $badge ?>"><?= e(str_replace('_', ' ', $s['estado'])) ?></span>
                                </td>
                                <td class="small text-muted"><?= e(tiempo_relativo($s['creado_en'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mensajes recientes -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b>Mensajes recientes</b>
                <a href="mensajes.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light"><tr><th>Remitente</th><th>Asunto</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php if (!$recientesMensajes): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin mensajes todavía.</td></tr>
                        <?php else: foreach ($recientesMensajes as $m): ?>
                            <tr>
                                <td><b><?= e($m['nombre']) ?></b><br><small class="text-muted"><?= e($m['email']) ?></small></td>
                                <td class="small"><?= e($m['asunto'] ?: 'Sin asunto') ?></td>
                                <td><?= $m['leido'] ? '<span class="badge badge-estado text-bg-success">Leído</span>' : '<span class="badge badge-estado text-bg-danger">Nuevo</span>' ?></td>
                                <td class="small text-muted"><?= e(tiempo_relativo($m['creado_en'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Chat del portal reciente -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <b>Mensajes recientes del portal</b>
                <a href="mensajes_portal.php" class="btn btn-sm btn-outline-primary">Abrir chat del portal</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light"><tr><th>Cliente</th><th>Mensaje</th><th>Remitente</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php if (!$recientesPortal): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Sin mensajes del portal todavía.</td></tr>
                        <?php else: foreach ($recientesPortal as $mp): ?>
                            <tr>
                                <td><b><?= e($mp['cliente']) ?></b></td>
                                <td class="small text-muted" style="max-width:420px;"><?= e(mb_strimwidth($mp['mensaje'] ?? '', 0, 100, '…')) ?></td>
                                <td><?= $mp['remitente'] === 'cliente'
                                    ? '<span class="badge badge-estado text-bg-primary">Cliente</span>'
                                    : '<span class="badge badge-estado text-bg-success">FV Digital</span>' ?></td>
                                <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($mp['creado_en']))) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('graficaIngresos');
    if (!canvas || typeof Chart === 'undefined') return;
    var meses = <?= json_encode($etiquetasMeses, JSON_UNESCAPED_UNICODE) ?>;
    var montos = <?= json_encode($montosMeses) ?>;
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Ingresos (MXN)',
                data: montos,
                backgroundColor: 'rgba(10, 61, 143, .75)',
                borderColor: '#0a3d8f',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { ticks: { callback: function (v) { return '$' + Number(v).toLocaleString(); } } } }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>