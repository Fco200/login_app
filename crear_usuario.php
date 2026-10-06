<?php
require_once 'conexion.php';

$nombre = 'Admin';
$email = 'admin@correo.com';
$passwordPlana = 'pass123';

// PHP genera el hash nativo compatible con tu versión exacta
$passwordHash = password_hash($passwordPlana, PASSWORD_BCRYPT);

try {
    // Borrar si ya existe para evitar error por clave duplicada
    $pdo->prepare("DELETE FROM usuarios WHERE email = ?")->execute([$email]);

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$nombre, $email, $passwordHash]);

    echo "<h3>¡Usuario creado con éxito!</h3>";
    echo "<p>Correo: <b>$email</b></p>";
    echo "<p>Contraseña: <b>$passwordPlana</b></p>";
    echo "<a href='index.php'>Ir al Login</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}