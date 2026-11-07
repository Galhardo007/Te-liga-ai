<?php
declare(strict_types=1);

function responder(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function falhar(string $erro, int $status = 400, ?string $campo = null, array $extra = []): void
{
    $r = ['sucesso' => false, 'erro' => $erro];
    if ($campo !== null) $r['campo'] = $campo;
    responder($r + $extra, $status);
}

function exigir_metodo(string ...$metodos): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $metodos, true)) {
        header('Allow: ' . implode(', ', $metodos));
        falhar('Método não permitido.', 405);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') exigir_mesma_origem();
}

/** Bloqueia POST vindo de outros sites (CSRF). */
function exigir_mesma_origem(): void
{
    $origem = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origem === '') return; // navegadores modernos sempre mandam Origin em fetch POST
    $hostOrigem = parse_url($origem, PHP_URL_HOST);
    $porta = parse_url($origem, PHP_URL_PORT);
    if ($porta) $hostOrigem .= ':' . $porta;
    if (!hash_equals(strtolower($_SERVER['HTTP_HOST'] ?? ''), strtolower((string)$hostOrigem))) {
        falhar('Origem não permitida.', 403);
    }
}

/** Corpo JSON da requisição como array. */
function ler_json(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== 0) falhar('Envie os dados em JSON.', 415);
    $bruto = file_get_contents('php://input', false, null, 0, 65536);
    $dados = json_decode((string)$bruto, true);
    if (!is_array($dados)) falhar('Dados inválidos.', 400);
    return $dados;
}

function texto($v, int $max = 0): string
{
    $s = is_scalar($v) ? (string)$v : '';
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return $max > 0 ? mb_substr($s, 0, $max) : $s;
}

function ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}
