<?php
declare(strict_types=1);

// Permissões efetivas = pacote padrão do tipo + concessões individuais − revogações individuais (não vencidas).
//   usuário:    padrão em usuario_permissoes_padrao, individuais em usuario_permissoes (MySQL)
//   anunciante: padrão e individuais em private/security/anun_limites.json (fora do banco)
//   admin:      usuário cujo e-mail está em private/auth/usuarios.json -> recebe todas as permissões
// O catálogo de chaves válidas é a tabela permissoes.

function arquivo_limites_anunciantes(): string
{
    return TLA_PRIVADO . '/security/anun_limites.json';
}

function limites_anunciantes_padrao(): array
{
    return [
        'padrao' => [
            'permissoes'          => ['perfil:editar', 'publicar:eventos', 'publicar:lojas', 'publicar:ofertas', 'publicar:servicos'],
            'publicacoes_por_dia' => 20,
        ],
        'contas' => [],
    ];
}

function limites_anunciantes(): array
{
    $arq = arquivo_limites_anunciantes();
    if (!is_file($arq)) {
        com_trava($arq, function () use ($arq) {
            if (!is_file($arq)) json_gravar($arq, limites_anunciantes_padrao());
        });
    }
    return json_ler($arq, limites_anunciantes_padrao());
}

function eh_admin(array $conta): bool
{
    if ($conta['tipo'] !== 'usuario' || $conta['status'] !== 'ativo') return false;
    $lista = json_ler(TLA_PRIVADO . '/auth/usuarios.json', ['admins' => []])['admins'] ?? [];
    return in_array(mb_strtolower($conta['email']), array_map('mb_strtolower', (array)$lista), true);
}

function catalogo_permissoes(): array
{
    static $c = null;
    return $c ??= consulta('SELECT chave FROM permissoes ORDER BY chave')->fetchAll(PDO::FETCH_COLUMN);
}

/** [{chave, efeito, expira_em}] das permissões dadas/tiradas só desta conta. */
function individuais_da_conta(array $conta): array
{
    if ($conta['tipo'] === 'anunciante') {
        $lista = [];
        foreach (limites_anunciantes()['contas'][$conta['id']]['permissoes'] ?? [] as $chave => $p) {
            $lista[] = ['chave' => $chave, 'efeito' => $p['efeito'], 'expira_em' => $p['expira_em'] ?? null];
        }
        return $lista;
    }
    return consulta('SELECT p.chave, up.efeito, up.expira_em FROM usuario_permissoes up
                       JOIN permissoes p ON p.id = up.permissao_id WHERE up.usuario_id = ?', [$conta['id']])->fetchAll();
}

function permissoes_da_conta(array $conta): array
{
    static $cache = [];
    if (isset($cache[$conta['ref']])) return $cache[$conta['ref']];

    if ($conta['tipo'] === 'anunciante') {
        $base = limites_anunciantes()['padrao']['permissoes'] ?? [];
    } elseif (eh_admin($conta)) {
        $base = catalogo_permissoes();
    } else {
        $base = consulta('SELECT p.chave FROM usuario_permissoes_padrao pp JOIN permissoes p ON p.id = pp.permissao_id')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    $efetivas = array_fill_keys($base, true);
    foreach (individuais_da_conta($conta) as $i) {
        if ($i['expira_em'] !== null && strtotime((string)$i['expira_em']) <= time()) continue;
        if ($i['efeito'] === 'conceder') $efetivas[$i['chave']] = true;
        else unset($efetivas[$i['chave']]);
    }
    $lista = array_keys($efetivas);
    sort($lista);
    return $cache[$conta['ref']] = $lista;
}

function pode(array $conta, string $permissao): bool
{
    return in_array($permissao, permissoes_da_conta($conta), true);
}

function exigir_permissao(array $conta, string $permissao): void
{
    if (!pode($conta, $permissao)) falhar('Sua conta não tem permissão para isso.', 403);
}

/** Concede ou revoga uma permissão para uma conta. $expira = 'Y-m-d H:i:s' ou null. */
function definir_permissao(array $conta, string $chave, string $efeito, ?string $expira, string $porQuem): void
{
    if (!in_array($chave, catalogo_permissoes(), true)) throw new InvalidArgumentException("Permissão desconhecida: $chave");
    if ($conta['tipo'] === 'anunciante') {
        json_atualizar(arquivo_limites_anunciantes(), function (array &$l) use ($conta, $chave, $efeito, $expira, $porQuem) {
            $l['contas'][$conta['id']]['permissoes'][$chave] = [
                'efeito' => $efeito, 'expira_em' => $expira, 'por' => $porQuem, 'em' => agora(),
            ];
        }, limites_anunciantes());
        return;
    }
    consulta(
        'INSERT INTO usuario_permissoes (usuario_id, permissao_id, efeito, expira_em, concedido_por)
         SELECT ?, id, ?, ?, ? FROM permissoes WHERE chave = ?
         ON DUPLICATE KEY UPDATE efeito = VALUES(efeito), expira_em = VALUES(expira_em), concedido_por = VALUES(concedido_por)',
        [$conta['id'], $efeito, $expira, $porQuem, $chave]
    );
}

/** Volta a conta ao padrão do tipo para essa permissão. */
function remover_permissao_individual(array $conta, string $chave): void
{
    if ($conta['tipo'] === 'anunciante') {
        json_atualizar(arquivo_limites_anunciantes(), function (array &$l) use ($conta, $chave) {
            unset($l['contas'][$conta['id']]['permissoes'][$chave]);
        }, limites_anunciantes());
        return;
    }
    consulta('DELETE up FROM usuario_permissoes up JOIN permissoes p ON p.id = up.permissao_id
               WHERE up.usuario_id = ? AND p.chave = ?', [$conta['id'], $chave]);
}

/** Quantas publicações por dia o anunciante pode fazer (null = sem limite diário). */
function limite_publicacoes_dia(array $conta): ?int
{
    if ($conta['tipo'] !== 'anunciante') return null;
    $l = limites_anunciantes();
    $n = $l['contas'][$conta['id']]['publicacoes_por_dia'] ?? ($l['padrao']['publicacoes_por_dia'] ?? null);
    return $n === null ? null : (int)$n;
}

function definir_limite_publicacoes(array $conta, ?int $porDia): void
{
    json_atualizar(arquivo_limites_anunciantes(), function (array &$l) use ($conta, $porDia) {
        if ($porDia === null) unset($l['contas'][$conta['id']]['publicacoes_por_dia']);
        else $l['contas'][$conta['id']]['publicacoes_por_dia'] = $porDia;
    }, limites_anunciantes());
}
