# BadgeForge — Fase 6 (providers Codecov/Coveralls/Laravel/PHP/Python)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase6.md`
- Levantamento/plano da skill: `docs/survey/20261104_badgeforge_fase6_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase6_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-6.md`
- ADR: `docs/adr/ADR-085-badgeforge-providers-de-cobertura-e-runtime.md`

## Objetivo

Entregar a F6 do SPEC-BADGEFORGE: cinco providers novos no catálogo —
**Codecov** e **Coveralls** (cobertura) e **Laravel**, **PHP** e **Python**
(versão de runtime) — mantendo o contrato da camada de providers da F4/F5:
catálogo em banco por migração aditiva, comportamento em código
(`client_key`/`extractor_key`), `parser_class` inerte, falha do provider
convertida em stale/fallback 200 e nenhuma credencial em URL, log, props ou
cache.

Duas lacunas reais da F4 precisaram ser fechadas:

1. o formatter não conhecia **percentual de cobertura**;
2. uma definição **sem coordenadas** (versão de runtime) não era representável
   — o parser exigia params e o builder TS só montava segmentos a partir deles.

Decisões fechadas no grill: versão de runtime (não downloads de linguagem nem
EOL); cobertura só da branch padrão; path literal `latest`; percentual
inteiro; fontes oficiais + `endoflife.date` para Python; Laravel expõe versão
**e** downloads; categorias `coverage`/`runtime`; os cinco providers são
públicos.

## Resumo executivo

- Nove providers e **quinze definições** no catálogo (9 da F4 + 6 da F6):
  `codecov/coverage`, `coveralls/coverage`, `laravel/{v,dt}`, `php/v` e
  `python/v`.
- Cobertura é o **percentual inteiro** da branch padrão: `66.89 → 67%`,
  `94.148 → 94%`; valor fora de 0–100 ou shape inválido vira fallback 200.
- Versão de runtime usa o **path literal `latest`**
  (`/api/badges/php/v/latest.svg`); qualquer outro valor responde 404.
- `laravel/v/latest` e `laravel/dt/latest` compartilham **uma chamada e uma
  entrada de cache**, porque o client projeta `version` e `downloads` juntos.
- `ProviderParam::listFrom([])` passou a devolver lista vazia; parser PHP,
  `CreateBadgeAsset` e builder TS aceitam a definição sem params e validam o
  literal, sem gerar 404 inesperado nem snippet quebrado.
- i18n nos cinco idiomas (seis métricas novas, cinco descrições, meta e
  subtítulo). Nenhum texto visível hardcoded.
- Gates finais: Pint/PHPStan ok, Pest **1.226 testes / 20.522 asserções**,
  Vitest **952 testes / 73 arquivos**, `types:check` limpo, `lint:check` 0
  erros, `build` exit 0.
- Smoke real confirmou `67%`, `94%`, `v13.40.0`, `612M`, `8.5.12` e `3.15.2`,
  o HIT compartilhado do Laravel, os 404 de path literal e PNG/download.

## Decisão (ADR-085)

### 1. Cinco providers, seis definições, todos públicos

| Provider | Definição | Fonte | Métrica |
|---|---|---|---|
| Codecov | `coverage` | `api.codecov.io/api/v2/github/{owner}/repos/{repo}/` | `totals.coverage` |
| Coveralls | `coverage` | `coveralls.io/github/{owner}/{repo}.json` | `covered_percent` |
| Laravel | `v`, `dt` | `packagist.org/packages/laravel/framework/stats.json` | `versions[]` / `downloads.total` |
| PHP | `v` | `php.net/releases/index.php?json&max=1` | primeiro release, `version` |
| Python | `v` | `endoflife.date/api/python.json` | primeiro item, `latest` |

`requires_auth=false`, `auth_type=null` e `supports_private=false` em todos.
Não há PAT, tutorial de credencial nem badge pessoal privada para eles.

### 2. Cobertura formatada como inteiro

`ProviderMetricFormatter::coverage()` aceita int/float/string numérica, exige
`0 ≤ valor ≤ 100` e devolve `((int) round($valor)).'%'`. Fora do intervalo,
NaN/INF ou tipo inválido → `null`, que o pipeline já converte em fallback 200.
Os clients `CodecovProviderClient` e `CoverallsProviderClient` exigem o shape
do serviço e lançam `INVALID_RESPONSE` quando o campo não é numérico.

### 3. Path literal e zero params

- `ProviderParam::listFrom([])`: lista vazia agora é `[]` (antes `null`),
  marcando definição sem coordenadas. JSON que não é lista continua `null`.
- `ProviderPathParser::parseLiteral()`: aceita somente
  `path === trim(path_pattern)`, com literal que case
  `\A[A-Za-z0-9._-]+\z`; recusa vazio, `{`, barra, dot segment e tamanho
  acima do limite.
- `CreateBadgeAsset`: para definição sem params, parseia o próprio
  `path_pattern`; params extras continuam `params_invalid`.
- `providerUrl.ts`: com `params.length === 0`, valida `pathPattern` como
  literal seguro e o empurra em `segments`; literal inválido devolve `''`.
