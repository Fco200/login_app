<?php
/* ============================================================
   FV DIGITAL - Motor de estados y finanzas del proyecto
   ------------------------------------------------------------
   Este archivo complementa `includes/repos/proyectos.php` con la
   lógica de anticipos, saldo, liquidación y cambio de estado.
   Conserva los nombres de función que ya usaban las vistas para
   que el flujo de negocio no cambie, sólo el almacenamiento.
   ============================================================ */

/**
 * Parser numérico del texto de presupuesto: "$50,000 MXN" -> 50000.
 * Si el valor es "A convenir" o no contiene números devuelve 0.
 * Si es un rango ("10,000 - 15,000") toma el tope superior.
 */
function proyecto_total_parsear(?string $presupuesto): float
{
    $texto = trim((string)$presupuesto);
    if ($texto === '') {
        return 0.0;
    }
    if (!preg_match_all('/\d+(?:\.\d+)?/', $texto, $nums) || empty($nums[0])) {
        return 0.0;
    }
    return (float)max(array_map('floatval', $nums[0]));
}

/* ---------- Anticipo ---------- */

/**
 * Suma un anticipo pagado. Deja el proyecto en 'anticipo_pendiente'
 * (a la espera de aprobación) si estaba en 'documentacion'; nunca
 * retrocede desde 'en_desarrollo', 'liquidado' o 'completado'.
 */
function proyecto_aplicar_anticipo($proyectoId, float $monto): void
{
    $o = oid($proyectoId);
    if ($o === null || $monto <= 0) {
        return;
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return;
    }

    col('proyectos_inicio')->updateOne(
        ['_id' => $o],
        ['$inc' => ['anticipo_pagado' => $monto]],
        mongo_opts([])
    );

    /* Si el proyecto sigue en fase inicial, pasa a espera de aprobación. */
    if (in_array($proy['estado'] ?? '', ['documentacion', 'anticipo_pendiente'], true)) {
        col('proyectos_inicio')->updateOne(
            ['_id' => $o],
            ['$set' => ['estado' => 'anticipo_pendiente', 'actualizado_en' => ahora_utc()]],
            mongo_opts([])
        );
    }
}

/**
 * Recalcula el estado tras aprobar/rechazar un anticipo: si el mínimo ya
 * está cubierto pasa a 'en_desarrollo'; si no, queda en 'anticipo_pendiente'.
 */
function proyecto_recalcular_estado_anticipo($proyectoId): void
{
    $o = oid($proyectoId);
    if ($o === null) {
        return;
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return;
    }
    if (!in_array($proy['estado'] ?? '', ['documentacion', 'anticipo_pendiente'], true)) {
        return;
    }
    $nuevo = ((float)($proy['anticipo_pagado'] ?? 0) >= (float)($proy['anticipo_minimo'] ?? 0))
        ? 'en_desarrollo'
        : 'anticipo_pendiente';
    col_actualizar('proyectos_inicio', $o, ['$set' => ['estado' => $nuevo]]);
}

/** Resta un monto (anticipo rechazado) y recalcula el estado. */
function proyecto_descontar_anticipo($proyectoId, float $monto): void
{
    $o = oid($proyectoId);
    if ($o === null || $monto <= 0) {
        return;
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return;
    }
    $restante = max(0.0, (float)($proy['anticipo_pagado'] ?? 0) - $monto);
    col_actualizar('proyectos_inicio', $o, ['$set' => ['anticipo_pagado' => $restante]]);
    proyecto_recalcular_estado_anticipo($proyectoId);
}

/**
 * Cambio manual de estado (panel admin). 'en_desarrollo' sólo aplica si el
 * anticipo mínimo ya está cubierto. Devuelve el estado final aplicado o
 * '' si el proyecto no existe / el estado no es válido.
 */
function proyecto_cambiar_estado($proyectoId, string $estado): string
{
    if (!in_array($estado, proy_estados(), true)) {
        return '';
    }
    $o = oid($proyectoId);
    if ($o === null) {
        return '';
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return '';
    }
    if ($estado === 'en_desarrollo'
        && (float)($proy['anticipo_pagado'] ?? 0) < (float)($proy['anticipo_minimo'] ?? 0)) {
        $estado = 'anticipo_pendiente';
    }
    col('proyectos_inicio')->updateOne(
        ['_id' => $o],
        ['$set' => ['estado' => $estado, 'actualizado_en' => ahora_utc()]],
        mongo_opts([])
    );
    return $estado;
}

/* ---------- Suministros y saldo ---------- */

/**
 * Suma de pagos APROBADOS ligados a un proyecto. Cubre los pagos
 * históricos que sólo tienen solicitud_id (proyecto_id nulo), igual
 * que la consulta original con `(proyecto_id = ? OR (proyecto_id IS
 * NULL AND solicitud_id = ?))`.
 */
function pago_sumar_aprobados($proyectoId, $solicitudId = null): float
{
    $condiciones = [
        ['proyecto_id' => oid($proyectoId)],
    ];
    $oSol = oid($solicitudId);
    if ($oSol !== null) {
        $condiciones[] = ['proyecto_id' => null, 'solicitud_id' => $oSol];
    }

    return col_suma(
        'pagos',
        [
            'estado'    => 'aprobado',
            'tipo_pago' => ['$in' => ['anticipo', 'restante', 'completo']],
            '$or'       => $condiciones,
        ],
        'monto'
    );
}

/**
 * Central del ciclo financiero: recalcula pagado_total y saldo_restante
 * y lleva el proyecto a 'liquidado' cuando el saldo se cubrió (nunca
 * retrocede ni pisa un proyecto 'completado').
 *
 * Devuelve ['ok','proyecto_id','total','pagado','saldo','estado'].
 * Envuélvela en tx() cuando formees parte de una operación mayor.
 */
function proyecto_recalcular_saldo($proyectoId): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.', 'proyecto_id' => (string)$proyectoId];
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.', 'proyecto_id' => (string)$o];
    }

    $total = max(0.0, (float)($proy['total_proyecto'] ?? 0));
    $pagado = pago_sumar_aprobados($o, $proy['solicitud_id'] ?? null);
    $saldo = max(0.0, round($total - $pagado, 2));
    $estado = $proy['estado'] ?? 'documentacion';

    /* Liquidación: total definido y saldo cubierto (tolera 0.01). */
    if ($total > 0 && $saldo <= 0.01 && $estado !== 'completado') {
        $estado = 'liquidado';
    }

    col('proyectos_inicio')->updateOne(
        ['_id' => $o],
        ['$set' => [
            'pagado_total'    => $pagado,
            'saldo_restante'  => $saldo,
            'estado'          => $estado,
            'actualizado_en'  => ahora_utc(),
        ]],
        mongo_opts([])
    );

    /* Sincronización con la liquidación: si ya está liquidado, se
       reevalúa el avance para no dejar el proyecto a medio camino. */
    if ($estado === 'liquidado') {
        proy_evaluar_avance($o);
    }

    return [
        'ok'          => true,
        'proyecto_id' => (string)$o,
        'total'       => $total,
        'pagado'      => $pagado,
        'saldo'       => $saldo,
        'estado'      => $estado,
    ];
}

/** Total de un proyecto en formato legible. */
function proyecto_total_txt($proyectoId): string
{
    $proy = proy_por_id($proyectoId);
    $total = (float)($proy['total_proyecto'] ?? 0);
    return $total > 0 ? '$' . number_format($total, 2) . ' MXN' : 'A convenir';
}