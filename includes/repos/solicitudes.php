<?php
/* ============================================================
   FV DIGITAL - Repositorio de solicitudes
   ------------------------------------------------------------
   Colección `solicitudes`:
     { _id, usuario_id, nombre, email, empresa, cargo, telefono,
       tipo_servicio, presupuesto, mensaje, estado, tipo_solicitud,
       empleados, rango, creado_en }

   Estados: nueva → en_proceso → completada | rechazada.
   Cada cambio de estado deja una traza en `solicitud_historial`.
   ============================================================ */

/** Estados válidos de una solicitud. */
function sol_estados(): array
{
    return ['nueva', 'en_proceso', 'completada', 'rechazada'];
}

function sol_campos(array $d, array $base = []): array
{
    $doc = [
        'usuario_id'      => isset($d['usuario_id']) ? oid($d['usuario_id']) : ($base['usuario_id'] ?? null),
        'nombre'          => trim((string)($d['nombre'] ?? ($base['nombre'] ?? ''))),
        'email'           => strtolower(trim((string)($d['email'] ?? ($base['email'] ?? '')))),
        'empresa'         => fv_texto($d['empresa'] ?? null),
        'cargo'           => fv_texto($d['cargo'] ?? null),
        'telefono'        => fv_texto($d['telefono'] ?? null),
        'tipo_servicio'   => fv_texto($d['tipo_servicio'] ?? null),
        'presupuesto'     => fv_texto($d['presupuesto'] ?? null),
        'mensaje'         => fv_texto($d['mensaje'] ?? null),
        'tipo_solicitud'  => (string)($d['tipo_solicitud'] ?? ($base['tipo_solicitud'] ?? 'individual')),
        'empleados'       => fv_texto($d['empleados'] ?? null),
        'rango'           => fv_texto($d['rango'] ?? null),
    ];
    if ($doc['tipo_solicitud'] !== 'empresa') {
        $doc['tipo_solicitud'] = 'individual';
    }
    return $doc;
}

