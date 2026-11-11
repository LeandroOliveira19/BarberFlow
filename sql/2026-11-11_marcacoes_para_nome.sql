-- Marcação feita por uma pessoa para outra (ex.: o pai marca para o filho).
-- NULL = a marcação é para o próprio cliente que marcou.
-- Correr uma vez em cada base de dados (local e UA) ANTES de publicar o código que usa a coluna.
ALTER TABLE `marcacoes`
  ADD COLUMN `para_nome` VARCHAR(120) NULL DEFAULT NULL AFTER `notas`;
