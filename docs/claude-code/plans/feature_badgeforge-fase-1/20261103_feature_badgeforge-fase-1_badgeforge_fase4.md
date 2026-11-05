# BadgeForge — Fase 4: providers + badges pessoais + PAT + docs públicas

## Contexto

F1 (SVG estático), F2 (PNG/fontes) e F3 (logos personalizados, presets e
contas) estão entregues. A F4 do SPEC é **"providers GitHub/npm/Packagist/Docker
(acesso público anônimo; PAT de usuário pago só para item privado)"**, e hoje
não existe nada dela no código: nenhuma tabela de provider/badge asset, nenhuma
chamada HTTP no BadgeForge, nenhuma rota `/api/badges/{provider}/...` nem
`/b/{username}/{ulid}`, nenhum cast `encrypted` no app e nenhuma página pública
de documentação do BadgeForge.

O objetivo é transformar o BadgeForge de gerador estático em produto com badges
dinâmicas e badges pessoais: um README pode exibir estrelas do GitHub, versão do
npm, downloads do Packagist ou pulls do Docker — sempre por URL, sem sessão e
sem JavaScript —, e um usuário pago pode publicar uma badge própria com item
privado protegido por PAT do próprio usuário.

Decisões fixadas nesta sessão:

| # | Decisão |
|---|---|
| 1 | **F4 completa**: 4 providers + badges pessoais + PAT para item privado (SPEC §16, ADR-068). |
| 2 | Definições em **tabelas** `badge_providers` e `badge_provider_definitions`, com seeder para os 4 providers (SPEC §8). |
| 3 | **9 métricas** (a opção escolhida dizia "8 comuns", mas a lista enumerada tem 9): `github/stars`, `github/forks`, `github/issues`, `github/license`; `npm/v`, `npm/dm`; `packagist/dt`, `packagist/v`; `docker/pulls`. |
| 4 | **Página pública `/{locale}/docs/badgeforge`** nesta fase, com seções por provider, 5 idiomas, SEO e sitemap. |
| 5 | Endpoint provider = `GET /api/badges/{provider}/{definition}/{path}.{ext}`, registrado **depois** da rota estática dentro do grupo `badges.`; a ordem da coleção garante que `/static/...` continue vencendo. |
| 6 | Chamada pública é **anônima** (nenhum token global em `.env`/config/banco); PAT é por badge pessoal, cifrado, e nunca aparece em URL/log/props/erro/exportação. |
| 7 | Cache de provider = 300 s (config), com requisição condicional e `stale-if-error`. Imagem pessoal é stateless e não aceita `?refresh=1`; atualizar é um `POST` autenticado do dono. |
| 8 | Badge pessoal só é criada por conta paga; depois de um downgrade as existentes continuam servindo, mas criação/edição ficam bloqueadas. |
| 9 | `parser_class` do SPEC fica **inerte**: o seeder grava `client_key`/`extractor_key` e o código mapeia para classes conhecidas (nunca executa classe vinda do banco). |
| 10 | Packagist é sempre público; npm `dm` também (a API de downloads não suporta pacote privado); Docker privado exige username (non-secret) além do PAT. |

Branch de trabalho: `feature/badgeforge-fase-1` (branch atual do checkout;
diretório `feature_badgeforge-fase-1`). Plano e relatório vão para
`docs/claude-code/{plans,reports}/feature_badgeforge-fase-1/`; pela skill
`grill-with-docs`, também
`docs/plans/feature_badgeforge-fase-1/20261103_badgeforge_fase4_plansfacts.md`
e `docs/survey/20261103_badgeforge_fase4_surveyfacts.md`. Data: 2026-11-03.

Confirmado na sessão de grill (2026-11-03): execução **contínua E1→E9** (parada
única no fim, salvo gate vermelho); **9 métricas**; `badge_assets` **sem
`slug`** e com `user_id` obrigatório (SPEC/ADR-081 registram o desvio);
smoke com **rede real**, registrando bloqueios e apoiando-se nos testes com
`Http::fake`.
Idiomas: os cinco de `config/locales.php` — `en`, `pt_BR`, `pt_PT`, `fr`, `es`.

## Fora de escopo

Endpoint JSON/SSRF (F5), analytics/`badge_asset_views`/exportação CSV (F7),
providers customizados e admin (F7), providers Codecov/Coveralls/Laravel/PHP/
Python (F6), refresh forçado por não-dono, edição de provider/params de uma
badge pessoal já criada (recriar é a saída), i18n do conteúdo da imagem (label e
message do badge são neutros de idioma; UI e docs são traduzidas) e remoção de
providers/definições.

## 1. Banco, models, seeders e config

### Migrações aditivas

`2026_11_03_000001_create_badge_providers_table.php`

```php
$table->id();
$table->string('slug', 40)->unique();
$table->string('name', 80);
$table->string('category', 40)->default('vcs');
$table->boolean('enabled')->default(true);
$table->boolean('requires_auth')->default(false); // item privado exige credencial
$table->string('auth_type', 20)->nullable();       // bearer|basic
$table->string('base_url', 255)->nullable();       // SPEC §8; hoje o host vem do url_template
$table->unsignedInteger('cache_ttl')->nullable();  // null = config('badgeforge.providers.cache_ttl')
$table->string('documentation_url', 255)->nullable();
$table->unsignedInteger('rate_limit_per_minute')->nullable();
$table->json('config')->nullable();
$table->timestamps();
```

`2026_11_03_000002_create_badge_provider_definitions_table.php`

