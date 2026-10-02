<?php
/* ============================================================
   FV DIGITAL - Repositorio de usuarios (colección `usuarios`)
   ------------------------------------------------------------
   Documento:
     { _id, nombre, email, email_normalizado, telefono, rfc,
       direccion, password, rol, activo, creado_en, actualizado_en }
   `email_normalizado` es único en minúsculas para emular el
   UNIQUE KEY(email) de MySQL sin distinguir mayúsculas.
   ============================================================ */

/** Normaliza un correo para búsquedas y para el índice único. */
function usr_norm_email(string $email): string
{
    return strtolower(trim($email));
}

/** Busca un usuario por correo (sin distinguir mayúsculas). */
function usr_por_email(string $email): ?array
{
    return col_q1('usuarios', ['email_normalizado' => usr_norm_email($email)]);
}

/** Busca un usuario por id. */
function usr_por_id($id): ?array
{
    return col_q1('usuarios', filtro_id($id));
}

/** Existe un usuario con ese correo. */
function usr_email_existe(string $email, string $exceptoId = ''): bool
{
    $f = ['email_normalizado' => usr_norm_email($email)];
    if ($exceptoId !== '') {
        $o = oid($exceptoId);
        if ($o !== null) {
            $f['_id'] = ['$ne' => $o];
        }
    }
    return col_contar('usuarios', $f) > 0;
}

/**
 * Crea un usuario.
 * Devuelve ['ok'=>bool,'id'=>string,'mensaje'=>string].
 */
function usr_crear(array $d): array
{
    $nombre   = trim((string)($d['nombre'] ?? ''));
    $email    = usr_norm_email((string)($d['email'] ?? ''));
    $password = (string)($d['password'] ?? '');

    if ($nombre === '') {
        return ['ok' => false, 'mensaje' => 'El nombre es obligatorio.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensaje' => 'El correo no es válido.'];
    }
    if ($password === '') {
        return ['ok' => false, 'mensaje' => 'La contraseña es obligatoria.'];
    }
    if (usr_email_existe($email)) {
        return ['ok' => false, 'mensaje' => 'Ese correo ya está registrado.'];
    }

    $rol = (string)($d['rol'] ?? 'cliente');
    if (!in_array($rol, ['admin', 'vendedor', 'cliente'], true)) {
        $rol = 'cliente';
    }

    $doc = [
        'nombre'            => $nombre,
        'email'             => $email,
        'email_normalizado' => $email,
        'telefono'          => fv_texto($d['telefono'] ?? null),
        'rfc'               => fv_texto($d['rfc'] ?? null),
        'direccion'         => fv_texto($d['direccion'] ?? null),
        'password'          => password_hash($password, PASSWORD_BCRYPT),
        'rol'               => $rol,
        'activo'            => fv_int($d['activo'] ?? 1, 1),
        'creado_en'         => ahora_utc(),
    ];

    try {
        $id = col_agregar('usuarios', $doc);
        return ['ok' => true, 'id' => $id, 'mensaje' => 'Usuario creado correctamente.'];
    } catch (\Throwable $e) {
        if (mongo_es_clave_duplicada($e)) {
            return ['ok' => false, 'mensaje' => 'Ese correo ya está registrado.'];
        }
        return ['ok' => false, 'mensaje' => 'No se pudo crear el usuario: ' . $e->getMessage()];
    }
}

/**
 * Actualiza un usuario. No toca `password` salvo que se envíe.
 * Devuelve ['ok'=>bool,'mensaje'=>string].
 */
