<?php
/* FV DIGITAL - Factura en PDF (generada 100% en el servidor con la marca).
   La descarga ya no depende de jsPDF ni del navegador: siempre funciona.
   Accesible para el cliente dueño de la factura o para administradores. */
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/../lib/pdf.php';

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
$pagos = is_array($pagos) ? array_values(array_filter($pagos, fn($p) => isset($p['monto']))) : [];

$direccionCliente = '';
if ($f['usuario_id']) {
    $stU = $GLOBALS['pdo']->prepare('SELECT direccion FROM usuarios WHERE id = ?');
    $stU->execute([(int)$f['usuario_id']]);
    $direccionCliente = (string)($stU->fetchColumn() ?: '');
}

$e = datos_emisor();

$subtotal = (float)$f['subtotal'];
$iva      = (float)$f['iva'];
$total    = (float)$f['total'];
$ivaTasa  = $subtotal > 0 ? $iva / $subtotal : 0.16;

$folio       = (string)$f['folio'];
$fecha       = date('d/m/Y', strtotime((string)$f['creado_en']));
$concepto    = (string)$f['concepto'];
$rfcCliente  = (string)($f['rfq_cliente'] ?? '');
$cliente     = (string)$f['cliente_nombre'];
$emailCliente= (string)$f['cliente_email'];
$solicitud   = $f['solicitud_id'] !== null ? (int)$f['solicitud_id'] : 0;
$servicio    = (string)($f['tipo_servicio'] ?? '');

$AZUL_DARK  = [7, 28, 61];      // #071c3d
$AZUL       = [11, 61, 143];    // #0a3d8f
$AZUL_MEDIO = [11, 94, 215];    // #0b5ed7
$AZUL_CLARO = [157, 184, 232];  // #9db8e8
$BG_BLOQUE  = [248, 251, 255];  // #f8fbff
$BORDE      = [227, 234, 245];  // #e3eaf5
$ZEBRA      = [244, 248, 255];  // #f4f8ff
$TEXTO      = [28, 43, 69];     // #1c2b45
$GRIS       = [138, 151, 173];  // #8a97ad
$RAYA       = [217, 225, 240];  // #d9e1f0

$pdf = new FV_PDF();

/* ================= CABEZA DE MARCA ================= */
$pdf->rectT(0, 0, 210, 34, ['fill' => $AZUL_DARK]);

/* Logotipo (si GD está disponible) */
$anchoLogo = 0;
if ($e['logo'] !== '') {
    if ($pdf->imagenT($e['logo'], 16, 6, 24, 22)) {
        $tam = @getimagesize($e['logo']);
        $rel = $tam && !empty($tam[0]) && !empty($tam[1]) ? $tam[0] / $tam[1] : 1.0;
        $anchoLogo = min(26, 22 * $rel);
    }
}
$marcaX = 16 + ($anchoLogo > 0 ? $anchoLogo + 8 : 0);
$pdf->textoT($marcaX, 13, $e['nombre'] !== '' ? $e['nombre'] : 'FV DIGITAL', ['tipo' => 'bold', 'tam' => 15, 'color' => [255, 255, 255]]);

/* Título + metadatos del documento */
$metaLinea = 'Folio: ' . $folio . '   ·   ' . $fecha;
if ($solicitud > 0) {
    $metaLinea .= '   ·   Solicitud #' . $solicitud;
}
$pdf->textoT(16, 44, 'FACTURA', ['tipo' => 'bold', 'tam' => 20, 'color' => $AZUL_DARK]);
$pdf->textoT(194, 48, $metaLinea, ['tipo' => 'regular', 'tam' => 8.5, 'color' => $GRIS, 'align' => 'right']);
$pdf->textoT(16, 51.5, 'Comprobante de facturación con IVA desglosado', ['tipo' => 'regular', 'tam' => 8.5, 'color' => $GRIS]);

/* ================= BLOQUES CLIENTE / EMISOR ================= */
function altoCampo(FV_PDF $pdf, string $valor, float $ancho): float {
    $lineas = $pdf->ajustarTexto($valor !== '' ? $valor : '—', $ancho, 'regular', 9.5);
    return 4.6 + count($lineas) * 4.2 + 5.5;
}
function pintarBloqueFactura(FV_PDF $pdf, float $x, float $y, float $w, float $h, string $titulo, array $campos): void {
    global $AZUL_MEDIO, $BG_BLOQUE, $BORDE;
    $pdf->rectT($x, $y, $w, $h, ['fill' => $BG_BLOQUE, 'stroke' => $BORDE, 'ancho' => 0.6]);
    $pdf->rectT($x, $y, $w, 1.6, ['fill' => $AZUL_MEDIO]);
    $pdf->textoT($x + 10, $y + 4.5, strtoupper($titulo), ['tipo' => 'bold', 'tam' => 8.5, 'color' => $AZUL_MEDIO]);
    $campoY = $y + 11;
    foreach ($campos as $campo) {
        $pdf->textoT($x + 10, $campoY, strtoupper($campo[0]), ['tipo' => 'regular', 'tam' => 8.2, 'color' => [138, 151, 173]]);
        $campoY2 = $campoY + 4.6;
        $bottom = $pdf->textoT($x + 10, $campoY2, $campo[1] !== '' ? $campo[1] : '—', ['tipo' => 'bold', 'tam' => 9.5, 'color' => [28, 43, 69], 'ancho' => $w - 20]);
        $campoY = $bottom + 5.5;
    }
}