- `ProviderUrlBuilder` não precisou mudar: template sem placeholders já
  resolvia; ganhou teste de regressão.

### 4. Laravel projeta as duas métricas

`LaravelProviderClient::project()` devolve **sempre**
`['version' => …, 'downloads' => …]`. `v` e `dt` têm a mesma URL resolvida e o
mesmo provider slug, portanto a mesma chave de cache; como `ProviderMetrics`
lê `$payload->metrics[$definition->extractor_key]`, projetar só a métrica
pedida faria `laravel/dt` cair em “unavailable” num HIT. A versão é a primeira
entrada de `versions[]` que não termina em `-dev`; sem entrada estável, só a
versão fica nula.

### 5. PHP lê o primeiro release, nunca a chave numérica

`releases/index.php?json&max=1` devolve um objeto cuja chave do major (`"8"`)
vira **int** no `json_decode(..., true)`. O client usa `reset($data)`, como o
client do Python, e lê `version` (`8.5.12`).

### 6. Migração aditiva

`2026_11_04_000002_seed_badgeforge_f6_providers.php`: `insertOrIgnore` para os
cinco providers e seis definições, `parser_class=null`,
`required_params='[]'` nos runtimes, `response_type='json'`, `down()` remove
só os slugs da F6. Não edita a migração da F4 e não sobrescreve `enabled` ou
`cache_ttl` ajustados pelo admin.

## Implementação

### Backend

- `app/Support/BadgeForge/Providers/ProviderParam.php` — lista vazia → `[]`.
- `app/Support/BadgeForge/Providers/ProviderPathParser.php` — branch
  `parseLiteral()`.
- `app/Actions/BadgeForge/CreateBadgeAsset.php` — parseia o literal para
  definição sem params.
- `app/Support/BadgeForge/Providers/Clients/AbstractProviderClient.php` —
  helper `number()` (int/float/string numérica finita ≥ 0, senão
  `INVALID_RESPONSE`).
- `app/Support/BadgeForge/Providers/Clients/{Codecov,Coveralls,Laravel,Php,Python}ProviderClient.php`
  — novos clients.
- `app/Support/BadgeForge/Providers/ProviderClientRegistry.php` — registra os
  cinco.
- `app/Support/BadgeForge/Providers/ProviderMetricFormatter.php` — branch e
  método `coverage()`.
- `database/migrations/2026_11_04_000002_seed_badgeforge_f6_providers.php` —
  catálogo da fase.

### Frontend

- `resources/js/tools/badgeforge/lib/providerUrl.ts` — segmento literal para
  definição sem params, com validação idêntica à do servidor.
- Nenhuma mudança de layout: o formulário já omitia inputs quando
  `definition.params` é vazio; a preview passou a montar a URL correta.

### i18n

- `tools.php` (5 idiomas): `codecov_coverage`, `coveralls_coverage`,
  `laravel_v`, `laravel_dt`, `php_v`, `python_v`.
- `docs.php` (5 idiomas): descrições de codecov/coveralls/laravel/php/python,
  `meta.description` e subtítulo do hero atualizados para citar cobertura e
  versões de runtime.

### Documentação

- ADR-085 (decisão completa).
- `docs/SPEC-BADGEFORGE.md`: §§6, 8, 10, 14, 16 e 17.
- `docs/badgeforge/API.md`: lista de definições, exemplos, cobertura e cache
  compartilhado do Laravel.
- `docs/badgeforge/OPENAPI.md`: `info.version 1.6.0`, enums de provider e
  definition, descrição do path literal.
- `docs/glossario.md`: provider de cobertura, provider de runtime, versão de
  runtime, percentual de cobertura e path literal; entradas de badge dinâmica,
  definição de provider e cache de provider ajustadas.
- `CLAUDE.md`: ADRs até 085, fase atual F6 entregue e bullets da fase.

## Testes

### Pest

- `ProviderPathParserTest` / `ProviderUrlBuilderTest`: literal aceito/recusado
  e template sem placeholders (14 testes).
- `ProviderClientTest` / `ProviderMetricFormatterTest`: projeções dos cinco
  clients, shapes inválidos, `supportsPrivate=false`, cobertura e formatação
  (28 testes).
- `ProviderSeederTest`, `ProviderCatalogTest`, `ProviderBadgeEndpointTest`:
  catálogo 9/15, categorias, ordem, 15 casos de endpoint, HIT compartilhado do
  Laravel, `Http::assertSentCount(11)` e os 404 de literal (25 testes).
- `PersonalBadgeManagementTest`: badge pessoal `php/v` e `laravel/dt` com
  `params: []`, storage `[[],[]]`, `Http::assertNothingSent()` e recusa de
  param extra.
- `BadgeForgeDocsPageTest`: 9 providers / 15 métricas.
- `TranslationsParityTest`: 5 testes / 11.338 asserções com as chaves novas.

### Vitest

- `badgeforge-provider.test.ts`: 15 URLs (incluindo literais e os novos
  `{owner}/{repo}`), literal inválido no catálogo, validação de params.
