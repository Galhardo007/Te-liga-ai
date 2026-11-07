-- Te Liga Aí — banco de dados (MySQL 5.7+/MariaDB 10.3+)
-- Importe pelo phpMyAdmin ou: mysql -u USUARIO -p BANCO < schema.sql
-- O banco guarda só as contas de USUÁRIO e o que acontece no site (feed, curtidas, comentários, compartilhamentos).
-- Contas de comerciante/anunciante ficam fora do banco, em private/comerciantes/ (criptografadas).

SET NAMES utf8mb4;

-- ─── usuários ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome                VARCHAR(80)  NOT NULL,
  email               VARCHAR(120) NOT NULL,
  whatsapp            VARCHAR(11)  NOT NULL,
  cidade              VARCHAR(80)  NOT NULL,
  bio                 VARCHAR(500) NULL,
  foto_perfil         VARCHAR(255) NULL,
  senha_hash          VARCHAR(255) NOT NULL,
  status              ENUM('pendente','ativo','bloqueado') NOT NULL DEFAULT 'pendente',
  sessao_versao       INT UNSIGNED NOT NULL DEFAULT 1,
  email_confirmado_em DATETIME NULL,
  aceite_termos_em    DATETIME NOT NULL,
  ultimo_login_em     DATETIME NULL,
  criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email),
  KEY ix_usuarios_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_fotos (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  arquivo    VARCHAR(255) NOT NULL,
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_fotos_usuario (usuario_id),
  CONSTRAINT fk_fotos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── permissões dos usuários ──────────────────────────────
-- permissoes = catálogo de todas as chaves (vale também para anunciantes, cujas regras ficam em security/anun_limites.json)
CREATE TABLE IF NOT EXISTS permissoes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chave     VARCHAR(64)  NOT NULL,
  descricao VARCHAR(160) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissoes_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pacote que todo usuário recebe
CREATE TABLE IF NOT EXISTS usuario_permissoes_padrao (
  permissao_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (permissao_id),
  CONSTRAINT fk_upp_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exceções por pessoa (conceder ou revogar, com validade opcional)
CREATE TABLE IF NOT EXISTS usuario_permissoes (
  usuario_id    BIGINT UNSIGNED NOT NULL,
  permissao_id  INT UNSIGNED NOT NULL,
  efeito        ENUM('conceder','revogar') NOT NULL DEFAULT 'conceder',
  expira_em     DATETIME NULL,
  concedido_por VARCHAR(20) NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, permissao_id),
  CONSTRAINT fk_up_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE CASCADE,
  CONSTRAINT fk_up_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── feed ─────────────────────────────────────────────────
-- Lugares do site onde se publica. Publicar na seção X exige a permissão 'publicar:X'.
CREATE TABLE IF NOT EXISTS secoes (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(40) NOT NULL,
  nome VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_secoes_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- autor: usuário (usuario_id) ou anunciante (comerciante_id = id do arquivo em private/comerciantes)
CREATE TABLE IF NOT EXISTS publicacoes (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  autor_tipo              ENUM('usuario','anunciante') NOT NULL,
  usuario_id              BIGINT UNSIGNED NULL,
  comerciante_id          CHAR(17) NULL,
  secao_id                INT UNSIGNED NOT NULL,
  titulo                  VARCHAR(120) NOT NULL,
  texto                   TEXT NOT NULL,
  status                  ENUM('publicada','oculta','removida') NOT NULL DEFAULT 'publicada',
  total_curtidas          INT UNSIGNED NOT NULL DEFAULT 0,
  total_comentarios       INT UNSIGNED NOT NULL DEFAULT 0,
  total_compartilhamentos INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pub_secao (secao_id, status, id),
  KEY ix_pub_usuario (usuario_id),
  KEY ix_pub_comerciante (comerciante_id, criado_em),
  CONSTRAINT fk_pub_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pub_secao   FOREIGN KEY (secao_id)   REFERENCES secoes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── interações (só usuários) ─────────────────────────────
CREATE TABLE IF NOT EXISTS curtidas (
  publicacao_id BIGINT UNSIGNED NOT NULL,
  usuario_id    BIGINT UNSIGNED NOT NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (publicacao_id, usuario_id),
  KEY ix_curtidas_usuario (usuario_id),
  CONSTRAINT fk_curt_pub     FOREIGN KEY (publicacao_id) REFERENCES publicacoes(id) ON DELETE CASCADE,
  CONSTRAINT fk_curt_usuario FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  publicacao_id BIGINT UNSIGNED NOT NULL,
  usuario_id    BIGINT UNSIGNED NOT NULL,
  texto         VARCHAR(1000) NOT NULL,
  status        ENUM('publicado','removido') NOT NULL DEFAULT 'publicado',
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_com_pub (publicacao_id, status, id),
  KEY ix_com_usuario (usuario_id),
  CONSTRAINT fk_com_pub     FOREIGN KEY (publicacao_id) REFERENCES publicacoes(id) ON DELETE CASCADE,
  CONSTRAINT fk_com_usuario FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compartilhamentos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  publicacao_id BIGINT UNSIGNED NOT NULL,
  usuario_id    BIGINT UNSIGNED NOT NULL,
  destino       ENUM('whatsapp','facebook','link','outro') NOT NULL DEFAULT 'link',
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_comp_pub (publicacao_id),
  KEY ix_comp_usuario (usuario_id),
  CONSTRAINT fk_comp_pub     FOREIGN KEY (publicacao_id) REFERENCES publicacoes(id) ON DELETE CASCADE,
  CONSTRAINT fk_comp_usuario FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de tentativas (anti-robô / força bruta). Só hashes, nenhum dado de conta.
CREATE TABLE IF NOT EXISTS limites (
  chave         CHAR(64) NOT NULL,
  contagem      INT UNSIGNED NOT NULL,
  janela_inicio INT UNSIGNED NOT NULL,
  PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── dados iniciais ──────────────────────────────────────
INSERT IGNORE INTO secoes (slug, nome) VALUES
  ('avaliacoes', 'Avaliações'),
  ('eventos',    'Eventos e baladas'),
  ('lojas',      'Lojas'),
  ('ofertas',    'Ofertas e promoções'),
  ('servicos',   'Serviços');

INSERT IGNORE INTO permissoes (chave, descricao) VALUES
  ('perfil:editar',       'Editar o próprio perfil e fotos'),
  ('chat:usar',           'Usar o chat'),
  ('comentar',            'Comentar nas publicações'),
  ('curtir',              'Curtir publicações'),
  ('compartilhar',        'Compartilhar publicações'),
  ('publicar:avaliacoes', 'Avaliar lojas e serviços'),
  ('publicar:eventos',    'Divulgar eventos'),
  ('publicar:lojas',      'Cadastrar e divulgar lojas'),
  ('publicar:ofertas',    'Publicar ofertas e promoções'),
  ('publicar:servicos',   'Divulgar serviços'),
  ('admin:usuarios',      'Ver, bloquear e desbloquear contas'),
  ('admin:permissoes',    'Conceder e revogar permissões');

-- usuário: chat, comentar, curtir, compartilhar, avaliar
INSERT IGNORE INTO usuario_permissoes_padrao (permissao_id)
  SELECT id FROM permissoes WHERE chave IN
    ('perfil:editar','chat:usar','comentar','curtir','compartilhar','publicar:avaliacoes');
