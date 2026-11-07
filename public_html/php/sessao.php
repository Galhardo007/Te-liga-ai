<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET -> { logado, usuario?, csrf }   (as páginas chamam ao abrir)
exigir_metodo('GET');
$u = usuario_logado();
responder([
    'sucesso' => true,
    'logado'  => (bool)$u,
    'usuario' => $u ? usuario_publico($u) : null,
    'csrf'    => $_SESSION['csrf'],
]);
