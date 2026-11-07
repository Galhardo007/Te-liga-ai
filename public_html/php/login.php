<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email, senha } -> { sucesso, usuario } | 403 { confirmacao: true } se o e-mail ainda não foi confirmado
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));
$senha = is_string($d['senha'] ?? null) ? $d['senha'] : '';

exigir_limite('login-ip', ip_cliente(), 30, 900);
exigir_limite('login-email', $email, 8, 900);

$u = linha('SELECT * FROM usuarios WHERE email = ?', [$email]);
// password_verify roda mesmo sem conta, para o tempo de resposta não denunciar quem existe
$hash = $u['senha_hash'] ?? '$2y$10$usesomesillystringforsaltDGqjNf3D0w5Uq8.1hTXlaK1Ag6cYO';
if (!password_verify($senha, $hash) || !$u) {
    falhar('E-mail ou senha incorretos.', 401, 'senha');
}
if ($u['status'] === 'bloqueado') falhar('Esta conta está bloqueada. Fale com o suporte.', 403);
if ($u['status'] === 'pendente') {
    falhar('Confirme seu e-mail para entrar. Enviamos um código quando você se cadastrou.', 403, null, ['confirmacao' => true]);
}
if (password_needs_rehash($u['senha_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
    consulta('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [gerar_hash_senha($senha), $u['id']]);
}
zerar_limite('login-email', $email);
entrar($u);
responder(['sucesso' => true, 'usuario' => usuario_publico($u), 'csrf' => $_SESSION['csrf']]);
