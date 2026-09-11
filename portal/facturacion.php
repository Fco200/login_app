<?php
$seccionPortal = 'facturacion';
$titulo = 'Facturación';
require_once __DIR__ . '/includes/cabecera.php';

date_default_timezone_set('America/Hermosillo');

$usuario = sesion_actual() ?? ['id' => (int)$_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'] ?? '', 'email' => '', 'rfc' => '', 'direccion' => ''];

/* ---------- Solicitar factura ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificar_csrf()) {
    if (($_POST['accion'] ?? '') === 'solicitar_factura') {
        $proyectoId = (int)($_POST['proyecto_id'] ?? 0);
        $rfc = strtoupper(trim((string)($_POST['rfc'] ?? '')));
        $direccion = trim((string)($_POST['direccion'] ?? ''));
        $concepto = trim((string)($_POST['concepto'] ?? ''));

        $st = $pdo->prepare('SELECT id, estado, total_proyecto, saldo_restante FROM proyectos_inicio WHERE id = ? AND usuario_id = ?');
        $st->execute([$proyectoId, (int)$usuario['id']]);
        $proy = $st->fetch();
        if (!$proy) {
            responder(['ok' => false, 'mensaje' => 'El proyecto no existe o no te pertenece.', 'tipo' => 'danger']);
        }
        $liquidable = in_array($proy['estado'], ['liquidado', 'completado'], true)
            || ((float)$proy['total_proyecto'] > 0 && (float)$proy['saldo_restante'] <= 0.01);
        if (!$liquidable) {
            responder(['ok' => false, 'mensaje' => 'El proyecto debe estar liquidado o completado para solicitar su factura.', 'tipo' => 'warning']);
        }
        $pagos = proyecto_pagos_aprobados_detalle($proyectoId);
        if (!$pagos) {
            responder(['ok' => false, 'mensaje' => 'Aún no hay pagos aprobados para facturar.', 'tipo' => 'warning']);
        }

        /* Guardar datos fiscales en el perfil del cliente. */
        $pdo->prepare('UPDATE usuarios SET rfc = ?, direccion = ? WHERE id = ?')
            ->execute([$rfc !== '' ? $rfc : null, $direccion !== '' ? $direccion : null, (int)$usuario['id']]);

        $r = factura_crear([
            'proyecto_id' => $proyectoId,
            'usuario_id'  => (int)$usuario['id'],
            'concepto'    => $concepto !== '' ? $concepto : 'Desarrollo de proyecto',
            'rfc_cliente' => $rfc,
            'emitida_por' => (string)($_POST['emitida_por'] ?? $_SESSION['nombre'] ?? 'Cliente vía portal'),
            'pagos'       => $pagos,
        ]);
        responder([
            'ok'      => $r['ok'],
            'mensaje' => $r['mensaje'],
            'tipo'    => $r['ok'] ? 'success' : 'danger',
            'destino' => $r['ok'] ? url_sitio('portal/facturacion.php') : '',
        ]);
    }
}

