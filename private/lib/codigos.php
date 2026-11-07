<?php
declare(strict_types=1);

// Códigos de 6 números (confirmar e-mail / trocar senha) em private/data/codigos_recuperacao.json.
// Só o hash fica gravado; cada conta tem no máximo 1 código vivo por finalidade.

function arquivo_codigos(): string
{
    return TLA_PRIVADO . '/data/codigos_recuperacao.json';
}

function hash_codigo(string $ref, string $finalidade, string $codigo): string
{
    return hash_hmac('sha256', "$ref|$finalidade|$codigo", (string)cfg('app.segredo'));
}

/** Cria um código novo (anula o anterior da mesma finalidade) e devolve o código em texto. */
function criar_codigo(string $ref, string $finalidade): string
{
    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    json_atualizar(arquivo_codigos(), function (array &$t) use ($ref, $finalidade, $codigo) {
        $agora = time();
        foreach ($t as $k => $c) {
            if (($c['expira'] ?? 0) < $agora) unset($t[$k]);
        }
        $t["$ref|$finalidade"] = [
            'hash'       => hash_codigo($ref, $finalidade, $codigo),
            'expira'     => $agora + 60 * (int)cfg('seguranca.codigo_minutos', 15),
            'tentativas' => 0,
        ];
    });
    return $codigo;
}

/** Cada erro gasta uma tentativa; passou do limite ou venceu, o código morre. Acertou: true e o código é apagado. */
function conferir_codigo(string $ref, string $finalidade, string $codigo): bool
{
    return json_atualizar(arquivo_codigos(), function (array &$t) use ($ref, $finalidade, $codigo): bool {
        $k = "$ref|$finalidade";
        $c = $t[$k] ?? null;
        if (!$c) return false;
        $max = (int)cfg('seguranca.codigo_tentativas', 5);
        if ($c['expira'] < time() || $c['tentativas'] >= $max) {
            unset($t[$k]);
            return false;
        }
        if (!preg_match('/^\d{6}$/', $codigo) || !hash_equals($c['hash'], hash_codigo($ref, $finalidade, $codigo))) {
            $t[$k]['tentativas']++;
            if ($t[$k]['tentativas'] >= $max) unset($t[$k]);
            return false;
        }
        unset($t[$k]);
        return true;
    });
}
