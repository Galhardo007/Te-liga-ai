<?php
declare(strict_types=1);

function iniciar_sessao(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('tla_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(cfg('seguranca.sessao_ociosa_min', 10080) * 60));
    session_start();

    $ocioso = cfg('seguranca.sessao_ociosa_min', 10080) * 60;
    if (isset($_SESSION['visto']) && time() - $_SESSION['visto'] > $ocioso) {
        encerrar_sessao();
        session_start();
    }
    $_SESSION['visto'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function entrar(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$usuario['id'];
    $_SESSION['versao'] = (int)$usuario['sessao_versao'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    consulta('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = ?', [$usuario['id']]);
}

function encerrar_sessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Usuário da sessão atual (ou null). Troca de senha / bloqueio derruba a sessão na hora. */
function usuario_logado(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (empty($_SESSION['uid'])) return null;
    $u = linha('SELECT * FROM usuarios WHERE id = ?', [$_SESSION['uid']]);
    if (!$u || $u['status'] !== 'ativo' || (int)$u['sessao_versao'] !== (int)($_SESSION['versao'] ?? 0)) {
        unset($_SESSION['uid'], $_SESSION['versao']);
        return null;
    }
    return $cache = $u;
}

function exigir_login(): array
{
    $u = usuario_logado();
    if (!$u) falhar('Entre na sua conta para continuar.', 401);
    return $u;
}

/** Para POSTs de quem está logado: o front manda o token no cabeçalho X-CSRF-Token. */
function exigir_csrf(): void
{
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        falhar('Sessão expirada. Recarregue a página.', 419);
    }
}

/** O que pode ir para o navegador (nunca senha_hash etc.). */
function usuario_publico(array $u): array
{
    $dados = [
        'id'         => (int)$u['id'],
        'nome'       => $u['nome'],
        'email'      => $u['email'],
        'whatsapp'   => $u['whatsapp'],
        'cidade'     => $u['cidade'],
        'bio'        => $u['bio'],
        'tipo'       => $u['tipo'],
        'foto'       => $u['foto_perfil'],
        'permissoes' => permissoes_do_usuario((int)$u['id'], $u['tipo']),
    ];
    if ($u['tipo'] === 'anunciante') {
        $a = linha('SELECT nome_negocio, categoria, verificado_em FROM anunciantes WHERE usuario_id = ?', [$u['id']]);
        $dados['negocio'] = [
            'nome'       => $a['nome_negocio'] ?? null,
            'categoria'  => $a['categoria'] ?? null,
            'verificado' => !empty($a['verificado_em']),
        ];
    }
    return $dados;
}
