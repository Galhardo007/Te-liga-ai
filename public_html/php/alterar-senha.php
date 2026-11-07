<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { senha_atual, senha_nova } (logado, com X-CSRF-Token)
exigir_metodo('POST');
$u = exigir_login();
exigir_csrf();
$d = ler_json();
exigir_limite('alterar-senha', (string)$u['id'], 5, 900);

if (!password_verify((string)($d['senha_atual'] ?? ''), $u['senha_hash'])) falhar('Senha atual incorreta.', 400, 'senha_atual');
if ($e = validar_senha($d['senha_nova'] ?? '')) falhar($e, 422, 'senha_nova');

consulta('UPDATE usuarios SET senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?',
    [gerar_hash_senha($d['senha_nova']), $u['id']]);
// mantém esta sessão, encerra as outras
$u['sessao_versao'] = (int)$u['sessao_versao'] + 1;
entrar($u);
responder(['sucesso' => true, 'csrf' => $_SESSION['csrf']]);
