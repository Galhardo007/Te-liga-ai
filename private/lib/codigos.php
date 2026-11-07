<?php
declare(strict_types=1);

function hash_codigo(string $codigo): string
{
    return hash_hmac('sha256', $codigo, (string)cfg('app.segredo'));
}

/** Cria um código novo de 6 números (anula os anteriores da mesma finalidade) e devolve o código em texto. */
function criar_codigo(int $usuarioId, string $finalidade): string
{
    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    consulta('UPDATE codigos_email SET usado_em = NOW() WHERE usuario_id = ? AND finalidade = ? AND usado_em IS NULL',
        [$usuarioId, $finalidade]);
    consulta('INSERT INTO codigos_email (usuario_id, finalidade, codigo_hash, expira_em) VALUES (?, ?, ?, ?)', [
        $usuarioId, $finalidade, hash_codigo($codigo),
        date('Y-m-d H:i:s', time() + 60 * (int)cfg('seguranca.codigo_minutos', 15)),
    ]);
    return $codigo;
}

/**
 * Confere o código. Cada erro gasta uma tentativa; passou do limite, o código morre.
 * Retorna true e marca como usado quando bate.
 */
function conferir_codigo(int $usuarioId, string $finalidade, string $codigo): bool
{
    $c = linha('SELECT * FROM codigos_email
                 WHERE usuario_id = ? AND finalidade = ? AND usado_em IS NULL
                 ORDER BY id DESC LIMIT 1', [$usuarioId, $finalidade]);
    if (!$c) return false;
    if (strtotime($c['expira_em']) < time() || (int)$c['tentativas'] >= (int)cfg('seguranca.codigo_tentativas', 5)) {
        consulta('UPDATE codigos_email SET usado_em = NOW() WHERE id = ?', [$c['id']]);
        return false;
    }
    if (!preg_match('/^\d{6}$/', $codigo) || !hash_equals($c['codigo_hash'], hash_codigo($codigo))) {
        consulta('UPDATE codigos_email SET tentativas = tentativas + 1 WHERE id = ?', [$c['id']]);
        return false;
    }
    consulta('UPDATE codigos_email SET usado_em = NOW() WHERE id = ?', [$c['id']]);
    return true;
}
