<?php
/* ============================================================
   FV DIGITAL - Soporte, juegos y módulo concesionaria
   ------------------------------------------------------------
   Colecciones: `soporte`, `juego_puntajes`, `vehiculos`.
   ============================================================ */

/* ============================================================
   Soporte técnico
   ============================================================ */

function sop_estados(): array
{
    return ['nuevo', 'atendido', 'cerrado'];
}

/** Categorías que el cliente puede elegir al abrir un ticket. */
function sop_categorias(): array
{
    return ['problema', 'duda', 'sugerencia', 'mejora', 'otro'];
}

function sop_crear(array $d): array
{
    $descripcion = trim((string)($d['descripcion'] ?? ''));
    if ($descripcion === '') {
        return ['ok' => false, 'mensaje' => 'Describe tu problema para poder ayudarte.'];
    }
    $categoria = (string)($d['categoria'] ?? 'problema');
    if (!in_array($categoria, sop_categorias(), true)) {
        $categoria = 'problema';
    }

    $id = col_agregar('soporte', [
        'usuario_id'   => isset($d['usuario_id']) ? oid($d['usuario_id']) : null,
        'nombre'       => trim((string)($d['nombre'] ?? '')),
        'email'        => strtolower(trim((string)($d['email'] ?? ''))),
        'categoria'    => $categoria,
        'pagina'       => fv_texto($d['pagina'] ?? null),
        'descripcion'  => $descripcion,
        'estado'       => 'nuevo',
        'respuesta'    => null,
        'atendido_en'  => null,
        'creado_en'    => ahora_utc(),
    ]);

    notificar_admins('info', 'Nuevo ticket de soporte', ($d['nombre'] ?? 'Un cliente') . ' abrió un ticket.', url_sitio('admin/soporte.php'));
    return ['ok' => true, 'id' => $id, 'mensaje' => 'Ticket registrado. Te responderemos pronto.'];
}

function sop_listar(string $estado = ''): array
{
    $f = $estado !== '' && in_array($estado, sop_estados(), true) ? ['estado' => $estado] : [];
    return col_q('soporte', $f, ['sort' => ['creado_en' => -1]]);
}

function sop_de_cliente($usuarioId, string $email = '', int $limite = 50): array
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
    return col_q('soporte', $f, $op);
}

function sop_por_id($id): ?array
{
    return col_q1('soporte', filtro_id($id));
}

/** Responde un ticket y lo marca como atendido. */
function sop_responder($id, string $respuesta, string $estado = 'atendido'): array
{
    $o = oid($id);
    if ($o === null) {
        return ['ok' => false, 'mensaje' => 'Ticket no encontrado.'];
    }
    $respuesta = trim($respuesta);
    if ($respuesta === '') {
        return ['ok' => false, 'mensaje' => 'Escribe la respuesta al cliente.'];
    }
    if (!in_array($estado, sop_estados(), true)) {
        $estado = 'atendido';
    }
    $ticket = col_q1('soporte', ['_id' => $o]);
    col('soporte')->updateOne(['_id' => $o], [
        '$set' => [
            'respuesta'   => $respuesta,
            'estado'      => $estado,
            'atendido_en' => ahora_utc(),
        ],
    ], mongo_opts([]));

    if ($ticket !== null && !empty($ticket['email'])) {
        enviar_correo(
            $ticket['email'],
            'Tu ticket de soporte fue atendido — ' . SITE_NOMBRE,
            correo_plantilla(
                'Respuesta a tu ticket',
                '<p>Hola <b>' . e($ticket['nombre'] ?? '') . '</b>,</p>'
                . '<p>Respondimos tu solicitud de soporte:</p>'
                . '<p style="background:#eef3fb;padding:14px;border-radius:8px">' . nl2br(e($respuesta)) . '</p>'
            )
        );
    }
    return ['ok' => true, 'mensaje' => 'Respuesta enviada.'];
}

function sop_cambiar_estado($id, string $estado): bool
{
    if (!in_array($estado, sop_estados(), true)) {
        return false;
    }
    return col_actualizar('soporte', $id, ['$set' => ['estado' => $estado]]);
}

