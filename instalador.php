<?php
/* ============================================================
   FV DIGITAL - Instalador del sistema
   Crea las tablas y datos iniciales. Ejecutar UNA vez desde el
   navegador y después ELIMINAR este archivo por seguridad.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];

$sql = [
"CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  rol ENUM('admin','vendedor','cliente') DEFAULT 'cliente',
  activo TINYINT(1) DEFAULT 1,
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  descripcion_corta VARCHAR(255) NOT NULL,
  descripcion TEXT,
  icono VARCHAR(60) DEFAULT 'bi-code-slash',
  categoria VARCHAR(40) DEFAULT 'desarrollo-web',
  precio_desde DECIMAL(10,2) DEFAULT NULL,
  destaque TINYINT(1) DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS proyectos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  descripcion TEXT,
  categoria VARCHAR(80) DEFAULT 'General',
  cliente VARCHAR(120) DEFAULT NULL,
  anio SMALLINT DEFAULT NULL,
  url VARCHAR(255) DEFAULT NULL,
  imagen VARCHAR(255) DEFAULT NULL,
  archivo VARCHAR(255) DEFAULT NULL,
  archivo_nombre VARCHAR(255) DEFAULT NULL,
  destaque TINYINT(1) DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS publicaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  resumen VARCHAR(255) DEFAULT NULL,
  contenido MEDIUMTEXT,
  categoria VARCHAR(80) DEFAULT 'Noticias',
  imagen VARCHAR(255) DEFAULT NULL,
  autor VARCHAR(100) DEFAULT NULL,
  destaque TINYINT(1) DEFAULT 0,
  activo TINYINT(1) DEFAULT 1,
  visitas INT DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS testimonios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  cargo VARCHAR(120) DEFAULT NULL,
  mensaje TEXT NOT NULL,
  valoracion TINYINT DEFAULT 5,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS cartas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  destinatario VARCHAR(180) DEFAULT NULL,
  contenido MEDIUMTEXT,
  firmado_por VARCHAR(150) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS solicitudes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  empresa VARCHAR(150) DEFAULT NULL,
  cargo VARCHAR(100) DEFAULT NULL,
  usuario_id INT(11) DEFAULT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  tipo_servicio VARCHAR(150) DEFAULT NULL,
  presupuesto VARCHAR(80) DEFAULT NULL,
  mensaje TEXT,
  estado ENUM('nueva','en_proceso','completada','rechazada') DEFAULT 'nueva',
  tipo_solicitud VARCHAR(20) NOT NULL DEFAULT 'individual',
  empleados VARCHAR(60) DEFAULT NULL,
  rango VARCHAR(90) DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS soporte (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT(11) DEFAULT NULL,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  categoria VARCHAR(40) NOT NULL DEFAULT 'problema',
  pagina VARCHAR(255) DEFAULT NULL,
  descripcion TEXT NOT NULL,
  estado ENUM('nuevo','atendido','cerrado') DEFAULT 'nuevo',
  respuesta TEXT DEFAULT NULL,
  atendido_en TIMESTAMP NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS mensajes_contacto (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(30) DEFAULT NULL,
  asunto VARCHAR(180) DEFAULT NULL,
  mensaje TEXT NOT NULL,
  leido TINYINT(1) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS suscripciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS datos_sitio (
  clave VARCHAR(60) PRIMARY KEY,
  valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS mensajes_portal (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  remitente ENUM('cliente','negocio') NOT NULL DEFAULT 'cliente',
  mensaje TEXT NOT NULL,
  leido TINYINT(1) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS notificaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo VARCHAR(30) DEFAULT 'info',
  titulo VARCHAR(180) NOT NULL,
  mensaje TEXT,
  enlace VARCHAR(255) DEFAULT NULL,
  leida TINYINT(1) DEFAULT 0,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS solicitud_historial (
  id INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id INT NOT NULL,
  estado VARCHAR(30) NOT NULL,
  nota VARCHAR(255) DEFAULT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY solicitud_id (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS juego_puntajes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  juego VARCHAR(40) NOT NULL,
  puntaje INT NOT NULL,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($sql as $q) {
    try {
        $pdo->exec($q);
    } catch (PDOException $e) {
        $errores[] = $e->getMessage();
    }
}

/* ---------- Datos del sitio (clave => valor) ---------- */
$datos = [
    'telefono'      => '662 537 4491',
    'whatsapp'      => '6625374491',
    'email'         => 'contacto@fvdigital.com',
    'direccion'     => 'Hermosillo, Sonora, México',
    'horario'       => 'Lun a Vie 9:00 - 18:00',
    'facebook'      => '',
    'instagram'     => '',
    'tiktok'        => '',
    'hero_titulo'   => 'Impulsamos tu negocio con soluciones digitales a la medida',
    'hero_subtitulo'=> 'Desarrollo web, diseño y branding, apps, plantillas y soporte técnico para que tu marca destaque.',
    'hero_cta'      => 'Cotiza tu proyecto',
    'acerca'        => 'FV Digital es una marca dedicada a la creación de soluciones digitales y desarrollo de software. Ayudamos a negocios, emprendedores y marcas a crecer con tecnología, diseño y estrategia.',
];
$insert = $pdo->prepare('INSERT INTO datos_sitio (clave, valor) VALUES (:clave, :valor)
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
foreach ($datos as $clave => $valor) {
    $insert->execute(['clave' => $clave, 'valor' => $valor]);
}

/* ---------- Servicios iniciales ---------- */
$servicios = [
    ['Desarrollo Web', 'Sitios web, tiendas en línea y landing pages a la medida de tu negocio.',
     'Creamos páginas web rápidas, modernas y adaptadas a cualquier dispositivo para que vendas más en internet.',
     'bi-code-slash', 'desarrollo-web', 3500],
    ['Diseño y Branding', 'Logotipos, identidad visual y papelería profesional para tu marca.',
     'Diseñamos la imagen de tu marca: logotipo, colores, tipografía y todos los materiales que necesitas.',
     'bi-palette', 'diseno-branding', 1500],
    ['Apps y Plantillas', 'Aplicaciones móviles y plantillas digitales listas para tu proyecto.',
     'Desarrollamos aplicaciones y ofrecemos plantillas y componentes reutilizables para ahorrarte tiempo.',
     'bi-phone', 'apps-plantillas', 4000],
    ['Soporte Técnico', 'Mantenimiento, instalación y asesoría para que tu tecnología funcione.',
     'Damos soporte y mantenimiento a equipos, sitios y sistemas para que no pares de trabajar.',
     'bi-headset', 'soporte', 500],
];
$insertServ = $pdo->prepare('INSERT IGNORE INTO servicios (titulo, slug, descripcion_corta, descripcion, icono, categoria, precio_desde)
                             VALUES (:titulo, :slug, :corta, :desc, :icono, :cat, :precio)');
foreach ($servicios as $s) {
    $slug = strtolower(str_replace([' ', '-'], '-', trim($s[0]))); // 'Desarrollo Web' -> 'desarrollo web'
    $slug = preg_replace('/[^a-z0-9\-]/', '', str_replace(' ', '-', strtolower($s[0])));
    $insertServ->execute(['titulo' => $s[0], 'slug' => $slug, 'corta' => $s[1], 'desc' => $s[2], 'icono' => $s[3], 'cat' => $s[4], 'precio' => $s[5]]);
}

/* ---------- Testimonio inicial ---------- */
$pdo->exec("INSERT INTO testimonios (nombre, cargo, mensaje, valoracion, activo)
            SELECT 'Cliente FV Digital', 'Emprendedor', 'FV Digital me entregó una página web increíble y con mucho profesionalismo. Mi negocio creció mucho desde entonces.', 5, 1
            WHERE NOT EXISTS (SELECT 1 FROM testimonios)");

/* ---------- Carta de presentación inicial ---------- */
$contenidoCarta = "Estimado cliente:

Por medio de la presente, me permito presentar a FV DIGITAL, Soluciones Digitales y Desarrollo, una marca dedicada al desarrollo web, diseño y branding, aplicaciones y plantillas, así como al soporte técnico.

En FV DIGITAL creemos que cada negocio merece una presencia digital profesional. Por eso ofrecemos soluciones a la medida, tiempos de entrega ágiles y un trato cercano y personalizado en cada proyecto.

Quedo a sus órdenes para resolver cualquier duda y con gusto le presentaré una propuesta acorde a sus necesidades.

Sin otro particular, le envío un cordial saludo.

Atentamente,
FV Digital — Soluciones Digitales y Desarrollo";
$insertCarta = $pdo->prepare('INSERT IGNORE INTO cartas (titulo, slug, destinatario, contenido, firmado_por)
                              VALUES (:titulo, :slug, :dest, :cont, :firma)');
$insertCarta->execute([
    'titulo' => 'Carta de presentación FV Digital',
    'slug' => 'carta-de-presentacion-fv-digital',
    'dest' => 'Estimado cliente',
    'cont' => $contenidoCarta,
    'firma' => 'FV Digital — Soluciones Digitales y Desarrollo',
]);

/* ---------- Publicación inicial ---------- */
$insertPub = $pdo->prepare('INSERT IGNORE INTO publicaciones (titulo, slug, resumen, contenido, categoria, autor)
                            VALUES (:titulo, :slug, :resumen, :contenido, :cat, :autor)');
$insertPub->execute([
    'titulo' => 'Bienvenido a FV Digital',
    'slug' => 'bienvenido-a-fv-digital',
    'resumen' => 'Conoce FV Digital, tu aliado en soluciones digitales y desarrollo.',
    'contenido' => "FV Digital es una marca joven, creativa y con mucha energía.\n\nNos especializamos en desarrollo web, diseño y branding, aplicaciones y plantillas, y soporte técnico. Trabajamos de la mano contigo para convertir tus ideas en realidad.\n\n¿Tienes un proyecto en mente? Escríbenos y lo hacemos posible.",
    'cat' => 'Noticias',
    'autor' => 'FV Digital',
]);

/* ---------- Usuario administrador ---------- */
try {
    $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existe->execute(['admin@correo.com']);
    if (!$existe->fetch()) {
        $hash = password_hash('pass123', PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)')
            ->execute(['Administrador', 'admin@correo.com', $hash, 'admin']);
    } else {
        $pdo->prepare('UPDATE usuarios SET rol = ? WHERE email = ?')
            ->execute(['admin', 'admin@correo.com']);
    }
} catch (PDOException $e) {
    $errores[] = 'Usuarios: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; }
        .card { max-width: 560px; margin: auto; border-radius: 14px; }
    </style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Instalación FV Digital</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">El sistema se instaló correctamente.</div>
        <ul class="small text-muted">
            <li>Tablas creadas: servicios, proyectos, publicaciones, testimonios, cartas, solicitudes, mensajes, suscripciones, datos del sitio.</li>
            <li>Datos iniciales cargados (servicios, carta, publicación, testimonio).</li>
            <li>Usuario administrador: <b>admin@correo.com</b> / <b>pass123</b></li>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-primary">Ver página pública</a>
        <a href="admin/login.php" class="btn btn-outline-primary">Ir al panel</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo (<code>instalador.php</code>) del servidor.</p>
</div>
</body>
</html>