# BarberFlow Web Modular Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pôr a gestão e a parte pública a funcionar através de dois `index.php` modulares (atividade 06), sem mudar o que cada ecrã faz.

**Architecture:** `gestao/index.php` e `index.php` (raiz) fazem sessão → `?m=&a=` validados por lista branca/perfil → CSRF central em POST → `ob_start()` + `require mod/$m/$a.php` → desenham layout com o conteúdo. Páginas antigas passam a redirecionamentos. Fase 2 extrai classes por entidade para `objects/`.

**Tech Stack:** PHP 8.2/8.3 (`declare(strict_types=1)`), PDO/MariaDB, testes PHP CLI com curl contra `php -S 127.0.0.1:8080`.

**Spec:** `docs/superpowers/specs/2026-11-11-web-modular-design.md`

**Repositório:** `C:\xampp\htdocs\projeto\projeto`, branch `web-modular`. Há alterações web antigas não commitadas (gestao/bancadas.php, gestao/index.php, gestao/stock.php, publico/index.php, .gitignore) — **fazer `git diff` de cada uma antes de a migrar**, migrar o conteúdo atual do ficheiro (é o que está em uso) e commitar só os ficheiros de cada task.

## Global Constraints

- Comportamento igual ao atual: mesmas consultas, mesmas mensagens PT-PT, mesmos campos. Mover código, não reescrever.
- Só `[a-z_]+` em `m` e `a`; só se inclui `mod/<m>/<a>.php` se `<m>/<a>` estiver na lista branca do perfil **e** o ficheiro existir.
- Todo o POST na gestão e no público passa pela verificação CSRF do `index.php` (`$_SESSION['csrf']`, campo `csrf`); falha → flash de erro + redirect, nunca executa o módulo.
- Ações de escrita: só POST; terminam em `flash()` + `redirecionar(urlModulo(...))` (POST→Redirect→GET). GET a uma ação de escrita → redireciona para a ação de leitura do módulo.
- Todos os ficheiros em `mod/`, `gestao/mod/`, `layout/`, `gestao/layout/`, `objects/`, `config/modular.php` começam por `bloquearAcessoDireto()` (ou o equivalente inline em `config/modular.php`).
- Sessão da gestão: chaves existentes `utilizador_id`, `utilizador_nome`, `barbearia_id`, `tipo` (`gestor`|`barbeiro`), `precisa_alterar_password`. Não renomear.
- Ficheiros com CRLF mantêm CRLF (o repositório mistura; não converter ficheiros inteiros — ver `git diff --stat` antes de cada commit).
- Testes verdes no fim de cada task: `php api/v1/tests/run.php` (97), `php api/v1/tests/web_marcar.php`, `php api/v1/tests/web_rotas.php`.

## Review Focus

1. **`m`/`a` maliciosos** (`../`, `%2e%2e`, maiúsculas, arrays `m[]=`) → nunca incluem fora de `mod/` (teste em Task 2).
2. **Barbeiro a fazer POST direto para uma ação de gestor** (ex.: `m=servicos&a=apagar`) → recusado mesmo sem passar pelo menu (Task 4).
3. **Guarda "tem de alterar a password" contornada por `?m=`** → só `perfil` e `logout` acessíveis enquanto a flag estiver ativa (Task 3).
4. **Saída antes de `header()`** (espaços/BOM nos ficheiros de módulo) → redirects continuam a funcionar porque o módulo corre em buffer (Task 2, teste de redirect após POST em Task 4).
5. **Links antigos em emails/favoritos** → continuam a abrir o ecrã certo (Tasks 3–6).

---

### Task 1: `config/modular.php` e utilizadores de teste

**Files:**
- Create: `config/modular.php`, `api/v1/tests/web_rotas.php`, `api/v1/tests/_sessao.php`

