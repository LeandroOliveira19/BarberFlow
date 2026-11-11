# BarberFlow Web — Aplicação Modular (Design)

Data: 2026-11-11 · Grupo 09 · UC Programação em Ambiente Web (requisito do professor, atividade 06)

## 1. Objetivo

Reorganizar a parte web para cumprir o requisito:

> "O projeto deverá estar modular, isto é, seguir a lógica da atividade 06 onde o ficheiro index.php trata de toda a lógica de validação e verificações de segurança, carrega a estrutura da página e carrega o ficheiro do módulo pretendido."

**Sem mudar o comportamento**: o que cada ecrã faz hoje continua igual. É só reorganização (mais as proteções que o modelo exige).

### Decisões fechadas (utilizador, 2026-11-11)

| Tema | Decisão |
|---|---|
| Âmbito | Dois pontos de entrada modulares separados: **`gestao/index.php`** (área autenticada) e **`index.php`** na raiz (parte pública). |
| API | `api/v1/` **não muda** (já é controlador único; a app Android depende dela). |
| Pasta de trabalho | Sempre `C:\xampp\htdocs\projeto\projeto` (repositório git), branch `web-modular`. A UA recebe pacotes via WinSCP. |
| Compatibilidade | URLs antigos continuam a funcionar (redirecionam para os novos). |

## 2. Estrutura de pastas (alvo)

```
projeto/
├── index.php                  ← ENTRADA PÚBLICA   ?m=site|marcar|verificar &a=… &slug=…
├── config/                    ← igual (db, credenciais, marcacoes, reservas, mailer, mailer_codigo, reviews, movimentos)
│   └── modular.php            ← NOVO: helpers do modelo (bloquearAcessoDireto, validarModulo, urlModulo, flash)
├── objects/                   ← NOVO (fase 2): uma classe por entidade com CRUD (POO, como nas aulas)
├── mod/                       ← NOVO: módulos públicos
│   ├── site/ver.php
│   ├── marcar/formulario.php, criar.php
│   └── verificar/formulario.php, confirmar.php, reenviar.php
├── layout/                    ← NOVO: estrutura pública (temas classico/minimalista como "moldura")
├── gestao/
│   ├── index.php              ← ENTRADA DA GESTÃO ?m=agenda&a=listar…
│   ├── login.php, logout.php  ← únicos ficheiros chamados diretamente (além do index)
│   ├── layout/                ← cabecalho.php, menu.php, rodape.php (vêm de includes/header.php e menu.php)
│   └── mod/<modulo>/<acao>.php
├── api/v1/                    ← não muda
└── publico/*.php, gestao/<pagina>.php  ← ficam como redirecionamentos (compatibilidade)
```

## 3. Fluxo de cada `index.php` (fluxograma da atividade 06)

1. **Segurança de sessão**
   - Gestão: `session_start()`; sem `$_SESSION` de utilizador → `login.php`. Mantém a guarda atual "tem de alterar a password" (só `perfil` e `logout` permitidos).
   - Público: sessão para CSRF; resolve a barbearia pelo `slug` (`barbearias.slug`, `ativa = 1`); slug inválido → página de erro.
2. **Obter módulo e ação**: `filter_input(INPUT_GET, 'm'|'a', …)`; só `[a-z_]+`.
3. **Validar** contra uma **lista branca por perfil** (tabela em `index.php`), e confirmar `file_exists("mod/$m/$a.php")`. Inválido → módulo por omissão (dashboard / site) com mensagem.
4. **Incluir o módulo com a saída em buffer** (`ob_start()` → `require` → `ob_get_clean()`), para que as ações POST possam fazer `header('Location: …'); exit;` (padrão POST → Redirect → GET) antes de qualquer HTML.
5. **Desenhar a estrutura**: cabeçalho, menu (módulo ativo realçado, opções conforme o perfil), conteúdo do módulo, rodapé.

**Acesso direto bloqueado**: todos os ficheiros de `mod/`, `layout/`, `objects/` começam por `bloquearAcessoDireto()`, que redireciona para o `index.php` correspondente quando `count(get_included_files()) == 1` (padrão do slide 19).

**Mensagens entre pedidos**: `flash($tipo, $texto)` guarda em `$_SESSION` e o layout mostra-as uma vez (substitui as mensagens que hoje vivem em variáveis da página).

## 4. Módulos e ações

### 4.1 Gestão (`gestao/index.php`)

