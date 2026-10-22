# Levantamento - Guia do cron e FASE 7

Data: 2026-10-22
Branch: `develop_natan`
Pedido original: "Crie um guia de como funciona o cron e porque em producao em
docs/guides. Pode continuar para a proxima fase".
Plano: `docs/plans/develop_natan/20261022_cron_fase7_plansfacts.md`
Copia no padrao do CLAUDE.md:
`docs/claude-code/plans/develop_natan/20261022_develop_natan_cron_fase7.md`

## 1. Estado no inicio

- F6 (pagamentos, doacoes e webhooks) implementada e aceita em 2026-10-21;
  unico criterio pendente: smoke real da Mercado Pago (sem credenciais).
- Gates da F6: 560 testes PHP, 749 testes Vitest, PHPStan 7 e Pint verdes.
- Branch `develop_natan`; nenhum commit ou push nesta sessao.

## 2. Fatos confirmados no codigo

### Cron e fila

- Dois crons independentes no VPS, ambos a cada minuto: deploy
  (`/var/www/devtools/deploy/deploy-instaled.sh`) e agendador
  (`php artisan schedule:run`). O primeiro roda uma copia fora do git; o
  arquivo do repo e `deploy/deploy.sh`.
- Worker: supervisor `devtools-worker`, 2 processos
  `queue:work database --sleep=3 --tries=2 --timeout=120 --max-time=3600`.
- `bootstrap/app.php` le `task_schedules` e enfileira `ExecuteCommand`
  (`tries=1`, `timeout=0`) na fila `database`.
- Rotinas semeadas: `accounts:purge-deleted` (`dailyAt:03:00`),
  `payments:expire` (`*/5`), `webhooks:prune --days=30` (`30 3 * * *`).
  `ai:prune-cache` e `ai:reconcile` sao linhas de crontab proprias.
- `last_run_at` nunca e atualizada; nao ha tela de agendamentos.
- `composer dev`/`php artisan dev` sobe servidor, fila e Vite, mas nao o
  agendador; local usa `php artisan schedule:work`.

### Historico

- `HistoryController` lista qualquer conta; nao ha opt-in, retencao nem
  exclusao. `StartAiQuery` grava `history_visible => true` sempre.
- `AiRequestStatus::isTerminal()` cobre `done`/`failed`.
- `AnonymizeUser` apaga vinculos e sessoes, mas nao `ai_requests`;
  `ExportUserData` nao inclui o historico.
- `docs/RUNBOOK.md` nao listava `accounts:purge-deleted` e mostrava
  `webhooks:prune` como `dailyAt:03:30`, divergindo da linha semeada.

### API, hardening e SEO

- Nao ha `routes/api.php`, Sanctum nem `HasApiTokens`; a API existente e a de
  sessao em `/api/ai`.
- Nao ha CSP, HSTS, TrustProxies, `forceScheme` ou Turnstile.
- `Seo::NOINDEX` cobre `settings` por prefixo (na pratica, as rotas
  `profile.edit`, `appearance.edit` etc. caem nele), mas nao
  `two-factor.login`; nao ha JSON-LD, `twitter:title/description/image` nem
  404 customizada.
- `app.blade.php` tem apenas `twitter:card`, script inline de tema e
  `@fonts` inline.
- `output_targets` e `ai_cache` nao tem tela administrativa.

## 3. Decisoes do usuario

| Tema | Decisao |
|---|---|
| API publica | completa: Sanctum PAT, POST/GET `/api/v1/sql-queries`, lista, `me`, `output-targets`; cota do plano + rate limits; `docs/API.md` + `openapi.yaml` |
| Historico | toggle global off por padrao; so pagos; linhas existentes preservadas; retencao de 90 dias; apagar tudo |
| Dados antigos | preservar para pagos; toggle ligado para quem ja tem historico |
| Hardening | headers + CSP enforce com nonce, HSTS, XCTO, Referrer-Policy, Permissions-Policy, frame-ancestors |
| Auth da API | Sanctum PAT |
| Superficie | assincrona (202 + ULID), com polling e lista |
| Cota | mesma do plano via `usage_ledger` + rate limit por token |
| Turnstile | so cadastro; desligado sem chaves |
| SEO | essencial + JSON-LD; sem lastmod, FAQ ou Offer |
| Coluna legada | duas etapas; DROP no deploy seguinte |
| Admin | `output_targets` e `ai_cache`, somente leitura + poda |
| Docs publicas | `docs/API.md` + `docs/openapi.yaml`, sem pagina `/docs` |

## 4. Evidencias coletadas

- Guia `docs/guides/cron.md` criado; RUNBOOK e CLAUDE.md alinhados a
  `deploy-instaled.sh` e as quatro rotinas do agendador.
- Evidencias de implementacao e gates no relatorio
  `docs/claude-code/reports/develop_natan/20261022_develop_natan_cron_fase7.md`.
- Nenhum segredo foi impresso ou gravado; nenhum commit ou push.
