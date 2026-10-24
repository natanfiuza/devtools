# Guia — Integração com o Mercado Pago (Pagamentos, Assinaturas e Doações PIX)

Como configurar, ativar e operar a integração com o Mercado Pago no site. **Todo o código necessário já existe na aplicação** (entregue na Fase 6 — ADRs 045 a 049): ativar os pagamentos online e o recebimento de doações por PIX dinâmico exige apenas configurar a aplicação no painel de desenvolvedores do Mercado Pago e preencher as variáveis de ambiente no arquivo `.env`. Não há código PHP ou TypeScript para escrever.

---

## 1. Perguntas Rápidas (FAQ)

### Preciso criar duas aplicações no Mercado Pago (uma para PIX/Cartão à vista e outra para Assinaturas recorrentes)?
**Não. Você deve criar apenas UMA aplicação** no painel do Mercado Pago Developers.
- No assistente de criação de aplicação do Mercado Pago, a pergunta *"Qual produto você está integrando?"* (Checkout Pro, Checkout Transparente ou Assinaturas) serve apenas para personalizar os atalhos de documentação no painel deles.
- Tecnicamente, o **`Access Token` (`APP_USR-...`) pertence à sua conta recebedora** e tem permissão simultânea na API REST para chamar todos os endpoints que o DevTools utiliza:
  1. **Doação PIX Transparente (`/{locale}/donate`):** `POST /v1/payments` (gera o QR Code e Copia e Cola direto na nossa tela);
  2. **Plano Pro — Pagamento Único (`/subscribe` -> Pagar agora):** `POST /checkout/preferences` (abre o Checkout Pro com PIX à vista, Cartão à vista e Boleto);
  3. **Plano Pro — Renovação Automática (`/subscribe` -> Renovação automática no cartão):** `POST /preapproval` (cria a assinatura recorrente sem plano associado).
- Além disso, na aba **Webhooks** dessa mesma aplicação única, você consegue marcar simultaneamente os 3 tópicos (`Pagamentos`, `Planos e assinaturas` e `Faturas de assinaturas`) sob um único `MERCADO_PAGO_WEBHOOK_SECRET`. Se você criasse duas aplicações separadas, cada uma teria uma *Assinatura secreta* diferente para o webhook, o que impediria validar ambas com uma única variável `MERCADO_PAGO_WEBHOOK_SECRET`.

### Precisa instalar algum SDK?
**Não.** O projeto **não utiliza** o SDK oficial PHP (`mercadopago/dx-php`) nem o SDK JavaScript (`@mercadopago/sdk-js`):
- **No Backend (PHP):** A classe `App\Services\Payments\MercadoPagoGateway` (que implementa o contrato `App\Contracts\Payments\PaymentGateway`) faz chamadas HTTP REST diretas usando o cliente nativo do Laravel (`Illuminate\Support\Facades\Http`), com `Bearer Token`, timeouts estritos (`connect_timeout` de 5 s e `timeout` de 20 s), cabeçalho `X-Idempotency-Key` determinístico por operação e exceções seguras (`PaymentGatewayException`), que nunca registram corpo da resposta ou credenciais em log (ADR-045).
- **No Frontend (Vue / Navegador):** O checkout avulso utiliza o **Checkout Pro** por redirecionamento (`init_point`) e a assinatura recorrente utiliza **Preapproval** também por redirecionamento (`init_point`). Nenhum script externo do Mercado Pago é injetado no navegador, preservando a Content-Security-Policy (CSP por nonce, ADR-052) e eliminando escopo PCI no nosso servidor.
- **Gerador de QR Code para contingência:** Para o modo de contingência (Pix Estático quando o Mercado Pago não está configurado), o projeto utiliza o pacote `bacon/bacon-qr-code` (`^3.0`), que **já está declarado e instalado** no `composer.json`.

### O Mercado Pago pode ser usado para receber doação por PIX?
**Sim, e o fluxo já está 100% implementado** na rota pública `/{locale}/donate` (`DonationController`, `CreateDonation` e `ConfirmDonation`, ADR-048). O sistema opera automaticamente em dois modos:

| Modo | Quando é ativado | Como funciona | Confirmação e Recibo |
|---|---|---|---|
| **1. Pix Dinâmico (Mercado Pago)** | `MERCADO_PAGO_ACCESS_TOKEN` preenchido no `.env` | Chama `POST /v1/payments` no Mercado Pago com `payment_method_id = "pix"` e `external_reference = "don_<ULID>"`. Exibe o QR Code PNG oficial, o código Copia e Cola do provedor e o link do comprovante (`ticket_url`), com expiração de 60 minutos. | **Automática via Webhook** (`POST /webhooks/mercadopago`). Muda o status da doação para `paid` na tela pública de status e envia recibo por e-mail (`DonationReceipt`) se o doador informou e-mail e `DONATIONS_SEND_RECEIPTS=true`. |
| **2. Pix Estático (Fallback Local)** | `MERCADO_PAGO_ACCESS_TOKEN` vazio, mas `DONATIONS_PIX_KEY`, `DONATIONS_PIX_HOLDER` e `DONATIONS_PIX_CITY` preenchidos | Gera localmente no servidor o payload BR Code no padrão EMV do Banco Central (`PixPayload` com CRC16-CCITT e o ULID da doação como `txid`) e desenha o QR Code em SVG (`PixQrCode`). | **Sem confirmação automática.** A tela avisa explicitamente que o QR é estático e que o comprovante é o do banco do doador. |

