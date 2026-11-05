# BadgeForge — Fase 9 (API de gestão completa)

- Data: 2026-11-05
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase9.md`
- Levantamento/plano da skill: `docs/survey/20261105_badgeforge_fase9_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_fase9_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-9.md`
- ADR: `docs/adr/ADR-090-badgeforge-api-de-gestao-completa.md` (emenda na
  `docs/adr/ADR-088-badgeforge-api-de-gestao.md` e nota de versão na
  `docs/adr/ADR-071-badgeforge-documentacao-publica.md`)

## Objetivo

A F8 entregou o contrato OpenAPI 3.1 (1.8.0) e a primeira metade da API de
gestão: catálogo anônimo, listas, criação de logo/badge e exclusões. A própria
ADR-088 registrou como fora de escopo o PATCH, a rotação de credencial, o
refresh, a criação/edição de endpoint e de provider customizado e a API JSON
de analytics — exatamente o que faltava para automatizar o painel
`/dashboard/badgeforge`.

A F9 fecha esse buraco espelhando o painel em `/api/v1/badgeforge/*`, reusando
as mesmas actions/FormRequests (sem uma segunda implementação de plano pago,
SSRF, quota, sanitização ou cifra de PAT) e sobe o contrato para **1.9.0** de
forma aditiva. Nenhum cliente da F8 quebra.

Decisões fechadas no grill: espelho completo sem presets; PATCH espelhando a
web (não JSON Merge Patch); credencial e refresh iguais à tela; Free pode
criar endpoint; custom provider só pago para criar/editar e excluível após
downgrade; analytics JSON sem `exportUrl`; sem GET por ID; contrato 1.9.0 +
ADR-090; sem mudança de UI pública/i18n.

## Resumo executivo

- **Badges**: `PATCH` do nome (estática e provider, com `unique` ignorando o
  próprio id), `PATCH .../credential` write-only que cifra o PAT, invalida
  SVG/PNG em cache e nunca ecoa o valor, e `POST .../refresh` síncrono que
  devolve `url`/`pngUrl`/`generatedAt`. O refresh empilha `api-write`
  (30/min por token) + `badgeforge-refresh` (10/min por conta), e o limiter
  passou a responder o envelope `rate_limited` em `api/*`.
- **Logos**: `PATCH` de rename delegando à nova action `RenameCustomLogo`,
  compartilhada com o controller web (uma regra só).
- **Endpoints**: CRUD completo com paginação, probe/SSRF das actions, Free
  podendo criar como na tela e URL de origem nunca devolvida.
- **Custom providers**: CRUD completo; `POST`/`PATCH` atrás de
  `badgeforge.paid` (403 `forbidden`), `DELETE` liberado após downgrade;
  `url_template`/`path_params`/`example_path` imutáveis, `url_template`
  `prohibited` no PATCH, conjunto de slugs fixo, re-probe e bump de
  `metrics_version` iguais à web; a origem nunca sai, o `pointer` sim.
- **Analytics**: `GET /analytics?window=` com o shape de `OwnerAnalytics` sob
  `data`, sem `exportUrl`; Free em 30 dias; janela inválida cai em 30 (nunca
  422); `QueryException` no controller vira 503 `badge_unavailable` com
  `Retry-After: 60` e `no-store`.
- **Contrato e docs**: OpenAPI **1.9.0** com 13 operações novas, schemas,
  parâmetros e a resposta 503; ADR-090, emenda ADR-088, nota na ADR-071,
  `API.md`, `OPENAPI.md`, SPEC §§12/14/16/17, glossário, `CLAUDE.md`,
  `RUNBOOK.md`.
- Gates: Pest **1.468 / 24.593**, Vitest **983 / 76**, `types:check` limpo,
  `lint:check` 0 erros, `build` exit 0, audits sem altas. Smoke real verde.

## Decisão (ADR-090 — API de gestão completa)

- A API espelha o painel, não o constrói de novo: PATCH usa os FormRequests da
  web e as actions continuam sendo a autoridade de plano, SSRF, quota, cifra e
  sanitização.
- PATCH é a representação dos campos mutáveis, e não JSON Merge Patch. Para o
  binding + `unique` funcionarem, o controller declara o model com o nome
  exato do parâmetro (`$badgeAsset`, `$badgeCustomLogo`, `$badgeEndpoint`,
  `$badgeCustomProvider`).
- O logo mantém dois paths na spec: `{logo}` no DELETE existente e
  `{badgeCustomLogo}` no PATCH novo.
- Credencial continua write-only e sem operação de remoção: revogar é excluir.
  Refresh é síncrono e devolve as três URLs/datas; falha de provider cai em
  stale/fallback 200, nunca 500.
- Endpoints são do Free (como na web), sem limite de contagem por conta; a
  origem é imutável e nunca volta.
- Custom providers são pagos para criar/editar (403 de middleware) e
  excluíveis depois do downgrade; template, path params e example path são
  imutáveis.
- Analytics é JSON-only e sem `exportUrl`; o CSV continua na web/sessão. O
  `QueryException` é tratado no controller porque o renderer global cobre só
  `api/badges/*` e `b/*`.
- Abilities e limites são os da F8, sem escopo novo; token novo segue só com
  `ai:query`.
- Contrato aditivo em 1.9.0; presets, GET por ID e CSV por API ficam fora.

## Etapas executadas

| Etapa | Entrega |
|---|---|
| E1 | PATCH de badge, credencial e refresh + limiter API-aware + helpers de refresh no provider + spec 1.9.0 |
| E2 | `RenameCustomLogo`, PATCH de logo na API e web delegando à action |
| E3 | CRUD de endpoints + `ApiBadgeEndpointResource` + spec |
| E4 | CRUD de custom providers + `ApiBadgeCustomProviderResource` + `badgeforge.paid` em POST/PATCH + spec |
| E5 | Analytics JSON + `OwnerAnalytics` na spec + 503 + isolamento de abilities |
| E6 | ADR-090, emenda ADR-088/ADR-071, `API.md`, `OPENAPI.md`, SPEC, glossário, `CLAUDE.md`, `RUNBOOK.md` |
| E7 | Aceite, gates, smoke real e este relatório |

Cada etapa de rota atualizou a spec no mesmo passo, mantendo o teste de
paridade bidirecional verde o tempo todo.

## Arquivos

**Rotas/API**: `routes/api.php`; `app/Http/Controllers/Api/V1/BadgeForge/BadgeAssetController.php`
e `BadgeLogoController.php` (estendidos); novos
`BadgeEndpointController.php`, `BadgeCustomProviderController.php`,
`AnalyticsController.php`; novos resources `ApiBadgeEndpointResource.php` e
`ApiBadgeCustomProviderResource.php`.

**Actions/middleware**: novo `app/Actions/BadgeForge/RenameCustomLogo.php`;
`app/Http/Controllers/BadgeForge/CustomLogoController.php` passou a usá-la;
`app/Http/Middleware/EnsureBadgeForgeHasPaidPlan.php` (nota da F9);
`app/Providers/BadgeForgeServiceProvider.php` (ramo de API no `response()` do
limiter `badgeforge-refresh`).

**Contrato/tests**: `docs/badgeforge/openapi.yaml` (1.9.0),
`tests/Feature/BadgeForge/OpenApiContractTest.php` (versão),
`tests/Feature/BadgeForge/ManagementApi/{BadgeWriteApiTest,BadgeRefreshApiTest,LogoWriteApiTest,EndpointApiTest,CustomProviderApiTest,AnalyticsApiTest,AbilityIsolationTest}.php`.

**Docs**: `docs/adr/ADR-090-badgeforge-api-de-gestao-completa.md`,
`docs/adr/ADR-088-badgeforge-api-de-gestao.md` (emenda),
`docs/adr/ADR-071-badgeforge-documentacao-publica.md` (versão),
`docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md`,
`docs/SPEC-BADGEFORGE.md` (§§12/14/16/17), `docs/glossario.md`,
`CLAUDE.md`, `docs/RUNBOOK.md`, `docs/acceptance/BADGEFORGE-FASE-9.md`.

## Testes

Novos/estendidos em `tests/Feature/BadgeForge/ManagementApi/`:

- `BadgeWriteApiTest` — PATCH estática/provider, nome inalterado, dono errado
  404, token read-only 403, Free 422, credencial cifrada e não ecoada.
- `BadgeRefreshApiTest` — envelope, 422 de estática/Free, 404 de dono, 429 com
  `Retry-After`/`X-RateLimit-Limit: 10` e cota compartilhada por conta.
- `LogoWriteApiTest` — rename, nome inalterado, duplicado 422, dono errado e
  resposta só com metadados.
- `EndpointApiTest` — Free cria 201, probe/SSRF 422, paginação só do dono,
  PATCH, DELETE e ausência da URL de origem em toda resposta.
- `CustomProviderApiTest` — lista com definitions e sem `url_template`, Free
  403 em POST/PATCH, pago cria/edita, probe 422, `url_template` no PATCH 422,
  slug set diferente 422, DELETE após downgrade, dono errado 404 e limite.
- `AnalyticsApiTest` — shape Free 30d, pago `window=7` e fallback, ausência de
  `exportUrl`, isolamento por dono, token write-only 403 e tabela ausente →
  503 com headers.
- `AbilityIsolationTest` — todos os verbos novos dos dois lados da fronteira
  read/write (com models reais, porque o binding roda antes das abilities).
- `OpenApiContractTest` — versão 1.9.0; a paridade já cobre os paths pelo
  prefixo.

## Gates (2026-11-05)

| Gate | Resultado |
|---|---|
| `composer test` (Pint + PHPStan 7 + Pest) | ok; **1.468 testes / 24.593 asserções** |
| `npm run test` (Vitest) | 983 testes / 76 arquivos |
| `npm run types:check` | ok |
| `npm run lint:check` (ESLint) | 0 erros; 1.182 avisos históricos |
| `npm run build` | exit 0 |
| `composer audit` | sem avisos |
| `npm audit --audit-level=high` | sem altas (14 moderadas fora do limiar) |
| `route:list --path=api/v1/badgeforge` | **21 rotas**, nomes e bindings corretos |

`composer ci:check` continua barrado apenas pelo `format:check` histórico (162
arquivos), dívida anterior à fase e documentada no workflow.

## Smoke (rede real)

Ambiente descartável: SQLite temporário migrado com o seed de planos, contas
`smokeowner` (`pro-monthly`), `smokefree` (Free) e `smokeother` (`pro-monthly`)
com tokens `badgeforge:read`/`write`, servidor embutido do PHP na porta 8911
com o mesmo router do `artisan serve`. Ao final: servidor encerrado, banco,
tokens, diretório temporário e `storage/app/data/tools/badgeforge/personal/smokeowner`
apagados. Resultado: **SMOKE OK**.

- Badge estática criada (201), renomeada (200), imagem `/b/smokeowner/{ulid}.svg`
  servida (200) e analytics com `totals.views = 1`, `window = 30`, sem
  `exportUrl`.
- Badge privada com PAT fictício criada (201, `credentialSet: true`), credencial
  trocada (200); o PAT nunca apareceu no corpo bruto.
- Refresh de badge pública do GitHub (200) com `url`, `pngUrl` e `generatedAt`
  reais.
- Logo enviado via multipart (201), renomeado (200), sem paths do SVG na
  resposta.
- Endpoint: JSON real do shields recusado com 422 `validation_error` (probe
  real sem `schemaVersion`), host com DNS morto aceito (201, falha tolerada),
  `GET ?per_page=1` com `meta`, `PATCH` (200), `DELETE` (200) e origem ausente.
- Custom provider com a origem shields e `{label}` criado (201, probe real),
  listado com definitions e sem `urlTemplate`, editado (200), excluído (200).
- Free em POST de custom provider → 403 `forbidden`; token de outra conta em
  badge alheia → 404 `not_found`; token `badgeforge:read` em escrita → 403.

### Desvio do smoke

O `.json` público do shields não traz `schemaVersion`, então o `POST` de
endpoint apontado para ele é corretamente 422; o sucesso do mesmo verbo foi
coberto com DNS que não resolve (tolerado pela action). O probe com payload
shields v1 válido está coberto por `Http::fake` nos testes. O custom provider
não sofre disso porque aceita JSON arbitrário com JSON Pointer.

## Notas

- **Bindings**: rotas usam `whereUlid` e nomeiam o model exatamente como o
  FormRequest espera; sem isso o `ignore()` do `unique` não funcionaria no
  PATCH com nome inalterado.
- **Ordem dos middlewares**: como `SubstituteBindings` roda antes das
  abilities, o isolamento de verbos precisou de models reais no teste para
  receber 403 em vez de 404.
- **Laravel 13**: `abort(403)` vira `HttpException` genérica e não entra no
  envelope; o middleware `badgeforge.paid` lança `AccessDeniedHttpException`
  de propósito.
- **Resource de custom provider**: `path_params` é `list<string>` no PHPDoc,
  então o `array_values()` era no-op e foi removido para o PHPStan.
- **Storage do smoke**: apenas a pasta do usuário descartável foi removida;
  `natanfiuza` e `smokef4` pré-existentes seguem intactos. Nenhum arquivo
  versionado foi tocado pelo smoke.
- **Estilo**: Pint corrigiu imports/FQCN em `BadgeRefreshApiTest`; nenhuma
  mudança de comportamento.

## Fora de escopo / próximos passos

Presets na API, GET por ID, CSV de analytics por API, API de admin,
webhooks/bulk, viewer embutido, meta-schema, página de status, docs
pública/i18n e os pendentes operacionais (smoke Mercado Pago real, checklist
da VPS, rotação de chaves, `TRUSTED_PROXIES`). A dívida de `format:check`
continua fora.
