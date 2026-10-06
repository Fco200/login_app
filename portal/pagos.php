<?php
require_once __DIR__ . '/../funciones.php';

/* Exportar historial completo en CSV (debe ir antes de imprimir cabecera) */
if (($_GET['export'] ?? '') === 'csv') {
    iniciar_sesion_segura();
    if (!esta_logueado()) {
        redirigir('iniciar-sesion.php');
    }
    $uid = (int)$_SESSION['usuario_id'];
    $stmt = $GLOBALS['pdo']->prepare('SELECT p.id, p.creado_en, s.tipo_servicio AS concepto, mp.nombre AS metodo,
                                             p.tipo_pago, p.monto, p.estado, p.notas
                                      FROM pagos p
                                      LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                                      LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                                      WHERE p.usuario_id = ?
                                      ORDER BY p.creado_en DESC');
    $stmt->execute([$uid]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historial-pagos-' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['Folio', 'Fecha', 'Concepto', 'Método', 'Tipo', 'Monto', 'Estado', 'Notas']);
    foreach ($stmt as $f) {
        fputcsv($salida, [
            'PAG-' . str_pad((string)$f['id'], 5, '0', STR_PAD_LEFT),
            date('d/m/Y H:i', strtotime($f['creado_en'])),
            $f['concepto'] ?: 'Compra de productos',
            $f['metodo'] ?: '',
            $f['tipo_pago'],
            number_format((float)$f['monto'], 2) . ' MXN',
            $f['estado'],
            $f['notas'] ?: '',
        ]);
    }
    fclose($salida);
    exit;
}

require_once __DIR__ . '/includes/cabecera.php';

$seccionPortal = 'pagos';
$titulo = 'Mis pagos';

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'email' => '', 'nombre' => $_SESSION['nombre'] ?? 'Cliente'];

/* ---------- Registrar pago desde Mis Pagos (saldos) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'pagar_saldo') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $tipo = ($_POST['tipo_pago'] ?? '') === 'restante' ? 'restante' : 'completo';
        if ($proyectoId <= 0 || $monto <= 0) {
            responder(['ok' => false, 'mensaje' => 'Captura un monto válido.', 'tipo' => 'warning']);
        }
        /* Validar pertenencia; el resto lo cubre pago_registrar en transacción. */
        $stmtProj = $pdo->prepare('SELECT id, estado FROM proyectos_inicio WHERE id = ? AND usuario_id = ?');
        $stmtProj->execute([$proyectoId, (int)$usuario['id']]);
        $proy = $stmtProj->fetch();
        if (!$proy) {
            responder(['ok' => false, 'mensaje' => 'El proyecto no existe o no te pertenece.', 'tipo' => 'danger']);
        }
        if (($proy['estado'] ?? '') === 'liquidado') {
            responder(['ok' => false, 'mensaje' => 'Este proyecto ya está liquidado. No tiene saldo pendiente.', 'tipo' => 'warning']);
        }
        if (empty($_FILES['comprobante']['name']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            responder(['ok' => false, 'mensaje' => 'Es obligatorio subir el comprobante de pago para registrar tu pago.', 'tipo' => 'warning']);
        }
        $comprobanteRuta = null;
        $res = subir_archivo('comprobante', 'comprobantes', ['jpg','jpeg','png','pdf','webp'], 8);
        if ($res['ok']) {
            $comprobanteRuta = $res['archivo'];
        } else {
            responder(['ok' => false, 'mensaje' => 'Error con el comprobante: ' . $res['error'], 'tipo' => 'danger']);
        }
        /* Registro central con transacción + idempotencia + historial. */
        $r = pago_registrar([
            'usuario_id'     => (int)$usuario['id'],
            'proyecto_id'    => $proyectoId,
            'tipo_pago'      => $tipo,
            'monto'          => $monto,
            'metodo_pago_id' => (int)($_POST['metodo_pago_id'] ?? 0),
            'comprobante'    => $comprobanteRuta,
            'clave_unica'    => trim((string)($_POST['clave_unica'] ?? '')),
            'notas'          => 'Pago de saldo para proyecto #' . $proyectoId,
        ]);
        if ($r['ok']) {
            responder([
                'ok'      => true,
                'mensaje' => $r['mensaje'],
                'tipo'    => !empty($r['ya_existia']) ? 'info' : 'success',
                'destino' => url_sitio('portal/pagos.php'),
            ]);
        }
        responder(['ok' => false, 'mensaje' => $r['mensaje'], 'tipo' => 'danger']);
    }

    if ($accion === 'pagar_producto') {
        $metodoId = (int)($_POST['metodo_pago_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        if ($monto <= 0) {
            responder(['ok' => false, 'mensaje' => 'Captura un monto válido.', 'tipo' => 'warning']);
        }
        /* Idempotencia del carrito: mismo token no duplica el pedido. */
        $claveProducto = trim((string)($_POST['clave_unica'] ?? ''));
        if ($claveProducto !== '') {
            $stK = $pdo->prepare('SELECT id FROM pagos WHERE clave_unica = ?');
            $stK->execute([$claveProducto]);
            if ($stK->fetch()) {
                responder(['ok' => true, 'mensaje' => 'Tu pedido ya fue registrado; no se duplicó.', 'tipo' => 'info', 'destino' => url_sitio('portal/pagos.php')]);
            }
        }
        if (empty($_FILES['comprobante']['name']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            responder(['ok' => false, 'mensaje' => 'Es obligatorio subir el comprobante de pago. Sin comprobante no podemos procesar tu pedido.', 'tipo' => 'warning']);
        }
        $comprobanteRuta = null;
        if (!empty($_FILES['comprobante']['name'])) {
            $res = subir_archivo('comprobante', 'comprobantes', ['jpg','jpeg','png','pdf','webp'], 8);
            if ($res['ok']) {
                $comprobanteRuta = $res['archivo'];
            } else {
                responder(['ok' => false, 'mensaje' => 'Error con el comprobante: ' . $res['error'], 'tipo' => 'danger']);
            }
        }
        try {
            $pdo->prepare('INSERT INTO pagos (usuario_id, solicitud_id, metodo_pago_id, monto, tipo_pago, comprobante, estado, notas, clave_unica) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([
                    (int)$usuario['id'],
                    null,
                    $metodoId > 0 ? $metodoId : null,
                    $monto,
                    'producto',
                    $comprobanteRuta,
                    'pendiente',
                    'Compra desde el carrito',
                    $claveProducto !== '' ? $claveProducto : null,
                ]);
            /* Vaciar el carrito del usuario después del pago */
            $pdo->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute([(int)$usuario['id']]);
            notificar((int)$usuario['id'], 'pago', 'Pedido registrado', 'Tu pago de $' . number_format($monto, 0) . ' MXN por productos fue registrado. Espera la confirmación del equipo.', url_sitio('portal/pagos.php'));
            notificar_admins('pago', 'Compra pendiente de confirmación', $usuario['nombre'] . ' realizó una compra por $' . number_format($monto, 0) . ' MXN desde el carrito.', url_sitio('admin/pagos.php'));
            responder(['ok' => true, 'mensaje' => 'Pago de productos registrado. Tu carrito se vació y tu pedido queda pendiente de confirmación.', 'tipo' => 'success', 'destino' => url_sitio('portal/pagos.php')]);
        } catch (PDOException $e) {
            /* Carrera de doble envío: el token ya se insertó.
               Se responde como ya registrado sin duplicar el pedido. */
            if ((string)$e->getCode() === '23000') {
                responder(['ok' => true, 'mensaje' => 'Tu pedido ya fue registrado; no se duplicó.', 'tipo' => 'info', 'destino' => url_sitio('portal/pagos.php')]);
            }
            responder(['ok' => false, 'mensaje' => 'No se pudo registrar el pedido: ' . $e->getMessage(), 'tipo' => 'danger']);
        }
    }
}

date_default_timezone_set('America/Hermosillo');

/* Pagos del usuario */
$stmt = $pdo->prepare('SELECT p.*, mp.nombre AS metodo_nombre, s.tipo_servicio
                       FROM pagos p
                       LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                       LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                       WHERE p.usuario_id = ?
                       ORDER BY p.creado_en DESC');
$stmt->execute([(int)$usuario['id']]);
$pagos = $stmt->fetchAll();

/* Proyectos con saldos (para pagar el restante) */
$stmtProj = $pdo->prepare('SELECT pi.*, s.tipo_servicio, s.presupuesto
                           FROM proyectos_inicio pi
                           LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                           WHERE pi.usuario_id = ? AND pi.estado IN ("anticipo_pendiente","en_desarrollo")
                             AND pi.saldo_restante > 0
                           ORDER BY pi.creado_en DESC');
$stmtProj->execute([(int)$usuario['id']]);
$proyectos = $stmtProj->fetchAll();

/* Total pagado por el usuario */
$totalPagado = 0.0;
foreach ($pagos as $pg) {
    if ($pg['estado'] === 'aprobado') {
        $totalPagado += (float)$pg['monto'];
    } elseif ($pg['estado'] === 'pendiente') {
        $totalPagado += (float)$pg['monto'];
    }
}

/* Métodos de pago activos */
$metodosPago = $pdo->query('SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY nombre ASC')->fetchAll();

$estadoPago = [
    'pendiente' => ['badge text-bg-warning', 'bi-clock', 'Pendiente'],
    'aprobado'  => ['badge text-bg-success', 'bi-check-circle', 'Aprobado'],
    'rechazado' => ['badge text-bg-danger',  'bi-x-circle', 'Rechazado'],
];
$tipoPago = [
    'anticipo' => ['Pago adelantado', 'bi-send'],
    'restante' => ['Saldo restante', 'bi-arrow-down-circle'],
    'completo' => ['Pago completo', 'bi-check2-circle'],
    'producto' => ['Productos / carrito', 'bi-bag'],
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Mis pagos</h4>
        <p class="text-muted mb-0">Lleva el control de tus anticipos, saldos e historial de pagos.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="facturacion" class="btn btn-outline-fv btn-sm"><i class="bi bi-receipt-cutoff me-1"></i>Facturación</a>
        <a href="procesos" class="btn btn-outline-fv btn-sm"><i class="bi bi-bezier2 me-1"></i>Mis procesos</a>
        <a href="carrito" class="btn btn-fv btn-sm"><i class="bi bi-cart3 me-1"></i>Mi carrito</a>
    </div>
</div>

<!-- Resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card portal-card border-0 shadow-sm h-100 p-3 text-center">
            <div class="portal-icon pb-2"><i class="bi bi-cash-stack text-success"></i></div>
            <b class="fs-4">$<?= number_format($totalPagado, 0) ?></b>
            <small class="text-muted">Total con proceso de pago</small>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <a href="#estado-pendiente" class="text-decoration-none">
            <div class="card portal-card border-0 shadow-sm h-100 p-3 text-center">
                <div class="portal-icon pb-2"><i class="bi bi-clock text-warning"></i></div>
                <b class="fs-4"><?= count(array_filter($pagos, fn($p) => $p['estado'] === 'pendiente')) ?></b>
                <small class="text-muted">Pagos pendientes</small>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="#saldo-pendiente" class="text-decoration-none">
            <div class="card portal-card border-0 shadow-sm h-100 p-3 text-center">
                <div class="portal-icon pb-2"><i class="bi bi-receipt text-primary"></i></div>
                <b class="fs-4"><?= count($proyectos) ?></b>
                <small class="text-muted">Proyectos con saldo</small>
            </div>
        </a>
    </div>
</div>

<?php if ($proyectos): ?>
    <!-- Saldos pendientes -->
    <div class="card portal-card border-0 shadow-sm mb-4" id="saldo-pendiente">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="bi bi-receipt me-1 text-primary"></i>Proyectos con saldo pendiente</b>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($proyectos as $proj): ?>
                    <?php
                    $total   = (float)($proj['total_proyecto'] ?? 0);
                    $pagado  = (float)($proj['pagado_total'] ?? 0);
                    $saldo   = (float)($proj['saldo_restante'] ?? 0);
                    $saldo   = max(0, $saldo);
                    $estado  = $proj['estado'] ?? '';
                    $esLiquidado = $estado === 'liquidado' || $saldo <= 0.01;
                    $claveSaldo = pago_generar_clave([
                        'proyecto' => (int)$proj['id'],
                        'usuario'  => (int)$usuario['id'],
                        'monto'    => $saldo,
                        'accion'   => 'pagar_saldo',
                        's'        => session_id(),
                    ]);
                    ?>
                    <div class="col-md-6">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <b><i class="bi bi-code-slash me-1 text-primary"></i><?= e($proj['tipo_servicio'] ?: 'Proyecto #' . (int)$proj['id']) ?></b>
                                    <?php if ($esLiquidado): ?>
                                        <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Liquidado / Finalizado</span>
                                    <?php elseif ($estado === 'en_desarrollo'): ?>
                                        <span class="badge text-bg-primary"><i class="bi bi-code-slash me-1"></i>En desarrollo</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning"><i class="bi bi-hourglass-split me-1"></i>Anticipo pendiente</span>
                                    <?php endif; ?>
                                </div>
                                <div class="row g-2 small mb-3">
                                    <div class="col-6">
                                        <span class="text-muted d-block">Cotización</span>
                                        <b><?= $total > 0 ? '$' . number_format($total, 0) . ' MXN' : e($proj['presupuesto'] ?: 'A convenir') ?></b>
                                    </div>
                                    <div class="col-6">
                                        <span class="text-muted d-block">Pagado aprobado</span>
                                        <b class="text-success">$<?= number_format($pagado, 0) ?> MXN</b>
                                    </div>
                                    <div class="col-12">
                                        <span class="text-muted d-block">Saldo restante</span>
                                        <b class="text-danger fs-5">$<?= number_format($saldo, 0) ?> MXN</b>
                                        <small class="text-muted d-block">El anticipo de $<?= number_format((float)$proj['anticipo_minimo'], 0) ?> MXN se descuenta del total de la cotización.</small>
                                    </div>
                                </div>
                                <?php if (!$esLiquidado): ?>
                                    <button class="btn btn-sm btn-fv w-100" data-bs-toggle="modal" data-bs-target="#modalSaldo<?= (int)$proj['id'] ?>"><i class="bi bi-credit-card me-1"></i>Pagar saldo</button>
                                <?php else: ?>
                                    <div class="text-center text-success small fw-semibold py-1">
                                        <i class="bi bi-check-circle me-1"></i>Proyecto liquidado sin saldo pendiente
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$esLiquidado): ?>
                    <!-- Modal de pago de saldo -->
                    <div class="modal fade" id="modalSaldo<?= (int)$proj['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" class="js-ajax" action="pagos.php" enctype="multipart/form-data">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="pagar_saldo">
                                    <input type="hidden" name="proyecto_id" value="<?= (int)$proj['id'] ?>">
                                    <input type="hidden" name="clave_unica" value="<?= e($claveSaldo) ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2 text-primary"></i>Pagar saldo del proyecto</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body">
                                        <?php if ($total > 0 && $saldo > 0): ?>
                                            <div class="alert alert-info small py-2">
                                                <i class="bi bi-info-circle me-1"></i>Cotización: <b>$<?= number_format($total, 0) ?> MXN</b> · Pagado aprobado: <b>$<?= number_format($pagado, 0) ?> MXN</b>. Saldo a cubrir: <b>$<?= number_format($saldo, 0) ?> MXN</b>.
                                            </div>
                                        <?php endif; ?>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Tipo de pago</label>
                                                <select name="tipo_pago" class="form-select">
                                                    <option value="restante">Saldo restante</option>
                                                    <option value="completo">Pago completo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold">Monto (MXN)</label>
                                                <input type="number" name="monto" class="form-control" min="1" step="0.01" value="<?= $saldo > 0 ? number_format($saldo, 2, '.', '') : '' ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold">Método de pago</label>
                                                <select name="metodo_pago_id" class="form-select metodo-pago-multi" required>
                                                    <option value="">Selecciona un método...</option>
                                                    <?php foreach ($metodosPago as $mp): ?>
                                                        <option value="<?= (int)$mp['id'] ?>" data-nombre="<?= e($mp['nombre']) ?>" data-descripcion="<?= e($mp['descripcion'] ?? '') ?>" data-detalles="<?= e($mp['detalles_cuenta'] ?? '') ?>" data-instrucciones="<?= e($mp['instrucciones'] ?? '') ?>" data-icono="<?= e($mp['icono'] ?? 'bi-credit-card') ?>"><?= e($mp['nombre']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="metodo-info d-none mt-2">
                                                    <div class="border rounded p-2 bg-light small">
                                                        <b class="d-block metodo-info-nombre"></b>
                                                        <span class="text-muted fw-semibold d-block metodo-info-detalles"></span>
                                                        <span class="text-muted d-block metodo-info-instrucciones"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold">Comprobante de pago (obligatorio) *</label>
                                                <input type="file" name="comprobante" class="form-control" accept="image/*,.pdf" required>
                                                <small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Tu pago no será aprobado hasta que subas el comprobante.</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-fv"><i class="bi bi-send me-1"></i>Registrar pago</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Historial de pagos -->
<div class="card portal-card border-0 shadow-sm mb-4" id="estado-pendiente">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <b><i class="bi bi-clock-history me-1 text-primary"></i>Historial de pagos</b>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge text-bg-light"><?= count($pagos) ?> registros</span>
            <a href="pagos.php?export=csv" class="btn btn-sm btn-outline-fv"><i class="bi bi-download me-1"></i>CSV</a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!$pagos): ?>
            <p class="text-center text-muted py-4 mb-0">Aún no has registrado pagos. Cuando hagas un anticipo o pagues un saldo aparecerá aquí.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover tabla-admin mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Folio</th>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Comprobante</th>
                            <th class="text-end pe-3">Recibo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pg): [$pBg, $pIco, $pTxt] = $estadoPago[$pg['estado']] ?? ['badge text-bg-light', 'bi-question', ucfirst($pg['estado'])]; [$tpTxt, $tpIco] = $tipoPago[$pg['tipo_pago']] ?? [ucfirst($pg['tipo_pago']), 'bi-receipt']; ?>
                            <tr>
                                <td class="ps-3 small fw-semibold"><?= e('PAG-' . str_pad((string)$pg['id'], 5, '0', STR_PAD_LEFT)) ?></td>
                                <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime($pg['creado_en']))) ?></td>
                                <td class="small"><?= e($pg['tipo_servicio'] ?: 'Compra de productos') ?><?= $pg['notas'] ? '<br><small class="text-muted">' . e(mb_strimwidth($pg['notas'], 0, 60, '…')) . '</small>' : '' ?></td>
                                <td class="small"><?= e($pg['metodo_nombre'] ?: '—') ?></td>
                                <td class="fw-semibold">$<?= number_format((float)$pg['monto'], 0) ?> MXN</td>
                                <td><span class="<?= $pBg ?>"><i class="bi <?= $pIco ?> me-1"></i><?= $pTxt ?></span></td>
                                <td class="text-end pe-3">
                                    <?php if ($pg['comprobante']): ?>
                                        <a href="../<?= e($pg['comprobante']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver comprobante"><i class="bi bi-file-earmark-arrow-down"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <?php if ($pg['estado'] === 'aprobado'): ?>
                                        <a href="recibo.php?id=<?= (int)$pg['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success" title="Descargar recibo"><i class="bi bi-receipt"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small" title="El recibo se habilita al aprobarse el pago">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Métodos de pago publicados -->
