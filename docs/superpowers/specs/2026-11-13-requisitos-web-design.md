# BarberFlow web: requisitos de avaliação em falta — desenho

**Data:** 2026-11-13 · **Estado:** desenho aprovado em conversa, à espera de revisão escrita
**Repositório:** `C:\xampp\htdocs\projeto\projeto` (web + API) · servidor de avaliação: UA
**Prazos:** 1.ª entrega web 21/12/2026 · final 02/01/2027 (quanto mais cedo, melhor)

## 1. Objetivo

Cobrir os critérios da grelha de PAW que ainda faltam, de forma útil também para a RL Barber em produção:

| Critério (valores) | O que se acrescenta |
|---|---|
| Registo/Login e sessões (2) | Ativação de barbeiros por convite; login mais robusto |
| Tecnologias base (2) | Cookie "Lembrar o meu email"; mail com alternativa quando falha |
| Tecnologias avançadas (3) | JWT nos tokens da app |
| Segurança (2) | Mudar/recuperar senha com OTP por email; 2FA (TOTP) opcional; vista SQL |
| CRUD/Pesquisa (2) | Pesquisa na agenda |
| Base de dados (1) | Diagrama ER e explicação |
| Documentação (2) | Relatório + tabela "critério → onde está" |

Decisões do utilizador:

| Pergunta | Decisão |
|---|---|
| Quem se regista | O gestor já vem criado; o **barbeiro é convidado** pelo gestor e ativa a conta |
| Ativação | **Link por email** (48 h) com **alternativa**: o gestor vê o link no ecrã para enviar por WhatsApp |
| SMS | Não (não há SMS gratuito); só email |
| JWT | **Tokens de marcação da app** passam a JWT; os antigos continuam aceites |
| 2FA | **Opcional**, ativa-se no Perfil, app autenticadora (TOTP) |
| BD | **Autorizado**: colunas novas em `utilizadores`, tabelas novas desta spec e a vista `vw_agenda` |
| QR code do 2FA | **Autorizada** uma biblioteca JS pequena só para desenhar o QR (não é framework) |
| Cliente | Fica como está (identificação por código), na web e na app |

## 2. Base de dados (migração `sql/2026-11-13_seguranca_contas.sql`)

```sql
ALTER TABLE utilizadores
  ADD COLUMN convite_token_hash CHAR(64) NULL,     -- sha256 do token do link; NULL = sem convite pendente
  ADD COLUMN convite_expira_em DATETIME NULL,
  ADD COLUMN totp_segredo VARCHAR(64) NULL,        -- base32; só fica ativo depois de confirmado
  ADD COLUMN totp_ativo TINYINT(1) NOT NULL DEFAULT 0;

-- Códigos de 6 dígitos enviados por email (mudar/recuperar senha)
CREATE TABLE codigos_seguranca (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilizador_id INT UNSIGNED NOT NULL,
  finalidade ENUM('alterar_password','recuperar_password') NOT NULL,
  codigo_hash VARCHAR(255) NOT NULL,               -- password_hash do código
  expira_em DATETIME NOT NULL,
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  usado TINYINT(1) NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_codigo_util (utilizador_id, finalidade),
  CONSTRAINT fk_codigo_util FOREIGN KEY (utilizador_id) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- Limite de tentativas de login (força bruta)
CREATE TABLE tentativas_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tent (email, criado_em), KEY idx_tent_ip (ip, criado_em)
);

-- Agenda: marcação + cliente + serviço + barbeiro numa só vista (usada pelo módulo agenda)
CREATE OR REPLACE VIEW vw_agenda AS
SELECT m.id, m.barbearia_id, m.barbeiro_id, m.inicio, m.fim, m.estado, m.notas, m.para_nome,
       c.nome AS cliente_nome, c.telemovel AS cliente_telemovel, c.email AS cliente_email,
       s.nome AS servico_nome, s.duracao_minutos, s.preco, u.nome AS barbeiro_nome
FROM marcacoes m
JOIN clientes c ON c.id = m.cliente_id
JOIN servicos s ON s.id = m.servico_id
JOIN utilizadores u ON u.id = m.barbeiro_id;
```

O SQL é idempotente onde o MariaDB o permite (`IF NOT EXISTS`, `CREATE OR REPLACE`). Corre-se na BD local e na UA **antes** de publicar o código.

## 3. Funcionalidades

### 3.1 Ativação de barbeiros (Registo)
- **Gestão → Barbeiros → Adicionar:** pede nome, email e telefone. A bancada passa a opcional.
  - Cria o utilizador com `ativo = 1`, `password_hash` aleatório e inutilizável, e um convite: token de 32 bytes aleatórios, guardado só o `sha256`, válido **48 h**.
- **Email "Ativa a tua conta"** com o link `gestao/ativar.php?t=<token>`.
  - Se o envio falhar, ou houver `codigo_se_email_falhar`, o gestor vê o link no ecrã com um botão **Copiar**.
  - Na lista de barbeiros, quem ainda não ativou mostra **"Por ativar"** e **"Reenviar convite"** (gera um token novo; o anterior deixa de valer).
- **`gestao/ativar.php`** (página pública, fora do `index.php` da gestão, como o `login.php`):
  - token inválido ou expirado → mensagem e indicação para pedir novo convite ao gestor;
  - token válido → escolher a senha (mín. 8 caracteres, com letra e número) e confirmar;
  - o hash é gravado e o convite apagado, e a página redireciona para o login com "Conta ativada".
- Um barbeiro **por ativar não consegue fazer login**: aparece a mensagem "Ativa a conta pelo link que recebeste".
- O fluxo antigo (senha temporária e `precisa_alterar_password`) mantém-se para as contas que já existem.

