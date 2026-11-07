<?php
declare(strict_types=1);

// Ponto de entrada de todo endpoint em public_html/php/*.php:
//   require dirname(__DIR__, 2) . '/private/bootstrap.php';

define('TLA_PRIVADO', __DIR__);

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"sucesso":false,"erro":"Servidor sem configuração (private/config.php)."}';
    exit;
}
$GLOBALS['TLA_CONFIG'] = require __DIR__ . '/config.php';

ini_set('display_errors', cfg('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-erros.log');
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/limites.php';
require __DIR__ . '/lib/sessao.php';
require __DIR__ . '/lib/permissoes.php';
require __DIR__ . '/lib/codigos.php';
require __DIR__ . '/lib/email.php';
require __DIR__ . '/lib/validacao.php';

set_exception_handler(function (Throwable $e) {
    error_log('[' . date('c') . '] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no servidor. Tente de novo em instantes.']);
});

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

/** Lê a configuração com "pontos": cfg('db.host'). */
function cfg(string $caminho, $padrao = null)
{
    $v = $GLOBALS['TLA_CONFIG'];
    foreach (explode('.', $caminho) as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) return $padrao;
        $v = $v[$p];
    }
    return $v;
}

iniciar_sessao();
