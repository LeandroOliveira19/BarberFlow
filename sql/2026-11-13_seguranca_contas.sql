-- BarberFlow: segurança das contas (convite de barbeiros, códigos por email, 2FA, limite de logins) e vista da agenda.
-- Correr UMA vez em cada base de dados (local e UA) ANTES de publicar o código. Pode repetir-se sem erro.

ALTER TABLE utilizadores
  ADD COLUMN IF NOT EXISTS convite_token_hash CHAR(64) NULL,          -- sha256 do token do link; NULL = sem convite pendente
  ADD COLUMN IF NOT EXISTS convite_expira_em DATETIME NULL,
  ADD COLUMN IF NOT EXISTS totp_segredo VARCHAR(64) NULL,             -- base32; só conta quando totp_ativo = 1
  ADD COLUMN IF NOT EXISTS totp_ativo TINYINT(1) NOT NULL DEFAULT 0;

-- Códigos de 6 dígitos enviados por email (mudar/recuperar senha); só se guarda o hash
CREATE TABLE IF NOT EXISTS codigos_seguranca (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT UNSIGNED NOT NULL,
  finalidade ENUM('alterar_password','recuperar_password') NOT NULL,
  codigo_hash VARCHAR(255) NOT NULL,
  expira_em DATETIME NOT NULL,
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_codigo_util (utilizador_id, finalidade),
  CONSTRAINT fk_codigo_util FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login falhadas (proteção contra adivinhar senhas)
CREATE TABLE IF NOT EXISTS tentativas_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tent (email, criado_em),
  KEY idx_tent_ip (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agenda: marcação + cliente + serviço + barbeiro numa só vista (usada pelo módulo agenda).
-- Os JOIN incluem barbearia_id para nunca misturar dados de barbearias diferentes.
CREATE OR REPLACE VIEW vw_agenda AS
SELECT m.id, m.barbearia_id, m.barbeiro_id, m.inicio, m.fim, m.estado, m.notas, m.para_nome,
       c.nome AS cliente_nome, c.telemovel AS cliente_telemovel, c.email AS cliente_email,
       s.nome AS servico_nome, s.duracao_minutos, s.preco AS servico_preco,
       u.nome AS barbeiro_nome
FROM marcacoes m
JOIN clientes     c ON c.id = m.cliente_id  AND c.barbearia_id = m.barbearia_id
JOIN servicos     s ON s.id = m.servico_id  AND s.barbearia_id = m.barbearia_id
JOIN utilizadores u ON u.id = m.barbeiro_id AND u.barbearia_id = m.barbearia_id;
