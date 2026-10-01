<?php
// Cargar el autoloader de Composer para MongoDB
require_once __DIR__ . '/vendor/autoload.php';

// 1. Conexión a MySQL local (XAMPP)
$mysqli = new mysqli("localhost", "root", "", "sistema_login");
if ($mysqli->connect_error) {
    die("Error de conexión a MySQL: " . $mysqli->connect_error);
}

// 2. Conexión a MongoDB Atlas (usando tu URI correcta)
$uri = "mongodb+srv://franciscoaguayo2005_db_user:fvdigitalhmo123@fvdigitalhmo.fvzluc5.mongodb.net/";
$client = new MongoDB\Client($uri);
$db = $client->FVDIGITALHMO;

// Lista de tablas que quieres migrar
$tablas = ['usuarios', 'cartas', 'datos_sitio', 'servicios', 'vehiculos', 'publicaciones', 'metodos_pago'];

foreach ($tablas as $tabla) {
    echo "Migrando tabla: $tabla...<br>";
    
    // Limpiar colección si ya existe para evitar duplicados
    $db->$tabla->drop();
    
    $resultado = $mysqli->query("SELECT * FROM $tabla");
    
    if ($resultado && $resultado->num_rows > 0) {
        $documentos = [];
        while ($fila = $resultado->fetch_assoc()) {
            // Convertir tipos de datos si es necesario (ej. enteros y flotantes)
            foreach ($fila as $key => $value) {
                if (is_numeric($value)) {
                    $fila[$key] = strpos($value, '.') !== false ? (float)$value : (int)$value;
                }
            }
            $documentos[] = $fila;
        }
        
        // Insertar en bloque en MongoDB
        if (!empty($documentos)) {
            $db->$tabla->insertMany($documentos);
            echo "¡Insertados " . count($documentos) . " registros en la colección '$tabla'!<br><br>";
        }
    } else {
        echo "La tabla '$tabla' está vacía o no tiene registros.<br><br>";
    }
}

echo "<strong>¡Migración completa con éxito!</strong>";
?>
```[cite: 4]

---

### Paso 2: Ejecutar el script
1. Asegúrate de tener encendido tu servidor local de **XAMPP** (Apache y MySQL)[cite: 4].
2. Asegúrate de haber instalado la librería de MongoDB en tu proyecto ejecutando en tu terminal[cite: 4]:
   ```bash
   composer require mongodb/mongodb
   ```[cite: 4]
3. Abre tu navegador web y entra a la ruta de tu script local (por ejemplo: `http://localhost/tu_proyecto/migrar.php`)[cite: 4].
4. El script leerá tus tablas de MySQL, creará las colecciones correspondientes en MongoDB Atlas y te mostrará un mensaje de éxito por cada tabla migrada[cite: 4].

---

### Paso 3: Verificar en MongoDB Compass
Vuelve a abrir tu aplicación de **MongoDB Compass**, dale al botón de refrescar y verás cómo aparecen automáticamente todas tus colecciones (`usuarios`, `servicios`, `vehiculos`, etc.) con sus respectivos documentos limpios y listos para usarse[cite: 4]. 

¿Tienes alguna duda con este script o quieres que ajustemos alguna tabla en particular?