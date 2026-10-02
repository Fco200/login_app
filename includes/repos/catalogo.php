<?php
/* ============================================================
   FV DIGITAL - Repositorio de catálogo
   ------------------------------------------------------------
   Colecciones: `servicios`, `proyectos`, `publicaciones`,
   `testimonios`, `cartas`, `productos`.
   ============================================================ */

/* ---------- Servicios ---------- */

/** Campos que el panel puede escribir de un servicio. */
function srv_campos(array $d): array
{
    return [
        'titulo'           => trim((string)($d['titulo'] ?? '')),
        'slug'             => fv_texto($d['slug'] ?? null),
        'descripcion_corta'=> fv_texto($d['descripcion_corta'] ?? null),
        'descripcion'      => fv_texto($d['descripcion'] ?? null),
        'icono'            => fv_texto($d['icono'] ?? 'bi-code-slash') ?? 'bi-code-slash',
        'categoria'        => fv_texto($d['categoria'] ?? 'desarrollo-web') ?? 'desarrollo-web',
        'precio_desde'     => fv_float($d['precio_desde'] ?? 0),
        'destaque'         => fv_int($d['destaque'] ?? 0),
        'activo'           => fv_int($d['activo'] ?? 1, 1),
    ];
}

function srv_activos(int $limite = 0): array
{
    $op = ['sort' => ['destaque' => -1, 'titulo' => 1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('servicios', ['activo' => 1], $op);
}

function srv_todos(): array
{
    return col_q('servicios', [], ['sort' => ['destaque' => -1, 'titulo' => 1]]);
}

function srv_por_id($id): ?array
{
    return col_q1('servicios', filtro_id($id));
}

function srv_por_slug(string $slug): ?array
{
    return col_q1('servicios', ['slug' => $slug]);
}

function srv_guardar($id, array $d): array
{
    $campos = srv_campos($d);
    if ($campos['titulo'] === '') {
        return ['ok' => false, 'mensaje' => 'El título es obligatorio.'];
    }
    if (($campos['slug'] ?? '') === '') {
        $campos['slug'] = slugify($campos['titulo']);
    }
    if (!es_nuevo($id)) {
        return crud_actualizar('servicios', $id, $campos);
    }
    return crud_crear('servicios', $campos);
}

/* ---------- Proyectos (portafolio público) ---------- */

function pro_campos(array $d): array
{
    return [
        'titulo'         => trim((string)($d['titulo'] ?? '')),
        'slug'           => fv_texto($d['slug'] ?? null),
        'descripcion'    => fv_texto($d['descripcion'] ?? null),
        'categoria'      => fv_texto($d['categoria'] ?? 'General') ?? 'General',
        'cliente'        => fv_texto($d['cliente'] ?? null),
        'anio'           => ($d['anio'] ?? '') === '' ? null : fv_int($d['anio']),
        'url'            => fv_texto($d['url'] ?? null),
        'imagen'         => fv_texto($d['imagen'] ?? null),
        'archivo'        => fv_texto($d['archivo'] ?? null),
        'archivo_nombre' => fv_texto($d['archivo_nombre'] ?? null),
        'destaque'       => fv_int($d['destaque'] ?? 0),
        'activo'         => fv_int($d['activo'] ?? 1, 1),
    ];
}

function pro_activos(int $limite = 0, string $categoria = ''): array
{
    $f = ['activo' => 1];
    if ($categoria !== '') {
        $f['categoria'] = $categoria;
    }
    $op = ['sort' => ['destaque' => -1, 'creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('proyectos', $f, $op);
}

function pro_todos(): array
{
    return col_q('proyectos', [], ['sort' => ['destaque' => -1, 'creado_en' => -1]]);
}

function pro_por_id($id): ?array
{
    return col_q1('proyectos', filtro_id($id));
}

function pro_guardar($id, array $d): array
{
    $campos = pro_campos($d);
    if ($campos['titulo'] === '') {
        return ['ok' => false, 'mensaje' => 'El título es obligatorio.'];
    }
    if (($campos['slug'] ?? '') === '') {
        $campos['slug'] = slugify($campos['titulo']);
    }
    if (!es_nuevo($id)) {
        return crud_actualizar('proyectos', $id, $campos);
    }
    return crud_crear('proyectos', $campos);
}

function pro_contar_activos(): int
{
    return col_contar('proyectos', ['activo' => 1]);
}

/* ---------- Publicaciones ---------- */

function pub_campos(array $d): array
{
    return [
        'titulo'      => trim((string)($d['titulo'] ?? '')),
        'slug'        => fv_texto($d['slug'] ?? null),
        'resumen'     => fv_texto($d['resumen'] ?? null),
        'contenido'   => fv_texto($d['contenido'] ?? null),
        'categoria'   => fv_texto($d['categoria'] ?? 'Noticias') ?? 'Noticias',
        'imagen'      => fv_texto($d['imagen'] ?? null),
        'autor'       => fv_texto($d['autor'] ?? null),
        'destaque'    => fv_int($d['destaque'] ?? 0),
        'activo'      => fv_int($d['activo'] ?? 1, 1),
    ];
}

function pub_activos(int $limite = 0): array
{
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('publicaciones', ['activo' => 1], $op);
}

function pub_todas(): array
{
    return col_q('publicaciones', [], ['sort' => ['destaque' => -1, 'creado_en' => -1]]);
}

function pub_por_id($id): ?array
{
    return col_q1('publicaciones', filtro_id($id));
}

function pub_por_slug(string $slug): ?array
{
    return col_q1('publicaciones', ['slug' => $slug, 'activo' => 1]);
}

function pub_por_slug_admin(string $slug): ?array
{
    return col_q1('publicaciones', ['slug' => $slug]);
}

function pub_guardar($id, array $d): array
{
    $campos = pub_campos($d);
    if ($campos['titulo'] === '') {
        return ['ok' => false, 'mensaje' => 'El título es obligatorio.'];
    }
    if (($campos['slug'] ?? '') === '') {
        $campos['slug'] = slugify($campos['titulo']);
    }
    return !es_nuevo($id)
        ? crud_actualizar('publicaciones', $id, $campos)
        : crud_crear('publicaciones', $campos);
}

function pub_visitas(int $id, int $visitas = 1): void
{
    col_actualizar('publicaciones', $id, ['$inc' => ['visitas' => $visitas]]);
}

function pub_visitas_totales(): float
{
    return col_suma('publicaciones', [], 'visitas');
}

/* ---------- Testimonios ---------- */

function tes_campos(array $d): array
{
    return [
        'nombre'     => trim((string)($d['nombre'] ?? '')),
        'cargo'      => fv_texto($d['cargo'] ?? null),
        'mensaje'    => trim((string)($d['mensaje'] ?? '')),
        'valoracion' => max(1, min(5, fv_int($d['valoracion'] ?? 5, 5))),
        'activo'     => fv_int($d['activo'] ?? 1, 1),
    ];
}

function tes_activos(int $limite = 0): array
{
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('testimonios', ['activo' => 1], $op);
}

function tes_todos(): array
{
    return col_q('testimonios', [], ['sort' => ['creado_en' => -1]]);
}

function tes_por_id($id): ?array
{
    return col_q1('testimonios', filtro_id($id));
}

function tes_guardar($id, array $d): array
{
    $campos = tes_campos($d);
    if ($campos['nombre'] === '' || $campos['mensaje'] === '') {
        return ['ok' => false, 'mensaje' => 'Nombre y mensaje son obligatorios.'];
    }
    return !es_nuevo($id)
        ? crud_actualizar('testimonios', $id, $campos)
        : crud_crear('testimonios', $campos);
}

/* ---------- Cartas ---------- */

function car_campos(array $d): array
{
    return [
        'titulo'       => trim((string)($d['titulo'] ?? '')),
        'slug'         => fv_texto($d['slug'] ?? null),
        'destinatario' => fv_texto($d['destinatario'] ?? null),
        'contenido'    => fv_texto($d['contenido'] ?? null),
        'firmado_por'  => fv_texto($d['firmado_por'] ?? null),
        'activo'       => fv_int($d['activo'] ?? 1, 1),
    ];
}

function car_activas(): array
{
    return col_q('cartas', ['activo' => 1], ['sort' => ['creado_en' => -1]]);
}

function car_todas(): array
{
    return col_q('cartas', [], ['sort' => ['creado_en' => -1]]);
}

function car_por_id($id): ?array
{
    return col_q1('cartas', filtro_id($id));
}

function car_por_slug(string $slug): ?array
{
    return col_q1('cartas', ['slug' => $slug, 'activo' => 1]);
}

function car_guardar($id, array $d): array
{
    $campos = car_campos($d);
    if ($campos['titulo'] === '') {
        return ['ok' => false, 'mensaje' => 'El título es obligatorio.'];
    }
    if (($campos['slug'] ?? '') === '') {
        $campos['slug'] = slugify($campos['titulo']);
    }
    return !es_nuevo($id)
        ? crud_actualizar('cartas', $id, $campos)
        : crud_crear('cartas', $campos);
}

/* ---------- Productos ---------- */

function prd_campos(array $d): array
{
    return [
        'titulo'      => trim((string)($d['titulo'] ?? '')),
        'slug'        => fv_texto($d['slug'] ?? null),
        'descripcion' => fv_texto($d['descripcion'] ?? null),
        'precio'      => fv_float($d['precio'] ?? 0),
        'imagen'      => fv_texto($d['imagen'] ?? null),
        'categoria'   => fv_texto($d['categoria'] ?? null),
        'stock'       => fv_int($d['stock'] ?? 0),
        'activo'      => fv_int($d['activo'] ?? 1, 1),
    ];
}

function prd_activos(int $limite = 0): array
{
    $op = ['sort' => ['creado_en' => -1]];
    if ($limite > 0) {
        $op['limit'] = $limite;
    }
    return col_q('productos', ['activo' => 1], $op);
}

/** Todos los productos, incluidos los inactivos (panel). */
function prd_todos(): array
{
    return col_q('productos', [], ['sort' => ['creado_en' => -1]]);
}

function prd_por_id($id): ?array
{
    return col_q1('productos', filtro_id($id));
}

function prd_guardar($id, array $d): array
{
    $campos = prd_campos($d);
    if ($campos['titulo'] === '') {
        return ['ok' => false, 'mensaje' => 'El título es obligatorio.'];
    }
    if (($campos['slug'] ?? '') === '') {
        $campos['slug'] = slugify($campos['titulo']);
    }
    return !es_nuevo($id)
        ? crud_actualizar('productos', $id, $campos)
        : crud_crear('productos', $campos);
}

/** Elimina un producto y sus renglones de carrito. */
function prd_eliminar($id): bool
{
    $o = oid($id);
    if ($o === null) {
        return false;
    }
    col_borrar_varios('carrito', ['producto_id' => $o]);
    return col_borrar('productos', $id);
}