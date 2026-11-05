# BadgeForge — Fase 9: API de gestão completa

Plano aprovado em 2026-11-05. Origem do grill:
`docs/survey/20261105_badgeforge_fase9_surveyfacts.md`.
Registro resumido: `docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_fase9_plansfacts.md`.

## Contexto

A F8 entregou o contrato OpenAPI 3.1 (versão 1.8.0) e a primeira metade da
API de gestão em `/api/v1/badgeforge/*`: catálogo anônimo, listas de logos e
badges, criação de logo (multipart) e de badge pessoal, e exclusões. A própria
ADR-088 registrou como fora de escopo o PATCH, a rotação de credencial, a
criação/edição de endpoint e provider customizado e a API JSON de analytics —
exatamente o que falta para automatizar tudo o que o painel
`/dashboard/badgeforge` faz.

O `docs/SPEC-BADGEFORGE.md` §16 termina na F8, então a F9 não tem definição.
O grill (três rodadas) fechou o tema **"API de gestão completa"**, escopo
**"só BadgeForge"** e os pendentes operacionais (smoke Mercado Pago, checklist
VPS, rotação de chaves, `TRUSTED_PROXIES`) **fora da fase**. O resultado é
aditivo: nenhum cliente F8 quebra, o contrato sobe para **1.9.0** e a API passa
a espelhar o painel, reusando as mesmas actions/FormRequests — sem uma segunda
implementação de plano pago, SSRF, quota, sanitização ou cifra de PAT.

## Decisões fechadas no grill

1. **Espelho completo do painel, sem presets**: badges (PATCH nome, PATCH
   credencial, POST refresh), logos (PATCH rename), endpoints
   (GET/POST/PATCH/DELETE) e custom providers (GET/POST/PATCH/DELETE). Presets
   ficam fora porque são snapshot do builder, sem URL pública nem uso fora do
   navegador.
2. **PATCH espelha o web**: recebe a representação completa dos campos
   mutáveis e reusa os FormRequests existentes. Badge/logo/endpoint aceitam só
   `name` (obrigatório); custom provider aceita `name` + `metrics` completo,
   com `url_template` `prohibited` e conjunto de slugs fixo. Não é JSON Merge
   Patch; documentar na spec.
3. **Credencial e refresh entram**, espelhando a web:
   `PATCH .../credential` write-only (cifrada, nunca ecoada, apaga SVG/PNG em
   cache, só provider que aceita privado e plano pago, sem "remover" —
   revogar = excluir) e `POST .../refresh` síncrono, devolvendo
   `url/pngUrl/generatedAt`. Refresh empilha `api-write` (30/min por token) +
   `badgeforge-refresh` (10/min por conta).
4. **Endpoints**: Free pode criar (igual à web), sem limite de contagem por
   conta; `url` de origem é imutável e nunca retornada; probe/SSRF idêntico.
5. **Custom providers**: POST/PATCH exigem plano pago (`badgeforge.paid` →
   403); DELETE continua permitido após downgrade. `url_template`,
   `path_params` e `example_path` imutáveis; conjunto de slugs fixo no PATCH;
   re-probe e bump de `metrics_version` iguais à web. `url_template` nunca
   retorna; `pointer` sim.
6. **Analytics JSON only**: `GET /analytics?window=` devolve o shape de
   `OwnerAnalytics` sob `data`, **sem `exportUrl`** (a rota de CSV é web e
   session-based); Free mantém o recorte de 30 dias; janela inválida cai em
   30 (nunca 422); `QueryException` → 503 `badge_unavailable`. CSV continua só
   na web.
7. **Sem GET por ID**: listas paginadas + recurso devolvido por POST/PATCH
   bastam; manter a superfície enxuta.
8. **Contrato 1.9.0 + ADR-090 + emenda na ADR-088**, sem mudança de UI
   pública/i18n. Atualiza `API.md`, `OPENAPI.md`, SPEC §§14/16/17, glossário,
   `CLAUDE.md` e `RUNBOOK.md`.
9. **Abilities e limites reusados**: `badgeforge:read` + `api-read` nas
   leituras, `badgeforge:write` + `api-write` nas escritas. Nenhuma ability
   nova; token novo continua só com `ai:query`.
10. **Erros**: envelope `ApiError` (`data`/`meta`/`links` no sucesso);
    dono errado → 404 `not_found`; validação/plano de badge → 422
    `validation_error`; plano pago de custom provider → 403 `forbidden`;
    throttle → 429 `rate_limited` com headers.

## Contrato

