<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email, codigo } -> { sucesso, usuario? }
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));
$codigo = so_numeros($d['codigo'] ?? '');

exigir_limite('confirmar-ip', ip_cliente(), 30, 900);

$u = linha('SELECT * FROM usuarios WHERE email = ?', [$email]);
if (!$u || $u['status'] !== 'pendente' || !conferir_codigo((int)$u['id'], 'confirmar_email', $codigo)) {
    falhar('Código incorreto ou vencido. Confira e tente de novo.', 400, 'codigo');
}

consulta("UPDATE usuarios SET status = 'ativo', email_confirmado_em = NOW() WHERE id = ?", [$u['id']]);
$u['status'] = 'ativo';
entrar($u);
responder(['sucesso' => true, 'usuario' => usuario_publico($u)]);
