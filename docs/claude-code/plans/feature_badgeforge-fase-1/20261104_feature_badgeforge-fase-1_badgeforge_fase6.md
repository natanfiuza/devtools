# BadgeForge — Fase 6: providers Codecov/Coveralls/Laravel/PHP/Python

## Contexto

F1–F5 estão entregues (estática, PNG/fontes, logos/presets/contas, providers
GitHub/npm/Packagist/Docker + badges pessoais + PAT + docs públicas, endpoint
JSON + SSRF + rate limit). A F6 do SPEC (`docs/SPEC-BADGEFORGE.md` §16) pede
mais cinco providers: **Codecov, Coveralls, Laravel, PHP e Python**.

O catálogo é dado (`badge_providers` / `badge_provider_definitions`), mas os
dois novos tipos de métrica expõem duas lacunas reais na arquitetura da F4:

1. **Cobertura** precisa de formatação própria (percentual inteiro), hoje o
   formatter só conhece `license`, `version` e número com k/M/B.
2. **Versão de runtime** não tem coordenadas por item: a URL é uma constante
   (`latest`). O parser atual exige `required_params` não vazio e o builder TS
   só monta segmentos a partir de params — ou seja, uma definição sem params
   não é representável hoje.

A fase corrige as duas lacunas mantendo o contrato existente: `client_key` /
`extractor_key` resolvem o comportamento em código (nunca `parser_class`), o
catálogo novo entra por migração com `insertOrIgnore`, nenhum provider novo
aceita item privado e falha continua virando stale/fallback 200, nunca 500.

## Decisões fechadas (grill)

1. **Laravel/PHP/Python medem a versão de runtime** (não downloads de
   linguagem nem EOL).
2. **Codecov e Coveralls usam só a branch padrão** — a URL pública não tem
   `?branch=`; o path é `{owner}/{repo}`.
3. **Definições de versão única usam path literal `latest`**:
   `/api/badges/php/v/latest.svg`.
4. **Cobertura é percentual inteiro**: `66.89 → 67%`, `94.148 → 94%`.
5. **Fontes**: Codecov API v2, Coveralls `.json`, Packagist `stats.json`
   (Laravel), `php.net/releases/index.php?json` (PHP) e
   `endoflife.date/api/python.json` (Python — o catálogo do python.org não
   expõe um `latest` limpo).
6. **Laravel expõe duas métricas**: versão (`v13.40.0`) e downloads (`611M`),
   ambas lendo o mesmo `stats.json` (uma chamada, uma entrada de cache).
7. **Categorias novas**: `coverage` (Codecov/Coveralls) e `runtime`
   (Laravel/PHP/Python).
8. **Todos os cinco providers são públicos**: `requires_auth=false`,
   `auth_type=null`, `supports_private=false` em todas as definições.

## Categoria e catálogo

Migração aditiva nova
`database/migrations/2026_11_04_000002_seed_badge_providers_f6.php`
(não editar a migração da F4). Usa `insertOrIgnore`, `parser_class=null`,
`response_type='json'`, `optional_params=null`, `cache_ttl=null`,
`rate_limit_per_minute=null`, `enabled=true`; `down()` remove só os slugs
desta migração.

### Providers (ids após `docker`)

| slug | name | category | base_url | documentation_url |
|---|---|---|---|---|
| `codecov` | Codecov | coverage | https://api.codecov.io | https://docs.codecov.com/reference |
| `coveralls` | Coveralls | coverage | https://coveralls.io | https://docs.coveralls.io/api-reference |
| `laravel` | Laravel | runtime | https://packagist.org | https://laravel.com/docs/releases |
| `php` | PHP | runtime | https://www.php.net | https://www.php.net/releases/ |
| `python` | Python | runtime | https://endoflife.date | https://endoflife.date/python |

### Definições (append; 15 no total com as 9 da F4)