Todas as rotas novas ficam nos grupos já existentes de `routes/api.php`
(leitura: `auth:sanctum, active, verified, abilities:badgeforge:read,
TrackApiTokenUsage, throttle:api-read`; escrita: idem com
`badgeforge:write`/`api-write`). Path params usam `whereUlid` e nomes exatos
para a paridade da spec.

| Método | Path | Corpo | Resposta |
| --- | --- | --- | --- |
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}` | `name` | 200 `data: BadgeAsset` |
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}/credential` | `credential`, `credential_username?` | 200 `data: BadgeAsset` (`credentialSet`) |
| POST | `/api/v1/badgeforge/badges/{badgeAsset}/refresh` | — | 200 `data: {url,pngUrl,generatedAt}` |
| PATCH | `/api/v1/badgeforge/logos/{badgeCustomLogo}` | `name` | 200 `data: BadgeLogo` |
| GET | `/api/v1/badgeforge/endpoints` | `per_page?` | 200 `data[]` + `meta`/`links` |
| POST | `/api/v1/badgeforge/endpoints` | `name`, `url` | 201 `data: BadgeEndpoint` |
| PATCH | `/api/v1/badgeforge/endpoints/{badgeEndpoint}` | `name` | 200 `data: BadgeEndpoint` |
| DELETE | `/api/v1/badgeforge/endpoints/{badgeEndpoint}` | — | 200 `data: {id,deleted:true}` |
| GET | `/api/v1/badgeforge/custom-providers` | `per_page?` | 200 `data[]` + `meta`/`links` |
| POST | `/api/v1/badgeforge/custom-providers` | `name`, `url_template`, `path_params`, `example_path`, `metrics` | 201 `data: BadgeCustomProvider` |
| PATCH | `/api/v1/badgeforge/custom-providers/{badgeCustomProvider}` | `name`, `metrics` | 200 `data: BadgeCustomProvider` |
| DELETE | `/api/v1/badgeforge/custom-providers/{badgeCustomProvider}` | — | 200 `data: {id,deleted:true}` |
| GET | `/api/v1/badgeforge/analytics?window=7\|30\|90` | — | 200 `data: OwnerAnalytics` (sem `exportUrl`) |

Detalhes de comportamento:

- **PATCH** usa os FormRequests da web sem mudança: `UpdateBadgeAssetRequest`,
  `SetBadgeAssetCredentialRequest`, `UpdateCustomLogoRequest`,
  `UpdateBadgeEndpointRequest`, `UpdateBadgeCustomProviderRequest`; o
  controller declara o model com o **nome exato** do parâmetro da rota
  (`$badgeAsset`, `$badgeCustomLogo`, `$badgeEndpoint`,
  `$badgeCustomProvider`) para o `ignore()` da regra `unique` funcionar.
- **Refresh**: `RefreshBadgeAsset` retorna `PersonalBadge`, mas persiste no
  próprio `$badgeAsset`; ler o model vinculado depois da chamada. O limiter
  `badgeforge-refresh` passa a responder o envelope `rate_limited` quando
  `$request->is('api/*')`, mantendo o toast/JSON `{message}` atual na web.
- **Analytics**: chama `OwnerAnalytics::for($user, $request->query('window'))`
  e remove `exportUrl` do array; `QueryException` é tratado no controller
  (o renderer global só cobre `api/badges/*` e `b/*`).
- **Custom provider**: POST/PATCH ficam em grupo aninhado
  `Route::middleware('badgeforge.paid')` dentro do grupo de escrita; DELETE
  fica fora, permitindo downgrade.
- **Recursos**: `ApiBadgeEndpointResource` (`id,name,svgUrl,pngUrl,createdAt`)
  e `ApiBadgeCustomProviderResource` (`id,name,enabled,examplePath,pathParams,
  definitions[{slug,label,pointer,formatter}],createdAt`; nunca
  `url_template`). Reusar `ApiBadgeAssetResource`/`ApiBadgeLogoResource`.
- **Spec OpenAPI 1.9.0**: paths, `operationId`s, `NameInput`,
  `BadgeCredentialInput`, `BadgeRefreshResult`, `BadgeEndpoint`,
  `BadgeEndpointInput`, `BadgeCustomProvider`,
  `BadgeCustomProviderDefinition`, `BadgeCustomProviderInput`,
  `BadgeCustomProviderUpdateInput`, `OwnerAnalytics`, parâmetros
  `BadgeCustomLogo`/`BadgeCustomProviderId`/`AnalyticsWindow` e resposta 503
  `BadgeUnavailable`. Atualizar a descrição do `bearerAuth` para incluir
  PATCH. O path novo de logo é separado do DELETE existente
  (`{badgeCustomLogo}` vs `{logo}`).