```php
$table->id();
$table->foreignId('provider_id')->constrained('badge_providers')->cascadeOnDelete();
$table->string('slug', 40);
$table->string('name', 80);
$table->string('label', 40);                       // texto da metade esquerda
$table->string('client_key', 40);                  // github|npm|packagist|docker
$table->string('extractor_key', 40);               // stars|forks|issues|license|v|dm|dt|pulls
$table->string('path_pattern', 120);               // {owner}/{repo}
$table->json('required_params');                   // [{name,pattern,allowSlash,placeholderKey}]
$table->json('optional_params')->nullable();
$table->string('url_template', 255);               // https://api.github.com/repos/{owner}/{repo}
$table->string('response_type', 20)->default('json');
$table->string('parser_class', 255)->nullable();   // inerte; seed grava NULL
$table->boolean('supports_private')->default(false);
$table->string('example_path', 255);               // natanfiuza/devtools, vue, ...
$table->unsignedSmallInteger('sort')->default(0);
$table->boolean('enabled')->default(true);
$table->timestamps();
$table->unique(['provider_id', 'slug']);
```

`2026_11_03_000003_create_badge_assets_table.php`

```php
$table->ulid('id')->primary();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 80);
$table->string('type', 20);                        // static|provider
$table->boolean('private')->default(false);        // provider: item privado
$table->string('provider_slug', 40)->nullable();
$table->string('definition_slug', 40)->nullable();
$table->json('params')->nullable();
$table->json('form')->nullable();                  // snapshot cosmético / estático
$table->text('credential')->nullable();            // cast encrypted
$table->string('credential_username', 80)->nullable(); // Docker (non-secret)
$table->string('credential_fingerprint', 64)->nullable(); // HMAC p/ cache
$table->timestamp('credential_set_at')->nullable();
$table->string('svg_path', 255)->nullable();
$table->string('png_path', 255)->nullable();
$table->string('etag', 64)->nullable();
$table->unsignedBigInteger('bytes')->nullable();
$table->timestamp('last_generated_at')->nullable();
$table->timestamp('expires_at')->nullable();
$table->timestamps();
$table->unique(['user_id', 'name']);
$table->index(['user_id', 'created_at']);
```

`2026_11_03_000004_seed_badge_providers.php` insere os provedores e as 9
definições abaixo com `insertOrIgnore`, como em
`2026_10_14_000004_seed_plans_catalog.php`: o deploy roda `migrate --force` e
nunca `db:seed`, então o catálogo precisa nascer na migração, sem duplicar nem
sobrescrever ajustes administrativos. `parser_class` fica **NULL**; o que
identifica o cliente é `client_key`.

| provider | definitions | endpoint |
|---|---|---|
| github | stars, forks, issues, license | `https://api.github.com/repos/{owner}/{repo}` |
| npm | v | `https://registry.npmjs.org/{package}/latest` |
| npm | dm | `https://api.npmjs.org/downloads/point/last-month/{package}` |
| packagist | dt | `https://packagist.org/packages/{vendor}/{package}.json` |
| packagist | v | `https://repo.packagist.org/p2/{vendor}/{package}.json` |
| docker | pulls | `https://hub.docker.com/v2/repositories/{namespace}/{repository}/` |

`supports_private`: github (todas), npm/v, docker/pulls = true; npm/dm e
packagist (ambas) = false. No provider, `requires_auth` = true para github,
npm e docker (e `auth_type` = bearer, bearer e basic, respectivamente) e false
para packagist. `documentation_url` aponta para a API pública de cada um;
`sort` segue a ordem da tabela.

`badge_assets.user_id` é obrigatório na F4 (badge pessoal sempre tem dono); o
SPEC previa nullable para uma futura badge anônima, que não existe. Desvio
confirmado na sessão de grill: `user_id` obrigatório e sem coluna `slug`; o
`source_path` do SPEC corresponde a `svg_path`/`png_path`, e o SPEC/ADR-081
registram a diferença.

Não há coluna `slug`: a URL usa o ULID e o path relativo do arquivo usa o
username capturado na criação (guardado em `svg_path`/`png_path`). Para não colidir com
`static/`, `png/` etc. do cache do renderer nem permitir traversal, o path é
`storage/app/data/tools/badgeforge/personal/{username}/{ulid}.{ext}`, **não**
`{username}/{ulid}` na raiz; atualizar o SPEC/ADR-081. A criação exige
`users.username` não nulo e diferente de `.`/`..`; sem ele, 422.

### Models/factories

- `App\Models\BadgeProvider`: `HasFactory`, casts `enabled`/`requires_auth` bool,
  `cache_ttl` int, `config` array; relations `definitions()`; `scopeEnabled`;
  `effectiveCacheTtl()` = `cache_ttl ?? config('badgeforge.providers.cache_ttl')`.
- `App\Models\BadgeProviderDefinition`: `belongsTo(BadgeProvider)`, casts
  `required_params`/`optional_params` array, bools e `sort` int; `scopeEnabled`,
  `forProvider`.
- `App\Models\BadgeAsset`: `HasUlids`, `belongsTo(User)`, casts `params`/`form`
  array, `private` bool, `credential => encrypted`, timestamps;
  `#[Hidden(['credential', 'credential_fingerprint'])]`;
  `publicSvgUrl()/publicPngUrl()` usando o `username` atual;
  `credentialSet(): bool`.
- `User::badgeAssets()` (`HasMany`).
- `BadgeProviderFactory`, `BadgeProviderDefinitionFactory`, `BadgeAssetFactory`
  (estados `static()`, `provider()`, `private()`).

### Config (`config/badgeforge.php`)

