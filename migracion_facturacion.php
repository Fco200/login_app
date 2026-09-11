<?php
/* ============================================================
   FV DIGITAL - Migración: pagos robustos + facturación
   Idempotente y reejecutable desde el navegador:
   - proyectos_inicio: total_proyecto, pagado_total, saldo_restante,
     estado 'liquidado' en el ENUM.
   - pagos: proyecto_id, clave_unica (UNIQUE), aplicado, índices y FK.
   - Tablas nuevas: proyecto_historial (bitácora) y facturas.
   - Backfill: total desde solicitudes.presupuesto y saldo_restante
     desde pagos aprobados existentes.
   Ejecutar una vez; por seguridad borrar el archivo al terminar.
   ============================================================ */

require_once __DIR__ . '/funciones.php';

$errores = [];
$pasos   = [];

/* ---------- Helpers de inspección (information_schema) ---------- */

function mf_tabla_existe(string $tabla): bool {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$tabla]);
    return (int)$st->fetchColumn() > 0;
}

function mf_col_existe(string $tabla, string $col): bool {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$tabla, $col]);
    return (int)$st->fetchColumn() > 0;
}

function mf_idx_existe(string $tabla, string $idx): bool {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$tabla, $idx]);
    return (int)$st->fetchColumn() > 0;
}

function mf_fk_existe(string $tabla, string $fk): bool {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"');
    $st->execute([$tabla, $fk]);
    return (int)$st->fetchColumn() > 0;
}

function mf_ejecutar(string $sql, string $etiqueta): void {
    global $pdo, $errores, $pasos;
    try {
        $pdo->exec($sql);
        $pasos[] = $etiqueta;
    } catch (PDOException $e) {
        $errores[] = $etiqueta . ' -> ' . $e->getMessage();
    }
}

$confirmado = (($_GET['confirmar'] ?? '') === '1');