> **Atenção aos 2 requisitos obrigatórios para o Pix Dinâmico no Mercado Pago:**
> 1. **Chave Pix cadastrada no Mercado Pago:** A conta do Mercado Pago dona do `MERCADO_PAGO_ACCESS_TOKEN` **precisa ter ao menos uma chave Pix ativa cadastrada dentro da conta Mercado Pago**. Sem chave Pix cadastrada lá, a API `POST /v1/payments` recusa a criação do QR Code com erro HTTP 400.
> 2. **E-mail de pagador para doações anônimas (`DONATIONS_PAYER_EMAIL`):** A página `/{locale}/donate` permite doação anônima (sem nome e sem e-mail). Porém, a API do Mercado Pago exige o campo `payer.email` em toda cobrança Pix. Quando o doador não preenche o e-mail, `CreateDonation` envia o endereço configurado em `DONATIONS_PAYER_EMAIL` (ou o `MAIL_FROM_ADDRESS` como fallback). Garanta que `DONATIONS_PAYER_EMAIL` (ou `MAIL_FROM_ADDRESS`) contenha um e-mail válido.

---

## 2. Princípios e Arquitetura no DevTools

A integração financeira do site cobre três frentes independentes e segue cinco regras arquiteturais (ver `docs/ARQUITETURA.md` e ADRs 045 a 049):

1. **Três caminhos em `/subscribe` (ADR-045):**
   - **Pagar agora (Avulso — Checkout Pro):** Cria uma preferência em `POST /checkout/preferences` com `external_reference = chk_<ULID da checkout_session>` e redireciona o usuário para o `init_point` do Mercado Pago, onde ele escolhe pagar via **Pix, cartão de crédito ou boleto**.
   - **Renovação automática no cartão (Recorrência — Preapproval sem plano):** Cria uma assinatura autorizada em `POST /preapproval` (`auto_recurring` de 1 mês no plano mensal ou 12 meses no plano anual). Os preços e limites continuam vindo exclusivamente da nossa tabela `plans`. A simples autorização do cartão (`authorized`) deixa a sessão em processamento e **não libera cota**; o período só nasce quando o primeiro pagamento recorrente (`subscription_authorized_payment`) é aprovado.
   - **Atendimento manual:** Formulário de intenção de assinatura (`subscription_intents`). Se `MERCADO_PAGO_ACCESS_TOKEN` estiver vazio, os dois botões online ficam ocultos e o atendimento manual segue funcionando normalmente.
2. **Um período pago = uma linha em `subscriptions` (ADR-046):**
   - Cada pagamento aprovado cria um novo registro de período. Pagamentos do mesmo plano empilham após o maior `current_period_end` futuro; planos diferentes começam imediatamente (cancelando eventual `preapproval` anterior no provedor antes de abrir o novo checkout).
3. **Doação separada de receita fiscal (ADR-048):**
   - Pagamentos de planos ficam na tabela `payments` (guarda fiscal, vinculados a faturas e cota de IA).
   - Doações ficam na tabela `donations` (`external_reference = don_<ULID>`), não exigem conta, não geram fatura de assinatura e não alteram cota de tokens.
4. **Webhook assinado e *Fetch-Before-Fulfill* (ADR-047):**
   - O endpoint `POST /webhooks/mercadopago` fica fora do prefixo de idioma (`/{locale}`) e é isento de CSRF.
   - Toda notificação exige assinatura HMAC-SHA256 válida no cabeçalho `x-signature` conferida em tempo constante (`MercadoPagoSignature`).
   - O corpo do webhook **nunca é fonte de verdade**: o controller apenas salva o evento cru em `webhook_events` (idempotente por `(gateway, event_id)`) e despacha o job `ProcessWebhookEvent`. O job consulta o recurso real na API do Mercado Pago (`GET /v1/payments/{id}`, `GET /preapproval/{id}` ou `GET /authorized_payments/{id}`), confere valor e moeda com o que foi congelado no banco e só então aplica o efeito.
5. **Gateway primeiro em cancelamentos e estornos (ADR-049):**
   - Quando o usuário cancela a renovação em `/dashboard/billing` (`PUT /preapproval/{id}` com `status=canceled`) ou quando o admin estorna um pagamento em `/admin/payments` (`POST /v1/payments/{id}/refunds`), a chamada ao Mercado Pago ocorre **antes** de alterar o banco local.

