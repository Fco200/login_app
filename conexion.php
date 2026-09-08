<?php //inicio de archivo php para conexion a la base de datos
$host = '127.0.0.1'; //host de la base de datos, en este caso localhost
$port = '3307'; //puerto de la base de datos, en este caso 3307
$db   = 'sistema_login'; //nombre de la base de datos, en este caso sistema_login
$user = 'root'; //usuario de la base de datos, en este caso root
$pass = ''; // Por defecto en XAMPP viene vacía
$charset = 'utf8mb4'; //conjunto de caracteres

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset"; //cadena de conexión
$options = [ //opciones de conexión
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]; //opciones de conexión

try { //intento de conexión a la base de datos
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error al conectar con la base de datos: " . $e->getMessage());//mensaje de error en caso de que falle la conexión
}