if ($confirmado) {
/* ============================================================
   1) proyectos_inicio: columnas de saldo y estado 'liquidado'
   ============================================================ */
if (!mf_col_existe('proyectos_inicio', 'total_proyecto')) {
    mf_ejecutar('ALTER TABLE proyectos_inicio ADD total_proyecto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER anticipo_minimo', 'proyectos_inicio.total_proyecto');
}
if (!mf_col_existe('proyectos_inicio', 'pagado_total')) {
    mf_ejecutar('ALTER TABLE proyectos_inicio ADD pagado_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_proyecto', 'proyectos_inicio.pagado_total');
}
if (!mf_col_existe('proyectos_inicio', 'saldo_restante')) {
    mf_ejecutar('ALTER TABLE proyectos_inicio ADD saldo_restante DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER pagado_total', 'proyectos_inicio.saldo_restante');
}
/* En MariaDB el ENUM se reemplaza completo (se redefine con 'liquidado'). */
mf_ejecutar("ALTER TABLE proyectos_inicio
             MODIFY estado ENUM('documentacion','anticipo_pendiente','en_desarrollo','liquidado','completado')
             NOT NULL DEFAULT 'documentacion'", 'proyectos_inicio.estado -> liquidado');

/* ============================================================
   2) pagos: proyecto_id, clave_unica, aplicado, índices y FK
   ============================================================ */
if (!mf_col_existe('pagos', 'proyecto_id')) {
    mf_ejecutar('ALTER TABLE pagos ADD proyecto_id INT NULL AFTER solicitud_id', 'pagos.proyecto_id');
}
if (!mf_col_existe('pagos', 'clave_unica')) {
    mf_ejecutar('ALTER TABLE pagos ADD clave_unica VARCHAR(64) NULL AFTER comprobante', 'pagos.clave_unica');
}
if (!mf_col_existe('pagos', 'aplicado')) {
    mf_ejecutar('ALTER TABLE pagos ADD aplicado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado', 'pagos.aplicado');
}
if (!mf_idx_existe('pagos', 'uk_pagos_clave')) {
    mf_ejecutar('ALTER TABLE pagos ADD UNIQUE KEY uk_pagos_clave (clave_unica)', 'pagos.clave_unica UNIQUE');
}
if (!mf_idx_existe('pagos', 'idx_pagos_usuario')) {
    mf_ejecutar('ALTER TABLE pagos ADD KEY idx_pagos_usuario (usuario_id)', 'pagos índice usuario');
}
if (!mf_idx_existe('pagos', 'idx_pagos_proyecto')) {
    mf_ejecutar('ALTER TABLE pagos ADD KEY idx_pagos_proyecto (proyecto_id)', 'pagos índice proyecto');
}
if (!mf_idx_existe('pagos', 'idx_pagos_estado')) {
    mf_ejecutar('ALTER TABLE pagos ADD KEY idx_pagos_estado (estado)', 'pagos índice estado');
}
if (!mf_fk_existe('pagos', 'fk_pagos_proyecto')) {
    mf_ejecutar('ALTER TABLE pagos ADD CONSTRAINT fk_pagos_proyecto
                 FOREIGN KEY (proyecto_id) REFERENCES proyectos_inicio(id) ON DELETE SET NULL', 'pagos FK proyecto_id');
}

/* ============================================================
   3) Tabla nueva: proyecto_historial (bitácora de proyectos)
   ============================================================ */
if (!mf_tabla_existe('proyecto_historial')) {
    mf_ejecutar("CREATE TABLE proyecto_historial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        proyecto_id INT NOT NULL,
        usuario_id INT NULL,
        accion VARCHAR(60) NOT NULL,
        detalle TEXT,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_hist_proyecto (proyecto_id, creado_en),
        CONSTRAINT fk_hist_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos_inicio(id) ON DELETE CASCADE,
        CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'tabla proyecto_historial');
}

/* ============================================================
   4) Tabla nueva: facturas
   ============================================================ */
if (!mf_tabla_existe('facturas')) {
    mf_ejecutar("CREATE TABLE facturas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folio VARCHAR(20) NOT NULL UNIQUE,
        proyecto_id INT NOT NULL,
        usuario_id INT NOT NULL,
        concepto VARCHAR(255) NOT NULL,
        rfq_cliente VARCHAR(20) NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        iva DECIMAL(12,2) NOT NULL DEFAULT 0,
        total DECIMAL(12,2) NOT NULL DEFAULT 0,
        pagos_json TEXT,
        estado ENUM('emitida','cancelada') DEFAULT 'emitida',
        emitida_por VARCHAR(100) NULL,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_fact_proyecto (proyecto_id),
        KEY idx_fact_usuario (usuario_id),
        CONSTRAINT fk_fact_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos_inicio(id) ON DELETE CASCADE,
        CONSTRAINT fk_fact_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'tabla facturas');
}

/* ============================================================
   5) datos fiscales opcionales en usuarios (RFC / dirección)
   ============================================================ */
if (mf_tabla_existe('usuarios') && !mf_col_existe('usuarios', 'rfc')) {
    mf_ejecutar('ALTER TABLE usuarios ADD rfc VARCHAR(20) NULL AFTER telefono', 'usuarios.rfc');
}
if (mf_tabla_existe('usuarios') && !mf_col_existe('usuarios', 'direccion')) {
    mf_ejecutar('ALTER TABLE usuarios ADD direccion VARCHAR(255) NULL AFTER rfc', 'usuarios.direccion');
}

/* ============================================================
   6) Backfill idempotente (DATOS)
   ============================================================ */
if (mf_tabla_existe('proyectos_inicio')) {
    /* 6a) Vincular pagos históricos a su proyecto por solicitud_id. */
    try {
        $pdo->exec("UPDATE pagos p
                    JOIN proyectos_inicio pi ON pi.solicitud_id = p.solicitud_id AND pi.usuario_id = p.usuario_id
                    SET p.proyecto_id = pi.id
                    WHERE p.proyecto_id IS NULL AND p.tipo_pago IN ('anticipo','restante','completo')");
        $pasos[] = 'pagos históricos vinculados por solicitud_id';
    } catch (PDOException $e) {
        $errores[] = 'vincular pagos históricos -> ' . $e->getMessage();
    }

    /* 6b) Definir total_proyecto a partir de solicitudes.presupuesto. */
    try {
        $rows = $pdo->query('SELECT pi.id, pi.total_proyecto, s.presupuesto
                             FROM proyectos_inicio pi
                             LEFT JOIN solicitudes s ON pi.solicitud_id = s.id')->fetchAll();
        $upd = $pdo->prepare('UPDATE proyectos_inicio SET total_proyecto = ? WHERE id = ?');
        foreach ($rows as $r) {
            if ((float)$r['total_proyecto'] > 0) {
                continue; // ya tiene valor definido
            }
            $total = proyecto_total_parsear($r['presupuesto']);
            if ($total > 0) {
                $upd->execute([$total, (int)$r['id']]);
            }
        }
        $pasos[] = 'total_proyecto calculado desde presupuesto';
    } catch (PDOException $e) {
        $errores[] = 'backfill total_proyecto -> ' . $e->getMessage();
    }

    /* 6c) Recalcular saldo de todos los proyectos (aprobados existentes). */
    try {
        if (mf_tabla_existe('proyecto_historial')) {
            $pdo->exec('DELETE FROM proyecto_historial WHERE accion = "migracion_saldo"');
        }
        foreach ($pdo->query('SELECT id FROM proyectos_inicio') as $r) {
            proyecto_recalcular_saldo((int)$r['id']);
            registrar_historial_proyecto((int)$r['id'], 'migracion_saldo', 'Saldo financiero recalculado por la migración de pagos y facturación.');
        }
        $pasos[] = 'saldo_restante / liquidación recalculados';
    } catch (Throwable $e) {
        $errores[] = 'backfill saldo_restante -> ' . $e->getMessage();
    }
}
} /* fin: solo se ejecuta con ?confirmar=1 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración: Pagos robustos y Facturación - FV Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background: #eef3fb; display: flex; align-items: center; min-height: 100vh; } .card { max-width: 620px; margin: auto; border-radius: 14px; }</style>
</head>
<body>
<div class="card shadow p-4 m-3">
    <h3 class="fw-bold text-primary mb-3">Migración: Pagos robustos y Facturación</h3>
    <?php if (!$confirmado): ?>
        <div class="alert alert-info">
            La migración aún no se aplica en esta visita. Este script es <b>idempotente</b> y reejecutable:
            si ya la pusiste en marcha antes, no es necesario correrla de nuevo y puedes borrar el archivo.
        </div>
        <a class="btn btn-primary" href="migracion_facturacion.php?confirmar=1"><i class="bi bi-play-fill me-1"></i>Ejecutar la migración</a>
    <?php elseif (empty($errores)): ?>
        <div class="alert alert-success">Migración ejecutada correctamente.</div>
        <ul class="small text-muted mb-3">
            <li>proyectos_inicio: total_proyecto, pagado_total, saldo_restante y estado "liquidado".</li>
            <li>pagos: proyecto_id, clave_unica (única contra duplicados) y aplicado.</li>
            <li>Tablas nuevas: proyecto_historial y facturas.</li>
        </ul>
        <div class="alert alert-light border small mb-3">
            <b><i class="bi bi-list-check me-1"></i>Pasos aplicados:</b>
            <ul class="mb-0 ps-3">
                <?php foreach ($pasos as $p): ?><li><?= e($p) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Ocurrieron errores (el script es reejecutable, corrige e inténtalo de nuevo):</div>
        <pre class="small"><?= e(implode("\n", $errores)) ?></pre>
    <?php endif; ?>
    <div class="d-flex gap-2">
        <a href="admin/facturacion.php" class="btn btn-primary">Ir a Facturación</a>
        <a href="portal/facturacion.php" class="btn btn-outline-primary">Facturación del cliente</a>
        <a href="admin/index.php" class="btn btn-outline-secondary">Panel</a>
    </div>
    <p class="text-danger small mt-3 mb-0">Por seguridad, elimina este archivo del servidor.</p>
</div>
</body>
</html>