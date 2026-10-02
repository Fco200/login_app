<?php
/* ============================================================
   FV DIGITAL - Repositorio de métodos de pago, pagos y carrito
   ------------------------------------------------------------
   Colecciones:
     `metodos_pago`  Métodos publicados por el admin (editables
                     desde el panel; el portal los lee en vivo).
     `pagos`         Pagos de anticipo, saldo y productos.
     `carrito`       Renglones de compra del cliente.
   ============================================================ */

/* ============================================================
   Métodos de pago (editables por el administrador)
   ============================================================ */

/** Métodos publicados: alimenta el carrito, el checkout y el modal de saldo. */
function mp_activos(): array
{
    return col_q('metodos_pago', ['activo' => 1], ['sort' => ['nombre' => 1]]);
}

function mp_todos(): array
{
    return col_q('metodos_pago', [], ['sort' => ['nombre' => 1]]);
}

function mp_por_id($id): ?array
{
    return col_q1('metodos_pago', filtro_id($id));
}

function mp_guardar($id, array $d): array
{
    $nombre = trim((string)($d['nombre'] ?? ''));
    if ($nombre === '') {
        return ['ok' => false, 'mensaje' => 'El nombre del método de pago es obligatorio.'];
    }
    $campos = [
        'nombre'          => $nombre,
        'descripcion'     => fv_texto($d['descripcion'] ?? null),
        'detalles_cuenta' => fv_texto($d['detalles_cuenta'] ?? null),
        'instrucciones'   => fv_texto($d['instrucciones'] ?? null),
        'icono'           => fv_texto($d['icono'] ?? 'bi-credit-card') ?? 'bi-credit-card',
        'activo'          => fv_int($d['activo'] ?? 1, 1),
    ];
    return !es_nuevo($id)
        ? crud_actualizar('metodos_pago', $id, $campos)
        : crud_crear('metodos_pago', $campos);
}

/** Activa o desactiva un método (baja lógica). */
function mp_alternar_activo($id, bool $activo): array
{
    if (oid($id) === null) {
        return ['ok' => false, 'mensaje' => 'Método de pago no encontrado.'];
    }
    col_actualizar('metodos_pago', $id, ['$set' => ['activo' => $activo ? 1 : 0]]);
    return ['ok' => true, 'mensaje' => $activo ? 'Método de pago activado.' : 'Método de pago desactivado.'];
}

/**
 * Ficha completa de un método, tal y como la consume la UI del portal.
 * Se devuelve normalizada para poder renderizar la tarjeta Glassmorphism
 * sin tener que conocer la forma del documento.
 */
function mp_ficha(array $mp): array
{
    return [
        'id'              => (string)$mp['id'],
        'nombre'          => (string)($mp['nombre'] ?? ''),
        'descripcion'     => (string)($mp['descripcion'] ?? ''),
        'detalles_cuenta' => (string)($mp['detalles_cuenta'] ?? ''),
        'instrucciones'   => (string)($mp['instrucciones'] ?? ''),
        'icono'           => (string)($mp['icono'] ?? 'bi-credit-card'),
    ];
}

/* ============================================================
   Pagos
   ============================================================ */

function pag_estados(): array
{
    return ['pendiente', 'aprobado', 'rechazado'];
}

function pag_tipos(): array
{
    return ['anticipo', 'restante', 'completo', 'producto'];
}

function pag_por_id($id): ?array
{
    return col_q1('pagos', filtro_id($id));
}

function pag_por_clave(string $clave): ?array
{
    if ($clave === '') {
        return null;
    }
    return col_q1('pagos', ['clave_unica' => $clave]);
}

function pag_por_clave_fuzzy(string $clave): ?array
{
    if ($clave === '') {
        return null;
    }
    return col_q1('pagos', ['clave_unica' => rx('^' . str_replace('*', '.*', preg_quote($clave, '/')) . '$')]);
}

/** Pago + método + cliente + solicitud + proyecto (reemplaza los JOIN). */
function pag_completo($id): ?array
{
    $pago = pag_por_id($id);
    if ($pago === null) {
        return null;
    }
    return pag_con_relaciones($pago);
}

