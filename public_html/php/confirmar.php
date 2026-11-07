<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST { email, codigo } -> { sucesso, usuario? }
exigir_metodo('POST');
$d = ler_json();
$email = mb_strtolower(texto($d['email'] ?? '', 120));
$codigo = so_numeros($d['codigo'] ?? '');

exigir_limite('confirmar-ip', ip_cliente(), 30, 900);

$u = conta_por_email($email);
if (!$u || $u['status'] !== 'pendente' || !conferir_codigo($u['ref'], 'confirmar_email', $codigo)) {
    falhar('Código incorreto ou vencido. Confira e tente de novo.', 400, 'codigo');
}

$u = conta_atualizar($u, ['status' => 'ativo', 'email_confirmado_em' => agora()]);
entrar($u);
responder(['sucesso' => true, 'usuario' => usuario_publico($u)]);
