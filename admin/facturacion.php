<?php
$titulo = 'Facturación y documentos';
$subtitulo = 'Facturas, recibos, cartas de agradecimiento y archivos descargables con la marca FV Digital';
$seccionAdmin = 'facturacion.php';

require_once __DIR__ . '/includes/cabecera.php';

date_default_timezone_set('America/Hermosillo');

/* ---------- Acción: cancelar factura ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    if (($_POST['accion'] ?? '') === 'cancelar_factura' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT folio, proyecto_id, usuario_id FROM facturas WHERE id = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        if ($f) {
            $pdo->prepare("UPDATE facturas SET estado = 'cancelada' WHERE id = ?")->execute([$id]);
            registrar_historial_proyecto((int)$f['proyecto_id'], 'factura_cancelada', 'Factura ' . $f['folio'] . ' cancelada.', (int)($_SESSION['admin_id'] ?? null));
            flash('Factura ' . $f['folio'] . ' cancelada.', 'warning');
        }
        header('Location: facturacion.php?tab=facturas');
        exit;
    }
}

$tab = (string)($_GET['tab'] ?? 'facturas');
if (!in_array($tab, ['facturas', 'recibos', 'cartas', 'archivos'], true)) {
    $tab = 'facturas';
}

/* ---------- Datos: facturas ---------- */
$facturas = $pdo->query('SELECT f.*, u.nombre AS cliente_nombre, u.email AS cliente_email
                         FROM facturas f
                         LEFT JOIN usuarios u ON f.usuario_id = u.id
                         ORDER BY f.creado_en DESC')->fetchAll();

$totalFacturado = 0.0;
$totalIva = 0.0;
$totalEmitidas = 0;
foreach ($facturas as $f) {
    if ($f['estado'] === 'emitida') {
        $totalFacturado += (float)$f['total'];
        $totalIva += (float)$f['iva'];
        $totalEmitidas++;
    }
}

/* ---------- Datos: recibos (pagos) ---------- */
$pagos = $pdo->query('SELECT p.*, mp.nombre AS metodo_nombre, u.nombre AS cliente_nombre, u.email AS cliente_email,
                             s.tipo_servicio, pi.id AS proyecto_id, pi.total_proyecto, pi.estado AS proyecto_estado
                      FROM pagos p
                      LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                      LEFT JOIN usuarios u ON p.usuario_id = u.id
                      LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                      LEFT JOIN proyectos_inicio pi ON pi.id = p.proyecto_id
                      ORDER BY p.creado_en DESC')->fetchAll();

$pagosAprobados = array_filter($pagos, fn($p) => $p['estado'] === 'aprobado');
$totalRecibos = array_sum(array_map(fn($p) => (float)$p['monto'], $pagosAprobados));

/* ---------- Datos: cartas de agradecimiento (proyectos entregados) ---------- */
$cartas = $pdo->query("SELECT pi.id, pi.creado_en, pi.estado, s.tipo_servicio, u.nombre AS cliente_nombre, u.email AS cliente_email,
                              (SELECT COUNT(*) FROM entregables en WHERE en.proyecto_id = pi.id) AS entregables
                       FROM proyectos_inicio pi
                       LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                       LEFT JOIN usuarios u ON pi.usuario_id = u.id
                       WHERE pi.estado = 'completado'
                       ORDER BY pi.creado_en DESC")->fetchAll();

/* ---------- Datos: archivos descargables (entregables) ---------- */
$entregables = $pdo->query('SELECT en.*, pi.usuario_id, u.nombre AS cliente_nombre, u.email AS cliente_email,
                                   s.tipo_servicio, pi.estado AS proyecto_estado
                            FROM entregables en
                            LEFT JOIN proyectos_inicio pi ON pi.id = en.proyecto_id
                            LEFT JOIN usuarios u ON pi.usuario_id = u.id
                            LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                            ORDER BY en.creado_en DESC')->fetchAll();
?>
<ul class="nav nav-tabs nav-tabs-fv gap-1 mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $tab === 'facturas' ? 'active' : '' ?>" href="facturacion.php?tab=facturas"><i class="bi bi-receipt-cutoff me-1"></i>Facturas (<?= count($facturas) ?>)</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $tab === 'recibos' ? 'active' : '' ?>" href="facturacion.php?tab=recibos"><i class="bi bi-receipt me-1"></i>Recibos (<?= count($pagos) ?>)</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $tab === 'cartas' ? 'active' : '' ?>" href="facturacion.php?tab=cartas"><i class="bi bi-envelope-heart me-1"></i>Cartas (<?= count($cartas) ?>)</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= $tab === 'archivos' ? 'active' : '' ?>" href="facturacion.php?tab=archivos"><i class="bi bi-folder2-open me-1"></i>Archivos (<?= count($entregables) ?>)</a>
    </li>
</ul>

<?php if ($tab === 'facturas'): ?>
    <!-- =========================== FACTURAS =========================== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 p-3 text-center factura-kpi">
                <div class="factura-kpi-icono bg-primary-subtle"><i class="bi bi-receipt-cutoff text-primary"></i></div>
                <div class="fs-4 fw-bold text-primary"><?= $totalEmitidas ?></div>
                <small class="text-muted">Facturas emitidas</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 p-3 text-center factura-kpi">
                <div class="factura-kpi-icono bg-success-subtle"><i class="bi bi-coin text-success"></i></div>
                <div class="fs-4 fw-bold text-success">$<?= number_format($totalFacturado, 2) ?></div>
                <small class="text-muted">Total facturado (incl. IVA)</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 p-3 text-center factura-kpi">
                <div class="factura-kpi-icono bg-warning-subtle"><i class="bi bi-percent text-warning"></i></div>
                <div class="fs-4 fw-bold text-warning">$<?= number_format($totalIva, 2) ?></div>
                <small class="text-muted">IVA facturado</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 p-3 text-center factura-kpi">
                <div class="factura-kpi-icono bg-info-subtle"><i class="bi bi-bank text-info"></i></div>
                <div class="fs-4 fw-bold"><?= count($facturas) ?></div>
                <small class="text-muted">Registros totales</small>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Concepto</th>
                        <th>RFC</th>
                        <th>Subtotal</th>
                        <th>IVA</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$facturas): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-receipt-cutoff me-1"></i>Todavía no se ha emitido ninguna factura.</td></tr>
                    <?php else: foreach ($facturas as $f): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><i class="bi bi-file-earmark-text text-primary me-1"></i><?= e($f['folio']) ?></td>
                            <td class="small text-muted"><?= e(date('d/m/Y', strtotime($f['creado_en']))) ?></td>
                            <td class="small">
                                <b><?= e($f['cliente_nombre'] ?: '—') ?></b>
                                <br><small class="text-muted"><?= e($f['cliente_email'] ?: '') ?></small>
                            </td>
                            <td class="small"><?= e(mb_strimwidth($f['concepto'], 0, 40, '…')) ?></td>
                            <td class="small"><?= e($f['rfq_cliente'] ?: '—') ?></td>
                            <td>$<?= number_format((float)$f['subtotal'], 2) ?></td>
                            <td>$<?= number_format((float)$f['iva'], 2) ?></td>
                            <td class="fw-semibold">$<?= number_format((float)$f['total'], 2) ?></td>
                            <td>
                                <?= $f['estado'] === 'emitida'
                                    ? '<span class="badge badge-estado text-bg-success">Emitida</span>'
                                    : '<span class="badge badge-estado text-bg-secondary">Cancelada</span>' ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-sm btn-outline-primary" title="Ver factura" onclick="verFactura(<?= (int)$f['id'] ?>)"><i class="bi bi-eye"></i></button>
                                    <a href="../portal/factura_pdf.php?id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-success" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                    <a href="../portal/factura_print.php?id=<?= (int)$f['id'] ?>&imprimir=1" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir"><i class="bi bi-printer"></i></a>
                                    <?php if ($f['estado'] === 'emitida'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Cancelar la factura <?= e($f['folio']) ?>?');">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="cancelar_factura">
                                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" title="Cancelar factura"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'recibos'): ?>
    <!-- =========================== RECIBOS =========================== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-primary"><?= count($pagosAprobados) ?></div><small class="text-muted">Recibos aprobados</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-success">$<?= number_format($totalRecibos, 2) ?></div><small class="text-muted">Total cobrado</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-warning"><?= count(array_filter($pagos, fn($p) => $p['estado'] === 'pendiente')) ?></div><small class="text-muted">Pendientes</small></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr><th class="ps-3">Folio</th><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Método</th><th>Tipo</th><th>Monto</th><th>Estado</th><th>Comprobante</th><th class="text-end pe-3">Recibo</th></tr>
                </thead>
                <tbody>
                    <?php if (!$pagos): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4"><i class="bi bi-receipt me-1"></i>Todavía no hay pagos registrados.</td></tr>
                    <?php else: foreach ($pagos as $p): ?>
                        <tr>
                            <td class="ps-3 fw-semibold">PAG-<?= str_pad((string)(int)$p['id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></td>
                            <td class="small">
                                <b><?= e($p['cliente_nombre'] ?: 'Cliente #' . (int)$p['usuario_id']) ?></b>
                                <br><small class="text-muted"><?= e($p['cliente_email'] ?: '—') ?></small>
                            </td>
                            <td class="small"><?= e($p['tipo_servicio'] ?: 'Compra de productos') ?></td>
                            <td class="small"><?= e($p['metodo_nombre'] ?: '—') ?></td>
                            <td class="small"><span class="text-capitalize"><?= e($p['tipo_pago']) ?></span></td>
                            <td class="fw-semibold">$<?= number_format((float)$p['monto'], 2) ?> MXN</td>
                            <td>
                                <span class="badge badge-estado text-uppercase text-bg-<?= $p['estado'] === 'aprobado' ? 'success' : ($p['estado'] === 'rechazado' ? 'danger' : 'warning') ?>" style="color:#fff;"><?= e($p['estado']) ?></span>
                            </td>
                            <td>
                                <?php if ($p['comprobante']): ?>
                                    <a href="../<?= e($p['comprobante']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver comprobante"><i class="bi bi-paperclip"></i></a>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="../portal/recibo.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm <?= $p['estado'] === 'aprobado' ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="Ver / descargar recibo"><i class="bi bi-receipt me-1"></i>Recibo</a>
                                <a href="../portal/recibo_pdf.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'cartas'): ?>
    <!-- ================= CARTA DE AGRADECIMIENTO ================= -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-6"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-primary"><?= count($cartas) ?></div><small class="text-muted">Proyectos entregados con carta</small></div></div>
        <div class="col-6 col-md-6"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-success"><?= array_sum(array_map(fn($c) => (int)$c['entregables'], $cartas)) ?></div><small class="text-muted">Entregables incluidos</small></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr><th class="ps-3">Proyecto</th><th>Cliente</th><th>Contacto</th><th>Entregado el</th><th>Entregables</th><th class="text-end pe-3">Carta</th></tr>
                </thead>
                <tbody>
                    <?php if (!$cartas): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-envelope-heart me-1"></i>Aún no hay proyectos entregados para generar cartas.</td></tr>
                    <?php else: foreach ($cartas as $c): ?>
                        <tr>
                            <td class="ps-3"><b><i class="bi bi-bezier2 text-primary me-1"></i><?= e($c['tipo_servicio'] ?: 'Proyecto #' . (int)$c['id']) ?></b>
                                <br><small class="text-muted">Proyecto #<?= (int)$c['id'] ?></small>
                            </td>
                            <td class="small"><b><?= e($c['cliente_nombre'] ?: '—') ?></b></td>
                            <td class="small text-muted"><?= e($c['cliente_email'] ?: '—') ?></td>
                            <td class="small text-muted"><?= e(date('d/m/Y', strtotime($c['creado_en']))) ?></td>
                            <td><span class="badge text-bg-light"><?= (int)$c['entregables'] ?> archivo(s)</span></td>
                            <td class="text-end pe-3">
                                <a href="../portal/carta_agradecimiento.php?proyecto=<?= (int)$c['id'] ?>" target="_blank" class="btn btn-sm btn-success" title="Ver / descargar carta"><i class="bi bi-envelope-heart me-1"></i>Carta de agradecimiento</a>
                                <a href="../portal/carta_pdf.php?proyecto=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-success" title="Descargar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: /* ---------- ARCHIVOS DESCARGABLES (ENTREGABLES) ---------- */ ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-primary"><?= count($entregables) ?></div><small class="text-muted">Archivos subidos</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-success"><?= count(array_unique(array_map(fn($en) => (int)$en['proyecto_id'], $entregables))) ?></div><small class="text-muted">Proyectos con entregables</small></div></div>
        <div class="col-6 col-md-4"><div class="card border-0 shadow-sm p-3 text-center factura-kpi"><div class="fs-4 fw-bold text-warning"><?= count(array_filter($entregables, fn($en) => $en['proyecto_estado'] === 'completado')) ?></div><small class="text-muted">Entregas finales</small></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover tabla-admin mb-0 align-middle">
                <thead class="table-light">
                    <tr><th class="ps-3">Archivo</th><th>Proyecto</th><th>Cliente</th><th>Tipo</th><th>Subido el</th><th>Estado</th><th class="text-end pe-3">Descargar</th></tr>
                </thead>
                <tbody>
                    <?php if (!$entregables): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-folder2-open me-1"></i>Aún no hay archivos descargables. Sube entregables desde la sección Entregables.</td></tr>
                    <?php else: foreach ($entregables as $en): ?>
                        <tr>
                            <td class="ps-3">
                                <b><i class="bi bi-file-earmark-arrow-down text-success me-1"></i><?= e($en['titulo']) ?></b>
                                <br><small class="text-muted"><?= e(mb_strimwidth($en['notas'], 0, 55, '…')) ?></small>
                            </td>
                            <td class="small"><?= e($en['tipo_servicio'] ?: 'Proyecto #' . (int)$en['proyecto_id']) ?></td>
                            <td class="small"><?= e($en['cliente_nombre'] ?: '—') ?></td>
                            <td>
                                <span class="badge text-bg-light text-uppercase"><?= e(pathinfo($en['archivo'], PATHINFO_EXTENSION)) ?></span>
                            </td>
                            <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($en['creado_en']))) ?></td>
                            <td>
                                <span class="badge badge-estado text-uppercase text-bg-<?= $en['proyecto_estado'] === 'completado' ? 'success' : 'primary' ?>"><?= e(str_replace('_', ' ', $en['proyecto_estado'] ?? '—')) ?></span>
                            </td>
                            <td class="text-end pe-3">
                                <a href="../portal/descargar-entregable.php?id=<?= (int)$en['id'] ?>" class="btn btn-sm btn-outline-success" title="Descargar archivo"><i class="bi bi-download me-1"></i>Descargar</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ================= Vista previa de factura ================= -->
<div class="modal fade" id="modalFactura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Vista previa de la factura</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="contenidoFactura">
                <div class="text-center text-muted py-5"><div class="spinner-border text-primary mb-2" role="status"></div><div>Cargando factura…</div></div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-secondary" id="btnImprimirFactura" href="#" target="_blank"><i class="bi bi-printer me-1"></i>Imprimir</a>
                <a class="btn btn-outline-success" id="btnPdfFactura" href="#"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar PDF</a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var facturaActual = null;
var modalFacturaObj = null;

function fmtMXN2(n) {
    return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
}

/* Plantilla HTML (pantalla + impresión) de la factura con la marca. */
function plantillaFacturaHTML(f) {
    var M = window.FVMarca || {};
    var logo = M.logo ? '<img src="' + M.logo + '" alt="" class="factura-logo">' : '';
    var pagos = (f && f.pagos) || [];
    var filas = pagos.length
        ? pagos.map(function (p) {
            var fol = (p.id != null) ? 'PAG-' + String(p.id).padStart(5, '0') : '';
            return '<tr><td><b>' + fol + '</b></td><td class="text-muted">' + (p.fecha || '—') + '</td><td class="text-capitalize">' + (p.tipo || 'Pago') + '</td><td class="text-end fw-semibold">' + fmtMXN2(p.monto) + '</td></tr>';
        }).join('')
        : '<tr><td colspan="4" class="text-center text-muted">Pagos aprobados del proyecto</td></tr>';
    var ivarate = (f.subtotal > 0) ? (f.iva / f.subtotal) : 0.16;
    var ivapct = Math.round(ivarate * 100);

    return '<div class="factura-hoja">'
        + '<div class="factura-encabezado">'
        + '<div class="factura-marca">' + logo + '<div><b>' + escHTML(f.emisor || M.nombre || 'FV Digital') + '</b><span>' + (M.eslogan || '') + '</span></div></div>'
        + '<div class="factura-folio"><span>FACTURA</span><b>' + escHTML(f.folio || '—') + '</b><small>Fecha: ' + (f.fecha || '—') + (f.solicitud_id ? ' · Nº solicitud: ' + f.solicitud_id : '') + '</small></div>'
        + '</div>'
        + '<div class="factura-cuerpo">'
        + '<div class="factura-bloques">'
        + '<div class="factura-bloque"><div class="factura-titulo-bloque">DATOS DEL CLIENTE</div>'
        + '<div><span>Cliente</span><b>' + escHTML(f.cliente_nombre || '—') + '</b></div>'
        + '<div><span>RFC</span><b>' + escHTML(f.rfc || '—') + '</b></div>'
        + '<div><span>Correo</span><b>' + escHTML(f.cliente_email || '—') + '</b></div>'
        + '<div><span>Dirección</span><b>' + escHTML(f.direccion || '—') + '</b></div>'
        + '<div><span>Concepto</span><b>' + escHTML(f.concepto || 'Desarrollo de proyecto') + '</b></div>'
        + '</div>'
        + '<div class="factura-bloque"><div class="factura-titulo-bloque">EMISOR</div>'
        + '<div><span>Empresa</span><b>' + escHTML(f.emisor || M.nombre || SITE_NOMBRE) + '</b></div>'
        + (M.razon_social ? '<div><span>Razón social</span><b>' + escHTML(M.razon_social) + '</b></div>' : '')
        + (M.rfc ? '<div><span>RFC</span><b>' + escHTML(M.rfc) + '</b></div>' : '')
        + (M.direccion ? '<div><span>Dirección</span><b>' + escHTML(M.direccion) + '</b></div>' : '')
        + (M.telefono ? '<div><span>Teléfono</span><b>' + escHTML(M.telefono) + '</b></div>' : '')
        + (M.email ? '<div><span>Correo</span><b>' + escHTML(M.email) + '</b></div>' : '')
        + '</div>'
        + '</div>'
        + '<table class="factura-tabla">'
        + '<thead><tr><th>Folio de pago</th><th>Fecha</th><th>Tipo</th><th class="text-end">Monto</th></tr></thead>'
        + '<tbody>' + filas + '</tbody>'
        + '</table>'
        + '<div class="factura-totales">'
        + '<div class="factura-total-fila"><span>Subtotal</span><b>' + fmtMXN2(f.subtotal) + '</b></div>'
        + '<div class="factura-total-fila"><span>IVA (' + ivapct + '%)</span><b>' + fmtMXN2(f.iva) + '</b></div>'
        + '<div class="factura-total-final"><span>TOTAL</span><b>' + fmtMXN2(f.total) + '</b></div>'
        + '</div>'
        + '<div class="factura-pie-doc">Esta factura acredita la contratación y entrega del proyecto señalado. Emitida por ' + escHTML(f.emisor || M.nombre || 'FV Digital') + ' con validez oficial de comprobante fiscal para tu expediente.</div>'
        + '</div>'
        + '</div>';
}

function escHTML(t) {
    return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function verFactura(id) {
    facturaActual = null;
    document.getElementById('contenidoFactura').innerHTML =
        '<div class="text-center text-muted py-5"><div class="spinner-border text-primary mb-2" role="status"></div><div>Cargando factura…</div></div>';
    fetch('../portal/facturas_api.php?id=' + id, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                document.getElementById('contenidoFactura').innerHTML =
                    '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>' + escHTML(d.mensaje || 'No se pudo cargar la factura.') + '</div>';
                return;
            }
            facturaActual = d.factura;
            document.getElementById('contenidoFactura').innerHTML = plantillaFacturaHTML(facturaActual);
            document.getElementById('btnPdfFactura').href = '../portal/factura_pdf.php?id=' + facturaActual.id;
            document.getElementById('btnImprimirFactura').href = '../portal/factura_print.php?id=' + facturaActual.id + '&imprimir=1';
            modalFacturaObj = modalFacturaObj || new bootstrap.Modal(document.getElementById('modalFactura'));
            modalFacturaObj.show();
        })
        .catch(function () {
            document.getElementById('contenidoFactura').innerHTML =
                '<div class="text-center text-muted py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>No se pudo cargar la factura.</div>';
        });
}

function estilosFacturaCSS() {
    return '.factura-hoja{max-width:820px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(7,28,61,.15);}'
        + '.factura-encabezado{background:#071c3d;padding:26px 34px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;}'
        + '.factura-marca{display:flex;align-items:center;gap:14px;color:#fff;}'
        + '.factura-logo{height:52px;width:auto;object-fit:contain;filter:drop-shadow(0 0 0 transparent);}'
        + '.factura-marca b{font-size:21px;letter-spacing:.5px;display:block;}'
        + '.factura-marca span{color:#9db8e8;font-size:12px;display:block;}'
        + '.factura-folio{text-align:right;color:#fff;}'
        + '.factura-folio span{display:block;color:#9db8e8;font-size:12px;text-transform:uppercase;letter-spacing:1px;}'
        + '.factura-folio b{font-size:24px;}'
        + '.factura-folio small{display:block;color:#9db8e8;font-size:12px;margin-top:2px;}'
        + '.factura-cuerpo{padding:30px 34px;}'
        + '.factura-bloques{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px;}'
        + '.factura-bloque{border:1px solid #e3eaf5;border-radius:12px;padding:16px;background:#f8fbff;}'
        + '.factura-titulo-bloque{font-size:12px;font-weight:700;color:#0b5ed7;letter-spacing:.6px;margin-bottom:10px;}'
        + '.factura-bloque div{margin-bottom:7px;}'
        + '.factura-bloque span{display:block;font-size:11px;color:#8a97ad;}'
        + '.factura-bloque b{font-size:14px;}'
        + '.factura-tabla{width:100%;border-collapse:collapse;margin-bottom:20px;}'
        + '.factura-tabla thead th{background:#eef3fb;color:#0b3a75;font-size:12px;text-transform:uppercase;letter-spacing:.4px;padding:10px 12px;text-align:left;}'
        + '.factura-tabla td{border-bottom:1px dashed #e3eaf5;padding:10px 12px;font-size:14px;}'
        + '.factura-totales{margin-left:auto;width:300px;}'
        + '.factura-total-fila{display:flex;justify-content:space-between;padding:8px 14px;font-size:14px;color:#456;}'
        + '.factura-total-final{background:#071c3d;color:#fff;padding:12px 14px;border-radius:10px;display:flex;justify-content:space-between;font-size:17px;font-weight:700;margin-top:4px;}'
        + '.factura-pie-doc{margin-top:24px;border-top:1px solid #e3eaf5;padding-top:12px;color:#8a97ad;font-size:12px;text-align:center;}'
        + 'body{background:#dfe7f3;font-family:Arial,Helvetica,sans-serif;color:#1c2b45;padding:24px 12px;}'
        + '@media print{body{background:#fff;padding:0;}body *{box-shadow:none!important;}.factura-hoja{max-width:none;border-radius:0;}}';
}

function descargarFacturaAdmin(id) {
    window.open('../portal/factura_pdf.php?id=' + id, '_blank');
}

document.addEventListener('DOMContentLoaded', function () {
    modalFacturaObj = new bootstrap.Modal(document.getElementById('modalFactura'));
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>