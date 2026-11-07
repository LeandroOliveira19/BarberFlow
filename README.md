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

## 🔐 Credenciais de Teste
- **Gestor:** `gestor@barberflow.pt` (Password: `123456`)
- **Barbeiro:** `andre@barberflow.pt` (Password: `barbeiro123`)

## ✨ Funcionalidades Principais
- [x] Isolamento de dados Multi-Tenant (ex: RLBarber e Navalha de Ouro).
- [x] Tematização dinâmica (cores e layouts diferentes por barbearia).
- [x] Registo de marcações com validação de horários.
- [x] Painel de gestão com estatísticas.
- [x] Sistema de envio de emails automáticos (Brevo).
- [x] Integração com Google Maps para reviews reais.

---
Desenvolvido por **Leandro Oliveira** (Grupo 09).