**Interfaces (Produces):**
- `bloquearAcessoDireto(string $destino = 'index.php'): void` — se `count(get_included_files()) === 1` → `header('Location: '.$destino); exit;`
- `lerParametro(string $nome, string $omissao): string` — `filter_input(INPUT_GET, $nome, FILTER_UNSAFE_RAW)`; se não for string ou não casar `^[a-z_]{1,40}$` → `$omissao`.
- `validarModulo(string $m, string $a, array $permitidos): bool` — `$permitidos` = `['agenda' => ['listar','estado'], …]`; true só se a ação estiver na lista do módulo.
- `urlModulo(string $m, string $a, array $extra = []): string` — `'index.php?'.http_build_query(['m'=>$m,'a'=>$a]+$extra)`.
- `flash(string $tipo, string $texto): void` e `lerFlashes(): array` (lê e limpa `$_SESSION['flash']`).
- `tokenCsrf(): string` (cria `$_SESSION['csrf']` se faltar) e `campoCsrf(): string` (HTML `<input type="hidden" name="csrf" …>`) e `csrfValido(): bool` (`hash_equals` com `$_POST['csrf']`).
- `redirecionar(string $url): never`.
- `_sessao.php` (testes): `class Sessao { __construct(string $base); get(string $url): array{status,html,local}; post(string $url, array $dados): array; login(string $email, string $pass): bool; csrf(string $html): string }` com cookie jar próprio; e `criarUtilizadoresTeste(PDO $pdo): array` / `apagarUtilizadoresTeste(PDO $pdo)` — cria `gestor.teste@example.com` e `barbeiro.teste@example.com` (barbearia 1, password `Teste!2026`, `precisa_alterar_password = 0`).

- [ ] **Step 1:** Escrever `web_rotas.php` com `teste_modular_helpers()` (inclui `config/modular.php` em CLI): `lerParametro` devolve omissão para `'../x'`, `'Agenda'`, `''`; `validarModulo('agenda','listar',$p)` true, `('agenda','apagar',$p)` false, `('x','y',$p)` false; `urlModulo('servicos','listar',['id'=>3])` === `'index.php?m=servicos&a=listar&id=3'`. Mais o arranque/limpeza dos utilizadores de teste em `try/finally`.
- [ ] **Step 2:** `php api/v1/tests/web_rotas.php` → FALHA (ficheiro não existe).
- [ ] **Step 3:** Implementar `config/modular.php` e `_sessao.php`.
- [ ] **Step 4:** Correr → OK; `php -l` nos 3 ficheiros.
- [ ] **Step 5:** Commit `feat(web): helpers do modelo modular e testes de rotas`.

### Task 2: `gestao/index.php`, layout e dashboard

**Files:**
- Create: `gestao/layout/cabecalho.php` (de `includes/header.php`, sem a guarda de password e sem `require menu`), `gestao/layout/menu.php` (de `includes/menu.php`; links `urlModulo`, ativo por `$modulo`), `gestao/layout/rodape.php`, `gestao/mod/dashboard/ver.php` (corpo de `gestao/index.php` atual, sem `session_start`/auth/`require header`/`</body></html>`)
- Rewrite: `gestao/index.php` (controlador)
- Modify: `gestao/login.php` (após login → `index.php`, já é), `api/v1/tests/web_rotas.php`

**Interfaces (Produces):**
- `gestao/index.php` define `$MODULOS = ['gestor' => [...], 'barbeiro' => [...]]` com as ações da tabela 4.1 da spec (as ainda não migradas ficam de fora até à sua task), `$modulo`, `$acao`, `$pdo`, `$bid = (int)$_SESSION['barbearia_id']`, `$eGestor = $_SESSION['tipo'] === 'gestor'` — variáveis disponíveis para todos os módulos.
- Ordem: `session_start` → sem `utilizador_id` → `login.php` → guarda de password (só `perfil/*` e logout) → `lerParametro` → `validarModulo` (inválido → `dashboard/ver` + flash `'Página não encontrada.'`) → POST: `csrfValido()` senão flash + redirect → `ob_start(); require "mod/$modulo/$acao.php"; $conteudo = ob_get_clean();` → `require layout/cabecalho.php`, `menu.php`, flashes, `echo $conteudo`, `rodape.php`.

- [ ] **Step 1:** Testes em `web_rotas.php` (`teste_gestao_base`): anónimo `gestao/index.php?m=agenda&a=listar` → 302 `login.php`; gestor `index.php` → 200 contém "Dashboard" e não contém `Warning`/`Fatal`; `?m=../../config&a=db` → 200 dashboard; `?m[]=x` → 200 dashboard; GET direto `gestao/mod/dashboard/ver.php` → 302 para `index.php`.
- [ ] **Step 2:** Correr → FALHA.
- [ ] **Step 3:** Implementar. `gestao/includes/*` ficam até à Task 6 (outras páginas ainda os usam).
- [ ] **Step 4:** Correr os 3 runners → OK. Verificar no browser `http://127.0.0.1:8080/projeto/projeto/gestao/` (login de teste) que o dashboard está igual ao anterior.
- [ ] **Step 5:** Commit `feat(gestao): index.php modular com layout e dashboard`.

