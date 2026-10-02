<?php
/* ============================================================
   FV DIGITAL - Repositorio de proyectos del cliente
   ------------------------------------------------------------
   Colecciones:
     `proyectos_inicio`   El proyecto que nace de una solicitud.
     `entregables`        Archivos que el admin entrega al cliente.
     `proyecto_historial` Bitácora (estados, pagos, facturas).

   Estados del proyecto:
     documentacion → anticipo_pendiente → en_desarrollo
                  → liquidado → completado

   Reglas de automatización:
     · Liquidación  = saldo cubierto por pagos APROBADOS.
     · Conclusión   = avance >= 90% de los entregables marcados como
       listos, o bien todos los entregables listos, o bien el admin
       marca el proyecto como listo para entrega.
     · Al concluir se notifica al panel y al cliente.
   ============================================================ */

function proy_estados(): array
{
    return ['documentacion', 'anticipo_pendiente', 'en_desarrollo', 'liquidado', 'completado'];
}

/** Campos que el cliente puede escribir al abrir su proyecto. */
function proy_campos(array $d, array $base = []): array
{
    return [
        'solicitud_id'       => isset($d['solicitud_id']) ? oid($d['solicitud_id']) : ($base['solicitud_id'] ?? null),
        'usuario_id'         => isset($d['usuario_id']) ? oid($d['usuario_id']) : ($base['usuario_id'] ?? null),
        'descripcion_proyecto' => fv_texto($d['descripcion_proyecto'] ?? null),
        'requisitos'         => fv_texto($d['requisitos'] ?? null),
        'objetivos'          => fv_texto($d['objetivos'] ?? null),
        'alcance'            => fv_texto($d['alcance'] ?? null),
        'cronograma'         => fv_texto($d['cronograma'] ?? null),
        'entregables'        => fv_texto($d['entregables'] ?? null),
        'total_proyecto'     => max(0.0, fv_float($d['total_proyecto'] ?? ($base['total_proyecto'] ?? 0))),
    ];
}

function proy_crear(array $d): array
{
    $solicitudId = oid($d['solicitud_id'] ?? null);
    if ($solicitudId === null) {
        return ['ok' => false, 'mensaje' => 'La solicitud origen es obligatoria.'];
    }
    $usuarioId = oid($d['usuario_id'] ?? null);
    if ($usuarioId === null) {
        return ['ok' => false, 'mensaje' => 'El cliente es obligatorio.'];
    }

    $campos = proy_campos($d);
    $anticipoMinimo = max(0.0, fv_float($d['anticipo_minimo'] ?? 2500));
    $total = $campos['total_proyecto'];

    try {
        $id = col_agregar('proyectos_inicio', $campos + [
            'anticipo_minimo'  => $anticipoMinimo,
            'anticipo_pagado'  => 0.0,
            'pagado_total'     => 0.0,
            'saldo_restante'   => $total,
            'avance_porcentaje' => 0,
            'listo_entrega'    => false,
            'estado'           => 'documentacion',
            'creado_en'        => ahora_utc(),
        ]);
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo crear el proyecto: ' . $e->getMessage()];
    }

    registrar_historial_proyecto($id, 'proyecto_creado', 'El cliente abrió el proyecto y solicitó su cotización.');
    return ['ok' => true, 'id' => $id, 'mensaje' => 'Proyecto creado correctamente.'];
}

function proy_por_id($id): ?array
{
    return col_q1('proyectos_inicio', filtro_id($id));
}

function proy_por_solicitud($solicitudId, $usuarioId = null): ?array
{
    $f = ['solicitud_id' => oid($solicitudId)];
    if ($f['solicitud_id'] === null) {
        return null;
    }
    if ($usuarioId !== null && $usuarioId !== '' && $usuarioId !== 0) {
        $f['usuario_id'] = oid($usuarioId);
    }
    return col_q1('proyectos_inicio', $f);
}

/** Proyectos del cliente, opcionalmente filtrados por estado. */
function proy_de_usuario($usuarioId, array $estados = []): array
{
    $f = ['usuario_id' => oid($usuarioId)];
    if ($f['usuario_id'] === null) {
        return [];
    }
    if (!empty($estados)) {
        $f['estado'] = ['$in' => array_values($estados)];
    }
    return col_q('proyectos_inicio', $f, ['sort' => ['creado_en' => -1]]);
}

