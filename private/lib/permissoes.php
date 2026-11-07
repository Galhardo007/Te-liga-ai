<?php
declare(strict_types=1);

/**
 * Permissões efetivas = pacote do tipo de conta
 *                     + concessões individuais (não vencidas)
 *                     − revogações individuais (não vencidas).
 * Calculado a cada requisição: mudou no banco, vale na hora.
 */
function permissoes_do_usuario(int $usuarioId, string $tipo): array
{
    static $cache = [];
    if (isset($cache[$usuarioId])) return $cache[$usuarioId];

    $base = consulta(
        'SELECT p.chave FROM tipo_permissoes tp JOIN permissoes p ON p.id = tp.permissao_id WHERE tp.tipo = ?',
        [$tipo]
    )->fetchAll(PDO::FETCH_COLUMN);

    $individuais = consulta(
        'SELECT p.chave, up.efeito FROM usuario_permissoes up
           JOIN permissoes p ON p.id = up.permissao_id
          WHERE up.usuario_id = ? AND (up.expira_em IS NULL OR up.expira_em > NOW())',
        [$usuarioId]
    )->fetchAll();

    $efetivas = array_fill_keys($base, true);
    foreach ($individuais as $i) {
        if ($i['efeito'] === 'conceder') $efetivas[$i['chave']] = true;
        else unset($efetivas[$i['chave']]);
    }
    $lista = array_keys($efetivas);
    sort($lista);
    return $cache[$usuarioId] = $lista;
}

function pode(array $usuario, string $permissao): bool
{
    return in_array($permissao, permissoes_do_usuario((int)$usuario['id'], $usuario['tipo']), true);
}

function exigir_permissao(array $usuario, string $permissao): void
{
    if (!pode($usuario, $permissao)) falhar('Sua conta não tem permissão para isso.', 403);
}

/** Concede (ou revoga) uma permissão a uma pessoa. $expira = 'Y-m-d H:i:s' ou null. */
function definir_permissao(int $usuarioId, string $chave, string $efeito = 'conceder', ?string $expira = null, ?int $porQuem = null): void
{
    $p = linha('SELECT id FROM permissoes WHERE chave = ?', [$chave]);
    if (!$p) throw new InvalidArgumentException("Permissão desconhecida: $chave");
    consulta(
        'INSERT INTO usuario_permissoes (usuario_id, permissao_id, efeito, expira_em, concedido_por)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE efeito = VALUES(efeito), expira_em = VALUES(expira_em), concedido_por = VALUES(concedido_por)',
        [$usuarioId, $p['id'], $efeito, $expira, $porQuem]
    );
}

function remover_permissao_individual(int $usuarioId, string $chave): void
{
    consulta(
        'DELETE up FROM usuario_permissoes up JOIN permissoes p ON p.id = up.permissao_id
          WHERE up.usuario_id = ? AND p.chave = ?',
        [$usuarioId, $chave]
    );
}
