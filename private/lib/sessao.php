<?php
declare(strict_types=1);

// Sessões gravadas em private/sessions (fora do public_html). Guardam só a ref da conta ("u12" ou "c…").

function iniciar_sessao(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $pasta = TLA_PRIVADO . '/sessions';
    garantir_pasta($pasta);
    session_save_path($pasta);
    session_name('tla_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $ocioso = (int)cfg('seguranca.sessao_ociosa_min', 10080) * 60;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)$ocioso);
    // pasta própria: o PHP precisa limpar as sessões velhas sozinho (o cron do Debian só limpa a pasta padrão)
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '1000');
    session_start();

    if (isset($_SESSION['visto']) && time() - $_SESSION['visto'] > $ocioso) {
        encerrar_sessao();
        session_start();
    }
    $_SESSION['visto'] = time();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function entrar(array $conta): void
{
    session_regenerate_id(true);
    $_SESSION['conta'] = $conta['ref'];
    $_SESSION['versao'] = (int)$conta['sessao_versao'];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    conta_atualizar($conta, ['ultimo_login_em' => agora()]);
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

/** Conta da sessão atual (ou null). Troca de senha / bloqueio derruba a sessão na hora. */
function usuario_logado(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (empty($_SESSION['conta']) || !is_string($_SESSION['conta'])) return null;
    $c = conta_por_ref($_SESSION['conta']);
    if (!$c || $c['status'] !== 'ativo' || $c['sessao_versao'] !== (int)($_SESSION['versao'] ?? 0)) {
        unset($_SESSION['conta'], $_SESSION['versao']);
        return null;
    }
    return $cache = $c;
}

function exigir_login(): array
{
    $c = usuario_logado();
    if (!$c) falhar('Entre na sua conta para continuar.', 401);
    return $c;
}

/** Para POSTs de quem está logado: o front manda o token no cabeçalho X-CSRF-Token. */
function exigir_csrf(): void
{
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        falhar('Sessão expirada. Recarregue a página.', 419);
    }
}

/** O que pode ir para o navegador da própria pessoa (nunca senha_hash etc.). */
function usuario_publico(array $c): array
{
    $dados = [
        'id'         => $c['id'],
        'nome'       => $c['nome'],
        'email'      => $c['email'],
        'whatsapp'   => $c['whatsapp'],
        'cidade'     => $c['cidade'],
        'bio'        => $c['bio'],
        'tipo'       => $c['tipo'],
        'admin'      => eh_admin($c),
        'foto'       => $c['foto_perfil'],
        'permissoes' => permissoes_da_conta($c),
    ];
    if ($c['tipo'] === 'anunciante') {
        $dados['negocio'] = [
            'nome'       => $c['negocio']['nome'] ?? null,
            'categoria'  => $c['negocio']['categoria'] ?? null,
            'verificado' => !empty($c['negocio']['verificado_em']),
        ];
        $dados['publicacoes_por_dia'] = limite_publicacoes_dia($c);
    }
    return $dados;
}
