<?php
/* ============================================================
   FV DIGITAL - Conexión centralizada a MongoDB Atlas
   ------------------------------------------------------------
   Este es el ÚNICO punto donde se crea el cliente de MongoDB.
   Todas las páginas y repositorios consumen la misma instancia
   a través de:  db() · col() · mongo_client()

   La configuración se resuelve en este orden:
     1. Variables de entorno  MONGODB_URI / MONGODB_DB
     2. config.php             MONGODB_URI / MONGODB_DB
     3. Configuración local ignorada por git  config/mongodb.local.php
   ============================================================ */

if (!defined('FV_MONGO')) {
    define('FV_MONGO', 1);

    /* Configuración local opcional (fuera del control de versiones). */
    if (is_file(__DIR__ . '/config/mongodb.local.php')) {
        require_once __DIR__ . '/config/mongodb.local.php';
    }

    /* ---- Autoloader de Composer (mongodb/mongodb) ---- */
    if (!is_file(__DIR__ . '/vendor/autoload.php')) {
        $faltaDependencias = true;
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
        $faltaDependencias = !class_exists(\MongoDB\Client::class);
    }

    if (!empty($faltaDependencias)) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8', true, 500);
        }
        die(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<title>Falta la librería de MongoDB</title></head>'
            . '<body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:40px">'
            . '<h1 style="color:#f87171">Falta la librería mongodb/mongodb</h1>'
            . '<p>No se encontró <code>vendor/autoload.php</code>. Ejecuta:</p>'
            . '<pre style="background:#1e293b;padding:16px;border-radius:8px;overflow:auto">composer install</pre>'
            . '<p>Además verifica que la extensión de PHP esté instalada y activa: '
            . '<code>extension=mongodb</code> en <code>php.ini</code> '
            . '(la versión 2.x de la librería requiere PHP 8.1+).</p>'
            . '</body></html>'
        );
    }

    if (!extension_loaded('mongodb')) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8', true, 500);
        }
        die(
            '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<title>Falta la extensión mongodb</title></head>'
            . '<body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:40px">'
            . '<h1 style="color:#f87171">La extensión PHP "mongodb" no está activa</h1>'
            . '<p>Agrega en tu <code>php.ini</code>:</p>'
            . '<pre style="background:#1e293b;padding:16px;border-radius:8px">extension=mongodb</pre>'
            . '<p>Después reinicia Apache y vuelve a cargar la página.</p>'
            . '</body></html>'
        );
    }
}

/* ---------- Configuración ---------- */

if (!function_exists('mongo_uri')) {
    /** URI de conexión. Prioridad: variable de entorno > config.php. */
    function mongo_uri(): string
    {
        $uri = getenv('MONGODB_URI');
        if (!is_string($uri) || $uri === '') {
            $uri = defined('MONGODB_URI') ? (string)MONGODB_URI : '';
        }
        return $uri;
    }
}

if (!function_exists('mongo_nombre_db')) {
    /** Nombre de la base de datos (FVDIGITALHMO). */
    function mongo_nombre_db(): string
    {
        $db = getenv('MONGODB_DB');
        if (!is_string($db) || $db === '') {
            $db = defined('MONGODB_DB') ? (string)MONGODB_DB : 'FVDIGITALHMO';
        }
        return $db;
    }
}

/* ---------- Instancias únicas (patrón Singleton) ---------- */

if (!function_exists('mongo_client')) {
    /** Cliente MongoDB compartido por toda la petición. */
    function mongo_client(): \MongoDB\Client
    {
        static $client = null;
        if ($client instanceof \MongoDB\Client) {
            return $client;
        }

        $uri = mongo_uri();
        if ($uri === '') {
            mongo_error_fatal('No hay URI de MongoDB configurada. Define MONGODB_URI (entorno) o MONGODB_URI en config.php.');
        }

        try {
            $client = new \MongoDB\Client($uri, [], [
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ]);
        } catch (\Throwable $e) {
            mongo_error_fatal('No se pudo conectar con MongoDB Atlas: ' . $e->getMessage(), $e);
        }

        return $client;
    }
}

if (!function_exists('db')) {
    /** Base de datos activa. */
    function db(): \MongoDB\Database
    {
        static $db = null;
        if ($db instanceof \MongoDB\Database) {
            return $db;
        }
        $db = mongo_client()->selectDatabase(mongo_nombre_db());
        return $db;
    }
}

if (!function_exists('col')) {
    /** Colección por nombre. */
    function col(string $coleccion): \MongoDB\Collection
    {
        static $cache = [];
        if (isset($cache[$coleccion])) {
            return $cache[$coleccion];
        }
        $cache[$coleccion] = db()->selectCollection($coleccion);
        return $cache[$coleccion];
    }
}

if (!function_exists('mongo_error_fatal')) {
    /**
     * Detiene la ejecución con un mensaje comprensible (HTML o JSON si
     * la petición es AJAX) en lugar de exponer un stack trace de BSON.
     */
    function mongo_error_fatal(string $mensaje, ?\Throwable $e = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, 500);
        }
        error_log('[FV-Mongo] ' . $mensaje . ($e ? ' :: ' . $e->getMessage() : ''));
        echo json_encode(
            ['ok' => false, 'mensaje' => $mensaje, 'tipo' => 'danger'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

/* ---------- Compatibilidad con el código heredado ---------- */
/*
 * Antes cada página relied en $pdo / $db. Se mantienen $client y $db
 * disponibles por si algún include los usa, pero la ruta oficial es
 * db() y col().
 */
if (!isset($GLOBALS['client'])) {
    try {
        $GLOBALS['client'] = mongo_client();
        $GLOBALS['db'] = db();
    } catch (\Throwable $e) {
        $GLOBALS['client'] = null;
        $GLOBALS['db'] = null;
    }
}