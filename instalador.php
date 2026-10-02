<?php
/* ============================================================
   FV DIGITAL - Instalador del sistema (MongoDB)
   ------------------------------------------------------------
   En MongoDB no hay CREATE TABLE: las colecciones se crean al
   escribir el primer documento. Este instalador deja la base lista:
     - índices de todas las colecciones del sistema,
     - datos iniciales del sitio, servicios, carta, publicación y
       testimonio,
     - usuario administrador por defecto.
   Es idempotente y reejecutable. Ejecutar UNA vez desde el navegador
   y después ELIMINAR este archivo por seguridad.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];
$pasos   = [];

/* ---------- 0) Requisitos del entorno ---------- */
/*
 * conexion.php ya aborta la petición con un mensaje claro si falta
 * vendor/autoload.php, si la extensión "mongodb" no está activa o si
 * no se puede abrir la conexión contra Atlas. Llegar hasta aquí implica
 * que el entorno está listo.
 */

function ins_indice(string $coleccion, array $claves, string $nombre, array $opciones = []): void
{
    global $errores, $pasos;
    try {
        col($coleccion)->createIndex($claves, ($opciones ?? []) + ['name' => $nombre]);
        $pasos[] = $coleccion . '/' . $nombre;
    } catch (\Throwable $e) {
        $errores[] = $coleccion . '/' . $nombre . ': ' . $e->getMessage();
    }
}

/** Ejecuta una semilla y registra el paso o el error. */
function ins_paso(callable $fn, string $etiqueta): void
{
    global $errores, $pasos;
    try {
        if ($fn() !== false) {
            $pasos[] = $etiqueta;
        }
    } catch (\Throwable $e) {
        $errores[] = $etiqueta . ': ' . $e->getMessage();
    }
}

