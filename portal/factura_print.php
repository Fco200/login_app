<?php
/* FV DIGITAL - Vista imprimible de la factura (con la marca).
   Misma información que el PDF: se abre en una pestaña nueva y permite
   Imprimir / Guardar como PDF desde el navegador. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de factura inválido.');
}
$f = factura_datos($id);
if (!$f) {
    http_response_code(404);
    exit('Factura no encontrada.');
}
$esAdmin = esta_admin();
$usuario = sesion_actual();
if (!$esAdmin && (!$usuario || (int)$usuario['id'] !== (int)$f['usuario_id'])) {
    http_response_code(403);
    exit('No autorizado.');
}

$pagos = json_decode((string)($f['pagos_json'] ?? '[]'), true);
$pagos = is_array($pagos) ? $pagos : [];

$direccionCliente = '';
if ($f['usuario_id']) {
    $stU = $GLOBALS['pdo']->prepare('SELECT direccion FROM usuarios WHERE id = ?');
    $stU->execute([(int)$f['usuario_id']]);
    $direccionCliente = (string)($stU->fetchColumn() ?: '');
}

$e = datos_emisor();
$subtotal = (float)$f['subtotal'];
$iva = (float)$f['iva'];
$total = (float)$f['total'];
$ivaPct = round($subtotal > 0 ? $iva / $subtotal * 100 : 16);

$folio = (string)$f['folio'];
$fecha = date('d/m/Y', strtotime((string)$f['creado_en']));
$concepto = (string)$f['concepto'];
$rfcCliente = (string)($f['rfq_cliente'] ?? '');
$cliente = (string)$f['cliente_nombre'];
$emailCliente = (string)$f['cliente_email'];
$solicitud = $f['solicitud_id'] !== null ? (int)$f['solicitud_id'] : 0;
$autoImprimir = ($_GET['imprimir'] ?? '') === '1';

function mxn($n): string {
    return '$' . number_format((float)$n, 2) . ' MXN';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?= e($folio) ?> — <?= e($e['nombre']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #dfe7f3; font-family: Arial, Helvetica, sans-serif; color: #1c2b45; padding: 24px 12px; }
        .hoja { max-width: 840px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(7,28,61,.15); overflow: hidden; }
        .encabezado { background: #071c3d; padding: 26px 34px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
        .marca { display: flex; align-items: center; gap: 14px; color: #fff; }
        .marca img { height: 52px; width: auto; object-fit: contain; }
        .marca b { font-size: 21px; letter-spacing: .5px; display: block; }
        .marca span { color: #9db8e8; font-size: 12px; display: block; }
        .folio { text-align: right; color: #fff; }
        .folio span { display: block; color: #9db8e8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .folio b { font-size: 24px; }
        .folio small { display: block; color: #9db8e8; font-size: 12px; margin-top: 2px; }
        .cuerpo { padding: 30px 34px; }
        .bloques { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 22px; }
        .bloque { border: 1px solid #e3eaf5; border-left: 4px solid #0b5ed7; border-radius: 12px; padding: 16px; background: #f8fbff; }
        .bloque h6 { font-size: 12px; font-weight: 700; color: #0b5ed7; letter-spacing: .6px; margin: 0 0 12px; text-transform: uppercase; }
        .bloque div { margin-bottom: 9px; }
        .bloque span { display: block; font-size: 11px; color: #8a97ad; }
        .bloque b { font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        thead th { background: #0a3d8f; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; padding: 10px 12px; text-align: left; }
        thead th.monto { text-align: right; }
        tbody td { border-bottom: 1px dashed #e3eaf5; padding: 10px 12px; font-size: 14px; }
        tbody tr:nth-child(even) { background: #f4f8ff; }
        td.monto, td.total { text-align: right; font-weight: 600; }
        .totales { margin-left: auto; width: 300px; }
        .total-fila { display: flex; justify-content: space-between; padding: 8px 14px; font-size: 14px; color: #345; }
        .total-final { background: #071c3d; color: #fff; padding: 12px 14px; border-radius: 10px; display: flex; justify-content: space-between; font-size: 17px; font-weight: 700; margin-top: 4px; }
        .concepto-nota { color: #8a97ad; font-size: 12px; margin-top: 10px; }
        .pie { margin-top: 24px; border-top: 1px solid #e3eaf5; padding-top: 12px; color: #8a97ad; font-size: 12px; text-align: center; }
        .barra { padding: 16px 34px; background: #f4f8ff; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center; }
        .barra .acciones { display: flex; gap: 8px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
        .btn-prim { background: #0a3d8f; color: #fff; }
        .btn-sec { background: #fff; color: #0a3d8f; border: 1px solid #c5d4e8; }
        @media print {
            body { background: #fff; padding: 0; }
            .hoja { box-shadow: none; border-radius: 0; max-width: none; }
            .barra { display: none; }
        }
    </style>
</head>
<body>
<div class="hoja">
    <div class="encabezado">
        <div class="marca">
            <?php $logo = logo_sitio(); ?>
            <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
            <div>
                <b><?= e($e['nombre']) ?></b>
                <span><?= e($e['eslogan']) ?></span>
            </div>
        </div>
        <div class="folio">
            <span>Factura</span>
            <b><?= e($folio) ?></b>
            <small>Fecha: <?= e($fecha) ?><?= $solicitud ? ' · Nº solicitud: ' . e((string)$solicitud) : '' ?></small>
        </div>
    </div>

    <div class="cuerpo">
        <div class="bloques">
            <div class="bloque">
                <h6>Datos del cliente</h6>
                <div><span>Cliente</span><b><?= e($cliente ?: '—') ?></b></div>
                <div><span>RFC</span><b><?= e($rfcCliente ?: '—') ?></b></div>
                <div><span>Correo</span><b><?= e($emailCliente ?: '—') ?></b></div>
                <div><span>Dirección</span><b><?= e($direccionCliente ?: '—') ?></b></div>
                <div><span>Concepto</span><b><?= e($concepto ?: 'Desarrollo de proyecto') ?></b></div>
            </div>
            <div class="bloque">
                <h6>Datos del emisor</h6>
                <div><span>Empresa</span><b><?= e($e['nombre']) ?></b></div>
                <?php if ($e['razon_social'] !== ''): ?><div><span>Razón social</span><b><?= e($e['razon_social']) ?></b></div><?php endif; ?>
                <?php if ($e['rfc'] !== ''): ?><div><span>RFC</span><b><?= e($e['rfc']) ?></b></div><?php endif; ?>
                <div><span>Dirección</span><b><?= e($e['direccion']) ?></b></div>
                <div><span>Teléfono</span><b><?= e($e['telefono']) ?></b></div>
                <div><span>Correo</span><b><?= e($e['email']) ?></b></div>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Folio de pago</th><th>Fecha</th><th>Tipo</th><th class="monto">Monto</th></tr>
            </thead>
            <tbody>
                <?php if (!$pagos): ?>
                    <tr><td class="text-muted">Pagos aprobados del proyecto</td><td></td><td></td><td class="monto"><?= mxn($subtotal) ?></td></tr>
                <?php else: foreach ($pagos as $p): ?>
                    <tr>
                        <td><b><?= e((string)($p['folio'] ?? 'PAG-00000')) ?></b></td>
                        <td><?= e((string)($p['fecha'] ?? '—')) ?></td>
                        <td class="text-capitalize"><?= e((string)($p['tipo'] ?? 'Pago')) ?></td>
                        <td class="monto"><?= mxn($p['monto'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="total-fila"><span>Subtotal</span><b><?= mxn($subtotal) ?></b></div>
            <div class="total-fila"><span>IVA (<?= (int)$ivaPct ?>%)</span><b><?= mxn($iva) ?></b></div>
            <div class="total-final"><span>TOTAL</span><b><?= mxn($total) ?></b></div>
        </div>

        <div class="concepto-nota">Documento generado por <?= e($e['nombre']) ?> para el proyecto registrado en su portal. Los datos fiscales del emisor se muestran conforme a la configuración del panel.</div>
    </div>

    <div class="pie"><?= e($e['nombre']) ?> · <?= e($e['eslogan']) ?> · <?= e($e['direccion']) ?></div>

    <div class="barra">
        <span class="small text-muted">Correo: <?= e($e['email']) ?> · Tel: <?= e($e['telefono']) ?></span>
        <div class="acciones">
            <a href="#" onclick="history.back();return false;" class="btn btn-sec"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="factura_pdf.php?id=<?= (int)$f['id'] ?>" class="btn btn-sec"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
            <button class="btn btn-prim" onclick="window.print()">Imprimir / Guardar PDF</button>
        </div>
    </div>
</div>
<script>
window.onload = function () {
    <?php if ($autoImprimir): ?>
        setTimeout(function () { window.print(); }, 350);
    <?php endif; ?>
};
</script>
</body>
</html>