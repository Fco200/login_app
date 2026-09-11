<?php
/* ============================================================
   FV DIGITAL - Migración: tablas de pagos, carrito, proyectos
   Ejecutar una vez desde el navegador.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

$sql = [
"CREATE TABLE IF NOT EXISTS metodos_pago (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  detalles_cuenta VARCHAR(255),
  instrucciones TEXT,
  icono VARCHAR(50) DEFAULT 'bi-credit-card',
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS productos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(200) UNIQUE NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255),
  categoria VARCHAR(100),
  stock INT DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS carrito (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  servicio_id INT,
  producto_id INT,
  cantidad INT DEFAULT 1,
  precio_unitario DECIMAL(10,2) NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS pagos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  solicitud_id INT,
  metodo_pago_id INT,
  monto DECIMAL(10,2) NOT NULL,
  tipo_pago ENUM('anticipo','restante','completo','producto') DEFAULT 'anticipo',
  comprobante VARCHAR(255),
  estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  notas TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE SET NULL,
  FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS proyectos_inicio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id INT NOT NULL,
  usuario_id INT NOT NULL,
  descripcion_proyecto TEXT,
  requisitos TEXT,
  objetivos TEXT,
  alcance TEXT,
  cronograma TEXT,
  entregables TEXT,
  anticipo_minimo DECIMAL(10,2) DEFAULT 2500.00,
  anticipo_pagado DECIMAL(10,2) DEFAULT 0.00,
  estado ENUM('documentacion','anticipo_pendiente','en_desarrollo','completado') DEFAULT 'documentacion',
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($sql as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        $errores[] = $e->getMessage();
    }
}

/* Insertar métodos de pago por defecto si la tabla está vacía */
$existe = $pdo->query('SELECT COUNT(*) FROM metodos_pago')->fetchColumn();
if ((int)$existe === 0) {
    $metodos = [
        ['Transferencia bancaria', 'Realiza una transferencia directa a nuestra cuenta bancaria.', 'CLABE: 012345678901234567 | Cuenta: 1234567890 | Banco: Banamex', 'Realiza la transferencia con el monto indicado. Envía tu comprobante desde el portal.', 'bi-bank'],
        ['PayPal', 'Paga de forma rápida y segura a través de PayPal.', 'Correo: pagos@fvdigital.com', 'Envía el pago a nuestro correo de PayPal y adjunta el comprobante.', 'bi-paypal'],
        ['OXXO', 'Paga en efectivo en cualquier tienda OXXO.', 'Referencia: Se genera al momento de elegir este método', 'Acude a tu OXXO más cercano y paga con la referencia proporcionada.', 'bi-shop'],
        ['Efectivo', 'Pago en efectivo directo.', 'Coordina con el equipo para el punto de entrega.', 'Contacta a nuestro equipo para coordinar la entrega de efectivo.', 'bi-cash-stack'],
    ];
    $ins = $pdo->prepare('INSERT INTO metodos_pago (nombre, descripcion, detalles_cuenta, instrucciones, icono) VALUES (?,?,?,?,?)');
    foreach ($metodos as $m) {
        $ins->execute($m);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 560px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Pagos, Carrito y Proyectos</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted">
            <li>Tablas creadas: metodos_pago, productos, carrito, pagos, proyectos_inicio.</li>
            <li>Métodos de pago por defecto insertados.</li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/index.php" class="btn btn-primary">Ir al panel</a>
        <a href="portal/index.php" class="btn btn-outline-primary">Portal de clientes</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>
