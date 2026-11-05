# Survey — BadgeForge Fase 9 (API de gestão completa)

Data: 2026-11-05
Branch: `feature/badgeforge-fase-1`
Origem: `/grill-with-docs` — "Pode continuar para a proxima fase 9"

## Contexto

A F8 terminou com a API de gestão pela metade: catálogo anônimo, listas,
criação de logo/badge e exclusões. A ADR-088 registrou explicitamente como
fora de escopo PATCH, rotação de credencial, refresh, criação/edição de
endpoint e provider customizado e a API JSON de analytics. O `SPEC-BADGEFORGE`
§16 terminava na F8, então a F9 não tinha definição.

O grill fechou o tema **"API de gestão completa"**, escopo **"só BadgeForge"**,
e deixou os pendentes operacionais (smoke Mercado Pago, checklist de VPS,
rotação de chaves, `TRUSTED_PROXIES`) fora da fase.

## Fatos levantados no repositório

- **Grupos existentes**: `routes/api.php` já tem o grupo de leitura
  (`badgeforge:read`, `api-read`) e o de escrita (`badgeforge:write`,
  `api-write`) da F8, com `SubstituteBindings` antes das abilities.
- **Alias pago**: `bootstrap/app.php` registra `badgeforge.paid` →
  `EnsureBadgeForgeHasPaidPlan`, que lança `AccessDeniedHttpException` (vira
  403 `forbidden` no envelope em `api/*`).
- **Actions reutilizáveis**: `UpdateBadgeAsset`, `SetBadgeAssetCredential`,
  `RefreshBadgeAsset`, `CreateBadgeEndpoint`, `UpdateBadgeEndpoint`,
  `DeleteBadgeEndpoint`, `CreateBadgeCustomProvider`,
  `UpdateBadgeCustomProvider`, `DeleteBadgeCustomProvider`.
- **FormRequests da web**: `UpdateBadgeAssetRequest`,
  `SetBadgeAssetCredentialRequest`, `UpdateCustomLogoRequest`,
  `UpdateBadgeEndpointRequest` e `UpdateBadgeCustomProviderRequest`;
  este último proíbe `url_template` e exige o conjunto de métricas.
- **Rename de logo**: a regra vivia inline no `CustomLogoController`; não
  havia action reutilizável (criada na F9 como `RenameCustomLogo`).
- **Refresh**: `BadgeAssetController::refresh()` usa o model vinculado depois
  da action, porque `RefreshBadgeAsset` devolve `PersonalBadge`.
- **Limiter**: `BadgeForgeServiceProvider` define `badgeforge-refresh`
  (10/min por conta) e `badgeforge-management`; o `response()` do primeiro
  respondia só o formato da web.
- **Analytics**: `OwnerAnalytics::for()` devolve `exportUrl` no fim do array
  e usa `badge_views`; o renderer global de exceções cobre `QueryException`
  apenas em `api/badges/*` e `b/*`.
- **Contrato**: `OpenApiContractTest` compara paths documentados e rotas
  registradas nos prefixos `api/badges/`, `api/v1/badgeforge/` e `b/`, com
  `$ref` sem dangling e nomes exatos de placeholder.
- **Bindings**: rotas usam o macro `whereUlid`; o nome do parâmetro precisa
  bater com o model tipado no controller para o `ignore()` do `unique`.
- **Secrets**: credencial, `url` de endpoint e `url_template` de custom
  provider são `#[Hidden]` e `dontFlash`; nunca podem voltar.
- **Testes ao fim da F8**: 1.413 Pest / 23.490 asserções; Vitest 983 em 76
  arquivos; `lint:check` com 0 erros e ~1.182 avisos históricos.
- **Probe de endpoint**: `EndpointPayloadParser` exige `schemaVersion === 1`
  e `label`/`message`; o JSON público do shields não carrega `schemaVersion`
  (fato que apareceu no smoke).

## Rodadas e decisões

| # | Decisão |
|---|---|
| Q1 | Espelho completo do painel, **sem presets** |
| Q2 | PATCH **espelha a web** (representação + FormRequests), não JSON Merge Patch |
| Q3 | Credencial e refresh **entram**, iguais à tela (write-only, síncrono) |
| Q4 | Endpoints **no Free**, como na web; origem imutável e nunca devolvida |
| Q5 | Custom providers pagos para criar/editar (403), DELETE após downgrade |
| Q6 | Analytics **JSON-only**, sem `exportUrl` (CSV só na web/sessão) |
| Q7 | **Sem GET por ID**; listas paginadas e recurso devolvido por mutação |
| Q8 | Contrato **1.9.0** + ADR-090 + emenda ADR-088, sem UI/i18n |
| Q9 | Abilities e limites da F8 reusados; nenhuma ability nova |
| Q10 | Envelope `ApiError` em todos os erros; dono errado 404 |

## Riscos registrados

- Placeholders da spec precisam bater com os bindings; o logo mantém
  `{logo}` no DELETE e `{badgeCustomLogo}` no PATCH.
- O 429 do limiter de refresh é `HttpResponseException` e não passa pelo
  renderer; o `response()` precisa checar `api/*`.
- O `unique` do PATCH só funciona com o nome exato do parâmetro no controller.
- `badgeforge.paid` roda depois das abilities: Free em custom provider recebe
  403, mas em badge/credencial/refresh recebe 422 (regra na action).
- `QueryException` de analytics precisa de `try/catch` local.
- Nenhuma resposta pode conter credencial, URL de origem ou template.
- O smoke de endpoint depende de um JSON shields v1 público; se a origem não
  responder ou o payload não aderir, registrar o desvio.

## Fora de escopo

Presets na API, GET por ID, CSV por API, API de admin, webhooks/bulk, viewer
embutido, meta-schema, página de status, docs pública/i18n e os pendentes
operacionais. `format:check` continua dívida histórica.
