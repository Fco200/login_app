<?php
/* ============================================================
   FV DIGITAL - Repositorio genérico CRUD
   ------------------------------------------------------------
   La mayoría de las colecciones del panel (servicios, proyectos,
   publicaciones, testimonios, cartas, productos...) comparten la
   misma forma: listar, ver, crear, actualizar y eliminar. Este
   helper evita repetir ese patrón 8 veces y garantiza que todas las
   escrituras pinnen `creado_en` / `actualizado_en`.
   ============================================================ */

/** Campos que nunca deben venir del POST (control de_mass_assignment). */
function crud_campos_permitidos(array $datos, array $permitidos): array
{
    $salida = [];
    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $datos)) {
            $salida[$campo] = $datos[$campo];
        }
    }
    return $salida;
}

/** Lista documentos de una colección con filtros y orden. */
function crud_listar(string $coleccion, array $filtro = [], array $opciones = []): array
{
    return col_q($coleccion, $filtro, $opciones);
}

/** Un documento por id. */
function crud_por_id(string $coleccion, $id): ?array
{
    return col_q1($coleccion, filtro_id($id));
}

/** Un documento por slug (único). */
function crud_por_slug(string $coleccion, string $slug, bool $soloActivo = false): ?array
{
    $f = ['slug' => $slug];
    if ($soloActivo) {
        $f['activo'] = 1;
    }
    return col_q1($coleccion, $f);
}

/**
 * Crea un documento. Los campos ausentes se rellenan con null para
 * que el esquema sea uniforme y los índices no fallen.
 */
function crud_crear(string $coleccion, array $datos, array $opciones = []): array
{
    $doc = $datos;
    $doc['creado_en'] = $doc['creado_en'] ?? ahora_utc();
    $doc['actualizado_en'] = ahora_utc();
    try {
        $id = col_agregar($coleccion, $doc);
        return ['ok' => true, 'id' => $id, 'mensaje' => $opciones['mensaje'] ?? 'Registro creado.'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => $opciones['error'] ?? ('No se pudo crear: ' . $e->getMessage())];
    }
}

/** Actualiza un documento por id. */
function crud_actualizar(string $coleccion, $id, array $datos, array $opciones = []): array
{
    if (oid($id) === null) {
        return ['ok' => false, 'mensaje' => 'Registro no encontrado.'];
    }
    if ($datos === []) {
        return ['ok' => true, 'mensaje' => $opciones['mensaje'] ?? 'Sin cambios.'];
    }
    $datos['actualizado_en'] = ahora_utc();
    try {
        col_actualizar($coleccion, $id, ['$set' => $datos]);
        return ['ok' => true, 'mensaje' => $opciones['mensaje'] ?? 'Registro actualizado.'];
    } catch (\Throwable $e) {
        return ['ok' => false, 'mensaje' => $opciones['error'] ?? ('No se pudo actualizar: ' . $e->getMessage())];
    }
}

/** Elimina un documento por id. */
function crud_eliminar(string $coleccion, $id): bool
{
    return col_borrar($coleccion, $id);
}

/**
 * Devuelve un slug único dentro de la colección.
 *
 * MySQL resolvía esto con el índice UNIQUE (o fallaba al insertar);
 * aquí se comprueba y se añade un sufijo numérico: "web", "web-2"...
 *
 * @param string      $coleccion Colección donde vive el slug.
 * @param string      $slug      Slug deseado.
 * @param string      $campo     Campo que almacena el slug.
 * @param string|null $ignorarId Documento que puede reutilizar su propio slug.
 */
function slug_unico(string $coleccion, string $slug, string $campo = 'slug', $ignorarId = null): string
{
    $base = trim($slug);
    if ($base === '') {
        $base = 'elemento-' . date('YmdHis');
    }

    $ignorar = oid($ignorarId);
    $candidato = $base;
    $n = 2;

    while (col_contar($coleccion, [$campo => $candidato]) > 0) {
        if ($ignorar !== null) {
            $existente = col_q1($coleccion, [$campo => $candidato], ['projection' => ['_id' => 1]]);
            if ($existente !== null && (string)($existente['id'] ?? '') === (string)$ignorar) {
                return $candidato;
            }
        }
        $candidato = $base . '-' . $n++;
    }

    return $candidato;
}

/**
 * Cuenta documentos de una colección (sustituye a
 * "SELECT COUNT(*) ... WHERE <condición SQL>").
 *
 * @param string $coleccion
 * @param array  $filtro    Filtro MongoDB, p. ej. ['estado' => 'nueva'].
 */
function contar_registros(string $coleccion, array $filtro = []): int
{
    return col_contar($coleccion, $filtro);
}

/**
 * Cuenta proyectos de un cliente agrupados por estado en una sola
 * ida y vuelta (sustituye los varios COUNT con subconsulta).
 *
 * @return array ['nueva' => 3, 'en_desarrollo' => 1, ...]
 */
function proyectos_conteo_por_estado($usuarioId = null): array
{
    $f = [];
    if ($usuarioId !== null && $usuarioId !== '' && $usuarioId !== 0) {
        $f['usuario_id'] = oid($usuarioId);
    }
    $pipeline = [
        ['$match' => $f],
        ['$group' => ['_id' => '$estado', 'total' => ['$sum' => 1]]],
    ];
    $filas = col('proyectos_inicio')->aggregate($pipeline, mongo_opts([]))->toArray();
    $out = [];
    foreach ($filas as $fila) {
        $out[(string)$fila['_id']] = (int)$fila['total'];
    }
    return $out;
}

/** Proyectos de un cliente (o todos) agrupados por estado. */
function proyectos_por_estado(): array
{
    return proyectos_conteo_por_estado(null);
}