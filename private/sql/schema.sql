-- Te Liga Aí — banco de dados (MySQL 5.7+/MariaDB 10.3+)
-- Importe pelo phpMyAdmin ou: mysql -u USUARIO -p BANCO < schema.sql

SET NAMES utf8mb4;

-- ─── contas ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo                ENUM('usuario','anunciante','admin') NOT NULL,
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
  KEY ix_usuarios_tipo_status (tipo, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados que só comerciantes têm (1 para 1 com usuarios.tipo = 'anunciante')
CREATE TABLE IF NOT EXISTS anunciantes (
  usuario_id    BIGINT UNSIGNED NOT NULL,
  nome_negocio  VARCHAR(120) NULL,
  categoria     VARCHAR(60)  NULL,
  documento     VARCHAR(14)  NULL,
  verificado_em DATETIME NULL,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_anunciantes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
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

-- Códigos de 6 números enviados por e-mail (guardados só como hash)
CREATE TABLE IF NOT EXISTS codigos_email (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  BIGINT UNSIGNED NOT NULL,
  finalidade  ENUM('confirmar_email','redefinir_senha') NOT NULL,
  codigo_hash CHAR(64) NOT NULL,
  tentativas  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expira_em   DATETIME NOT NULL,
  usado_em    DATETIME NULL,
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_codigos_usuario (usuario_id, finalidade),
  CONSTRAINT fk_codigos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── permissões (RBAC) ────────────────────────────────────
-- Cada tipo de conta recebe um pacote padrão (tipo_permissoes).
-- Depois você concede ou revoga permissões específicas por pessoa (usuario_permissoes).
CREATE TABLE IF NOT EXISTS permissoes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  chave     VARCHAR(64)  NOT NULL,
  descricao VARCHAR(160) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permissoes_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipo_permissoes (
  tipo         ENUM('usuario','anunciante','admin') NOT NULL,
  permissao_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (tipo, permissao_id),
  CONSTRAINT fk_tp_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_permissoes (
  usuario_id    BIGINT UNSIGNED NOT NULL,
  permissao_id  INT UNSIGNED NOT NULL,
  efeito        ENUM('conceder','revogar') NOT NULL DEFAULT 'conceder',
  expira_em     DATETIME NULL,
  concedido_por BIGINT UNSIGNED NULL,
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, permissao_id),
  CONSTRAINT fk_up_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)   ON DELETE CASCADE,
  CONSTRAINT fk_up_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lugares do site onde se publica. Publicar na seção X exige a permissão 'publicar:X'.
CREATE TABLE IF NOT EXISTS secoes (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(40) NOT NULL,
  nome VARCHAR(80) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_secoes_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS publicacoes (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  secao_id   INT UNSIGNED NOT NULL,
  titulo     VARCHAR(120) NOT NULL,
  texto      TEXT NOT NULL,
  status     ENUM('publicada','oculta','removida') NOT NULL DEFAULT 'publicada',
  criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pub_secao (secao_id, status, criado_em),
  KEY ix_pub_usuario (usuario_id),
  CONSTRAINT fk_pub_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_pub_secao   FOREIGN KEY (secao_id)   REFERENCES secoes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de tentativas (anti-robô / força bruta)
CREATE TABLE IF NOT EXISTS limites (
  chave         CHAR(64) NOT NULL,
  contagem      INT UNSIGNED NOT NULL,
  janela_inicio INT UNSIGNED NOT NULL,
  PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── dados iniciais ──────────────────────────────────────
INSERT IGNORE INTO secoes (slug, nome) VALUES
  ('comentarios', 'Comentários'),
  ('avaliacoes',  'Avaliações'),
  ('eventos',     'Eventos e baladas'),
  ('lojas',       'Lojas'),
  ('ofertas',     'Ofertas e promoções'),
  ('servicos',    'Serviços');

INSERT IGNORE INTO permissoes (chave, descricao) VALUES
  ('perfil:editar',         'Editar o próprio perfil e fotos'),
  ('chat:usar',             'Usar o chat'),
  ('publicar:comentarios',  'Publicar comentários'),
  ('publicar:avaliacoes',   'Avaliar lojas e serviços'),
  ('publicar:eventos',      'Divulgar eventos'),
  ('publicar:lojas',        'Cadastrar e divulgar lojas'),
  ('publicar:ofertas',      'Publicar ofertas e promoções'),
  ('publicar:servicos',     'Divulgar serviços'),
  ('admin:usuarios',        'Ver, bloquear e desbloquear contas'),
  ('admin:permissoes',      'Conceder e revogar permissões');

-- usuário comum: interage, mas não anuncia
INSERT IGNORE INTO tipo_permissoes (tipo, permissao_id)
  SELECT 'usuario', id FROM permissoes WHERE chave IN
    ('perfil:editar','chat:usar','publicar:comentarios','publicar:avaliacoes');

-- comerciante/anunciante: tudo do usuário + publicar anúncios
INSERT IGNORE INTO tipo_permissoes (tipo, permissao_id)
  SELECT 'anunciante', id FROM permissoes WHERE chave IN
    ('perfil:editar','chat:usar','publicar:comentarios',
     'publicar:eventos','publicar:lojas','publicar:ofertas','publicar:servicos');

-- admin: tudo
INSERT IGNORE INTO tipo_permissoes (tipo, permissao_id)
  SELECT 'admin', id FROM permissoes;
