-- ============================================================
-- BarberFlow — Tabela de movimentos de stock (auditoria)
-- ------------------------------------------------------------
-- Importa no phpMyAdmin (Base: esan-dsg09) via "Importar".
-- Executa UMA vez.
-- ============================================================

SET NAMES utf8mb4;

-- Rastreio de quem mexeu em quê, quando e porquê.
CREATE TABLE IF NOT EXISTS movimentos_stock (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  barbearia_id INT UNSIGNED NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  bancada_id INT UNSIGNED DEFAULT NULL,
  utilizador_id INT UNSIGNED NOT NULL,
  tipo ENUM('entrada','transferencia','consumo','perda','ajuste') NOT NULL,
  quantidade DECIMAL(10,2) NOT NULL,
  motivo VARCHAR(200) DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mov (barbearia_id, material_id, criado_em),
  KEY fk_mov_bancada (bancada_id),
  CONSTRAINT fk_mov_material FOREIGN KEY (material_id) REFERENCES materiais (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mov_bancada FOREIGN KEY (bancada_id) REFERENCES bancadas (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_mov_utilizador FOREIGN KEY (utilizador_id) REFERENCES utilizadores (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mov_barbearia FOREIGN KEY (barbearia_id) REFERENCES barbearias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Código de barras único por barbearia (para o leitor/câmara).
-- Os valores NULL não conflituam, por isso é seguro correr já.
ALTER TABLE materiais ADD UNIQUE KEY uq_material_codigo (barbearia_id, codigo_barras);