function sop_eliminar($id): bool
{
    return col_borrar('soporte', $id);
}

function sop_no_leidos(): int
{
    return col_contar('soporte', ['estado' => 'nuevo']);
}

/* ============================================================
   Mini-juegos (tabla de puntajes)
   ============================================================ */

/** Guarda un puntaje y devuelve el mejor marca del usuario. */
function juego_guardar($usuarioId, string $juego, int $puntaje): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    col_agregar('juego_puntajes', [
        'usuario_id' => $o,
        'juego'      => $juego,
        'puntaje'    => $puntaje,
        'creado_en'  => ahora_utc(),
    ]);
    return juego_mejor($usuarioId, $juego);
}

/** Mejor puntaje del usuario en un juego. */
function juego_mejor($usuarioId, string $juego): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    $doc = col_q1(
        'juego_puntajes',
        ['usuario_id' => $o, 'juego' => $juego],
        ['sort' => ['puntaje' => -1], 'limit' => 1, 'projection' => ['puntaje' => 1]]
    );
    return (int)($doc['puntaje'] ?? 0);
}

/** Mejores puntajes del usuario por juego. */
function juego_mejores($usuarioId): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $pipeline = [
        ['$match' => ['usuario_id' => $o]],
        ['$group' => ['_id' => '$juego', 'mejor' => ['$max' => '$puntaje']]],
        ['$sort' => ['mejor' => -1]],
    ];
    $filas = col('juego_puntajes')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $f) {
        $out[(string)$f['_id']] = (int)$f['mejor'];
    }
    return $out;
}

/** Tabla de posiciones global (con nombre del jugador). */
function juego_tablero(string $juego, int $limite = 10): array
{
    $pipeline = [
        ['$match' => ['juego' => $juego]],
        ['$group' => ['_id' => '$usuario_id', 'mejor' => ['$max' => '$puntaje'], 'fecha' => ['$max' => '$creado_en']]],
        ['$sort' => ['mejor' => -1]],
        ['$limit' => $limite],
    ];
    $filas = col('juego_puntajes')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $f) {
        $u = usr_por_id($f['_id']);
        $out[] = [
            'puntaje' => (int)$f['mejor'],
            'nombre'  => $u['nombre'] ?? 'Jugador',
            'fecha'   => fecha_php($f['fecha'] ?? ''),
        ];
    }
    return $out;
}

/* ============================================================
   Módulo concesionaria (demostración)
   ------------------------------------------------------------
   Colección `vehiculos`, independiente del portal principal.
   ============================================================ */

function veh_guardar($id, array $d): array
{
    $vin = strtoupper(trim((string)($d['vin'] ?? '')));
    if ($vin === '') {
        return ['ok' => false, 'mensaje' => 'El VIN es obligatorio.'];
    }
    $campos = [
        'vin'        => $vin,
        'marca'      => trim((string)($d['marca'] ?? '')),
        'modelo'     => trim((string)($d['modelo'] ?? '')),
        'anio'       => fv_int($d['anio'] ?? 0),
        'color'      => fv_texto($d['color'] ?? null),
        'kilometraje'=> fv_int($d['kilometraje'] ?? 0),
        'precio'     => fv_float($d['precio'] ?? 0),
        'estado'     => fv_texto($d['estado'] ?? 'disponible') ?? 'disponible',
    ];
    return !es_nuevo($id)
        ? crud_actualizar('vehiculos', $id, $campos)
        : crud_crear('vehiculos', $campos + ['creado_por' => fv_texto($_SESSION['nombre'] ?? 'Panel')]);
}

function veh_listar(string $buscar = ''): array
{
    $f = [];
    if ($buscar !== '') {
        $rx = rx(escapar_like($buscar));
        $f['$or'] = [['vin' => $rx], ['marca' => $rx], ['modelo' => $rx]];
    }
    return col_q('vehiculos', $f, ['sort' => ['creado_en' => -1]]);
}

function veh_por_id($id): ?array
{
    return col_q1('vehiculos', filtro_id($id));
}

function veh_eliminar($id): bool
{
    return col_borrar('vehiculos', $id);
}