/** Proyectos con saldo pendiente (para el botón "pagar saldo"). */
function proy_con_saldo($usuarioId): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $filas = col_q(
        'proyectos_inicio',
        [
            'usuario_id'      => $o,
            'estado'          => ['$in' => ['anticipo_pendiente', 'en_desarrollo', 'documentacion']],
            'saldo_restante'  => ['$gt' => 0.01],
        ],
        ['sort' => ['creado_en' => -1]]
    );
    if (empty($filas)) {
        return [];
    }

    /* Enriquece con la solicitud de origen (tipo de servicio y presupuesto). */
    $solicitudes = sol_por_ids(array_values(array_filter(array_map(
        static fn($p) => (string)($p['solicitud_id'] ?? ''),
        $filas
    ))));
    foreach ($filas as &$p) {
        $sol = $solicitudes[(string)($p['solicitud_id'] ?? '')] ?? null;
        $p['tipo_servicio'] = $sol['tipo_servicio'] ?? '';
        $p['presupuesto']   = $sol['presupuesto'] ?? '';
    }
    unset($p);

    return $filas;
}

/** Todos los proyectos para el panel, con filtros. */
function proy_listar(array $filtros = []): array
{
    $f = [];
    if (!empty($filtros['estado']) && in_array($filtros['estado'], proy_estados(), true)) {
        $f['estado'] = $filtros['estado'];
    }
    if (!empty($filtros['usuario_id'])) {
        $f['usuario_id'] = oid($filtros['usuario_id']);
    }
    return col_q('proyectos_inicio', $f, ['sort' => ['creado_en' => -1]]);
}

/** Sólo el _id de todos los proyectos (para consultas $in). */
function proy_ids(array $filtros = []): array
{
    $filas = col_q('proyectos_inicio', $filtros, ['projection' => ['_id' => 1]]);
    return array_map(static fn($f) => $f['id'], $filas);
}

/**
 * Proyecto con los datos de su cliente y de la solicitud de origen.
 * Reemplaza el JOIN de tres tablas de MySQL.
 */
function proyecto_datos($proyectoId): ?array
{
    $proy = proy_por_id($proyectoId);
    if ($proy === null) {
        return null;
    }

    $cliente = null;
    if (!empty($proy['usuario_id'])) {
        $cliente = usr_por_id($proy['usuario_id']);
    }

    $solicitud = null;
    if (!empty($proy['solicitud_id'])) {
        $solicitud = sol_por_id($proy['solicitud_id']);
    }

    return $proy + [
        'cliente_id'      => $cliente['id'] ?? null,
        'cliente_nombre'  => $cliente['nombre'] ?? '',
        'cliente_email'   => $cliente['email'] ?? '',
        'tipo_servicio'   => $solicitud['tipo_servicio'] ?? '',
        'presupuesto'     => $solicitud['presupuesto'] ?? '',
        'sol_id'          => $solicitud['id'] ?? null,
    ];
}

/** Proyecto + cliente + solicitud, para listados del panel. */
function proy_con_cliente(array $proyecto): array
{
    if (empty($proyecto['usuario_id']) && empty($proyecto['solicitud_id'])) {
        return $proyecto;
    }
    $cliente = !empty($proyecto['usuario_id']) ? usr_por_id($proyecto['usuario_id']) : null;
    $solicitud = !empty($proyecto['solicitud_id']) ? sol_por_id($proyecto['solicitud_id']) : null;

    return $proyecto + [
        'cliente_nombre' => $cliente['nombre'] ?? '',
        'cliente_email'  => $cliente['email'] ?? '',
        'tipo_servicio'  => $solicitud['tipo_servicio'] ?? '',
        'presupuesto'    => $solicitud['presupuesto'] ?? '',
        'solicitud'      => $solicitud,
        'cliente'        => $cliente,
    ];
}

/**
 * Proyectos del panel "Procesos": filtra por estado y añade en una sola
 * pasada el cliente, la solicitud de origen y el número de entregables.
 *
 * Reemplaza el SELECT con tres LEFT JOIN + el COUNT agrupado por proyecto.
 *
 * @param array $estados Lista de estados (vacío = todos).
 */
