<?php
/* ============================================================
   FV DIGITAL - Notificaciones, mensajería y contacto
   ------------------------------------------------------------
   Colecciones: `notificaciones`, `mensajes_portal`,
                `mensajes_contacto`.
   ============================================================ */

/* ============================================================
   Notificaciones
   ============================================================ */

/** Crea una notificación para un usuario. */
function notificar($usuarioId, string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): bool
{
    if (oid($usuarioId) === null) {
        return false;
    }
    try {
        col_agregar('notificaciones', [
            'usuario_id' => oid($usuarioId),
            'tipo'       => $tipo !== '' ? $tipo : 'info',
            'titulo'     => $titulo,
            'mensaje'    => $mensaje !== '' ? $mensaje : null,
            'enlace'     => $enlace,
            'leida'      => false,
            'creado_en'  => ahora_utc(),
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log('[FV-Mongo] notificar: ' . $e->getMessage());
        return false;
    }
}

/** Notifica a TODOS los administradores activos. Devuelve cuántos avisó. */
function notificar_admins(string $tipo, string $titulo, string $mensaje = '', ?string $enlace = null): int
{
    $avisados = 0;
    foreach (usr_ids_admins_activos() as $adminId) {
        if (notificar($adminId, $tipo, $titulo, $mensaje, $enlace)) {
            $avisados++;
        }
    }
    return $avisados;
}

/** Notificaciones de un usuario, más recientes primero. */
function notif_de_usuario($usuarioId, int $limite = 0): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('notificaciones', ['usuario_id' => $o], $op);
}

/** Notificaciones no leídas de un usuario. */
function notif_no_leidas($usuarioId, int $limite = 0): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return col_contar('notificaciones', ['usuario_id' => $o, 'leida' => false]);
}

/** Marca como leídas todas las notificaciones de un usuario. */
function notif_marcar_leidas($usuarioId): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return col_actualizar_varios(
        'notificaciones',
        ['usuario_id' => $o, 'leida' => false],
        ['$set' => ['leida' => true, 'leida_en' => ahora_utc()]]
    );
}

/** Marca una notificación concreta como leída. */
function notif_marcar_leida($usuarioId, $notifId): bool
{
    $oUsuario = oid($usuarioId);
    if ($oUsuario === null) {
        return false;
    }
    return col_actualizar('notificaciones', $notifId, [
        '$set' => ['leida' => true, 'leida_en' => ahora_utc()],
    ]);
}

/** ¿Existe ya una notificación de este tipo para este usuario? */
function notif_existe($usuarioId, string $tipo): bool
{
    $o = oid($usuarioId);
    if ($o === null) {
        return false;
    }
    return col_contar('notificaciones', ['usuario_id' => $o, 'tipo' => $tipo]) > 0;
}

/* ============================================================
   Mensajería del portal (chat cliente <-> negocio)
   ============================================================ */

function mp_enviar($usuarioId, string $remitente, string $mensaje): array
{
    $o = oid($usuarioId);
    $texto = trim($mensaje);
    if ($o === null || $texto === '') {
        return ['ok' => false, 'mensaje' => 'No se pudo enviar el mensaje.'];
    }
    if (!in_array($remitente, ['cliente', 'negocio'], true)) {
        $remitente = 'cliente';
    }
    try {
        $id = col_agregar('mensajes_portal', [
            'usuario_id' => $o,
            'remitente'  => $remitente,
            'mensaje'    => $texto,
            'leido'      => false,
            'creado_en'  => ahora_utc(),
        ]);
        return ['ok' => true, 'id' => $id, 'mensaje' => 'Mensaje enviado.'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo enviar el mensaje: ' . $e->getMessage()];
    }
}

/** Conversación completa de un cliente (cronológica). */
function mp_conversacion($usuarioId): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    $filas = col_q('mensajes_portal', ['usuario_id' => $o], ['sort' => ['creado_en' => 1]]);
    $nombre = usr_por_id($usuarioId)['nombre'] ?? '';
    foreach ($filas as &$m) {
        $m['nombre'] = $m['remitente'] === 'negocio' ? (datos_emisor()['nombre'] ?? SITE_NOMBRE) : $nombre;
    }
    unset($m);
    return $filas;
}

