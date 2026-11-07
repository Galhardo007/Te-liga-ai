<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';
require TLA_PRIVADO . '/lib/firebase.php';

// POST (logado, X-CSRF-Token) -> { token, uid, config }  Login no Firebase para o chat.
exigir_metodo('POST');
$u = exigir_login();
exigir_csrf();
exigir_permissao($u, 'chat:usar');
exigir_limite('firebase-token', (string)$u['id'], 60, 3600);

$token = firebase_token_personalizado(firebase_uid((int)$u['id']), [
    'tipo' => $u['tipo'],
    'nome' => $u['nome'],
]);
responder([
    'sucesso' => true,
    'token'   => $token,
    'uid'     => firebase_uid((int)$u['id']),
    'nome'    => $u['nome'],
    'config'  => cfg('firebase.web'),
]);
