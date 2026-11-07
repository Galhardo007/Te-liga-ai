<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// Só admins (e-mail em private/auth/usuarios.json). POST JSON com X-CSRF-Token.
// A conta-alvo vem em "usuario_id" (12 = usuário, "c…" = anunciante) ou em "email".
// { acao: "ver", usuario_id | email }
// { acao: "permissao", usuario_id | email, permissao: "publicar:lojas", efeito: "conceder"|"revogar"|"padrao", expira_em?: "2027-01-31 23:59:59" }
// { acao: "status", usuario_id | email, status: "ativo"|"bloqueado" }
// { acao: "limite", usuario_id | email, publicacoes_por_dia: 50 | null }   (só anunciante)
exigir_metodo('POST');
$admin = exigir_login();
exigir_csrf();
$d = ler_json();
$alvo = !empty($d['email'])
    ? conta_por_email(mb_strtolower(texto($d['email'], 120)))
    : conta_por_id($d['usuario_id'] ?? null);

switch ($d['acao'] ?? '') {
    case 'ver':
        exigir_permissao($admin, 'admin:usuarios');
        if (!$alvo) falhar('Conta não encontrada.', 404);
        responder(['sucesso' => true, 'usuario' => usuario_publico($alvo) + ['status' => $alvo['status']],
            'individuais' => individuais_da_conta($alvo)]);

    case 'permissao':
        exigir_permissao($admin, 'admin:permissoes');
        if (!$alvo) falhar('Conta não encontrada.', 404);
        $chave = texto($d['permissao'] ?? '', 64);
        $efeito = $d['efeito'] ?? '';
        $expira = !empty($d['expira_em']) ? date('Y-m-d H:i:s', strtotime((string)$d['expira_em'])) : null;
        try {
            if ($efeito === 'padrao') remover_permissao_individual($alvo, $chave);
            elseif (in_array($efeito, ['conceder', 'revogar'], true)) definir_permissao($alvo, $chave, $efeito, $expira, $admin['ref']);
            else falhar('Efeito inválido.');
        } catch (InvalidArgumentException $e) {
            falhar('Permissão desconhecida.', 404);
        }
        responder(['sucesso' => true, 'permissoes' => permissoes_da_conta(conta_por_ref($alvo['ref']))]);

    case 'status':
        exigir_permissao($admin, 'admin:usuarios');
        if (!$alvo) falhar('Conta não encontrada.', 404);
        $status = $d['status'] ?? '';
        if (!in_array($status, ['ativo', 'bloqueado'], true) || $alvo['ref'] === $admin['ref']) falhar('Status inválido.');
        conta_atualizar($alvo, ['status' => $status], true); // bloquear também derruba as sessões abertas
        responder(['sucesso' => true]);

    case 'limite':
        exigir_permissao($admin, 'admin:permissoes');
        if (!$alvo || $alvo['tipo'] !== 'anunciante') falhar('Conta de anunciante não encontrada.', 404);
        $n = $d['publicacoes_por_dia'] ?? null;
        if ($n !== null && (!is_int($n) || $n < 0 || $n > 10000)) falhar('Limite inválido.');
        definir_limite_publicacoes($alvo, $n);
        responder(['sucesso' => true, 'publicacoes_por_dia' => limite_publicacoes_dia($alvo)]);
}
falhar('Ação inválida.');