/** Enriquece un pago con las colecciones relacionadas. */
function pag_con_relaciones(array $pago): array
{
    $cliente = !empty($pago['usuario_id']) ? usr_por_id($pago['usuario_id']) : null;
    $metodo  = !empty($pago['metodo_pago_id']) ? mp_por_id($pago['metodo_pago_id']) : null;
    $solicitud = !empty($pago['solicitud_id']) ? sol_por_id($pago['solicitud_id']) : null;
    $proyecto  = !empty($pago['proyecto_id']) ? proy_por_id($pago['proyecto_id']) : null;

    return $pago + [
        'cliente_nombre'   => $cliente['nombre'] ?? '',
        'cliente_email'    => $cliente['email'] ?? '',
        'cliente_telefono' => $cliente['telefono'] ?? '',
        'metodo_nombre'    => $metodo['nombre'] ?? '',
        'tipo_servicio'    => $solicitud['tipo_servicio'] ?? '',
        /* Alias heredado: varias vistas imprimen "concepto". */
        'concepto'         => $solicitud['tipo_servicio'] ?? '',
        'proyecto'         => $proyecto,
        'total_proyecto'   => $proyecto['total_proyecto'] ?? 0,
        'pagado_total'     => $proyecto['pagado_total'] ?? 0,
        'saldo_restante'   => $proyecto['saldo_restante'] ?? 0,
        'proyecto_estado'  => $proyecto['estado'] ?? '',
    ];
}

/** Pagos del cliente, más recientes primero. */
function pag_de_usuario($usuarioId, array $estados = [], int $limite = 0): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $f = ['usuario_id' => $o];
    if (!empty($estados)) {
        $f['estado'] = ['$in' => array_values($estados)];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('pagos', $f, $op);
}

/**
 * Pagos del cliente vinculados a una lista de proyectos o solicitudes.
 *
 * Cubre los dos casos del flujo: pagos con `proyecto_id` y pagos
 * heredados que sólo apuntan a la `solicitud_id`.
 */
function pag_de_usuario_proyectos($usuarioId, array $idsProyectos = [], array $idsSolicitudes = []): array
{
    $oUsuario = oid($usuarioId);
    if ($oUsuario === null) {
        return [];
    }

    $condiciones = [];
    $oProy = [];
    foreach ($idsProyectos as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oProy[] = $o;
        }
    }
    $oSol = [];
    foreach ($idsSolicitudes as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oSol[] = $o;
        }
    }
    if (!empty($oProy)) {
        $condiciones[] = ['proyecto_id' => ['$in' => $oProy]];
    }
    if (!empty($oSol)) {
        $condiciones[] = ['solicitud_id' => ['$in' => $oSol]];
    }
    if (empty($condiciones)) {
        return [];
    }

    return col_q(
        'pagos',
        ['usuario_id' => $oUsuario, '$or' => $condiciones],
        ['sort' => ['creado_en' => -1]]
    );
}

/** Todos los pagos para el panel, enriquecidos para la tabla. */
function pag_listar_panel(): array
{
    $pagos = col_q('pagos', [], ['sort' => ['creado_en' => -1]]);
    return $pagos; // el panel enrichce con pag_con_relaciones
}

/**
 * Clientes que tienen al menos un pago, para el filtro del panel.
 * Devuelve [['id' => hex, 'nombre' => …, 'email' => …], …].
 */
