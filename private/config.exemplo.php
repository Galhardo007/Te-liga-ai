<?php
// Copie para config.php e preencha. config.php NUNCA vai para o Git nem para o public_html.
return [
    'app' => [
        'nome'    => 'Te Liga Aí',
        'url'     => 'https://seudominio.com.br',
        // Gere com: php -r "echo bin2hex(random_bytes(32));"
        'segredo' => 'TROQUE-POR-64-CARACTERES-ALEATORIOS',
        'debug'   => false,
    ],

    'db' => [
        'host'    => 'localhost',
        'porta'   => 3306,
        'nome'    => 'teligaai',
        'usuario' => 'teligaai',
        'senha'   => '',
    ],

    'email' => [
        // 'smtp' (recomendado), 'mail' (função mail() do PHP) ou 'log' (só grava em private/logs/emails_enviados.json, para testes)
        'modo'    => 'smtp',
        'de'      => 'nao-responda@seudominio.com.br',
        'de_nome' => 'Te Liga Aí',
        // freio anti-spam (o Gmail bloqueia acima de ~500 e-mails por dia)
        'max_por_dia'           => 450,
        'max_por_endereco_hora' => 6,
        'smtp'    => [
            'host'      => 'smtp.hostinger.com',
            'porta'     => 465,
            'seguranca' => 'ssl', // 'ssl' (porta 465) ou 'tls' (STARTTLS, porta 587)
            'usuario'   => 'nao-responda@seudominio.com.br',
            'senha'     => '',
        ],
    ],

    'firebase' => [
        // Configuração Web do app (Console Firebase > Configurações do projeto > Seus apps). É pública por natureza.
        'web' => [
            'apiKey'            => '',
            'authDomain'        => 'seu-projeto.firebaseapp.com',
            'projectId'         => 'seu-projeto',
            'storageBucket'     => 'seu-projeto.appspot.com',
            'messagingSenderId' => '',
            'appId'             => '',
        ],
        // Chave da conta de serviço (Configurações do projeto > Contas de serviço > Gerar nova chave). SECRETA.
        'conta_servico' => __DIR__ . '/firebase/conta-servico.json',
    ],

    'uploads' => [
        'dir'        => dirname(__DIR__) . '/public_html/uploads',
        'url'        => '/uploads',
        'max_bytes'  => 5 * 1024 * 1024,
        'max_fotos'  => 8,
        'max_lado'   => 1600,
    ],

    'seguranca' => [
        'codigo_minutos'    => 15,
        'codigo_tentativas' => 5,
        'sessao_ociosa_min' => 60 * 24 * 7,
    ],
];