```php
'provider_endpoint' => '/api/badges',
'personal_endpoint' => '/b',
'providers' => [
    'cache_ttl' => 300,
    'stale_ttl' => 86400,
    'fallback_ttl' => 60,
    'connect_timeout' => 3,
    'timeout' => 5,
    'max_body_bytes' => 1048576,
    'max_param_length' => 200,
    'user_agent' => 'devtools-badgeforge/1.0',
    'unavailable_message' => 'unavailable',
    'refresh_rate_limit' => 10,
    'refresh_rate_decay_seconds' => 60,
],
'personal_badges' => [
    'name_max_length' => 80,
    'max_cache_seconds' => 3600,
    'credential_max_length' => 512,
    'prune_after_hours' => 48,
    'path' => 'app/data/tools/badgeforge', // personal/{username}/{ulid}.{ext}
],
```

Nenhum TTL/limite fica hardcoded em controller, client ou Vue.

## 2. Camada de providers

Pasta `app/Support/BadgeForge/Providers/`:

- `ProviderCatalog`: consulta providers/definitions habilitados, memoiza por
  request; `pair(string $provider, string $definition): ?ProviderPair`,
  `forDocs(): list<array>`, `all(): list<BadgeProvider>`. Captura `QueryException`
  e devolve catálogo vazio: durante o deploy (código novo antes da migração) a
  página da tool e a docs não podem dar 500. `static` é slug reservado (rota
  estática); um provider com esse slug é ignorado pelo catálogo.
- `ProviderPair` (readonly): provider + definition.
- `ProviderPathParser`: valida `{provider}/{definition}/{path}` e devolve
  `array<string,string>` de params; rejeita vazio, `..`, control chars, excesso
  de comprimento e contagem errada; suporta **um** param `allowSlash` (pacote
  npm com escopo) e proíbe dois.
- `ProviderUrlBuilder`: substitui placeholders do `url_template` com
  `rawurlencode` (exceto `allowSlash`, onde `/` é preservado); valida HTTPS,
  host do próprio `url_template`, sem userinfo/porta e sem redirect.
- `ProviderClientRegistry`: mapa `client_key => client`; chave desconhecida
  devolve `null` e loga apenas o slug.
- `ProviderFetchRequest` (readonly): pair, params, URL resolvida,
  `?ProviderCredential`, `?string $etag` (condicional).
- `ProviderPayload` (readonly): `array<string, scalar|null> $metrics` já
  **projetado** pelo cliente (só os números/versões que o badge usa), `?etag`,
  `bool $notModified`. Nunca guarda o corpo cru: um repo privado não deixa
  descrição/README no cache.
- `ProviderFetchException`: códigos estáveis (`unavailable`, `rate_limited`,
  `not_found`, `unauthorized`, `invalid_response`, `timeout`, `unsafe_url`).
- `ProviderMetricFormatter`: números (`<1000`, `k`, `M`, `B`), licença
  (`spdx_id` ou `unknown`), versão como string; trunca por
  `limits.max_text_length`; nunca escapa HTML (o renderer escapa).
- `ProviderCredential` (readonly): `token`, `?username`; `__debugInfo()`
  redigido; `fingerprint()` = HMAC-SHA256 do token com `APP_KEY`, prefixado
  pelo provider.

Clientes em `app/Support/BadgeForge/Providers/Clients/`:

```php
interface ProviderClient
{
    public function fetch(ProviderFetchRequest $request): ProviderPayload;

    public function supportsPrivate(BadgeProviderDefinition $definition): bool;
}
```

- `GithubProviderClient`: uma chamada a `/repos/{owner}/{repo}` projeta
  `stars`, `forks`, `issues` e `license` de uma vez; envia
  `Authorization: Bearer <PAT>` só quando há credencial;
  `Accept: application/vnd.github+json`. As 4 definições compartilham a mesma
  URL e, portanto, a mesma entrada de cache.
- `NpmProviderClient`: `v` no registry (`Bearer` quando privado) projeta
  `version`; `dm` no `api.npmjs.org` projeta `downloads` e é **sempre anônimo**
  (privado rejeitado na criação). URLs diferentes, caches diferentes.
- `PackagistProviderClient`: `dt` projeta `downloads.total`; `v` no
  `repo.packagist.org` p2 projeta a primeira versão (latest). Sempre público.
- `DockerHubProviderClient`: pulls no Hub; privado usa Basic
  `username:token` (username exigido e não secreto).

Regras comuns:

1. `Http::withHeaders([...])->connectTimeout(config(...))->timeout(config(...))
   ->withoutRedirecting()`; `User-Agent` de config; `Http::preventStrayRequests()`
   nos testes.
2. Resposta > `max_body_bytes` ou JSON inválido → `invalid_response`.
3. 401/403/404/429/5xx/timeout → exceção com código; **nunca** 500 na imagem.
4. Credencial nunca entra em log, mensagem, cache ou URL; log só com
   provider, definition e classe da exceção.
5. `supportsPrivate()` é a autoridade para aceitar `private=1`.
6. O `extractor_key` da definição escolhe a chave em `ProviderPayload::metrics`
   (`stars`, `forks`, `issues`, `license`, `version`, `downloads`, `pulls`); a
   mensagem sai do `ProviderMetricFormatter` e o label do `definition.label`.
   Shape ausente/tipo inesperado → `invalid_response`.

## 3. Endpoint público de provider

Rota em `routes/api.php`, dentro do grupo existente
`Route::prefix('badges')->name('badges.')` e **depois** da estática (a coleção
do Symfony casa na ordem de registro; não há lookahead frágil):

```php
Route::get('{provider}/{definition}/{path}', ProviderBadgeController::class)
    ->where('provider', '[a-z0-9][a-z0-9-]*')
    ->where('definition', '[a-z0-9][a-z0-9-]*')
    ->where('path', '.+\.(?:svg|png)')
    ->name('provider');
```

`{path}` precisa capturar `/` (o requisito `.+` permite); o controller separa a
extensão, chama `ProviderPathParser`, resolve o `ProviderPair` e monta o spec.
Provider/definition desconhecido, desabilitado, path inválido ou parâmetro
fora do pattern → **404** com envelope `ApiError`. Um teste garante que
`/api/badges/static/...` continua na rota estática mesmo com a rota nova
registrada.