function proy_panel_procesos(array $estados, int $limite = 0): array
{
    $f = [];
    if (!empty($estados)) {
        $f['estado'] = ['$in' => array_values($estados)];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }

    $filas = col_q('proyectos_inicio', $f, $op);
    if (empty($filas)) {
        return [];
    }

    /* Cliente y solicitud de todos los proyectos, en dos consultas. */
    $usuarios = [];
    $sols     = [];
    foreach ($filas as $p) {
        if (!empty($p['usuario_id'])) {
            $usuarios[(string)oid($p['usuario_id'])] = oid($p['usuario_id']);
        }
        if (!empty($p['solicitud_id'])) {
            $sols[(string)oid($p['solicitud_id'])] = oid($p['solicitud_id']);
        }
    }
    $usuarios = usr_por_ids(array_values($usuarios));
    $sols     = sol_por_ids(array_values($sols));

    /* Conteo de entregables por proyecto (una consulta agrupada). */
    $entregables = ent_por_proyectos(array_map(static fn($p) => $p['id'], $filas));

    foreach ($filas as &$p) {
        $claveUsuario = (string)oid($p['usuario_id'] ?? null);
        $claveSol     = (string)oid($p['solicitud_id'] ?? null);
        $p['cliente_nombre'] = $usuarios[$claveUsuario]['nombre'] ?? '';
        $p['cliente_email']  = $usuarios[$claveUsuario]['email'] ?? '';
        $p['tipo_servicio']  = $sols[$claveSol]['tipo_servicio'] ?? '';
        $p['presupuesto']    = $sols[$claveSol]['presupuesto'] ?? '';
        $p['entregables']    = count($entregables[(string)$p['id']] ?? []);
    }
    unset($p);

    return $filas;
}

/**
 * Bitácora global del panel "Procesos": los últimos movimientos de todos
 * los proyectos con el nombre de quien los hizo y el de su cliente.
 *
 * Reemplaza el SELECT de cinco LEFT JOIN con LIMIT 300.
 */
function proy_historial_panel(int $limite = 300): array
{
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }

    $filas = col_q('proyecto_historial', [], $op);
    if (empty($filas)) {
        return [];
    }

    /* Proyectos (cliente + solicitud) y actores, en dos consultas. */
    $proyOids = [];
    $actores  = [];
    foreach ($filas as $h) {
        if (!empty($h['proyecto_id'])) {
            $proyOids[(string)oid($h['proyecto_id'])] = oid($h['proyecto_id']);
        }
        if (!empty($h['usuario_id'])) {
            $actores[(string)oid($h['usuario_id'])] = oid($h['usuario_id']);
        }
    }

    $proyectos = col_q('proyectos_inicio', ['_id' => ['$in' => array_values($proyOids)]]);
    $usuarios  = usr_por_ids(array_merge(
        array_values($actores),
        array_values(array_unique(array_map(static fn($p) => oid($p['usuario_id'] ?? null), $proyectos), SORT_REGULAR))
    ));
    $sols = sol_por_ids(array_values(array_filter(
        array_map(static fn($p) => oid($p['solicitud_id'] ?? null), $proyectos),
        static fn($o) => $o !== null
    )));

    $porProyecto = [];
    foreach ($proyectos as $p) {
        $claveUsuario = (string)oid($p['usuario_id'] ?? null);
        $claveSol     = (string)oid($p['solicitud_id'] ?? null);
        $porProyecto[(string)$p['id']] = [
            'cliente_nombre' => $usuarios[$claveUsuario]['nombre'] ?? '',
            'tipo_servicio'  => $sols[$claveSol]['tipo_servicio'] ?? '',
        ];
    }

    foreach ($filas as &$h) {
        $claveActor = (string)oid($h['usuario_id'] ?? null);
        $datos      = $porProyecto[(string)oid($h['proyecto_id'] ?? null)] ?? [];
        $h['proy_id']         = (string)oid($h['proyecto_id'] ?? null);
        $h['usuario_nombre']  = $usuarios[$claveActor]['nombre'] ?? null;
        $h['cliente_nombre']  = $datos['cliente_nombre'] ?? '';
        $h['tipo_servicio']   = $datos['tipo_servicio'] ?? '';
    }
    unset($h);

    return $filas;
}

/* ============================================================
   Bitácora del proyecto
   ============================================================ */

/**
 * Registra un evento en la bitácora del proyecto.
 * $usuarioId: id del actor (admin o cliente). Si se omite, se toma de la
 * sesión actual. Acepta el hex del ObjectId, no un entero.
 */
