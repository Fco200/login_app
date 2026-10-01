<?php
// Cargar el autoloader si ya tienes la carpeta vendor, o conexión directa
require_once __DIR__ . '/vendor/autoload.php';

$uri = "mongodb+srv://franciscoaguayo2005_db_user:fvdigitalhmo123@fvdigitalhmo.fvzluc5.mongodb.net/";
$client = new MongoDB\Client($uri);
$db = $client->FVDIGITALHMO;

// Lista con los nombres de tus 24 tablas de MySQL
$tablas = [
    'carrito', 'cartas', 'datos_sitio', 'entregables', 'facturas', 
    'juego_puntajes', 'mensajes_contacto', 'mensajes_portal', 'metodos_pago', 
    'notificaciones', 'pagos', 'productos', 'proyectos', 'proyectos_inicio', 
    'proyecto_historial', 'publicaciones', 'servicios', 'solicitudes', 
    'solicitud_historial', 'soporte', 'suscripciones', 'testimonios', 
    'usuarios', 'vehiculos'
];

echo "<h2>Creando colecciones en MongoDB Atlas...</h2>";

foreach ($tablas as $tabla) {
    try {
        // MongoDB crea la colección automáticamente al insertar una estructura vacía o usar createCollection
        $db->createCollection($tabla);
        echo "✅ Colección creada con éxito: <b>{$tabla}</b><br>";
    } catch (Exception $e) {
        // Si ya existe, solo avisa
        echo "⚠️ La colección <b>{$tabla}</b> ya existe o ya fue creada.<br>";
    }
}

echo "<br><h3>¡Listo! Todas tus tablas ya están como colecciones vacías en MongoDB Compass.</h3>";
?>