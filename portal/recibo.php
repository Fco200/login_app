<?php
/* Recibo / comprobante de pago (imprimible → "Guardar como PDF").
   Visible para el cliente dueño del pago y para los administradores. */
require_once __DIR__ . '/../funciones.php';

iniciar_sesion_segura();

$id = (int)($_GET['id'] ?? 0);
$pago = null;
if ($id > 0) {
    $stmt = $GLOBALS['pdo']->prepare('SELECT p.*, mp.nombre AS metodo_nombre, u.nombre AS cliente_nombre, u.email AS cliente_email,
                                             s.tipo_servicio AS concepto
                                      FROM pagos p
                                      LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                                      LEFT JOIN usuarios u ON p.usuario_id = u.id
                                      LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                                      WHERE p.id = ?');
    $stmt->execute([$id]);
    $pago = $stmt->fetch();
}

$esAdmin = !empty($_SESSION['admin_id']);
$esDueno = $pago && esta_logueado() && (int)$pago['usuario_id'] === (int)$_SESSION['usuario_id'];

if (!$pago) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Recibo no encontrado</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
        . '<body class="bg-light"><div class="container py-5 text-center"><h4>Recibo no encontrado</h4>'
        . '<a href="' . e(url_sitio($esAdmin ? 'admin/pagos.php' : 'portal/pagos.php')) . '" class="btn btn-fv mt-3">Volver</a></div></body></html>';
    exit;
}

if (!$esAdmin && !$esDueno) {
    redirigir('iniciar-sesion.php');
}

$datosSitio = datos_sitio();

$folio = 'PAG-' . str_pad((string)$pago['id'], 5, '0', STR_PAD_LEFT);
$monto = '$' . number_format((float)$pago['monto'], 2) . ' MXN';
$estadoTxt = [ 'aprobado' => 'Pagado', 'rechazado' => 'Rechazado', 'pendiente' => 'Pendiente' ][$pago['estado']] ?? $pago['estado'];
$claseEstado = $pago['estado'] === 'aprobado' ? 'ok' : ($pago['estado'] === 'rechazado' ? 'no' : 'pen');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recibo <?= e($folio) ?> — <?= e(SITE_NOMBRE) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #dfe7f3; font-family: Arial, Helvetica, sans-serif; color: #1c2b45; padding: 28px 12px; }
    .hoja { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(7,28,61,.15); overflow: hidden; }
    .encabezado { background: #071c3d; color: #fff; padding: 24px 34px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
    .encabezado .marca { display: flex; align-items: center; gap: 14px; }
    .encabezado .marca .logo-recibo { height: 48px; width: auto; object-fit: contain; }
    .encabezado .marca b { font-size: 20px; letter-spacing: .5px; display: block; }
    .encabezado .marca small { color: #9db8e8; display: block; }
    .folio { text-align: right; }
    .folio span { display: block; color: #9db8e8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
    .folio b { font-size: 20px; color: #fff; }
    .cuerpo { padding: 30px 34px; }
    .estado { display: inline-block; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
    .estado.ok { background: #e6f7ee; color: #1b7a43; }
    .estado.no { background: #fdecec; color: #c0392b; }
    .estado.pen { background: #fff4e0; color: #a06a00; }
    h1 { font-size: 22px; margin: 4px 0 6px; }
    .sub { color: #8a97ad; font-size: 13px; }
    table { width: 100%; border-collapse: collapse; margin-top: 22px; }
    td { padding: 9px 10px; font-size: 14px; vertical-align: top; }
    td.et { color: #8a97ad; width: 34%; }
    tr.linea td { border-top: 1px dashed #e3eaf5; }
    .total td { font-size: 18px; font-weight: bold; background: #f4f8ff; }
    .firma { margin-top: 26px; padding-top: 14px; border-top: 1px solid #e3eaf5; color: #8a97ad; font-size: 12px; }
    .barra { padding: 16px 34px; background: #f4f8ff; display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center; }
    .barra .acciones { display: flex; gap: 8px; }
    .btn { display: inline-block; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
    .btn-prim { background: #0a3d8f; color: #fff; }
    .btn-sec { background: #fff; color: #0a3d8f; border: 1px solid #c5d4e8; }
    .nota { margin-top: 16px; background: #fff8ec; border-left: 4px solid #f0b429; padding: 10px 14px; border-radius: 6px; font-size: 13px; }
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
            <?php $logoRecibo = logo_sitio(); ?>
            <?php if ($logoRecibo): ?><img src="<?= e($logoRecibo) ?>" alt="" class="logo-recibo" style="height:48px;width:auto;object-fit:contain;filter:drop-shadow(0 0 0 transparent);"><?php endif; ?>
            <div>
                <b><?= e(SITE_NOMBRE) ?></b>
                <small><?= e(SITE_ESLOGAN) ?></small>
            </div>
        </div>
        <div class="folio">
            <span>Folio</span>
            <b><?= e($folio) ?></b>
            <small style="display:block;color:#9db8e8;font-size:12px;margin-top:2px;"><?= e(SITE_DIRECCION) ?></small>
        </div>
    </div>

    <div class="cuerpo">
        <h1>Recibo de pago <span class="estado <?= $claseEstado ?>"><?= e($estadoTxt) ?></span></h1>
        <p class="sub">Emitido el <?= e(date('d/m/Y', strtotime($pago['creado_en']))) ?> a las <?= e(date('H:i', strtotime($pago['creado_en']))) ?> · <?= e(SITE_DIRECCION) ?></p>

        <table>
            <tr><td class="et">Cliente</td><td><b><?= e($pago['cliente_nombre'] ?: 'Cliente registrado') ?></b></td></tr>
            <tr><td class="et">Correo</td><td><?= e($pago['cliente_email'] ?: '—') ?></td></tr>
            <tr class="linea"><td class="et">Concepto</td><td><?= e($pago['concepto'] ?: 'Compra de productos') ?></td></tr>
            <tr><td class="et">Tipo de pago</td><td class="text-capitalize"><?= e($pago['tipo_pago']) ?></td></tr>
            <tr><td class="et">Método de pago</td><td><?= e($pago['metodo_nombre'] ?: '—') ?></td></tr>
            <?php if ($pago['comprobante']): ?>
                <tr><td class="et">Comprobante</td><td>Anexo adjunto en el sistema</td></tr>
            <?php endif; ?>
            <tr class="linea total"><td class="et">Total</td><td><?= e($monto) ?></td></tr>
        </table>

        <?php if ($pago['notas']): ?>
            <div class="nota"><b>Nota:</b> <?= e($pago['notas']) ?></div>
        <?php endif; ?>

        <div class="firma">
            Gracias por tu confianza. Para dudas sobre este pago escríbenos a
            <?= e($datosSitio['email'] ?? SITE_EMAIL) ?> o visita tu portal de clientes.
        </div>
    </div>

    <div class="barra">
        <span class="sub">Correo: <?= e($datosSitio['email'] ?? SITE_EMAIL) ?> · <?= e($datosSitio['telefono'] ?? SITE_TELEFONO) ?></span>
        <div class="acciones">
            <button class="btn btn-sec" onclick="history.back()"><i class="bi bi-arrow-left"></i> Volver</button>
            <button class="btn btn-prim" onclick="window.print()">Imprimir / Guardar PDF</button>
        </div>
    </div>

</div>
<script>
window.onload = function () {
    var t = new URLSearchParams(location.search).get('imprimir');
    if (t === '1') setTimeout(function () { window.print(); }, 350);
};
</script>
</body>
</html>