function registrar_historial_proyecto($proyectoId, string $accion, string $detalle = '', $usuarioId = null): void
{
    $o = oid($proyectoId);
    if ($o === null) {
        return;
    }
    $oUsuario = $usuarioId !== null
        ? oid($usuarioId)
        : oid($_SESSION['admin_id'] ?? ($_SESSION['usuario_id'] ?? null));

    try {
        col_agregar('proyecto_historial', [
            'proyecto_id' => $o,
            'usuario_id'  => $oUsuario,
            'accion'      => $accion,
            'detalle'     => $detalle !== '' ? $detalle : null,
            'creado_en'   => ahora_utc(),
        ]);
    } catch (\Throwable $e) {
        error_log('[FV-Mongo] historial proyecto: ' . $e->getMessage());
    }
}

function proy_historial($proyectoId, int $limite = 0): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return [];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('proyecto_historial', ['proyecto_id' => $o], $op);
}

/** Bitácora de varios proyectos a la vez, para listados del panel. */
function proy_historiales(array $idsProyectos, int $limitePorProyecto = 0): array
{
    $oids = [];
    foreach ($idsProyectos as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $filas = col_q('proyecto_historial', ['proyecto_id' => ['$in' => $oids]], ['sort' => ['creado_en' => -1]]);
    $agrupado = [];
    foreach ($filas as $f) {
        $clave = (string)$f['proyecto_id'];
        if ($limitePorProyecto > 0 && isset($agrupado[$clave]) && count($agrupado[$clave]) >= $limitePorProyecto) {
            continue;
        }
        $agrupado[$clave][] = $f;
    }
    return $agrupado;
}

/**
 * Bitácora plana (no agrupada) de varios proyectos, para el historial
 * del cliente. Enriquece con el tipo de servicio de la solicitud.
 */
function proy_historial_plano(array $idsProyectos, int $limite = 0): array
{
    $oids = [];
    foreach ($idsProyectos as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[(string)$o] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    $filas = col_q('proyecto_historial', ['proyecto_id' => ['$in' => array_values($oids)]], $op);
    if (empty($filas)) {
        return [];
    }

    /* Solicitudes de origen de cada proyecto, en una sola consulta. */
    $proyectos = col_q(
        'proyectos_inicio',
        ['_id' => ['$in' => array_values($oids)]],
        ['projection' => ['solicitud_id' => 1]]
    );
    $sols = sol_por_ids(array_map(static fn($p) => (string)($p['solicitud_id'] ?? ''), $proyectos));

    $tiposPorProyecto = [];
    foreach ($proyectos as $p) {
        $clave = (string)($p['solicitud_id'] ?? '');
        if ($clave === '') {
            continue;
        }
        $tiposPorProyecto[(string)$p['id']] = $sols[$clave]['tipo_servicio'] ?? '';
    }
    foreach ($filas as &$f) {
        $f['proy_id'] = (string)($f['proyecto_id'] ?? '');
        $f['tipo_servicio'] = $tiposPorProyecto[$f['proy_id']] ?? '';
    }
    unset($f);

    return $filas;
}

/* ============================================================
   Entregables y avance
   ============================================================ */

/** Registra un entregable. Devuelve ['ok','id','mensaje']. */
function ent_crear($proyectoId, array $d): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.'];
    }
    $titulo = trim((string)($d['titulo'] ?? ''));
    $archivo = trim((string)($d['archivo'] ?? ''));
    if ($titulo === '') {
        return ['ok' => false, 'mensaje' => 'El título del entregable es obligatorio.'];
    }
    if ($archivo === '') {
        return ['ok' => false, 'mensaje' => 'Debes adjuntar el archivo del entregable.'];
    }

    $id = col_agregar('entregables', [
        'proyecto_id'   => $o,
        'titulo'        => $titulo,
        'archivo'       => $archivo,
        'notas'         => (string)($d['notas'] ?? ''),
        'listo_entrega' => !empty($d['listo_entrega']),
        'creado_en'     => ahora_utc(),
    ]);

    registrar_historial_proyecto($proyectoId, 'entregable_agregado', 'Se agregó el entregable "' . $titulo . '".');
    proy_evaluar_avance($proyectoId);

    return ['ok' => true, 'id' => $id, 'mensaje' => 'Entregable registrado.'];
}

function ent_por_id($id): ?array
{
    return col_q1('entregables', filtro_id($id));
}

/** Entregables de un proyecto (más recientes primero). */
function ent_de_proyecto($proyectoId): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return [];
    }
    return col_q('entregables', ['proyecto_id' => $o], ['sort' => ['creado_en' => -1]]);
}

