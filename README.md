# BarberFlow — Gestão de barbearias (SaaS)

**UC:** Programação em Ambiente Web (PAW) · **ESAN — Universidade de Aveiro** · **Grupo 09**
**Servidor:** <https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g9/projeto/>

O BarberFlow é uma plataforma **multi-tenant**: várias barbearias usam a mesma instalação, cada uma só com os seus dados.

- **Site público:** serviços, equipa, reviews e **marcação online** confirmada por código.
- **Gestão:** agenda, equipa (convites), serviços, bancadas, stock, pedidos, fornecedores e definições, com perfis **gestor** e **barbeiro**.
- **API REST** (`api/v1`): usada pela **app Android** do cliente, com tokens **JWT**.

PHP 8 + MariaDB + HTML/CSS/JavaScript, **sem frameworks**.

## Documentação

| Documento | Para quem |
|---|---|
| **[Manual de utilização](docs/manual.md)** | Quem usa: cliente, gestor, barbeiro; contas de teste; problemas comuns |
| **[Relatório](docs/relatorio.md)** | Avaliação: arquitetura, segurança, API, testes e tabela "critério → onde está" |
| **[Base de dados](docs/base-de-dados.md)** | Diagrama ER, tabelas, vista `vw_agenda`, ficheiros SQL |

## Contas de teste

Na barbearia de demonstração **Navalha de Ouro**, sem clientes reais:

| Perfil | Email | Senha |
|---|---|---|
| Gestor | `avaliacao.gestor@example.com` | `Avaliacao!2026` |
| Barbeiro | `avaliacao.barbeiro@example.com` | `Barbeiro!2026` |

Entrada da gestão: `…/projeto/projeto/gestao/login.php`.

---

## Estrutura do projeto

```
index.php            Ponto de entrada do SITE PÚBLICO (valida tudo e carrega mod/<módulo>)
mod/                 Módulos do site: site/ (página + temas), marcar/, verificar/
layout/              Estrutura HTML do site
gestao/
  index.php          Ponto de entrada da GESTÃO (sessão, perfil, CSRF, lista branca de módulos)
  login.php          Login (CSRF, limite de tentativas, 2FA, "lembrar email")
  ativar.php         Ativação da conta de um barbeiro convidado
  recuperar.php      "Esqueci-me da senha"
  mod/<módulo>/      Módulos: agenda, barbeiros, bancadas, servicos, stock, pedidos, fornecedores, definicoes, perfil, dashboard
  layout/            Cabeçalho, menu e rodapé da gestão
api/v1/              API REST da app (index.php) e testes automáticos (tests/)
objects/             Classes por entidade (Servico, Fornecedor, Bancada, Configuracao)
config/              Configuração e bibliotecas próprias:
                       db.php · credenciais.php (fora do git) · modular.php (rotas, CSRF, flash)
                       sessao.php (sessão segura) · contas.php (login, códigos, convites)
                       jwt.php · totp.php (2FA) · reservas.php · marcacoes.php · mailer_*.php
assets/js/qrcode.js  Biblioteca só de desenho do QR code do 2FA (MIT)
sql/                 Migrações da BD (correr por ordem de data) e utilizadores de teste
docs/                Manual, relatório e base de dados (docs/superpowers/ = notas de desenho)
publico/             Endereços antigos que reencaminham para o index.php
uploads/             Fotografias dos barbeiros (criada pelo sistema; fora do git)
```

## Instalação local (XAMPP)

1. **Código:** clona para dentro do `htdocs`, ex.: `C:\xampp\htdocs\projeto\projeto`.
2. **Base de dados:**
   - cria a BD `esan-dsg09` e importa o dump do grupo (não está no repositório, porque tem dados reais);
   - corre os ficheiros de `sql/` **por ordem de data**:
     1. `2026-11-10_movimentos_stock.sql`
     2. `2026-11-11_marcacoes_para_nome.sql`
     3. `2026-11-13_seguranca_contas.sql`
     4. opcional: `utilizadores_teste.sql`
3. **Configuração** (pasta `config/`):
   - copia `db.example.php` para **`db.php`** (não precisa de edição);
   - copia `credenciais.example.php` para **`credenciais.php`** e preenche como na tabela abaixo.
4. **Arrancar:** com o Apache do XAMPP, ou `php -S 127.0.0.1:8080 -t C:\xampp\htdocs`.
5. **Abrir:**
   - site: `http://127.0.0.1:8080/projeto/projeto/`;
   - gestão: `http://127.0.0.1:8080/projeto/projeto/gestao/login.php`.

