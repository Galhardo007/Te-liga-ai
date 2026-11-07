<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// Só para contas com admin:*. Todas as ações são POST JSON com X-CSRF-Token.
// { acao: "ver", usuario_id }
// { acao: "permissao", usuario_id, permissao: "publicar:lojas", efeito: "conceder"|"revogar"|"padrao", expira_em?: "2027-01-31 23:59:59" }
// { acao: "status", usuario_id, status: "ativo"|"bloqueado" }
exigir_metodo('POST');
$admin = exigir_login();
exigir_csrf();
$d = ler_json();
$alvo = linha('SELECT * FROM usuarios WHERE id = ?', [(int)($d['usuario_id'] ?? 0)]);
if (!$alvo) falhar('Conta não encontrada.', 404);

switch ($d['acao'] ?? '') {
    case 'ver':
        exigir_permissao($admin, 'admin:usuarios');
        $individuais = consulta('SELECT p.chave, up.efeito, up.expira_em FROM usuario_permissoes up
                                   JOIN permissoes p ON p.id = up.permissao_id WHERE up.usuario_id = ?', [$alvo['id']])->fetchAll();
        responder(['sucesso' => true, 'usuario' => usuario_publico($alvo) + ['status' => $alvo['status']], 'individuais' => $individuais]);

    case 'permissao':
        exigir_permissao($admin, 'admin:permissoes');
        $chave = texto($d['permissao'] ?? '', 64);
        $efeito = $d['efeito'] ?? '';
        $expira = !empty($d['expira_em']) ? date('Y-m-d H:i:s', strtotime((string)$d['expira_em'])) : null;
        try {
            if ($efeito === 'padrao') remover_permissao_individual((int)$alvo['id'], $chave);
            elseif (in_array($efeito, ['conceder', 'revogar'], true)) definir_permissao((int)$alvo['id'], $chave, $efeito, $expira, (int)$admin['id']);
            else falhar('Efeito inválido.');
        } catch (InvalidArgumentException $e) {
            falhar('Permissão desconhecida.', 404);
        }
        responder(['sucesso' => true, 'permissoes' => permissoes_do_usuario((int)$alvo['id'], $alvo['tipo'])]);

    case 'status':
        exigir_permissao($admin, 'admin:usuarios');
        $status = $d['status'] ?? '';
        if (!in_array($status, ['ativo', 'bloqueado'], true) || (int)$alvo['id'] === (int)$admin['id']) falhar('Status inválido.');
        // bloquear também derruba as sessões abertas
        consulta('UPDATE usuarios SET status = ?, sessao_versao = sessao_versao + 1 WHERE id = ?', [$status, $alvo['id']]);
        responder(['sucesso' => true]);
}
falhar('Ação inválida.');
