<?php
/* FV DIGITAL - Carta de agradecimiento en PDF (generada 100% en el servidor).
   Mismo contenido que la vista previa, pero garantiza la descarga directa. */
require_once __DIR__ . '/../funciones.php';
require_once __DIR__ . '/../lib/pdf.php';

iniciar_sesion_segura();

$proyectoId = (int)($_GET['proyecto'] ?? ($_GET['proyecto_id'] ?? 0));
if ($proyectoId <= 0) {
    http_response_code(400);
    exit('Proyecto inválido.');
}

$esAdmin = esta_admin();
$usuario = sesion_actual();

$st = $GLOBALS['pdo']->prepare('SELECT pi.*, s.tipo_servicio, s.presupuesto, s.creado_en AS solicitud_creada,
                                       u.nombre AS cliente_nombre, u.email AS cliente_email
                                FROM proyectos_inicio pi
                                LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                                LEFT JOIN usuarios u ON pi.usuario_id = u.id
                                WHERE pi.id = ?');
$st->execute([$proyectoId]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    exit('Proyecto no encontrado.');
}
if (!$esAdmin && (!$usuario || (int)$usuario['id'] !== (int)$p['usuario_id'])) {
    http_response_code(403);
    exit('No autorizado.');
}
if ($p['estado'] !== 'completado') {
    http_response_code(409);
    exit('La carta de agradecimiento está disponible cuando el proyecto se entrega (Completado).');
}

$entregables = [];
$stE = $GLOBALS['pdo']->prepare('SELECT titulo, notas FROM entregables WHERE proyecto_id = ? ORDER BY creado_en ASC');
$stE->execute([$proyectoId]);
foreach ($stE as $en) {
    $entregables[] = [
        'titulo' => (string)$en['titulo'],
        'notas'  => (string)($en['notas'] ?? ''),
    ];
}

$e = datos_emisor();
$pagos = proyecto_pagos_aprobados_detalle($proyectoId);
$subtotalPagos = round(array_sum(array_map(fn($pp) => (float)$pp['monto'], $pagos)), 2);

$folio = 'FV-' . str_pad((string)$p['id'], 4, '0', STR_PAD_LEFT);
$servicio = (string)($p['tipo_servicio'] ?? '');
$descripcion = (string)($p['descripcion_proyecto'] ?? '');
$cliente = (string)($p['cliente_nombre'] ?? 'Cliente');
$emailCliente = (string)($p['cliente_email'] ?? '');
$iniciado = date('d/m/Y', strtotime($p['creado_en']));
$entregado = date('d/m/Y');
$montoIncluido = max(0.0, (float)$p['total_proyecto']);

$textoAgradecimiento =
    'Todas las personas que formamos FV Digital queremos agradecerte de corazón el habernos '
    . 'confiado tu proyecto. Trabajamos cada día para que tu negocio cuente con una presencia '
    . 'digital profesional, con tiempos de entrega ágiles y un trato cercano.';

$textoCierre =
    'Nos encantará seguir siendo tu aliado digital. Si quieres dar a conocer lo que logramos '
    . 'juntos, tu testimonio nos ayuda muchísimo; y si necesitas ideas, soporte o un nuevo '
    . 'proyecto, estaremos encantados de acompañarte.';

$AZUL_DARK  = [7, 28, 61];
$AZUL_MEDIO = [11, 94, 215];
$AZUL_CLARO = [157, 184, 232];
$BG_BLOQUE  = [248, 251, 255];
$BORDE      = [227, 234, 245];
$TEXTO      = [30, 30, 30];
$GRIS       = [120, 135, 160];
$RAYA       = [217, 225, 240];

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
$pdf->textoT(18, 44, 'Carta de agradecimiento', ['tipo' => 'bold', 'tam' => 17, 'color' => $AZUL_DARK]);
$pdf->textoT(192, 47.5, 'Folio: ' . $folio . '   ·   Proyecto entregado', ['tipo' => 'regular', 'tam' => 8.5, 'color' => $GRIS, 'align' => 'right']);
$pdf->textoT(18, 52.5, 'Sonora, ' . date('d/m/Y'), ['tipo' => 'regular', 'tam' => 9, 'color' => $GRIS]);

$y = 62;
$pdf->textoT(18, $y, 'Apreciado/a ' . $cliente . ':', ['tipo' => 'bold', 'tam' => 11, 'color' => $AZUL_DARK]);
$y += 7;
$y = $pdf->textoT(18, $y, $textoAgradecimiento, ['tipo' => 'regular', 'tam' => 10.5, 'color' => $TEXTO, 'ancho' => 174]) + 3;
$y = $pdf->textoT(18, $y, 'Tenemos el gusto de confirmar que tu proyecto fue ENTREGADO y COMPLETADO con éxito. Estos son los detalles de tu entrega:', ['tipo' => 'regular', 'tam' => 10.5, 'color' => $TEXTO, 'ancho' => 174]) + 4;

/* Recuadro de detalles */
$pdf->rectT(18, $y, 174, 26, ['fill' => $BG_BLOQUE, 'stroke' => $BORDE, 'ancho' => 0.6]);
$detalle = [
    ['Proyecto', $servicio !== '' ? $servicio : 'Proyecto #' . $proyectoId],
    ['Folio', $folio],
    ['Iniciado', $iniciado],
    ['Entregado', $entregado],
];
$filaY = $y + 4.5;
foreach ($detalle as $fila) {
    $pdf->textoT(26, $filaY, $fila[0], ['tipo' => 'regular', 'tam' => 9, 'color' => $GRIS]);
    $pdf->textoT(62, $filaY, $fila[1], ['tipo' => 'bold', 'tam' => 9.5, 'color' => $TEXTO]);
    $filaY += 4.4;
}
$y += 26;

if ($descripcion !== '') {
    $y += 2;
    $pdf->textoT(18, $y, 'TU PROYECTO', ['tipo' => 'bold', 'tam' => 8.5, 'color' => $AZUL_MEDIO]);
    $y += 5.5;
    $y = $pdf->textoT(18, $y, $descripcion, ['tipo' => 'regular', 'tam' => 10, 'color' => $TEXTO, 'ancho' => 174]) + 2;
}

if ($entregables) {
    $y += 2;
    $pdf->textoT(18, $y, 'LO QUE RECIBISTE', ['tipo' => 'bold', 'tam' => 8.5, 'color' => $AZUL_MEDIO]);
    $y += 5.5;
    foreach ($entregables as $eEnt) {
        $linea = '•  ' . $eEnt['titulo'] . ($eEnt['notas'] !== '' ? '  (' . $eEnt['notas'] . ')' : '');
        $y = $pdf->textoT(21, $y, $linea, ['tipo' => 'regular', 'tam' => 10, 'color' => $TEXTO, 'ancho' => 166]) + 1.5;
    }
    $y += 4;
}

$y = $pdf->textoT(18, $y, $textoCierre, ['tipo' => 'regular', 'tam' => 10.5, 'color' => $TEXTO, 'ancho' => 174]) + 4;

$pdf->textoT(18, $y, 'Con todo nuestro agradecimiento,', ['tipo' => 'bold', 'tam' => 11, 'color' => $AZUL_DARK]);
$y += 7;
$pdf->textoT(18, $y, 'El equipo de ' . $e['nombre'], ['tipo' => 'bold', 'tam' => 11, 'color' => $AZUL_DARK]);

/* Pie */
$pdf->lineaT(16, 278, 194, 278, $RAYA, 0.4);
$pdf->textoT(105, 282, 'Esta carta reconoce la entrega oficial de tu proyecto. Gracias por confiar en nosotros.', ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);
$pdf->textoT(105, 287, $e['nombre'] . ' · ' . $e['eslogan'] . ' · ' . $e['direccion'], ['tipo' => 'regular', 'tam' => 8, 'color' => $GRIS, 'align' => 'center']);

$pdf->descargar('carta-agradecimiento-' . $folio . '.pdf');