| Módulo `m` | Ações `a` (ficheiros) | Perfis | Vem de |
|---|---|---|---|
| `dashboard` | `ver` (omissão) | gestor, barbeiro | `index.php` |
| `agenda` | `listar`, `estado` (POST) | gestor, barbeiro | `agenda.php` |
| `barbeiros` | `listar`, `guardar`, `ativar`, `apagar` | gestor | `barbeiros.php` |
| `servicos` | `listar`, `guardar`, `apagar` | gestor | `servicos.php` |
| `stock` | `listar`, `guardar`, `apagar`, `transferir`, `pedir_reposicao` | gestor, barbeiro (as ações de gestor continuam protegidas como hoje) | `stock.php` |
| `bancadas` | `listar`, `ativar`, `apagar` | gestor | `bancadas.php` |
| `pedidos` | `listar`, `aprovar`, `rejeitar`, `entregar` | gestor | `pedidos.php` |
| `fornecedores` | `listar` (+ ações que a página já tem) | gestor | `fornecedores.php` |
| `definicoes` | `editar`, `guardar` | gestor | `definicoes.php` |
| `perfil` | `editar`, `guardar`, `alterar_password` | gestor, barbeiro | `perfil.php` |

Ações de leitura (`listar`, `ver`, `editar`) desenham HTML; ações de escrita (POST) validam CSRF + perfil, executam, fazem `flash()` e redirecionam para a ação de leitura. Um GET a uma ação de escrita → 405 / redireciona.

### 4.2 Público (`index.php`)

| Módulo `m` | Ações `a` | Vem de |
|---|---|---|
| `site` | `ver` (omissão) | `publico/index.php` + `publico/layouts/*` |
| `marcar` | `formulario` (omissão), `criar` (POST) | `publico/marcar.php` |
| `verificar` | `formulario` (omissão), `confirmar` (POST), `reenviar` (POST) | `publico/verificar.php` |

A "estrutura" pública é o tema da barbearia (`classico`/`minimalista`): `layout/` passa a ter o `<head>`, as cores do tema e o rodapé; os temas deixam de ser páginas completas e passam a desenhar só o corpo do módulo `site`.

### 4.3 Redirecionamentos de compatibilidade

- `publico/index.php?slug=X` → `../index.php?slug=X`; `publico/marcar.php?…` → `../index.php?m=marcar&…`; `publico/verificar.php` → `../index.php?m=verificar`.
- `gestao/agenda.php` → `index.php?m=agenda&a=listar` (idem para cada página).
- Raiz `index.php` sem `slug` → `slug=rlbarber` (comportamento atual).
- Links nos emails/mailer que apontem para páginas antigas passam a usar os novos URLs.

## 5. Classes por entidade — `objects/` (fase 2)

Como nos slides (CRUD + POO): `Servico`, `Barbeiro` (utilizadores tipo barbeiro), `Fornecedor`, `Bancada`, `Material`, `PedidoReposicao`, `Marcacao`, `Configuracao`. Cada classe recebe o `PDO` e o `barbearia_id` no construtor e tem `read()`, `readOne($id)`, `create()`, `update()`, `delete()` (só os que o módulo usa), com prepared statements e sempre filtradas por `barbearia_id`.

Os módulos de gestão passam a usar estas classes em vez de SQL inline. **A lógica de marcações não muda de sítio**: continua em `config/marcacoes.php` + `config/reservas.php` (partilhada com a API) — a classe `Marcacao` só cobre leitura/estado da agenda.

A fase 2 vem depois da fase 1 (encaminhamento) estar completa e testada; pode ser entregue sem ela se o tempo apertar.

## 6. Testes

Todos em PHP CLI contra o `php -S` local, em `api/v1/tests/` (já existe o runner):

- **Regressão**: `run.php` (API, 97) e `web_marcar.php` (formulário público, atualizado para o novo URL) continuam verdes.
- **Novo `web_rotas.php`** (cria um gestor e um barbeiro de teste na BD local com password conhecida e apaga-os no fim):
  - anónimo em `gestao/index.php?m=agenda` → 302 `login.php`;
  - gestor: cada módulo/ação de leitura da tabela 4.1 responde 200 sem erros PHP;
  - barbeiro em `m=fornecedores` (só gestor) → não mostra o módulo (dashboard + mensagem);
  - `m=../../config&a=db` e módulos inexistentes → omissão, nunca inclui fora de `mod/`;
  - pedido direto a `gestao/mod/agenda/listar.php` e a `mod/marcar/formulario.php` → redireciona para o index;
  - URLs antigos (`gestao/agenda.php`, `publico/index.php?slug=rlbarber`) → 302 para o novo;
  - ação POST sem CSRF → recusada;
  - fluxo de escrita de exemplo: criar e apagar um serviço de teste pela gestão.
- **Manual (utilizador)**: percorrer o painel no browser como gestor e barbeiro; site público e marcação.

## 7. Publicação na UA

Pacote `deploy-ua/atualizacao-5` com a nova estrutura e um guia: enviar as pastas novas (`mod/`, `layout/`, `objects/`, `gestao/mod/`, `gestao/layout/`, `config/modular.php`) e substituir os ficheiros que passam a redirecionamentos. Nada na BD.

## 8. Fora de âmbito

Mudanças de funcionalidade/UI, campos novos, o fluxo da app Android (fica para depois — ver pendentes do mobile), migração para o monorepo GitHub (`web/` + `mobile/`), alojamento com domínio próprio.
