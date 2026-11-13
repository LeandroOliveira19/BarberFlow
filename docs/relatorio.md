# BarberFlow — Relatório (Programação em Ambiente Web)

**UC:** Programação em Ambiente Web (PAW) · **ESAN — Universidade de Aveiro** · **Grupo 09**
**Servidor:** <https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g9/projeto/>
**Tecnologias:** PHP 8, MariaDB, HTML, CSS e JavaScript, **sem frameworks** (a única biblioteca externa desenha o QR code do 2FA, ver §4).

O BarberFlow é uma plataforma SaaS multi-tenant para barbearias:
- **site público:** serviços, equipa, reviews e marcação online com confirmação por código;
- **área de gestão:** agenda, serviços, equipa, bancadas, stock, fornecedores e definições, com perfis **gestor** e **barbeiro**;
- **API REST:** usada pela app Android do cliente.

---

## 1. Arquitetura modular (atividade 06)

Há dois pontos de entrada. Cada um faz **toda a validação e segurança**, desenha a estrutura da página e carrega o módulo pedido em `?m=<módulo>&a=<ação>`:

| Entrada | Módulos | Estrutura |
|---|---|---|
| `index.php` (público, `?slug=`) | `mod/site`, `mod/marcar`, `mod/verificar` | `layout/estrutura.php` |
| `gestao/index.php` (gestor/barbeiro) | `gestao/mod/<módulo>/<ação>.php` | `gestao/layout/` |

O fluxo de um pedido no `index.php`:
1. **Sessão:** existe uma sessão, ou um slug válido no site público.
2. **Lista branca:** `m` e `a` são validados contra a lista do perfil (`$MODULOS`).
3. **CSRF:** os POST são validados.
4. **Módulo:** é incluído com a saída em buffer, para que as ações de escrita possam fazer POST → Redirect → GET.
5. **Página:** é desenhada com cabeçalho, menu, mensagens e conteúdo.

Os módulos não podem ser abertos diretamente: cada um começa por `if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }`.

As páginas que têm de funcionar **sem sessão** ficam fora do `index.php`, tal como o `login.php`: `gestao/login.php`, `gestao/ativar.php` e `gestao/recuperar.php`.

## 2. Base de dados

Ver **[base-de-dados.md](base-de-dados.md)**: diagrama ER, explicação de cada tabela, a vista `vw_agenda` e os ficheiros SQL.

## 3. Registo, login e sessões

- **Login** (`gestao/login.php`):
  - CSRF;
  - `password_verify` (bcrypt);
  - **limite de tentativas** (5 por email ou 20 por IP em 15 min, tabela `tentativas_login`);
  - **`session_regenerate_id(true)`** no fim, contra fixação de sessão;
  - **2.º fator** quando a conta o tem ativo.
- **Registo de barbeiros por convite:**
  1. O gestor adiciona o barbeiro (`gestao/mod/barbeiros/criar.php`).
  2. O barbeiro recebe um **link de ativação** (válido 48 h e de uso único; na BD fica só o `sha256`).
  3. Em `gestao/ativar.php` escolhe a própria senha.
  4. Se o email não sair, por exemplo na UA, o gestor vê o link com um botão "Copiar" para o enviar por WhatsApp. Também há "Reenviar convite".
  5. Uma conta por ativar não consegue entrar.
- **Sessão:** guarda `utilizador_id`, `barbearia_id` e `tipo`. Todas as páginas da gestão passam pelo `gestao/index.php`, que recusa quem não tem sessão. O logout destrói a sessão.
- **Mudar a senha:** exige um **código enviado por email** (OTP de 6 dígitos, 15 min, 5 tentativas, uso único).
- **"Esqueci-me da senha":** `gestao/recuperar.php`.
  - A resposta é igual para emails que existem e que não existem, e o tempo de resposta também, porque se faz o mesmo trabalho.
  - Limite de 5 pedidos por email em 15 min.
  - O código **nunca aparece no ecrã para uma conta real**, só para as contas de teste (`example.com`). De outra forma, quem soubesse o email ficava com a conta.
- **Limite do 2FA:** os códigos errados da app contam no mesmo limite de tentativas da conta. Repetir a senha não reinicia a contagem.
- **Regra de senha:** pelo menos 8 caracteres, com letras e números.

## 4. Segurança