### Task 3: Agenda e Perfil (gestor e barbeiro)

**Files:**
- Create: `gestao/mod/agenda/listar.php`, `gestao/mod/agenda/estado.php`, `gestao/mod/perfil/editar.php`, `gestao/mod/perfil/guardar.php`, `gestao/mod/perfil/alterar_password.php`
- Rewrite as redirect: `gestao/agenda.php`, `gestao/perfil.php` (preservam query string relevante, ex.: `perfil.php?force=1` → `index.php?m=perfil&a=editar&force=1`; filtros da agenda)
- Modify: `gestao/index.php` (`$MODULOS`), `api/v1/tests/web_rotas.php`

**Interfaces:** `urlAgenda()` (hoje em `agenda.php`) passa a devolver `urlModulo('agenda','listar', filtros)`; formulários usam `campoCsrf()` e `action="<?= urlModulo('agenda','estado') ?>"`.

- [ ] **Step 1:** Testes: gestor e barbeiro → `m=agenda&a=listar` 200; POST `m=agenda&a=estado` sem csrf → não muda o estado (verificar na BD com uma marcação de teste criada no arranque); com csrf → muda e responde 302 para `a=listar`; `gestao/agenda.php` → 302 `index.php?m=agenda&a=listar`. Guarda: utilizador com `precisa_alterar_password=1` em `m=agenda` → 302 para `m=perfil&a=editar` (Review Focus 3); alterar password com csrf → flag a 0.
- [ ] **Step 2:** FALHA. **Step 3:** Implementar (mover código; `die('Erro de segurança CSRF.')` sai porque o CSRF passou a ser central). **Step 4:** Runners OK + browser (agenda com filtros, mudar estado, perfil). **Step 5:** Commit `feat(gestao): agenda e perfil como modulos`.

### Task 4: Módulos só de gestor — serviços, barbeiros, bancadas, fornecedores, definições

**Files:**
- Create: `gestao/mod/servicos/{listar,guardar,apagar}.php`, `gestao/mod/barbeiros/{listar,guardar,ativar,apagar}.php`, `gestao/mod/bancadas/{listar,ativar,apagar}.php`, `gestao/mod/fornecedores/listar.php` (+ ficheiros para as ações que `fornecedores.php` tiver — inventariar no Step 0), `gestao/mod/definicoes/{editar,guardar}.php`
- Rewrite as redirect: as 5 páginas antigas
- Modify: `gestao/index.php`, `api/v1/tests/web_rotas.php`

- [ ] **Step 0:** `grep -n "\$_POST\|\$_GET" gestao/fornecedores.php gestao/definicoes.php` e registar as ações reais no ledger.
- [ ] **Step 1:** Testes: gestor → `listar`/`editar` de cada módulo 200 sem `Warning`; barbeiro → cada um destes módulos devolve o dashboard com flash (não mostra o conteúdo); **barbeiro POST com csrf válido para `m=servicos&a=apagar`** → recusado e o serviço continua na BD (Review Focus 2); gestor cria serviço de teste (POST `guardar`) → 302 + aparece em `listar`; apaga-o (POST `apagar`) → desaparece (Review Focus 4: o 302 prova que não houve saída antes do `header`); páginas antigas → 302.
- [ ] **Step 2:** FALHA. **Step 3:** Implementar. **Step 4:** Runners OK + browser (cada ecrã como gestor). **Step 5:** Commit `feat(gestao): servicos, barbeiros, bancadas, fornecedores e definicoes como modulos`.

### Task 5: Stock e pedidos de reposição

**Files:**
- Create: `gestao/mod/stock/{listar,guardar,apagar,transferir,pedir_reposicao}.php`, `gestao/mod/pedidos/{listar,aprovar,rejeitar,entregar}.php`
- Rewrite as redirect: `gestao/stock.php`, `gestao/pedidos.php`
- Modify: `gestao/index.php` (stock: gestor todas; barbeiro só `listar` e `pedir_reposicao`), `api/v1/tests/web_rotas.php`

