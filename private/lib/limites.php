<?php
declare(strict_types=1);

/**
 * Conta tentativas por chave dentro de uma janela de tempo.
 * Retorna false quando passou do limite (o endpoint deve responder 429).
 */
function dentro_do_limite(string $acao, string $chave, int $max, int $janelaSeg): bool
{
    $id = hash('sha256', $acao . '|' . $chave);
    $agora = time();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $r = linha('SELECT contagem, janela_inicio FROM limites WHERE chave = ? FOR UPDATE', [$id]);
        if (!$r || $agora - (int)$r['janela_inicio'] >= $janelaSeg) {
            consulta('REPLACE INTO limites (chave, contagem, janela_inicio) VALUES (?, 1, ?)', [$id, $agora]);
            $pdo->commit();
            return true;
        }
        if ((int)$r['contagem'] >= $max) {
            $pdo->commit();
            return false;
        }
        consulta('UPDATE limites SET contagem = contagem + 1 WHERE chave = ?', [$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function exigir_limite(string $acao, string $chave, int $max, int $janelaSeg): void
{
    if (!dentro_do_limite($acao, $chave, $max, $janelaSeg)) {
        falhar('Muitas tentativas seguidas. Aguarde um pouco e tente de novo.', 429);
    }
}

function zerar_limite(string $acao, string $chave): void
{
    consulta('DELETE FROM limites WHERE chave = ?', [hash('sha256', $acao . '|' . $chave)]);
}
