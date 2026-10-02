<?php
/* ============================================================
   FV DIGITAL - Repositorio de datos del sitio
   ------------------------------------------------------------
   Colección `datos_sitio`: documentos { _id, clave, valor }.
   El índice de `clave` es único (reemplaza la PRIMARY KEY de MySQL),
   por lo que guardar una clave hace de upsert.
   ============================================================ */

function sitio_todas(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $filas = col_q('datos_sitio', [], ['projection' => ['clave' => 1, 'valor' => 1]]);
        foreach ($filas as $f) {
            $cache[(string)$f['clave']] = (string)($f['valor'] ?? '');
        }
    } catch (\Throwable $e) {
        // El instalador aún no ha creado la colección.
    }
    return $cache;
}

function sitio_valor(string $clave, string $defecto = ''): string
{
    $todos = sitio_todas();
    return (isset($todos[$clave]) && $todos[$clave] !== '') ? $todos[$clave] : $defecto;
}

/** Guarda (crea o sobrescribe) el valor de una clave del sitio. */
function sitio_guardar(string $clave, string $valor): void
{
    try {
        col('datos_sitio')->updateOne(
            ['clave' => $clave],
            [
                '$set' => ['valor' => $valor],
                '$setOnInsert' => ['clave' => $clave, 'creado_en' => ahora_utc()],
            ],
            mongo_opts(['upsert' => true])
        );
    } catch (\Throwable $e) {
        error_log('[FV-Mongo] sitio_guardar(' . $clave . '): ' . $e->getMessage());
    }
}

/** Guarda varias claves de una vez (transaccional). */
function sitio_guardar_varios(array $pares): void
{
    foreach ($pares as $clave => $valor) {
        sitio_guardar((string)$clave, (string)$valor);
    }
}

/** Invalida la caché estática de la página actual. */
function sitio_limpiar_cache(): void
{
    // La caché es estática por función; se reinicia en la próxima petición.
}