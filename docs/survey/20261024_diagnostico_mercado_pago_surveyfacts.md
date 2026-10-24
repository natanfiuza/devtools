# Survey — Diagnóstico e Integração do Mercado Pago (2026-10-24)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Informei as variaveis do mercado pago PAYMENTS_GATEWAY, MERCADO_PAGO_ACCESS_TOKEN, MERCADO_PAGO_WEBHOOK_SECRET, MERCADO_PAGO_BASE_URL, MERCADO_PAGO_TIMEOUT e MERCADO_PAGO_CONNECT_TIMEOUT. Mas na pagina de doação continua mostrando a mensagem que não tem pagamento integrado e na pagina de assinatura tambem. O que esta errado ?"
- **Tela / Modulo:** Doação (`/{locale}/donate` -> `DonationController`, `Donate.vue`), Assinatura (`/subscribe` -> `SubscriptionIntentController`, `Subscribe.vue`), Gateway (`MercadoPagoGateway`, `PaymentsServiceProvider`) e Diagnóstico CLI (`php artisan about`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada, 2 decisões de fronteira, inspeção completa dos arquivos de configuração, controllers, providers, `.env` e script de deploy.
- **Arquivos-chave lidos:** `config/payments.php`, `config/donations.php`, `app/Services/Payments/MercadoPagoGateway.php`, `app/Providers/PaymentsServiceProvider.php`, `app/Providers/AiServiceProvider.php`, `app/Http/Controllers/DonationController.php`, `app/Http/Controllers/SubscriptionIntentController.php`, `resources/js/pages/Donate.vue`, `resources/js/pages/Subscribe.vue`, `.env`, `.env.example`, `deploy/deploy.sh`, `docs/RUNBOOK.md`, `docs/guides/mercado-pago.md`.

## 2. Decisoes das rodadas

### Rodada 1 — Diagnóstico de Ambiente e Melhorias de Observabilidade
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Ambiente onde as variáveis foram informadas e causa raiz da mensagem de indisponibilidade | **Produção (VPS) com cache de configuração (`bootstrap/cache/config.php`) gerado por `php artisan optimize` no deploy, exigindo `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`, além de ausência dos blocos `MERCADO_PAGO_*` e `DONATIONS_*` no `.env` local.** |
| Q2 | Melhorias no repositório e documentação | **Executar A + B + C: (A) Destacar alerta crítico de cache de configuração e verificação rápida em `docs/guides/mercado-pago.md`; (B) Registrar seção `DevTools` no `php artisan about` (`AboutCommand::add`) exibindo status booleano seguro (`configured` / `not configured`) de Mercado Pago, Webhook, Pix Estático e IA (sem nunca expor segredos); (C) Sincronizar as chaves vazias de `PAYMENTS_*`, `MERCADO_PAGO_*` e `DONATIONS_*` no `.env` local.** |

### Defaults assumidos (nao contestados)
- Nenhuma credencial ou segredo (`MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_WEBHOOK_SECRET`, `AI_API_KEY`) pode ser exibido no `php artisan about` ou em logs — apenas indicadores de estado (`configured` / `not configured`).
- Nenhuma nova ADR é necessária, pois o comportamento segue estritamente as ADRs 045, 047 e 048 e cumprimos apenas o diagnóstico já prometido pelo `docs/RUNBOOK.md` (linha 618: "`php artisan about` e `optimize:clear && optimize`").

## 3. Relatorio de fatos levantados
### Fato 1 — Como as telas decidem se o pagamento está integrado
- Em `app/Services/Payments/MercadoPagoGateway.php`, `isAvailable()` retorna `trim((string) config('payments.mercadopago.access_token')) !== ''`.
- Em `DonationController::index`, a prop `online` recebe `$this->gateway->isAvailable()` e `staticPix` recebe `! $online && PixPayload::isConfigured()`. Em `Donate.vue`, `available = computed(() => props.online || props.staticPix)`. Quando ambos são `false`, renderiza o bloco `data-test="donate-unavailable"` com o título `donations.info.title` ("Doação indisponível no momento / O Pix ainda não está configurado por aqui").
- Em `SubscriptionIntentController::create`, a prop `onlineCheckoutAvailable` recebe `$this->gateway->isAvailable()`. Em `Subscribe.vue`, quando `onlineCheckoutAvailable` é `false`, os caminhos `one_time` e `recurring` ficam desabilitados e aparece o aviso `data-test="subscribe-online-unavailable"` (`home.subscribe.checkout.unavailable`).

### Fato 2 — Por que o `.env` editado não surte efeito imediato
- No VPS (`/var/www/devtools`), `deploy/deploy.sh` executa `php artisan optimize` ao final de cada deploy, criando `bootstrap/cache/config.php`. Com esse arquivo presente, o Laravel ignora chamadas `env()` e não lê o arquivo `.env` até que `php artisan optimize:clear && php artisan optimize` seja executado.
- No ambiente local (`.env`), o arquivo possuía apenas 75 linhas e não continha as variáveis `PAYMENTS_*`, `MERCADO_PAGO_*` nem `DONATIONS_*` que constam no `.env.example`.

### Fato 3 — Lacuna no `php artisan about` mencionado no RUNBOOK
- Nas linhas 611 e 618 do `docs/RUNBOOK.md`, a documentação orienta rodar `php artisan about` para verificar se o provedor de IA e o Mercado Pago estão configurados. Contudo, nenhum `ServiceProvider` registrava informações no `Illuminate\Foundation\Console\AboutCommand`. Adicionar `AboutCommand::add('DevTools', ...)` torna o diagnóstico imediato e fiel ao RUNBOOK.
