<?php
/* FV DIGITAL - Recibo de pago en PDF (generado 100% en el servidor).
   Accesible para el cliente dueño del pago o administradores. */
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/../lib/pdf.php';

iniciar_sesion_segura();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de pago inválido.');
}

$st = $GLOBALS['pdo']->prepare('SELECT p.*, mp.nombre AS metodo_nombre, u.nombre AS cliente_nombre, u.email AS cliente_email,
                                       s.tipo_servicio AS concepto
                                FROM pagos p
                                LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                                LEFT JOIN usuarios u ON p.usuario_id = u.id
                                LEFT JOIN solicitudes s ON p.solicitud_id = s.id
                                WHERE p.id = ?');
$st->execute([$id]);
$pago = $st->fetch();
if (!$pago) {
    http_response_code(404);
    exit('Recibo no encontrado.');
}

$esAdmin = esta_admin();
$usuario = sesion_actual();
if (!$esAdmin && (!$usuario || (int)$usuario['id'] !== (int)$pago['usuario_id'])) {
    http_response_code(403);
    exit('No autorizado.');
}

$e = datos_emisor();
$folio = 'PAG-' . str_pad((string)$pago['id'], 5, '0', STR_PAD_LEFT);
$monto = (float)$pago['monto'];
$fechaTxt = date('d/m/Y', strtotime($pago['creado_en']));
$horaTxt = date('H:i', strtotime($pago['creado_en']));
$estadoTxt = ['aprobado' => 'Pagado', 'rechazado' => 'Rechazado', 'pendiente' => 'Pendiente'][$pago['estado']] ?? $pago['estado'];
$colorEstado = $pago['estado'] === 'aprobado' ? [27, 122, 67] : ($pago['estado'] === 'rechazado' ? [192, 57, 43] : [160, 106, 0]);

$AZUL_DARK  = [7, 28, 61];
$AZUL_CLARO = [157, 184, 232];
$AZUL_MEDIO = [11, 94, 215];
$TEXTO      = [28, 43, 69];
$GRIS       = [138, 151, 173];
$BORDE      = [227, 234, 245];
$RAYA       = [217, 225, 240];
$BG_FILA    = [248, 251, 255];

$pdf = new FV_PDF();

/* Cabecera */
$pdf->rectT(0, 0, 210, 34, ['fill' => $AZUL_DARK]);
$anchoLogo = 0;
if ($e['logo'] !== '') {
    if ($pdf->imagenT($e['logo'], 16, 6, 22, 22)) {
        $tam = @getimagesize($e['logo']);
        $rel = $tam && !empty($tam[0]) && !empty($tam[1]) ? $tam[0] / $tam[1] : 1.0;
        $anchoLogo = min(26, 22 * $rel);
    }
}
$marcaX = 16 + ($anchoLogo > 0 ? $anchoLogo + 8 : 0);
$pdf->textoT($marcaX, 13, $e['nombre'], ['tipo' => 'bold', 'tam' => 15, 'color' => [255, 255, 255]]);

/* Título + metadatos del documento */
$pdf->textoT(16, 44, 'RECIBO DE PAGO', ['tipo' => 'bold', 'tam' => 19, 'color' => $AZUL_DARK]);
$pdf->textoT(194, 48, 'Folio: ' . $folio . '   ·   ' . $fechaTxt . ' ' . $horaTxt, ['tipo' => 'regular', 'tam' => 8.5, 'color' => $GRIS, 'align' => 'right']);
$pdf->textoT(16, 51.5, 'Comprobante de pago', ['tipo' => 'regular', 'tam' => 8.5, 'color' => $GRIS]);

/* Pasillos de filas */
$filas = [
    ['Cliente',  $pago['cliente_nombre'] ?: 'Cliente registrado'],
    ['Correo',   $pago['cliente_email'] ?: '—'],
    ['Concepto', $pago['concepto'] ?: 'Compra de productos'],
    ['Tipo de pago', ucfirst((string)$pago['tipo_pago'])],
    ['Método de pago', $pago['metodo_nombre'] ?: '—'],
    ['Referencia bancaria', trim((string)($pago['referencia'] ?? '')) !== '' ? (string)$pago['referencia'] : '—'],
    ['Estado', $estadoTxt],
];

$y = 60;
$pdf->textoT(16, $y, 'RESUMEN DEL PAGO', ['tipo' => 'bold', 'tam' => 13, 'color' => $TEXTO]);
$y += 8;

foreach ($filas as $fila) {
    $bottom = $pdf->textoT(34, $y, (string)$fila[0], ['tipo' => 'regular', 'tam' => 10, 'color' => $GRIS]);
    $bottomV = $pdf->textoT(98, $y, (string)$fila[1], ['tipo' => 'bold', 'tam' => 10, 'color' => $TEXTO, 'ancho' => 100]);
    $y = max($bottom, $bottomV) + 6.5;
}

/* Total */
$pdf->lineaT(16, $y, 194, $y, $BORDE, 0.5);
$y += 4;
$pdf->rectT(16, $y, 178, 14, ['fill' => $AZUL_DARK]);
$pdf->textoT(34, $y + 9.5, 'TOTAL', ['tipo' => 'bold', 'tam' => 12, 'color' => [255, 255, 255]]);
$pdf->textoT(186, $y + 9.5, '$' . number_format($monto, 2) . ' MXN', ['tipo' => 'bold', 'tam' => 13, 'color' => [255, 255, 255], 'align' => 'right']);
$y += 22;

/* Nota */
$nota = trim((string)($pago['notas'] ?? ''));
if ($nota !== '') {
    $pdf->textoT(16, $y, 'NOTA DEL PAGO', ['tipo' => 'bold', 'tam' => 9, 'color' => $AZUL_MEDIO]);
    $y += 6;
    $y = $pdf->textoT(16, $y, $nota, ['tipo' => 'regular', 'tam' => 10, 'color' => $TEXTO, 'ancho' => 178]) + 6;
}

/* Pie */
$pdf->lineaT(16, 278, 194, 278, $RAYA, 0.4);
$pdf->textoT(105, 282, 'Gracias por tu confianza. Documento generado por ' . $e['nombre'], ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);
$pdf->textoT(105, 287, $e['direccion'] . ' · ' . $e['email'] . ' · ' . $e['telefono'], ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);

$pdf->descargar('recibo-' . $folio . '.pdf');