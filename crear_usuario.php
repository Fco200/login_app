<?php
/* Utilidad de consola: crea (o restablece) un usuario administrador.
   Ejecuta desde el navegador: http://localhost/login_app/crear_usuario.php
   IMPORTANTE: borra esta página una vez creado el administrador. */
require_once __DIR__ . '/funciones.php';

$nombre        = 'Admin';
$email         = 'admin@correo.com';
$passwordPlana = 'pass123';

$existente = usr_por_email($email);
if ($existente !== null) {
    usr_actualizar($existente['id'], [
        'nombre'   => $nombre,
        'password' => $passwordPlana,
        'rol'      => 'admin',
        'activo'   => 1,
    ]);
    echo '<h3>¡Administrador restablecido con éxito!</h3>';
    echo '<p>Id del usuario: <b>' . e((string)$existente['id']) . '</b></p>';
} else {
    $res = usr_crear([
        'nombre'   => $nombre,
        'email'    => $email,
        'password' => $passwordPlana,
        'rol'      => 'admin',
        'activo'   => 1,
    ]);
    if (empty($res['ok'])) {
        echo '<h3>No se pudo crear el usuario</h3>';
        echo '<p>' . e((string)($res['mensaje'] ?? 'Error desconocido.')) . '</p>';
        exit;
    }
    echo '<h3>¡Usuario creado con éxito!</h3>';
    echo '<p>Id del usuario: <b>' . e((string)$res['id']) . '</b></p>';
}

echo '<p>Correo: <b>' . e($email) . '</b></p>';
echo '<p>Contraseña: <b>' . e($passwordPlana) . '</b></p>';
echo '<p><a href="' . e(url_sitio('admin/login.php')) . '">Ir al login del panel</a></p>';
echo '<p style="color:#c0392b"><b>Borra este archivo (crear_usuario.php) por seguridad.</b></p>';