| provider/slug | label | client_key | extractor_key | path_pattern | example_path | url_template | sort |
|---|---|---|---|---|---|---|---|
| codecov/`coverage` | coverage | `codecov` | `coverage` | `{owner}/{repo}` | `psf/requests` | `https://api.codecov.io/api/v2/github/{owner}/repos/{repo}/` | 0 |
| coveralls/`coverage` | coverage | `coveralls` | `coverage` | `{owner}/{repo}` | `twbs/bootstrap` | `https://coveralls.io/github/{owner}/{repo}.json` | 0 |
| laravel/`v` | laravel | `laravel` | `version` | `latest` | `latest` | `https://packagist.org/packages/laravel/framework/stats.json` | 0 |
| laravel/`dt` | downloads | `laravel` | `downloads` | `latest` | `latest` | idem | 1 |
| php/`v` | php | `php` | `version` | `latest` | `latest` | `https://www.php.net/releases/index.php?json&max=1` | 0 |
| python/`v` | python | `python` | `version` | `latest` | `latest` | `https://endoflife.date/api/python.json` | 0 |

`required_params` de Laravel/PHP/Python é `[]` (JSON `"[]"`). Codecov/
Coveralls reutilizam os patterns `owner`/`repo` do GitHub.

## Mudanças de código

### 1. Zero-param / path literal

- `app/Support/BadgeForge/Providers/ProviderParam.php`: `listFrom()` passa a
  devolver `[]` para array vazio (hoje devolve `null`); array inválido/JSON que
  não é lista continua `null`. Atualizar o docblock.
- `app/Support/BadgeForge/Providers/ProviderPathParser.php`: quando
  `$params === []`, aceitar somente `$path === trim($definition->path_pattern)`
  e rejeitar literal vazio ou com `{`. Manter as checagens de controle/
  traversal/tamanho antes.
- `app/Actions/BadgeForge/CreateBadgeAsset.php` (`params()`): para definição
  sem params, validar o path literal via
  `(string) $pair->definition->path_pattern` em vez de `implode('/', [])`.
  Params extras continuam `validation.badge_asset.params_invalid`.
- `app/Support/BadgeForge/Providers/ProviderUrlBuilder.php`: nenhuma mudança
  (o template sem placeholders já resolve), mas ganha teste.
- `resources/js/tools/badgeforge/lib/providerUrl.ts`: quando
  `definition.params.length === 0`, validar `definition.pathPattern` como
  literal seguro (`^[A-Za-z0-9._-]+$`) e empurrá-lo em `segments`; literal
  inválido devolve `''`. `exampleParams`/`invalidProviderParam` já funcionam
  com zero params.

### 2. Clients (`app/Support/BadgeForge/Providers/Clients/`)

- `AbstractProviderClient`: novo helper `protected function number(array $data,
  string $key): float` (int/float/string numérica finita ≥ 0; senão
  `INVALID_RESPONSE`).
- `CodecovProviderClient` (`codecov`): exige `totals` array e projeta
  `['coverage' => number(totals, 'coverage')]`.
- `CoverallsProviderClient` (`coveralls`): projeta
  `['coverage' => number(data, 'covered_percent')]`.
- `LaravelProviderClient` (`laravel`): projeta **as duas métricas** de
  `stats.json` — `downloads` = `downloads.total` (via `integer`) e `version` =
  primeira entrada de `versions[]` que não termina em `-dev` (null se não
  houver). Assim `v` e `dt` compartilham a entrada de cache (mesma URL e mesmo
  provider). Não estender `PackagistProviderClient`.
- `PhpProviderClient` (`php`): `reset($data)` → primeiro release (a chave
  numérica `"8"` vira int no decode) → `['version' => text(release, 'version')]`.
- `PythonProviderClient` (`python`): `$data[0]` → `['version' =>
  text(item, 'latest')]`.
- Todos: `supportsPrivate(): false`.
- `ProviderClientRegistry`: injetar e registrar os cinco clients novos.

### 3. Formatter

`ProviderMetricFormatter::format()` ganha o branch `'coverage' =>
$this->coverage($value)` e o método `coverage()`: aceita int/float/string
numérica, exige 0 ≤ valor ≤ 100 e devolve `((int) round($valor)).'%'`; fora do
intervalo/NaN → `null`. `version` e `downloads` continuam usando os branches
existentes.

### 4. Frontend

`providerUrl.ts` (acima). Nenhuma mudança de layout: o formulário já não
renderiza inputs quando `definition.params` é vazio e a preview usa a URL
montada. O painel de badge pessoal passa a permitir criar badge de
Laravel/PHP/Python com `params: {}`.

