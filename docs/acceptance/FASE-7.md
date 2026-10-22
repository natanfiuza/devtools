# Criterios de aceite - FASE 7 (historico, API publica, hardening, SEO, admin e docs)

**Aceite de codigo em 2026-10-22.** A F7 fecha o SPEC: o historico de consultas
passa a ser opt-in e de plano pago, com retencao e exclusao; a API publica v1
entra com Personal Access Token do Sanctum e a mesma cota do plano; o site
ganha CSP por nonce, HSTS, TrustProxies, cookies seguros e Turnstile no
cadastro; o SEO fecha com metas, JSON-LD, 404 propria e `noindex` corrigido;
`output_targets` e o cache de IA ganham tela de admin; a coluna legada
`usage_ledger.ai_request_id` deixa de ser lida e escrita; e a documentacao
final acompanha o codigo, incluindo o guia do cron. O **smoke real da Mercado
Pago** herdado da F6 continua pendente por falta de credenciais e nao bloqueia
esta fase (criterio 28).

Fonte: `docs/SPEC.md` secao 9 (F7), `docs/ARQUITETURA.md`, `docs/API.md`,
`docs/openapi.yaml`, `docs/RUNBOOK.md`, `docs/guides/cron.md` e as ADRs 050 a
053, com emendas nas ADRs 028, 040 e 042.

## Escopo entregue

Historico opt-in por conta (`users.history_enabled`, desligado por padrao, com
backfill para quem ja tinha consultas visiveis), so em plano pago, com toggle,
retencao configuravel de 90 dias (`history:prune`, diaria as 03:45) e exclusao
apenas de linhas terminais; exportacao e anonimizacao cientes do historico. API
publica v1 em `/api/v1` com Bearer token, ability `ai:query`, consultas
assincronas escopadas por token, `/me`, `/output-targets`, mesma cota do plano
e rate limits proprios; tela de tokens em settings. CSP com nonce e tres modos,
headers de navegador, HSTS, TrustProxies, `forceScheme`, cookie seguro e
Turnstile apenas no cadastro. SEO com `noindex` em settings/2FA/tokens,
`twitter:*` completos, JSON-LD de Organization/WebSite/SoftwareApplication/
BreadcrumbList e 404 customizada. Admin com alvos de saida (toggle auditado) e
metadados do cache de IA (poda auditada). Remocao da leitura/escrita da coluna
legada, com `DROP` adiado para o deploy seguinte. Guia do cron e documentacao
final sincronizada.

## Mapa das decisoes

| Decisao | Onde virou criterio |
| --- | --- |
| ADR-050: API v1 com Sanctum, ability `ai:query`, contrato assincrono, cota do plano e rate limits por token | 9, 10, 11, 12, 13, 14, 15 |
| ADR-051: historico opt-in, so pago, retencao de 90 dias, exclusao so de terminais | 2, 3, 4, 5, 6, 7, 8 |
| ADR-052: headers + CSP por nonce, TrustProxies, HTTPS/cookies seguros, Turnstile no cadastro | 16, 17, 18 |
| ADR-053: JSON-LD, Twitter cards, 404 com noindex, sem lastmod/FAQ/Offer | 19, 20, 21 |
| Decisao de produto: coluna legada em duas etapas (parar agora, DROP no deploy seguinte) | 24 |
| Decisao de produto: admin sem newsletter/campanhas; uma tela por catalogo/cache | 22, 23 |
| Decisao de produto: documentacao da API em `docs/API.md` + OpenAPI, sem `/docs` publico | 15, 26 |
| Entregavel extra da sessao: guia do cron e correcao do comando instalado | 1 |

## Criterios