---

## 3. Passo a Passo no Painel do Mercado Pago

### Passo 1: Preparar a Conta Recebedora (Chave Pix)

Antes de gerar credenciais de produção:
1. Acesse sua conta no aplicativo ou site do **Mercado Pago** (<https://www.mercadopago.com.br>).
2. Vá na área **Pix** -> **Minhas chaves** e certifique-se de que há **pelo menos uma chave Pix ativa** (CPF, CNPJ, e-mail, telefone ou chave aleatória) vinculada à conta.
   > Sem uma chave Pix ativa na conta do Mercado Pago, o Checkout Pro não exibirá a opção Pix e a página de doações (`/{locale}/donate`) receberá erro ao tentar criar o QR Code dinâmico.

---

### Passo 2: Criar a Aplicação no Painel de Desenvolvedores

1. Acesse o painel **Suas integrações** do Mercado Pago Developers: <https://www.mercadopago.com.br/developers/panel/app>.
2. Faça login com a conta Mercado Pago que receberá os pagamentos.
3. Clique em **Criar aplicação** (Create application).
4. Preencha os dados básicos:
   - **Nome da aplicação**: `DevTools` (ou `devtools.natanfiuza.dev.br`).
   - **Qual tipo de solução de pagamento você vai integrar?**: selecione **Pagamentos online** (Online payments).
   - **Você está usando uma plataforma de e-commerce?**: selecione **Não** (desenvolvimento próprio).
   - **Qual produto você está integrando?**: selecione **Checkout Pro** *(ou **Checkout Transparente** — a escolha neste campo não restringe a API: os endpoints `/v1/payments` para Pix transparente, `/checkout/preferences` para Checkout Pro e `/preapproval` para assinaturas recorrentes ficam todos habilitados na mesma aplicação)*.
   - **Modelo de integração**: opcional / padrão.
5. Aceite os termos de uso e clique em **Criar aplicação**.

---

### Passo 3: Obter o Access Token (`MERCADO_PAGO_ACCESS_TOKEN`)

Dentro da aplicação recém-criada no painel (<https://www.mercadopago.com.br/developers/panel/app>):

#### A) Para Testes / Desenvolvimento Local (Credenciais de Teste)
1. No menu lateral esquerdo da aplicação, clique em **Credenciais de teste** (Test credentials).
2. Copie o valor do campo **Access Token** (geralmente começa com `APP_USR-...` ou `TEST-...`).
3. Este token será usado na variável `MERCADO_PAGO_ACCESS_TOKEN` no seu `.env` local (veja a seção 6 para detalhes de contas de teste no Sandbox).

#### B) Para Produção (Credenciais de Produção)
1. No menu lateral esquerdo da aplicação, clique em **Credenciais de produção** (Production credentials).
2. Caso seja a primeira vez, o Mercado Pago pedirá para **Ativar credenciais de produção**:
   - Selecione o **Setor / Indústria** (ex.: *Software / Serviços digitais*).
   - Informe o **Site da aplicação**: `https://devtools.natanfiuza.dev.br`.
   - Clique em **Ativar credenciais de produção**.
3. Copie o **Access Token** de produção (`APP_USR-...`).
   > **Nota:** A aplicação **não utiliza** a `Public Key` no frontend, pois todo o fluxo ocorre no servidor via `Access Token`. Você só precisa do **Access Token**.

---

### Passo 4: Configurar os Webhooks e Obter o `MERCADO_PAGO_WEBHOOK_SECRET`

O Mercado Pago avisa nossa aplicação sobre pagamentos aprovados, doações Pix pagas, estornos e renovações de assinatura através de Webhooks.

1. Ainda dentro da sua aplicação em <https://www.mercadopago.com.br/developers/panel/app>, no menu lateral esquerdo, clique em **Webhooks** -> **Configurar notificações**.
2. Selecione a aba correspondente ao ambiente (**Modo de produção** para o VPS ou **Modo de teste** se estiver usando um túnel local).
3. No campo **URL de produção** (e/ou **URL de teste**), informe o endpoint exato da aplicação (sem prefixo de idioma):
   ```text
   https://devtools.natanfiuza.dev.br/webhooks/mercadopago
   ```
   > **Atenção:** A rota de webhook fica fora do `/{locale}`. Nunca adicione `/pt-br/` na frente da URL do webhook.
4. Em **Eventos recomendados** / **Eventos**, marque os três tópicos que a aplicação processa (`HandleWebhookEvent`):
   - **Pagamentos** (`payment`) — *Obrigatório para confirmar Checkout Pro avulso, Doações por Pix Dinâmico e Estornos.*
   - **Planos e assinaturas** (`subscription_preapproval`) — *Obrigatório para sincronizar autorização e cancelamento da recorrência no cartão.*
   - **Faturas de assinaturas / Pagamentos autorizados de assinatura** (`subscription_authorized_payment`) — *Obrigatório para abrir/empilhar o período pago a cada cobrança recorrente e avisar falha de cartão.*
5. Clique em **Salvar configuração** (Save configuration).
6. Ao salvar, o painel exibirá a **Assinatura secreta** (*Secret signature* — uma string hexadecimal longa de 64 caracteres).
7. Copie essa **Assinatura secreta**: ela é o valor exato que deve ir em `MERCADO_PAGO_WEBHOOK_SECRET` no seu `.env`.

---

## 4. Configurar no Arquivo `.env`

Abra o arquivo `.env` (em `/var/www/devtools/.env` na produção ou na raiz do projeto local) e configure os blocos de **Pagamentos** e **Doações**:

```dotenv
# ---------------------------------------------------------------------------
# Pagamentos (Mercado Pago — config/payments.php)
# ---------------------------------------------------------------------------
# Provedor ativo (único implementado na F6).
PAYMENTS_GATEWAY=mercadopago

# Access Token obtido em "Suas integrações > Credenciais" no painel do Mercado Pago.
# Vazio = esconde os botões de pagamento online em /subscribe (mantém apenas o manual)
# e ativa o fallback de Pix estático em /{locale}/donate.
MERCADO_PAGO_ACCESS_TOKEN=APP_USR-0000000000000000-000000-00000000000000000000000000000000-000000000

# Assinatura secreta obtida em "Suas integrações > Webhooks" no painel do Mercado Pago.
# Vazio = POST /webhooks/mercadopago responde 503 e recusa qualquer notificação.
MERCADO_PAGO_WEBHOOK_SECRET=sua_assinatura_secreta_do_webhook_aqui

# URL base e timeouts HTTP da API do Mercado Pago (em segundos).
MERCADO_PAGO_BASE_URL=https://api.mercadopago.com
MERCADO_PAGO_TIMEOUT=20
MERCADO_PAGO_CONNECT_TIMEOUT=5

# Tempo de expiração da sessão de checkout avulso (minutos).
PAYMENTS_CHECKOUT_EXPIRES_MINUTES=60

# Opcionais: deixe vazios em produção para que o Laravel use automaticamente as rotas
# route('subscribe.checkout.return') e route('webhooks.mercadopago') baseadas no APP_URL.
# Preencha PAYMENTS_WEBHOOK_URL apenas em dev local se estiver usando túnel (ngrok/cloudflared).
PAYMENTS_BACK_URL=
PAYMENTS_WEBHOOK_URL=

# Nome que aparece na fatura do cartão de crédito do comprador (máx. 22 caracteres).
PAYMENTS_STATEMENT_DESCRIPTOR=DEVTOOLS

# ---------------------------------------------------------------------------
# Doações por PIX (config/donations.php)
# ---------------------------------------------------------------------------
DONATIONS_CURRENCY=BRL

# Valores sugeridos nos botões rápidos e limites mínimo/máximo aceitos, em centavos.
# Padrão: R$ 5,00, R$ 10,00, R$ 25,00 e R$ 50,00 | Mínimo R$ 1,00 (100) | Máximo R$ 1.000,00 (100000).
DONATIONS_SUGGESTED_CENTS=500,1000,2500,5000
DONATIONS_MIN_CENTS=100
DONATIONS_MAX_CENTS=100000

# Validade do QR Code dinâmico gerado pelo Mercado Pago (minutos, mínimo 5).
DONATIONS_EXPIRES_MINUTES=60

# E-mail de pagador enviado ao Mercado Pago quando o doador faz uma doação anônima
# (sem preencher o campo de e-mail). Se vazio, usa o MAIL_FROM_ADDRESS.
# IMPORTANTE: nunca deixe ambos vazios ou com e-mail inválido, pois o Mercado Pago
# exige payer.email em toda criação de Pix dinâmico.
DONATIONS_PAYER_EMAIL=devtools@natanfiuza.dev.br

# Envia recibo por e-mail quando o webhook confirma a doação (se o doador informou e-mail).
DONATIONS_SEND_RECEIPTS=true

# Pix Estático de Contingência (usado automaticamente quando MERCADO_PAGO_ACCESS_TOKEN está vazio).
# Mesmo usando o Mercado Pago em produção, recomenda-se manter preenchido como contingência:
# se um dia você precisar remover o token do Mercado Pago para manutenção, a página /donate
# continua funcionando com este Pix estático sem quebrar.
DONATIONS_PIX_KEY=sua_chave_pix_aqui
DONATIONS_PIX_HOLDER="NATANIEL FIUZA"
DONATIONS_PIX_CITY="RECIFE"
```

### Aplicar as alterações (Obrigatório!)

> [!IMPORTANT]
> **Por que preencher o `.env` sozinho não ativa os botões nas páginas `/donate` e `/subscribe`?**
> Em produção (VPS), o script de deploy (`deploy/deploy.sh`) executa `php artisan optimize`, que compila todas as configurações em `bootstrap/cache/config.php`. Enquanto esse cache existir, **o Laravel ignora completamente as leituras de `env()` do arquivo `.env`**. Portanto, sempre que você adicionar ou alterar `MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_WEBHOOK_SECRET` ou `DONATIONS_*`, é **obrigatório** limpar e recriar o cache de configuração e reiniciar os workers da fila.

Após salvar o `.env`, limpe e recarregue o cache de configuração e **reinicie os workers da fila** (para que os processos em memória leiam as novas chaves):

**Em produção (VPS):**
```bash
cd /var/www/devtools
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
sudo supervisorctl status devtools-worker:*
```

**Em desenvolvimento local:**
```bash
php artisan config:clear
```

**Como confirmar imediatamente no terminal se o Laravel enxergou as credenciais:**
```bash
php artisan about --only=payments
```
A saída deve exibir `Mercado Pago Token: configured`, `Mercado Pago Webhook Secret: configured` e `Donation Mode: dynamic (mercadopago)` (sem jamais expor o token na tela).

---

## 5. Processos Obrigatórios no Servidor (Fila e Agendador)

A integração do Mercado Pago é assíncrona. Para que os pagamentos e doações funcionem de ponta a ponta em produção, **dois serviços de background** precisam estar ativos (consulte o guia completo em [docs/guides/cron.md](cron.md) e [docs/RUNBOOK.md](../RUNBOOK.md)):

1. **Worker da Fila (`devtools-worker` sob Supervisor):**
   - Quando o Mercado Pago chama `POST /webhooks/mercadopago`, o controller apenas valida a assinatura `x-signature`, grava a linha em `webhook_events` e enfileira o job `ProcessWebhookEvent` na fila `database`.
   - É o worker que executa `ProcessWebhookEvent` (3 tentativas, backoff de `10s`, `60s` e `300s`, timeout de `60s`), consulta a API do Mercado Pago, libera a assinatura ou confirma a doação PIX e dispara os e-mails (`DonationReceipt`, `PaymentConfirmed`, `PaymentRefunded`, `PaymentRenewalFailed`).
   - **Se o worker estiver parado:** Os webhooks chegarão com status `201` e ficarão com status `pending` em `/admin/webhooks`, sem liberar planos e sem atualizar o status das doações.

2. **Agendador (`php artisan schedule:run` no Crontab a cada minuto):**
   - Executa as rotinas semeadas na tabela `task_schedules`:
     - `payments:expire` (a cada 5 minutos): marca como `expired` as sessões de checkout (`checkout_sessions`) e doações Pix dinâmicas (`donations`) que continuam `pending` após `expires_at` (60 min). *(Nota: a leitura nas telas já calcula o status efetivo em tempo real via `effectiveStatus()`, mas o comando mantém o banco limpo).*
     - `webhooks:prune --days=30` (diariamente às 03:30): remove payloads brutos de `webhook_events` com mais de 30 dias (LGPD/retenção, ADR-047), sem nunca apagar `payments`, `subscriptions` ou `donations`.

---

## 6. Como Testar e Validar (Sandbox Local e Produção)

### Regras do Sandbox do Mercado Pago (Contas de Teste)
O Mercado Pago **não permite que uma conta pague a si mesma**. Se você estiver logado no navegador com a mesma conta Mercado Pago que gerou o `MERCADO_PAGO_ACCESS_TOKEN`, o Checkout Pro bloqueará o pagamento.

Para testar no Sandbox:
1. No painel do Mercado Pago (<https://www.mercadopago.com.br/developers/panel/app>), vá no menu lateral em **Suas integrações** -> **Contas de teste** (Test accounts).
2. Crie **duas contas de teste**:
   - **Vendedor (Seller)** (ou use as **Credenciais de teste** da sua aplicação principal).
   - **Comprador (Buyer)**: anote o usuário, senha e e-mail `@testuser.com` gerados pelo Mercado Pago.
3. Ao testar o Checkout Pro ou Preapproval, abra uma janela anônima e faça login no Mercado Pago com a conta de **Comprador**, usando os [cartões de crédito de teste oficiais do Mercado Pago](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-content/your-integrations/test/cards) (use `APRO` no nome do titular para aprovar na hora, ou `OTHE` para simular recusa).

### Recebendo Webhooks no Ambiente Local (Túnel HTTPS)
Como o Mercado Pago precisa alcançar a sua máquina para entregar o `POST /webhooks/mercadopago`, exponha a porta `8000` local com um túnel HTTPS (como `cloudflared` ou `ngrok`):

```bash
# Exemplo com Cloudflare Tunnel:
cloudflared tunnel --url http://localhost:8000

# Ou com ngrok:
ngrok http 8000
```

1. Copie a URL HTTPS gerada pelo túnel (ex.: `https://exemplo-aleatorio.trycloudflare.com`).
2. No seu `.env` local, defina:
   ```dotenv
   PAYMENTS_WEBHOOK_URL=https://exemplo-aleatorio.trycloudflare.com/webhooks/mercadopago
   ```
   *(Ao preencher `PAYMENTS_WEBHOOK_URL`, a aplicação envia essa URL pública no campo `notification_url` de cada preferência, preapproval e Pix criado, mesmo que o seu `APP_URL` continue sendo `http://localhost:8000`).*
3. Cadastre essa mesma URL em **Webhooks (Modo de teste)** no painel do Mercado Pago e copie o `MERCADO_PAGO_WEBHOOK_SECRET` para o `.env` local.
4. Rodar `php artisan config:clear` e manter o worker rodando (`composer dev` ou `php artisan queue:work`).

---

### Roteiro de Validação dos Fluxos (Smoke Test)

#### Teste 1: Doação por PIX Dinâmico (Mercado Pago)
1. Com `MERCADO_PAGO_ACCESS_TOKEN` configurado, acesse `http://localhost:8000/pt-br/donate` (ou em produção `https://devtools.natanfiuza.dev.br/pt-br/donate`).
2. Escolha um valor sugerido (ex.: R$ 5,00) ou digite um valor livre, informe opcionalmente nome e e-mail (ou deixe em branco para testar doação anônima via `DONATIONS_PAYER_EMAIL`) e clique em **Gerar Pix**.
3. Você será redirecionado para `/pt-br/donate/{ulid}`:
   - A tela exibirá o **QR Code PNG** gerado pelo Mercado Pago, o código **Copia e Cola**, o link para o ticket externo e a data/hora de expiração (60 minutos).
   - No banco, a linha em `donations` estará com `gateway = 'mercadopago'`, `status = 'pending'` e `gateway_ref` preenchido com o ID do pagamento no Mercado Pago.
4. Após o pagamento do Pix (ou aprovação no Sandbox):
   - O Mercado Pago envia `POST /webhooks/mercadopago` com `type = "payment"`.
   - O worker executa `ProcessWebhookEvent` -> `HandleWebhookEvent` -> consulta `GET /v1/payments/{id}` -> chama `ConfirmDonation`.
   - O status da doação muda para `paid` (`paid_at` preenchido), o QR Code desaparece da página de status e, se havia e-mail do doador, o recibo `DonationReceipt` é enviado no idioma da doação.
   - Em `/admin/payments` (aba **Doações**), a doação aparece como **Paga**.

#### Teste 2: Doação por PIX Estático (Contingência sem Mercado Pago)
1. Comente ou deixe vazio `MERCADO_PAGO_ACCESS_TOKEN=` no `.env` e preencha `DONATIONS_PIX_KEY`, `DONATIONS_PIX_HOLDER` e `DONATIONS_PIX_CITY`. Rode `php artisan config:clear`.
2. Acesse `http://localhost:8000/pt-br/donate` e gere uma doação.
3. Na página de status (`/pt-br/donate/{ulid}`), verifique que:
   - O QR Code é renderizado em SVG local (`data:image/svg+xml;base64,...`) e o código Copia e Cola começa com `00020101021226...br.gov.bcb.pix...`.
   - Aparece o aviso informando que o Pix é estático e não possui confirmação automática.

#### Teste 3: Assinatura Avulsa via Checkout Pro (Pix, Cartão ou Boleto)
1. Com `MERCADO_PAGO_ACCESS_TOKEN` ativo e logado em uma conta com e-mail verificado, acesse `/pt-br/pricing` e clique para assinar o plano **Pro Mensal** (`/subscribe?plan=pro-monthly`).
2. Na seção **Pagar agora**, clique no botão para abrir o Checkout Pro.
3. O sistema cria uma linha `pending` em `checkout_sessions` e redireciona o navegador para o `init_point` do Mercado Pago.
4. Conclua o pagamento no Mercado Pago.
5. Ao receber o webhook `payment` aprovado:
   - Uma linha fiscal é criada em `payments` (`status = 'paid'`, `gateway = 'mercadopago'`).
   - Um novo período é aberto (ou empilhado) em `subscriptions`.
   - A sessão em `checkout_sessions` muda para `approved`.
   - A cota de 2.000.000 de tokens fica imediatamente disponível em `/dashboard` e no AI SQL Builder.

#### Teste 4: Renovação Automática no Cartão (Preapproval) e Cancelamento
1. Em `/subscribe?plan=pro-monthly`, escolha **Renovação automática no cartão**.
2. Autorize o cartão na tela do Mercado Pago.
3. O webhook `subscription_preapproval` marca a sessão como autorizada (`authorized_at` preenchido), e a tela `/dashboard/billing` informa que o pagamento está em processamento (sem liberar tokens antes da cobrança).
4. Quando o webhook `subscription_authorized_payment` chega aprovado, o período é aberto em `subscriptions` e o pagamento é registrado em `payments`.
5. Em `/dashboard/billing`, clique em **Cancelar renovação automática**:
   - O sistema chama `PUT /preapproval/{id}` (`status = canceled`) no Mercado Pago e grava `renewal_canceled_at` no banco.
   - A assinatura **continua ativa** até o fim do período já pago (`current_period_end`).

---

## 7. Resolução de Problemas (Troubleshooting)

| Sintoma | Causa Provável | Como Resolver |
|---|---|---|
| **Opções de pagamento online não aparecem em `/subscribe` (só aparece o formulário manual)** | `MERCADO_PAGO_ACCESS_TOKEN` está vazio no `.env` ou o cache de configuração está desatualizado. | Preencha `MERCADO_PAGO_ACCESS_TOKEN` no `.env` e rode `php artisan optimize:clear && php artisan optimize`. |
| **Página `/donate` gera Pix Estático mesmo com o Mercado Pago configurado** | `MERCADO_PAGO_ACCESS_TOKEN` vazio em cache. | Rode `php artisan optimize:clear && php artisan optimize`. |
| **Erro ao gerar Pix na página `/donate` (`Payments: donation.pix respondeu HTTP 400` no log)** | 1) **E-mail do doador (ou `DONATIONS_PAYER_EMAIL`) igual ao e-mail da conta Mercado Pago recebedora** (o Mercado Pago bloqueia autofinanciamento: *"The payer cannot be the same as the collector"*); ou<br>2) A conta do Mercado Pago não possui **chave Pix ativa cadastrada** (*"Collector doesn't have a pix key"*); ou<br>3) `APP_URL` no `.env` do VPS está como `http://localhost:8000` (ou sem `https://`), gerando uma `notification_url` recusada pelo Mercado Pago; ou<br>4) O doador foi anônimo e `DONATIONS_PAYER_EMAIL` / `MAIL_FROM_ADDRESS` está vazio ou inválido. | 1) Confira os campos estruturados `gateway_error` e `gateway_causes` gravados automaticamente em `storage/logs/laravel.log`;<br>2) Ao testar logado ou preencher `DONATIONS_PAYER_EMAIL`, use um **e-mail diferente** do e-mail da sua conta Mercado Pago;<br>3) Cadastre uma chave Pix ativa no app/site do Mercado Pago;<br>4) Garanta `APP_ENV=production` e `APP_URL=https://devtools.natanfiuza.dev.br` no `.env` do VPS e rode `php artisan optimize:clear && php artisan optimize`. |
| **Teste de Webhook no painel do Mercado Pago retorna HTTP `503`** | `MERCADO_PAGO_WEBHOOK_SECRET` está vazio no `.env` (ou config em cache). Por segurança, o endpoint fica desligado quando não há segredo (ADR-047). | Copie a **Assinatura secreta** na tela de Webhooks do Mercado Pago, salve em `MERCADO_PAGO_WEBHOOK_SECRET` no `.env` e rode `php artisan optimize:clear && php artisan optimize`. |
| **Webhook retorna HTTP `401 Unauthorized`** | A assinatura HMAC `x-signature` não bateu com `MERCADO_PAGO_WEBHOOK_SECRET`. | Verifique se você copiou a **Assinatura secreta** (da tela de Webhooks) e não o `Client Secret` ou `Access Token`. Depois de atualizar no `.env`, rode `php artisan optimize:clear && php artisan optimize`. |
| **Pagamento foi aprovado no Mercado Pago, mas o plano/doação continua `pending` no site** | 1) O webhook não está chegando (URL errada no painel MP); ou<br>2) O worker da fila (`devtools-worker`) está parado; ou<br>3) O evento falhou na consulta à API. | 1) Abra `/admin/webhooks` no painel admin do site: se a lista estiver vazia, o Mercado Pago não está chamando a URL;<br>2) Se o evento estiver `pending`, reinicie o worker (`sudo supervisorctl restart devtools-worker:*`);<br>3) Se estiver `failed`, veja a coluna de erro em `/admin/webhooks` (ex.: token de teste usado para buscar pagamento de produção). |
| **Evento de teste simulado pelo botão "Simular notificação" do painel MP fica `failed` em `/admin/webhooks`** | O botão "Simular notificação" do painel do Mercado Pago envia um `data.id` fictício (ex.: `123456`). Quando o nosso job `ProcessWebhookEvent` vai buscar `GET /v1/payments/123456` na API real (*fetch-before-fulfill*), a API do Mercado Pago retorna `404 Not Found`. | **Comportamento esperado e correto por desenho (ADR-047):** o endpoint respondeu `201` (provando que a URL e a assinatura HMAC funcionam!), e o job recusou o ID fictício porque ele não existe na API. Em um pagamento real ou de Sandbox real, o `data.id` existe na API e o evento passa para `processed`. |

