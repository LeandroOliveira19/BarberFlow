-- BarberFlow: utilizadores de teste para a avaliação de PAW.
-- Ficam na barbearia de DEMONSTRAÇÃO "Navalha de Ouro" (id 2), nunca na RL Barber:
-- as senhas estão no relatório/GitHub, por isso estas contas não podem ver clientes reais.
-- Pode correr-se várias vezes (repõe as senhas). Apagar ou mudar as senhas depois da avaliação.
--
--   Gestor:   avaliacao.gestor@example.com   / Avaliacao!2026
--   Barbeiro: avaliacao.barbeiro@example.com / Barbeiro!2026
--   (example.com é um domínio reservado: o sistema nunca envia emails para lá; os códigos aparecem
--    no ecrã quando 'api_codigo_se_email_falhar' está ligado, como na UA)

INSERT INTO utilizadores (barbearia_id, nome, email, password_hash, tipo, ativo, precisa_alterar_password)
VALUES
  (2, 'Gestor de Avaliação',   'avaliacao.gestor@example.com',   '$2y$10$EyyOCO.jsmZ3dWK3Et.8neEOEgKIkolqHLiV5fRdOf.K1UpA/6qCa', 'gestor',   1, 0),
  (2, 'Barbeiro de Avaliação', 'avaliacao.barbeiro@example.com', '$2y$10$X7hcOCF5YoNNWHsb6pEjSeayZirxGnMp5kxu13pVgK2zT4Zb0Uk3.', 'barbeiro', 1, 0)
ON DUPLICATE KEY UPDATE
  password_hash = VALUES(password_hash), ativo = 1, barbearia_id = 2, tipo = VALUES(tipo),
  precisa_alterar_password = 0, convite_token_hash = NULL, convite_expira_em = NULL,
  totp_segredo = NULL, totp_ativo = 0;
