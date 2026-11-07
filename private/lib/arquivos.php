<?php
declare(strict_types=1);

// Arquivos JSON dentro de private/: trava (flock) entre requisições simultâneas e gravação atômica (tmp + rename).

function garantir_pasta(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException("Sem permissão para criar $dir");
    }
}

function json_ler(string $arquivo, $padrao = [])
{
    if (!is_file($arquivo)) return $padrao;
    $t = file_get_contents($arquivo);
    if ($t === false || trim($t) === '') return $padrao;
    $d = json_decode($t, true);
    if (!is_array($d)) throw new RuntimeException("JSON inválido em $arquivo");
    return $d;
}

function json_gravar(string $arquivo, $dados): void
{
    garantir_pasta(dirname($arquivo));
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $tmp = $arquivo . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if ($json === false || file_put_contents($tmp, $json) === false) throw new RuntimeException("Falha ao gravar $arquivo");
    @chmod($tmp, 0600);
    if (!rename($tmp, $arquivo)) {
        @unlink($tmp);
        throw new RuntimeException("Falha ao gravar $arquivo");
    }
}

/** Roda $fn com trava exclusiva sobre $arquivo (usa o arquivo irmão .lock). */
function com_trava(string $arquivo, callable $fn)
{
    garantir_pasta(dirname($arquivo));
    $h = fopen($arquivo . '.lock', 'c');
    if (!$h || !flock($h, LOCK_EX)) throw new RuntimeException("Sem trava para $arquivo");
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Lê, muda e grava sob trava. $fn(array &$dados) pode devolver um valor. */
function json_atualizar(string $arquivo, callable $fn, array $padrao = [])
{
    return com_trava($arquivo, function () use ($arquivo, $fn, $padrao) {
        $d = json_ler($arquivo, $padrao);
        $r = $fn($d);
        json_gravar($arquivo, $d);
        return $r;
    });
}

/** Chave de 32 bytes guardada em base64. Criada na primeira vez. FAÇA BACKUP destes arquivos. */
function ler_chave(string $arquivo): string
{
    static $cache = [];
    if (isset($cache[$arquivo])) return $cache[$arquivo];
    $k = com_trava($arquivo, function () use ($arquivo) {
        if (!is_file($arquivo)) {
            file_put_contents($arquivo, base64_encode(random_bytes(32)));
            @chmod($arquivo, 0600);
        }
        return base64_decode(trim((string)file_get_contents($arquivo)), true);
    });
    if ($k === false || strlen($k) !== 32) throw new RuntimeException("Chave inválida em $arquivo");
    return $cache[$arquivo] = $k;
}
