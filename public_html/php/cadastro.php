<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { nome, email, whatsapp, cidade, senha, tipo, aceite, website_hp } -> { sucesso, confirmacao, usuario? }
exigir_metodo('POST');
$d = ler_json();

// armadilha para robôs: finge sucesso e não grava nada
if (texto($d['website_hp'] ?? '') !== '') responder(['sucesso' => true, 'confirmacao' => true]);

exigir_limite('cadastro-ip', ip_cliente(), 10, 3600);

$nome     = texto($d['nome'] ?? '', 80);
$email    = mb_strtolower(texto($d['email'] ?? '', 200));
$whatsapp = so_numeros($d['whatsapp'] ?? '');
$cidade   = texto($d['cidade'] ?? '', 80);
$senha    = $d['senha'] ?? '';
$tipo     = (string)($d['tipo'] ?? '');

if ($e = validar_nome($nome))         falhar($e, 422, 'nome');
if ($e = validar_email($email))       falhar($e, 422, 'email');
if ($e = validar_whatsapp($whatsapp)) falhar($e, 422, 'whatsapp');
if (mb_strlen($cidade) < 2)           falhar('Digite sua cidade.', 422, 'cidade');
if ($e = validar_senha($senha))       falhar($e, 422, 'senha');
if (!in_array($tipo, TIPOS_CADASTRO, true)) falhar('Escolha o tipo de conta para continuar.', 422, 'tipo');
if (($d['aceite'] ?? false) !== true) falhar('Para criar a conta, aceite os termos de uso e a política de privacidade.', 422, 'aceite');

$pdo = db();
$pdo->beginTransaction();
$existente = linha('SELECT id, status FROM usuarios WHERE email = ? FOR UPDATE', [$email]);

if ($existente && $existente['status'] !== 'pendente') {
    $pdo->rollBack();
    falhar('Este e-mail já tem conta. Entre ou use "Esqueci a senha".', 409, 'email');
}

$hash = gerar_hash_senha($senha);
if ($existente) {
    // conta nunca confirmada: quem tem acesso ao e-mail pode refazer o cadastro
    $id = (int)$existente['id'];
    consulta('UPDATE usuarios SET tipo=?, nome=?, whatsapp=?, cidade=?, senha_hash=?, aceite_termos_em=NOW() WHERE id=?',
        [$tipo, $nome, $whatsapp, $cidade, $hash, $id]);
    consulta('DELETE FROM anunciantes WHERE usuario_id = ?', [$id]);
} else {
    consulta('INSERT INTO usuarios (tipo, nome, email, whatsapp, cidade, senha_hash, status, aceite_termos_em)
              VALUES (?, ?, ?, ?, ?, ?, \'pendente\', NOW())', [$tipo, $nome, $email, $whatsapp, $cidade, $hash]);
    $id = (int)$pdo->lastInsertId();
}
if ($tipo === 'anunciante') {
    consulta('INSERT INTO anunciantes (usuario_id) VALUES (?)', [$id]);
}
$codigo = criar_codigo($id, 'confirmar_email');
$pdo->commit();

dentro_do_limite('reenvio-email', $email, 99, 60); // conta este envio para o intervalo de reenvio
if (!enviar_codigo_por_email($email, $nome, $codigo, 'confirmar_email')) {
    falhar('Conta criada, mas não conseguimos enviar o e-mail agora. Toque em "reenviar o código".', 502, null,
        ['confirmacao' => true]);
}
responder(['sucesso' => true, 'confirmacao' => true]);
