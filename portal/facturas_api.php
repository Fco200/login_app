<?php
/* API JSON de facturas (portal y panel). Usada por jsPDF para generar el PDF.
   Accesible para el cliente dueño de la factura o para cualquier admin. */
require_once __DIR__ . '/../funciones.php';
iniciar_sesion_segura();

header('Content-Type: application/json; charset=utf-8');

$esAdmin   = esta_admin();
$usuario   = sesion_actual();

/* ---------- Autocompletado de factura por proyecto ---------- */
$proyectoId = (int)($_GET['proyecto_id'] ?? 0);
if ($proyectoId > 0) {
    $st = $GLOBALS['pdo']->prepare('SELECT pi.*, s.tipo_servicio AS servicio, u.nombre, u.email
                                    FROM proyectos_inicio pi
                                    JOIN usuarios u ON pi.usuario_id = u.id
                                    LEFT JOIN solicitudes s ON pi.solicitud_id = s.id
                                    WHERE pi.id = ?');
    $st->execute([$proyectoId]);
    $proy = $st->fetch();
    if (!$proy) {
        http_response_code(404);
        exit(json_encode(['ok' => false, 'mensaje' => 'Proyecto no encontrado.'], JSON_UNESCAPED_UNICODE));
    }
    if (!$esAdmin && (!$usuario || (int)$usuario['id'] !== (int)$proy['usuario_id'])) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE));
    }

    /* Datos fiscales del cliente desde su perfil. */
    $fisc = ['rfc' => '', 'direccion' => ''];
    if ($proy['usuario_id']) {
        $stU = $GLOBALS['pdo']->prepare('SELECT rfc, direccion FROM usuarios WHERE id = ?');
        $stU->execute([(int)$proy['usuario_id']]);
        $fisc = $stU->fetch() ?: $fisc;
    }

    $pagos    = proyecto_pagos_aprobados_detalle($proyectoId);
    $ivaRate  = factura_iva_rate();
    $subtotal = round((float)array_sum(array_map(fn($pp) => (float)$pp['monto'], $pagos)), 2);
    $iva      = round($subtotal * $ivaRate, 2);
    $total    = round($subtotal + $iva, 2);

    $estado    = (string)($proy['estado'] ?? '');
    $saldoRest = (float)($proy['saldo_restante'] ?? 0);
    $liquidado = in_array($estado, ['liquidado', 'completado'], true)
        || ((float)($proy['total_proyecto'] ?? 0) > 0 && $saldoRest <= 0.01);

    echo json_encode([
        'ok'       => true,
        'proyecto' => [
            'id'              => (int)$proy['id'],
            'concepto'        => (string)($proy['servicio'] ?? ''),
            'estado'          => $estado,
            'total_proyecto'  => (float)$proy['total_proyecto'],
            'pagado_total'    => (float)$proy['pagado_total'],
            'saldo_restante'  => $saldoRest,
            'liquidado'       => $liquidado,
        ],
        'cliente'  => [
            'nombre'    => (string)($proy['nombre'] ?? ''),
            'email'     => (string)($proy['email'] ?? ''),
            'rfc'       => (string)($fisc['rfc'] ?? ''),
            'direccion' => (string)($fisc['direccion'] ?? ''),
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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'mensaje' => 'ID de factura inválido.'], JSON_UNESCAPED_UNICODE));
}

$f = factura_datos($id);
if (!$f) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'mensaje' => 'Factura no encontrada.'], JSON_UNESCAPED_UNICODE));
}

if (!$esAdmin && (!$usuario || (int)$usuario['id'] !== (int)$f['usuario_id'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE));
}

$pagos = json_decode((string)($f['pagos_json'] ?? '[]'), true) ?: [];
if (!is_array($pagos)) {
    $pagos = [];
}

$direccion = '';
if ($usuario) {
    $stU = $GLOBALS['pdo']->prepare('SELECT rfc, direccion FROM usuarios WHERE id = ?');
    $stU->execute([(int)$usuario['id']]);
    $fisc = $stU->fetch();
    $direccion = (string)($fisc['direccion'] ?? '');
}

echo json_encode([
    'ok' => true,
    'factura' => [
        'id'            => (int)$f['id'],
        'folio'         => (string)$f['folio'],
        'fecha'         => date('d/m/Y', strtotime((string)$f['creado_en'])),
        'concepto'      => (string)$f['concepto'],
        'subtotal'      => (float)$f['subtotal'],
        'iva'           => (float)$f['iva'],
        'total'         => (float)$f['total'],
        'emisor'        => dato_sitio('nombre', SITE_NOMBRE),
        'cliente_nombre'=> (string)($f['cliente_nombre'] ?? ''),
        'cliente_email' => (string)($f['cliente_email'] ?? ''),
        'rfc'           => (string)($f['rfq_cliente'] ?? ''),
        'direccion'     => $direccion,
        'servicio'      => (string)($f['tipo_servicio'] ?? ''),
        'solicitud_id'  => $f['solicitud_id'] !== null ? (int)$f['solicitud_id'] : null,
        'pagos'         => $pagos,
    ],
], JSON_UNESCAPED_UNICODE);
exit;