/* Facturas emitidas para este cliente */
$facturas = $pdo->prepare('SELECT f.*, s.tipo_servicio, s.presupuesto
                           FROM facturas f
                           LEFT JOIN proyectos_inicio pi ON f.proyecto_id = pi.id
                           LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                           WHERE f.usuario_id = ?
                           ORDER BY f.creado_en DESC');
$facturas->execute([(int)$usuario['id']]);
$facturas = $facturas->fetchAll();

/* Proyectos facturables (liquidados o con pagos aprobados) */
$proyectosFact = [];
$stmtP = $pdo->prepare('SELECT pi.*, s.tipo_servicio, s.presupuesto
                        FROM proyectos_inicio pi
                        LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                        WHERE pi.usuario_id = ?
                        ORDER BY pi.creado_en DESC');
$stmtP->execute([(int)$usuario['id']]);
foreach ($stmtP->fetchAll() as $pr) {
    $liquidable = in_array($pr['estado'], ['liquidado', 'completado'], true)
        || ((float)$pr['total_proyecto'] > 0 && (float)$pr['saldo_restante'] <= 0.01);
    if (!$liquidable) {
        continue;
    }
    $pagosProv = proyecto_pagos_aprobados_detalle((int)$pr['id']);
    if (!$pagosProv) {
        continue;
    }
    $pr['_pagos'] = $pagosProv;
    $pr['_subtotal'] = array_sum(array_map(fn($pp) => (float)$pp['monto'], $pagosProv));
    $proyectosFact[] = $pr;
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-1">Facturación</h4>
        <p class="text-muted mb-0">Solicita y descarga las facturas de tus proyectos terminados.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="pagos" class="btn btn-outline-fv btn-sm"><i class="bi bi-credit-card me-1"></i>Mis pagos</a>
        <a href="procesos" class="btn btn-fv btn-sm"><i class="bi bi-bezier2 me-1"></i>Mis procesos</a>
    </div>
</div>

<?php if (!$facturas && !$proyectosFact): ?>
    <div class="card portal-card border-0 shadow-sm p-5 text-center">
        <i class="bi bi-receipt-cutoff fs-1 text-primary d-block mb-3"></i>
        <h5>Aún no tienes facturas</h5>
        <p class="text-muted mb-0">Cuando un proyecto esté liquidado o completado podrás solicitar su factura desde aquí.</p>
    </div>
<?php else: ?>

    <?php if ($proyectosFact): ?>
        <!-- Autocompletado inteligente: el cliente elige el proyecto y se llenan los datos -->
        <div class="card portal-card border-0 shadow-sm mb-4" id="autocompletado">
            <div class="card-header bg-white">
                <b><i class="bi bi-magic me-1 text-primary"></i>Autocompletado inteligente</b>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Selecciona tu proyecto liquidado y llenaremos tu factura automáticamente con tus datos fiscales,
                    el desglose de los pagos realizados, el IVA y el total.
                </p>
                <select id="proyectoFactura" class="form-select form-select-lg mb-3" aria-label="Proyecto a facturar">
                    <option value="">— Selecciona un proyecto liquidado —</option>
                    <?php foreach ($proyectosFact as $pr): ?>
                        <option value="<?= (int)$pr['id'] ?>">
                            <?= e($pr['tipo_servicio'] ?: 'Proyecto #' . (int)$pr['id']) ?> · Total a facturar: $<?= number_format((float)$pr['_subtotal'] * 1.16, 2) ?> MXN
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="facturaPreview" class="d-none">
                    <form method="POST" action="facturacion.php" id="formFacturaAut">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="solicitar_factura">
                        <input type="hidden" name="proyecto_id" id="fp_proyecto_id" value="">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Cliente</label>
                                <input id="fp_cliente" class="form-control" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Correo</label>
                                <input id="fp_email" class="form-control" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">RFC (factura fiscal)</label>
                                <input type="text" name="rfc" id="fp_rfc" class="form-control" maxlength="20" placeholder="AAA010101AAA">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Dirección fiscal</label>
                                <input type="text" name="direccion" id="fp_direccion" class="form-control" maxlength="255" placeholder="Calle, número, colonia, CP, ciudad">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Concepto</label>
                                <input type="text" name="concepto" id="fp_concepto" class="form-control" maxlength="255">
                            </div>
                        </div>

                        <h6 class="small fw-bold text-uppercase text-muted"><i class="bi bi-clock-history me-1"></i>Desglose de pagos realizados</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm tabla-admin mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3">Folio</th><th>Fecha</th><th>Tipo</th><th class="text-end pe-3">Monto</th></tr>
                                </thead>
                                <tbody id="fp_desglose"></tbody>
                            </table>
                        </div>

                        <div class="row g-2 justify-content-end mb-3">
                            <div class="col-auto text-end small">
                                <div>Subtotal: <b id="fp_subtotal">$0.00 MXN</b></div>
                                <div>IVA (16%): <b id="fp_iva">$0.00 MXN</b></div>
                                <div class="text-primary" style="font-size:1.25rem;">Total: <b id="fp_total">$0.00 MXN</b></div>
                                <div id="fp_estado" class="mt-1"></div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" id="btnEmitirFactura" class="btn btn-fv"><i class="bi bi-file-earmark-check me-1"></i>Solicitar / emitir factura</button>
                            <button type="button" id="btnLimpiarPreview" class="btn btn-outline-fv"><i class="bi bi-x-lg me-1"></i>Limpiar</button>
                        </div>
                    </form>
                </div>

                <div id="facturaPreviewVacio" class="text-center text-muted py-4 d-none">
                    <i class="bi bi-receipt-cutoff fs-3 d-block mb-2"></i>Selecciona un proyecto para autocompletar la factura.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Facturas emitidas -->
    <div class="card portal-card border-0 shadow-sm">
        <div class="card-header bg-white"><b><i class="bi bi-journal-richtext me-1 text-primary"></i>Mis facturas</b></div>
        <div class="card-body p-0">
            <?php if (!$facturas): ?>
                <p class="text-center text-muted py-4 mb-0">Cuando solicites una factura, aparecerá aquí lista para descargar.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover tabla-admin mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Folio</th>
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th>RFC</th>
                                <th>Subtotal</th>
                                <th>IVA</th>
                                <th>Total</th>
                                <th class="text-end pe-3">PDF</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($facturas as $f): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold"><?= e($f['folio']) ?></td>
                                    <td class="small text-muted"><?= e(date('d/m/Y', strtotime($f['creado_en']))) ?></td>
                                    <td class="small"><?= e($f['concepto']) ?></td>
                                    <td class="small"><?= e($f['rfq_cliente'] ?: '—') ?></td>
                                    <td>$<?= number_format((float)$f['subtotal'], 2) ?></td>
                                    <td>$<?= number_format((float)$f['iva'], 2) ?></td>
                                    <td class="fw-semibold">$<?= number_format((float)$f['total'], 2) ?></td>
                                    <td class="text-end pe-3">
                                        <a href="factura_pdf.php?id=<?= (int)$f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Descargar PDF"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function fmtMXN(n) {
    return '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' MXN';
}

/* ---------- Autocompletado inteligente: selector de proyecto ---------- */
var selProyecto = document.getElementById('proyectoFactura');
var previewFact = document.getElementById('facturaPreview');

if (selProyecto) {
    selProyecto.addEventListener('change', function () {
        var id = parseInt(this.value || '0', 10);
        if (id > 0) {
            cargarProyectoFactura(id);
        } else {
            resetPreview();
        }
    });
}

var btnLimpiar = document.getElementById('btnLimpiarPreview');
if (btnLimpiar) {
    btnLimpiar.addEventListener('click', function () {
        selProyecto.value = '';
        resetPreview();
    });
}

function resetPreview() {
    if (!previewFact) return;
    previewFact.classList.add('d-none');
    var vacio = document.getElementById('facturaPreviewVacio');
    if (vacio) vacio.classList.remove('d-none');
}

function cargarProyectoFactura(id) {
    fetch('facturas_api.php?proyecto_id=' + id, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.ok) {
                if (window.Swal) { Swal.fire({ icon: 'warning', title: 'Ups', text: d.mensaje || 'No se pudo cargar el proyecto.' }); }
                resetPreview();
                return;
            }
            var p = d.proyecto;
            var c = d.cliente;
            document.getElementById('fp_proyecto_id').value = p.id;
            document.getElementById('fp_cliente').value = c.nombre || 'Cliente';
            document.getElementById('fp_email').value = c.email || '';
            document.getElementById('fp_rfc').value = c.rfc || '';
            document.getElementById('fp_direccion').value = c.direccion || '';
            document.getElementById('fp_concepto').value = 'Desarrollo de proyecto' + (p.concepto ? ' ' + p.concepto : '');

            var tb = document.getElementById('fp_desglose');
            tb.innerHTML = '';
            (d.pagos || []).forEach(function (pg) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td class="ps-3 small fw-semibold">' + (pg.folio || 'PAG') + '</td>'
                    + '<td class="small text-muted">' + (pg.fecha || '') + '</td>'
                    + '<td class="small text-capitalize">' + (pg.tipo || '') + '</td>'
                    + '<td class="text-end pe-3 fw-semibold">' + fmtMXN(pg.monto) + '</td>';
                tb.appendChild(tr);
            });

            document.getElementById('fp_subtotal').textContent = fmtMXN(d.subtotal);
            document.getElementById('fp_iva').textContent = fmtMXN(d.iva);
            document.getElementById('fp_total').textContent = fmtMXN(d.total);

            var est = document.getElementById('fp_estado');
            est.innerHTML = p.liquidado
                ? '<span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Liquidado / Finalizado</span>'
                : '<span class="badge text-bg-warning"><i class="bi bi-hourglass-split me-1"></i>Saldo pendiente</span>';

            previewFact.classList.remove('d-none');
            if (window.Swal && previewFact.dataset.notalistado !== '1') {
                previewFact.dataset.notalistado = '1';
            }
        })
        .catch(function () {
            if (window.Swal) { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el proyecto.' }); }
            resetPreview();
        });
}

/* ---------- Emisión con confirmación SweetAlert2 ---------- */
var btnEmitir = document.getElementById('btnEmitirFactura');
if (btnEmitir) {
    btnEmitir.addEventListener('click', function () {
        var form = document.getElementById('formFacturaAut');
        var pid = parseInt(document.getElementById('fp_proyecto_id').value || '0', 10);
        if (pid <= 0) {
            if (window.Swal) { Swal.fire({ icon: 'info', title: 'Selecciona un proyecto', text: 'Elige primero tu proyecto liquidado en el selector.' }); }
            return;
        }
        Swal.fire({
            title: '¿Emitir la factura?',
            text: 'Se generará la factura con el desglose de pagos aprobados y se te notificará.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Sí, emitir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0a3d8f'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            btnEmitir.disabled = true;
            var fd = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(function (resp) { return resp.json(); })
            .then(function (d) {
                btnEmitir.disabled = false;
                if (d.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Factura emitida!',
                        text: d.mensaje || 'Tu factura quedó lista para descargar.',
                        confirmButtonColor: '#0a3d8f'
                    }).then(function () { location.href = d.destino || 'facturacion'; });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: d.mensaje || 'No se pudo emitir la factura.' });
                }
            })
            .catch(function () {
                btnEmitir.disabled = false;
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo emitir la factura.' });
            });
        });
    });
}
</script>

<?php require_once __DIR__ . '/includes/pie.php'; ?>