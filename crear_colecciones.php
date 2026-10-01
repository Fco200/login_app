<?php
// Script ultrarrápido para crear las 24 colecciones en MongoDB Atlas sin dependencias de Composer

$uri = "mongodb+srv://franciscoaguayo2005_db_user:fvdigitalhmo123@fvdigitalhmo.fvzluc5.mongodb.net/";

// Verificamos si la extensión de MongoDB está activa en PHP
if (!class_exists('MongoDB\Client')) {
    // Si no está la librería oficial, usaremos un método alternativo o la conexión nativa si existe, 
    // pero si tienes Compass abierto, la forma más rápida sin lidiar con PHP es la siguiente:
    die("<h3>⚠️ Atención:</h3> Tu PHP local no tiene activa la extensión de MongoDB para ejecutar este script directamente. <br><br>Pero no te preocupes: <b>como ya tienes abierto tu MongoDB Compass</b>, puedes crear las colecciones con un clic o usar la interfaz de Compass de manera rapidísima.");
}

try {
    $client = new MongoDB\Client($uri);
    $db = $client->FVDIGITALHMO;

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
        $db->createCollection($tabla);
        echo "✅ Colección creada: <b>{$tabla}</b><br>";
    }

    echo "<br><h3>¡Listo! Todas tus tablas ya están creadas en MongoDB.</h3>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>