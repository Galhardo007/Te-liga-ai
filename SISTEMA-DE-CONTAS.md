# Sistema de contas — Te Liga Aí

Cadastro com dois tipos de conta (**usuário** e **anunciante/comerciante**), permissões por tipo e por pessoa,
confirmação de e-mail por código, "esqueci a senha" por código, página de perfil com fotos e login no chat Firebase.
PHP puro (8.0+), MySQL/MariaDB e JavaScript puro. Sem Composer.

## Estrutura no servidor

```
/home/SEU_USUARIO/
├── private/              ← FORA da web: nada aqui pode ser aberto pelo navegador
│   ├── config.php        ← senhas do banco, SMTP, segredo (copie de config.exemplo.php)
│   ├── bootstrap.php     ← carregado por todo endpoint
│   ├── lib/              ← sessão, permissões, códigos, e-mail, Firebase…
│   ├── firebase/conta-servico.json  ← chave da conta de serviço (secreta)
│   ├── sql/schema.sql    ← tabelas + permissões iniciais
│   └── logs/             ← erros do PHP (e e-mails no modo 'log')
└── public_html/          ← o site
    ├── cadastro.html  esqueci-senha.html  perfil.html  chat.html
    ├── php-pages/auth.html   (login)
    ├── php/*.php         ← endpoints JSON (só fazem require do ../../private/bootstrap.php)
    ├── css/ js/
    └── uploads/u/{id}/   ← fotos (regravadas pelo PHP; .htaccess impede executar qualquer coisa)
```

## Instalação

1. Envie `private/` para **ao lado** de `public_html/` (não dentro).
2. Crie o banco no painel da hospedagem e importe `private/sql/schema.sql` (phpMyAdmin > Importar).
3. Copie `private/config.exemplo.php` para `private/config.php` e preencha banco, SMTP, `app.segredo`
   - Opcional: o SMTP pode ficar em `private/auth/smtp.json` (copie de `smtp.exemplo.json`); se existir, ele substitui o SMTP do `config.php`. No Gmail use `smtp.gmail.com`, porta 587 e uma **Senha de app** (Conta Google > Segurança > Verificação em 2 etapas > Senhas de app), não a senha normal
   (`php -r "echo bin2hex(random_bytes(32));"`) e a configuração Web do Firebase.
4. Firebase: Console > Configurações do projeto > Contas de serviço > **Gerar nova chave** → salve como
   `private/firebase/conta-servico.json`. Ative o Firestore e publique `firebase/firestore.rules`.
5. Garanta que o PHP consegue gravar em `public_html/uploads/u/` e `private/logs/`.
6. Teste local: `cd public_html && php -S 127.0.0.1:8080` (com `email.modo = 'log'` os códigos aparecem em `private/logs/emails.log`).

## Tipos de conta e permissões

| Permissão | usuário | anunciante | admin |
|---|:-:|:-:|:-:|
| perfil:editar, chat:usar, publicar:comentarios | ✓ | ✓ | ✓ |
| publicar:avaliacoes | ✓ | – | ✓ |
| publicar:lojas, publicar:ofertas, publicar:servicos, publicar:eventos | – | ✓ | ✓ |
| admin:usuarios, admin:permissoes | – | – | ✓ |

- O tipo é escolhido no cadastro; `admin` nunca pode vir do formulário (vire admin pelo banco:
  `UPDATE usuarios SET tipo='admin' WHERE email='voce@...'`).
- **Permissões específicas por pessoa**: `POST /php/admin.php`
  `{ "acao":"permissao", "usuario_id":12, "permissao":"publicar:eventos", "efeito":"conceder", "expira_em":"2027-01-31 23:59:59" }`
  (`efeito`: `conceder`, `revogar` ou `padrao` para voltar ao pacote do tipo). Vale na hora.
- **Nova seção para publicar**: `INSERT INTO secoes (slug,nome) VALUES ('vagas','Vagas de emprego');`
  `INSERT INTO permissoes (chave,descricao) VALUES ('publicar:vagas','Publicar vagas');` e ligue ao tipo em `tipo_permissoes`.
- No PHP: `exigir_permissao($usuario, 'publicar:lojas');` — veja `public_html/php/publicar.php`.

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
| `GET/POST /php/publicar.php` | secao, titulo, texto | exige `publicar:<secao>` |
| `GET  /php/usuario-publico.php?id=` | – | perfil público |
| `POST /php/firebase-token.php` | – | token do chat |
| `POST /php/admin.php` | acao ver/permissao/status | só admin |

POSTs de quem está logado mandam o cabeçalho `X-CSRF-Token` (o `js/api.js` já faz isso).

## Segurança já incluída

- Senhas com Argon2id/bcrypt; códigos de 6 números guardados só como HMAC, valem 15 min e morrem após 5 erros.
- Limite de tentativas em login, cadastro, códigos e uploads (tabela `limites`).
- Sessão com cookie HttpOnly/SameSite, novo ID no login; trocar senha ou bloquear a conta derruba todas as sessões.
- Bloqueio de POST de outros sites (Origin) + token CSRF; todas as consultas com prepared statements.
- Uploads: confere o conteúdo real, regrava a imagem (remove EXIF/GPS e código escondido), nome aleatório.
- Chat: o Firebase só aceita quem o PHP autenticou; regras do Firestore limitam cada conversa aos dois participantes.