<div class="card portal-card border-0 shadow-sm">
    <div class="card-header bg-white"><b><i class="bi bi-wallet2 me-1 text-primary"></i>Métodos de pago disponibles</b></div>
    <div class="card-body">
        <?php if (!$metodosPago): ?>
            <p class="text-muted small mb-0 text-center py-3">No hay métodos de pago publicados por ahora.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($metodosPago as $mp): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card border-0 bg-light h-100 text-center p-3">
                            <div class="portal-icon pb-2"><i class="bi <?= e($mp['icono'] ?: 'bi-credit-card') ?> text-primary"></i></div>
                            <b class="mb-1"><?= e($mp['nombre']) ?></b>
                            <?php if ($mp['descripcion']): ?><span class="small text-muted mb-2"><?= e(mb_strimwidth($mp['descripcion'], 0, 80, '…')) ?></span><?php endif; ?>
                            <?php if ($mp['detalles_cuenta']): ?>
                                <span class="small d-block mb-1"><i class="bi bi-info-circle me-1"></i><?= e($mp['detalles_cuenta']) ?></span>
                            <?php endif; ?>
                            <?php if ($mp['instrucciones']): ?>
                                <span class="small text-muted d-block mb-2"><?= e(mb_strimwidth($mp['instrucciones'], 0, 90, '…')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Mostrar los datos de pago al elegir un método en cualquier modal
document.querySelectorAll('.metodo-pago-multi')?.forEach(function (sel) {
    sel.addEventListener('change', function () {
        var info = this.closest('.col-12').querySelector('.metodo-info');
        if (!info) return;
        var opt = this.options[this.selectedIndex];
        if (!opt || !opt.value) { info.classList.add('d-none'); return; }
        info.querySelector('.metodo-info-nombre').textContent = opt.dataset.nombre || '';
        info.querySelector('.metodo-info-detalles').textContent = opt.dataset.detalles || '';
        info.querySelector('.metodo-info-instrucciones').textContent = opt.dataset.instrucciones || '';
        info.classList.remove('d-none');
    });
});
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>