/** Últimos mensajes de un cliente (para el panel). */
function mp_ultimos($usuarioId, int $limite = 5): array
{
    $o = oid($usuarioId);
    if ($o === null) {
        return [];
    }
    return col_q('mensajes_portal', ['usuario_id' => $o], ['sort' => ['creado_en' => -1], 'limit' => $limite]);
}

/** Marca como leídos los mensajes enviados por el cliente. */
function mp_marcar_leidos_cliente($usuarioId): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return col_actualizar_varios('mensajes_portal', ['usuario_id' => $o, 'remitente' => 'cliente'], ['$set' => ['leido' => true]]);
}

/**
 * Marca como leídos los mensajes que envió el negocio a este cliente.
 * Es lo que ocurre cuando el cliente abre (o refresca) el chat.
 */
function mp_marcar_leidos_negocio($usuarioId): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return col_actualizar_varios('mensajes_portal', ['usuario_id' => $o, 'remitente' => 'negocio'], ['$set' => ['leido' => true]]);
}

/** Clientes que tienen conversación, con su último mensaje. */
function mp_conversaciones_recientes(int $limite = 0): array
{
    $pipeline = [
        ['$sort' => ['creado_en' => -1]],
        ['$group' => ['_id' => '$usuario_id', 'ultimo' => ['$first' => '$$ROOT']]],
        ['$sort' => ['ultimo.creado_en' => -1]],
    ];
    if ($limite > 0) {
        $pipeline[] = ['$limit' => $limite];
    }
    $filas = col('mensajes_portal')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $f) {
        $usuario = usr_por_id($f['_id']);
        $ultimo = bson_doc($f['ultimo']);
        if ($usuario !== null) {
            $ultimo['usuario'] = $usuario;
            $ultimo['nombre'] = $usuario['nombre'];
            $ultimo['email'] = $usuario['email'];
        }
        $out[] = $ultimo;
    }
    return $out;
}

/** Mensajes sin leer de los clientes (badge del panel). */
function mp_no_leidos_clientes(): int
{
    return col_contar('mensajes_portal', ['remitente' => 'cliente', 'leido' => false]);
}

/**
 * Conversaciones del panel administrativo: una fila por cliente, con el
 * último mensaje, la fecha del último envío y los pendientes de respuesta.
 *
 * Reemplaza el GROUP BY con SUM(CASE...) y la SUBSTRING anidada.
 *
 * @return array [['id','nombre','email','ultimo_mensaje','ultimo','pendientes'], .]
 */
function mp_conversaciones_panel(): array
{
    try {
        $filas = col('mensajes_portal')->aggregate([
            ['$group' => [
                '_id'        => '$usuario_id',
                'ultimo'     => ['$first' => '$$ROOT'],
                'ultimo_fecha'=> ['$max' => '$creado_en'],
                'pendientes' => ['$sum' => ['$cond' => [[
                    '$and' => [
                        ['$eq' => ['$remitente', 'cliente']],
                        ['$ne' => ['$leido', true]],
                    ],
                ], 1, 0]]],
            ]],
            ['$sort' => ['pendientes' => -1, 'ultimo_fecha' => -1]],
        ], mongo_opts([]))->toArray();
    } catch (\Throwable $e) {
        return [];
    }

    $usuarios = usr_por_ids(array_map(static fn($f) => (string)$f['_id'], $filas));

    $out = [];
    foreach ($filas as $fila) {
        $clave = (string)$fila['_id'];
        $usuario = $usuarios[$clave] ?? null;
        if ($usuario === null) {
            continue;
        }
        $ultimo = bson_doc($fila['ultimo']);
        $out[] = [
            'id'            => $clave,
            'usuario_id'    => $clave,
            'nombre'        => $usuario['nombre'] ?? '',
            'email'         => $usuario['email'] ?? '',
            'ultimo_mensaje'=> mb_strimwidth((string)($ultimo['mensaje'] ?? ''), 0, 60, '…'),
            'ultimo'        => $ultimo['creado_en'] ?? null,
            'pendientes'    => (int)($fila['pendientes'] ?? 0),
        ];
    }
    return $out;
}