## i18n (5 idiomas)

`lang/{en,es,fr,pt_BR,pt_PT}/tools.php` — `provider.metrics` ganha:
`codecov_coverage`, `coveralls_coverage`, `laravel_v`, `laravel_dt`, `php_v`,
`python_v`.

`lang/{en,es,fr,pt_BR,pt_PT}/docs.php`:

- `providers.descriptions` ganha `codecov`, `coveralls`, `laravel`, `php`,
  `python`;
- `meta.description` deixa de citar só GitHub/npm/Packagist/Docker e passa a
  mencionar cobertura e versões de runtime.

Sem chaves novas de params (owner/repo já existem). Nenhum texto visível
hardcoded.

## Documentação

- **ADR-085 — BadgeForge F6: providers de cobertura e runtime**: fontes
  escolhidas, branch padrão, versão de runtime, duas métricas do Laravel,
  percentual inteiro, categorias e o contrato de path literal sem params.
- `docs/SPEC-BADGEFORGE.md` §6 (nove providers / quinze definições, path
  literal), §10 (entrada compartilhada do Laravel), §16 (F6 entregue ao final)
  e critérios §17 se necessário.
- `docs/badgeforge/API.md`: lista de definições/exemplos, zero-param e nota de
  cache compartilhado do Laravel.
- `docs/badgeforge/OPENAPI.md`: `info.version: 1.6.0`, enums de provider e
  definition, descrição do `path` com literal e observação de cobertura.
- `docs/glossario.md`: ajustar “Badge dinâmica” e “Definição de provider”;
  novos termos: Provider de cobertura, Provider de runtime, Versão de runtime,
  Percentual de cobertura, Path literal.
- `CLAUDE.md`: faixa de ADRs até 085 e Fase atual F6 (entregue).

## Testes

### Pest

- `ProviderPathParserTest`: definição sem params aceita `latest` e devolve
  `[]`; recusa `latest/`, `Latest`, `latest/x`, literal com `{`.
- `ProviderUrlBuilderTest`: template sem placeholders devolve a URL; params
  vazios não quebram.
- `ProviderMetricFormatterTest`: `66.89→67%`, `94.148→94%`, `0→0%`,
  `100→100%`, `-1`/`100.4`/`INF`/`nope` → `null`, dispatch do `coverage`.
- `ProviderClientTest`: projeções de codecov, coveralls, laravel (downloads +
  versão estável, pulando `13.x-dev`), php e python; shapes inválidos;
  `supportsPrivate=false`.
- `ProviderSeederTest`: 9 providers / 15 definições, ordem, `required_params`
  `[]` nos três runtimes, idempotência; lista privada continua
  `['stars','forks','issues','license','v','pulls']`.
- `ProviderCatalogTest`: 9 providers / 15 definições na ordem, params `[]`
  publicado.
- `ProviderBadgeEndpointTest`: 6 casos novos (`codecov/coverage/psf/requests`
  → 67%, `coveralls/coverage/twbs/bootstrap` → 94%, `laravel/v/latest` →
  v13.40.0, `laravel/dt/latest` → 611M, `php/v/latest` → 8.5.12,
  `python/v/latest` → 3.15.2); `Http::assertSentCount(11)`; `laravel` v/dt
  compartilham uma chamada (MISS → HIT); `php/v/8.5` → 404. Ajustar o fake do
  Packagist para separar o `stats.json` do Laravel (usar `symfony/console` no
  caso `packagist/dt` ou um stub específico ordenado antes do genérico).
- Personal: caso novo criando badge pessoal `php/v` com `params: []` (e
  `laravel/dt`) para travar a mudança do `CreateBadgeAsset`.
- `BadgeForgeDocsPageTest`: 9 providers / 15 definições.
- `TranslationsParityTest` continua verde com as chaves novas.

### Vitest

- `badgeforge-provider.test.ts`: 15 casos de URL, incluindo os literais
  (`php/v/latest.svg`, `laravel/v/latest.svg`, `laravel/dt/latest.svg`,
  `python/v/latest.svg`) e os `{owner}/{repo}` novos.