/** Entregables de varios proyectos, agrupados por proyecto. */
function ent_por_proyectos(array $idsProyectos): array
{
    $oids = [];
    foreach ($idsProyectos as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $filas = col_q('entregables', ['proyecto_id' => ['$in' => $oids]], ['sort' => ['creado_en' => -1]]);
    $agrupado = [];
    foreach ($filas as $f) {
        $agrupado[(string)$f['proyecto_id']][] = $f;
    }
    return $agrupado;
}

/**
 * Todos los entregables con el cliente, el servicio y el estado de su
 * proyecto: la vista "Archivos" del panel de facturación.
 *
 * Reemplaza el SELECT de entregables con tres LEFT JOIN.
 */
function ent_panel_completo(int $limite = 0): array
{
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    $entregables = col_q('entregables', [], $op);
    if (empty($entregables)) {
        return [];
    }

    /* Proyectos de los entregables, y sus clientes y solicitudes. */
    $proyOids = [];
    foreach ($entregables as $en) {
        if (!empty($en['proyecto_id'])) {
            $proyOids[(string)oid($en['proyecto_id'])] = oid($en['proyecto_id']);
        }
    }
    $proyectos = $proyOids
        ? col_q('proyectos_inicio', ['_id' => ['$in' => array_values($proyOids)]])
        : [];

    $usuarios = usr_por_ids(array_values(array_filter(
        array_unique(array_map(static fn($p) => oid($p['usuario_id'] ?? null), $proyectos), SORT_REGULAR),
        static fn($o) => $o !== null
    )));
    $sols = sol_por_ids(array_values(array_filter(
        array_unique(array_map(static fn($p) => oid($p['solicitud_id'] ?? null), $proyectos), SORT_REGULAR),
        static fn($o) => $o !== null
    )));

    $datosProy = [];
    foreach ($proyectos as $p) {
        $claveUsuario = (string)oid($p['usuario_id'] ?? null);
        $claveSol     = (string)oid($p['solicitud_id'] ?? null);
        $datosProy[(string)$p['id']] = [
            'usuario_id'       => $p['usuario_id'] ?? null,
            'cliente_nombre'   => $usuarios[$claveUsuario]['nombre'] ?? '',
            'cliente_email'    => $usuarios[$claveUsuario]['email'] ?? '',
            'tipo_servicio'    => $sols[$claveSol]['tipo_servicio'] ?? '',
            'proyecto_estado'  => $p['estado'] ?? '',
        ];
    }

    foreach ($entregables as &$en) {
        $clave = (string)oid($en['proyecto_id'] ?? null);
        $datos = $datosProy[$clave] ?? [];
        $en['usuario_id']      = $datos['usuario_id'] ?? null;
        $en['cliente_nombre']  = $datos['cliente_nombre'] ?? '';
        $en['cliente_email']   = $datos['cliente_email'] ?? '';
        $en['tipo_servicio']   = $datos['tipo_servicio'] ?? '';
        $en['proyecto_estado'] = $datos['proyecto_estado'] ?? '';
    }
    unset($en);

    return $entregables;
}

function ent_eliminar($id): bool
{
    $ent = ent_por_id($id);
    if ($ent === null) {
        return false;
    }
    $borrado = col_borrar('entregables', $id);
    if ($borrado && !empty($ent['proyecto_id'])) {
        proy_evaluar_avance($ent['proyecto_id']);
    }
    return $borrado;
}

/** Marca / desmarca un entregable como entregado. */
function ent_alternar_listo($id, bool $listo): array
{
    $ent = ent_por_id($id);
    if ($ent === null) {
        return ['ok' => false, 'mensaje' => 'Entregable no encontrado.'];
    }
    col_actualizar('entregables', $id, ['$set' => ['listo_entrega' => $listo]]);
    $avance = proy_evaluar_avance($ent['proyecto_id']);
    return ['ok' => true, 'mensaje' => $listo ? 'Entregable marcado como entregado.' : 'Entregable revertido.', 'avance' => $avance];
}

/**
 * Recalcula el avance de un proyecto a partir de sus entregables.
 * Regla: avance = (entregables marcados listos / total de entregables) * 100.
 * Si no hay entregables, se conserva el avance previo.
 *
 * Devuelve ['avance' => int, 'total' => int, 'listos' => int,
 *           'listo_entrega' => bool, 'concluido' => bool].
 */
function proy_recalcular_avance($proyectoId): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return ['avance' => 0, 'total' => 0, 'listos' => 0, 'listo_entrega' => false, 'concluido' => false];
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return ['avance' => 0, 'total' => 0, 'listos' => 0, 'listo_entrega' => false, 'concluido' => false];
    }

    $entregables = col_q('entregables', ['proyecto_id' => $o], ['projection' => ['listo_entrega' => 1]]);
    $total = count($entregables);
    $listos = 0;
    foreach ($entregables as $e) {
        if (!empty($e['listo_entrega'])) {
            $listos++;
        }
    }

    $avance = $total > 0 ? (int)round(($listos / $total) * 100) : (int)($proy['avance_porcentaje'] ?? 0);
    $todosListos = $total > 0 && $listos === $total;
    $listoEntrega = !empty($proy['listo_entrega']) || $todosListos;

    col('proyectos_inicio')->updateOne(
        ['_id' => $o],
        ['$set' => ['avance_porcentaje' => $avance, 'listo_entrega' => $listoEntrega]],
        mongo_opts([])
    );

    return [
        'avance'       => $avance,
        'total'        => $total,
        'listos'       => $listos,
        'listo_entrega' => $listoEntrega,
        'concluido'    => $avance >= 90 || $listoEntrega,
    ];
}

