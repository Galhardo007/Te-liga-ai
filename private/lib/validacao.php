<?php
declare(strict_types=1);

const TIPOS_CADASTRO = ['usuario', 'anunciante']; // 'admin' nunca vem do formulário

function so_numeros($v): string
{
    return preg_replace('/\D+/', '', is_scalar($v) ? (string)$v : '') ?? '';
}

function validar_nome(string $nome): ?string
{
    if (mb_strlen($nome) < 2) return 'Digite seu nome.';
    if (!preg_match('/\p{L}/u', $nome)) return 'O nome precisa ter letras.';
    return null;
}

function validar_email(string $email): ?string
{
    if (mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Digite um e-mail válido, como nome@email.com.';
    }
    return null;
}

function validar_whatsapp(string $n): ?string
{
    if (strlen($n) < 10 || strlen($n) > 11 || $n[0] === '0') {
        return 'Digite o WhatsApp com DDD, por exemplo (51) 99999-9999.';
    }
    return null;
}

function validar_senha($senha): ?string
{
    if (!is_string($senha) || strlen($senha) < 8) return 'A senha precisa ter pelo menos 8 caracteres.';
    if (strlen($senha) > 128) return 'A senha pode ter no máximo 128 caracteres.';
    return null;
}

function gerar_hash_senha(string $senha): string
{
    return password_hash($senha, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
}
