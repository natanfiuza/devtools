# BadgeForge — Fase 7 (providers customizados, analytics completo e admin)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase7.md`
- Levantamento/plano da skill: `docs/survey/20261104_badgeforge_fase7_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase7_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-7.md`
- ADRs: `docs/adr/ADR-086-badgeforge-analytics.md`,
  `docs/adr/ADR-087-badgeforge-providers-customizados.md` (a ADR-063 foi
  marcada como substituída)

## Objetivo

Fechar o escopo funcional do SPEC-BADGEFORGE (seção 16) com três frentes que
faltavam desde a F1:

1. **providers customizados** — o plano pago cria o próprio provider a partir
   de um JSON público, sem código no servidor;
2. **analytics completo** — visualizações das imagens com dono, privacidade
   desde o desenho, retenção e exportação;
3. **admin** — catálogo de providers, custom providers e volume global.

O desenho tinha três restrições que moldaram tudo: o catálogo global é anônimo
e sem dono, não existe `badge_asset_views` (só a ADR-063 no papel) e nada pode
fazer a imagem virar 500. A fase inteira é aditiva: nenhuma tabela existente
muda de contrato, `parser_class` continua inerte e não há código do usuário
executado no servidor.

Decisões fechadas no grill: mapping declarativo (URL template + JSON Pointer
RFC 6901 + formatter), URL pública opaca, template e conjunto de métricas
imutáveis, probe com `example_path`, reuso do stack SSRF da F5, analytics só
em imagem com dono, duas tabelas (bruto + diário), HMAC com `APP_KEY`, GeoIP
opcional, superfície web + CSV e admin que desabilita mas não exclui.

## Resumo executivo

- **Providers customizados** em `/api/badges/custom/{ulid}/{definition}/{path}.{svg|png}`:
  stateless, anônimo, opaco e registrado **antes** da rota genérica de provider;
  `custom` entrou em `RESERVED_SLUGS`.
- Só plano pago cria/edita (Free 403); limite em `custom_providers.limit`
  (default 10, `null` = ilimitado); excluir continua permitido no downgrade.
- Template imutável, `slug` de métrica imutável e conjunto de métricas fixo na
  criação; o update muda nome/label/pointer/formatter e bumpa
  `metrics_version`, que entra na chave de cache
  (`badgeforge:custom:v1:{ulid}:{metricsVersion}:sha256(url)`).
- O cadastro faz probe com `example_path`: shape/URL insegura → **422**;
  timeout/DNS/5xx/429 são tolerados (salva e a imagem faz fallback).
- O serving reusa `EndpointUrlGuard` + `EndpointTargetResolver` +
  `EndpointHttpClient::fetchBody()` e **qualquer** falha vira STALE/fallback
  200 — nunca 422/500.
- **Analytics** gravando em três controllers (badge pessoal, endpoint e custom)
  em 200 e 304, uma linha por acesso, síncrono e best-effort. Estática e
  provider global não gravam.
- `badge_views` polimórfica (`personal|endpoint|custom`, `definition` só no
  custom) + `badge_view_dailies`; agregação horária e poda diária em
  `task_schedules`.
- Privacidade: `ip_hash`/`user_agent_hash` = HMAC-SHA256 com `APP_KEY`, UA cru
  truncado, `referer_domain` só host, país via GeoLite2 **opcional**.
- Free 30 dias; pago 7/30/90 com série, referrers, países, formatos e taxa de
  cache; CSV sem hash/IP/UA (gate pago). Bruto 90 dias, diário 400.
- **Admin** com `/admin/badgeforge/{providers,custom-providers,analytics}`, atrás
  de `can:access-admin`, auditoria sem URL de origem, desabilitar sem excluir.
- i18n nos cinco idiomas; ADR-086/087; SPEC §§2/6/7/8/10/11/12/14/15/16/17;
  API.md; OPENAPI 1.7.0; glossário; `CLAUDE.md`; `RUNBOOK.md`.
- Gates: Pest **1.371 testes / 22.003 asserções**, Vitest **981 / 76**,
  `types:check` limpo, `lint:check` 0 erros, `build` exit 0.

## Decisão (ADR-087 — providers customizados)

### 1. Declarativo, não código