function usr_actualizar($id, array $d): array
{
    $o = oid($id);
    if ($o === null) {
        return ['ok' => false, 'mensaje' => 'Usuario no encontrado.'];
    }
    $actual = col_q1('usuarios', ['_id' => $o]);
    if ($actual === null) {
        return ['ok' => false, 'mensaje' => 'Usuario no encontrado.'];
    }

    $set = ['actualizado_en' => ahora_utc()];

    if (array_key_exists('nombre', $d)) {
        $set['nombre'] = trim((string)$d['nombre']);
    }
    if (array_key_exists('email', $d)) {
        $email = usr_norm_email((string)$d['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'El correo no es válido.'];
        }
        if (usr_email_existe($email, (string)$o)) {
            return ['ok' => false, 'mensaje' => 'Ese correo ya está registrado.'];
        }
        $set['email'] = $email;
        $set['email_normalizado'] = $email;
    }
    foreach (['telefono', 'rfc', 'direccion'] as $campo) {
        if (array_key_exists($campo, $d)) {
            $set[$campo] = fv_texto($d[$campo]);
        }
    }
    if (array_key_exists('rol', $d) && in_array($d['rol'], ['admin', 'vendedor', 'cliente'], true)) {
        $set['rol'] = $d['rol'];
    }
    if (array_key_exists('activo', $d)) {
        $set['activo'] = fv_int($d['activo'], 1);
    }
    if (!empty($d['password'])) {
        $set['password'] = password_hash((string)$d['password'], PASSWORD_BCRYPT);
    }

    try {
        col('usuarios')->updateOne(['_id' => $o], ['$set' => $set], mongo_opts([]));
        return ['ok' => true, 'mensaje' => 'Usuario actualizado.'];
    } catch (\Throwable $e) {
        if (mongo_es_clave_duplicada($e)) {
            return ['ok' => false, 'mensaje' => 'Ese correo ya está registrado.'];
        }
        return ['ok' => false, 'mensaje' => 'No se pudo actualizar: ' . $e->getMessage()];
    }
}

/** Verifica la contraseña de un usuario. */
function usr_verificar_password(array $usuario, string $clave): bool
{
    $hash = (string)($usuario['password'] ?? '');
    if ($hash === '') {
        return false;
    }
    return password_verify($clave, $hash);
}

/** Lista usuarios, opcionalmente filtrada por rol. */
function usr_listar(string $rol = '', array $extra = []): array
{
    $f = $extra;
    if ($rol !== '') {
        $f['rol'] = $rol;
    }
    return col_q('usuarios', $f, ['sort' => ['nombre' => 1]]);
}

/**
 * Directorio de clientes con conteos de actividad (solicitudes, pagos
 * aprobados y proyectos). Reemplaza los tres sub-SELECT correlacionados
 * que el panel usaba en SQL.
 */
function usr_clientes_con_conteos(string $q = ''): array
{
    $f = ['rol' => 'cliente'];
    if ($q !== '') {
        $rx = rx(escapar_like($q));
        $f['$or'] = [
            ['nombre' => $rx],
            ['email' => $rx],
            ['telefono' => $rx],
        ];
    }
    $clientes = col_q('usuarios', $f, ['sort' => ['creado_en' => -1]]);
    if (empty($clientes)) {
        return [];
    }

    $ids = array_map(static fn($c) => $c['id'], $clientes);

    $conteoSolicitudes = [];
    try {
        $filas = col('solicitudes')->aggregate([
            ['$match' => ['usuario_id' => ['$in' => array_map('oid', $ids)]]],
            ['$group' => ['_id' => '$usuario_id', 'n' => ['$sum' => 1]]],
        ], mongo_opts([]))->toArray();
        foreach ($filas as $f) {
            $conteoSolicitudes[(string)$f['_id']] = (int)$f['n'];
        }
    } catch (\Throwable $e) {
        $conteoSolicitudes = [];
    }

    $conteoPagos = [];
    try {
        $filas = col('pagos')->aggregate([
            ['$match' => ['usuario_id' => ['$in' => array_map('oid', $ids)], 'estado' => 'aprobado']],
            ['$group' => ['_id' => '$usuario_id', 'n' => ['$sum' => 1]]],
        ], mongo_opts([]))->toArray();
        foreach ($filas as $f) {
            $conteoPagos[(string)$f['_id']] = (int)$f['n'];
        }
    } catch (\Throwable $e) {
        $conteoPagos = [];
    }

    $conteoProyectos = [];
    try {
        $filas = col('proyectos_inicio')->aggregate([
            ['$match' => ['usuario_id' => ['$in' => array_map('oid', $ids)]]],
            ['$group' => ['_id' => '$usuario_id', 'n' => ['$sum' => 1]]],
        ], mongo_opts([]))->toArray();
        foreach ($filas as $f) {
            $conteoProyectos[(string)$f['_id']] = (int)$f['n'];
        }
    } catch (\Throwable $e) {
        $conteoProyectos = [];
    }

    foreach ($clientes as $i => $c) {
        $hex = (string)$c['id'];
        $clientes[$i]['n_solicitudes'] = $conteoSolicitudes[$hex] ?? 0;
        $clientes[$i]['n_pagos']       = $conteoPagos[$hex] ?? 0;
        $clientes[$i]['n_proyectos']   = $conteoProyectos[$hex] ?? 0;
    }
    return $clientes;
}

/** Varios usuarios en una sola consulta, indexados por su id hex. */
function usr_por_ids(array $ids): array
{
    $oids = [];
    foreach ($ids as $id) {
        $o = oid($id);
        if ($o !== null) {
            $oids[] = $o;
        }
    }
    if (empty($oids)) {
        return [];
    }
    $filas = col_q('usuarios', ['_id' => ['$in' => $oids]]);
    $out = [];
    foreach ($filas as $f) {
        $out[(string)$f['id']] = $f;
    }
    return $out;
}

/** Ids de todos los administradores activos. */
function usr_ids_admins_activos(): array
{
    $filas = col_q('usuarios', ['rol' => 'admin', 'activo' => 1], [
        'projection' => ['nombre' => 1],
    ]);
    $ids = [];
    foreach ($filas as $f) {
        $ids[] = $f['id'];
    }
    return $ids;
}

/** Elimina un usuario (y sus datos dependientes). */
function usr_eliminar($id): bool
{
    return col_borrar('usuarios', $id);
}

/** Recalcula el conteo de(role, activo) para el panel. */
function usr_conteo_por_rol(): array
{
    $pipeline = [
        ['$group' => ['_id' => ['rol' => '$rol', 'activo' => '$activo'], 'n' => ['$sum' => 1]]],
    ];
    $filas = col('usuarios')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $f) {
        $rol    = (string)($f['_id']['rol'] ?? '');
        $activo = (int)($f['_id']['activo'] ?? 0);
        $k = $rol . ':' . $activo;
        $out[$k] = (int)$f['n'];
    }
    return $out;
}