---

## 8. Rotação de Credenciais sem Downtime

### Rotacionar o `MERCADO_PAGO_ACCESS_TOKEN`
1. Acesse <https://www.mercadopago.com.br/developers/panel/app>, abra sua aplicação -> **Credenciais de produção** e gere/renove o **Access Token**.
2. No servidor (`/var/www/devtools`), atualize apenas a variável no `.env` (nunca imprima o token no terminal ou logs):
   ```bash
   sed -i 's|^MERCADO_PAGO_ACCESS_TOKEN=.*|MERCADO_PAGO_ACCESS_TOKEN=NOVO_TOKEN_AQUI|' .env
   ```
3. Recarregue a configuração e reinicie os workers da fila (obrigatório para que os workers em execução abandonem o token antigo em memória):
   ```bash
   php artisan optimize:clear && php artisan optimize
   php artisan queue:restart
   ```
4. Verifique em `/admin/webhooks` se as notificações seguintes são processadas com status `processed`.

### Rotacionar o `MERCADO_PAGO_WEBHOOK_SECRET`
1. Na tela de **Webhooks** do painel do Mercado Pago, gere uma nova assinatura secreta.
2. Atualize imediatamente `MERCADO_PAGO_WEBHOOK_SECRET` no `.env` do servidor e execute:
   ```bash
   php artisan optimize:clear && php artisan optimize
   ```
   > **Importante:** Faça a troca no painel do Mercado Pago e no `.env` na mesma janela de segundos. Enquanto os dois segredos divergirem, `POST /webhooks/mercadopago` responderá `401` (o Mercado Pago reenviará a notificação alguns minutos depois).

