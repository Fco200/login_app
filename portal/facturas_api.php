<?php
/* API JSON de facturas (portal y panel). Usada por jsPDF para generar el PDF.
   Accesible para el cliente dueño de la factura o para cualquier admin. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

header('Content-Type: application/json; charset=utf-8');

$esAdmin   = esta_admin();
$usuario   = sesion_actual();

/* ---------- Autocompletado de factura por proyecto ---------- */
$proyectoId = trim((string)($_GET['proyecto_id'] ?? ''));
if ($proyectoId !== '' && oid($proyectoId) !== null) {
    $proy = proy_por_id($proyectoId);
    if ($proy === null) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'mensaje' => 'Proyecto no encontrado.'], JSON_UNESCAPED_UNICODE));
    }
    if (!$esAdmin && (!$usuario || (string)($usuario['id'] ?? '') !== (string)($proy['usuario_id'] ?? ''))) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE));
    }

    $cliente = !empty($proy['usuario_id']) ? usr_por_id($proy['usuario_id']) : null;
    $solicitud = !empty($proy['solicitud_id']) ? sol_por_id($proy['solicitud_id']) : null;

    $pagos    = proyecto_pagos_aprobados_detalle($proyectoId);
    $ivaRate  = factura_iva_rate();
    $subtotal = round((float)array_sum(array_map(static fn($pp) => (float)$pp['monto'], $pagos)), 2);
    $iva      = round($subtotal * $ivaRate, 2);
    $total    = round($subtotal + $iva, 2);

    $estado    = (string)($proy['estado'] ?? '');
    $saldoRest = (float)($proy['saldo_restante'] ?? 0);
    $liquidado = in_array($estado, ['liquidado', 'completado'], true)
        || ((float)($proy['total_proyecto'] ?? 0) > 0 && $saldoRest <= 0.01);

    echo json_encode([
        'ok'       => true,
        'proyecto' => [
            'id'              => (string)$proy['id'],
            'concepto'        => (string)($solicitud['tipo_servicio'] ?? ''),
            'estado'          => $estado,
            'total_proyecto'  => (float)($proy['total_proyecto'] ?? 0),
            'pagado_total'    => (float)($proy['pagado_total'] ?? 0),
            'saldo_restante'  => $saldoRest,
            'liquidado'       => $liquidado,
        ],
        'cliente'  => [
            'nombre'    => (string)($cliente['nombre'] ?? ''),
            'email'     => (string)($cliente['email'] ?? ''),
            'rfc'       => (string)($cliente['rfc'] ?? ''),
            'direccion' => (string)($cliente['direccion'] ?? ''),
        ],
        'subtotal' => $subtotal,
        'iva'      => $iva,
        'total'    => $total,
        'iva_rate' => $ivaRate,
        'pagos'    => $pagos,
        'facturas' => facturas_por_proyecto($proyectoId),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = trim((string)($_GET['id'] ?? ''));
if (oid($id) === null) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'mensaje' => 'ID de factura inválido.'], JSON_UNESCAPED_UNICODE));
}

$f = factura_por_id($id);
if (!$f) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.'], JSON_UNESCAPED_UNICODE));
}

if (!$esAdmin && (!$usuario || (string)($usuario['id'] ?? '') !== (string)($f['usuario_id'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE));
}

/* El desglose se guarda como snapshot dentro del documento de la factura. */
$pagos = $f['pagos'] ?? [];
if (!is_array($pagos)) {
    $pagos = [];
}

$fiscal = is_array($f['cliente_fiscal'] ?? null) ? $f['cliente_fiscal'] : [];
$direccion = (string)($fiscal['direccion'] ?? '');

echo json_encode([
    'ok' => true,
    'factura' => [
        'id'            => (string)$f['id'],
        'folio'         => (string)$f['folio'],
        'fecha'         => fecha_php($f['creado_en'] ?? '', 'd/m/Y'),
        'concepto'      => (string)$f['concepto'],
        'subtotal'      => (float)$f['subtotal'],
        'iva'           => (float)$f['iva'],
        'total'         => (float)$f['total'],
        'emisor'        => dato_sitio('nombre', SITE_NOMBRE),
        'cliente_nombre'=> (string)($fiscal['nombre'] ?? ($f['cliente_nombre'] ?? '')),
        'cliente_email' => (string)($f['cliente_email'] ?? ''),
        'rfc'           => (string)($fiscal['rfc'] ?? ''),
        'direccion'     => $direccion,
        'servicio'      => (string)($f['tipo_servicio'] ?? ''),
        'solicitud_id'  => !empty($f['solicitud_id']) ? (string)$f['solicitud_id'] : null,
        'estado'        => (string)($f['estado'] ?? ''),
        'pagos'         => $pagos,
    ],
], JSON_UNESCAPED_UNICODE);
exit;