| Medida | Onde |
|---|---|
| **Perfis e privilégios:** a lista branca de módulos e ações por perfil; o barbeiro só vê a sua agenda e o seu stock | `gestao/index.php` (`$MODULOS`), módulos com `$eGestor` |
| **Multi-tenant:** todas as consultas filtram por `barbearia_id` | todos os módulos, `objects/`, `vw_agenda` |
| **CSRF** em todos os formulários POST, validado de forma central | `config/modular.php`, `gestao/index.php`, `index.php` |
| **2FA com app autenticadora (TOTP, RFC 6238)**, opcional, ativado no Perfil com QR code | `config/totp.php` (feito à mão), `gestao/mod/perfil/ativar_dois_fatores.php`, `gestao/login.php` |
| **OTP por email:** confirmação de marcações; mudar e recuperar a senha | `config/reservas.php`, `config/contas.php`, tabela `codigos_seguranca` |
| **Limite de tentativas** de login | `config/contas.php`, tabela `tentativas_login` |
| **Vista SQL** `vw_agenda` | `sql/2026-11-13_seguranca_contas.sql`, `gestao/mod/agenda/listar.php` |
| **Senhas e códigos só com hash** (bcrypt, sha256 nos convites) | `utilizadores`, `codigos_seguranca`, `verificacoes` |
| **Prepared statements** (PDO) | `config/db.php` e todas as consultas |
| **XSS:** toda a saída passa por `htmlspecialchars` (`e()`) | `config/marcacoes.php` e todas as vistas |
| **Pesquisa segura:** `%` e `_` escritos pelo utilizador são escapados no `LIKE` | `config/modular.php` (`likeSeguro`) |
| **Cookies:** sessão e `bf_email` com `HttpOnly` e `SameSite=Lax` (`Secure` em HTTPS) | `config/contas.php` |

**Biblioteca externa:** `assets/js/qrcode.js` (qrcode-generator, MIT) serve só para desenhar o QR code do 2FA no browser. Não é um framework e está guardada no projeto, sem CDN. A chave também aparece em texto.

## 5. Tecnologias base

- **Estrutura modular:** §1.
- **Cookies:**
  - a sessão PHP;
  - **"Lembrar o meu email"** no login (`bf_email`, 30 dias, só guarda o email e nunca a senha).
- **Uploads:** fotografia de perfil dos barbeiros (`gestao/mod/perfil/guardar.php`), com a extensão e o tamanho validados, para a pasta `uploads/barbeiros/`.
- **Mail:**
  - pela API do Brevo: códigos de marcação (`config/mailer_codigo.php`), códigos de segurança e convites (`config/mailer_contas.php`);
  - no servidor da UA as ligações externas estão bloqueadas, por isso, com `api_codigo_se_email_falhar`, o código ou o link aparece no ecrã (`config/politica_codigo.php`).

## 6. Tecnologias avançadas: API REST, JWT e app mobile

A API v1 (`api/v1/index.php`) responde sempre em JSON, com códigos HTTP corretos (200/201/400/404/409/410/422/429).

| Método | Rota (`?r=`) | Função |
|---|---|---|
| GET | `barbearias/{slug}` | Dados, horário e regras da barbearia |
| GET | `barbearias/{slug}/servicos` · `/equipa` · `/reviews` | Catálogo, equipa, reviews |
| GET | `barbearias/{slug}/disponibilidade` | Horários livres de um barbeiro num dia |
| POST | `barbearias/{slug}/marcacoes` | Criar marcação (envia código) |
| POST | `marcacoes/{id}/verificar` · `/reenviar` | Confirmar com o código; reenviar |
| GET | `marcacoes?tokens=id:token,…` | As marcações do cliente |
| POST | `marcacoes/{id}/cancelar` · `/remarcar` | Cancelar ou remarcar (até X horas antes) |

**JWT (HS256), feito sem bibliotecas** (`config/jwt.php`):
- Quando o cliente confirma a marcação, a API devolve um JWT `{"sub": marcação, "cli": cliente, "iat": …}` assinado com o segredo do servidor.
- A app guarda-o e envia-o para listar, cancelar e remarcar.
- Tokens com `alg: none` ou outro algoritmo, assinatura errada ou payload alterado são recusados.
- Os tokens antigos (HMAC) das apps já instaladas continuam aceites.

**Interação mobile:** a app Android do cliente (Kotlin, Retrofit, Room, WorkManager) usa esta API. É um repositório à parte, entregue em Programação Mobile.

## 7. CRUD e pesquisa

- **CRUD na gestão:** serviços, barbeiros, bancadas, fornecedores, stock, pedidos de reposição e definições. Há classes em `objects/` para Serviço, Fornecedor, Bancada e Configuração.
- **Pesquisa:**
  - na **Agenda**, por nome ou telemóvel do cliente, combinada com os filtros de dia, semana, estado e barbeiro (`gestao/mod/agenda/listar.php`, sobre a `vw_agenda`);
  - no **Stock**.

