<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email, codigo, senha } -> { sucesso }
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));
$codigo = so_numeros($d['codigo'] ?? '');
$senha = $d['senha'] ?? '';

exigir_limite('redefinir-ip', ip_cliente(), 30, 900);
if ($e = validar_senha($senha)) falhar($e, 422, 'senha');

$u = conta_por_email($email);
if (!$u || $u['status'] !== 'ativo' || !conferir_codigo($u['ref'], 'redefinir_senha', $codigo)) {
    falhar('Código incorreto ou vencido. Confira e tente de novo.', 400, 'codigo');
}

// troca a senha e derruba todas as sessões abertas (sessao_versao + 1)
conta_atualizar($u, ['senha_hash' => gerar_hash_senha($senha)], true);
zerar_limite('login-email', $email);
responder(['sucesso' => true]);
