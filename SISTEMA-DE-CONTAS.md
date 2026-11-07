# Sistema de contas — Te Liga Aí

Cadastro com dois tipos de conta (**usuário** no MySQL e **anunciante/comerciante** em arquivos criptografados em `private/`), permissões por tipo e por pessoa,
confirmação de e-mail por código, "esqueci a senha" por código, página de perfil com fotos e login no chat Firebase.
PHP puro (8.0+), MySQL/MariaDB e JavaScript puro. Sem Composer.

## Estrutura no servidor

```
/home/SEU_USUARIO/
├── private/                    ← FORA da web: nada aqui pode ser aberto pelo navegador
│   ├── config.php              ← banco, segredo, Firebase (copie de config.exemplo.php)
│   ├── bootstrap.php  lib/     ← carregados por todo endpoint
│   ├── auth/
│   │   ├── usuarios.json       ← e-mails dos admins (copie de usuarios.exemplo.json)
│   │   └── smtp.json           ← SMTP (opcional; também aceito em comerciantes/smtp.json)
│   ├── comerciantes/           ← CONTAS DE ANUNCIANTE (fora do banco)
│   │   ├── chave.key           ← chave AES-256 que criptografa as contas — FAÇA BACKUP
│   │   ├── contas.json         ← índice: HMAC do e-mail -> id (e-mail não fica em texto aberto)
│   │   └── contas/{id}.json    ← dados de cada anunciante, criptografados
│   ├── security/
│   │   ├── anun_limites.json   ← permissões e limite de publicações por dia dos anunciantes
│   │   └── anun_chave.key      ← chave do HMAC do índice — FAÇA BACKUP
│   ├── data/
│   │   ├── codigos_recuperacao.json  ← códigos de confirmação/troca de senha (só hash, 15 min)
│   │   └── emails_enviados.json      ← freio anti-spam (por endereço/hora e total/dia)
│   ├── logs/  emails_enviados.json (histórico de envios)  php_erros.log
│   ├── sessions/               ← sessões de login do PHP
│   ├── db/schema.sql           ← tabelas do MySQL
│   └── firebase/conta-servico.json  ← chave da conta de serviço (secreta)
└── public_html/                ← o site
    ├── cadastro.html  esqueci-senha.html  perfil.html  chat.html  php-pages/auth.html
    ├── php/*.php               ← endpoints JSON (só fazem require do ../../private/bootstrap.php)
    └── uploads/u/{id}/  uploads/c/{id}/   ← fotos de usuários / anunciantes
```

**O que fica onde**

- **MySQL**: só contas de **usuário** e o que acontece no site: feed (`publicacoes`), `curtidas`, `comentarios`, `compartilhamentos`, permissões dos usuários e a tabela `limites` (anti-robô, só hashes).
- **Arquivos em `private/`**: contas de **anunciante**, regras dos anunciantes, admins, códigos, SMTP, sessões e logs.
- `chave.key` e `anun_chave.key` são criadas sozinhas no primeiro cadastro. Sem elas as contas de anunciante não abrem: guarde uma cópia fora do servidor.

## Instalação

1. Envie `private/` para **ao lado** de `public_html/` (não dentro).
2. Crie o banco no painel da hospedagem e importe `private/db/schema.sql` (phpMyAdmin > Importar).
   Se importou a versão anterior, apague as tabelas antigas antes.
3. Copie `private/config.exemplo.php` para `private/config.php` e preencha banco, `app.segredo`
   (`php -r "echo bin2hex(random_bytes(32));"`) e a configuração Web do Firebase.
4. SMTP: crie `private/auth/smtp.json` (modelo em `smtp.exemplo.json`). No Gmail use `smtp.gmail.com`, porta 587
   e uma **Senha de app** (Conta Google > Segurança > Verificação em 2 etapas > Senhas de app), não a senha normal.
5. Admins: crie `private/auth/usuarios.json` com `{"admins": ["seu-email@gmail.com"]}`. O admin é uma conta de usuário normal com esse e-mail.
6. Firebase: Console > Configurações do projeto > Contas de serviço > **Gerar nova chave** → salve como
   `private/firebase/conta-servico.json`. Ative o Firestore e publique `firebase/firestore.rules`.
7. O PHP precisa gravar em `private/` (comerciantes, security, data, logs, sessions) e em `public_html/uploads/`.
8. Teste local: `cd public_html && php -S 127.0.0.1:8080` (com `email.modo = 'log'` os códigos aparecem em `private/logs/emails_enviados.json`).

