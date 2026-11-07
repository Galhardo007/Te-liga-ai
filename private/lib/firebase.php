<?php
declare(strict_types=1);

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/**
 * Token personalizado do Firebase Auth, assinado com a conta de serviço (sem SDK, só OpenSSL).
 * O navegador usa com signInWithCustomToken(). As claims viram request.auth.token.* nas regras.
 * https://firebase.google.com/docs/auth/admin/create-custom-tokens#create_custom_tokens_using_a_third-party_jwt_library
 */
function firebase_token_personalizado(string $uid, array $claims = []): string
{
    $arquivo = (string)cfg('firebase.conta_servico');
    $conta = is_file($arquivo) ? json_decode((string)file_get_contents($arquivo), true) : null;
    if (empty($conta['client_email']) || empty($conta['private_key'])) {
        throw new RuntimeException('Conta de serviço do Firebase não configurada.');
    }
    $agora = time();
    $cabecalho = ['alg' => 'RS256', 'typ' => 'JWT'];
    $corpo = [
        'iss'    => $conta['client_email'],
        'sub'    => $conta['client_email'],
        'aud'    => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
        'iat'    => $agora,
        'exp'    => $agora + 3600,
        'uid'    => $uid,
        'claims' => (object)$claims,
    ];
    $entrada = b64url(json_encode($cabecalho)) . '.' . b64url(json_encode($corpo));
    $chave = openssl_pkey_get_private($conta['private_key']);
    if (!$chave || !openssl_sign($entrada, $assinatura, $chave, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Não foi possível assinar o token do Firebase.');
    }
    return $entrada . '.' . b64url($assinatura);
}

function firebase_uid(int $usuarioId): string
{
    return 'u' . $usuarioId;
}