if (empty($errores)) {

    /* ============================================================
       1) Índices del sistema
       ============================================================ */

    /* Identidad y unicidad */
    ins_indice('usuarios',         ['email_normalizado' => 1], 'ux_usuarios_email', ['unique' => true]);
    ins_indice('usuarios',         ['rol' => 1, 'activo' => 1], 'ix_usuarios_rol_activo');

    /* Catálogo público */
    ins_indice('servicios',        ['slug' => 1], 'ux_servicios_slug', ['unique' => true]);
    ins_indice('servicios',        ['activo' => 1, 'destaque' => -1], 'ix_servicios_activo');
    ins_indice('proyectos',        ['slug' => 1], 'ux_proyectos_slug', ['unique' => true]);
    ins_indice('proyectos',        ['activo' => 1, 'destaque' => -1, 'anio' => -1], 'ix_proyectos_activo');
    ins_indice('publicaciones',    ['slug' => 1], 'ux_publicaciones_slug', ['unique' => true]);
    ins_indice('publicaciones',    ['activo' => 1, 'creado_en' => -1], 'ix_publicaciones_activo');
    ins_indice('cartas',           ['slug' => 1], 'ux_cartas_slug', ['unique' => true]);
    ins_indice('cartas',           ['activo' => 1], 'ix_cartas_activo');
    ins_indice('testimonios',      ['activo' => 1], 'ix_testimonios_activo');
    ins_indice('productos',        ['slug' => 1], 'ux_productos_slug', ['unique' => true]);
    ins_indice('productos',        ['activo' => 1, 'categoria' => 1], 'ix_productos_activo');

    /* Solicitudes y proyectos de clientes */
    ins_indice('solicitudes',      ['estado' => 1, 'creado_en' => -1], 'ix_sol_estado_fecha');
    ins_indice('solicitudes',      ['email' => 1, 'creado_en' => -1], 'ix_sol_email_fecha');
    ins_indice('solicitudes',      ['usuario_id' => 1, 'creado_en' => -1], 'ix_sol_usuario_fecha');
    ins_indice('solicitudes',      ['tipo_solicitud' => 1], 'ix_sol_tipo');
    ins_indice('solicitud_historial', ['solicitud_id' => 1, 'creado_en' => -1], 'ix_solhist_solicitud');
    ins_indice('proyectos_inicio', ['usuario_id' => 1, 'estado' => 1], 'ix_proy_usuario_estado');
    ins_indice('proyectos_inicio', ['solicitud_id' => 1], 'ix_proy_solicitud');
    ins_indice('proyecto_historial', ['proyecto_id' => 1, 'creado_en' => -1], 'ix_hist_proyecto');

    /* Pagos, carrito y facturación */
    ins_indice('metodos_pago',     ['activo' => 1], 'ix_metodos_activo');
    ins_indice('carrito',          ['usuario_id' => 1], 'ix_carrito_usuario');
    ins_indice('pagos',            ['clave_unica' => 1], 'ux_pagos_clave', [
        'unique'                  => true,
        'partialFilterExpression' => ['clave_unica' => ['$type' => 'string', '$ne' => '']],
    ]);
    ins_indice('pagos',            ['usuario_id' => 1, 'creado_en' => -1], 'ix_pagos_usuario');
    ins_indice('pagos',            ['proyecto_id' => 1, 'estado' => 1], 'ix_pagos_proyecto');
    ins_indice('pagos',            ['estado' => 1, 'creado_en' => -1], 'ix_pagos_estado');
    ins_indice('facturas',         ['folio' => 1], 'ux_facturas_folio', ['unique' => true]);
    ins_indice('facturas',         ['proyecto_id' => 1, 'creado_en' => -1], 'ix_fact_proyecto');
    ins_indice('facturas',         ['usuario_id' => 1, 'creado_en' => -1], 'ix_fact_usuario');

    /* Comunicación y soporte */
    ins_indice('mensajes_contacto', ['leido' => 1, 'creado_en' => -1], 'ix_mens_contacto');
    ins_indice('suscripciones',     ['email_normalizado' => 1], 'ux_suscripciones_email', ['unique' => true]);
    ins_indice('suscripciones',     ['activo' => 1], 'ix_suscripciones_activo');
    ins_indice('soporte',           ['estado' => 1, 'creado_en' => -1], 'ix_soporte_estado');
    ins_indice('mensajes_portal',   ['usuario_id' => 1, 'creado_en' => -1], 'ix_mensajes_usuario');
    ins_indice('mensajes_portal',   ['usuario_id' => 1, 'remitente' => 1, 'leido' => 1], 'ix_mensajes_pendientes');
    ins_indice('notificaciones',    ['usuario_id' => 1, 'creado_en' => -1], 'ix_notificaciones_usuario');
    ins_indice('juego_puntajes',    ['juego' => 1, 'puntaje' => -1], 'ix_juego_tabla');

    /* Concesionaria */
    ins_indice('vehiculos', ['activo' => 1, 'creado_en' => -1], 'ix_veh_activo');

    /* ============================================================
       2) Datos iniciales del sitio
       ============================================================ */
    ins_paso(static function () {
        $datos = [
            'telefono'       => '662 537 4491',
            'whatsapp'       => '6625374491',
            'email'          => 'contacto@fvdigital.com',
            'direccion'      => 'Hermosillo, Sonora, México',
            'horario'        => 'Lun a Vie 9:00 - 18:00',
            'facebook'       => '',
            'instagram'      => '',
            'tiktok'         => '',
            'hero_titulo'    => 'Impulsamos tu negocio con soluciones digitales a la medida',
            'hero_subtitulo' => 'Desarrollo web, diseño y branding, apps, plantillas y soporte técnico para que tu marca destaque.',
            'hero_cta'       => 'Cotiza tu proyecto',
            'acerca'         => 'FV Digital es una marca dedicada a la creación de soluciones digitales y desarrollo de software. Ayudamos a negocios, emprendedores y marcas a crecer con tecnología, diseño y estrategia.',
        ];
        sitio_guardar_varios($datos);
        return true;
    }, 'datos_sitio cargados');

    /* ============================================================
       3) Servicios iniciales (idempotente por slug)
       ============================================================ */
    ins_paso(static function () {
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
        $nuevos = 0;
        foreach ($servicios as $s) {
            $slug = slugify($s[0]);
            if (srv_por_slug($slug) !== null) {
                continue;
            }
            $r = srv_guardar(null, [
                'titulo'            => $s[0],
                'slug'              => $slug,
                'descripcion_corta' => $s[1],
                'descripcion'       => $s[2],
                'icono'             => $s[3],
                'categoria'         => $s[4],
                'precio_desde'      => $s[5],
                'activo'            => 1,
            ]);
            if ($r['ok']) {
                $nuevos++;
            }
        }
        return $nuevos;
    }, 'servicios iniciales cargados');

    /* ============================================================
       4) Testimonio inicial (sólo si no hay ninguno)
       ============================================================ */
    ins_paso(static function () {
        if (tes_todos() !== []) {
            return false;
        }
        $r = tes_guardar(null, [
            'nombre'     => 'Cliente FV Digital',
            'cargo'      => 'Emprendedor',
            'mensaje'    => 'FV Digital me entregó una página web increíble y con mucho profesionalismo. Mi negocio creció mucho desde entonces.',
            'valoracion' => 5,
            'activo'     => 1,
        ]);
        return $r['ok'];
    }, 'testimonio inicial cargado');

    /* ============================================================
       5) Carta de presentación inicial
       ============================================================ */
    ins_paso(static function () {
        $contenidoCarta = "Estimado cliente:\n\n"
            . "Por medio de la presente, me permito presentar a FV DIGITAL, Soluciones Digitales y Desarrollo, una marca dedicada al desarrollo web, diseño y branding, aplicaciones y plantillas, así como al soporte técnico.\n\n"
            . "En FV DIGITAL creemos que cada negocio merece una presencia digital profesional. Por eso ofrecemos soluciones a la medida, tiempos de entrega ágiles y un trato cercano y personalizado en cada proyecto.\n\n"
            . "Quedo a sus órdenes para resolver cualquier duda y con gusto le presentaré una propuesta acorde a sus necesidades.\n\n"
            . "Sin otro particular, le envío un cordial saludo.\n\n"
            . "Atentamente,\n"
            . "FV Digital — Soluciones Digitales y Desarrollo";

        $slug = slugify('Carta de presentación FV Digital');
        if (car_por_slug($slug) !== null) {
            return false;
        }
        $r = car_guardar(null, [
            'titulo'       => 'Carta de presentación FV Digital',
            'slug'         => $slug,
            'destinatario' => 'Estimado cliente',
            'contenido'    => $contenidoCarta,
            'firmado_por'  => 'FV Digital — Soluciones Digitales y Desarrollo',
            'activo'       => 1,
        ]);
        return $r['ok'];
    }, 'carta de presentación cargada');

    /* ============================================================
       6) Publicación inicial
       ============================================================ */
    ins_paso(static function () {
        $slug = slugify('Bienvenido a FV Digital');
        if (pub_por_slug($slug) !== null) {
            return false;
        }
        $r = pub_guardar(null, [
            'titulo'    => 'Bienvenido a FV Digital',
            'slug'      => $slug,
            'resumen'   => 'Conoce FV Digital, tu aliado en soluciones digitales y desarrollo.',
            'contenido' => "FV Digital es una marca joven, creativa y con mucha energía.\n\n"
                . "Nos especializamos en desarrollo web, diseño y branding, aplicaciones y plantillas, y soporte técnico. Trabajamos de la mano contigo para convertir tus ideas en realidad.\n\n"
                . "¿Tienes un proyecto en mente? Escríbenos y lo hacemos posible.",
            'categoria' => 'Noticias',
            'autor'     => 'FV Digital',
            'activo'    => 1,
        ]);
        return $r['ok'];
    }, 'publicación inicial cargada');

    /* ============================================================
       7) Usuario administrador por defecto
       ============================================================ */
    ins_paso(static function () {
        $email  = 'admin@correo.com';
        $actual = usr_por_email($email);
        if ($actual === null) {
            $r = usr_crear([
                'nombre'   => 'Administrador',
                'email'    => $email,
                'password' => 'pass123',
                'rol'      => 'admin',
                'activo'   => 1,
            ]);
            return $r['ok'];
        }
        /* Ya existía: sólo se asegura el rol de administrador. */
        $r = usr_actualizar($actual['id'], ['rol' => 'admin']);
        return $r['ok'];
    }, 'usuario administrador verificado');

    /* ============================================================
       8) Carpetas de almacenamiento protegidas
       ============================================================ */
    ins_paso(static function () {
        $carpetas = [
            UPLOADS_DIR . '/entregables',
            UPLOADS_DIR . '/comprobantes',
            UPLOADS_DIR . '/facturas',
        ];
        $ok = true;
        foreach ($carpetas as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                $ok = false;
                continue;
            }
            $ht = $dir . '/.htaccess';
            if (!file_exists($ht)) {
                @file_put_contents($ht, "Require all denied\nDeny from all\n");
            }
        }
        return $ok;
    }, 'carpetas de uploads protegidas');
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
        .card { max-width: 640px; margin: auto; border-radius: 14px; }
    </style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Instalación FV Digital</h3>
    <?php if (empty($errores)): ?>
        <div class="alert alert-success">El sistema se instaló correctamente.</div>
        <ul class="small text-muted mb-3">
            <li>Índices creados en todas las colecciones (usuarios, catálogo, solicitudes, proyectos, pagos, facturación, comunicación y concesionaria).</li>
            <li>Datos iniciales cargados (datos del sitio, servicios, carta, publicación y testimonio).</li>
            <li>Usuario administrador: <b>admin@correo.com</b> / <b>pass123</b> — cámbiala al entrar.</li>
        </ul>
        <div class="alert alert-light border small mb-3">
            <b><i class="bi bi-list-check me-1"></i>Pasos aplicados (<?= count($pasos) ?>):</b>
            <ul class="mb-0 ps-3 small" style="max-height:220px;overflow:auto">
                <?php foreach ($pasos as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">No se pudo completar la instalación:</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
        <?php if ($pasos !== []): ?>
            <p class="small text-muted mb-0">Pasos completados antes del error (<?= count($pasos) ?>):</p>
            <ul class="small text-muted ps-3">
                <?php foreach ($pasos as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-primary">Ver página pública</a>
        <a href="admin/login.php" class="btn btn-outline-primary">Ir al panel</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo (<code>instalador.php</code>) del servidor.</p>
</div>
</body>
</html>