Fluxo:

1. `ProviderBadgeComposer` monta o `ProviderFetchRequest` anônimo (sem
   credencial; `?refresh=1` é ignorado nesta rota).
2. `ProviderMetrics::get($request)` resolve a URL e consulta o `ProviderCache`:
   - entrada fresca (`fetched_at + provider->effectiveCacheTtl() > now`) → HIT;
   - vencida → lock (`Cache::lock`), requisição condicional com `If-None-Match`;
     304 atualiza `fetched_at`; sucesso grava o payload projetado; falha →
     **stale** se existir;
   - sem entrada e falha → **fallback** (`label` da definition + mensagem de
     `providers.unavailable_message`), cache curto de `fallback_ttl`;
   - falha do próprio cache (store indisponível) → tenta o fetch direto e, se
     falhar, fallback; nunca 500.
3. `BadgeSpecBuilder::fromPayload()` (extraído de `BadgeRequest`) aplica
   style/cores/logo/font/link e
   `cacheSeconds = min(provider->effectiveCacheTtl(),
   config('badgeforge.personal_badges.max_cache_seconds'))`.
   Provider mode **ignora** `?cacheSeconds` do cliente; `?refresh=1` também é
   ignorado nesta rota.
4. `StaticBadgeRenderer` + `PngCache` (SVG/PNG), `ETag = sha256(body)`,
   `Cache-Control: public, max-age=<ttl>`, `X-Badge-Cache: HIT|MISS|STALE`,
   `Warning: 110 - "Response is Stale"` quando stale, `download=1` como hoje.

Cache:

- `ProviderCacheKey`: `badgeforge:provider:` + sha256 de
  `provider|URL resolvida|fingerprint|public`, com params canônicos (ksort) na
  URL. GitHub stars/forks/issues/license compartilham a entrada porque a URL é
  a mesma; npm `v`/`dm` e Packagist `dt`/`v` têm entradas distintas.
- Valor `CachedProviderPayload`: `metrics` projetado, `etag`, `fetched_at`,
  `stale_until`; TTL de `stale_ttl`; corrupção → `null` e refetch. O corpo cru
  do provider não é persistido.
- Store default é `database`; os testes forçam `cache.default=array`.
- O controller usa um `BadgeImageResponder` extraído de
  `StaticBadgeController` (headers, `ETag`/304, `download=1`, PNG/Imagick 503),
  com fonte de PNG `PngCache` (provider público) ou `BadgeAssetStorage`
  (pessoal). A rota estática passa a usar o mesmo responder; os testes dela
  continuam valendo.

## 4. Badges pessoais e PAT

### Rotas

A imagem pessoal é **stateless**, como o endpoint estático: sem sessão, sem
CSRF e sem auth. Um arquivo novo `routes/public.php` é registrado em
`bootstrap/app.php` pelo callback `then` do `withRouting`, sem o grupo `web`:

```php
Route::get('b/{username}/{badgeAsset}.{ext}', PersonalBadgeController::class)
    ->where('username', '[A-Za-z0-9_][A-Za-z0-9._-]{0,39}')
    ->whereUlid('badgeAsset')
    ->where('ext', 'svg|png')
    ->name('badges.personal');
```

O controller resolve o ULID manualmente (não por implicit binding), captura
`QueryException` e devolve `response('', 404)` diretamente — `abort(404)` cairia
no responder global que monta a página Inertia de erro, imprópria para uma
URL de imagem. O mesmo vale para PNG sem Imagick: 503 direto, sem JSON.

Em `routes/badgeforge.php` (grupo `auth, verified, active`; escritas em
`throttle:badgeforge-management`):

| Método | Caminho | Ação |
|---|---|---|
| POST | `/badgeforge/badges` | criar badge pessoal (pago) |
| PATCH | `/badgeforge/badges/{badgeAsset}` | renomear |
| PATCH | `/badgeforge/badges/{badgeAsset}/credential` | definir/trocar PAT |
| POST | `/badgeforge/badges/{badgeAsset}/refresh` | forçar nova geração (dono, pago, `throttle:badgeforge-refresh`) |
| DELETE | `/badgeforge/badges/{badgeAsset}` | excluir |

Dono errado → **404** (padrão F3). O `POST /refresh` só existe para provider;
static responde 422 `not_provider`. O `BadgeForgeServiceProvider` registra o
limiter `badgeforge-refresh` lendo `providers.refresh_rate_limit` e
`providers.refresh_rate_decay_seconds`. A URL pública nunca ganha
`?refresh=1`; a UI chama o POST e recarrega a imagem com um `?v=<timestamp>`
local.

### Ações e regras

- `CreateBadgeAsset` (transação): exige `!effectivePlan()->isFree()` e
  `username` válido; `type=static` grava `form` normalizado
  (`PresetSpecNormalizer`), renderiza o SVG inicial e grava `svg_path`,
  `etag`, `last_generated_at` e `expires_at = now + cacheSeconds`;
  `type=provider` valida provider/definition/params e grava `params` + `form`
  cosmético **sem fetch na criação** (o primeiro `GET` público gera e grava;
  assim um provider fora do ar não impede criar a badge). `private=1`
  exige `credential` e rejeita definitions `supports_private=false`; uma
  `credential` com `private=0` é recusada (422 `credential_not_allowed`);
  Docker privado exige `credential_username`; Packagist e npm `dm` nunca
  aceitam privado (`private_unsupported`).