## Tipos de conta e permissões

| Permissão | usuário | anunciante | admin |
|---|:-:|:-:|:-:|
| perfil:editar | ✓ | ✓ | ✓ |
| chat:usar, comentar, curtir, compartilhar, publicar:avaliacoes | ✓ | – | ✓ |
| publicar:lojas, publicar:ofertas, publicar:servicos, publicar:eventos | – | ✓ | ✓ |
| admin:usuarios, admin:permissoes | – | – | ✓ |

- O tipo é escolhido no cadastro (`usuario` ou `anunciante`); ninguém vira admin pelo formulário.
- Padrão dos usuários: tabela `usuario_permissoes_padrao`. Padrão dos anunciantes: `padrao` em `security/anun_limites.json`.
- **Permissões específicas por pessoa** (vale na hora, para os dois tipos): `POST /php/admin.php`
  `{ "acao":"permissao", "email":"loja@x.com", "permissao":"publicar:eventos", "efeito":"conceder", "expira_em":"2027-01-31 23:59:59" }`
  (`efeito`: `conceder`, `revogar` ou `padrao`). Também aceita `"usuario_id": 12` (usuário) ou `"usuario_id": "c…"` (anunciante).
- **Limite de publicações por dia do anunciante**: `{ "acao":"limite", "email":"loja@x.com", "publicacoes_por_dia": 50 }`.
- **Nova seção para publicar**: `INSERT INTO secoes (slug,nome) VALUES ('vagas','Vagas de emprego');`
  `INSERT INTO permissoes (chave,descricao) VALUES ('publicar:vagas','Publicar vagas');` e coloque `publicar:vagas`
  no padrão de quem deve publicar.
- No PHP: `exigir_permissao($conta, 'publicar:lojas');` — veja `public_html/php/publicar.php`.

## Endpoints

| Endpoint | Entrada | Observação |
|---|---|---|
| `POST /php/cadastro.php` | nome, email, whatsapp, cidade, senha, tipo, aceite | envia código de confirmação |
| `POST /php/confirmar.php` | email, codigo | ativa a conta e já entra |
| `POST /php/reenviar-codigo.php` | email | 1 por minuto |
| `POST /php/login.php` | email, senha | 403 + `confirmacao:true` se não confirmou |
| `POST /php/logout.php` | – | |
| `GET  /php/sessao.php` | – | `{ logado, usuario, csrf }` |
| `POST /php/esqueci-senha.php` | email | sempre responde sucesso (não revela quem tem conta) |
| `POST /php/redefinir-senha.php` | email, codigo, senha | derruba todas as sessões |
| `GET/POST /php/perfil.php` | nome, whatsapp, cidade, bio, negocio_* | logado + CSRF |
| `POST /php/fotos.php` | multipart foto + destino (perfil/galeria) ou JSON `{acao:"remover",id}` | |
| `POST /php/alterar-senha.php` | senha_atual, senha_nova | |
| `GET/POST /php/publicar.php` | secao, titulo, texto | exige `publicar:<secao>`; GET pagina com `antes` |
| `GET/POST /php/interagir.php` | acao curtir/descurtir/comentar/apagar_comentario/compartilhar | só usuários |
| `GET  /php/usuario-publico.php?id=` | – | perfil público (`12` ou `c…`) |
| `POST /php/firebase-token.php` | – | token do chat (só usuários) |
| `POST /php/admin.php` | acao ver/permissao/status/limite | só admin |

POSTs de quem está logado mandam o cabeçalho `X-CSRF-Token` (o `js/api.js` já faz isso).

## Segurança já incluída

- Senhas com Argon2id/bcrypt; códigos de 6 números guardados só como HMAC, valem 15 min e morrem após 5 erros.
- Contas de anunciante criptografadas (AES-256-GCM) e gravadas com trava + troca atômica do arquivo (sem corromper com acessos simultâneos).
- Freio de envio de e-mail para não estourar o limite diário do Gmail.
- Limite de tentativas em login, cadastro, códigos e uploads (tabela `limites`).
- Sessão com cookie HttpOnly/SameSite, novo ID no login; trocar senha ou bloquear a conta derruba todas as sessões.
- Bloqueio de POST de outros sites (Origin) + token CSRF; todas as consultas com prepared statements.
- Uploads: confere o conteúdo real, regrava a imagem (remove EXIF/GPS e código escondido), nome aleatório.
- Chat: o Firebase só aceita quem o PHP autenticou; regras do Firestore limitam cada conversa aos dois participantes.
