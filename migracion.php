<?php
// Cargar el autoloader de Composer para MongoDB
require_once __DIR__ . '/vendor/autoload.php';

// 1. Conexión a MySQL local (XAMPP)
$mysqli = new mysqli("localhost", "root", "", "sistema_login");
if ($mysqli->connect_error) {
    die("Error de conexión a MySQL: " . $mysqli->connect_error);
}

// 2. Conexión a MongoDB Atlas (tu URI con la contraseña limpia)
$uri = "mongodb+srv://franciscoaguayo2005_db_user:fvdigitalhmo123@fvdigitalhmo.fvzluc5.mongodb.net/";
$client = new MongoDB\Client($uri);
$db = $client->FVDIGITALHMO;

// Lista de tablas principales de tu sistema para migrar
$tablas = ['usuarios', 'cartas', 'datos_sitio', 'servicios', 'vehiculos', 'publicaciones', 'metodos_pago', 'solicitudes'];

foreach ($tablas as $tabla) {
    echo "Migrando tabla: <b>$tabla</b>...<br>";
    
    // Limpiar colección si ya existe para evitar duplicados
    $db->$tabla->drop();
    
    $resultado = $mysqli->query("SELECT * FROM $tabla");
    
    if ($resultado && $resultado->num_rows > 0) {
        $documentos = [];
        while ($fila = $resultado->fetch_assoc()) {
            // Convertir valores numéricos para que MongoDB los guarde como números y no como texto
            foreach ($fila as $key => $value) {
                if (is_numeric($value)) {
                    $fila[$key] = strpos($value, '.') !== false ? (float)$value : (int)$value;
                }
            }
            $documentos[] = $fila;
        }
        
        // Insertar documentos en bloque en la colección de MongoDB
        if (!empty($documentos)) {
            $db->$tabla->insertMany($documentos);
            echo "¡Insertados " . count($documentos) . " registros en la colección '$tabla'!<br><br>";
        }
    } else {
        echo "La tabla '$tabla' está vacía o no tiene registros.<br><br>";
    }
}

echo "<strong>¡Migración completa con éxito! Ya puedes revisar tus colecciones en MongoDB Compass.</strong>";
?>