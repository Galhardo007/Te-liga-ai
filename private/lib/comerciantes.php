<?php
declare(strict_types=1);

// Contas de comerciante/anunciante: fora do banco, em private/comerciantes/
//   contas.json         índice HMAC(e-mail) -> id (o e-mail não fica em texto aberto)
//   contas/{id}.json    dados da conta criptografados (AES-256-GCM) com chave.key
//   chave.key           chave da criptografia — sem ela as contas não abrem: FAÇA BACKUP
// private/security/anun_chave.key é a chave do HMAC do índice.

function dir_comerciantes(): string
{
    return TLA_PRIVADO . '/comerciantes';
}

function arquivo_indice_comerciantes(): string
{
    return dir_comerciantes() . '/contas.json';
}

function eh_id_comerciante($id): bool
{
    return is_string($id) && preg_match('/^c[0-9a-f]{16}$/', $id) === 1;
}

function novo_id_comerciante(): string
{
    return 'c' . bin2hex(random_bytes(8));
}

function indice_email(string $email): string
{
    return hash_hmac('sha256', mb_strtolower($email), ler_chave(TLA_PRIVADO . '/security/anun_chave.key'));
}

function arquivo_comerciante(string $id): string
{
    if (!eh_id_comerciante($id)) throw new InvalidArgumentException('Id de comerciante inválido');
    return dir_comerciantes() . "/contas/$id.json";
}

function cifrar_comerciante(array $c): array
{
    $iv = random_bytes(12);
    $tag = '';
    $json = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $cifrado = openssl_encrypt($json, 'aes-256-gcm', ler_chave(dir_comerciantes() . '/chave.key'), OPENSSL_RAW_DATA, $iv, $tag, $c['id']);
    if ($cifrado === false) throw new RuntimeException('Falha ao criptografar conta');
    return ['v' => 1, 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'dados' => base64_encode($cifrado)];
}

function decifrar_comerciante(string $id, array $env): array
{
    $json = openssl_decrypt(
        (string)base64_decode((string)($env['dados'] ?? '')), 'aes-256-gcm', ler_chave(dir_comerciantes() . '/chave.key'),
        OPENSSL_RAW_DATA, (string)base64_decode((string)($env['iv'] ?? '')), (string)base64_decode((string)($env['tag'] ?? '')), $id
    );
    $c = $json === false ? null : json_decode($json, true);
    if (!is_array($c)) throw new RuntimeException("Conta $id não abre (chave.key errada ou arquivo alterado)");
    return $c;
}

function comerciante_por_id(string $id): ?array
{
    if (!eh_id_comerciante($id)) return null;
    $arq = arquivo_comerciante($id);
    return is_file($arq) ? decifrar_comerciante($id, json_ler($arq)) : null;
}

function comerciante_por_email(string $email): ?array
{
    $id = json_ler(arquivo_indice_comerciantes())[indice_email($email)] ?? null;
    return $id ? comerciante_por_id($id) : null;
}

function comerciante_gravar(array $c): void
{
    $c['atualizado_em'] = agora();
    json_gravar(arquivo_comerciante($c['id']), cifrar_comerciante($c));
}

/** Muda a conta sob trava: $fn(array &$conta). Devolve a conta gravada. */
function comerciante_atualizar(string $id, callable $fn): array
{
    $arq = arquivo_comerciante($id);
    return com_trava($arq, function () use ($id, $arq, $fn) {
        if (!is_file($arq)) throw new RuntimeException("Conta $id não existe");
        $c = decifrar_comerciante($id, json_ler($arq));
        $fn($c);
        comerciante_gravar($c);
        return $c;
    });
}

function comerciante_apagar_arquivo(string $id): void
{
    $arq = arquivo_comerciante($id);
    @unlink($arq);
    @unlink($arq . '.lock');
}