Um provider é `url_template` + `path_params` + métricas. Cada métrica tem
`slug`, `label`, `pointer` (JSON Pointer RFC 6901) e `formatter`
(`text|number|percent`). O servidor só renderiza o template com os segmentos do
path (`rawurlencode`), busca o JSON e extrai escalares. Não há expressão,
script, header configurável nem credencial na origem.

### 2. URL opaca e stateless

`GET /api/badges/custom/{ulid}/{definition}/{path}.{ext}`. Quem tem o link
renderiza; a gestão é só do dono. O ULID não deriva do template. A rota fica
registrada antes de `{provider}` e `custom` é slug reservado — as duas coisas
têm teste de regressão.

### 3. Imutabilidade

Trocar o template = excluir e recriar. O update recusa `url_template` e slug
(`prohibited`); o conjunto de métricas não muda. `metrics_version` invalida o
cache de um pointer editado sem servir valor antigo.

### 4. Probe no cadastro

`example_path` concreto permite validar o shape antes de salvar: URL insegura,
redirect, JSON inválido, pointer ausente ou não escalar → 422 com
`validation.badge_custom_provider.*` (sem a URL na mensagem). Rede é tolerada:
o cadastro salva e a imagem faz fallback. O probe roda na mesma transação da
criação.

### 5. Origem nunca sai do servidor

`url_template` é `#[Hidden]`, `dontFlash`, e não aparece em props do dono,
builder, docs, CSV, log ou auditoria. Exceções explícitas: o dono recebe o
`pointer` (é ele quem edita as métricas) e o admin vê template/pointer na tela
de custom providers. Desabilitar no admin faz a URL responder 404; o admin não
exclui.

### 6. Serving nunca vira 500

Diferença deliberada em relação ao endpoint da F5 (que responde 422 para URL
guardada considerada insegura): aqui o link é público e a imagem não pode
quebrar. Guard, DNS, fetch, JSON e pointer caem em STALE/fallback 200. O
fallback usa `custom_providers.unavailable_message` e TTL de 60 s.

## Decisão (ADR-086 — analytics)

### 1. Só imagem com dono

Badge pessoal, endpoint JSON e provider custom. Estática e provider global são
anônimas e ficam fora. Conta **200 e 304**, uma linha por acesso, sem dedupe;
únicos = `COUNT(DISTINCT ip_hash)`.

### 2. Duas tabelas

`badge_views` (bruto) e `badge_view_dailies` (agregado). A polimórfica usa
`subject_type = personal|endpoint|custom` e `subject_id` ULID; `definition` só
vem preenchida no custom. Nas dailies as dimensões opcionais usam `''` e
`COALESCE` — NULL quebraria o índice único no MySQL.

### 3. Registro síncrono best-effort

`BadgeViewRecorder` grava na própria requisição dentro de `try/catch(Throwable)`.
Banco fora, migração pendente ou GeoIP ilegível não impedem a imagem. Nunca
loga IP, UA ou conteúdo.

### 4. Privacidade

`ip_hash`/`user_agent_hash` = HMAC-SHA256 do IP/UA com `APP_KEY`; UA cru
truncado em `user_agent_max_length`; `referer_domain` só o host em minúsculas;
país por GeoLite2 local opcional (`maxmind-db/reader`, caminho em
`analytics.geoip_path`). Sem arquivo, corrompido ou IP privado → `null`.
Trocar `APP_KEY` reinicia a contagem de únicos — documentado no RUNBOOK.

### 5. Janelas e retenção

Free: 30 dias, totais e únicos por sujeito + conta. Pago: 7/30/90 com série
diária zero-filled, top referrers, países, formatos, taxa de cache e CSV.
Admin: 7/30/90/400 lendo só as dailies. Bruto 90 dias, diário 400.
`badgeforge:analytics:aggregate` reconstrói 7 dias de hora em hora (`13 * * * *`)
e `...:prune` poda de madrugada (`17 4 * * *`), ambos em `task_schedules`.

## Admin

`routes/admin.php`, grupo `can:access-admin`, prefixo `badgeforge`:

| Rota | Para que |
|---|---|
| `GET /admin/badgeforge/providers` | catálogo com toggles |
| `PATCH /admin/badgeforge/providers/{provider}` | habilita/desabilita provider |
| `PATCH /admin/badgeforge/providers/{provider}/definitions/{definition}` | habilita/desabilita definição |
| `GET /admin/badgeforge/custom-providers` | dono, métricas, template/pointer, toggle |
| `PATCH /admin/badgeforge/custom-providers/{customProvider}` | habilita/desabilita (sem delete) |
| `GET /admin/badgeforge/analytics?window=7\|30\|90\|400` | volume das dailies |

