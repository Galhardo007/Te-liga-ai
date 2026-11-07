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

$u = linha("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'", [$email]);
if (!$u || !conferir_codigo((int)$u['id'], 'redefinir_senha', $codigo)) {
    falhar('Código incorreto ou vencido. Confira e tente de novo.', 400, 'codigo');
}

// troca a senha e derruba todas as sessões abertas (sessao_versao + 1)
consulta('UPDATE usuarios SET senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?',
    [gerar_hash_senha($senha), $u['id']]);
zerar_limite('login-email', $email);
responder(['sucesso' => true]);