## Arquivos

**Rotas/API**: `routes/api.php`; estender
`app/Http/Controllers/Api/V1/BadgeForge/BadgeAssetController.php` e
`BadgeLogoController.php`; criar `BadgeEndpointController.php`,
`BadgeCustomProviderController.php`, `AnalyticsController.php`; criar
`app/Http/Resources/ApiBadgeEndpointResource.php` e
`ApiBadgeCustomProviderResource.php`.

**Actions**: criar `app/Actions/BadgeForge/RenameCustomLogo.php` e passar a
usá-la em `app/Http/Controllers/BadgeForge/CustomLogoController.php` e no
controller da API (evita regra duplicada). Demais actions reusadas:
`UpdateBadgeAsset`, `SetBadgeAssetCredential`, `RefreshBadgeAsset`,
`CreateBadgeEndpoint`, `UpdateBadgeEndpoint`, `DeleteBadgeEndpoint`,
`CreateBadgeCustomProvider`, `UpdateBadgeCustomProvider`,
`DeleteBadgeCustomProvider`.

**Provider**: `app/Providers/BadgeForgeServiceProvider.php` — ramo de API no
`response()` do limiter `badgeforge-refresh`, seguindo o padrão do
`badgeforge-management`.

**Contrato**: `docs/badgeforge/openapi.yaml` (1.9.0),
`tests/Feature/BadgeForge/OpenApiContractTest.php` (versão 1.9.0).

**Docs**: `docs/adr/ADR-090-badgeforge-api-de-gestao-completa.md` (novo),
emenda em `docs/adr/ADR-088-badgeforge-api-de-gestao.md`,
`docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md`,
`docs/SPEC-BADGEFORGE.md` (§14, §16 com a linha F9, §17),
`docs/glossario.md`, `CLAUDE.md`, `docs/RUNBOOK.md`.

## Etapas

| Etapa | Entrega |
| --- | --- |
| E1 | Badge writes: rotas PATCH/credential/refresh, controller, limiter de refresh API-aware, spec 1.9.0 (`info.version` e três operações), testes |
| E2 | Logo rename: `RenameCustomLogo`, PATCH na API, web delegando à action, spec, testes |
| E3 | Endpoints: GET/POST/PATCH/DELETE, `ApiBadgeEndpointResource`, spec, testes |
| E4 | Custom providers: GET/POST/PATCH/DELETE, resource, middleware `badgeforge.paid` em POST/PATCH, spec, testes |
| E5 | Analytics JSON: controller, rota, schema `OwnerAnalytics`, 503, spec, testes |
| E6 | ADR-090, emenda ADR-088, `API.md`, `OPENAPI.md`, SPEC, glossário, `CLAUDE.md`, `RUNBOOK.md` |
| E7 | Aceite, gates completos, smoke real, relatório, artefatos da skill e parada para revisão |

Ordem: E1 antes de E2–E5 (versão e limiter compartilhados); E2–E5 são
independentes entre si; E6 depois das rotas (documenta o contrato final); E7
por último. Cada etapa de rota atualiza a spec no mesmo passo para manter o
teste de paridade verde.

## Testes

Pest, em `tests/Feature/BadgeForge/ManagementApi/`:

- `BadgeWriteApiTest` (estender): PATCH renomeia static/provider, nome
  inalterado passa, dono errado 404, token read-only 403, Free 422
  `validation_error`/`badge`, credencial não ecoada.
- `BadgeRefreshApiTest` (novo): envelope `data.url/pngUrl/generatedAt`;
  static/Free 422; dono errado 404; 429 `rate_limited` com `Retry-After` e
  `X-RateLimit-Limit: 10`; dois tokens da mesma conta dividem a cota.
- `LogoWriteApiTest` (estender): rename, nome inalterado, duplicado 422,
  dono errado 404, resposta só com metadados.
- `EndpointApiTest` (novo): Free cria 201; probe/SSRF insegura 422; lista
  paginada só do dono; PATCH nome; DELETE; `url` ausente de toda resposta;
  token read-only 403.
- `CustomProviderApiTest` (novo): lista com definitions e sem
  `url_template`; Free POST/PATCH 403; pago cria/edita; probe 422;
  `url_template` no PATCH 422 (`prohibited`); slug set diferente 422; DELETE
  após downgrade 200; dono errado 404; limite de plano.
