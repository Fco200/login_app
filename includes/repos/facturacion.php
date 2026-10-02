<?php
/* ============================================================
   FV DIGITAL - Repositorio de facturación
   ------------------------------------------------------------
   Colección `facturas`. Además del importe se guarda una COPIA
   congelada de los datos fiscales del emisor y del receptor, para
   que el PDF sea idéntico aunque después cambien los datos del
   cliente.

   Documento:
     { _id, folio, proyecto_id, usuario_id, concepto,
       cliente_fiscal: { nombre, razon_social, rfc, direccion, cp,
                        ciudad, estado, pais, email, telefono },
       emisor_fiscal: { nombre, razon_social, rfc, direccion,
                        telefono, email, logo },
       subtotal, iva, iva_rate, total, pagos: [...],
       estado, emitida_por, notas, creado_en }
   ============================================================ */

function fac_estados(): array
{
    return ['emitida', 'cancelada'];
}

/** Datos fiscales del receptor a partir del perfil del cliente. */
function fac_datos_cliente(array $usuario): array
{
    return [
        'nombre'       => (string)($usuario['nombre'] ?? ''),
        'razon_social' => (string)($usuario['razon_social'] ?? ''),
        'rfc'          => strtoupper(trim((string)($usuario['rfc'] ?? ''))),
        'direccion'    => (string)($usuario['direccion'] ?? ''),
        'cp'           => (string)($usuario['cp'] ?? ''),
        'ciudad'       => (string)($usuario['ciudad'] ?? ''),
        'estado'       => (string)($usuario['estado'] ?? ''),
        'pais'         => (string)($usuario['pais'] ?? 'México'),
        'email'        => (string)($usuario['email'] ?? ''),
        'telefono'     => (string)($usuario['telefono'] ?? ''),
    ];
}

/** ¿El cliente ya tiene los datos fiscales mínimos para facturar? */
function fac_datos_completos(array $cliente): bool
{
    return trim((string)($cliente['rfc'] ?? '')) !== ''
        && trim((string)($cliente['direccion'] ?? '')) !== ''
        && (trim((string)($cliente['razon_social'] ?? '')) !== '' || trim((string)($cliente['nombre'] ?? '')) !== '');
}

/* ============================================================
   Folio consecutivo
   ============================================================ */

/**
 * Siguiente folio disponible con formato FV-0001.
 * Usa la colección `contadores` para garantizar consecutividad sin
 * depender de un MAX() (evita colisiones bajo concurrencia).
 */