| # | Criterio | Como verificar |
| --- | --- | --- |
| 1 | `docs/guides/cron.md` explica os dois crons e o supervisor (deploy, agendador e worker), por que sao obrigatorios em producao, como instalar/verificar e os problemas comuns; `docs/RUNBOOK.md` cita `deploy-instaled.sh` e `accounts:purge-deleted`, e o `CLAUDE.md` aponta o comando instalado | Leitura do guia; `docs/RUNBOOK.md` secao de deploy/agendador; `CLAUDE.md` |
| 2 | `users.history_enabled` e aditiva, default `false`, e o backfill liga a preferencia para quem ja tinha alguma linha `history_visible = true`; o default de `ai_requests.history_visible` continua `false` | `2026_10_22_000001_add_history_enabled_to_users_table.php`, `User` (`casts`) |
| 3 | `StartAiQuery` grava `history_visible` a partir de `$user->history_enabled` nas duas criacoes (fila e cache hit), e a visibilidade nao e mais fixa em `true` | `AiActionsTest` ("grava a visibilidade do historico conforme o opt-in da conta"), `StartAiQuery` |
| 4 | O historico e de plano pago: o Free recebe `locked`, CTA de upgrade e nenhuma linha consultada; `PATCH dashboard/history/preference` e `DELETE dashboard/history` respondem 403 no Free; a conta paga lista so as proprias linhas visiveis, 20 por pagina | `AccountPanelTest` ("entrega o historico bloqueado...", "entrega o historico vazio...", "lista o historico...", "recusa escritas do historico no plano gratuito"), `EnsureHistoryHasPaidPlan`, `HistoryController` |
| 5 | O toggle liga/desliga a gravacao sem apagar o que existe: desligado, novas consultas nascem fora do historico; ligado de novo, as antigas continuam listadas; o valor e validado como booleano | `AccountPanelTest` ("liga e desliga o opt-in sem apagar o que ja foi gravado", "valida o toggle do historico"), `UpdateHistoryPreferenceRequest` |
| 6 | "Apagar historico" remove as linhas terminais (`done`/`failed`) da propria conta, preserva `queued`/`processing` e nao toca em `usage_ledger` nem em `ai_cache` | `AccountPanelTest` ("apaga o historico terminal da propria conta e preserva fila e ledger"), `ClearHistory` |
| 7 | A retencao padrao e `AI_HISTORY_RETENTION_DAYS` (90), o comando `history:prune` so apaga terminais mais antigos, aceita `--days`/`--dry-run`, nunca trata zero como tudo e esta semeado uma unica vez as `45 3 * * *` | `HistoryMaintenanceTest` (5 testes), `PruneHistory`, `2026_10_22_000004_seed_history_maintenance_schedule.php` |
| 8 | A exportacao inclui o historico da conta sem segredo; a anonimizacao apaga `ai_requests` e revoga todos os tokens Sanctum | `AccountPanelTest` (exportacao), `ExportUserData`, `AnonymizeUser` |
| 9 | A API v1 exige Bearer token do Sanctum, ability `ai:query`, conta ativa e e-mail verificado: sem token 401 (inclusive sem o cabecalho `Accept`), token sem ability 403, revogado deixa de autenticar, conta suspensa 403 | `ApiErrorEnvelopeTest` ("401 sem token", "401 JSON mesmo sem o cabecalho Accept", "403 sem ability", "403 conta suspensa"), `ForceJsonResponse`, `SqlQueryApiTest` ("recusa pedido sem token", "recusa token sem a ability", "nega conta suspensa"), `ApiTokenSettingsTest` ("token revogado deixa de autenticar") |
| 10 | `POST /api/v1/sql-queries` usa o mesmo contrato da tela e devolve 202 na fila com `poll_url`; cache hit devolve 200 e nao debita; `GET .../{ulid}` acompanha o estado e para de pollar no fim | `SqlQueryApiTest` ("cria a consulta do Free...", "devolve 200 quando o cache responde", "consulta o status pelo poll url..."), `ApiSqlQueryResource`, `StartAiQuery` |
| 11 | A lista e o `show` so enxergam consultas criadas **pelo mesmo token** (`api_token_id`); outro token da mesma conta, consulta da web e conta alheia respondem 404; a lista aceita filtro `status` | `SqlQueryApiTest` ("nao deixa um token ler a consulta de outro token...", "nao expoe consulta da web nem consulta de outra conta", "filtra a lista por status") |
| 12 | A cota e a do plano via `usage_ledger`, nao uma cota paralela: esgotada responde 402 com a unidade em `details`; validacao usa o mesmo contrato e responde 422 com os campos; schema e descricao nunca aparecem no log | `SqlQueryApiTest` ("valida o corpo...", "devolve 402...", "nao escreve schema nem descricao no log"), `ApiErrorEnvelopeTest` (422, 402) |
| 13 | Os rate limits da API sao por token (`API_RATE_*`, `config/api.php`): POST 30/min e 300/h, leitura 120/min; o 429 preserva `Retry-After`; o CORS cobre `api/*` fechado por padrao e sem credenciais | `SqlQueryApiTest` ("aplica o limite de taxa por token..."), `ApiErrorEnvelopeTest` ("429 com Retry-After"), `ApiServiceProvider`, `config/cors.php` |
| 14 | `GET /api/v1/me` devolve conta, plano e cota; `GET /api/v1/output-targets` lista somente alvos `enabled`, com slug, label, idioma e dialetos | `SqlQueryApiTest` ("devolve conta, plano e cota...", "lista somente os alvos habilitados"), `MeController`, `OutputTargetController` |
| 15 | Tokens de API: a tela lista sem expor o valor, cria com o texto puro mostrado **uma vez**, recusa nome repetido na mesma conta, permite o mesmo nome em outra conta e revoga so o token da propria conta; a pagina e `noindex`; o contrato esta em `docs/API.md` e `docs/openapi.yaml`, sem `/docs` publico | `ApiTokenSettingsTest` (6 testes), `Settings\ApiTokenController`, `ApiTokens.vue`, `docs/API.md`, `docs/openapi.yaml` |
| 16 | `SecurityHeaders` emite CSP em `enforce` com nonce por resposta, o script inline do tema usa o mesmo nonce, nonces nao se repetem, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options: DENY` e `frame-ancestors 'none'`; `report-only` troca o header e `off` nao emite CSP | `SecurityHeadersTest` (10 testes), `SecurityHeaders`, `config/security.php` |
| 17 | HSTS so sai em requisicao segura, respeita `HSTS_MAX_AGE`/`includeSubDomains` e desliga com zero; TrustProxies aceita `X-Forwarded-*` so do proxy listado e le a config a cada requisicao; `force_https` gera URLs em `https`; o cookie de sessao e `secure` por padrao em producao, mantendo `http_only` e `same_site=lax` | `SecurityHeadersTest` (HSTS, force https), `TrustProxiesTest` (4 testes), `SessionConfigTest` (4 testes) |
| 18 | Turnstile so vale no cadastro: com chave, token valido passa e confere o segredo na siteverify, sem token ou com token reprovado recusa, e erro/timeout falham fechado; sem a chave, o cadastro passa sem token e o widget nao aparece; `Http::fake` impede chamada real | `TurnstileTest` (6 testes), `ValidTurnstile`, `TurnstileWidget.vue`, `Register.vue` |
| 19 | `noindex` cobre settings, desafio 2FA e tokens de API, e nao atinge paginas publicas; `twitter:card/title/description/image/image:alt` saem completos nas paginas indexaveis | `SeoMetaTest` ("marca como noindex as telas de conta...", "mantem as paginas publicas fora...", "completa o card do Twitter..."), `Seo::NOINDEX`, `app.blade.php` |
| 20 | JSON-LD: Organization e WebSite na home, SoftwareApplication e BreadcrumbList na tool, no idioma da pagina, em um bloco por entidade, com JSON valido; nada e emitido em pagina `noindex`, na 404 ou sem dados proprios | `StructuredDataTest` (7 testes), `StructuredData`, `app.blade.php` |
| 21 | A 404 customizada usa o componente `Error` com layout publico, no idioma do prefixo (ou idioma preferido sem prefixo), status 404 e `noindex`, sem card, hreflang ou JSON-LD; `abort(404)` de controller tambem cai nela; a API responde JSON | `ErrorPageTest` (6 testes), `Error.vue`, `lang/*/errors.php` |
| 22 | Admin de alvos: lista o catalogo com idioma, dialetos e plano exigido; PATCH alterna **somente** `enabled`, registra `OutputTargetUpdated` com `slug`/`enabled` quando muda e nao registra quando o estado e o mesmo; visitante e usuario comum sao barrados; valor nao booleano e 422 | `AdminOutputTargetsTest` (6 testes), `Admin\OutputTargetController`, `OutputTargets.vue` |
| 23 | Admin de cache: lista **so metadados** (hash truncado, modelo, versao, tokens, hits, datas, expirado), filtra `valid`/`expired`, poda apenas as expiradas com `AiCachePruned` (contagem em `meta.removidas`) e nao registra auditoria quando nao ha nada; prompt, schema, payload canonico e resposta nunca chegam nas props. **Desvio consciente**: a tabela nao tem coluna de alvo e a tela nao exibe alvo, porque derivar o alvo exigiria decodificar `canonical_payload`, proibido pelo projeto | `AdminAiCacheTest` (6 testes), `Admin\AiCacheController`, `AiCache.vue` |
| 24 | A coluna legada `usage_ledger.ai_request_id` nao e mais lida nem escrita: modelo, action e factory nao a referenciam, o lancamento continua funcionando e a coluna permanece nula; o `DROP` fica documentado para o deploy seguinte, com a justificativa do worker antigo | `AiActionsTest` ("debita um uso do Free..." verifica `ai_request_id` nulo), `UsageLedger`, `AiLedger`, `UsageLedgerFactory`, `docs/RUNBOOK.md`, `docs/SPEC.md` |
| 25 | Toda string visivel vem do i18n nos cinco idiomas (novos namespaces `errors`, chaves de `account`, `settings`, `admin`, `auth`, `home`); a paridade de chaves continua valendo; nenhum modelo, preco ou limite fica fixo no codigo; nenhum segredo aparece em chat, git, log, issue ou documento | `TranslationsParityTest`, testes de cinco idiomas, `config/ai.php`, `config/api.php`, `config/security.php`, `.env.example` |
| 26 | Documentacao final sincronizada: `docs/SPEC.md` (F7 concluida, API v1, historico, hardening, SEO, coluna legada), `docs/RUNBOOK.md` (producao, CSP, Turnstile, API, historico), `docs/ARQUITETURA.md` (API v1, seguranca HTTP), `docs/glossario.md` (PAT, opt-in, CSP, nonce, HSTS, Turnstile, JSON-LD, breadcrumb), `CHANGELOG.md`, ADRs 050 a 053 e `docs/guides/cron.md` | Leitura e busca por termos desatualizados ("Docs entra na F7", "API publica futura") |
| 27 | Gates da fase verdes e nenhum commit/push do assistente | Evidencias abaixo; `git status` e historico |
| 28 | Smoke real com a Mercado Pago (herdado da F6) | **Pendente**: sem credenciais no ambiente; roteiro no `docs/acceptance/FASE-6.md`. Fora do escopo da F7 |

## Evidencias dos gates (2026-10-22)

```text
composer test        -> 654 testes, 654 passaram, 13.962 assercoes (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 57 arquivos, 771 testes, 771 passaram
npm run types:check  -> vue-tsc limpo (Wayfinder regenerado)
npm run lint:check   -> 0 erros (10.513 avisos de estilo pre-existentes)
npm run format:check -> "All matched files use Prettier code style!"
npm run build        -> ok (20,42 s; aviso apenas de chunk > 500 kB)
npm run build:ssr    -> ok (15,79 s; bootstrap/ssr/ssr.js gerado)
migrate:fresh --seed -> SQLite dedicado storage/smoke_f7.sqlite: 36 migrations + seed ok (9 alvos, 3 planos, 4 rotinas)
route:list           -> api/v1 (me, output-targets, sql-queries index/store/show), settings/api-tokens (3), dashboard/history (3), admin/output-targets (2), admin/ai-cache (2)
schedule:list        -> 4 closures ativas; task_schedules: accounts:purge-deleted dailyAt:03:00, payments:expire */5, webhooks:prune 30 3, history:prune 45 3
dry-runs             -> history:prune 0 consulta(s), ai:prune-cache 0 resposta(s), payments:expire 0 checkout/doacao, webhooks:prune 0 evento(s)
smoke local          -> CSP nonce no header e no HTML (32 chars, iguais na mesma resposta), HSTS/XCTO/Referrer-Policy/Permissions-Policy/X-Frame-Options; sem HSTS em HTTP direto; canonical/OG em https; home com Organization + WebSite; 404 com componente Error e noindex; /register sem cf-turnstile; API 401 sem Accept, /me 200, /output-targets 200 (9 alvos), /sql-queries 200 e POST invalido 422 no envelope
```

Arquivos de teste novos ou ampliados na fase, com os casos que sustentam o mapa
acima:

| Arquivo | Casos |
| --- | --- |
| `tests/Feature/Api/V1/SqlQueryApiTest.php` | 16 |
| `tests/Feature/Api/V1/ApiErrorEnvelopeTest.php` | 8 |
| `tests/Feature/Settings/ApiTokenSettingsTest.php` | 6 |
| `tests/Feature/SecurityHeadersTest.php` | 10 |
| `tests/Feature/TrustProxiesTest.php` | 4 |
| `tests/Feature/Auth/TurnstileTest.php` | 6 |
| `tests/Feature/SessionConfigTest.php` | 4 |
| `tests/Feature/StructuredDataTest.php` | 7 |
| `tests/Feature/ErrorPageTest.php` | 6 |
| `tests/Feature/Ai/HistoryMaintenanceTest.php` | 5 |
| `tests/Feature/AdminOutputTargetsTest.php` | 6 |
| `tests/Feature/AdminAiCacheTest.php` | 6 |
| `tests/Feature/AccountPanelTest.php` | historico e exportacao ampliados |
| `tests/Feature/Ai/AiActionsTest.php` | visibilidade e coluna legada ampliados |
| `resources/js/__tests__/account-panel.test.ts` | toggle, exclusao e estado bloqueado |
| `resources/js/__tests__/api-tokens-settings.test.ts` | criar, copiar uma vez e revogar |
| `resources/js/__tests__/turnstile-widget.test.ts` | render e callback do widget |

## Desvios e ajustes feitos durante a verificacao

- **Cache de IA sem coluna de alvo**: o plano previa listar "alvo" na tela de
  cache, mas `ai_cache` nao tem essa coluna. Derivar o alvo exigiria decodificar
  `canonical_payload`, que contem schema - proibido pelo projeto. A tela exibe
  apenas metadados e a poda; a decisao fica registrada aqui e no codigo.
- **Pint**: `composer test` acusou espacos em `bootstrap/app.php` e fim de
  linha nos `lang/*/admin.php`; o `pint` corrigiu antes da suite final.
- **AdminPanelTest**: passou a comparar `actionTypes` com
  `AdminActionType::cases()` em vez de um numero fixo, para nao quebrar a cada
  novo tipo de auditoria.
- **Smoke da API sem `Accept`**: o smoke local achou um 500 em
  `GET /api/v1/me` sem o cabecalho `Accept`: o middleware de autenticacao
  tentava montar o redirect de login com `{locale}` e estourava antes do
  envelope. O middleware `ForceJsonResponse` passou a fixar
  `Accept: application/json` em `api/*`, e o teste "401 JSON mesmo sem o
  cabecalho Accept" cobre o caso.

## Fora de escopo e riscos

- **Smoke real da Mercado Pago**: continua pendente da F6, sem credenciais;
  nenhuma afirmacao deste aceite diz que o provedor real foi exercitado.
- **CSP**: `style-src` mantem `'unsafe-inline'` por causa do tema e do shadcn-vue;
  um host externo novo exige atualizar a politica. O rollback operacional e
  `CSP_MODE=report-only`.
- **TrustProxies**: sem `TRUSTED_PROXIES=127.0.0.1` no VPS, HSTS, canonical e
  rate limit por IP ficam errados; o RUNBOOK traz o checklist.
- **Turnstile**: sem chaves fica desativado; com chaves, a indisponibilidade da
  Cloudflare bloqueia cadastros (falha fechada).
- **Retencao**: e uma varredura diaria; uma linha pode viver ate ~24 h alem do
  prazo. A exclusao manual nao apaga consulta em voo.
- **Coluna legada**: o `DROP` **nao** entra nesta entrega; so no deploy
  seguinte, quando nenhum worker antigo referenciar a coluna.
- **Nenhum commit ou push**: o usuario revisa e aprova pelo `gitpr`.