---

## 9. Onde Isso Vive no Código

| Peça | Arquivo |
|---|---|
| Configuração do gateway, timeouts e URLs | `config/payments.php` |
| Configuração de valores de doação e Pix estático | `config/donations.php` |
| Contrato agnóstico do gateway | `app/Contracts/Payments/PaymentGateway.php` |
| Cliente HTTP fino do Mercado Pago (Checkout Pro, Preapproval, Pix, Estorno) | `app/Services/Payments/MercadoPagoGateway.php` |
| Registro do singleton `PaymentGateway` | `app/Providers/AppServiceProvider.php` |
| Rota do webhook (`POST /webhooks/mercadopago`) | `routes/webhooks.php` |
| Controller do webhook (validação HMAC + persistência em `webhook_events`) | `app/Http/Controllers/Webhooks/MercadoPagoWebhookController.php` |
| Validador em tempo constante da assinatura `x-signature` | `app/Support/Payments/MercadoPagoSignature.php` |
| Job assíncrono de processamento do webhook | `app/Jobs/ProcessWebhookEvent.php` |
| Roteamento por tópico e *fetch-before-fulfill* | `app/Actions/Payments/HandleWebhookEvent.php` |
| Abertura de Checkout Pro e Preapproval | `app/Actions/Payments/StartCheckout.php` |
| Aprovação de pagamento de gateway e empilhamento de período | `app/Actions/Payments/ApproveGatewayPayment.php`, `app/Actions/Subscriptions/OpenSubscriptionPeriod.php` |
| Cancelamento de recorrência e estorno gateway-primeiro | `app/Actions/Payments/CancelRecurring.php`, `app/Actions/Payments/RefundGatewayPayment.php` |
| Criação e confirmação de Doação por PIX (Dinâmico e Estático) | `app/Actions/Donations/CreateDonation.php`, `app/Actions/Donations/ConfirmDonation.php` |
| Gerador local de BR Code EMV (CRC16) e QR Code SVG (fallback estático) | `app/Support/Payments/PixPayload.php`, `app/Support/Payments/PixQrCode.php` |
| Comandos agendados de expiração e poda de webhooks | `app/Console/Commands/ExpirePayments.php`, `app/Console/Commands/PruneWebhookEvents.php` |
| Telas de assinatura, doação e faturas | `resources/js/pages/Subscribe.vue`, `resources/js/pages/Donate.vue`, `resources/js/pages/DonationStatus.vue`, `resources/js/pages/dashboard/Billing.vue` |
| Telas administrativas (Pagamentos/Doações e Eventos de Webhook) | `resources/js/pages/admin/Payments.vue`, `resources/js/pages/admin/WebhookEvents.vue` |
| Decisões arquiteturais (ADRs) | `docs/adr/ADR-045-gateway-mercado-pago-checkout-pro-e-preapproval.md` a `ADR-049-ciclo-da-recorrencia-cancelamento-falha-e-estorno.md` |