- [ ] **Step 1:** Testes: gestor `stock/listar` e `pedidos/listar` 200; barbeiro `stock/listar` 200 e POST `stock/transferir` recusado; barbeiro POST `stock/pedir_reposicao` (material de teste) → cria pedido `pendente`; gestor POST `pedidos/rejeitar` desse pedido → estado `rejeitado`; limpeza dos registos de teste. Respeitar `bancada_materiais` sem `id` e estados `pendente|aprovado|entregue|rejeitado`.
- [ ] **Step 2:** FALHA. **Step 3:** Implementar. **Step 4:** Runners OK + browser. **Step 5:** Commit `feat(gestao): stock e pedidos como modulos`.

### Task 6: Parte pública — `index.php` modular

**Files:**
- Create: `layout/cabecalho.php`, `layout/rodape.php` (tema: cores/fontes de `configBarbearia`), `mod/site/ver.php` (usa `layout/temas/classico.php` ou `minimalista.php` — os atuais `publico/layouts/*` sem `<html>/<head>/<body>`), `mod/marcar/{formulario,criar}.php`, `mod/verificar/{formulario,confirmar,reenviar}.php`
- Rewrite: `index.php` (raiz: controlador público; sem `slug` → `slug=rlbarber`), `publico/index.php`, `publico/marcar.php`, `publico/verificar.php` (redirects)
- Modify: `config/mailer*.php` e qualquer `header('Location: …publico…')` para os novos URLs; `api/v1/tests/web_marcar.php` (novo URL `index.php?m=marcar&slug=rlbarber`, POST para `?m=marcar&a=criar`)
- Delete: `gestao/includes/` (já sem utilizadores) — confirmar com `grep -rn "includes/" gestao`

- [ ] **Step 1:** Testes em `web_rotas.php`: `index.php?slug=rlbarber` 200 contém o nome da barbearia; `slug=nao-existe` → página de erro 404; `index.php?m=../config&a=db` → site; GET direto `mod/marcar/formulario.php` → 302; `publico/index.php?slug=rlbarber` e `publico/marcar.php?slug=rlbarber` → 302 para os novos; `web_marcar.php` atualizado (6 asserções) verde.
- [ ] **Step 2:** FALHA. **Step 3:** Implementar. **Step 4:** Runners OK + browser (site nos dois temas, marcar, verificar com código do modo dev). **Step 5:** Commit `feat(publico): index.php modular para site, marcar e verificar`.

### Task 7: Fase 2 — classes em `objects/`

**Files:**
- Create: `objects/{Servico,Barbeiro,Fornecedor,Bancada,Material,PedidoReposicao,Configuracao}.php`, `api/v1/tests/web_objects.php`
- Modify: os módulos de gestão correspondentes para usarem as classes

**Interfaces:** cada classe `__construct(PDO $pdo, int $barbeariaId)`; métodos só os usados pelos módulos: `read(): array`, `readOne(int $id): ?array`, `create(array $d): int`, `update(int $id, array $d): bool`, `delete(int $id): bool` (+ métodos próprios, ex.: `Barbeiro::ativar(int $id, bool $ativo)`, `PedidoReposicao::aprovar(int $id)`), SQL igual ao atual, sempre com `barbearia_id = ?`.

- [ ] **Step 1:** `web_objects.php`: para `Servico` e `Fornecedor`: create → readOne → update → read contém → delete → readOne null; isolamento: `readOne` com outro `barbeariaId` → null. Para as restantes, um teste de `read()` por classe.
- [ ] **Step 2:** FALHA. **Step 3:** Implementar classes e trocar o SQL inline dos módulos pelas chamadas. **Step 4:** Os 4 runners OK (rotas prova que os módulos continuam iguais). **Step 5:** Commit `feat(web): classes por entidade em objects (CRUD POO)`.

### Task 8: Publicação e documentação

**Files:**
- Create: `C:\projetosAndroid\projeto\deploy-ua\atualizacao-5\` (estrutura nova + `LER-PRIMEIRO.md`)
- Modify: `README.md` do web (secção "Arquitetura modular": fluxograma, mapa módulo→ficheiro, como acrescentar um módulo)

- [ ] **Step 1:** Montar o pacote com `git diff --name-status d95bc45..HEAD` (só ficheiros do site; sem `api/v1/tests`, `docs`); o guia diz que pastas novas enviar, que ficheiros substituir, e que `gestao/includes/` se apaga **depois** de tudo enviado.
- [ ] **Step 2:** Correr os 4 runners uma última vez e `run.php --so-leitura` contra a UA depois de o utilizador publicar.
- [ ] **Step 3:** Commit `docs: arquitetura modular no README e pacote da atualizacao 5`.