/** Mensajes del cliente aún sin leer (badge del portal). */
function mp_no_leidas_de_usuario($usuarioId): int
{
    $o = oid($usuarioId);
    if ($o === null) {
        return 0;
    }
    return col_contar('mensajes_portal', ['usuario_id' => $o, 'remitente' => 'cliente', 'leido' => false]);
}

/* ============================================================
   Mensajes del formulario de contacto
   ============================================================ */

function contacto_registrar(array $d): array
{
    $nombre  = trim((string)($d['nombre'] ?? ''));
    $email   = strtolower(trim((string)($d['email'] ?? '')));
    $mensaje = trim((string)($d['mensaje'] ?? ''));
    if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $mensaje === '') {
        return ['ok' => false, 'mensaje' => 'Completa tu nombre, correo y mensaje.'];
    }
    try {
        col_agregar('mensajes_contacto', [
            'nombre'    => $nombre,
            'email'     => $email,
            'telefono'  => fv_texto($d['telefono'] ?? null),
            'asunto'    => fv_texto($d['asunto'] ?? null),
            'mensaje'   => $mensaje,
            'leido'     => false,
            'creado_en' => ahora_utc(),
        ]);
        notificar_admins('info', 'Nuevo mensaje de contacto', $nombre . ' escribió desde el formulario de contacto.', url_sitio('admin/mensajes.php'));
        return ['ok' => true, 'mensaje' => '¡Mensaje recibido! Te responderemos pronto.'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => 'No se pudo enviar el mensaje: ' . $e->getMessage()];
    }
}

function contacto_listar(bool $soloNoLeidos = false, int $limite = 0): array
{
    $f = $soloNoLeidos ? ['leido' => false] : [];
    $op = ['sort' => ['leido' => 1, 'creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('mensajes_contacto', $f, $op);
}

function contacto_no_leidos(): int
{
    return col_contar('mensajes_contacto', ['leido' => false]);
}

function contacto_por_id($id): ?array
{
    return col_q1('mensajes_contacto', filtro_id($id));
}

function contacto_marcar_leido($id, bool $leido = true): bool
{
    return col_actualizar('mensajes_contacto', $id, ['$set' => ['leido' => $leido]]);
}

function contacto_marcar_todos_leidos(): int
{
    return col_actualizar_varios('mensajes_contacto', ['leido' => false], ['$set' => ['leido' => true]]);
}

function contacto_eliminar($id): bool
{
    return col_borrar('mensajes_contacto', $id);
}

/* ============================================================
   Suscripciones al boletín
   ============================================================ */

function suscripcion_activar(string $email): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    col('suscripciones')->updateOne(
        ['email' => $email],
        [
            '$set' => ['email' => $email, 'activo' => true],
            '$setOnInsert' => ['creado_en' => ahora_utc()],
        ],
        mongo_opts(['upsert' => true])
    );
    return true;
}

function suscripcion_activa(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }
    return col_contar('suscripciones', ['email' => $email, 'activo' => true]) > 0;
}

function suscripcion_alternar(string $email, bool $activo): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    col('suscripciones')->updateOne(
        ['email' => $email],
        [
            '$set' => ['email' => $email, 'activo' => $activo],
            '$setOnInsert' => ['creado_en' => ahora_utc()],
        ],
        mongo_opts(['upsert' => true])
    );
    return true;
}

function suscripcion_listar(): array
{
    return col_q('suscripciones', [], ['sort' => ['creado_en' => -1]]);
}

function suscripcion_eliminar($id): bool
{
    return col_borrar('suscripciones', $id);
}

/** Da de alta o de baja la suscripción indicada por su id. */
function suscripcion_alternar_id($id): bool
{
    $actual = col_q1('suscripciones', filtro_id($id));
    if ($actual === null) {
        return false;
    }
    return col_actualizar('suscripciones', $id, [
        '$set' => ['activo' => empty($actual['activo'])],
    ]);
}