function pag_clientes_con_pagos(): array
{
    try {
        $filas = col('pagos')->aggregate([
            ['$group' => ['_id' => '$usuario_id']],
            ['$match' => ['_id' => ['$ne' => null]]],
            ['$sort' => ['_id' => 1]],
        ], mongo_opts([]))->toArray();
    } catch (\Throwable $e) {
        return [];
    }
    if (empty($filas)) {
        return [];
    }
    $usuarios = usr_por_ids(array_map(static fn($f) => (string)$f['_id'], $filas));
    $out = [];
    foreach ($filas as $fila) {
        $u = $usuarios[(string)$fila['_id']] ?? null;
        if ($u !== null) {
            $out[] = ['id' => (string)$u['id'], 'nombre' => (string)($u['nombre'] ?? ''), 'email' => (string)($u['email'] ?? '')];
        }
    }
    usort($out, static fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
    return $out;
}

/** Pagos por estado (para el panel de cobros). */
function pag_por_estado(string $estado, int $limite = 0): array
{
    if (!in_array($estado, pag_estados(), true)) {
        return [];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('pagos', ['estado' => $estado], $op);
}

/** Pagos pendientes de aprobación, con cliente y método (panel). */
function pag_pendientes_aprobacion(int $limite = 8): array
{
    $filas = pag_por_estado('pendiente', $limite);
    return array_map('pag_con_relaciones', $filas);
}

/* ---------- Reportes financieros (reemplazan los GROUP BY de MySQL) ---------- */

/** Ingresos aprobados por mes: [['mes' => '2026-01', 'total' => 1234.0], ...]. */
function pagos_ingresos_por_mes(int $meses = 6): array
{
    $desde = new \DateTimeImmutable('first day of this month 00:00:00', fv_tz());
    $desde = $desde->modify('-' . max(0, $meses - 1) . ' months');
    $pipeline = [
        ['$match' => ['estado' => 'aprobado', 'creado_en' => ['$gte' => fecha_utc($desde)]]],
        ['$group' => [
            '_id'   => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$creado_en']],
            'total' => ['$sum' => '$monto'],
        ]],
        ['$sort' => ['_id' => 1]],
    ];
    $filas = col('pagos')->aggregate($pipeline, mongo_opts([]))->toArray();

    $out = [];
    foreach ($filas as $fila) {
        $out[(string)$fila['_id']] = (float)$fila['total'];
    }
    /* Rellena los meses sin ingresos para que la gráfica no tenga huecos. */
    $serie = [];
    for ($i = $meses - 1; $i >= 0; $i--) {
        $ym = $desde->modify("+{$i} months")->format('Y-m');
        $serie[] = ['mes' => $ym, 'total' => $out[$ym] ?? 0.0];
    }
    return $serie;
}

/** Clientes con más facturación por pagos aprobados. */
function pagos_top_clientes(int $limite = 5): array
{
    $pipeline = [
        ['$match' => ['estado' => 'aprobado']],
        ['$group' => ['_id' => '$usuario_id', 'total' => ['$sum' => '$monto'], 'n' => ['$sum' => 1]]],
        ['$sort' => ['total' => -1]],
        ['$limit' => $limite],
    ];
    $filas = col('pagos')->aggregate($pipeline, mongo_opts([]))->toArray();

    $out = [];
    foreach ($filas as $fila) {
        $usuario = usr_por_id($fila['_id']);
        $out[] = [
            'id'    => (string)$fila['_id'],
            'nombre'=> $usuario['nombre'] ?? 'Cliente',
            'email' => $usuario['email'] ?? '',
            'total' => (float)$fila['total'],
            'n'     => (int)$fila['n'],
        ];
    }
    return $out;
}

/** Métricas financieras del panel. */
function pagos_metricas(): array
{
    $aprobado = ['estado' => 'aprobado'];
    return [
        'ingresos_total'   => col_suma('pagos', $aprobado, 'monto'),
        'pagos_aprobados'  => col_contar('pagos', $aprobado),
        'pendiente_monto'  => col_suma('pagos', ['estado' => 'pendiente'], 'monto'),
        'ticket_promedio'  => col_promedio('pagos', $aprobado, 'monto'),
    ];
}

/**
 * Pagos aprobados y desglosados de un proyecto (para facturas y API).
 * Incluye los pagos históricos ligados sólo por solicitud_id.
 */
function proyecto_pagos_aprobados_detalle($proyectoId): array
{
    $proy = proy_por_id($proyectoId);
    if ($proy === null) {
        return [];
    }
    $condiciones = [['proyecto_id' => oid($proyectoId)]];
    $oSol = oid($proy['solicitud_id'] ?? null);
    if ($oSol !== null) {
        $condiciones[] = ['proyecto_id' => null, 'solicitud_id' => $oSol];
    }

    $filas = col_q(
        'pagos',
        [
            'estado'    => 'aprobado',
            'tipo_pago' => ['$in' => ['anticipo', 'restante', 'completo']],
            '$or'       => $condiciones,
        ],
        ['sort' => ['creado_en' => 1]]
    );

    $pagos = [];
    foreach ($filas as $p) {
        $pagos[] = [
            'id'    => (string)$p['id'],
            'folio' => 'PAG-' . str_pad(counter_partes($p['id']), 5, '0', STR_PAD_LEFT),
            'fecha' => fecha_php($p['creado_en'] ?? '', 'd/m/Y'),
            'monto' => (float)($p['monto'] ?? 0),
            'tipo'  => (string)($p['tipo_pago'] ?? 'anticipo'),
        ];
    }
    return $pagos;
}

/* ---------- Registro y aprobación ---------- */

/**
 * Token de idempotencia para un pago: único por contexto de envío
 * (evita el doble clic y el F5 duplicando un pago).
 */
function pago_generar_clave(array $ctx): string
{
    $base = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash_hmac('sha256', (string)$base, bin2hex(random_bytes(16)));
}

/**
 * Registro central de un pago ligado a un proyecto, con idempotencia
 * por clave_unica, validación de saldo y bitácora.
 *
 * $d: usuario_id, proyecto_id, tipo_pago (anticipo|restante|completo),
 *     monto, metodo_pago_id, comprobante, notas, clave_unica.
 * Devuelve ['ok','ya_existia','pago_id','proyecto_id','mensaje'].
 */
function pago_registrar(array $d): array
{
    $usuarioId  = $d['usuario_id'] ?? 0;
    $proyectoId = $d['proyecto_id'] ?? 0;
    $tipo  = (string)($d['tipo_pago'] ?? '');
    $monto = (float)($d['monto'] ?? 0);
    $metodoId = $d['metodo_pago_id'] ?? null;
    $clave = trim((string)($d['clave_unica'] ?? ''));
    $notas = trim((string)($d['notas'] ?? ''));

    if (oid($usuarioId) === null || oid($proyectoId) === null || $monto <= 0
        || !in_array($tipo, ['anticipo', 'restante', 'completo'], true)) {
        return ['ok' => false, 'mensaje' => 'Datos inválidos para registrar el pago.'];
    }
    if ($clave === '') {
        $clave = pago_generar_clave($d);
    }

    /* Idempotencia: el mismo token (F5 / doble clic) nunca duplica. */
    $ya = pag_por_clave($clave);
    if ($ya !== null) {
        return [
            'ok' => true, 'ya_existia' => true,
            'pago_id' => (string)$ya['id'],
            'proyecto_id' => (string)($ya['proyecto_id'] ?? ''),
            'mensaje' => 'Tu pago ya había sido registrado; no se duplicó.',
        ];
    }

    /* Pertenencia del proyecto al cliente. */
    $proy = col_q1('proyectos_inicio', ['_id' => oid($proyectoId), 'usuario_id' => oid($usuarioId)]);
    if ($proy === null) {
        return ['ok' => false, 'mensaje' => 'El proyecto no existe o no te pertenece.'];
    }
    if ($tipo === 'anticipo' && $monto < (float)($proy['anticipo_minimo'] ?? 0)) {
        return ['ok' => false, 'mensaje' => 'El anticipo mínimo es $' . number_format((float)($proy['anticipo_minimo'] ?? 0), 0) . ' MXN.'];
    }
    $total = (float)($proy['total_proyecto'] ?? 0);
    $saldoActual = (float)($proy['saldo_restante'] ?? 0);
    if ($total > 0 && in_array($tipo, ['restante', 'completo'], true) && $monto > $saldoActual + 0.01) {
        return ['ok' => false, 'mensaje' => 'El monto supera el saldo restante del proyecto ($' . number_format($saldoActual, 0) . ' MXN).'];
    }

    try {
        $pagoId = tx(static function () use ($proy, $usuarioId, $proyectoId, $tipo, $monto, $metodoId, $d, $clave, $notas) {
            $nuevoId = col_agregar('pagos', [
                'usuario_id'     => oid($usuarioId),
                'solicitud_id'   => $proy['solicitud_id'] ?? null,
                'proyecto_id'    => oid($proyectoId),
                'metodo_pago_id' => oid($metodoId),
                'monto'          => $monto,
                'tipo_pago'      => $tipo,
                'comprobante'    => $d['comprobante'] ?? null,
                'estado'         => 'pendiente',
                'aplicado'       => false,
                'notas'          => $notas !== '' ? $notas : null,
                'clave_unica'    => $clave,
                'creado_en'      => ahora_utc(),
            ]);

            /* Avance visual del cliente: el anticipo enviado se refleja
               de inmediato en anticipo_pagado. */
            if ($tipo === 'anticipo') {
                proyecto_aplicar_anticipo($proyectoId, $monto);
            }

            registrar_historial_proyecto(
                $proyectoId,
                'pago_solicitado',
                'Pago de $' . number_format($monto, 2) . ' MXN (' . $tipo . ') registrado, pendiente de aprobación.',
                $usuarioId
            );
            proyecto_recalcular_saldo($proyectoId);

            return $nuevoId;
        });
    } catch (\Throwable $e) {
        /* Carrera de doble envío: la clave única se insertó mientras tanto. */
        if (mongo_es_clave_duplicada($e)) {
            $ya = pag_por_clave($clave);
            if ($ya !== null) {
                return [
                    'ok' => true, 'ya_existia' => true,
                    'pago_id' => (string)$ya['id'],
                    'proyecto_id' => (string)($ya['proyecto_id'] ?? ''),
                    'mensaje' => 'Tu pago ya había sido registrado; no se duplicó.',
                ];
            }
        }
        return ['ok' => false, 'mensaje' => 'No se pudo registrar el pago: ' . $e->getMessage()];
    }

    /* Notificaciones (después de confirmar la escritura). */
    $nombreCliente = (usr_por_id($usuarioId)['nombre'] ?? 'Cliente');
    notificar($usuarioId, 'pago', 'Pago registrado', 'Tu pago de $' . number_format($monto, 0) . ' MXN (' . $tipo . ') quedó registrado y pendiente de aprobación.', url_sitio('portal/pagos.php'));
    notificar_admins('pago', 'Pago pendiente de aprobación', $nombreCliente . ' registró un pago de $' . number_format($monto, 0) . ' MXN para el proyecto ' . (string)$proyectoId . '.', url_sitio('admin/pagos.php'));

    return [
        'ok' => true, 'pago_id' => $pagoId,
        'proyecto_id' => (string)$proyectoId, 'ya_existia' => false,
        'mensaje' => 'Pago registrado correctamente.',
    ];
}

/**
 * Registro de la compra del carrito (tipo_pago = 'producto').
 *
 * A diferencia de pago_registrar() esta compra no pertenece a un proyecto:
 * guarda una foto de los renglones del carrito para que el panel pueda
 * revisar el pedido aunque el cliente lo vacíe después.
 *
 * $d: usuario_id, metodo_pago_id, monto, comprobante, clave_unica, notas.
 * Devuelve ['ok','ya_existia','pago_id','mensaje'].
 */
function pago_registrar_producto(array $d): array
{
    $usuarioId = $d['usuario_id'] ?? '';
    $monto = (float)($d['monto'] ?? 0);
    $metodoId = $d['metodo_pago_id'] ?? null;
    $clave = trim((string)($d['clave_unica'] ?? ''));
    $notas = trim((string)($d['notas'] ?? ''));

    $oUsuario = oid($usuarioId);
    if ($oUsuario === null || $monto <= 0) {
        return ['ok' => false, 'mensaje' => 'Datos inválidos para registrar la compra.'];
    }
    if ($clave === '') {
        $clave = pago_generar_clave($d);
    }

    $ya = pag_por_clave($clave);
    if ($ya !== null) {
        return [
            'ok' => true, 'ya_existia' => true,
            'pago_id' => (string)$ya['id'],
            'mensaje' => 'Tu pedido ya fue registrado; no se duplicó.',
        ];
    }

    /* Foto del carrito antes de vaciarlo. */
    $carrito = car_resumen($usuarioId);
    $renglones = [];
    foreach ($carrito['items'] as $it) {
        $renglones[] = [
            'tipo'            => !empty($it['servicio_id']) ? 'servicio' : 'producto',
            'item_id'         => (string)($it['servicio_id'] ?: $it['producto_id']),
            'titulo'          => (string)($it['servicio_titulo'] ?: $it['producto_titulo']),
            'cantidad'        => (int)($it['cantidad'] ?? 1),
            'precio_unitario' => (float)($it['precio_unitario'] ?? 0),
            'importe'         => (float)($it['precio_unitario'] ?? 0) * (int)($it['cantidad'] ?? 1),
        ];
    }

    try {
        $pagoId = tx(static function () use ($oUsuario, $usuarioId, $monto, $metodoId, $d, $clave, $notas, $renglones) {
            $nuevoId = col_agregar('pagos', [
                'usuario_id'     => $oUsuario,
                'solicitud_id'   => null,
                'proyecto_id'    => null,
                'metodo_pago_id' => oid($metodoId),
                'monto'          => $monto,
                'tipo_pago'      => 'producto',
                'comprobante'    => $d['comprobante'] ?? null,
                'estado'         => 'pendiente',
                'aplicado'       => false,
                'notas'          => $notas !== '' ? $notas : 'Compra desde el carrito',
                'clave_unica'    => $clave,
                'renglones'      => $renglones,
                'creado_en'      => ahora_utc(),
            ]);

            col_borrar_varios('carrito', ['usuario_id' => $oUsuario]);

            return $nuevoId;
        });
    } catch (\Throwable $e) {
        if (mongo_es_clave_duplicada($e)) {
            $ya = pag_por_clave($clave);
            if ($ya !== null) {
                return [
                    'ok' => true, 'ya_existia' => true,
                    'pago_id' => (string)$ya['id'],
                    'mensaje' => 'Tu pedido ya fue registrado; no se duplicó.',
                ];
            }
        }
        return ['ok' => false, 'mensaje' => 'No se pudo registrar el pedido: ' . $e->getMessage()];
    }

    $nombreCliente = (string)(usr_por_id($usuarioId)['nombre'] ?? 'Cliente');
    $enlace = url_sitio('portal/pagos.php');
    notificar($usuarioId, 'pago', 'Pedido registrado', 'Tu pago de $' . number_format($monto, 0) . ' MXN por productos fue registrado. Espera la confirmación del equipo.', $enlace);
    notificar_admins('pago', 'Compra pendiente de confirmación', $nombreCliente . ' realizó una compra por $' . number_format($monto, 0) . ' MXN desde el carrito.', url_sitio('admin/pagos.php'));

    return [
        'ok' => true, 'pago_id' => $pagoId, 'ya_existia' => false,
        'mensaje' => 'Pago de productos registrado. Tu carrito se vació y tu pedido queda pendiente de confirmación.',
    ];
}

/** Aprueba o rechaza un pago, recalculando el saldo del proyecto. */
function pago_aprobar_o_rechazar($pagoId, string $estado, string $notasAdmin = ''): array
{
    if (!in_array($estado, ['aprobado', 'rechazado'], true)) {
        return ['ok' => false, 'mensaje' => 'Estado de pago no válido.'];
    }
    $pago = pag_por_id($pagoId);
    if ($pago === null) {
        return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
    }
    if (($pago['estado'] ?? '') === $estado) {
        return [
            'ok' => true, 'ya_estaba' => true, 'pago' => $pago,
            'proyecto_id' => (string)($pago['proyecto_id'] ?? ''),
            'recalculo' => ['ok' => false],
            'mensaje' => 'El pago ya estaba en "' . $estado . '".',
        ];
    }

    try {
        $resultado = tx(static function () use ($pagoId, $estado, $notasAdmin, $pago) {
            col('pagos')->updateOne(
                filtro_id($pagoId),
                ['$set' => [
                    'estado'    => $estado,
                    'notas'     => $notasAdmin !== '' ? $notasAdmin : null,
                    'aplicado'  => $estado === 'aprobado',
                    'revisado_en' => ahora_utc(),
                ]],
                mongo_opts([])
            );

            /* Vincular proyecto: los pagos nuevos ya lo traen; los
               históricos se empatan por solicitud_id + usuario. */
            $proyectoId = $pago['proyecto_id'] ?? null;
            if (oid($proyectoId) === null && !empty($pago['solicitud_id'])) {
                $candidato = col_q1('proyectos_inicio', [
                    'solicitud_id' => oid($pago['solicitud_id']),
                    'usuario_id'   => oid($pago['usuario_id']),
                ]);
                if ($candidato !== null) {
                    $proyectoId = $candidato['id'];
                    col('pagos')->updateOne(filtro_id($pagoId), ['$set' => ['proyecto_id' => oid($proyectoId)]], mongo_opts([]));
                }
            }

            $recalculo = ['ok' => false];
            if (oid($proyectoId) !== null) {
                /* Compatibilidad con el avance de anticipos. */
                if (($pago['tipo_pago'] ?? '') === 'anticipo') {
                    if ($estado === 'aprobado') {
                        proyecto_recalcular_estado_anticipo($proyectoId);
                    } else {
                        proyecto_descontar_anticipo($proyectoId, (float)($pago['monto'] ?? 0));
                    }
                }
                $recalculo = proyecto_recalcular_saldo($proyectoId);
                registrar_historial_proyecto(
                    $proyectoId,
                    $estado === 'aprobado' ? 'pago_aprobado' : 'pago_rechazado',
                    ($estado === 'aprobado' ? 'Pago aprobado' : 'Pago rechazado') . ': ' . folio_pago_txt($pago) . ' de ' . monto_pago_txt($pago) . '. Saldo restante: $' . number_format((float)($recalculo['saldo'] ?? 0), 2) . '.'
                );
            }

            return [
                'proyecto_id' => (string)($proyectoId ?? ''),
                'recalculo'   => $recalculo,
            ];
        });
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo actualizar el pago: ' . $e->getMessage()];
    }

    /* Notificaciones y correo tras confirmar la escritura. */
    notificacion_pago_correo(pag_completo($pagoId), $estado, $notasAdmin);

    return [
        'ok'          => true,
        'pago'        => $pago,
        'proyecto_id' => $resultado['proyecto_id'],
        'recalculo'   => $resultado['recalculo'],
        'mensaje'     => 'Pago actualizado a "' . $estado . '".',
    ];
}

/** Adjunta (o reemplaza) el comprobante de un pago. */
function pag_adjuntar_comprobante($pagoId, string $ruta): array
{
    if (oid($pagoId) === null) {
        return ['ok' => false, 'mensaje' => 'Pago no encontrado.'];
    }
    col_actualizar('pagos', $pagoId, ['$set' => ['comprobante' => $ruta]]);
    return ['ok' => true, 'mensaje' => 'Comprobante adjuntado.', 'comprobante' => $ruta];
}

/** Elimina un pago (y su comprobante en disco). */
function pag_eliminar($pagoId): bool
{
    return col_borrar('pagos', $pagoId);
}

/* ---------- Formato ---------- */

/** Folio PAG-00001 para la interfaz y las bitácoras. */
function folio_pago_txt($pago): string
{
    $id = is_array($pago) ? ($pago['id'] ?? $pago['_id'] ?? '') : $pago;
    return 'PAG-' . str_pad(counter_partes($id), 5, '0', STR_PAD_LEFT);
}

/** Monto formateado para textos de bitácoras. */
function monto_pago_txt($pago): string
{
    $monto = is_array($pago) ? (float)($pago['monto'] ?? 0) : 0.0;
    return '$' . number_format($monto, 2) . ' MXN';
}

/**
 * Parte "visual" de un id para los folios. En MySQL era el
 * AUTO_INCREMENT; en MongoDB mantenemos un contador atómico en
 * `contadores` para que los folios sigan siendo consecutivos y
 * legibles por humanos (PAG-00001, PAG-00002...).
 */
function counter_partes($id): string
{
    $hex = trim((string)$id);
    if ($hex === '') {
        return '0';
    }
    $dec = hexdec(substr($hex, -8));
    return (string)$dec;
}

/** Notifica al cliente y envía correo sobre la aprobación de un pago. */
function notificacion_pago_correo(array $pago, string $estado, string $notasAdmin = ''): void
{
    $montoTxt = '$' . number_format((float)($pago['monto'] ?? 0), 0) . ' MXN';
    $clienteId = $pago['usuario_id'] ?? 0;
    $folio = folio_pago_txt($pago);
    $nombre = (string)($pago['cliente_nombre'] ?? ($pago['nombre'] ?? ''));
    $email = (string)($pago['cliente_email'] ?? ($pago['email'] ?? ''));
    $tipo = (string)($pago['tipo_pago'] ?? '');
    $reciboUrl = url_sitio('portal/recibo.php?id=' . (string)$pago['id']);

    if ($estado === 'aprobado') {
        if ($tipo === 'producto') {
            $titulo = 'Pedido confirmado';
            $mensaje = 'Tu pedido por ' . $montoTxt . ' fue confirmado. Pronto procesaremos tus productos.';
        } elseif ($tipo === 'anticipo') {
            $titulo = 'Pago aprobado';
            $mensaje = 'Tu anticipo de ' . $montoTxt . ' fue aprobado. ¡Tu proyecto está en desarrollo!';
        } else {
            $titulo = 'Pago aprobado';
            $mensaje = 'Tu pago de ' . $montoTxt . ' fue aprobado.';
        }
        if ($notasAdmin !== '') {
            $mensaje .= ' Nota del equipo: ' . $notasAdmin;
        }
        notificar($clienteId, 'exito', $titulo, $mensaje, $reciboUrl);
        if ($email !== '') {
            enviar_correo(
                $email,
                $titulo . ' — ' . SITE_NOMBRE,
                correo_plantilla(
                    $tipo === 'producto' ? '¡Pedido confirmado!' : '¡Pago aprobado!',
                    '<p>Hola <b>' . e($nombre) . '</b>,</p>'
                    . '<p>' . e($mensaje) . '</p>'
                    . '<p>Folio: <b>' . e($folio) . '</b> · Monto: <b>' . e($montoTxt) . '</b> · Fecha: ' . e(date('d/m/Y H:i')) . '</p>'
                    . '<p style="margin:22px 0;"><a href="' . e($reciboUrl) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Descargar recibo</a></p>'
                    . '<p class="small" style="color:#8a97ad;">Puedes consultar tu historial de pagos desde tu portal.</p>'
                )
            );
        }
    } else {
        $mensaje = 'Tu pago de ' . $montoTxt . ' no fue aprobado. Contáctanos para resolverlo.';
        if ($tipo === 'anticipo') {
            $mensaje .= ' El anticipo se descontó de tu proyecto y podrás rehacer el pago desde tu portal.';
        }
        if ($notasAdmin !== '') {
            $mensaje .= ' Motivo: ' . $notasAdmin;
        }
        notificar($clienteId, 'info', 'Pago rechazado', $mensaje, url_sitio('portal/pagos.php'));
        if ($email !== '') {
            enviar_correo(
                $email,
                'Pago rechazado — ' . SITE_NOMBRE,
                correo_plantilla(
                    'Tu pago fue rechazado',
                    '<p>Hola <b>' . e($nombre) . '</b>,</p>'
                    . '<p>' . e($mensaje) . '</p>'
                    . '<p>Folio: <b>' . e($folio) . '</b></p>'
                    . '<p><a href="' . e(url_sitio('portal/pagos.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mis pagos</a></p>'
                )
            );
        }
    }
}

/* ============================================================
   Carrito
   ============================================================ */

/** Renglones del carrito con el detalle de servicio o producto resuelto. */
function car_resumen($usuarioId): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return ['items' => [], 'subtotal' => 0.0, 'unidades' => 0, 'count' => 0];
    }
    $items = col_q('carrito', ['usuario_id' => $o], ['sort' => ['creado_en' => -1]]);
    if (empty($items)) {
        return ['items' => [], 'subtotal' => 0.0, 'unidades' => 0, 'count' => 0];
    }

    $subtotal = 0.0;
    $unidades = 0;
    foreach ($items as &$it) {
        $servicio = !empty($it['servicio_id']) ? srv_por_id($it['servicio_id']) : null;
        $producto = !empty($it['producto_id']) ? prd_por_id($it['producto_id']) : null;
        $it['servicio_titulo'] = $servicio['titulo'] ?? null;
        $it['servicio_icono']  = $servicio['icono'] ?? null;
        $it['producto_titulo'] = $producto['titulo'] ?? null;
        $it['producto_imagen'] = $producto['imagen'] ?? null;
        $subtotal += (float)($it['precio_unitario'] ?? 0) * (int)($it['cantidad'] ?? 1);
        $unidades += (int)($it['cantidad'] ?? 1);
    }
    unset($it);

    return [
        'items'    => $items,
        'subtotal' => $subtotal,
        'unidades' => $unidades,
        'count'    => $unidades,
    ];
}

/** Número total de unidades en el carrito (badge del menú). */
function contar_carrito($usuarioId): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return (int)col_suma('carrito', ['usuario_id' => $o], 'cantidad');
}

/**
 * Agrega un ítem al carrito. Si ya existe el mismo servicio o producto,
 * acumula la cantidad en lugar de duplicar la línea.
 */
function car_agregar($usuarioId, string $tipo, $itemId, int $cantidad = 1): array
{
    $oUsuario = oid($usuarioId);
    if ($oUsuario === null) {
        return ['ok' => false, 'mensaje' => 'Debes iniciar sesión para usar el carrito.'];
    }
    $cantidad = max(1, $cantidad);
    $precio = 0.0;
    $titulo = '';

    if ($tipo === 'producto') {
        $p = prd_por_id($itemId);
        if ($p === null || (int)$p['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'El producto ya no está disponible.'];
        }
        $precio = (float)($p['precio'] ?? 0);
        $titulo = (string)$p['titulo'];
        $oItem = oid($p['id']);
    } else {
        $s = srv_por_id($itemId);
        if ($s === null || (int)$s['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'El servicio ya no está disponible.'];
        }
        $precio = (float)($s['precio_desde'] ?? 0);
        $titulo = (string)$s['titulo'];
        $oItem = oid($s['id']);
    }

    if ($precio <= 0) {
        return ['ok' => false, 'mensaje' => 'Este ítem no tiene precio configurado.'];
    }

    $filtro = $tipo === 'producto'
        ? ['usuario_id' => $oUsuario, 'producto_id' => $oItem]
        : ['usuario_id' => $oUsuario, 'servicio_id' => $oItem];

    $existente = col_q1('carrito', $filtro);
    if ($existente !== null) {
        col('carrito')->updateOne(
            ['_id' => oid($existente['id'])],
            ['$set' => [
                'cantidad'        => (int)($existente['cantidad'] ?? 1) + $cantidad,
                'precio_unitario' => $precio,
            ]],
            mongo_opts([])
        );
    } else {
        col_agregar('carrito', $filtro + [
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio,
            'creado_en'       => ahora_utc(),
        ]);
    }

    return ['ok' => true, 'mensaje' => $titulo . ' se agregó a tu carrito.'];
}

/** Cambia la cantidad de una línea del carrito. */
function car_actualizar($usuarioId, $itemId, int $cantidad): array
{
    $o = oid($itemId);
    $oUsuario = oid($usuarioId);
    if ($o === null || $oUsuario === null) {
        return ['ok' => false, 'mensaje' => 'Ese ítem ya no está en tu carrito.'];
    }
    if (col_contar('carrito', ['_id' => $o, 'usuario_id' => $oUsuario]) === 0) {
        return ['ok' => false, 'mensaje' => 'Ese ítem ya no está en tu carrito.'];
    }
    col('carrito')->updateOne(
        ['_id' => $o, 'usuario_id' => $oUsuario],
        ['$set' => ['cantidad' => max(1, $cantidad)]],
        mongo_opts([])
    );
    return ['ok' => true, 'mensaje' => 'Carrito actualizado.'];
}

function car_eliminar($usuarioId, $itemId): array
{
    $oUsuario = oid($usuarioId);
    if ($oUsuario === null) {
        return ['ok' => false, 'mensaje' => 'Sesión no válida.'];
    }
    col_borrar_varios('carrito', array_merge(filtro_id($itemId), ['usuario_id' => $oUsuario]));
    return ['ok' => true, 'mensaje' => 'Ítem eliminado del carrito.'];
}

function car_vaciar($usuarioId): array
{
    $oUsuario = oid($usuarioId);
    if ($oUsuario === null) {
        return ['ok' => false, 'mensaje' => 'Sesión no válida.'];
    }
    col_borrar_varios('carrito', ['usuario_id' => $oUsuario]);
    return ['ok' => true, 'mensaje' => 'Carrito vaciado.'];
}