# BarberFlow — Sistema de Gestão de Barbearias (SaaS)

**UC:** Programação em Ambiente Web (PAW)  
**Instituição:** ESAN — Universidade de Aveiro  
**Grupo:** 09  

# Sobre o Projeto
O BarberFlow é uma plataforma web desenhada para a gestão de barbearias. O sistema foi construído com uma arquitetura **SaaS (Software as a Service) Multi-Tenant**, o que significa que várias barbearias podem usar a mesma instalação do sistema, mas os seus dados (agendas, stock, serviços) estão completamente isolados.

O projeto inclui:
- **Site Público:** Uma landing page dinâmica para os clientes verem os serviços, preços, equipa, avaliações do Google Maps e fazerem marcações online.
- **Painel de Gestão:** Uma área restrita (com login) para gestores e barbeiros gerirem a agenda, stock, serviços e definições da sua barbearia.

# Tecnologias Utilizadas
- **Backend:** PHP 8.3 (Puro, Orientado a Objetos)
- **Base de Dados:** MariaDB (MySQL)
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Integrações:**
  - **Apify:** Para recolha de reviews reais do Google Maps.
  - **Brevo API:** Para envio automático de emails de boas-vindas com credenciais.
  - **Google Places API:** Para pesquisa automática de moradas.

# Como Instalar e Configurar

1. Clona o repositório para a tua máquina local (ex: dentro da pasta `htdocs` do XAMPP).
2. Importa a base de dados `esan-dsg09.sql` (presente na raiz) para o teu servidor MySQL local.
3. Na pasta `config/`:
   - Renomeia `credenciais.example.php` para `credenciais.php` e preenche com as tuas chaves de API.
   - Renomeia `db.example.php` para `db.php` e ajusta as configurações de ligação à base de dados.
4. Acede ao site através de `http://localhost/projeto/projeto/`.

# Arquitetura modular (atividade 06)

O site público e a área de gestão têm cada um **um só ponto de entrada**. Esse `index.php`
faz todas as validações e verificações de segurança, desenha a estrutura da página e
inclui o ficheiro do módulo pedido no endereço: `?m=<módulo>&a=<ação>`.

```
pedido ──► index.php ──► 1. sessão / barbearia válida?   (não → login ou página 404)
                         2. m e a válidos para o perfil?  (lista $MODULOS; não → módulo por omissão)
                         3. POST com token CSRF válido?   (não → recusado)
                         4. ob_start + require mod/<m>/<a>.php
                              └─ ações de escrita: flash() + redirecionar()  (POST → Redirect → GET)
                         5. cabeçalho + menu + mensagens + conteúdo do módulo + rodapé
```

| Entrada | Módulos (`m`) e ações (`a`) | Ficheiros |
|---|---|---|
| `index.php` (público, `?slug=<barbearia>`, por omissão `rlbarber`) | `site/ver`, `marcar/formulario`, `verificar/formulario` | `mod/<m>/<a>.php`, estrutura em `layout/estrutura.php`, temas em `mod/site/temas/` |
| `gestao/index.php` (gestor) | `dashboard`, `agenda`, `perfil`, `servicos`, `barbeiros`, `bancadas`, `fornecedores`, `definicoes`, `stock`, `pedidos` | `gestao/mod/<m>/<a>.php`, estrutura em `gestao/layout/` |
| `gestao/index.php` (barbeiro) | `dashboard`, `agenda`, `perfil`, `stock/listar`, `stock/pedir_reposicao` | idem |

- **Segurança centralizada:** os módulos não abrem sessão nem validam o CSRF. Cada módulo
  começa com `if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }`,
  por isso abri-lo diretamente no browser só reencaminha para o `index.php`.
- **Classes (POO):** `objects/Servico.php`, `Fornecedor.php`, `Bancada.php` e `Configuracao.php`
  (métodos `read`, `readOne`, `create`, `update`, ...), carregadas por autoload no `gestao/index.php`.
- **Funções partilhadas:** `config/modular.php` (`urlModulo`, `flash`, `campoCsrf`, `redirecionar`, ...).
- **Endereços antigos:** `gestao/agenda.php`, `publico/marcar.php`, etc. ficaram como ficheiros
  pequenos que reencaminham para o módulo novo (`config/redirecionar_antigo.php`).
- **A API da app Android** (`api/v1/`) é independente e não passa por estes `index.php`.

**Acrescentar um módulo:** criar `gestao/mod/<novo>/<ação>.php` (com a linha de proteção acima),
acrescentar `'<novo>' => ['<ação>', ...]` à lista `$MODULOS` do perfil em `gestao/index.php`
e, se for para aparecer no menu, uma entrada em `gestao/layout/menu.php`.

**Testes** (na raiz, com o XAMPP ligado): `php api/v1/tests/run.php`, `web_rotas.php`,
`web_objects.php` e `web_marcar.php`.

## Credenciais de Teste
- **Gestor:** `gestor@barberflow.pt` (Password: `123456`)
- **Barbeiro:** `andre@barberflow.pt` (Password: `barbeiro123`)

## Funcionalidades Principais
- [x] Isolamento de dados Multi-Tenant (ex: RLBarber e Navalha de Ouro).
- [x] Tematização dinâmica (cores e layouts diferentes por barbearia).
- [x] Registo de marcações com validação de horários.
- [x] Painel de gestão com estatísticas.
- [x] Sistema de envio de emails automáticos (Brevo).
- [x] Integração com Google Maps para reviews reais.

---
Desenvolvido por **Leandro Oliveira** (Grupo 09).
