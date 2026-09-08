<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($nombre) || empty($email) || empty($password) || empty($confirm)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipoMensaje = 'error';
    } elseif ($password !== $confirm) {
        $mensaje = 'Las contraseñas no coinciden.';
        $tipoMensaje = 'error';
    } elseif (strlen($password) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
        $tipoMensaje = 'error';
    } else {
        // Verificar si el correo ya existe
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $mensaje = 'El correo ya está registrado.';
            $tipoMensaje = 'error';
        } else {
            // Cifrar contraseña y guardar
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtInsert = $pdo->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
            
            if ($stmtInsert->execute([$nombre, $email, $hash])) {
                $mensaje = 'Registro exitoso. Ya puedes iniciar sesión.';
                $tipoMensaje = 'exito';
            } else {
                $mensaje = 'Ocurrió un error al guardar el usuario.';
                $tipoMensaje = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Cuenta</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f4f6f9; margin: 0; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 360px; }
        h2 { margin-top: 0; text-align: center; }
        .campo { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-size: 0.9rem; }
        input { width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.7rem; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #218838; }
        .error { color: #dc3545; background: #f8d7da; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .exito { color: #155724; background: #d4edda; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
        .links { text-align: center; margin-top: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Crear Cuenta</h2>
        <?php if ($mensaje): ?>
            <div class="<?= $tipoMensaje ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        <form action="registro.php" method="POST">
            <div class="campo">
                <label>Nombre:</label>
                <input type="text" name="nombre" required>
            </div>
            <div class="campo">
                <label>Correo:</label>
                <input type="email" name="email" required>
            </div>
            <div class="campo">
                <label>Contraseña:</label>
                <input type="password" name="password" required>
            </div>
            <div class="campo">
                <label>Confirmar Contraseña:</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit">Registrarse</button>
        </form>
        <div class="links">
            ¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a>
        </div>
    </div>
</body>
</html>