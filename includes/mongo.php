<?php
/* ============================================================
   FV DIGITAL - Capa de acceso a datos (MongoDB)
   ------------------------------------------------------------
   Primitivas used por todos los repositorios y páginas.

   Reglas de la capa:
     · Los documentos se devuelven SIEMPRE como arrays asociativos
       planos, con la clave `id` (hex de 24) en lugar de `_id`.
     · Los BSONDateTime se devuelven como cadenas 'Y-m-d H:i:s'
       para que las vistas puedan seguir usando strtotime()/date().
     · Nada de PDO: no existen transacciones SQL; se usan sesiones de
       MongoDB (Atlas es un replica set, por lo que sí las soporta).
   ============================================================ */

require_once __DIR__ . '/../conexion.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\Exception as DriverException;

/* ============================================================
   1) Identificadores
   ============================================================ */

/** Id nuevo (hex de 24 caracteres) listo para usar en HTML y consultas. */
function nuevo_id(): string
{
    return (string)new ObjectId();
}

/** Convierte hex / ObjectId / array['_id'] en un ObjectId consultable. */
function oid($valor): ?ObjectId
{
    if ($valor instanceof ObjectId) {
        return $valor;
    }
    if (is_array($valor) && isset($valor['_id'])) {
        return oid($valor['_id']);
    }
    $hex = trim((string)$valor);
    if ($hex === '') {
        return null;
    }
    return ObjectId::isValid($hex) ? new ObjectId($hex) : null;
}

/** Devuelve el 'id' hexadecimal de un documento (objeto o array). */
function id_de($doc): string
{
    if (is_array($doc) && isset($doc['_id'])) {
        return (string)$doc['_id'];
    }
    return (string)$doc;
}

/** Filtro por id: acepta cualquier forma de id y devuelve el filtro Mongo. */
function filtro_id($id): array
{
    $o = oid($id);
    return $o === null ? ['_id' => '__id_invalido__'] : ['_id' => $o];
}

/**
 * ¿Este id corresponde a un documento NUEVO (o a ninguno)?
 *
 * Sustituye al clásico "$id > 0": los ids de MongoDB son cadenas hex de
 * 24 caracteres y compararlos con enteros no es fiable (un id que empieza
 * por letra se evaluaría como 0).
 */
function es_nuevo($id): bool
{
    if ($id === null || $id === 0 || $id === '0' || $id === false) {
        return true;
    }
    if (is_string($id)) {
        return trim($id) === '';
    }
    return oid($id) === null;
}

/* ============================================================
   2) Fechas
   ============================================================ */

/** Zona horaria de la aplicación (la misma que usa config.php). */
function fv_tz(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new DateTimeZone(date_default_timezone_get());
    }
    return $tz;
}

/**
 * Convierte una fecha a UTCDateTime. Si el texto no trae zona, se
 * interpreta en la zona horaria de la aplicación para que el viaje de
 * ida y vuelta sea estable.
 */
function fecha_utc($fecha): ?UTCDateTime
{
    if ($fecha instanceof UTCDateTime) {
        return $fecha;
    }
    if ($fecha instanceof \DateTimeInterface) {
        return new UTCDateTime((int)round((float)$fecha->format('U.u') * 1000));
    }
    if ($fecha instanceof \MongoDB\Model\BSONDocument) {
        $fecha = $fecha->getArrayCopy();
    }
    $texto = trim((string)$fecha);
    if ($texto === '' || str_starts_with($texto, '0000-00-00')) {
        return null;
    }
    try {
        $dt = new \DateTimeImmutable($texto, fv_tz());
    } catch (\Exception $e) {
        return null;
    }
    return new UTCDateTime((int)round((float)$dt->format('U.u') * 1000));
}

/** Normaliza cualquier valor de fecha a cadena 'Y-m-d H:i:s' (o ''). */
function fecha_php($fecha, string $formato = 'Y-m-d H:i:s'): string
{
    if ($fecha instanceof UTCDateTime) {
        $dt = $fecha->toDateTime()->setTimezone(fv_tz());
        return $dt->format($formato);
    }
    if ($fecha instanceof \DateTimeInterface) {
        return $fecha->format($formato);
    }
    if (is_int($fecha)) {
        return (new \DateTimeImmutable('@' . $fecha))->setTimezone(fv_tz())->format($formato);
    }
    $texto = trim((string)$fecha);
    if ($texto === '') {
        return '';
    }
    try {
        $dt = new \DateTimeImmutable($texto, fv_tz());
    } catch (\Exception $e) {
        return '';
    }
    return $dt->format($formato);
}

