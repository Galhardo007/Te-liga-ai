<?php
declare(strict_types=1);

// Uma "conta" tem sempre os mesmos campos, more ela no MySQL (usuário) ou em arquivo (anunciante):
//   id   (número para usuário, "c…" para anunciante)
//   ref  ("u12" ou "c…": é o que vai na sessão, nos códigos e no Firebase)
//   tipo ('usuario' | 'anunciante'), nome, email, whatsapp, cidade, bio, foto_perfil,
//   senha_hash, status ('pendente'|'ativo'|'bloqueado'), sessao_versao
// O e-mail é único entre os dois tipos.

function conta_de_usuario(?array $u): ?array
{
    if (!$u) return null;
    $u['id'] = (int)$u['id'];
    $u['ref'] = 'u' . $u['id'];
    $u['tipo'] = 'usuario';
    $u['sessao_versao'] = (int)$u['sessao_versao'];
    return $u;
}

function conta_de_comerciante(?array $c): ?array
{
    if (!$c) return null;
    $c['ref'] = $c['id'];
    $c['tipo'] = 'anunciante';
    $c['sessao_versao'] = (int)$c['sessao_versao'];
    return $c;
}

function conta_por_ref(string $ref): ?array
{
    if (eh_id_comerciante($ref)) return conta_de_comerciante(comerciante_por_id($ref));
    if (preg_match('/^u([1-9]\d{0,18})$/', $ref, $m)) {
        return conta_de_usuario(linha('SELECT * FROM usuarios WHERE id = ?', [$m[1]]));
    }
    return null;
}

/** Aceita 12, "12", "u12" ou "c…". */
function conta_por_id($id): ?array
{
    if (is_int($id) || (is_string($id) && ctype_digit($id))) return conta_por_ref('u' . (int)$id);
    return is_string($id) ? conta_por_ref($id) : null;
}

function conta_por_email(string $email): ?array
{
    return conta_de_comerciante(comerciante_por_email($email))
        ?? conta_de_usuario(linha('SELECT * FROM usuarios WHERE email = ?', [$email]));
}

const CAMPOS_CONTA = ['nome', 'whatsapp', 'cidade', 'bio', 'foto_perfil', 'senha_hash', 'status', 'email_confirmado_em', 'ultimo_login_em'];

/** Atualiza campos da conta. $novaVersao = derruba as outras sessões. Devolve a conta atualizada. */
function conta_atualizar(array $conta, array $campos, bool $novaVersao = false): array
{
    foreach (array_keys($campos) as $k) {
        if (!in_array($k, CAMPOS_CONTA, true)) throw new InvalidArgumentException("Campo $k");
    }
    if ($conta['tipo'] === 'anunciante') {
        return conta_de_comerciante(comerciante_atualizar($conta['id'], function (array &$c) use ($campos, $novaVersao) {
            foreach ($campos as $k => $v) $c[$k] = $v;
            if ($novaVersao) $c['sessao_versao'] = (int)$c['sessao_versao'] + 1;
        }));
    }
    $sets = [];
    $vals = [];
    foreach ($campos as $k => $v) {
        $sets[] = "$k = ?";
        $vals[] = $v;
    }
    if ($novaVersao) $sets[] = 'sessao_versao = sessao_versao + 1';
    if ($sets) {
        $vals[] = $conta['id'];
        consulta('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
    }
    return conta_por_ref($conta['ref']);
}

/**
 * Cria a conta pendente (ou refaz uma pendente nunca confirmada, até trocando o tipo).
 * Devolve null se o e-mail já tem conta confirmada/bloqueada.
 * Usa a trava do índice de comerciantes para que dois cadastros do mesmo e-mail nunca passem juntos.
 */
function conta_cadastrar(string $tipo, array $d): ?array
{
    $indice = arquivo_indice_comerciantes();
    return com_trava($indice, function () use ($tipo, $d, $indice) {
        $email = $d['email'];
        $h = indice_email($email);
        $idx = json_ler($indice);
        $com = isset($idx[$h]) ? comerciante_por_id($idx[$h]) : null;
        $usu = linha('SELECT id, status FROM usuarios WHERE email = ?', [$email]);
        if (($com && $com['status'] !== 'pendente') || ($usu && $usu['status'] !== 'pendente')) return null;

        if ($com && $tipo !== 'anunciante') {
            comerciante_apagar_arquivo($com['id']);
            unset($idx[$h]);
            json_gravar($indice, $idx);
            $com = null;
        }
        if ($usu && $tipo !== 'usuario') {
            consulta("DELETE FROM usuarios WHERE id = ? AND status = 'pendente'", [$usu['id']]);
            $usu = null;
        }

        if ($tipo === 'usuario') {
            if ($usu) {
                consulta('UPDATE usuarios SET nome = ?, whatsapp = ?, cidade = ?, senha_hash = ?, aceite_termos_em = NOW() WHERE id = ?',
                    [$d['nome'], $d['whatsapp'], $d['cidade'], $d['senha_hash'], $usu['id']]);
                return conta_por_ref('u' . $usu['id']);
            }
            consulta("INSERT INTO usuarios (nome, email, whatsapp, cidade, senha_hash, status, aceite_termos_em)
                      VALUES (?, ?, ?, ?, ?, 'pendente', NOW())",
                [$d['nome'], $email, $d['whatsapp'], $d['cidade'], $d['senha_hash']]);
            return conta_por_ref('u' . db()->lastInsertId());
        }

        $agora = agora();
        $c = $com ?? [
            'id'                  => novo_id_comerciante(),
            'bio'                 => null,
            'foto_perfil'         => null,
            'fotos'               => [],
            'negocio'             => ['nome' => null, 'categoria' => null, 'documento' => null, 'verificado_em' => null],
            'sessao_versao'       => 1,
            'email_confirmado_em' => null,
            'ultimo_login_em'     => null,
            'criado_em'           => $agora,
        ];
        $c = array_merge($c, [
            'nome'             => $d['nome'],
            'email'            => $email,
            'whatsapp'         => $d['whatsapp'],
            'cidade'           => $d['cidade'],
            'senha_hash'       => $d['senha_hash'],
            'status'           => 'pendente',
            'aceite_termos_em' => $agora,
        ]);
        comerciante_gravar($c);
        $idx[$h] = $c['id'];
        json_gravar($indice, $idx);
        return conta_de_comerciante($c);
    });
}

/** Pasta e URL das fotos da conta: uploads/u/{id} ou uploads/c/{id}. */
function pasta_fotos(array $conta): array
{
    $sub = ($conta['tipo'] === 'anunciante' ? 'c/' : 'u/') . $conta['id'];
    return [
        rtrim((string)cfg('uploads.dir'), '/') . '/' . $sub,
        rtrim((string)cfg('uploads.url'), '/') . '/' . $sub,
    ];
}

/** [{id, url}] da galeria. */
function fotos_da_conta(array $conta): array
{
    if ($conta['tipo'] === 'anunciante') {
        return array_map(fn($f) => ['id' => $f['id'], 'url' => $f['url']], $conta['fotos'] ?? []);
    }
    return array_map(fn($f) => ['id' => (int)$f['id'], 'url' => $f['arquivo']],
        consulta('SELECT id, arquivo FROM usuario_fotos WHERE usuario_id = ? ORDER BY id', [$conta['id']])->fetchAll());
}
