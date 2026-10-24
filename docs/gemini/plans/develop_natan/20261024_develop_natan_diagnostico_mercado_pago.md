# Plano de Implementação — Diagnóstico e Observabilidade da Integração Mercado Pago

- **Data:** 2026-10-24
- **Branch:** `develop_natan`
- **Survey de Referência:** `docs/survey/20261024_diagnostico_mercado_pago_surveyfacts.md`

---

## 1. Objetivo

Resolver a confusão operacional ao ativar as variáveis do Mercado Pago (`MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_WEBHOOK_SECRET`, etc.) e de Doações (`DONATIONS_*`) quando o cache de configuração (`bootstrap/cache/config.php`) está ativo no servidor ou quando faltam as chaves no `.env` local, além de entregar o diagnóstico no comando `php artisan about` previsto no `docs/RUNBOOK.md`.

---

## 2. Etapas de Implementação

### Etapa 1: Diagnóstico seguro no `php artisan about`
- **Arquivos:**
  - `app/Providers/PaymentsServiceProvider.php`
  - `app/Providers/AiServiceProvider.php`
- **O que fazer:**
  - Registrar no `boot()` de `PaymentsServiceProvider` (usando `Illuminate\Foundation\Console\AboutCommand::add('Payments & Donations', fn () => [...])`) os indicadores seguros (sem nunca expor segredos ou tokens):
    - `Gateway`: valor de `config('payments.gateway', 'mercadopago')`
    - `Mercado Pago Token`: `'configured'` se `app(PaymentGateway::class)->isAvailable()` for `true`, senão `'not configured'`
    - `Mercado Pago Webhook Secret`: `'configured'` se `trim((string) config('payments.mercadopago.webhook_secret')) !== ''`, senão `'not configured'`
    - `Donation Mode`: `'dynamic (mercadopago)'` se o gateway estiver disponível, `'static pix'` se `PixPayload::isConfigured()` for `true`, ou `'unavailable'` caso contrário
  - Registrar no `boot()` de `AiServiceProvider` (`AboutCommand::add('AI SQL Builder', fn () => [...])`):
    - `Provider`: `AiConfiguration::isAvailable() ? 'configured' : 'not configured'`
    - `Model`: `config('ai.model') ?: 'not configured'`

### Etapa 2: Sincronização das chaves de Pagamento e Doação no `.env` local
- **Arquivo:** `.env` (não versionado)
- **O que fazer:**
  - Adicionar ao final do `.env` local os blocos `PAYMENTS_*`, `MERCADO_PAGO_*` e `DONATIONS_*` (espelhando `.env.example`), preservando todas as variáveis locais já existentes.

### Etapa 3: Atualização da Documentação (`docs/guides/mercado-pago.md`)
- **Arquivo:** `docs/guides/mercado-pago.md`
- **O que fazer:**
  - Adicionar um bloco de alerta `> [!IMPORTANT]` logo no início da Seção 4 ("Aplicar as alterações") explicando por que editar o `.env` no VPS não surte efeito nas páginas `/donate` e `/subscribe` até rodar `php artisan optimize:clear && php artisan optimize && php artisan queue:restart` (pois `deploy/deploy.sh` mantém `bootstrap/cache/config.php` compilado).
  - Incluir o comando de verificação imediata (`php artisan about`) para confirmar que `Mercado Pago Token` aparece como `configured` e `Donation Mode` como `dynamic (mercadopago)`.

### Etapa 4: Testes Automatizados
- **Arquivo:** `tests/Feature/Payments/PaymentsAboutCommandTest.php` (ou no arquivo de teste existente do Mercado Pago)
- **O que fazer:**
  - Validar que `php artisan about` exibe a seção `Payments & Donations` refletindo corretamente os estados `not configured` vs `configured` sem jamais vazar o valor de `MERCADO_PAGO_ACCESS_TOKEN` ou `MERCADO_PAGO_WEBHOOK_SECRET` na saída.
  - Rodar `composer test`, `npm run test` e `npm run types:check`.