/** Marca de tiempo actual como UTCDateTime. */
function ahora_utc(): UTCDateTime
{
    return new UTCDateTime((int)round(microtime(true) * 1000));
}

/** Normaliza recursivamente un filtro para poder guardar como UTCDateTime. */
function fv_fechas($dato)
{
    if (is_array($dato)) {
        $out = [];
        foreach ($dato as $k => $v) {
            $out[$k] = fv_fechas($v);
        }
        return $out;
    }
    return $dato;
}

/* ============================================================
   3) Traducción BSON -> array plano
   ============================================================ */

/**
 * Convierte un documento BSON en array asociativo plano:
 *   _id  -> 'id'  (hex)
 *   fecha-> 'Y-m-d H:i:s'
 */
function bson_doc($doc): array
{
    if ($doc instanceof \MongoDB\Model\BSONDocument) {
        $doc = $doc->getArrayCopy();
    }
    if (!is_array($doc)) {
        return [];
    }
    $salida = [];
    foreach ($doc as $clave => $valor) {
        if ($clave === '_id') {
            $salida['id'] = (string)$valor;
            continue;
        }
        if ($valor instanceof ObjectId) {
            $salida[$clave] = (string)$valor;
            continue;
        }
        if ($valor instanceof UTCDateTime) {
            $salida[$clave] = fecha_php($valor);
            continue;
        }
        if ($valor instanceof \MongoDB\Model\BSONDocument || $valor instanceof \MongoDB\Model\BSONArray) {
            $salida[$clave] = bson_doc($valor->getArrayCopy());
            continue;
        }
        $salida[$clave] = $valor;
    }
    return $salida;
}

/** Convierte un cursor completo (o array de documentos) a array plano. */
function bson_docs($docs): array
{
    $lista = is_array($docs) || $docs instanceof \Traversable ? iterator_to_array($docs) : [];
    $out = [];
    foreach ($lista as $doc) {
        $out[] = bson_doc($doc);
    }
    return $out;
}

/** Convierte unaaggregation "documento único" (BSON) a array plano. */
function bson_row($doc): array
{
    return bson_doc($doc);
}

/* ============================================================
   4) Transacciones (sessions de MongoDB)
   ============================================================ */

/** Sesión activa, si la hay. */
function mongo_sesion_actual(): ?\MongoDB\Driver\Session
{
    return $GLOBALS['__fv_sesion'] ?? null;
}

/** Añade la sesión activa a las opciones del driver, si corresponde. */
function mongo_opts(array $opciones): array
{
    $ses = mongo_sesion_actual();
    if ($ses !== null) {
        $opciones['session'] = $ses;
    }
    return $opciones;
}

/**
 * Ejecuta $fn dentro de una transacción de MongoDB con reintentos
 * automáticos ante errores transitorios. Si el clúster no soporta
 * transacciones, ejecuta el cuerpo sin ella (degradación segura).
 *
 * @return mixed Lo que devuelva $fn.
 */
function tx(callable $fn)
{
    if (mongo_sesion_actual() !== null) {
        // Ya estamos dentro de una transacción: no anidar.
        return $fn();
    }

    $sesion = null;
    try {
        $sesion = mongo_client()->startSession();
    } catch (\Throwable $e) {
        return $fn(); // sin soporte de sesiones: degradación segura
    }

    $GLOBALS['__fv_sesion'] = $sesion;
    try {
        return $sesion->withTransaction(
            $fn,
            [
                'readConcern' => new \MongoDB\Driver\ReadConcern(\MongoDB\Driver\ReadConcern::LOCAL),
                'writeConcern' => new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY),
                'readPreference' => new \MongoDB\Driver\ReadPreference(\MongoDB\Driver\ReadPreference::PRIMARY),
            ]
        );
    } catch (\Throwable $e) {
        error_log('[FV-Mongo] Transacción revertida: ' . $e->getMessage());
        throw $e;
    } finally {
        $GLOBALS['__fv_sesion'] = null;
        try {
            $sesion->endSession();
        } catch (\Throwable $e) {
            // ignorado
        }
    }
}

/* ============================================================
   5) Primitivas de colección
   ============================================================ */

/**
 * Busca documentos.
 * $opciones: ['sort' => ['campo'=>1|-1], 'limit' => int, 'skip' => int,
 *              'projection' => [...], 'count' => bool]
 */
