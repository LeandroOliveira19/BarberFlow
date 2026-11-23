# BarberFlow — Manual de utilização

Este manual explica como se usa o BarberFlow em cada perfil. Para instalar, ver o [README](../README.md).

| Perfil | Onde entra | O que faz |
|---|---|---|
| **Cliente** | Site público (`/projeto/projeto/`) ou app Android | Vê serviços e equipa, marca, cancela ou remarca |
| **Gestor** | `/projeto/projeto/gestao/login.php` | Gere toda a barbearia: agenda, equipa, serviços, stock, definições |
| **Barbeiro** | `/projeto/projeto/gestao/login.php` | Vê a sua agenda, a sua bancada e pede material |

---

## 1. Cliente

### Marcar pelo site
1. Abre o site da barbearia (ex.: `…/projeto/projeto/?slug=rlbarber`) e carrega em **Marcar**.
2. Escolhe o serviço, o barbeiro e o dia, e depois uma hora livre.
3. Escreve o nome, o telemóvel e o email. Se a marcação for para outra pessoa (ex.: um filho), preenche "Para quem é?".
4. Recebes por email um **código de 6 dígitos**. Escreve-o para confirmar.
   - O código vale 15 minutos e tens 5 tentativas.
   - Regra: cada pessoa só pode ter uma marcação por dia.

### App Android
- Faz o mesmo que o site. Depois de confirmares, a marcação fica guardada no telemóvel, no separador **Marcações**.
- Até X horas antes, o valor que a barbearia definir (24 h por omissão), aparecem os botões **Cancelar** e **Remarcar**.
- Depois desse prazo, contacta a barbearia (separador Início).

---

## 2. Gestor

### Entrar
- Escreve o email e a senha. "Lembrar o meu email" guarda **só o email** neste computador.
- Se tiveres o **2FA** ligado, a seguir pede o código de 6 dígitos da app autenticadora.
- **5 tentativas erradas** em 15 minutos bloqueiam o login durante esse tempo.
- **Esqueci-me da senha:** escreve o email, recebes um código e escolhes uma senha nova.

### Menu e Dashboard
- **Menu lateral** em grupos: Hoje, Negócio, Stock e Conta.
  - Os números dourados mostram as marcações de hoje (Agenda) e os pedidos de reposição pendentes.
  - No fundo do menu estão o teu nome e o botão **Terminar sessão** (ícone de saída).
- **Barra de cima:** mostra onde estás (Barbearia › Página). O botão **Ver site** abre o site público da barbearia.
- **Dashboard:**
  - a receita de hoje aparece em destaque, comparada com o mesmo dia da semana passada, com um gráfico dos últimos 7 dias;
  - em baixo estão as próximas marcações e o que **precisa de atenção** (stock em falta, pedidos e marcações por verificar).
- **Filtros:** os de Pedidos, Stock e Agenda são botões lado a lado (por exemplo Todos, Pendente, Aprovado…). Basta um clique.

### Agenda
- **Vistas:** dia, semana ou próximos 90 dias.
- **Filtros:** estado, barbeiro e **Pesquisar cliente** (por nome ou telemóvel).
- **Mudar o estado de uma marcação:** Confirmar, Concluir ou Cancelar.
- As notas mostram o que o cliente pediu e o que fez na app (ex.: "Remarcada pelo cliente na app…").

### Equipa (Barbeiros)
1. **Adicionar barbeiro:** escreve o nome e o email. A bancada é opcional.
2. O barbeiro recebe por email um **link de ativação** (válido 48 h, só serve uma vez) e escolhe a própria senha.
3. Se o email não chegar (o servidor da UA não envia emails), aparece no ecrã o link com **Copiar**. Envia-o por WhatsApp.
4. Enquanto não ativar, o barbeiro aparece como **Por ativar**, com o botão **Reenviar convite** (o link antigo deixa de servir).
5. Também podes **editar** os dados e a bancada, ou **desativar/reativar** o barbeiro.

> O link de ativação abre-se no telemóvel ou no computador do barbeiro. Se for aberto no teu computador,
> a tua sessão é terminada ao ativar, e o barbeiro entra com a conta dele, nunca com a tua.