- `AnalyticsApiTest` (novo): shape Free 30d; pago `window=7` e fallback;
  `exportUrl` ausente; isolamento por dono; token write-only 403; drop de
  `badge_views` → 503 `badge_unavailable` com `Retry-After: 60`/`no-store`.
- `AbilityIsolationTest` (estender): cada verbo novo nos dois lados da
  fronteira read/write.
- `OpenApiContractTest`: versão 1.9.0; a paridade bidirecional já cobre todos
  os paths novos pelo prefixo.

Fixtures: reusar os helpers de conta paga/token de `BadgeWriteApiTest` e os
bindings `HostResolver` + `Http::fake` de
`tests/Feature/BadgeForge/Endpoint/EndpointManagementTest.php` e
`tests/Feature/BadgeForge/Custom/CustomProviderManagementTest.php`; nomes de
helpers únicos por arquivo (funções globais no Pest).

## Gates e smoke

Gates: `composer test` (Pint + PHPStan 7 + Pest), `npm run test`,
`npm run types:check`, `npm run lint:check`, `npm run build`,
`composer audit`, `npm audit --audit-level=high`,
`php artisan route:list --path=api/v1/badgeforge`. `composer ci:check`
continua falhando só no `format:check` histórico (162 arquivos), documentado.

Smoke com rede real (`php artisan serve`, SQLite descartável migrado, usuário
`pro-monthly`, tokens descartáveis, tudo apagado ao final): PATCH de badge,
credencial em badge privado (dummy PAT), refresh de badge público, rename de
logo, criação de endpoint com probe público
(`https://img.shields.io/badge/build-passing-green.json`), criação de custom
provider com a mesma origem (`/message`), listas paginadas, analytics após
acessar a badge pessoal (totais > 0), DELETE dos recursos, 403 de Free em
custom provider e 404 de dono errado. Se a origem pública não responder ao
probe no momento do smoke, registrar o desvio no relatório (os testes cobrem
o probe com `Http::fake`).

Execução em 2026-11-05: o JSON do shields não traz `schemaVersion`, então o
endpoint apontado para ele recebeu 422 (comportamento correto); o sucesso foi
coberto com host que não resolve (tolerado) e o custom provider usou a mesma
origem com probe real aceito. Detalhes no aceite e no relatório.

## Riscos

- **Paridade da spec**: a spec precisa usar exatamente `{badgeAsset}`,
  `{badgeCustomLogo}`, `{badgeEndpoint}` e `{badgeCustomProvider}`; o logo
  mantém dois paths distintos (`{logo}` no DELETE, `{badgeCustomLogo}` no
  PATCH).
- **Binding + unique**: sem o model tipado com o nome exato do parâmetro, o
  `ignore()` da regra `unique` não recebe o id e o PATCH com nome inalterado
  vira 422.
- **429 do refresh**: `HttpResponseException` do limiter não passa pelo
  renderer; o `response()` precisa devolver o envelope quando for `api/*`,
  senão a API responde `{message}`.
- **Ordem do `badgeforge.paid`**: dentro do grupo de escrita, o 429 de
  `api-write` vence o 403 de plano; Free em custom provider recebe 403 antes
  da validação — e 422 (não 403) em badge/credencial/refresh, porque ali a
  regra está na action. Testar os dois de propósito.
- **Analytics**: `QueryException` só é mapeada globalmente para `api/badges/*`
  e `b/*`; sem o `try/catch` no controller, tabela ausente vira 500.
- **Probe em teste**: sem `Http::fake`/`HostResolver`, os testes de endpoint e
  custom provider tentam rede real.
- **Segredos**: nenhuma resposta pode conter `url_template`, `url` de
  endpoint ou `credential`; garantir com asserção no corpo cru.
- **Refresh**: ler o `$badgeAsset` vinculado depois da action; o retorno
  `PersonalBadge` não tem os campos da resposta.

## Fora de escopo

Presets na API, GET por ID, CSV de analytics por API, API de admin,
webhooks/bulk, viewer embutido, validação por meta-schema, página de status,
mudanças na página pública de docs/i18n e os pendentes operacionais (smoke
Mercado Pago real, checklist de VPS, rotação de chaves, `TRUSTED_PROXIES`).
A dívida de `format:check` continua fora.

## Regra final

Ao terminar, gerar `docs/acceptance/BADGEFORGE-FASE-9.md`, relatório em
`docs/claude-code/reports/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase9.md`,
plano em `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase9.md`,
`docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_fase9_plansfacts.md`
e `docs/survey/20261105_badgeforge_fase9_surveyfacts.md`; rodar todos os
gates e parar para revisão. **Nenhum commit nem push.**
