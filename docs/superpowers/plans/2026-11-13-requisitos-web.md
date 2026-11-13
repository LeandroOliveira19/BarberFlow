# Requisitos web em falta — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cobrir os critérios de PAW em falta: convite de barbeiros, OTP por email para a senha, 2FA TOTP opcional, login robusto com cookie, JWT na API, pesquisa com vista SQL e documentação.

**Architecture:** Funções puras novas em `config/` (`jwt.php`, `totp.php`, `contas.php`) usadas pelos módulos da gestão e pela API. Duas páginas públicas novas ao lado do `login.php` (`ativar.php`, `recuperar.php`). Migração SQL única e idempotente. Tudo sem frameworks.

**Tech Stack:** PHP 8 + PDO/MariaDB, HTML/CSS/JS sem frameworks; uma biblioteca JS só para desenhar o QR.

**Spec:** `docs/superpowers/specs/2026-11-13-requisitos-web-design.md`

## Global Constraints

- Sem frameworks (enunciado de PAW). A única biblioteca externa é o desenhador de QR em JS, vendido no projeto (sem CDN).
- PDO com prepared statements; todas as queries filtradas por `barbearia_id` onde a tabela o tem.
- Textos visíveis em português de Portugal.
- Segredos só em `config/credenciais.php` (fora do git). O segredo do JWT é o `api_segredo` que já existe.
- A opção "mostrar o código quando o email falha" é a chave existente **`api_codigo_se_email_falhar`** (a spec chama-lhe `codigo_se_email_falhar`), e o modo dev é `api_dev_devolver_codigo`, lidas pela função `deveDevolverCodigo()` (`api/v1/_lib.php`).
- A API só acrescenta: a app Android já instalada continua a funcionar (tokens antigos aceites).
- Commits sem `Co-Authored-By` nem atribuição ao Claude. Trabalho no ramo `requisitos-web`.
- Testes contra `php -S 127.0.0.1:8080 -t C:\xampp\htdocs`; runners em `api/v1/tests/`.

## Review Focus

1. **2FA contornado:** com a senha certa mas sem o código, a sessão não pode abrir nenhuma página de `gestao/index.php` (o estado intermédio não tem `utilizador_id`). Teste na Task 4.
2. **JWT forjado:** `alg: none`, `alg: HS512`, assinatura de outro segredo, payload alterado e partes a mais ou a menos → recusado sem avisos PHP. Teste na Task 2.
3. **Link de convite reutilizado** depois da ativação, ou usado depois de "Reenviar convite" → recusado. Teste na Task 7.
4. **Recuperar senha:** a resposta para um email inexistente é igual à de um existente (mesmo texto e mesmo destino). Teste na Task 6.
5. **Pesquisa com `%` ou `_`** escritos pelo utilizador: tratados como texto (escapados no `LIKE`) e nunca mostram outra barbearia. Teste na Task 1.

---

### Task 1: Migração SQL, vista `vw_agenda` e pesquisa na agenda

**Files:**
- Create: `sql/2026-11-13_seguranca_contas.sql` (o SQL da spec §2, idempotente: `ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`, `CREATE OR REPLACE VIEW`)
- Modify: `gestao/mod/agenda/_comum.php` (filtro `q`), `gestao/mod/agenda/listar.php` (campo de pesquisa; query sobre `vw_agenda`)
- Test: `api/v1/tests/web_rotas.php` (função nova `teste_pesquisa_agenda`, registada na lista de execução)