### `config/credenciais.php`

| Chave | Local (XAMPP) | Servidor UA | Produção |
|---|---|---|---|
| `db_host`, `db_user`, `db_pass` | `127.0.0.1`, `root`, `''` | (não definir) | dados do alojamento |
| `user`, `pass` | — | utilizador e senha da BD da UA | — |
| `api_segredo` | `bin2hex(random_bytes(32))` | idem (outro valor) | idem (outro valor) |
| `brevo_api_key` | opcional | inútil (a UA bloqueia saídas) | **obrigatória** (emails) |
| `apify_token`, `google_api_key` | opcional | inútil | opcional |
| `api_dev_devolver_codigo` | `true` (códigos no ecrã) | `false` | `false` |
| `api_codigo_se_email_falhar` | `false` | `true` | `false` |

Os ficheiros `db.php` e `credenciais.php` estão no `.gitignore`: **nunca vão para o GitHub**.

## Publicar no servidor da UA (WinSCP)

1. **BD (phpMyAdmin da UA):** correr os ficheiros novos de `sql/`.
2. **Ficheiros:** copiar as pastas do projeto para `projeto/` no servidor, **exceto** `config/db.php`, `config/credenciais.php`, `api/v1/tests/` e `docs/`.
3. **No servidor:** o `credenciais.php` tem de ter `'api_codigo_se_email_falhar' => true`.

## Testes automáticos

Com o servidor local ligado, na pasta do projeto:

```bash
php api/v1/tests/run.php            # API: marcações, JWT, cancelar/remarcar, concorrência
php api/v1/tests/web_rotas.php      # Gestão e site: rotas, perfis, CSRF, login, 2FA, senhas, convites, privilégios
php api/v1/tests/web_seguranca.php  # TOTP e base32 (vetores do RFC 6238)
php api/v1/tests/web_objects.php    # Classes de objects/
php api/v1/tests/web_marcar.php     # Formulário de marcação do site
```

Os testes criam e apagam os seus próprios dados (emails `@example.com`). O `run.php <url>/api/v1/index.php --so-leitura` pode correr contra a UA sem criar dados.

## Arquitetura modular (atividade 06)

O site e a gestão têm cada um **um só ponto de entrada**. Esse `index.php` faz as validações e verificações de segurança, desenha a estrutura da página e inclui o módulo pedido em `?m=<módulo>&a=<ação>`:

```
pedido ──► index.php ──► 1. sessão / barbearia válida?   (não → login ou página 404)
                         2. m e a válidos para o perfil?  (lista $MODULOS; não → "Página não encontrada")
                         3. POST com token CSRF válido?   (não → recusado)
                         4. ob_start + require mod/<m>/<a>.php   (escritas: flash + POST → Redirect → GET)
                         5. cabeçalho + menu + mensagens + conteúdo + rodapé
```

- **Abrir um módulo diretamente no browser não funciona:** cada módulo começa com `if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }`, que reencaminha para o `index.php`.
- **Acrescentar um módulo:**
  1. criar `gestao/mod/<novo>/<ação>.php`, com a linha de proteção acima;
  2. juntar-lhe a ação na lista `$MODULOS` do perfil, em `gestao/index.php`;
  3. se for para aparecer no menu, juntá-lo a `gestao/layout/menu.php`.

## Segurança (resumo)

| Proteção | Como |
|---|---|
| Perfis | Lista branca de módulos por perfil; o barbeiro só vê os seus dados |
| Separação entre barbearias | Todas as consultas filtram por `barbearia_id` |
| Formulários e base de dados | CSRF em todos os POST; *prepared statements*; saída escapada |
| Senhas | bcrypt; regra de 8+ caracteres com letras e números; mudar ou recuperar com código por email (OTP) |
| Login | **2FA (TOTP)** opcional; limite de tentativas; sessão regenerada |
| Cookies | `HttpOnly` + `SameSite=Lax` + `use_strict_mode` |
| Convites de barbeiros | Link de uso único, 48 h; só o hash fica na BD |
| App | Tokens **JWT HS256** |
| Testes | Uma auditoria automática prova que um barbeiro não faz ações de gestor e que um gestor de outra barbearia não lê nem altera nada |

Detalhes no [relatório](docs/relatorio.md#4-segurança).

---
Desenvolvido por **Leandro Oliveira** (Grupo 09).
