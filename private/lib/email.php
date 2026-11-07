<?php
declare(strict_types=1);

/** Envia e-mail (HTML + texto). Modo em config: smtp | mail | log. */
function enviar_email(string $para, string $assunto, string $texto, string $html): bool
{
    $modo = cfg('email.modo', 'log');
    $de = (string)cfg('email.de');
    $deNome = (string)cfg('email.de_nome', cfg('app.nome'));
    $limite = 'tla-' . bin2hex(random_bytes(8));
    $assuntoCod = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    $cabecalhos = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($deNome) . "?= <$de>",
        "To: <$para>",
        "Subject: $assuntoCod",
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url((string)cfg('app.url'), PHP_URL_HOST) ?: 'localhost') . '>',
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$limite\"",
    ];
    $corpo = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texto))
        . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$limite--\r\n";

    if (!pode_enviar_email($para)) {
        registrar_email($para, $assunto, $modo, false, 'limite de envio atingido');
        return false;
    }
    try {
        if ($modo === 'log') {
            registrar_email($para, $assunto, $modo, true, null, $texto);
            return true;
        }
        if ($modo === 'mail') {
            $extra = array_filter($cabecalhos, fn($h) => !preg_match('/^(To|Subject):/i', $h));
            $ok = mail($para, $assuntoCod, $corpo, implode("\r\n", $extra), '-f' . $de);
        } else {
            $ok = smtp_enviar($de, $para, implode("\r\n", $cabecalhos) . "\r\n\r\n" . $corpo);
        }
        registrar_email($para, $assunto, $modo, $ok);
        return $ok;
    } catch (Throwable $e) {
        error_log('[email] ' . $e->getMessage());
        registrar_email($para, $assunto, $modo, false, $e->getMessage());
        return false;
    }
}

/**
 * Freio de envio em private/data/emails_enviados.json: no máximo N por endereço por hora
 * e um teto por dia (o Gmail bloqueia a conta acima de ~500/dia).
 */
function pode_enviar_email(string $para): bool
{
    $porHora = (int)cfg('email.max_por_endereco_hora', 6);
    $porDia = (int)cfg('email.max_por_dia', 450);
    return json_atualizar(TLA_PRIVADO . '/data/emails_enviados.json', function (array &$d) use ($para, $porHora, $porDia): bool {
        $hoje = date('Y-m-d');
        $agora = time();
        if (($d['dia'] ?? '') !== $hoje) $d = ['dia' => $hoje, 'total' => 0, 'por_endereco' => []];
        foreach ($d['por_endereco'] as $k => $lista) {
            $lista = array_values(array_filter($lista, fn($t) => $agora - $t < 3600));
            if ($lista) $d['por_endereco'][$k] = $lista; else unset($d['por_endereco'][$k]);
        }
        $k = hash_hmac('sha256', mb_strtolower($para), (string)cfg('app.segredo'));
        if ($d['total'] >= $porDia || count($d['por_endereco'][$k] ?? []) >= $porHora) return false;
        $d['total']++;
        $d['por_endereco'][$k][] = $agora;
        return true;
    });
}

/** Histórico em private/logs/emails_enviados.json (uma linha JSON por envio). Códigos só aparecem no modo 'log' (testes). */
function registrar_email(string $para, string $assunto, string $modo, bool $ok, ?string $erro = null, ?string $texto = null): void
{
    $linha = ['quando' => date('c'), 'para' => $para, 'modo' => $modo, 'ok' => $ok,
        'assunto' => $modo === 'log' ? $assunto : preg_replace('/\b\d{6}\b/', '******', $assunto)];
    if ($erro !== null) $linha['erro'] = $erro;
    if ($texto !== null) $linha['texto'] = $texto;
    file_put_contents(TLA_PRIVADO . '/logs/emails_enviados.json',
        json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

/** Cliente SMTP mínimo (SSL/STARTTLS + AUTH LOGIN), sem bibliotecas externas. */
function smtp_enviar(string $de, string $para, string $mensagem): bool
{
    $s = cfg('email.smtp');
    $seg = $s['seguranca'] ?? 'ssl';
    $host = ($seg === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . (int)$s['porta'];
    $con = stream_socket_client($host, $errno, $errstr, 15, STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$con) throw new RuntimeException("SMTP: $errstr");
    stream_set_timeout($con, 15);

    $ler = function () use ($con): string {
        $r = '';
        while (($l = fgets($con, 515)) !== false) {
            $r .= $l;
            if (isset($l[3]) && $l[3] === ' ') break;
        }
        return $r;
    };
    $cmd = function (?string $c, int $esperado) use ($con, $ler): string {
        if ($c !== null) fwrite($con, $c . "\r\n");
        $r = $ler();
        if ((int)substr($r, 0, 3) !== $esperado) throw new RuntimeException('SMTP: ' . trim($r));
        return $r;
    };

    $nomeLocal = parse_url((string)cfg('app.url'), PHP_URL_HOST) ?: 'localhost';
    $cmd(null, 220);
    $cmd("EHLO $nomeLocal", 250);
    if ($seg === 'tls') {
        $cmd('STARTTLS', 220);
        if (!stream_socket_enable_crypto($con, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP: STARTTLS falhou');
        $cmd("EHLO $nomeLocal", 250);
    }
    $cmd('AUTH LOGIN', 334);
    $cmd(base64_encode($s['usuario']), 334);
    $cmd(base64_encode($s['senha']), 235);
    $cmd("MAIL FROM:<$de>", 250);
    $cmd("RCPT TO:<$para>", 250);
    $cmd('DATA', 354);
    $mensagem = preg_replace('/^\./m', '..', $mensagem); // dot-stuffing
    $cmd($mensagem . "\r\n.", 250);
    $cmd('QUIT', 221);
    fclose($con);
    return true;
}

function enviar_codigo_por_email(string $para, string $nome, string $codigo, string $finalidade): bool
{
    $app = (string)cfg('app.nome');
    $min = (int)cfg('seguranca.codigo_minutos', 15);
    $primeiroNome = htmlspecialchars(explode(' ', $nome)[0], ENT_QUOTES, 'UTF-8');
    if ($finalidade === 'redefinir_senha') {
        $assunto = "$codigo é seu código para trocar a senha — $app";
        $frase = 'Recebemos um pedido para trocar a senha da sua conta. Use o código abaixo:';
        $aviso = 'Se não foi você, ignore este e-mail: sua senha continua a mesma.';
    } else {
        $assunto = "$codigo é seu código de confirmação — $app";
        $frase = 'Falta pouco! Use o código abaixo para confirmar seu e-mail e ativar sua conta:';
        $aviso = 'Se você não criou uma conta, ignore este e-mail.';
    }
    $texto = "Olá, {$primeiroNome}!\n\n$frase\n\n$codigo\n\nO código vale por $min minutos.\n$aviso\n\n— $app";
    $html = "<div style=\"font-family:system-ui,Arial,sans-serif;max-width:480px;margin:auto;padding:24px;color:#0f0f0f\">"
        . "<p>Olá, <strong>{$primeiroNome}</strong>!</p><p>$frase</p>"
        . "<p style=\"font-size:32px;letter-spacing:8px;font-weight:700;text-align:center;background:#f5f5f8;border-radius:12px;padding:16px\">$codigo</p>"
        . "<p style=\"color:#555\">O código vale por $min minutos.<br>$aviso</p><p>— $app</p></div>";
    return enviar_email($para, $assunto, $texto, $html);
}