- `docs-badgeforge.test.ts`: 9 articles / 15 métricas e caminho
  `/api/badges/php/v/latest.svg`.
- `tests/Fixtures/badgeforge/tool-props.json`: regenerar a partir de
  `BadgeForgeToolProps::for()` (mesma ordem de chaves, JSON pretty) e conferir
  igualdade em `BadgeForgePageTest`/`BadgeForgeDocsPageTest`.

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Migração F6 + zero-param (`ProviderParam`, parser, `CreateBadgeAsset`) + unit tests |
| E2 | `number()` no abstract, 5 clients, registry, formatter coverage + unit tests |
| E3 | Feature de seeder/catálogo/endpoint + personal zero-param + fixture |
| E4 | `providerUrl.ts` literal + Vitest + docs page test |
| E5 | i18n nos 5 idiomas (métricas + descrições + meta) |
| E6 | ADR-085, SPEC, API.md, OPENAPI 1.6.0, glossário, CLAUDE.md |
| E7 | Aceite, relatório, gates completos, smoke real e parada para revisão |

Gate por etapa: `composer test`; quando tocar TS, `npm run test` e
`npm run types:check`. No E7 rodar `composer test`, `npm run test`,
`npm run types:check`, `npm run lint:check` e `npm run build`.

## Verificação e smoke (rede real)

1. `php artisan migrate` e `php artisan migrate:rollback --step=1` na migração
   nova; `php artisan route:list --path=api/badges`.
2. Shell público (`php artisan serve`, sem mocks), conferindo `X-Badge-Cache`
   e `Content-Type`:
   - `codecov/coverage/psf/requests.svg` → `67%`, MISS e depois HIT;
   - `coveralls/coverage/twbs/bootstrap.svg` → `94%`;
   - `laravel/v/latest.svg` → `v13.40.0` (valor corrente); em seguida
     `laravel/dt/latest.svg` → `611M` com HIT (mesma URL/cache);
   - `php/v/latest.svg` → `8.5.12`; `python/v/latest.svg` → `3.15.2`;
   - `php/v/8.5.svg` → 404 `not_found`;
   - PNG e `download=1` em uma das novas.
3. `storage/logs/laravel.log` sem corpo cru/URL de origem; cache só com
   métricas projetadas.
4. `git status` revisado; **nenhum commit/push** (regra do `CLAUDE.md`).

## Riscos e mitigações

- **Laravel `stats.json` × Packagist `dt`** usam a mesma URL, mas a chave de
  cache inclui o slug do provider — entradas distintas, e no fake de testes o
  stub específico precisa vir antes do genérico.
- **`versions[]` com `-dev`**: escolher a primeira entrada estável; sem
  estável, só a métrica de versão vira “unavailable” (downloads continua).
- **Chave numérica do php.net vira int no decode**: usar `reset($data)`, nunca
  a chave `"8"`.
- **Cobertura nula/fora de 0–100** (Codecov sem dados): `INVALID_RESPONSE` →
  fallback 200 “unavailable”.
- **Mudança de `listFrom([])`**: revisar os quatro chamadores (parser, builder,
  catálogo, criação de badge pessoal) e cobrir com testes; definição com params
  `[]` e `path_pattern` com `{` continua inválida.
- **Rate limit anônimo do Codecov/Coveralls**: cache de 300 s + stale-if-error
  absorvem; nenhum limite novo de config nesta fase.
- **Fixture do tool-props**: regenerar pelo payload real para não divergir da
  ordem/formato.

## Fora de escopo

Badges por branch (Codecov/Coveralls), EOL/versões antigas, downloads por
linguagem, providers customizados (F7), analytics, OpenAPI 3.1 completo (F8),
logos embutidos novos (não há logo de Codecov/Coveralls no catálogo).

## Regra final

Ao terminar: aceite em `docs/acceptance/BADGEFORGE-FASE-6.md`, relatório em
`docs/claude-code/reports/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase6.md`,
plano em `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_..._badgeforge_fase6.md`,
`docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase6_plansfacts.md` e
`docs/survey/20261104_badgeforge_fase6_surveyfacts.md`, e parar para revisão —
sem commit/push.