- `docs-badgeforge.test.ts`: 9 articles / 15 métricas e o caminho
  `/api/badges/php/v/latest.svg`.
- `tests/Fixtures/badgeforge/tool-props.json` regenerada do payload real.

### Gates

| Comando | Resultado |
|---|---|
| `composer test` | Pint ok, PHPStan 0 erros, Pest 1.226 / 20.522 |
| `npm run test` | 952 testes / 73 arquivos |
| `npm run types:check` | limpo |
| `npm run lint:check` | 0 erros, 1.180 avisos pré-existentes |
| `npm run build` | exit 0 |

## Smoke com rede real (2026-11-04)

Servidor local (`php artisan serve`, sem mocks), migração reaplicada antes:

```text
codecov/coverage/psf/requests      → 200 MISS→HIT | coverage: 67%
coveralls/coverage/twbs/bootstrap  → 200 MISS     | coverage: 94%
laravel/v/latest                   → 200 MISS     | laravel: v13.40.0
laravel/dt/latest                  → 200 HIT      | downloads: 612M
php/v/latest                       → 200 MISS     | php: 8.5.12
python/v/latest                    → 200 MISS     | python: 3.15.2
```

- `php/v/8.5.svg`, `php/v/Latest.svg` e `laravel/v/latest/extra.svg` → **404**
  `{"error":{"code":"not_found",…}}`.
- `python/v/latest.png` → **200** `image/png` com `X-Badge-Cache: HIT`.
- `coveralls/coverage/twbs/bootstrap.svg?download=1` → **200** com
  `Content-Disposition: attachment; filename="coverage-94.svg"` e variante
  RFC 5987.
- Conteúdo do cache inspecionado no Tinker:
  - Codecov: `{"coverage": 66.89}` (só a métrica projetada);
  - Laravel: `{"version":"v13.40.0","downloads":612249942}` (uma entrada para
    as duas definições).
- `storage/logs/laravel.log`: nenhuma linha `local.` nova; nenhuma URL de
  provider, corpo cru ou credencial registrada.
- Migração: `migrate:rollback --step=1` + `migrate --force` → contagem 9/15.
- `git status` revisado; **nenhum commit/push** executado.

## Desvios e observações

- **PHPStan no client Python**: o shape real é uma lista, mas o contrato do
  método é `array<string, mixed>`; `$data[0]` disparava
  `nullCoalesce.offset` e tornava o `is_array` inalcançável. Trocado por
  `reset($data)`, igual ao client do PHP, sem mudança de comportamento.
- **Downloads do Laravel** mudaram entre o levantamento e o smoke
  (611.976.413 → 612.249.942); a imagem arredonda para `612M`. O aceite
  registra o valor observado na data.
- **Subtítulo/meta da página pública** também citavam apenas os quatro
  providers da F4; foram atualizados junto com o previsto `meta.description`.
- Nenhuma rota nova: os providers entram pelo catálogo e a rota
  `api/badges/{provider}/{definition}/{path}` já os serve.

## Arquivos

Novos:

- `database/migrations/2026_11_04_000002_seed_badgeforge_f6_providers.php`
- `app/Support/BadgeForge/Providers/Clients/{Codecov,Coveralls,Laravel,Php,Python}ProviderClient.php`
- `docs/adr/ADR-085-badgeforge-providers-de-cobertura-e-runtime.md`
- `docs/acceptance/BADGEFORGE-FASE-6.md`
- `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase6_plansfacts.md`
- `docs/survey/20261104_badgeforge_fase6_surveyfacts.md`
- `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase6.md`

Modificados:

- `app/Actions/BadgeForge/CreateBadgeAsset.php`
- `app/Support/BadgeForge/Providers/{ProviderParam,ProviderPathParser,ProviderMetricFormatter,ProviderClientRegistry}.php`
- `app/Support/BadgeForge/Providers/Clients/AbstractProviderClient.php`
- `resources/js/tools/badgeforge/lib/providerUrl.ts`
- `resources/js/__tests__/{badgeforge-provider,docs-badgeforge}.test.ts`
- `tests/Unit/BadgeForge/Providers/{ProviderPathParser,ProviderUrlBuilder,ProviderClient,ProviderMetricFormatter}Test.php`
- `tests/Feature/BadgeForge/Providers/{ProviderSeeder,ProviderCatalog,ProviderBadgeEndpoint}Test.php`
- `tests/Feature/BadgeForge/Personal/PersonalBadgeManagementTest.php`
- `tests/Feature/BadgeForge/BadgeForgeDocsPageTest.php`
- `tests/Fixtures/badgeforge/tool-props.json`
- `lang/{en,es,fr,pt_BR,pt_PT}/{tools,docs}.php`
- `docs/SPEC-BADGEFORGE.md`, `docs/badgeforge/{API,OPENAPI}.md`,
  `docs/glossario.md`, `CLAUDE.md`

## Próximos passos

- Revisão humana; commit e PR pelo fluxo do usuário (`gitpr`).
- F7: providers customizados, analytics e painel admin.
- F8: OpenAPI 3.1 completo e hardening.
