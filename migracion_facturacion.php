<?php
/* ============================================================
   FV DIGITAL - Migración: pagos robustos + facturación (MongoDB)
   ------------------------------------------------------------
   En MongoDB no hay ALTER TABLE ni information_schema: el esquema
   es dinámico. Esta migración deja lo que sí aplica a un almacén de
   documentos:
     - índices de pagos (clave única contra duplicados), facturas y
       del historial de proyectos,
     - backfill de datos: vincular pagos históricos a su proyecto,
       calcular total_proyecto desde el presupuesto de la solicitud
       y recalcular el saldo / liquidación de cada proyecto.
   Es idempotente y reejecutable. Ejecutar una vez y borrar el archivo.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];
$pasos   = [];

$confirmado = (($_GET['confirmar'] ?? '') === '1');

/* Crea un índice y registra el paso; los duplicados ya existentes
   se omiten con sparsePartialFilterExpression cuando aplica. */
function mf_indice(string $coleccion, array $claves, string $nombre, array $opciones = []): void
{
    global $errores, $pasos;
    try {
        col($coleccion)->createIndex($claves, ($opciones ?? []) + ['name' => $nombre]);
        $pasos[] = $coleccion . '/' . $nombre;
    } catch (\Throwable $e) {
        $errores[] = $coleccion . '/' . $nombre . ': ' . $e->getMessage();
    }
}

if ($confirmado) {
    /* ============================================================
       1) Índices de pagos: clave única, proyecto, estado y usuario
       ============================================================ */
    mf_indice('pagos', ['clave_unica' => 1], 'ux_pagos_clave', [
        'unique' => true,
        /* Los pagos sin clave (ventas oldies) no deben bloquear el índice. */
        'partialFilterExpression' => ['clave_unica' => ['$type' => 'string', '$ne' => '']],
    ]);
    mf_indice('pagos', ['proyecto_id' => 1], 'ix_pagos_proyecto');
    mf_indice('pagos', ['estado' => 1], 'ix_pagos_estado');
    mf_indice('pagos', ['usuario_id' => 1, 'creado_en' => -1], 'ix_pagos_usuario');

    /* ============================================================
       2) Índices de facturas
       ============================================================ */
    mf_indice('facturas', ['folio' => 1], 'ux_facturas_folio', ['unique' => true]);
    mf_indice('facturas', ['proyecto_id' => 1, 'creado_en' => -1], 'ix_facturas_proyecto');
    mf_indice('facturas', ['usuario_id' => 1, 'creado_en' => -1], 'ix_facturas_usuario');
    mf_indice('facturas', ['estado' => 1], 'ix_facturas_estado');

    /* ============================================================
       3) Índices de la bitácora de proyectos
       ============================================================ */
    mf_indice('proyecto_historial', ['proyecto_id' => 1, 'creado_en' => -1], 'ix_hist_proyecto');
    mf_indice('proyecto_historial', ['accion' => 1], 'ix_hist_accion');

    /* ============================================================
       4) Backfill idempotente de datos
       ============================================================ */
    try {
        $proyectos = col_q('proyectos_inicio');
        $sols      = sol_por_ids(array_map(static fn($p) => (string)($p['solicitud_id'] ?? ''), $proyectos));

        $presupuestos = [];
        foreach ($proyectos as $p) {
            $clave = (string)oid($p['solicitud_id'] ?? null);
            $presupuestos[(string)$p['id']] = $sols[$clave]['presupuesto'] ?? null;
        }

        /* 4a) Vincular pagos históricos a su proyecto por solicitud_id. */
$vinculados = 0;
        foreach ($proyectos as $p) {
            $sol_id = oid($p['solicitud_id'] ?? null);
            $usr_id = oid($p['usuario_id'] ?? null);
            if ($sol_id === null || $usr_id === null) {
                continue;
            }
            $filtro = [
                'proyecto_id'  => null,
                'solicitud_id' => $sol_id,
                'usuario_id'   => $usr_id,
                'tipo_pago'    => ['$in' => ['anticipo', 'restante', 'completo']],
            ];
            $vinculados += col_actualizar_varios('pagos', $filtro, ['$set' => ['proyecto_id' => oid($p['id'])]]);
        }
        $pasos[] = 'pagos históricos vinculados por solicitud_id: ' . $vinculados;

        /* 4b) Definir total_proyecto a partir de solicitudes.presupuesto. */
        $conTotal = 0;
        foreach ($proyectos as $p) {
            if ((float)($p['total_proyecto'] ?? 0) > 0) {
                continue; // ya tiene valor definido
            }
            $total = proyecto_total_parsear($presupuestos[(string)$p['id']] ?? null);
            if ($total > 0) {
                col_actualizar('proyectos_inicio', $p['id'], ['$set' => ['total_proyecto' => $total]]);
                $conTotal++;
            }
        }
        $pasos[] = 'total_proyecto calculado desde presupuesto: ' . $conTotal;

        /* 4c) Recalcular el saldo de todos los proyectos (aprobados existentes). */
        col_borrar_varios('proyecto_historial', ['accion' => 'migracion_saldo']);
        foreach ($proyectos as $p) {
            proyecto_recalcular_saldo($p['id']);
            registrar_historial_proyecto($p['id'], 'migracion_saldo', 'Saldo financiero recalculado por la migración de pagos y facturación.');
        }
        $pasos[] = 'saldo_restante / liquidación recalculados: ' . count($proyectos);
} catch (\Throwable $e) {
        $errores[] = 'backfill: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración: Pagos robustos y Facturación - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 620px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Pagos robustos y Facturación</h3>
    <?php if (!$confirmado): ?>
        <div class="alert alert-info">
            La migración aún no se aplica en esta visita. Este script es <b>idempotente</b> y reejecutable:
            si ya la pusiste en marcha antes, no es necesario correrla de nuevo y puedes borrar el archivo.
        </div>
        <a class="btn btn-primary" href="migracion_facturacion.php?confirmar=1"><i class="bi bi-play-fill me-1"></i>Ejecutar la migración</a>
    <?php elseif (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted mb-3">
            <li>pagos: índice único de <b>clave_unica</b> (parcial, ignora pagos sin clave) e índices de proyecto, estado y usuario.</li>
            <li>facturas: folio único e índices por proyecto, usuario y estado.</li>
            <li>proyecto_historial: índices por proyecto y acción.</li>
            <li>Datos: pagos vinculados a su proyecto, total_proyecto desde el presupuesto y saldos recalculados.</li>
        </ul>
        <div class="alert alert-light border small mb-3">
            <b><i class="bi bi-list-check me-1"></i>Pasos aplicados:</b>
            <ul class="mb-0 ps-3">
                <?php foreach ($pasos as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores (el script es reejecutable, corrige e inténtalo de nuevo):</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/facturacion.php" class="btn btn-primary">Ir a Facturación</a>
        <a href="portal/facturacion.php" class="btn btn-outline-primary">Facturación del cliente</a>
        <a href="admin/index.php" class="btn btn-outline-secondary">Panel</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>