function col_q(string $coleccion, array $filtro = [], array $opciones = []): array
{
    $cursor = col($coleccion)->find(
        $filtro,
        mongo_opts(array_filter([
            'sort'      => $opciones['sort'] ?? null,
            'limit'     => $opciones['limit'] ?? null,
            'skip'      => $opciones['skip'] ?? null,
            'projection'=> $opciones['projection'] ?? null,
        ], static fn($v) => $v !== null && $v !== [])
    ));
    if (!empty($opciones['count'])) {
        return [count($cursor->toArray())];
    }
    return bson_docs($cursor);
}

/** Primer documento que coincide, o null. */
function col_q1(string $coleccion, array $filtro = [], array $opciones = []): ?array
{
    $filas = col_q($coleccion, $filtro, $opciones + ['limit' => 1]);
    return $filas[0] ?? null;
}

/** Número de documentos que coinciden. */
function col_contar(string $coleccion, array $filtro = []): int
{
    return (int)col($coleccion)->countDocuments($filtro, mongo_opts([]));
}

/** Devuelve sólo un campo (como el fetchColumn de PDO). */
function col_valor(string $coleccion, array $filtro, string $campo, $defecto = null)
{
    $doc = col_q1($coleccion, $filtro, ['projection' => [$campo => 1]]);
    if ($doc === null || !array_key_exists($campo, $doc)) {
        return $defecto;
    }
    return $doc[$campo];
}

/** Suma de un campo numérico en los documentos que cumplen el filtro. */
function col_suma(string $coleccion, array $filtro, string $campo): float
{
    $pipeline = [
        ['$match' => $filtro],
        ['$group' => ['_id' => null, 'total' => ['$sum' => '$' . $campo]]],
    ];
    $row = col($coleccion)->aggregate($pipeline, mongo_opts([]))->toArray()[0] ?? null;
    return $row ? (float)$row['total'] : 0.0;
}

/** Promedio de un campo numérico. */
function col_promedio(string $coleccion, array $filtro, string $campo): float
{
    $pipeline = [
        ['$match' => $filtro],
        ['$group' => ['_id' => null, 'promedio' => ['$avg' => '$' . $campo]]],
    ];
    $row = col($coleccion)->aggregate($pipeline, mongo_opts([]))->toArray()[0] ?? null;
    return $row && $row['promedio'] !== null ? (float)$row['promedio'] : 0.0;
}

/** Agrega un documento y devuelve su id hexadecimal. */
function col_agregar(string $coleccion, array $doc): string
{
    if (!isset($doc['_id'])) {
        $doc['_id'] = new ObjectId();
    }
    col($coleccion)->insertOne($doc, mongo_opts([]));
    return (string)$doc['_id'];
}

/** Agrega varios documentos en una sola operación. */
function col_agregar_varios(string $coleccion, array $docs): int
{
    if (empty($docs)) {
        return 0;
    }
    foreach ($docs as $i => $doc) {
        if (!isset($doc['_id'])) {
            $docs[$i]['_id'] = new ObjectId();
        }
    }
    $r = col($coleccion)->insertMany($docs, mongo_opts([]));
    return $r->getInsertedCount();
}

/** Normaliza un mapa de campos a operadores de actualización. */
function fv_operadores(array $cambios): array
{
    if ($cambios === []) {
        return [];
    }
    $claves = array_keys($cambios);
    $esOperador = count($claves) > 0 && $claves[0][0] === '$' && count(array_unique(array_map(static fn($k) => $k[0], $claves))) === count($claves);
    return $esOperador ? $cambios : ['$set' => $cambios];
}

/** Actualiza un documento por id. Devuelve true si se modificó. */
function col_actualizar(string $coleccion, $id, array $cambios, array $opciones = []): bool
{
    $o = oid($id);
    if ($o === null) {
        return false;
    }
    $upd = fv_operadores($cambios);
    if ($upd === []) {
        return false;
    }
    if (isset($opciones['upsert'])) {
        $upd['$setOnInsert'] = ['_id' => $o];
    }
    $r = col($coleccion)->updateOne(['_id' => $o], $upd, mongo_opts($opciones));
    return $r->getModifiedCount() > 0 || $r->getMatchedCount() > 0;
}

/** Actualiza el primer documento que coincida. Devuelve el n° modificados. */
function col_actualizar_uno(string $coleccion, array $filtro, array $cambios, array $opciones = []): int
{
    $upd = fv_operadores($cambios);
    if ($upd === []) {
        return 0;
    }
    $r = col($coleccion)->updateOne($filtro, $upd, mongo_opts($opciones));
    return (int)$r->getModifiedCount();
}