- `UpdateBadgeAsset`: só `name`; provider/params/form imutáveis (recriar).
- `SetBadgeAssetCredential`: define/troca o PAT; recalcula
  `credential_fingerprint`, apaga SVG/PNG e força nova geração. Só provider
  com `supports_private` e badge `private=true`; static ou badge pública →
  422 `credential_not_allowed`. Docker exige `credential_username` no corpo
  (pode repetir o atual). Não há remoção: revogar o token é excluir a badge
  (recriar é a saída), e uma badge privada nunca fica sem credencial.
- `RefreshBadgeAsset`: dono, pago e provider; refaz o fetch e regrava SVG/PNG;
  responde `{url, pngUrl, generatedAt}` para a UI cache-bustar.
- `DeleteBadgeAsset`: apaga linhas e arquivos `svg_path`/`png_path`.
- Os FormRequests de criação/credencial declaram `$dontFlash = ['credential']`
  (e o equivalente no Vue não ecoa o valor), para que um 422 nunca grave o PAT
  no `_old_input` da sessão.
- Limite: badges pessoais são ilimitadas para pagos; Free recebe 422
  `paid_only` **só na criação/refresh/edição**; guest redireciona para login
  (rota autenticada).
- Downgrade: assets existentes continuam servindo e aparecem na conta; copiar
  URL e excluir permanecem; criar, renomear, trocar PAT e refresh ficam
  bloqueados com 422 `paid_only`.

### Armazenamento e renderização

- `BadgeAssetStorage`: base `storage_path(config('badgeforge.personal_badges.path'))`;
  o banco guarda o path **relativo** `personal/{username}/{ulid}.svg|png`
  (sem path absoluto, que muda entre deploys), e `path()` resolve sob a base.
  O username é sanitizado e não pode ser `.`/`..`; escrita atômica (tmp +
  rename, padrão `PngCache`); `delete()`, `read()`, `writeSvg()`,
  `writePng()`. Qualquer path vindo do banco é conferido contra o prefixo
  `personal/` antes de ler.
- `PersonalBadgeRenderer`:
  - `static`: `form` → `PresetSpecNormalizer::normalize(..., $asset->user)` →
    `BadgeSpecBuilder::fromPayload`;
  - `provider`: `ProviderMetrics` com a credencial do asset (fingerprint na
    chave) → label/message + `form` cosmético normalizado e sobrescrito;
    `DecryptException` na leitura do PAT → tenta sem credencial, usa o último
    arquivo bom (stale) ou fallback, nunca 500;
  - `cacheSeconds` efetivo = `min($provider->effectiveCacheTtl(),
    config('badgeforge.personal_badges.max_cache_seconds'))`.
- `PersonalBadgeController`:
  1. resolve o asset (ULID + username exato; username diferente ou linha
     excluída → `response('', 404)`); `QueryException` → idem;
  2. SVG/PNG do disco quando `expires_at` está no futuro; senão regenera e
     regrava (provider usa lock por asset para não disparar N fetches);
  3. se a regeneração falhar e houver arquivo anterior, serve o anterior;
     sem arquivo, renderiza fallback e grava com `expires_at = now + fallback_ttl`;
  4. ETag/304, `Cache-Control`, `download=1`, PNG via `PngRenderer` e
     `BadgeImageResponder`.
- `badgeforge:prune` ganha a limpeza de arquivos sob `personal/` que não estão
  em `svg_path`/`png_path` de nenhuma linha e têm mtime > `prune_after_hours`,
  com `--dry-run`; nunca apaga arquivo referenciado.
- Anonimização: apaga arquivos e assets; exportação inclui metadados
  (name/type/provider/definition/params/URLs/datas), **sem** `credential` nem
  fingerprint.

## 5. Props, tipos TS e contrato

`BadgeForgeToolProps::for(?User)` acrescenta, depois das chaves atuais:

```text
endpoint: '/api/badges/static'               // ja existe
providerEndpoint: '/api/badges'              // config('badgeforge.provider_endpoint')
personalEndpoint: '/b'                       // config('badgeforge.personal_endpoint')
providers: [{ slug, name, category, requiresAuth, documentationUrl,
              definitions: [{ slug, name, label, supportsPrivate, examplePath,
                              params: [{name, required, pattern, allowSlash, placeholderKey}] }] }]
limits.personalBadges: { limit, used }       // pago: null/used; free: 0/used; guest: 0/0
management.canCreatePersonalBadges: bool
personalBadges: [{ id, name, type, providerSlug, definitionSlug, params,
                   private, credentialSet, credentialUsername,
                   url, pngUrl, lastGeneratedAt, expiresAt, createdAt }]
```

- Convidado: `providers` presente (é catálogo público), `personalBadges: []`,
  `canCreatePersonalBadges: false`; fixture `tool-props.json` regravada e
  comparada exatamente em Pest e Vitest.
- Free: `providers` presente, `personalBadges` **também presente** (o dono
  precisa ver/copiar/excluir o que sobreviveu ao downgrade), `limit: 0`,
  `canCreatePersonalBadges: false`; a UI desabilita renomear/PAT/refresh.
- Nenhuma query de assets para convidado; a consulta de assets **nunca
  seleciona** `credential` nem `credential_fingerprint`; `credentialSet` vem de
  `credential_set_at`. A consulta de assets captura `QueryException` (migração
  ainda não rodou) e devolve lista vazia/`used = 0`, para a página da tool não
  dar 500 durante o deploy.
- `url`/`pngUrl` são `null` quando `users.username` está vazio; a criação exige
  username.
- `name`/`label` do provider são metadados; a UI mostra os nomes de métrica
  traduzidos por chave (`tools.badgeforge.provider.metrics.<provider>_<slug>`),
  com o `name` do banco só como fallback. Os placeholders dos campos também são
  traduzidos por `placeholderKey` (`tools.badgeforge.provider.params.<key>`),
  nunca texto vindo do banco. A marca (GitHub, npm...) é nome próprio e não se
  traduz.
