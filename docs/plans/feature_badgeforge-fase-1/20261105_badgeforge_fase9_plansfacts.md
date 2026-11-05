# BadgeForge — Fase 9: API de gestão completa

Plano aprovado em 2026-11-05. Origem do grill:
`docs/survey/20261105_badgeforge_fase9_surveyfacts.md`.
Plano completo: `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase9.md`.

## Contexto

A F8 entregou o contrato OpenAPI 3.1 (1.8.0) e metade da API de gestão em
`/api/v1/badgeforge/*`. A ADR-088 deixou de fora PATCH, rotação de
credencial, refresh, CRUD de endpoint/provider customizado e analytics JSON.
A F9 fecha esse espelho do painel sem criar uma segunda implementação das
regras: as actions e FormRequests da web continuam sendo a autoridade.

Escopo: só BadgeForge. Fora: pendentes operacionais (Mercado Pago, VPS,
rotação de chaves, `TRUSTED_PROXIES`), presets, GET por ID e UI/i18n.

## Decisões fechadas no grill

1. Espelho completo do painel, sem presets: badges (PATCH, credential,
   refresh), logos (PATCH), endpoints (CRUD) e custom providers (CRUD).
2. PATCH recebe a representação dos campos mutáveis e reusa os FormRequests
   da web — não é JSON Merge Patch.
3. Credencial é write-only (cifrada, nunca ecoada, invalida cache) e refresh
   é síncrono devolvendo `url/pngUrl/generatedAt`; refresh empilha
   `api-write` + `badgeforge-refresh`.
4. Endpoints: Free cria como na tela; origem imutável e nunca retornada.
5. Custom providers: POST/PATCH atrás de `badgeforge.paid` (403); DELETE
   liberado após downgrade; template/path params/example path imutáveis;
   conjunto de slugs fixo; re-probe e bump de `metrics_version`.
6. Analytics JSON-only, sem `exportUrl`; Free 30 dias; janela inválida em 30;
   `QueryException` → 503 `badge_unavailable`.
7. Sem GET por ID na API.
8. Contrato 1.9.0 + ADR-090 + emenda ADR-088; sem UI pública/i18n.
9. Abilities e limites da F8 reusados; nenhuma ability nova.
10. Envelope `ApiError` em todos os erros; dono errado 404; Free em badge
    422; Free em custom provider 403.

## Contrato

| Método | Path | Nota |
|---|---|---|
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}` | `name` |
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}/credential` | write-only |
| POST | `/api/v1/badgeforge/badges/{badgeAsset}/refresh` | `badgeforge-refresh` extra |
| PATCH | `/api/v1/badgeforge/logos/{badgeCustomLogo}` | `RenameCustomLogo` |
| GET/POST | `/api/v1/badgeforge/endpoints` | lista paginada / cria no Free |
| PATCH/DELETE | `/api/v1/badgeforge/endpoints/{badgeEndpoint}` | nome / `{id,deleted}` |
| GET/POST | `/api/v1/badgeforge/custom-providers` | pago para criar |
| PATCH/DELETE | `/api/v1/badgeforge/custom-providers/{badgeCustomProvider}` | pago para editar / exclui após downgrade |
| GET | `/api/v1/badgeforge/analytics?window=` | sem `exportUrl`, 503 |

## Arquivos

**API**: `routes/api.php`; controllers novos `BadgeEndpointController`,
`BadgeCustomProviderController`, `AnalyticsController`; estender
`BadgeAssetController`, `BadgeLogoController`; resources
`ApiBadgeEndpointResource` e `ApiBadgeCustomProviderResource`.

**Actions/web**: `app/Actions/BadgeForge/RenameCustomLogo.php` (nova, usada
também pelo `CustomLogoController`); `EnsureBadgeForgeHasPaidPlan` com nota da
F9; `BadgeForgeServiceProvider` com ramo de API no `response()` do limiter.

**Contrato**: `docs/badgeforge/openapi.yaml` 1.9.0;
`OpenApiContractTest` na versão 1.9.0.

**Docs**: ADR-090, emenda ADR-088, nota ADR-071, `API.md`, `OPENAPI.md`,
SPEC §§12/14/16/17, glossário, `CLAUDE.md`, `RUNBOOK.md`, aceite e relatório.

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Badge PATCH/credential/refresh + limiter API-aware + spec 1.9.0 |
| E2 | Rename de logo via action + PATCH + spec |
| E3 | CRUD de endpoints + resource + spec |
| E4 | CRUD de custom providers + paid gate + spec |
| E5 | Analytics JSON + 503 + spec |
| E6 | ADR-090 e atualização de docs |
| E7 | Aceite, gates, smoke real, relatório e artefatos |

## Testes

`ManagementApi/`: `BadgeWriteApiTest` (estendido), `BadgeRefreshApiTest`,
`LogoWriteApiTest` (estendido), `EndpointApiTest`, `CustomProviderApiTest`,
`AnalyticsApiTest`, `AbilityIsolationTest` (estendido) e
`OpenApiContractTest`.

## Gates e smoke

`composer test`, `npm run test`, `npm run types:check`,
`npm run lint:check`, `npm run build`, `composer audit`,
`npm audit --audit-level=high`, `route:list --path=api/v1/badgeforge`.

Smoke com SQLite descartável e servidor efêmero: PATCH, credencial,
refresh, rename de logo, CRUD de endpoint e custom provider, analytics com
acesso real, 403 de Free, 404 de dono errado e DELETE; tudo apagado ao final.

## Riscos

- Placeholders da spec precisam bater com os bindings reais; o logo tem dois
  paths (`{logo}` no DELETE, `{badgeCustomLogo}` no PATCH).
- Sem o model tipado com o nome do parâmetro, o `unique` ignora o id errado e
  o nome inalterado vira 422.
- O 429 do limiter de refresh não passa pelo renderer; precisa devolver o
  envelope quando `is('api/*')`.
- `badgeforge.paid` roda depois de `SubstituteBindings` e das abilities; Free
  recebe 403 antes da validação no custom provider.
- Analytics precisa de `try/catch` local para `QueryException`.
- Nenhuma resposta pode conter `credential`, `url` de endpoint ou
  `url_template`.

## Fora de escopo

Presets, GET por ID, CSV por API, API de admin, webhooks/bulk, viewer,
meta-schema, página de status, docs pública/i18n e pendentes operacionais.
`format:check` continua dívida histórica.