## 8. Testes automáticos

Correm com o servidor local ligado (`php -S 127.0.0.1:8080 -t C:\xampp\htdocs`).

| Comando | O que testa |
|---|---|
| `php api/v1/tests/run.php` | API: marcações, códigos, JWT, cancelar e remarcar, concorrência. Com `--so-leitura` corre contra a UA |
| `php api/v1/tests/web_rotas.php` | Gestão e site: rotas, perfis, CSRF, login, 2FA, senhas, convites, pesquisa |
| `php api/v1/tests/web_seguranca.php` | TOTP e base32 com os vetores oficiais do RFC 6238 |
| `php api/v1/tests/web_objects.php` | Classes de `objects/` |
| `php api/v1/tests/web_marcar.php` | Formulário de marcação do site |

## 9. Instalação

1. Copiar o projeto para o servidor PHP (ex.: `htdocs` do XAMPP).
2. Importar o dump da BD `esan-dsg09` (não está no repositório, porque tem dados reais) e correr, por ordem, `movimentos_stock.sql` e os ficheiros de `sql/` (§2).
3. Em `config/`, copiar `credenciais.example.php` para `credenciais.php` e preencher a BD, o `api_segredo` (`bin2hex(random_bytes(32))`) e a chave do Brevo.
   - Num servidor sem email (UA): `'api_codigo_se_email_falhar' => true`.
   - Em produção: as duas opções de código no ecrã desligadas.
4. Abrir `/projeto/projeto/` (site) e `/projeto/projeto/gestao/login.php` (gestão).

## 10. Utilizadores de teste

Ficam na barbearia de demonstração **Navalha de Ouro**, para não darem acesso aos clientes reais da RL Barber (`sql/utilizadores_teste.sql`):

| Perfil | Email | Senha |
|---|---|---|
| Gestor | `avaliacao.gestor@example.com` | `Avaliacao!2026` |
| Barbeiro | `avaliacao.barbeiro@example.com` | `Barbeiro!2026` |

`example.com` é um domínio reservado. Os códigos por email aparecem no ecrã no servidor da UA.

Como estas senhas são públicas, alguém pode mudá-las ou ativar o 2FA nestas contas. Para as repor, basta voltar a correr `sql/utilizadores_teste.sql`.

## 11. Critérios de avaliação → onde está

| Critério (valores) | Onde está no projeto |
|---|---|
| **CRUD/Pesquisa de entidades (2)** | `gestao/mod/servicos`, `barbeiros`, `bancadas`, `fornecedores`, `stock`, `pedidos`, `definicoes`; `objects/*.php`; pesquisa em `gestao/mod/agenda/listar.php` |
| **Base de dados: modelos, diagramas e explicação (1)** | [`docs/base-de-dados.md`](base-de-dados.md), `sql/` |
| **Registo/Login de utilizadores e sessões (2)** | `gestao/login.php`, `gestao/ativar.php`, `gestao/recuperar.php`, `gestao/mod/barbeiros/criar.php`, `config/contas.php`, `gestao/index.php`, `gestao/logout.php` |
| **Interação segura e abstrata com a BD (1)** | `config/db.php` (PDO), `objects/`, `config/reservas.php`, `vw_agenda` |
| **Tecnologias base: estrutura, cookies, uploads, mail (2)** | `index.php` + `mod/` + `layout/`, `gestao/index.php` + `gestao/mod/`; cookie `bf_email`; `gestao/mod/perfil/guardar.php`; `config/mailer_*.php` |
| **Tecnologias avançadas: API REST, JWT, mobile (3)** | `api/v1/index.php`, `api/v1/_lib.php`, `config/jwt.php`; app Android |
| **Segurança: privilégios, perfis, vistas, 2FA, OTP (2)** | `$MODULOS` em `gestao/index.php`; `config/totp.php` + `gestao/mod/perfil/ativar_dois_fatores.php`; `config/contas.php` (OTP, tentativas); `vw_agenda`; CSRF em `config/modular.php` |
| **Funcionamento e funcionalidades (1)** | Servidor da UA; testes em `api/v1/tests/` |
| **Complexidade da solução (2)** | Multi-tenant, API + app, disponibilidade com concorrência (`FOR UPDATE`), JWT, TOTP e convites feitos à mão |
| **Design e interação (2)** | Temas do site (`mod/site/temas/`), gestão com layout próprio (`gestao/layout/`), responsivo |
| **Documentação (2)** | Este relatório, [`base-de-dados.md`](base-de-dados.md), `README.md`, comentários no código |