- Tipos em `resources/js/tools/badgeforge/types.ts`; `AccountBadgeForgeProps`
  continua com `toolProps/toolUrl/upgradeUrl`.

## 6. UI

### Builder (`resources/js/tools/badgeforge/`)

- `Tool.vue`: abas `static` (atual), `provider` e um botão/CTA de badges
  pessoais. O modo estático continua 100% local e sem requisição por digitação
  (guardas atuais permanecem).
- Novos: `ModeTabs.vue`, `ProviderForm.vue`, `ProviderPreview.vue`,
  `PersonalBadgeDialog.vue`, `PersonalBadgesPanel.vue`.
- `lib/providerUrl.ts`: `buildProviderBadgeUrl(origin, endpoint, provider,
  definition, params, cosmetics, {ext, download})`; reusa de `lib/url.ts` o
  `serializeColor`/defaults e um helper `cosmeticParams(spec, defaults)`
  extraído (sem `cacheSeconds`); valida params no cliente. O modo provider
  **nunca** manda `cacheSeconds`: o TTL é do servidor.
- Preview de provider usa `<img :src="url">` (a imagem é o contrato), com
  debounce ao digitar; sem `fetch` manual. Modo estático segue com
  `renderBadge`/`buildBadgeUrl` e sem requisição por digitação.
- `PersonalBadgeDialog`: nome, `private` (só quando `supportsPrivate`), PAT
  `type=password` write-only (nunca pré-preenchido, limpo ao fechar/sucesso),
  username só para Docker; Free vê CTA de upgrade.
- `PersonalBadgesPanel`: lista, copiar URL SVG/PNG, status da credencial
  (definida/ausente/não exigida), refresh via `POST /badgeforge/badges/{id}/refresh`
  seguido de `img?v=<timestamp>`, renomear, trocar PAT, excluir com
  confirmação; nenhum valor de token no DOM. Free vê a lista em modo limitado
  (copiar/excluir) e o CTA de upgrade.

### Dashboard (`resources/js/pages/dashboard/BadgeForge.vue`)

- Adiciona `PersonalBadgesPanel` abaixo de logos/presets, reusando
  `toolProps`; Free vê o bloco de upgrade; sem novo contrato de página.

### i18n (5 idiomas)

`tools.badgeforge.{modes,provider,personal}.*`,
`account.badgeforge.personal_*`, `validation.badge_asset.*`,
`docs.badgeforge.*`, `app.footer.badgeforge_docs`, os nomes de métrica
`tools.badgeforge.provider.metrics.<provider>_<slug>` e os placeholders
`tools.badgeforge.provider.params.<key>`.
O namespace `docs` entra em `config/locales.php` (`namespaces`), com arquivos em
`lang/{en,pt_BR,pt_PT,fr,es}/docs.php`; a paridade de traduções passa a cobri-lo.
Toda string visível traduzida; label/message da imagem são neutros de idioma
(exceção documentada no ADR-082).

## 7. Docs públicas

- Rota no grupo de locale de `routes/web.php`, logo após `api-docs`:
  `GET {locale}/docs/badgeforge` → `BadgeForgeDocsController`, nome
  `badgeforge-docs`, página `resources/js/pages/BadgeForgeDocs.vue`.
- Props públicas (sem auth/credencial): `providers` (catálogo + 9 exemplos),
  `staticEndpoint`, `providerEndpoint`, `personalEndpoint`, `cacheTtl` e
  `origin` (`url('/')`) para montar snippets absolutos.
- Conteúdo: intro, quick start, URL estática, 4 seções de provider com
  exemplos, badges pessoais/PAT (GitHub, npm, Docker; Packagist público),
  cache/ETag/stale/refresh, erros/fallback, segurança, snippets copiáveis.
- `resolveLayout` (`resources/js/lib/inertiaApp.ts`): `BadgeForgeDocs` entra na
  lista de páginas públicas.
- `useRoutes`: `badgeforgeDocs`; footer público ganha link
  (`app.footer.badgeforge_docs`).
- `Seo::pageMeta()` ganha o caso `'badgeforge-docs'` (título/descrição de
  `docs.badgeforge.meta.*`); `Sitemap::PAGES` ganha `'badgeforge-docs'`; testes de SEO,
  sitemap e hreflang.
- `config/locales.php`: adicionar `docs` a `namespaces`; criar
  `lang/{en,pt_BR,pt_PT,fr,es}/docs.php`.
- Tutoriais de repositório `docs/tutorial/{github,npm,docker}-token.md`
  atualizados para apontar para a badge pessoal/campo de PAT e para a página
  pública.

## 8. Documentação da fase

- **ADR-080 — Camada de providers**: metadados em tabelas + registry de
  clientes, `parser_class` inerte, acesso público anônimo, cache 300 s por URL
  com payload **projetado**, condicional, stale-if-error, fallback 200, rota
  sem sombrear a estática.
- **ADR-081 — Badges pessoais e PAT por badge**: só pago cria, URL pública por
  username+ULID, PAT cifrado por badge, Docker username, Packagist/npm dm
  públicos, downgrade serve/exclui mas não edita, storage
  `personal/{username}/{ulid}`, exportação/anonimização e refresh por POST
  autenticado (imagem stateless).
- **ADR-082 — Docs públicas e i18n**: `/docs/badgeforge`, 5 idiomas, SEO,
  sitemap e exceção de idioma do conteúdo da imagem.
- Notas em ADR-002 (provider mode é chamada explícita; estático continua local),
  ADR-064/068/069/071 apontando para as decisões da F4.
