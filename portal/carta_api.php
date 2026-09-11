<?php
/* FV DIGITAL - API de la Carta de Agradecimiento
   Devuelve los datos de un proyecto ENTREGADO (estado 'completado') para
   generar la carta: datos del cliente, descripción, entregables, pagos
   aprobados y saldos. Valida que el proyecto pertenezca al cliente o que
   quien consulta sea administrador. */
require_once __DIR__ . '/../funciones.php';

header('Content-Type: application/json; charset=utf-8');

iniciar_sesion_segura();
if (!esta_admin() && !esta_logueado()) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'mensaje' => 'Sesión no válida.'], JSON_UNESCAPED_UNICODE));
}

if (!es_ajax()) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'mensaje' => 'Solicitud inválida.'], JSON_UNESCAPED_UNICODE));
}

$proyectoId = (int)($_GET['proyecto_id'] ?? ($_GET['id'] ?? 0));
if ($proyectoId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'mensaje' => 'Proyecto inválido.'], JSON_UNESCAPED_UNICODE));
}

$esAdmin = esta_admin();

/* Datos del proyecto con cliente y solicitud */
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
    exit(json_encode(['ok' => false, 'mensaje' => 'Proyecto no encontrado.'], JSON_UNESCAPED_UNICODE));
}
if (!$esAdmin) {
    $usuario = sesion_actual();
    if (!$usuario || (int)$p['usuario_id'] !== (int)$usuario['id']) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'mensaje' => 'No autorizado.'], JSON_UNESCAPED_UNICODE));
    }
}
if ($p['estado'] !== 'completado') {
    http_response_code(409);
    exit(json_encode(['ok' => false, 'mensaje' => 'La carta de agradecimiento está disponible cuando el proyecto se entrega (Completado).'], JSON_UNESCAPED_UNICODE));
}

/* Entregables registrados */
$entregables = [];
$stE = $GLOBALS['pdo']->prepare('SELECT id, titulo, notas, creado_en FROM entregables WHERE proyecto_id = ? ORDER BY creado_en ASC');
$stE->execute([$proyectoId]);
foreach ($stE as $en) {
    $entregables[] = [
        'titulo' => (string)$en['titulo'],
        'notas'  => (string)($en['notas'] ?? ''),
        'fecha'  => date('d/m/Y', strtotime($en['creado_en'])),
    ];
}

/* Pagos aprobados (desglose de lo que el cliente cubrió) */
$pagos = proyecto_pagos_aprobados_detalle($proyectoId);
$subtotal = round(array_sum(array_map(fn($pp) => (float)$pp['monto'], $pagos)), 2);

$total = max(0, (float)$p['total_proyecto']);

/* Textos personales de la carta (fáciles de editar aquí) */
$textoAgradecimiento =
    'Todas las personas que formamos FV Digital queremos agradecerte de corazón el habernos '
    . 'confiado tu proyecto. Trabajamos cada día para que tu negocio cuente con una presencia '
    . 'digital profesional, con tiempos de entrega ágiles y un trato cercano.';

$textoCierre =
    'Nos encantará seguir siendo tu aliado digital. Si quieres dar a conocer lo que logramos '
    . 'juntos, tu testimonio nos ayuda muchísimo; y si necesitas ideas, soporte o un nuevo '
    . 'proyecto, estaremos encantados de acompañarte.';

echo json_encode([
    'ok' => true,
    'proyecto' => [
        'id'                  => (int)$p['id'],
        'servicio'            => (string)($p['tipo_servicio'] ?? 'Proyecto #' . (int)$p['id']),
        'descripcion'         => (string)($p['descripcion_proyecto'] ?? ''),
        'entregables_txt'     => (string)($p['entregables'] ?? ''),
        'estado'              => (string)$p['estado'],
        'iniciado'            => date('d/m/Y', strtotime($p['creado_en'])),
        'finalizado'          => date('d/m/Y'),
        'folio'               => 'FV-' . str_pad((string)$p['id'], 4, '0', STR_PAD_LEFT),
        'total'               => $total,
        'pagado'              => max(0, (float)$p['pagado_total']),
        'saldo'               => max(0, (float)$p['saldo_restante']),
        'pagos_subtotal'      => $subtotal,
    ],
    'cliente' => [
        'nombre' => (string)($p['cliente_nombre'] ?? 'Cliente'),
        'email'  => (string)($p['cliente_email'] ?? ''),
    ],
    'entregables' => $entregables,
    'pagos'       => $pagos,
    'texto'       => $textoAgradecimiento,
    'cierre'      => $textoCierre,
], JSON_UNESCAPED_UNICODE);
exit;