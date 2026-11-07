<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email } -> { sucesso }   Envia o código para trocar a senha (usuário ou anunciante).
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));
if ($e = validar_email($email)) falhar($e, 422, 'email');

exigir_limite('esqueci-ip', ip_cliente(), 10, 3600);
exigir_limite('esqueci-email', $email, 1, 60);
exigir_limite('esqueci-email-hora', $email, 5, 3600);

$u = conta_por_email($email);
if ($u && $u['status'] === 'ativo') {
    $codigo = criar_codigo($u['ref'], 'redefinir_senha');
    enviar_codigo_por_email($u['email'], $u['nome'], $codigo, 'redefinir_senha');
}
// sempre a mesma resposta: ninguém descobre se um e-mail tem conta
responder(['sucesso' => true]);