- `docs/SPEC-BADGEFORGE.md` §2/§6/§7/§8/§9/§10/§11/§14/§16/§17;
  `docs/badgeforge/API.md` e `OPENAPI.md` (v1.4.0) com provider/pessoal
  entregues; `docs/glossario.md` (atualiza "Badge dinâmica (de provider)" e
  "Badge pessoal", que já existem nas linhas 125–126, apontando para as ADRs da
  F4; adiciona Definição de provider, Item público/privado, Credencial por
  badge, Fingerprint de credencial, Cache de provider, Stale-if-error e Fallback
  badge — sem duplicar "Provider badge");
  `CLAUDE.md` (F4 entregue, ADRs 062–082).
- `docs/acceptance/BADGEFORGE-FASE-4.md`; relatório em
  `docs/claude-code/reports/feature_badgeforge-fase-1/20261103_feature_badgeforge-fase-1_badgeforge_fase4.md`
  e plano em `docs/claude-code/plans/feature_badgeforge-fase-1/`; pela skill
  `grill-with-docs`, também
  `docs/plans/feature_badgeforge-fase-1/20261103_badgeforge_fase4_plansfacts.md`
  e `docs/survey/20261103_badgeforge_fase4_surveyfacts.md`.

## 9. Testes

### Pest — unit

`tests/Unit/BadgeForge/Providers/`:

- `ProviderPathParserTest`: 9 definições, npm com escopo, params inválidos,
  traversal, excesso de tamanho, contagem errada, dois `allowSlash`.
- `ProviderUrlBuilderTest`: substituição/encoding, HTTPS, host, userinfo,
  redirect, placeholders desconhecidos.
- `ProviderMetricFormatterTest`: 0/999/1000/1234/1M/1B, licença nula, versão,
  truncamento, caracteres de controle.
- `ProviderCacheKeyTest`: ordem de params, fingerprint, anônimo vs privado,
  token ausente da chave, GitHub compartilhando entrada por URL e npm
  `v`/`dm` em entradas separadas.
- `ProviderPayloadProjectionTest`: payload projetado só com as métricas do
  provider, sem descrição/README/owner; shape inválido → exceção.
- `ProviderCredentialTest`: fingerprint estável/HMAC, debug redigido,
  `toArray()` sem segredo, `DecryptException` vira ausência de credencial.
- `BadgeAssetStorageTest`: paths sob `personal/`, escrita atômica, delete,
  órfãos, rejeição de path fora do prefixo.

### Pest — feature

`tests/Feature/BadgeForge/Providers/`:

- `ProviderBadgeEndpointTest`: 9 métricas com `Http::fake`; anônimo sem
  `Authorization`; GitHub stars/forks/issues/license fazendo **uma** chamada
  só; cosméticos/PNG/download; 404 para desconhecido/desabilitado/path
  inválido; 500/429/401/403/404/timeout/JSON inválido → 200 fallback; nunca
  500; `Http::preventStrayRequests()`.
- `ProviderBadgeCacheTest`: HIT/MISS, TTL 300, `travel`, 304, stale-if-error,
  entrada corrompida, separação por fingerprint e por URL, lock contention,
  store de cache fora do ar → fetch direto/fallback.
- `ProviderRouteConflictTest`: `/static/...` continua estática; `static` é slug
  reservado (o catálogo não aceita provider com esse slug); nomes de rota.
- `ProviderSeederTest`: 4 providers/9 definitions, idempotência, `client_key`
  resolve no registry, `parser_class` NULL e nunca instanciado.
- `ProviderCatalogTest`: `QueryException` (migração ausente) devolve catálogo
  vazio e a página da tool/docs continua 200.

`tests/Feature/BadgeForge/Personal/`:

- `PersonalBadgeManagementTest`: pago cria static/provider; free 422
  `paid_only` na criação e vê a lista existente em modo leitura; guest login;
  user sem username 422; validação de provider/params/private/credencial; Docker
  username; Packagist/npm dm privados recusados; rename/delete; refresh POST;
  404 alheio; downgrade serve e bloqueia edição; throttle.
- `PersonalBadgeServingTest`: rota stateless (sem cookie/sessão); username/ULID
  corretos; username errado, inexistente, excluído e banco fora → 404 direto;
  static sem rede; provider com PAT; expiração regenera; falha de regeneração
  serve arquivo anterior/fallback; `?refresh=1` é ignorado; ETag/304;
  PNG/download; cache cap; `DecryptException` não vira 500.
- `BadgeAssetCredentialLeakTest`: token ausente de props, resposta, flash,
  exceção, log, export, cache e `toArray()`; valor no banco difere do token;
  troca de token invalida arquivo; refresh não devolve o token.
- `PersonalBadgePruneTest`: órfão antigo remove, referenciado preserva,
  `--dry-run`.

`tests/Feature/BadgeForge/`: props exatas (convidado/free/pago), conta,
exportação/anonimização, docs (5 idiomas), sitemap/SEO/hreflang, `docs` em
`config('locales.namespaces')`.
`TranslationsParityTest`: namespace `docs` e chaves novas nos 5 idiomas.

### Vitest

- `badgeforge-provider.test.ts`: `buildProviderBadgeUrl` para os 4 providers/
  9 métricas, escopo npm, vendor/package, cosméticos, **sem `cacheSeconds`**,
  validação; preview por `<img>`; estático segue sem requisição.
- `badgeforge-personal.test.ts`: free vê upgrade e lista somente leitura; pago
  vê form; PAT password write-only/limpo; sem token em props/DOM; URL pública e
  cópia; status de credencial; refresh chama o POST; exclusão confirmada.
- `docs-badgeforge.test.ts`: 4 seções de provider, pessoal/PAT, links, cache e
  exemplos no locale; namespace `docs` carregado.
- Atualizar `badgeforge-component.test.ts`, `badgeforge-account.test.ts` e a
  fixture `tool-props.json` (igualdade exata).