$izquierda = [
    ['Cliente', $cliente],
    ['RFC', $rfcCliente],
    ['Correo', $emailCliente],
    ['Dirección', $direccionCliente],
    ['Concepto', $concepto],
];
$derecha = [['Empresa', $e['nombre']]];
if ($e['razon_social'] !== '') { $derecha[] = ['Razón social', $e['razon_social']]; }
if ($e['rfc'] !== '')          { $derecha[] = ['RFC', $e['rfc']]; }
$derecha[] = ['Dirección', $e['direccion']];
$derecha[] = ['Teléfono', $e['telefono']];
$derecha[] = ['Correo', $e['email']];

$bloqueY = 62;
$altoIzq = 12 + array_sum(array_map(fn($c) => altoCampo($pdf, $c[1], 66.0), $izquierda));
$altoDer = 12 + array_sum(array_map(fn($c) => altoCampo($pdf, $c[1], 66.0), $derecha));
$bloqueH = max($altoIzq, $altoDer) + 6;

pintarBloqueFactura($pdf, 16, $bloqueY, 86, $bloqueH, 'Datos del cliente', $izquierda);
pintarBloqueFactura($pdf, 108, $bloqueY, 86, $bloqueH, 'Datos del emisor', $derecha);

/* ================= TABLA DE PAGOS ================= */
$tablaY = $bloqueY + $bloqueH + 10;
$colMontos = 190;

$pdf->rectT(16, $tablaY, 178, 8, ['fill' => $AZUL]);
$pdf->textoT(18, $tablaY + 5, 'FOLIO DE PAGO', ['tipo' => 'bold', 'tam' => 8, 'color' => [255, 255, 255]]);
$pdf->textoT(58, $tablaY + 5, 'FECHA', ['tipo' => 'bold', 'tam' => 8, 'color' => [255, 255, 255]]);
$pdf->textoT(94, $tablaY + 5, 'TIPO DE PAGO', ['tipo' => 'bold', 'tam' => 8, 'color' => [255, 255, 255]]);
$pdf->textoT($colMontos, $tablaY + 5, 'MONTO', ['tipo' => 'bold', 'tam' => 8, 'color' => [255, 255, 255], 'align' => 'right']);

$filaY = $tablaY + 8;
if ($pagos) {
    foreach ($pagos as $i => $p) {
        if ($i % 2 === 1) {
            $pdf->rectT(16, $filaY, 178, 7, ['fill' => $ZEBRA]);
        }
        $monto = (float)($p['monto'] ?? 0);
        $pdf->textoT(18, $filaY + 4.5, (string)($p['folio'] ?? 'PAG-00000'), ['tipo' => 'bold', 'tam' => 9, 'color' => $TEXTO]);
        $pdf->textoT(58, $filaY + 4.5, (string)($p['fecha'] ?? ''), ['tipo' => 'regular', 'tam' => 9, 'color' => $GRIS]);
        $pdf->textoT(94, $filaY + 4.5, ucfirst((string)($p['tipo'] ?? 'Pago')), ['tipo' => 'regular', 'tam' => 9, 'color' => $TEXTO]);
        $pdf->textoT($colMontos, $filaY + 4.5, '$' . number_format($monto, 2), ['tipo' => 'bold', 'tam' => 9, 'color' => $TEXTO, 'align' => 'right']);
        $filaY += 7;
    }
} else {
    $pdf->rectT(16, $filaY, 178, 7, ['fill' => $ZEBRA]);
    $pdf->textoT(18, $filaY + 4.5, 'Pagos aprobados del proyecto', ['tipo' => 'regular', 'tam' => 9, 'color' => $GRIS]);
    $pdf->textoT($colMontos, $filaY + 4.5, '$' . number_format($subtotal, 2), ['tipo' => 'bold', 'tam' => 9, 'color' => $TEXTO, 'align' => 'right']);
    $filaY += 7;
}

/* ================= TOTALES ================= */
$totY = $filaY + 9;
$pdf->textoT(16, $totY, 'Subtotal', ['tipo' => 'regular', 'tam' => 10.5, 'color' => [60, 80, 110]]);
$pdf->textoT($colMontos, $totY, '$' . number_format($subtotal, 2), ['tipo' => 'bold', 'tam' => 10.5, 'color' => $TEXTO, 'align' => 'right']);

$pdf->textoT(16, $totY + 6.5, 'IVA (' . round($ivaTasa * 100) . '%)', ['tipo' => 'regular', 'tam' => 10.5, 'color' => [60, 80, 110]]);
$pdf->textoT($colMontos, $totY + 6.5, '$' . number_format($iva, 2), ['tipo' => 'bold', 'tam' => 10.5, 'color' => $TEXTO, 'align' => 'right']);

$boxY = $totY + 14;
$pdf->rectT(16, $boxY, 178, 18, ['fill' => $AZUL_DARK]);
$pdf->textoT(28, $boxY + 4.4, 'TOTAL A PAGAR (MXN)', ['tipo' => 'bold', 'tam' => 9, 'color' => [255, 255, 255]]);
$pdf->textoT(186, $boxY + 12.8, '$' . number_format($total, 2), ['tipo' => 'bold', 'tam' => 15, 'color' => [255, 255, 255], 'align' => 'right']);

$pdf->textoT(16, $boxY + 24, 'Concepto: ' . ($servicio !== '' ? $servicio : $concepto), ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS]);

/* ================= PIE ================= */
$pdf->lineaT(16, 278, 194, 278, $RAYA, 0.4);
$pdf->textoT(105, 282, 'Documento generado automáticamente por ' . $e['nombre'] . ($e['eslogan'] !== '' ? ' · ' . $e['eslogan'] : ''), ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);
$pdf->textoT(105, 287, 'Folio ' . $folio . ' · Fecha de emisión ' . $fecha . ' · Página 1 de 1', ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);

$pdf->descargar('factura-' . $folio . '.pdf');