`AdminActionType` ganhou `provider.updated`, `definition.updated` e
`custom_provider.updated`, auditados sem a URL de origem. As páginas
`BadgeProviders.vue`, `BadgeCustomProviders.vue` e `BadgeAnalytics.vue` usam
`AdminTable.vue` ampliado para `id: number|string` (ULIDs).

## Implementação

### Backend — custom

- Migrations `2026_11_04_000003` a `000004`:
  `badge_custom_providers` (ULID, `url_template` 2048, `path_params` JSON,
  `example_path`, `metrics_version`, `enabled`) e
  `badge_custom_provider_definitions` (`slug`, `label`, `pointer`,
  `formatter`, `sort`).
- `app/Support/BadgeForge/Custom/`: `CustomProviderUrlTemplate`,
  `CustomPathParams`, `JsonPointer`, `JsonDocument`, `CustomMetricFormatter`,
  `CustomProviderProbe`, `CustomProviderGuard`, `CustomProviderCacheKey`,
  `CachedCustomMetrics`, `CustomProviderCache`, `CustomProviderMetrics`,
  `CustomMetricsResult`, `CustomBadge`, `CustomBadgeComposer`.
- `app/Actions/BadgeForge/{Create,Update,Delete}BadgeCustomProvider.php`,
  `StoreBadgeCustomProviderRequest` / `UpdateBadgeCustomProviderRequest`,
  `CustomProviderManagementController`.
- `EnsureBadgeForgeHasPaidPlan` (alias `badgeforge.paid`, 403) em create/update.
- `CustomProviderBadgeController` + rota pública `badges.custom` com
  `throttle:badgeforge-custom` (pago 3000/min, Free 300/min, anônimo 60/min por
  IP para ULID inválido/desconhecido).
- `EndpointHttpClient` ganhou `fetchBody()` (corpo/etag/304 pinados) e `fetch()`
  passou a delegar; a pinagem SSRF ficou num lugar só e os testes da F5
  continuam verdes.

### Backend — analytics

- Migrations `000005` a `000007`: `badge_views`, `badge_view_dailies` e o seed
  dos dois agendamentos.
- `app/Support/BadgeForge/Analytics/`: `PrivacyHash`, `RefererDomain`,
  `CountryReader` / `NullCountryReader` / `MaxMindCountryReader`,
  `BadgeViewRecorder`, `AnalyticsSubjectResolver`, `OwnerAnalytics`,
  `AnalyticsCsvStreamer`, `AdminAnalytics`.
- `BadgeForgeAccountController` injeta `analytics` (período inválido cai no
  default, nunca 422); `AnalyticsExportController` streama o CSV com gate pago.
- `BadgeForgeAnalyticsAggregate` (`--days`, default 7) e
  `BadgeForgeAnalyticsPrune` (`--raw-days`, `--daily-days`, `--dry-run`).

### Frontend

- `CustomProvidersPanel.vue`, `CustomProviderDialog.vue`,
  `CustomProviderForm.vue` e `CustomProviderPreview.vue`; modo `custom` em
  `ModeTabs.vue`/`Tool.vue` só quando o plano permite.
- `lib/customProviderUrl.ts` espelha `providerUrl.ts` (params, encoding, query
  cosmética, sem `cacheSeconds`).
- `AnalyticsPanel.vue` + `components/badgeforge/AnalyticsChart.vue` (SVG à
  mão, com `role`/`title`/`aria`, no padrão do gráfico de tokens da API).
- Páginas de admin e `admin.ts`; nav em `AdminLayout.vue`.

### i18n

- `tools.badgeforge.custom_providers.*` e `modes.custom`; `account.php`
  `limit_custom_providers` e `badgeforge.analytics.*`; `admin.nav.*`,
  `admin.badgeforge.*` e `admin.actions.*`; `docs.badgeforge.custom.*`;
  `validation.badge_custom_provider.*` — nos cinco idiomas, com paridade
  verificada pelo `TranslationsParityTest`. Os exemplos de URL usam
  `{'{'}owner{'}'}` para o vue-i18n não interpretar as chaves.

### Documentação

- ADR-086 (analytics) e ADR-087 (providers customizados); ADR-063 marcada como
  substituída.