### Comandos

`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`,
`npm run build`; `Http::preventStrayRequests()` e `cache.default=array` nos
testes de provider.

## 10. Etapas de execução

| Etapa | Conteúdo | Gate |
|---|---|---|
| E1 | Migrações, models, factories, seeder, config | migrate/rollback; Pint/PHPStan |
| E2 | `ProviderCatalog`, `ProviderPair`, parser, URL builder, registry | testes de path/URL/registro; 404 corretos |
| E3 | 4 clientes, fetch request/payload/exception, formatter | `Http::fake` das 9 métricas; erros nunca 500 |
| E4 | Cache projetado por URL, métricas, `ProviderBadgeComposer`, controller, `BadgeImageResponder`/`BadgeSpecBuilder` extraídos, rota | endpoint/cache/stale/304/PNG; estática intacta |
| E5 | `BadgeAsset*` actions, requests, storage `personal/`, renderer, `routes/public.php` + `bootstrap/app.php`, refresh POST, prune, anonimização/exportação | pago/free/guest; 404 direto; token não vaza; ETag/refresh |
| E6 | Props, fixture exata, tipos TS, endpoints, export/anonymize | paridade Pest/Vitest; sem credencial nas props; free lista |
| E7 | UI provider/personal, dashboard, i18n 5 idiomas | Vitest; types/lint; guarda local-first |
| E8 | Docs page, `docs` namespace, SEO/sitemap, footer, tutoriais, API/OpenAPI | docs 5 idiomas; sitemap; build |
| E9 | ADR-080/081/082, SPEC, glossário, CLAUDE, aceite, relatório | gates completos; `git status` revisado |

Imediatamente após a aprovação deste plano, gravar os dois artefatos da skill
`grill-with-docs` (`docs/plans/..._plansfacts.md` com o plano de execução e
`docs/survey/..._surveyfacts.md` com o levantamento e as decisões das rodadas);
o aceite e o relatório fecham na E9.

## 11. Verificação final

```bash
composer test          # rodar antes do Vitest, não em paralelo
npm run test
npm run types:check
npm run lint:check
npm run build
php artisan route:list --path=api/badges
php artisan route:list --path=b/
php artisan route:list --path=docs/badgeforge
php artisan route:list --path=badgeforge/badges
```

Smoke local (`php artisan serve`):

- 9 URLs de provider retornam SVG válido; anônimas, sem token; GitHub
  stars/forks/issues/license compartilham uma chamada. Smoke com **rede real**
  (confirmado); se a rede do ambiente bloquear alguma API, registrar a URL e o
  erro no relatório e considerar o item coberto pelos testes com `Http::fake`.
- Provedor offline/429 → 200 com fallback ou stale, nunca 500; cache fora do
  ar também não derruba a imagem.
- `/api/badges/static/...` continua vencendo a rota de provider.
- Cache HIT/MISS/STALE e TTL 300; `?refresh=1` na URL pública é ignorado.
- Badge pessoal paga: criar, servir por username+ULID, ETag/304, PNG/download,
  `POST /refresh` do dono, trocar PAT, excluir; URL antiga 404.
- `/b/...` responde sem cookie de sessão; rota errada → 404 direto (sem página
  Inertia).
- PAT cifrado no banco, ausente de props/log/erro/exportação; Docker username
  separado; `APP_KEY` trocada → badge não quebra (stale/fallback).
- Free não cria, mas vê/copia/exclui as badges que sobreviveram ao downgrade.
- Docs em 5 idiomas, namespace `docs`, link no footer, sitemap/SEO.
- Builder estático continua sem requisição por digitação.
- `git diff tests/Fixtures/badgeforge/expected` vazio para fixtures
  históricas (provider não usa o renderer local).
- `git status` revisado; **nunca commit/push** (regra do CLAUDE.md).

## 12. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Rota de provider sombrear `static` | rota estática registrada primeiro + teste de conflito (`/static/...` vence) |
| Rate limit anônimo do provider | cache 300 s, condicional, stale-if-error e fallback |
| Formato de API mudar | validação estrita de shape; fallback e testes com fixtures |
| 429/timeout quebrar README | sempre 200 com stale/fallback; `Retry-After` respeitado no TTL de fallback |
| PAT vazar | cast `encrypted`, hidden, nunca em select/props/log/cache; fingerprint HMAC; testes de não-vazamento |
| Cache key carregar token | fingerprint HMAC; token nunca na chave/valor |
| Username colidir com `static/`/`png/` ou escapar do storage | subdir `personal/{username}/{ulid}`, username sanitizado, path do banco conferido contra o prefixo |
| Banco/cache fora durante a imagem | provider: fetch direto/fallback 200; pessoal: 404 direto; nunca 500 |
| 404 de imagem cair na página Inertia global | `response('', 404)` no controller, não `abort(404)` |
| Free perder a própria lista após downgrade | `personalBadges` continua nas props, em modo somente leitura |
| npm `dm` privado / Packagist privado | `supports_private=false`; 422 na criação; docs explicam |
| Docker exigir username | coluna non-secret + validação + tutorial |
| `open_issues_count` incluir PRs | documentar na docs page/API |
| Perda de `APP_KEY` invalida PATs | documentar no RUNBOOK; fluxo de re-credencialização |
| Username mudar quebrar `/b` | path do arquivo guardado em `svg_path`; URL atual usa username atual; docs avisam |
| Exclusão atrás de CDN | cap 3600 s na badge pessoal; docs explicam |
| Fixture exata divergir | atualizar uma vez na E6; Pest e Vitest comparam igualdade |
| Regressão do builder estático | refatorar `cosmeticParams` sem mudar o comportamento de `buildBadgeUrl`/`render.ts`; guardas local-first e fixtures seguem verdes |