/** Actualiza varios documentos. Devuelve el n° modificados. */
function col_actualizar_varios(string $coleccion, array $filtro, array $cambios): int
{
    $upd = fv_operadores($cambios);
    if ($upd === []) {
        return 0;
    }
    $r = col($coleccion)->updateMany($filtro, $upd, mongo_opts([]));
    return (int)$r->getModifiedCount();
}

/** Inserta si no existe, o actualiza si existe (upsert por filtro). */
function col_upsert(string $coleccion, array $filtro, array $doc): string
{
    $id = oid($filtro['_id'] ?? null);
    if ($id !== null) {
        try {
            col($coleccion)->updateOne(
                ['_id' => $id],
                ['$set' => $doc, '$setOnInsert' => ['_id' => $id]],
                mongo_opts(['upsert' => true])
            );
            return (string)$id;
        } catch (BulkWriteException $e) {
            if (mongo_es_clave_duplicada($e)) {
                return (string)$id;
            }
            throw $e;
        }
    }
    return col_agregar($coleccion, $doc);
}

/** Elimina un documento por id. */
function col_borrar(string $coleccion, $id): bool
{
    $o = oid($id);
    if ($o === null) {
        return false;
    }
    return col($coleccion)->deleteOne(['_id' => $o], mongo_opts([]))->getDeletedCount() > 0;
}

/** Elimina varios documentos. Devuelve cuántos se borraron. */
function col_borrar_varios(string $coleccion, array $filtro): int
{
    return (int)col($coleccion)->deleteMany($filtro, mongo_opts([]))->getDeletedCount();
}

/** ¿El error corresponde a una clave duplicada (índice único)? */
function mongo_es_clave_duplicada(\Throwable $e): bool
{
    if ($e instanceof BulkWriteException && $e->getCode() === 11000) {
        return true;
    }
    if ($e instanceof DriverException && $e->getCode() === 11000) {
        return true;
    }
    return false;
}

/* ============================================================
   6) Contadores atómicos (folios y consecutivo de pagos)
   ============================================================ */

/** Devuelve el siguiente valor de una secuencia y lo incrementa. */
function proximo_secuencia(string $nombre, int $inicio = 1): int
{
    $doc = col('contadores')->findOneAndUpdate(
        ['_id' => $nombre],
        ['$inc' => ['valor' => 1], '$setOnInsert' => ['nombre' => $nombre]],
        mongo_opts(['upsert' => true, 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER])
    );
    if ($doc === null) {
        // Segunda pasada defensiva (carrera en el upsert).
        $doc = col('contadores')->findOneAndUpdate(
            ['_id' => $nombre],
            ['$inc' => ['valor' => 1]],
            mongo_opts(['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER])
        );
    }
    $valor = $doc !== null ? (int)($doc['valor'] ?? $inicio) : $inicio;
    return max($inicio, $valor);
}

/** Fija una secuencia al valor indicado (reinicio de folios). */
function fijar_secuencia(string $nombre, int $valor): void
{
    col('contadores')->updateOne(
        ['_id' => $nombre],
        ['$set' => ['valor' => $valor, 'nombre' => $nombre]],
        mongo_opts(['upsert' => true])
    );
}

/* ============================================================
   7) Utilidades de consulta
   ============================================================ */

/** Devuelve una expresión regular insensible a mayúsculas (sin / delimitadores). */
function rx(string $patron, bool $insensible = true): \MongoDB\BSON\Regex
{
    return new \MongoDB\BSON\Regex($patron, $insensible ? 'i' : '');
}

/** Traduce un id de usuario a ObjectId (o un filtro imposible si no aplica). */
function filtro_usuario($usuarioId): array
{
    $o = oid($usuarioId);
    return $o === null ? ['_id' => '__id_invalido__'] : ['_id' => $o];
}

/** Normaliza un valor a int para guardarlo (los Mongo "1"/0.bool vienen del admin). */
function fv_int($valor, int $defecto = 0): int
{
    if (is_bool($valor)) {
        return $valor ? 1 : 0;
    }
    if ($valor === null || $valor === '') {
        return $defecto;
    }
    return (int)$valor;
}

/** Normaliza un valor a float. */
function fv_float($valor, float $defecto = 0.0): float
{
    return ($valor === null || $valor === '') ? $defecto : (float)$valor;
}

/** Normaliza un valor a texto limpio (trim), o null si queda vacío. */
function fv_texto($valor, bool $vacioEsNull = true)
{
    $txt = trim((string)$valor);
    return ($txt === '' && $vacioEsNull) ? null : $txt;
}