function factura_folio(): string
{
    $n = proximo_secuencia('facturas', 1);
    return 'FV-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/* ============================================================
   Emisión
   ============================================================ */

/**
 * Emite la factura de un proyecto.
 *
 * $d: proyecto_id, usuario_id (opcional: se toma del proyecto),
 *     concepto, notas, cliente_fiscal (array opcional: si falta se
 *     toma del perfil del cliente), emitida_por.
 *
 * Devuelve ['ok','factura_id','folio','subtotal','iva','total','mensaje'].
 */
function factura_crear(array $d): array
{
    $proyectoId = $d['proyecto_id'] ?? 0;
    $proy = proy_por_id($proyectoId);
    if ($proy === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.'];
    }
    $usuarioId = oid($d['usuario_id'] ?? ($proy['usuario_id'] ?? 0));
    if ($usuarioId === null) {
        return ['ok' => false, 'mensaje' => 'El proyecto no tiene cliente asignado.'];
    }

    $usuario = usr_por_id($usuarioId);
    if ($usuario === null) {
        return ['ok' => false, 'mensaje' => 'Cliente no encontrado.'];
    }

    /* Datos fiscales: se respetan los que envía el formulario y, si no,
       se completan con los del perfil del cliente. */
    $fiscal = fac_datos_cliente($usuario);
    if (is_array($d['cliente_fiscal'] ?? null)) {
        foreach (array_keys($fiscal) as $campo) {
            if (isset($d['cliente_fiscal'][$campo])) {
                $fiscal[$campo] = trim((string)$d['cliente_fiscal'][$campo]);
            }
        }
    }
    $fiscal['rfc'] = strtoupper($fiscal['rfc']);
    $fiscal['pais'] = $fiscal['pais'] !== '' ? $fiscal['pais'] : 'México';

    if (!fac_datos_completos($fiscal)) {
        return [
            'ok' => false,
            'faltan_datos' => true,
            'mensaje' => 'Faltan datos fiscales del cliente (RFC y dirección son obligatorios).',
        ];
    }

    $concepto = trim((string)($d['concepto'] ?? ''));
    if ($concepto === '') {
        $solicitud = !empty($proy['solicitud_id']) ? sol_por_id($proy['solicitud_id']) : null;
        $concepto = 'Desarrollo de proyecto: ' . (($solicitud['tipo_servicio'] ?? '') ?: ('Proyecto ' . (string)$proy['id']));
    }
    $emitidaPor = trim((string)($d['emitida_por'] ?? (($_SESSION['admin_nombre'] ?? '') ?: ($_SESSION['nombre'] ?? 'Portal'))));

    /* Desglose y cálculo de IVA sobre los pagos aprobados. */
    $pagos = is_array($d['pagos'] ?? null)
        ? $d['pagos']
        : proyecto_pagos_aprobados_detalle($proy['id']);

    $subtotal = 0.0;
    $snapshot = [];
    foreach ($pagos as $p) {
        $monto = (float)($p['monto'] ?? 0);
        $subtotal += $monto;
        $snapshot[] = [
            'id'    => (string)($p['id'] ?? ''),
            'folio' => (string)($p['folio'] ?? folio_pago_txt($p)),
            'fecha' => (string)($p['fecha'] ?? ''),
            'monto' => $monto,
            'tipo'  => (string)($p['tipo'] ?? 'anticipo'),
        ];
    }
    $subtotal = round($subtotal, 2);
    $ivaRate = factura_iva_rate();
    $iva = round($subtotal * $ivaRate, 2);
    $total = round($subtotal + $iva, 2);

    try {
        $folio = factura_folio();
        $facturaId = col_agregar('facturas', [
            'folio'          => $folio,
            'proyecto_id'    => oid($proy['id']),
            'usuario_id'     => $usuarioId,
            'concepto'       => $concepto,
            'cliente_fiscal' => $fiscal,
            'emisor_fiscal'  => datos_emisor(),
            'subtotal'       => $subtotal,
            'iva'            => $iva,
            'iva_rate'       => $ivaRate,
            'total'          => $total,
            'pagos'          => $snapshot,
            'estado'         => 'emitida',
            'emitida_por'    => $emitidaPor !== '' ? $emitidaPor : null,
            'notas'          => fv_texto($d['notas'] ?? null),
            'creado_en'      => ahora_utc(),
        ]);
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo emitir la factura: ' . $e->getMessage()];
    }

    registrar_historial_proyecto($proy['id'], 'factura_emitida', 'Factura ' . $folio . ' emitida por $' . number_format($total, 2) . ' MXN.');
    notificar($usuarioId, 'factura', 'Factura ' . $folio . ' emitida', 'Tu factura por $' . number_format($total, 0) . ' MXN está lista para descargar.', url_sitio('portal/facturacion.php'));
    notificar_admins('factura', 'Factura emitida', $emitidaPor . ' emitió la factura ' . $folio . ' por $' . number_format($total, 0) . ' MXN del proyecto ' . (string)$proy['id'] . '.', url_sitio('admin/facturacion.php'));

    if (!empty($usuario['email'])) {
        enviar_correo(
            $usuario['email'],
            'Factura ' . $folio . ' — ' . SITE_NOMBRE,
            correo_plantilla(
                'Tu factura está lista',
                '<p>Hola <b>' . e($usuario['nombre'] ?? '') . '</b>,</p>'
                . '<p>Emitimos tu factura <b>' . e($folio) . '</b> por un total de <b>$' . number_format($total, 2) . ' MXN</b>.</p>'
                . '<p><a href="' . e(url_sitio('portal/facturacion.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mi factura</a></p>'
            )
        );
    }

    return [
        'ok' => true, 'factura_id' => $facturaId, 'folio' => $folio,
        'subtotal' => $subtotal, 'iva' => $iva, 'total' => $total,
        'mensaje' => 'Factura ' . $folio . ' emitida correctamente.',
    ];
}

/* ============================================================
   Consulta
   ============================================================ */

function factura_por_id($id): ?array
{
    $f = col_q1('facturas', filtro_id($id));
    return $f === null ? null : factura_con_relaciones($f);
}

function factura_por_folio(string $folio): ?array
{
    $f = col_q1('facturas', ['folio' => $folio]);
    return $f === null ? null : factura_con_relaciones($f);
}

/** Enriquece la factura con cliente, proyecto y solicitud. */
function factura_con_relaciones(array $f): array
{
    $cliente = !empty($f['usuario_id']) ? usr_por_id($f['usuario_id']) : null;
    $proy = !empty($f['proyecto_id']) ? proy_por_id($f['proyecto_id']) : null;
    $solicitud = ($proy !== null && !empty($proy['solicitud_id'])) ? sol_por_id($proy['solicitud_id']) : null;

    return $f + [
        'cliente_nombre' => $cliente['nombre'] ?? '',
        'cliente_email'  => $cliente['email'] ?? '',
        'tipo_servicio'  => $solicitud['tipo_servicio'] ?? '',
        'proyecto'       => $proy,
        'solicitud_id'   => $proy['solicitud_id'] ?? null,
    ];
}

/** Facturas emitidas de un proyecto (más recientes primero). */
function facturas_por_proyecto($proyectoId): array
{
    $o = oid($proyectoId);
    if ($o === null) {
        return [];
    }
    $filas = col_q('facturas', ['proyecto_id' => $o], ['sort' => ['creado_en' => -1]]);
    return array_map('factura_con_relaciones', $filas);
}

/** Facturas de un cliente (más recientes primero). */
function facturas_de_usuario($usuarioId, int $limite = 0): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('facturas', ['usuario_id' => $o], $op);
}

/** Todas las facturas para el panel. */
function facturas_listar(array $filtros = []): array
{
    $f = [];
    if (!empty($filtros['estado']) && in_array($filtros['estado'], fac_estados(), true)) {
        $f['estado'] = $filtros['estado'];
    }
    if (!empty($filtros['proyecto_id'])) {
        $f['proyecto_id'] = oid($filtros['proyecto_id']);
    }
    if (!empty($filtros['usuario_id'])) {
        $f['usuario_id'] = oid($filtros['usuario_id']);
    }
    $filas = col_q('facturas', $f, ['sort' => ['creado_en' => -1]]);
    return array_map('factura_con_relaciones', $filas);
}

/** ¿Ya se facturó este proyecto? (evita dobles facturas del mismo saldo). */
function factura_existe_para_proyecto($proyectoId): bool
{
    return col_contar('facturas', ['proyecto_id' => oid($proyectoId), 'estado' => 'emitida']) > 0;
}

/** Cancela una factura. */
function factura_cancelar($id, string $motivo = ''): array
{
    $f = factura_por_id($id);
    if ($f === null) {
        return ['ok' => false, 'mensaje' => 'Factura no encontrada.'];
    }
    if ($f['estado'] === 'cancelada') {
        return ['ok' => true, 'mensaje' => 'La factura ya estaba cancelada.'];
    }
    col_actualizar('facturas', $id, [
        '$set' => [
            'estado'          => 'cancelada',
            'cancelada_en'    => ahora_utc(),
            'motivo_cancelacion' => fv_texto($motivo),
        ],
    ]);
    registrar_historial_proyecto($f['proyecto_id'], 'factura_cancelada', 'Factura ' . $f['folio'] . ' cancelada. ' . $motivo);

    if (!empty($f['usuario_id'])) {
        notificar($f['usuario_id'], 'info', 'Factura ' . $f['folio'] . ' cancelada', 'Tu factura fue cancelada. Contacta al equipo para más información.', url_sitio('portal/facturacion.php'));
    }
    return ['ok' => true, 'mensaje' => 'Factura ' . $f['folio'] . ' cancelada.'];
}

function factura_eliminar($id): bool
{
    return col_borrar('facturas', $id);
}

/* ============================================================
   Liquidación <-> datos fiscales
   ============================================================ */

/**
 * Proyectos del cliente ya liquidados (o concluidos) que aún no
 * tienen factura emitida: son los que requieren atención del panel.
 */
function proyectos_pendientes_facturar($usuarioId = null): array
{
    $f = ['estado' => ['$in' => ['liquidado', 'completado']]];
    if ($usuarioId !== null && $usuarioId !== '') {
        $f['usuario_id'] = oid($usuarioId);
    }
    $proyectos = col_q('proyectos_inicio', $f, ['sort' => ['creado_en' => -1]]);
    if (empty($proyectos)) {
        return [];
    }

    $ids = array_map(static fn($p) => oid($p['id']), $proyectos);
    $facturadas = col_q(
        'facturas',
        ['proyecto_id' => ['$in' => $ids], 'estado' => 'emitida'],
        ['projection' => ['proyecto_id' => 1, 'folio' => 1, 'total' => 1]]
    );
    $porProyecto = [];
    foreach ($facturadas as $f2) {
        $porProyecto[(string)$f2['proyecto_id']] = $f2;
    }

    $pendientes = [];
    foreach ($proyectos as $p) {
        $p['factura'] = $porProyecto[(string)$p['id']] ?? null;
        $pendientes[] = proy_con_cliente($p);
    }
    return $pendientes;
}

/**
 * Marca un proyecto como "pendiente de datos fiscales": avisa al
 * cliente con una notificación que enlaza a su perfil para que
 * complete RFC, razón social y dirección.
 */
function facturar_solicitar_datos($proyectoId, string $mensajeExtra = ''): array
{
    $proy = proy_por_id($proyectoId);
    if ($proy === null) {
        return ['ok' => false, 'mensaje' => 'Proyecto no encontrado.'];
    }
    $clienteId = $proy['usuario_id'] ?? null;
    if ($clienteId === null) {
        return ['ok' => false, 'mensaje' => 'El proyecto no tiene cliente asignado.'];
    }

    col('proyectos_inicio')->updateOne(
        ['_id' => oid($proy['id'])],
        [
            '$set' => ['fiscal_pendiente' => true],
            '$addToSet' => ['fiscal_notificado_en' => ahora_utc()],
        ],
        mongo_opts([])
    );

    $mensaje = 'Tu proyecto está liquidado y listo para facturar. Para emitir tu factura necesitamos tus datos fiscales: RFC, Razón Social o nombre, y dirección de facturación.'
        . ($mensajeExtra !== '' ? ' ' . $mensajeExtra : '');

    notificar($clienteId, 'factura', 'Completa tus datos fiscales', $mensaje, url_sitio('portal/facturacion.php'));
    registrar_historial_proyecto($proyectoId, 'datos_fiscales_solicitados', 'Se solicitó al cliente completar sus datos fiscales para facturar.');

    return ['ok' => true, 'mensaje' => 'Se solicitaron los datos fiscales al cliente.'];
}

/** Marca que el cliente ya completó sus datos fiscales. */
function facturar_datos_completados($proyectoId): void
{
    col('proyectos_inicio')->updateOne(
        ['_id' => oid($proyectoId)],
        ['$set' => ['fiscal_pendiente' => false, 'fiscal_completado_en' => ahora_utc()]],
        mongo_opts([])
    );
}