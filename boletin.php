<?php
/* Suscripción al boletín de FV Digital */
require_once __DIR__ . '/funciones.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['empresa'])) {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if ($email) {
        $pdo->prepare('INSERT INTO suscripciones (email) VALUES (?) ON DUPLICATE KEY UPDATE activo = 1')->execute([$email]);
        if (es_ajax()) {
            responder([
                'ok'      => true,
                'titulo'  => '¡Suscripción confirmada!',
                'mensaje' => 'Recibirás novedades y promociones de FV Digital en ' . $email,
            ]);
        }
        $_SESSION['boletin'] = 'ok';
    } else {
        if (es_ajax()) {
            responder(['ok' => false, 'mensaje' => 'Escribe un correo válido para suscribirte.', 'tipo' => 'danger']);
        }
        $_SESSION['boletin'] = 'error';
    }
}

if (es_ajax()) {
    exit;
}

$retorno = $_POST['retorno'] ?? 'index.php';
if (preg_match('/^[a-z0-9_\-\/\.\?\=\&]*$/i', $retorno) && str_starts_with($retorno, '/')) {
    header('Location: ' . $retorno);
} else {
    header('Location: index.php');
}
exit;