### Serviços, Bancadas, Stock, Pedidos, Fornecedores
- **Serviços:** nome, duração, preço e descrição. "Desativar" esconde-o do site sem apagar o histórico.
- **Bancadas:** o posto de cada barbeiro e o material que lá está. Só se atribuem barbeiros da tua barbearia.
  - Cada bancada é um card. Lá dentro, cada categoria (Consumíveis, Produtos, Brindes) aparece fechada com o resumo, por exemplo "5 itens · 2 em falta", e abre ao clicar.
  - As bancadas com material em falta aparecem primeiro.
- **Stock:**
  - materiais e stock geral, em cards agrupados por categoria;
  - cada card mostra o nome, a quantidade e o estado (OK ou Repor), e ao clicar abre o detalhe com **Editar**, **Transferir** e **Desativar**;
  - **Transferir** envia material para uma bancada;
  - os materiais em falta (quantidade igual ou abaixo do mínimo) aparecem primeiro e com a margem a vermelho.
- **Pedidos:** os barbeiros pedem reposição e tu **aprovas** (o material passa do stock geral para a bancada), **rejeitas** ou marcas como **entregue**.
  - Os pedidos aparecem em cards agrupados por estado: Pendentes, Aprovados (por entregar), Entregues e Rejeitados.
  - Os grupos já resolvidos (Entregues e Rejeitados) ficam fechados e abrem ao clicar.
- **Fornecedores:** contactos e NIF.

### Definições
- **Informação pública:** nome, morada, slogan, "Sobre nós", horário.
- **Cancelar/remarcar na app até:** quantas horas antes o cliente ainda pode alterar sozinho (0 a 168).
- **Aparência:** tema do site, cores, fonte, imagem principal, galeria e redes sociais.
  - No tema **Clássico (Escuro)**, o topo do site tem um fumo animado na **cor principal** escolhida e o slogan e os títulos aparecem palavra a palavra. O tema **Minimalista** não tem estes efeitos.
  - Quem tiver a opção **"reduzir movimento"** ligada no sistema vê tudo parado. O mesmo acontece no ecrã de entrada da gestão.

### Perfil (gestor e barbeiro)
- **Dados pessoais:** e a fotografia (JPG, PNG ou WEBP, até 2 MB).
- **Mudar a senha:**
  1. "Enviar código" manda-te um código por email.
  2. Escreve o código e a senha nova (pelo menos 8 caracteres, com letras e números).
- **Ativar 2FA:**
  1. Lê o QR code com o Google Authenticator ou o Microsoft Authenticator, ou escreve a chave à mão.
  2. Confirma com o código da app.
  3. **Para desligar** são precisos a senha e um código atual.

---

## 3. Barbeiro

1. **Ativar a conta:** abre o link que o gestor te enviou, escolhe a senha e entra.
2. **Agenda:** só vês as **tuas** marcações e podes mudar o estado delas.
3. **Minha Bancada:** vês os materiais da **tua** bancada em cards por categoria, com os que faltam primeiro. Ao clicar num card abre o detalhe, onde fazes **Pedir reposição**.
4. **Perfil:** dados, fotografia, mudar a senha e 2FA (como o gestor).

O barbeiro **não** tem acesso a serviços, equipa, bancadas, fornecedores, pedidos de outros nem definições. Essas páginas respondem "Página não encontrada".

---

## 4. Contas de teste (avaliação)

Ficam na barbearia de demonstração **Navalha de Ouro**, que não tem clientes reais:

| Perfil | Email | Senha |
|---|---|---|
| Gestor | `avaliacao.gestor@example.com` | `Avaliacao!2026` |
| Barbeiro | `avaliacao.barbeiro@example.com` | `Barbeiro!2026` |

Se alguém mudar estas senhas ou ligar o 2FA nestas contas, volta a correr `sql/utilizadores_teste.sql` para as repor.

---

## 5. Problemas comuns

| Situação | O que fazer |
|---|---|
| "Demasiadas tentativas" no login | Espera o tempo indicado (até 15 min) |
| O código por email não chega | Vê o spam. No servidor da UA os emails não saem e o código aparece no ecrã (exceto no "Recuperar senha" de contas reais: aí entra e usa "Mudar senha" no Perfil) |
| "Este link de ativação é inválido ou já expirou" | Pede ao gestor que use **Reenviar convite** |
| Perdi o telemóvel com a app autenticadora | Um gestor pode desligar o 2FA na BD (`UPDATE utilizadores SET totp_ativo = 0, totp_segredo = NULL WHERE email = '…'`) |
| A app diz "Já não é possível alterar online" | Passou o prazo definido pela barbearia: contacta-a |