/**
 * AUTOMATIZACIÓN DE CONCLUSIÓN.
 * Si el avance alcanza el 90% o el proyecto está listo para entrega,
 * el proyecto pasa a 'completado' y se notifica al panel y al cliente.
 * Devuelve el arreglo de `proy_recalcular_avance`.
 */
function proy_evaluar_avance($proyectoId): array
{
    $avance = proy_recalcular_avance($proyectoId);

    $o = oid($proyectoId);
    if ($o === null) {
        return $avance;
    }
    $proy = col_q1('proyectos_inicio', ['_id' => $o]);
    if ($proy === null) {
        return $avance;
    }

    $yaCompletado = ($proy['estado'] ?? '') === 'completado';
    if ($avance['concluido'] && !$yaCompletado) {
        col('proyectos_inicio')->updateOne(
            ['_id' => $o, 'estado' => ['$ne' => 'completado']],
            ['$set' => ['estado' => 'completado', 'completado_en' => ahora_utc()]],
            mongo_opts([])
        );
        $motivo = $avance['listo_entrega']
            ? 'Todos los entregables están listos para entrega.'
            : 'El alcance del proyecto llegó al ' . $avance['avance'] . '%.';
        registrar_historial_proyecto($proyectoId, 'proyecto_concluido', 'El proyecto se marcaró como CONCLUIDO. ' . $motivo);

        $datos = proyecto_datos($o);
        $clienteId = $proy['usuario_id'] ?? null;
        if ($clienteId !== null) {
            notificar(
                $clienteId,
                'exito',
                'Tu proyecto está concluido',
                'El proyecto "' . ($datos['tipo_servicio'] ?: 'FV Digital') . '" fue marcado como concluido. ' . $motivo
                . ' Descarga tus entregables desde el portal.',
                url_sitio('portal/procesos.php')
            );
        }
        notificar_admins(
            'exito',
            'Proyecto concluido automáticamente',
            'El proyecto #' . (string)$o . ' de ' . ($datos['cliente_nombre'] ?: 'un cliente') . ' alcanzó el ' . $avance['avance'] . '% de avance.',
            url_sitio('admin/entregables.php')
        );
    }

    return $avance;
}

/** Marca (o revierte) manualmente el proyecto como listo para entrega. */
function proy_alternar_listo($proyectoId, bool $listo): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.'];
    }
    col('proyectos_inicio')->updateOne(
        ['_id' => $o],
        ['$set' => ['listo_entrega' => $listo, 'actualizado_en' => ahora_utc()]],
        mongo_opts([])
    );
    registrar_historial_proyecto(
        $proyectoId,
        $listo ? 'listo_para_entrega' : 'entrega_pendiente',
        $listo ? 'El administrador marcó el proyecto como listo para entrega.' : 'Se quitó la marca de listo para entrega.'
    );
    $avance = proy_evaluar_avance($proyectoId);
    return ['ok' => true, 'mensaje' => $listo ? 'Proyecto marcado como listo para entrega.' : 'Marca de entrega retirada.', 'avance' => $avance];
}