/** Registra una solicitud nueva. Devuelve ['ok','id','mensaje']. */
function sol_crear(array $d): array
{
    $campos = sol_campos($d);
    if ($campos['nombre'] === '') {
        return ['ok' => false, 'mensaje' => 'El nombre es obligatorio.'];
    }
    if (!filter_var((string)$campos['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensaje' => 'El correo no es válido.'];
    }

    try {
        $id = tx(static function () use ($campos) {
            $nuevoId = col_agregar('solicitudes', $campos + [
                'estado'    => 'nueva',
                'creado_en' => ahora_utc(),
            ]);
            col_agregar('solicitud_historial', [
                'solicitud_id' => oid($nuevoId),
                'estado'       => 'nueva',
                'nota'         => 'Solicitud recibida.',
                'creado_en'    => ahora_utc(),
            ]);
            return $nuevoId;
        });

        notificar_admins(
            'solicitud',
            'Nueva solicitud de servicio',
            trim((string)$campos['nombre']) . ' envió una solicitud de ' . ($campos['tipo_servicio'] ?: 'asesoría') . '.',
            url_sitio('admin/solicitudes.php')
        );

        return ['ok' => true, 'id' => $id, 'mensaje' => 'Solicitud registrada correctamente.'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo registrar la solicitud: ' . $e->getMessage()];
    }
}

function sol_por_id($id): ?array
{
    return col_q1('solicitudes', filtro_id($id));
}

/**
 * Varias solicitudes en una sola consulta, indexadas por id (hex).
 *
 * Evita el N+1 al enrichcer listados de proyectos o pagos: recibe un
 * arreglo de ids (hex, ObjectId o documentos) y devuelve
 * ['<id hex>' => solicitud].
 */
function sol_por_ids(array $ids): array
{
    $oids = [];
    foreach ($ids as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[(string)$o] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $filas = col_q('solicitudes', ['_id' => ['$in' => array_values($oids)]]);
    $mapa = [];
    foreach ($filas as $f) {
        $mapa[(string)$f['id']] = $f;
    }
    return $mapa;
}

/**
 * Una solicitud, verificando que realmente pertenezca al cliente.
 *
 * IMPORTANTE: la pertenencia se comprueba sobre el MISMO documento
 * (AND), nunca con un $or que sólo exigiría "id correcto O usuario
 * correcto", porque eso devolvería solicitudes de otros clientes.
 */
function sol_de_usuario($id, $usuarioId, string $email = ''): ?array
{
    $o = oid($id);
    if ($o === null) {
        return null;
    }
    $puedeComprobar = ($usuarioId !== null && $usuarioId !== '' && $usuarioId !== 0 && oid($usuarioId) !== null)
        || ($email !== '');

    if (!$puedeComprobar) {
        return null;
    }

    /* Cualquiera de estos datos coincide en el documento solicitado. */
    $coincide = [];
    if (oid($usuarioId) !== null) {
        $coincide[] = ['usuario_id' => oid($usuarioId)];
    }
    if ($email !== '') {
        $coincide[] = ['email' => usr_norm_email($email)];
    }

    return col_q1('solicitudes', ['_id' => $o, '$or' => $coincide]);
}

/** Solicitudes del cliente: suyas por usuario_id o por correo. */
function sol_de_cliente($usuarioId, string $email = '', int $limite = 0): array
{
    $condiciones = [];
    if ($usuarioId !== null && $usuarioId !== '' && $usuarioId !== 0) {
        $condiciones[] = ['usuario_id' => oid($usuarioId)];
    }
    if ($email !== '') {
        $condiciones[] = ['email' => usr_norm_email($email)];
    }
    $f = $condiciones ? ['$or' => $condiciones] : [];
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('solicitudes', $f, $op);
}

/** Todas las solicitudes con filtros opcionales del panel. */
function sol_listar(array $filtros = []): array
{
    $f = [];
    if (!empty($filtros['estado']) && in_array($filtros['estado'], sol_estados(), true)) {
        $f['estado'] = $filtros['estado'];
    }
    if (!empty($filtros['tipo'])) {
        $f['tipo_solicitud'] = $filtros['tipo'];
    }
    if (!empty($filtros['buscar'])) {
        $rx = rx(escapar_like($filtros['buscar']));
        $f['$or'] = [
            ['nombre' => $rx],
            ['email' => $rx],
            ['empresa' => $rx],
            ['tipo_servicio' => $rx],
        ];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if (!empty($filtros['limite'])) {
        $op['limit'] = (int)$filtros['limite'];
    }
    return col_q('solicitudes', $f, $op);
}

/** Últimas solicitudes del sistema (panel). */
function sol_recientes(int $limite = 5): array
{
    return sol_listar(['limite' => $limite]);
}

/** Conteo de solicitudes por tipo (individual / empresa). */
function sol_conteo_por_tipo(): array
{
    $pipeline = [
        ['$group' => ['_id' => '$tipo_solicitud', 'total' => ['$sum' => 1]]],
    ];
    $filas = col('solicitudes')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $fila) {
        $out[(string)$fila['_id']] = (int)$fila['total'];
    }
    return $out;
}

/** Cambia el estado de una solicitud y notifica al cliente. */
function sol_cambiar_estado($id, string $estado, string $nota = ''): array
{
    if (!in_array($estado, sol_estados(), true)) {
        return ['ok' => false, 'mensaje' => 'Estado de solicitud no válido.'];
    }
    $s = sol_por_id($id);
    if ($s === null) {
        return ['ok' => false, 'mensaje' => 'Solicitud no encontrada.'];
    }
    if ($s['estado'] === $estado) {
        return ['ok' => true, 'ya_esta' => true, 'mensaje' => 'La solicitud ya estaba en "' . $estado . '".'];
    }

    $idSolicitud = oid($id);
    try {
        tx(static function () use ($idSolicitud, $estado, $nota) {
            col('solicitudes')->updateOne(['_id' => $idSolicitud], ['$set' => ['estado' => $estado, 'actualizado_en' => ahora_utc()]], mongo_opts([]));
            col_agregar('solicitud_historial', [
                'solicitud_id' => $idSolicitud,
                'estado'       => $estado,
                'nota'         => $nota !== '' ? $nota : null,
                'creado_en'    => ahora_utc(),
            ]);
        });
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo actualizar: ' . $e->getMessage()];
    }

    // Notificación al cliente, sólo si la cuenta sigue existiendo.
    if (!empty($s['usuario_id'])) {
        $texto = [
            'en_proceso' => 'Ya estamos trabajando en tu solicitud.',
            'completada' => 'Tu solicitud fue completada. ¡Gracias por confiar en nosotros!',
            'rechazada'  => 'Tu solicitud no pudo procederse. Contáctanos para más información.',
            'nueva'      => 'Tu solicitud está en nuestra lista de espera.',
        ][$estado] ?? 'Tu solicitud cambió de estado: ' . $estado . '.';
        if ($nota !== '') {
            $texto .= ' Nota: ' . $nota;
        }
        notificar(
            $s['usuario_id'],
            $estado === 'completada' ? 'exito' : ($estado === 'rechazada' ? 'info' : 'info'),
            'Solicitud actualizada',
            $texto,
            url_sitio('portal/mis-solicitudes.php')
        );
    }

    // Aviso por correo al cliente (además de la notificación en el portal).
    if (!empty($s['usuario_id']) && !empty($s['email'])) {
        $tituloCorreo = 'Tu solicitud ahora está: ' . ucfirst(str_replace('_', ' ', $estado));
        enviar_correo(
            (string)$s['email'],
            'Actualización de tu solicitud — ' . SITE_NOMBRE,
            correo_plantilla(
                $tituloCorreo,
                '<p>Hola <b>' . e($s['nombre'] ?? '') . '</b>,</p>'
                . '<p><b>Servicio:</b> ' . e($s['tipo_servicio'] ?? '—') . '</p>'
                . '<p>' . e($nota !== '' ? $nota : $texto) . '</p>'
                . '<p><a href="' . e(url_sitio('portal/solicitudes.php')) . '" style="background:#0a3d8f;color:#fff;padding:11px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Ver mi solicitud</a></p>'
            )
        );
    }

    return ['ok' => true, 'mensaje' => 'Solicitud actualizada a "' . $estado . '".'];
}

/** Conteo de solicitudes agrupado por estado (para las pestañas del panel). */
function sol_conteo_estados(string $tipo = ''): array
{
    $match = [];
    if ($tipo === 'empresa' || $tipo === 'individual') {
        $match['tipo_solicitud'] = $tipo;
    }
    $pipeline = [];
    if ($match) {
        $pipeline[] = ['$match' => $match];
    }
    $pipeline[] = ['$group' => ['_id' => '$estado', 'total' => ['$sum' => 1]]];

    try {
        $filas = col('solicitudes')->aggregate($pipeline, mongo_opts([]))->toArray();
    } catch (\Throwable $e) {
        return [];
    }
    $out = array_fill_keys(sol_estados(), 0);
    foreach ($filas as $fila) {
        $out[(string)$fila['_id']] = (int)$fila['total'];
    }
    return $out;
}

function sol_eliminar($id): bool
{
    $o = oid($id);
    if ($o === null) {
        return false;
    }
    col_borrar_varios('solicitud_historial', ['solicitud_id' => $o]);
    return col_borrar('solicitudes', $id);
}

/** Historial (línea de tiempo) de una solicitud. */
function sol_historial($idSolicitud): array
{
    $o = oid($idSolicitud);
    if ($o === null) {
        return [];
    }
    return col_q('solicitud_historial', ['solicitud_id' => $o], ['sort' => ['creado_en' => 1]]);
}

/** Historial de varias solicitudes a la vez, agrupado por solicitud. */
function sol_historiales(array $idsSolicitudes): array
{
    $oids = [];
    foreach ($idsSolicitudes as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $filas = col_q('solicitud_historial', ['solicitud_id' => ['$in' => $oids]], ['sort' => ['creado_en' => 1]]);
    $agrupado = [];
    foreach ($filas as $f) {
        $agrupado[(string)$f['solicitud_id']][] = $f;
    }
    return $agrupado;
}

/** Registra una nota en el historial de una solicitud. */
function sol_registrar_historial($idSolicitud, string $estado, string $nota = ''): void
{
    $o = oid($idSolicitud);
    if ($o === null) {
        return;
    }
    col_agregar('solicitud_historial', [
        'solicitud_id' => $o,
        'estado'       => $estado,
        'nota'         => $nota !== '' ? $nota : null,
        'creado_en'    => ahora_utc(),
    ]);
}

/** Conteo de solicitudes del mes en curso. */
function sol_creadas_en_mes(?string $anioMes = null): int
{
    $ym = $anioMes ?: date('Y-m');
    $desde = fecha_utc($ym . '-01 00:00:00');
    $hasta = fecha_utc(date('Y-m-t 23:59:59', strtotime($ym . '-01 00:00:00')));
    return col_contar('solicitudes', ['creado_en' => ['$gte' => $desde, '$lte' => $hasta]]);
}

/** Escapa los comodines de LIKE para usarlos en una expresión regular. */
function escapar_like(string $texto): string
{
    return preg_quote(trim($texto), '/');
}