**Interfaces:**
- Produces: as colunas `utilizadores.convite_token_hash`, `convite_expira_em`, `totp_segredo`, `totp_ativo`; as tabelas `codigos_seguranca` e `tentativas_login`; a vista `vw_agenda`; e o parâmetro GET `q` da agenda (máx. 60 caracteres, em `urlAgenda()`).
- `function likeSeguro(string $texto): string`, em `config/modular.php`: escapa `\`, `%` e `_` e envolve em `%…%`.

- [ ] **Step 1: Testes.**
  - Cria duas marcações de teste do dia (cliente "Cliente Pesquisa Rotas", telemóvel `919990001`) e uma de outra barbearia com o mesmo nome.
  - Asserções, como gestor:
    - `m=agenda&a=listar&vista=proximas&q=Pesquisa Rotas` mostra o cliente;
    - `q=9999900` mostra-o pelo telemóvel;
    - `q=%` não lista tudo (só quem tiver `%` no nome, ou seja, ninguém);
    - a marcação da outra barbearia nunca aparece;
    - o campo `name="q"` mantém o valor.
  - Como barbeiro, só vê as suas.
- [ ] **Step 2: Run** `php api/v1/tests/web_rotas.php`. Expected: as asserções novas falham (sem campo e sem filtro).
- [ ] **Step 3:** Aplicar `sql/2026-11-13_seguranca_contas.sql` na BD local (`mysql -u root esan-dsg09 < …`). Implementar `likeSeguro`, o filtro `(cliente_nome LIKE ? ESCAPE '\\' OR cliente_telemovel LIKE ? ESCAPE '\\')` e a query da listagem sobre `vw_agenda`.
- [ ] **Step 4: Run** `php api/v1/tests/web_rotas.php; php api/v1/tests/run.php`. Expected: 0 falhas nos dois.
- [ ] **Step 5: Commit:** `feat(gestao): migracao de seguranca, vista vw_agenda e pesquisa na agenda`.

### Task 2: JWT nos tokens da API

**Files:**
- Create: `config/jwt.php`
- Modify: `api/v1/_lib.php` (`tokenMarcacao` passa a emitir JWT; nova `tokenMarcacaoValido`), `api/v1/index.php` (as 4 verificações das linhas que usam `hash_equals(tokenMarcacao(...))`; o regex dos `tokens` da listagem)
- Test: `api/v1/tests/run.php` (`teste_jwt` em `$leitura`; casos JWT em `teste_listar` e `teste_alterar`)

**Interfaces:**
- Produces:
  - `jwtCriar(array $payload, string $segredo): string`;
  - `jwtValidar(string $jwt, string $segredo): ?array` (HS256 apenas; base64url sem padding; `hash_equals`; devolve o payload ou null);
  - `tokenMarcacao(int $id, int $clienteId): string` passa a devolver o JWT `{"sub":id,"cli":clienteId,"iat":time()}`;
  - `tokenMarcacaoValido(string $tok, int $id, int $clienteId): bool` aceita o JWT (com `sub` e `cli` iguais) **ou** o token antigo (o HMAC hex atual, mantido como `tokenMarcacaoAntigo`).
- Listagem: os pares `id:token` aceitam `^(\d{1,10}):([0-9a-f]{64}|[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)$`. O `array_slice` de 50 mantém-se.

- [ ] **Step 1: Testes.**
  - `teste_jwt`:
    - criar e validar dá ida e volta;
    - `alg:none` → null; `alg:HS512` → null;
    - assinatura com outro segredo → null; payload alterado → null;
    - `a.b`, `a.b.c.d`, `''` e base64 inválido → null, sem avisos.
  - `teste_listar`:
    - o token devolvido pelo verificar tem 3 partes e lista a marcação;
    - o token antigo (`tokenMarcacaoAntigo`) também lista;
    - um JWT de outra marcação → `[]`.
  - `teste_alterar`: cancelar com o token antigo continua a funcionar.
- [ ] **Step 2: Run** `php api/v1/tests/run.php`. Expected: falham os casos novos (`jwtCriar` não existe).
- [ ] **Step 3: Implementar** `config/jwt.php` e trocar as 4 comparações por `tokenMarcacaoValido`.
- [ ] **Step 4: Run** `php api/v1/tests/run.php; php api/v1/tests/web_marcar.php`. Expected: 0 falhas.
- [ ] **Step 5: Commit:** `feat(api): tokens de marcacao em JWT (HS256) com compatibilidade`.

### Task 3: Biblioteca TOTP

**Files:**
- Create: `config/totp.php`
- Test: `api/v1/tests/web_objects.php` (ou um novo `api/v1/tests/web_seguranca.php`, se o primeiro for só de classes)

**Interfaces:**
- Produces:
  - `base32Codificar(string $bytes): string`;
  - `base32Decodificar(string $b32): ?string`;
  - `totpGerarSegredo(): string` (20 bytes aleatórios em base32);
  - `totpCodigo(string $segredoB32, int $instante, int $periodo = 30, int $digitos = 6): string`;
  - `totpVerificar(string $segredoB32, string $codigo, int $instante, int $janela = 1): bool`;
  - `totpUri(string $segredoB32, string $email, string $emissor = 'BarberFlow'): string`.
  - Algoritmo: RFC 6238 com HMAC-SHA1, contador de 8 bytes big-endian e truncagem dinâmica, feito à mão.

- [ ] **Step 1: Testes.**
  - Vetor do RFC 6238 (segredo ASCII `12345678901234567890` em base32 = `GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ`): `T=59` → `287082`, `T=1111111109` → `081804`, `T=2000000000` → `279037` (6 dígitos).
  - Ida e volta do base32.
  - `totpVerificar` aceita o código do período anterior e do seguinte e recusa o de 2 períodos antes.
  - `totpUri` contém `secret=` e `issuer=BarberFlow` e codifica o email.
- [ ] **Step 2: Run** o runner. Expected: falha (funções inexistentes).
- [ ] **Step 3: Implementar** `config/totp.php`.
- [ ] **Step 4: Run.** Expected: 0 falhas.
- [ ] **Step 5: Commit:** `feat(seguranca): TOTP (RFC 6238) e base32 sem bibliotecas`.

### Task 4: Login robusto, cookie e 2.º fator no login

**Files:**
- Create: `config/contas.php` (regras de login partilhadas)
- Modify: `gestao/login.php`, `api/v1/tests/_sessao.php` (`Sessao::login` passa a ir buscar o CSRF ao formulário; aceita o código 2FA opcional)
- Test: `api/v1/tests/web_rotas.php` (`teste_login_seguro`)

**Interfaces:**
- Produces, em `config/contas.php`:
  - `tentativasExcedidas(PDO $pdo, string $email, string $ip): ?int` (minutos de espera ou null; 5 por email ou 20 por IP em 15 min);
  - `registarTentativaFalhada(PDO $pdo, string $email, string $ip): void` (apaga também os registos com mais de 1 dia);
  - `limparTentativas(PDO $pdo, string $email): void`;
  - `iniciarSessaoUtilizador(array $u): void` (`session_regenerate_id(true)`, chaves de sessão de hoje).
- Login em dois passos: depois da senha certa com `totp_ativo = 1`, fica `$_SESSION['login_2fa'] = ['id' => …, 'expira' => time()+300, 'falhas' => 0]`, **sem** `utilizador_id`, e o mesmo `login.php` mostra o passo "Código da app" (`name="codigo_2fa"`).
- Cookie `bf_email`: `setcookie('bf_email', $email, ['expires' => time()+30*86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => HTTPS ligado])`. A opção chama-se `lembrar_email` e, desmarcada, o cookie expira.
- O login recusa contas com `convite_token_hash` não nulo e `password_hash` inutilizável, com a mensagem "Ativa a conta pelo link que recebeste" (usado pela Task 7).

- [ ] **Step 1: Testes.**
  - POST sem CSRF → não entra.
  - 6.º falhanço para o mesmo email → mensagem "Demasiadas tentativas" mesmo com a senha certa (limpar a tabela no fim).
  - Login certo → o cookie de sessão muda (id regenerado).
  - Com `lembrar_email=1` → resposta com `Set-Cookie: bf_email=` e `HttpOnly`; o GET seguinte do login traz o email no campo.
  - Utilizador com 2FA ativo (segredo fixo no teste):
    - senha certa → 200 com `name="codigo_2fa"`, e GET `gestao/index.php` → 302 para o login (Review Focus 1);
    - código certo (`totpCodigo`) → 302 para `index.php`;
    - 5 códigos errados → volta ao passo da senha.
- [ ] **Step 2: Run** `php api/v1/tests/web_rotas.php`. Expected: falham os casos novos. Os antigos continuam a passar depois de atualizar `Sessao::login` para ler o CSRF.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Run** `php api/v1/tests/web_rotas.php; php api/v1/tests/web_objects.php`. Expected: 0 falhas.
- [ ] **Step 5: Commit:** `feat(gestao): login com CSRF, limite de tentativas, sessao regenerada, cookie e 2FA`.

### Task 5: Ativar e desativar o 2FA no Perfil (QR)

**Files:**
- Create: `gestao/mod/perfil/ativar_2fa.php`, `gestao/mod/perfil/desativar_2fa.php`, `assets/js/qrcode.min.js` (biblioteca de QR, MIT, vendida)
- Modify: `gestao/mod/perfil/editar.php` (cartão "Segurança"), `gestao/index.php` (`perfil` ganha `ativar_2fa`, `desativar_2fa` nos dois perfis)
- Test: `api/v1/tests/web_rotas.php` (`teste_2fa_perfil`)

**Interfaces:**
- Consumes: `totpGerarSegredo`, `totpUri` e `totpVerificar` (Task 3).
- **Ativar:**
  - o GET do Perfil gera um segredo pendente em `$_SESSION['totp_pendente']` (não na BD) e mostra o QR de `totpUri` e a chave;
  - o POST `ativar_2fa` com `codigo` válido grava `totp_segredo` e `totp_ativo = 1`.
- **Desativar:** o POST `desativar_2fa` exige `password` e `codigo` válidos.
- **Biblioteca do QR:** escolher uma biblioteca pequena, MIT, de ficheiro único, e **pedir confirmação ao utilizador** com nome, origem e tamanho antes de a descarregar (regra de downloads).

- [ ] **Step 1: Testes.**
  - O Perfil mostra "Ativar 2FA" e uma chave base32.
  - POST com código errado → continua desativado.
  - POST com o código certo (calculado a partir da chave mostrada) → `totp_ativo = 1`, e o login seguinte pede o código.
  - Desativar sem senha → recusado; com senha e código → desativado.
- [ ] **Step 2: Run.** Expected: falham.
- [ ] **Step 3: Implementar** (incluindo o pedido de confirmação do download do JS do QR).
- [ ] **Step 4: Run** `php api/v1/tests/web_rotas.php`. Expected: 0 falhas. Verificar o QR no browser com uma app autenticadora real, ou pelo menos ver que o desenho aparece.
- [ ] **Step 5: Commit:** `feat(gestao): ativar e desativar 2FA no perfil com QR`.

### Task 6: Mudar e recuperar a senha com código por email

**Files:**
- Create: `config/mailer_contas.php`, `gestao/recuperar.php`
- Modify: `config/contas.php` (códigos), `gestao/mod/perfil/alterar_password.php` (passa a exigir o código), `gestao/mod/perfil/editar.php` (botão "Enviar código"), `gestao/index.php` (`perfil` ganha `enviar_codigo`), `gestao/login.php` (ligação "Esqueci-me da senha")
- Test: `api/v1/tests/web_rotas.php` (`teste_senha_com_codigo`; atualizar o teste antigo de "alterar password limpa a obrigação" para usar o código)

**Interfaces:**
- Produces, em `config/contas.php`:
  - `criarCodigoSeguranca(PDO $pdo, int $uid, string $finalidade): string` (invalida os anteriores da mesma finalidade e devolve o código em claro);
  - `validarCodigoSeguranca(PDO $pdo, int $uid, string $finalidade, string $codigo): bool` (15 min, 5 tentativas, uso único);
  - `senhaValida(string $s): bool` (mín. 8 caracteres, com letra e número).
- Produces, em `config/mailer_contas.php`:
  - `enviarEmailBrevo(string $para, string $assunto, string $html): bool` (o transporte do `mailer_codigo.php`, com os mesmos domínios de teste bloqueados);
  - `enviarCodigoSegurancaEmail(string $para, string $nome, string $codigo, string $finalidade): bool`.
- **Mostrar o código no ecrã:** só quando `deveDevolverCodigo(credenciais(), $enviado)` for verdadeiro (é preciso o `require` do `_lib.php`, ou mover `deveDevolverCodigo` para `config/`; decidir e registar no ledger).
- **Recuperar:** em `gestao/recuperar.php` o passo 1 pede o email; a resposta é sempre "Se o email existir, enviámos um código" e passa ao passo 2. O passo 2 pede o email, o código e a senha nova (2×).

- [ ] **Step 1: Testes.**
  - Perfil → `enviar_codigo` → código na BD (hash).
  - `alterar_password` com código errado → recusado; com o certo e a senha `Nova!2026x` → ok, e o login entra com a nova (repor a de teste no fim).
  - O código não serve 2 vezes.
  - 6.º código errado → recusado mesmo com o certo.
  - Recuperar: um email inexistente e um existente devolvem o mesmo HTML no passo 1 (Review Focus 4). O fluxo completo com o código lido do ecrã (modo dev) muda a senha.
  - Senha fraca (`abc`) → recusada.
- [ ] **Step 2: Run.** Expected: falham.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Run** `php api/v1/tests/web_rotas.php; php api/v1/tests/run.php`. Expected: 0 falhas.
- [ ] **Step 5: Commit:** `feat(gestao): mudar e recuperar senha com codigo por email`.

### Task 7: Convite e ativação de barbeiros

**Files:**
- Create: `gestao/ativar.php`
- Modify: `config/contas.php` (convites), `config/mailer_contas.php` (`enviarConviteEmail`), `gestao/mod/barbeiros/criar.php` (sem senha temporária; bancada opcional), `gestao/mod/barbeiros/listar.php` ("Por ativar", "Reenviar convite", link copiável), `gestao/index.php` (`barbeiros` ganha `reenviar_convite`)
- Test: `api/v1/tests/web_rotas.php` (`teste_convite_barbeiro`)

**Interfaces:**
- Produces, em `config/contas.php`:
  - `criarConvite(PDO $pdo, int $uid): string` (devolve o token em claro; guarda o sha256 e `agora + 48 h`);
  - `utilizadorPorConvite(PDO $pdo, string $token): ?array` (válido e não expirado);
  - `ativarConta(PDO $pdo, int $uid, string $senha): void` (grava o hash e limpa o convite);
  - `linkAtivacao(string $token): string` (URL absoluto a partir de `urlBaseSite()`, ou da mesma lógica em `config/`).
- `password_hash` das contas por ativar: `password_hash(bin2hex(random_bytes(32)))`, que ninguém conhece.
- Ecrã do gestor depois de criar ou reenviar: flash com "Convite enviado para X". Se o email falhou ou estiver em modo dev/UA, mostra também o link com um botão "Copiar" (`navigator.clipboard`).

- [ ] **Step 1: Testes.**
  - Gestor cria "Barbeiro Convite Rotas" (`convite.rotas@example.com`) → fica "Por ativar" na lista e o link aparece no ecrã (o email de teste não sai).
  - Login dessa conta → "Ativa a conta pelo link".
  - `ativar.php?t=<token>` com senha fraca → recusado; com `Ativa!2026x` → "Conta ativada", e o login entra.
  - O mesmo link outra vez → inválido (Review Focus 3).
  - "Reenviar convite" noutra conta → o link antigo deixa de valer e o novo funciona.
  - Token expirado (forçar `convite_expira_em` no passado) → inválido.
  - Barbeiro (perfil) não consegue reenviar convites.
  - Limpeza das contas de teste.
- [ ] **Step 2: Run.** Expected: falham.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Run** `php api/v1/tests/web_rotas.php; php api/v1/tests/run.php; php api/v1/tests/web_objects.php; php api/v1/tests/web_marcar.php`. Expected: 0 falhas.
- [ ] **Step 5: Commit:** `feat(gestao): convite e ativacao de barbeiros por link`.

### Task 8: Documentação e utilizadores de teste

**Files:**
- Create: `docs/base-de-dados.md` (diagrama Mermaid `erDiagram` de todas as tabelas, PK/FK, a vista, e uma explicação curta por tabela), `docs/relatorio.md`, `sql/utilizadores_teste.sql` (gestor e barbeiro de teste da RL Barber com senhas conhecidas só para avaliação, idempotente)
- Modify: `README.md` (ligações para `docs/`)

**Interfaces:**
- `docs/relatorio.md` tem as secções: Arquitetura modular; Base de dados (ligação); API REST e JWT; Segurança (perfis/privilégios, CSRF, prepared statements, OTP, 2FA, limite de tentativas, sessões, cookies); Tecnologias base (uploads, mail, cookies); Interação com a app Android; Instalação; Utilizadores de teste; **Tabela critério da grelha → ficheiros**.

- [ ] **Step 1:** Gerar a lista real de tabelas e colunas a partir da BD (`SHOW TABLES` / `SHOW COLUMNS`), para o diagrama corresponder à BD e não à memória.
- [ ] **Step 2:** Escrever os documentos. Cada ficheiro citado na tabela de critérios tem de existir (verificar com `ls`).
- [ ] **Step 3:** Aplicar `sql/utilizadores_teste.sql` na BD local e entrar com eles (o login devolve 302 para `index.php`).
- [ ] **Step 4: Run** todos os runners. Expected: 0 falhas.
- [ ] **Step 5: Commit:** `docs: base de dados, relatorio e utilizadores de teste`.

### Task 9: Publicação

**Files:**
- Create: `C:\projetosAndroid\projeto\deploy-ua\atualizacao-8\` (`LER-PRIMEIRO.md`, o SQL da Task 1, `sql/utilizadores_teste.sql` e os ficheiros alterados `git diff --name-only main..requisitos-web` sem `api/v1/tests` e `docs/superpowers`)

- [ ] **Step 1:** Montar o pacote. O guia diz:
  - correr primeiro o SQL;
  - depois os ficheiros;
  - na UA, `api_codigo_se_email_falhar => true` no `credenciais.php` do painel **direito**;
  - como testar cada funcionalidade.
- [ ] **Step 2:** Correr todos os runners. Merge para `main` e push só com autorização do utilizador.
- [ ] **Step 3:** Depois de o utilizador publicar: `php api/v1/tests/run.php <url UA>/api/v1/index.php --so-leitura`.
