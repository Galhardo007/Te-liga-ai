<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email } -> { sucesso }   (código de confirmação de cadastro)
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));

exigir_limite('reenvio-ip', ip_cliente(), 10, 3600);
exigir_limite('reenvio-email', $email, 1, 60);
exigir_limite('reenvio-email-hora', $email, 5, 3600);

$u = conta_por_email($email);
if ($u && $u['status'] === 'pendente') {
    $codigo = criar_codigo($u['ref'], 'confirmar_email');
    if (!enviar_codigo_por_email($u['email'], $u['nome'], $codigo, 'confirmar_email')) {
        falhar('Não foi possível reenviar agora. Tente de novo em instantes.', 502);
    }
}
// mesma resposta exista ou não a conta (não revela quem está cadastrado)
responder(['sucesso' => true]);