- `docs/SPEC-BADGEFORGE.md`: §§2, 6, 7, 8, 10, 11, 12, 14, 15, 16 (F7
  entregue) e 17.
- `docs/badgeforge/API.md`: rota custom, cache/SSRF, gestão e CSV.
- `docs/badgeforge/OPENAPI.md`: 1.7.0 e o path
  `/api/badges/custom/{ulid}/{definition}/{path}.{ext}`.
- `docs/glossario.md`: provider customizado, template de URL, métrica
  customizada, JSON Pointer, formatter, visualização, visitante único,
  agregado diário, taxa de cache, GeoIP opcional, referer domain e hash de
  privacidade.
- `CLAUDE.md` (ADRs até 087, fase F7 entregue, bullets) e `docs/RUNBOOK.md`
  (GeoLite2, comandos, worker/scheduler e diagnóstico).

## Testes

### Pest

- Unit: `CustomProviderUrlTemplateTest`, `CustomPathParamsTest`,
  `JsonPointerTest`, `CustomMetricFormatterTest`, `CustomProviderCacheKeyTest`,
  `PrivacyHashTest`, `RefererDomainTest`, `MaxMindCountryReaderTest` (8).
- Feature custom: `CustomProviderManagementTest`, `CustomProviderBadgeTest`,
  `CustomProviderRouteTest`, `CustomProviderRateLimitTest`.
- Feature analytics: `BadgeAnalyticsRecordingTest`, `OwnerAnalyticsTest`,
  `AnalyticsExportTest`, `AnalyticsDashboardTest`, `AnalyticsAggregationTest`,
  `AnalyticsPruneTest`, `AnalyticsScheduleSeedTest`.
- Feature admin: `AdminBadgeForgeTest`.
- Atualizados: `BadgeForgePageTest`, `BadgeForgeAccountPageTest`,
  `BadgeForgeAccountPropsTest`, `BadgeForgeDocsPageTest` e
  `TranslationsParityTest`.

### Vitest

- Novos: `badgeforge-custom.test.ts`, `badgeforge-analytics.test.ts`,
  `admin-badgeforge.test.ts`.
- Atualizados: `badgeforge-account.test.ts`, `docs-badgeforge.test.ts` e os
  testes de componente/painel que compartilham o fixture.
- `tests/Fixtures/badgeforge/tool-props.json` regenerada do payload real.

### Gates

| Comando | Resultado |
|---|---|
| `composer test` | Pint ok, PHPStan 0 erros, Pest 1.371 / 22.003 |
| `npm run test` | 981 testes / 76 arquivos |
| `npm run types:check` | limpo |
| `npm run lint:check` | 0 erros, 1.182 avisos pré-existentes |
| `npm run build` | exit 0 |

## Smoke com rede real (2026-11-04)

Servidor local (`php artisan serve`), provider custom apontando para
`https://api.github.com/repos/{owner}/{repo}` com a métrica `stars` em
`/stargazers_count`:

```text
GET custom/{ulid}/stars/natanfiuza/devtools.svg      → 200 MISS | stars: 0
GET (segunda)                                        → 200 HIT
GET If-None-Match                                    → 304
GET .png                                             → 200 image/png (60×20)
GET .svg?download=1                                  → attachment stars-0.svg
definição inexistente / 1 segmento / .gif / ..%2F    → 404
provider desabilitado no banco                       → 404 (reabilitado → 200)
origem api.github.invalid                            → 200 fallback | unavailable
```

- `badge_views`: só o provider custom gravou (estática e provider global não);
  `definition=stars`, `format` svg/png e `cache_hit` coerente.
- `badgeforge:analytics:aggregate --days=1` → 2 linhas diárias;
  `...:prune --dry-run` → nada a remover.
- `storage/logs/laravel.log`: sem a URL de origem e sem o ULID do provider.
- Migrações: `migrate:rollback --step=5` + `migrate --force` → 2 agendamentos.
- Rotas: `route:list --path=api/badges` confirma `custom` antes de `{provider}`.
- Dados do smoke apagados ao final; `git status` revisado; **nenhum commit/push**.

## Desvios e observações

- **`CustomProviderValidationTest` não existe como arquivo**: os casos de
  validação (URL insegura, IP privado, redirect, JSON inválido, pointer
  ausente/não escalar, rede tolerada) ficaram em `CustomProviderManagementTest`.
  A cobertura é a mesma; só a divisão de arquivos não aconteceu.