### 3.2 Mudar e recuperar a senha com OTP por email
- **Perfil → Mudar senha:** botão "Enviar código" → código de 6 dígitos para o email (15 min, 5 tentativas, `codigos_seguranca`). Depois escreve-se o código e a senha nova (2×). Um pedido novo invalida o código anterior.
- **Login → "Esqueci-me da senha"** (`gestao/recuperar.php`, pública): pede o email; se existir, envia um código.
  - A resposta é sempre a mesma, para não revelar se o email existe.
  - Depois escreve-se o código e a senha nova.
- **Na UA:** o código só aparece no ecrã se `codigo_se_email_falhar` estiver ligado, a mesma opção que a app já usa.
- O `precisa_alterar_password` atual passa a usar este fluxo para mudar a senha.

### 3.3 2FA com app autenticadora (TOTP, RFC 6238), opcional
- **Perfil → Segurança → Ativar 2FA:**
  - gera um segredo base32 de 20 bytes;
  - mostra o QR do `otpauth://totp/BarberFlow:<email>?secret=…&issuer=BarberFlow` e a chave em texto;
  - pede um código para confirmar → `totp_ativo = 1`.
- **"Desativar 2FA"** pede a senha e um código atual.
- **Login com 2FA ativo:**
  - depois da senha certa, a sessão fica em "falta o 2.º fator" (sem `utilizador_id`) e aparece o passo "Código da app";
  - aceita a janela de ±1 período de 30 s;
  - 5 códigos errados → volta ao início.
- **Implementação:**
  - HMAC-SHA1 e truncagem dinâmica feitos à mão em `config/totp.php`, com o vetor de teste do RFC;
  - QR desenhado no browser por uma biblioteca JS pequena vendida no projeto (`assets/js/qrcode.min.js`), sem CDN.

### 3.4 Login mais robusto
- `session_regenerate_id(true)` depois do login (e do 2.º fator).
- **Limite de tentativas:** mais de 5 falhas por email, ou 20 por IP, em 15 min → "Demasiadas tentativas, tenta daqui a X minutos" (`tentativas_login`; registos com mais de 1 dia são apagados).
- **CSRF** no formulário de login.
- **Cookie "Lembrar o meu email":**
  - `bf_email`, 30 dias, `HttpOnly`, `SameSite=Lax`, `Secure` quando a ligação é HTTPS;
  - só guarda o email (nunca a senha) e preenche o campo no login;
  - desmarcar a opção apaga o cookie.

### 3.5 JWT nos tokens da app
- `config/jwt.php`:
  - `jwtCriar(array $payload): string`;
  - `jwtValidar(string $jwt): ?array`, com HS256, base64url e `hash_equals`;
  - recusa `alg` diferente de HS256 (incluindo `none`) e tokens mal formados.
- **Token de marcação:** `{"sub": marcacao_id, "cli": cliente_id, "iat": …}`, assinado com o mesmo segredo da API (`api_segredo`).
- **Verificação:** os sítios que hoje usam `tokenMarcacao` (listar, cancelar, remarcar, disponibilidade, verificar) passam a aceitar:
  - um JWT válido cujo `sub` e `cli` coincidem com a marcação; **ou**
  - o token antigo (HMAC hex), para as apps já instaladas.
- Os tokens novos que a API devolve passam a ser JWT. A app não muda: guarda o que recebe. O limite de tamanho do parâmetro `tokens` na listagem tem de aceitar JWT.

### 3.6 Pesquisa na agenda
- Campo "Pesquisar cliente" (nome ou telemóvel, com parte do texto) na agenda, combinado com os filtros que já existem.
- Usa a `vw_agenda` com `LIKE` parametrizado e sempre filtrada por `barbearia_id` (e pelo barbeiro, no perfil barbeiro).

### 3.7 Documentação (`docs/`)
- `docs/base-de-dados.md`: diagrama ER (imagem exportada + código Mermaid), explicação de cada tabela, das relações e da vista.
- `docs/relatorio.md`: arquitetura modular (atividade 06), API REST e JWT, segurança (perfis, privilégios, CSRF, OTP, 2FA, limite de tentativas, prepared statements), tecnologias base e instruções de instalação.
- Tabela **"critério da grelha → onde está"** com ligações para os ficheiros.
- Utilizadores de teste para o professor (gestor e barbeiro), descritos no relatório e criados por SQL.

## 4. Testes
- `api/v1/tests/run.php`:
  - JWT: criar e validar; `alg=none`, assinatura trocada e payload alterado → recusado;
  - API com token JWT e com token antigo;
  - TOTP: vetor de teste do RFC 6238.
- `api/v1/tests/web_rotas.php`:
  - convite: criar, ativar, token expirado e reutilizado, "por ativar" não entra;
  - mudar e recuperar a senha: código errado, expirado, 5 tentativas, resposta igual para email inexistente;
  - 2FA: ativar e entrar com código, código errado;
  - limite de tentativas;
  - cookie (definido e apagado);
  - pesquisa: encontra pelo nome e pelo telemóvel, não mostra outra barbearia.
- Os runners existentes continuam verdes.

## 5. Publicação
- Pacote `deploy-ua/atualizacao-8`: SQL primeiro, depois os ficheiros.
- Na UA: `codigo_se_email_falhar` ligado, para os fluxos de email serem demonstráveis.

## 6. Fora de âmbito
SMS, contas de cliente, login social, 2FA obrigatório, chaves de recuperação do 2FA, alterações à app Android além de aceitar JWT (que não exige mudanças).
