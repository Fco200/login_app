<?php
/* ============================================================
   FV DIGITAL - Migración: tablas de pagos, carrito, proyectos
   Ejecutar una vez desde el navegador.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

/* Índices de las colecciones de pagos (sustituye los CREATE TABLE). */
$indices = [
    ['productos',        [['slug' => 1], 'ux_productos_slug', ['unique' => true]]],
    ['productos',        [['activo' => 1, 'categoria' => 1], 'ix_productos_activo_categoria']],
    ['carrito',          [['usuario_id' => 1], 'ix_carrito_usuario']],
    ['pagos',            [['usuario_id' => 1, 'creado_en' => -1], 'ix_pagos_usuario_fecha']],
    ['pagos',            [['estado' => 1, 'creado_en' => -1], 'ix_pagos_estado_fecha']],
    ['pagos',            [['proyecto_id' => 1, 'estado' => 1], 'ix_pagos_proyecto_estado']],
    ['pagos',            [['solicitud_id' => 1], 'ix_pagos_solicitud']],
    ['proyectos_inicio', [['usuario_id' => 1, 'estado' => 1], 'ix_proy_usuario_estado']],
    ['proyectos_inicio', [['estado' => 1, 'creado_en' => -1], 'ix_proy_estado_fecha']],
    ['proyectos_inicio', [['solicitud_id' => 1], 'ix_proy_solicitud']],
    ['metodos_pago',     [['activo' => 1], 'ix_metodos_activo']],
];

foreach ($indices as [$coleccion, $claves, $nombre, $opciones]) {
    try {
        col($coleccion)->createIndex($claves, ($opciones ?? []) + ['name' => $nombre]);
    } catch (\Throwable $e) {
        $errores[] = $coleccion . '/' . $nombre . ': ' . $e->getMessage();
    }
}

/* Insertar métodos de pago por defecto si la colección está vacía */
if (contar_registros('metodos_pago') === 0) {
    $metodos = [
        ['Transferencia bancaria', 'Realiza una transferencia directa a nuestra cuenta bancaria.', 'CLABE: 012345678901234567 | Cuenta: 1234567890 | Banco: Banamex', 'Realiza la transferencia con el monto indicado. Envía tu comprobante desde el portal.', 'bi-bank'],
        ['PayPal', 'Paga de forma rápida y segura a través de PayPal.', 'Correo: pagos@fvdigital.com', 'Envía el pago a nuestro correo de PayPal y adjunta el comprobante.', 'bi-paypal'],
        ['OXXO', 'Paga en efectivo en cualquier tienda OXXO.', 'Referencia: Se genera al momento de elegir este método', 'Acude a tu OXXO más cercano y paga con la referencia proporcionada.', 'bi-shop'],
        ['Efectivo', 'Pago en efectivo directo.', 'Coordina con el equipo para el punto de entrega.', 'Contacta a nuestro equipo para coordinar la entrega de efectivo.', 'bi-cash-stack'],
    ];
    foreach ($metodos as $m) {
        mp_guardar(null, [
            'nombre'          => $m[0],
            'descripcion'     => $m[1],
            'detalles_cuenta' => $m[2],
            'instrucciones'   => $m[3],
            'icono'           => $m[4],
            'activo'          => 1,
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 560px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Pagos, Carrito y Proyectos</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted">
            <li>Índices creados en: metodos_pago, productos, carrito, pagos, proyectos_inicio.</li>
            <li>Métodos de pago por defecto insertados.</li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/index.php" class="btn btn-primary">Ir al panel</a>
        <a href="portal/index.php" class="btn btn-outline-primary">Portal de clientes</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>