- **Lint**: a primeira passada acusou um `selectedDefinition` calculado e nunca
  usado em `CustomProviderForm.vue`; o computed e o import foram removidos.
- **Nome do composer**: o plano citava um `CustomProviderComposer`; a
  implementação usa `CustomBadgeComposer` (composição da badge a partir de
  `CustomMetricsResult`), e `CustomProviderMetrics` guarda a máquina de estados
  MISS/HIT/STALE/fallback.
- **Únicos no admin**: a tela soma `unique_visitors` apenas dentro de cada dia,
  nunca entre dias — um visitante que volta em dois dias conta duas vezes, por
  desenho.

## Arquivos

Novos:

- Migrations `2026_11_04_000003` a `000007`.
- `app/Actions/BadgeForge/{Create,Update,Delete}BadgeCustomProvider.php`
- `app/Console/Commands/BadgeForgeAnalytics{Aggregate,Prune}.php`
- `app/Enums/{BadgeViewSubject,CustomMetricFormatter}.php`
- `app/Http/Controllers/Admin/Badge{Provider,CustomProvider,Analytics}AdminController.php`
- `app/Http/Controllers/BadgeForge/{CustomProviderBadge,CustomProviderManagement,AnalyticsExport}Controller.php`
- `app/Http/Middleware/EnsureBadgeForgeHasPaidPlan.php`
- `app/Http/Requests/BadgeForge/{Store,Update}BadgeCustomProviderRequest.php`
- `app/Models/{BadgeCustomProvider,BadgeCustomProviderDefinition,BadgeView,BadgeViewDaily}.php`
- `app/Support/BadgeForge/Custom/*` (15 classes)
- `app/Support/BadgeForge/Analytics/*` (10 classes)
- `app/Support/BadgeForge/Endpoint/EndpointBodyResult.php`
- `resources/js/pages/admin/{BadgeProviders,BadgeCustomProviders,BadgeAnalytics}.vue`
- `resources/js/tools/badgeforge/{CustomProvidersPanel,CustomProviderDialog,CustomProviderForm,CustomProviderPreview,AnalyticsPanel}.vue`
- `resources/js/tools/badgeforge/lib/customProviderUrl.ts`
- `resources/js/components/badgeforge/AnalyticsChart.vue`
- `docs/adr/ADR-086-badgeforge-analytics.md`,
  `docs/adr/ADR-087-badgeforge-providers-customizados.md`
- `docs/acceptance/BADGEFORGE-FASE-7.md`

Modificados:

- `routes/{api,badgeforge,admin}.php`, `bootstrap/app.php`,
  `app/Providers/BadgeForgeServiceProvider.php`, `app/Enums/AdminActionType.php`,
  `app/Models/User.php`, `app/Support/BadgeForge/BadgeForgeToolProps.php`,
  `app/Support/BadgeForge/Providers/ProviderCatalog.php`,
  `app/Support/BadgeForge/Endpoint/EndpointHttpClient.php`,
  `app/Http/Controllers/BadgeForge/{Personal,Endpoint,BadgeForgeAccount,BadgeForgeDocs}Controller.php`
- `config/badgeforge.php`, `composer.json`
- `lang/{en,es,fr,pt_BR,pt_PT}/{tools,account,docs,validation,admin}.php`
- `resources/js/{pages/BadgeForgeDocs.vue,pages/dashboard/BadgeForge.vue,tools/badgeforge/*,types/*,layouts/admin/AdminLayout.vue,components/admin/AdminTable.vue}`
- `tests/Feature/BadgeForge/*`, `tests/Fixtures/badgeforge/tool-props.json`,
  `resources/js/__tests__/*`
- `docs/SPEC-BADGEFORGE.md`, `docs/badgeforge/{API,OPENAPI}.md`,
  `docs/glossario.md`, `docs/RUNBOOK.md`, `CLAUDE.md`,
  `docs/adr/ADR-063-badgeforge-analytics-duas-tabelas.md`

## Próximos passos

- Revisão humana; commit e PR pelo fluxo do usuário (`gitpr`).
- F8: OpenAPI 3.1 completo e hardening.
- Fora do escopo desta fase: badge pessoal usando provider custom,
  métricas adicionáveis depois da criação, credenciais na origem custom e API
  JSON de analytics.
