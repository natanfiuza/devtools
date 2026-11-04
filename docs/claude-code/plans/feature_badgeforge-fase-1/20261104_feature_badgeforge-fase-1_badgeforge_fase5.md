# BadgeForge — Fase 5: badge de endpoint JSON + SSRF + limite por plano

## Contexto

F1–F4 estão entregues (estática, PNG/fontes, logos/presets/contas, providers +
badges pessoais + PAT + docs públicas). A F5 do SPEC é "endpoint JSON + SSRF"
e hoje **não existe nada dela no código**.

O problema central: o `url=` é escolhido pelo usuário, então o servidor passa a
buscar um destino arbitrário — exatamente o cenário de SSRF — e a imagem é
consumida por `<img>` anônimo em README, o que conflita com o "apenas
cadastrados" do SPEC. A fase fecha esse contrato: o dono cadastra o endpoint na
dashboard, o servidor guarda a URL (nunca a expõe) e publica uma URL opaca com
ULID; a imagem continua anônima e stateless como as demais.

Referências: `docs/SPEC-BADGEFORGE.md` §6, §11, §14–§17;
`docs/badgeforge/API.md:135-141`; `docs/badgeforge/OPENAPI.md:226-239`.

## Decisões fechadas (grill)

1. **Contrato JSON**: núcleo shields v1 — `schemaVersion === 1`,
   `label`/`message` obrigatórios (message não vazio), `color`/`labelColor`
   opcionais. `logoSvg`, `namedLogo`, `style`, `cacheSeconds` e `link` do JSON
   são ignorados.
2. **Identidade**: tabela `badge_endpoints` (ULID + dono); a URL pública é
   `GET /api/badges/endpoint/{ulid}.{svg|png}`. O dono cadastra na dashboard;
   a imagem em si não exige sessão.
3. **SSRF**: HTTPS/443, sem userinfo/redirect, resolução DNS + **todos** os IPs
   precisam ser públicos, conexão **pinada** no IP validado
   (`CURLOPT_RESOLVE`), proxy desabilitado, curl obrigatório.
4. **Rate limit**: por **dono** — Free 300/min, pago 3000/min; ULID
   desconhecido/inválido cai no anônimo 60/min por IP. Tudo em config.
5. **UI**: painel e diálogo em `/dashboard/badgeforge`; o builder ganha só um
   CTA para o painel.
6. **Criação**: valida URL/SSRF e busca uma amostra; SSRF ou schema inválido →
   422; falha de rede/origem fora do ar **não** impede criar.
7. **JSON**: só o núcleo acima; cosméticos continuam vindo da query
   (`?color=`, `?label=`, `?style=`, logo etc.).
8. **TTL**: servidor fixa 300 s; `?cacheSeconds=` e `?refresh=1` são
   ignorados (igual ao provider).

## Reconciliação bloqueante de config

`config/badgeforge.php:31` hoje usa `'endpoint' => '/api/badges/static'`.
Renomear para `'static_endpoint'` e usar `'endpoint'` para a nova seção.
Ajustar:

- `app/Support/BadgeForge/BadgeForgeToolProps.php:42` — ler
  `badgeforge.static_endpoint`, **mantendo a chave `endpoint` no payload**
  (fixture e TS não mudam por causa disso).
- `app/Http/Controllers/BadgeForgeDocsController.php:22` — idem.
- `tests/Feature/BadgeForge/BadgeForgeDocsPageTest.php` — referências ao
  config antigo.

## Config nova (`config/badgeforge.php`)

```php
'endpoint' => [
    'path' => '/api/badges/endpoint',
    'name_max_length' => 80,
    'url_max_length' => 2048,
    'allowed_scheme' => 'https',
    'allowed_port' => 443,
    'max_url_length' => 2048,
    'cache_ttl' => 300,
    'max_cache_ttl' => 3600,
    'stale_ttl' => 86400,
    'fallback_ttl' => 60,
    'connect_timeout' => 3,
    'timeout' => 5,
    'max_body_bytes' => 524288,
    'max_json_depth' => 32,
    'user_agent' => 'devtools-badgeforge/1.0',
    'unavailable_message' => 'unavailable',
    'follow_redirects' => false,
    'require_curl' => true,
    'rate_limit' => [
        'anonymous' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_ANONYMOUS', 60),
        'free' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_FREE', 300),
        'paid' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_PAID', 3000),
        'decay_seconds' => 60,
    ],
    'blocked_ipv4_cidrs' => [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
        '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
        '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ],
    'blocked_ipv6_cidrs' => [
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96',
        '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8',
    ],
    'require_ipv6_global_unicast' => true,
],
```

Os `env()` só existem para operação/smoke dos limites; o resto é config puro.
Nenhum número fica no código.

## Banco e model

Migração aditiva `create_badge_endpoints_table` (retrocompatível, sem
downtime):

```php
$table->ulid('id')->primary();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 80);
$table->string('url', 2048);
$table->timestamps();
$table->unique(['user_id', 'name']);
$table->index(['user_id', 'created_at']);
```

- `App\Models\BadgeEndpoint`: `HasUlids`, `HasFactory`,
  `#[Fillable(['id','user_id','name','url'])]`, `#[Hidden(['url'])]`,
  `user(): BelongsTo`, `publicSvgUrl()`/`publicPngUrl()` (montam
  `config('badgeforge.endpoint.path').'/'.$id`), `scopeForUser`.
- `User::badgeEndpoints(): HasMany`.
- Factory `BadgeEndpointFactory`.
- A URL de origem **nunca** entra em props, flash, log, exceção ou cache
  legível; `#[Hidden]` é a última linha de defesa.

## Classes novas (`app/Support/BadgeForge/Endpoint/`)

| Classe | Responsabilidade |
|---|---|
| `SafeEndpointUrl` | value object com `canonical`, `host`, `port` |
| `EndpointUrlException` | motivos: `scheme`, `port`, `userinfo`, `host`, `length`, `chars`, `unresolvable`, `unsafe_ip`, `redirect` |
| `EndpointUrlGuard` | parse + canonicalização da URL |
| `HostResolver` / `DnsHostResolver` | A/AAAA via `dns_get_record`, com fallback |
| `IpAddressClassifier` | IPv4/IPv6 público, normalização `::ffff:` e CIDRs da config |
| `ResolvedEndpoint` | host + IP escolhido |
| `EndpointTargetResolver` | resolve e exige **todos** os IPs públicos; prefere IPv4 |
| `EndpointFetchRequest` / `EndpointPayload` | entrada e saída do fetch |
| `EndpointFetchException` | `unavailable`, `rate_limited`, `timeout`, `invalid_response`, `unsupported_content_type`, `too_large`, `unsafe_url`, `redirected` |
| `EndpointPayloadParser` | shields v1 → `EndpointPayload` |
| `EndpointHttpClient` | request pinada (detalhes abaixo) |
| `CachedEndpointPayload` | só label/message/color/labelColor/etag/fetched_at |
| `EndpointCacheKey` | `badgeforge:endpoint:v1:sha256(url canônica)` |
| `EndpointCache` | get/put/forget tolerantes a store fora do ar |
| `EndpointMetrics` / `EndpointMetricsResult` | HIT/MISS/STALE, lock, stale-if-error, fallback |
| `EndpointBadge` / `EndpointBadgeComposer` | spec + status a partir do `BadgeEndpoint` |
| `EndpointGuard` | mapeia motivo → `ValidationException` `validation.badge_endpoint.*` |

Reusar `BadgeSpecBuilder`, `StaticBadgeRenderer` e `BadgeImageResponder`.
**Não** generalizar `ProviderMetrics`/`ProviderCache`: o contrato de payload é
outro e o provider está estável.

## Algoritmo SSRF

1. `trim`; rejeitar vazio, acima de `max_url_length`, controle/espaço/`\` e
   `#`.
2. `parse_url` precisa funcionar; `scheme === https`; sem `user`/`pass`;
   host ASCII (`[a-z0-9.-]+`, aceita `xn--`); sem `%` no host; rejeitar
   `localhost` e host sem ponto que não seja IP literal; portas ausentes ou
   443.
3. Canonicalizar: host minúsculo, sem ponto final, porta omitida, path vazio
   vira `/`, query preservada. A URL canônica é a chave de cache.
4. Resolver A+AAAA. Se não resolver: na criação tolera (nada a conectar); no
   serve vira fallback 200.
5. `IpAddressClassifier` exige **todos** os IPs públicos; IP literal é
   classificado direto. IPv4 mapeado em IPv6 é normalizado antes do teste.
6. Escolher o primeiro IPv4 (senão IPv6) e montar `ResolvedEndpoint`.
7. Fetch com `Http::withOptions()`:
   ```php
   'allow_redirects' => false,
   'proxy' => '',
   'curl' => [
       CURLOPT_RESOLVE => ["{$host}:443:{$ip}"], // IPv6: "[{$ip}]"
       CURLOPT_NOPROXY => '*',
       CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
       CURLOPT_FOLLOWLOCATION => false,
   ],
   ```
   com `User-Agent`, `Accept: application/json`, connect timeout 3 s, timeout
   5 s e `withoutRedirecting()`. Exigir `extension_loaded('curl')`; sem curl,
   falha fechada (fallback 200).
8. Aceitar só 2xx `application/json` (ou `+json`); 3xx →
   `redirected` (422 na criação); 429/5xx/timeout/rede → `unavailable`
   (stale/fallback no serve). Corpo limitado a `max_body_bytes` mesmo sem
   `Content-Length`.
9. JSON com profundidade máxima, objeto, `schemaVersion === 1`,
   `label`/`message` strings não vazias; truncar no `BadgeSpecBuilder`.
   `color`/`labelColor` do JSON entram como defaults; a query vence.

DNS é revalidado a cada cache miss; o IP pinado impede rebinding entre a
validação e a conexão.

## Cache e resposta

- Chave hash: nunca contém host/URL; testada.
- HIT/MISS/STALE com `Cache::lock`, `If-None-Match` opcional quando a origem
  manda `ETag`; 304 renova `fetched_at`.
- TTL de resposta = `max(300, min(config cache_ttl, max_cache_ttl))`;
  `?cacheSeconds=` e `?refresh=1` ignorados.
- Falha com entrada utilizável → `STALE` + `Warning: 110 - "Response is Stale"`.
- Sem entrada → fallback 200 com label = nome do endpoint e message =
  `endpoint.unavailable_message`; TTL curto. Nunca 500.
- Store fora do ar → fetch direto e, se falhar, fallback.
- Headers: `X-Badge-Cache`; ETag/304, `download=1` e PNG vêm do responder.

## Rotas

`routes/api.php` (ordem: estática → endpoint → provider):

```php
Route::get('endpoint/{badgeEndpoint}.{ext}', EndpointBadgeController::class)
    ->where('badgeEndpoint', '[0-9A-Za-z]{1,64}')
    ->where('ext', 'svg|png')
    ->middleware('throttle:badgeforge-endpoint')
    ->name('endpoint');
```

- `endpoint` entra nos slugs reservados do `ProviderCatalog`.
- ULID desconhecido → binding implícito → 404 `ApiError`.
- URL que falhe no guard no serve → 422 `unsafe_url`; schema/rede →
  stale/fallback 200.

`routes/badgeforge.php` (grupo autenticado + `badgeforge-management`):

```text
POST   badgeforge/endpoints                     badgeforge.endpoints.store
PATCH  badgeforge/endpoints/{badgeEndpoint}     badgeforge.endpoints.update
DELETE badgeforge/endpoints/{badgeEndpoint}     badgeforge.endpoints.destroy
```

`whereUlid('badgeEndpoint')`, 404 para recurso de outra conta.

## Rate limiter

`RateLimiter::for('badgeforge-endpoint')` em `BadgeForgeServiceProvider`:

- O `ThrottleRequests` roda **antes** do `SubstituteBindings`
  (`vendor/.../Kernel.php:110-113`), então o limiter recebe o ULID **cru** —
  não tipar o parâmetro como model.
- `Str::isUlid($id)`; carregar `BadgeEndpoint` (id/user_id) + dono; plano Free
  → 300, pago → 3000; chave `user:<id>`.
- ULID inválido/desconhecido ou banco fora → anônimo 60 por IP
  (`guest:<ip>`), sem 500.
- Sem `->response()`: o `ThrottleRequestsException` cai no `ApiError` e vira
  429 `rate_limited` com `X-RateLimit-*`/`Retry-After`.
- Custo: 2–3 queries por hit de origem (endpoint + dono + assinatura); o CDN
  absorve. Não cachear o plano para a mudança valer na hora.

## Criação/gestão

- `StoreBadgeEndpointRequest`: `name` obrigatório (máx. 80, único por dono),
  `url` obrigatório `string` (máx. 2048); autorização = usuário logado.
  Adicionar `url` a `$exceptions->dontFlash([...])` em
  `bootstrap/app.php:100`.
- `CreateBadgeEndpoint`:
  1. `EndpointUrlGuard::parse` → `EndpointUrlException` vira 422
     `validation.badge_endpoint.<motivo>`;
  2. resolve DNS (não resolve = segue);
  3. busca amostra: 2xx válido ou rede fora → segue; schema inválido, corpo
     grande, content-type errado ou redirect → 422 `invalid_payload`;
  4. grava a linha (sem fetch persistido).
- `UpdateBadgeEndpoint`: só renomeia (URL imutável; trocar URL é excluir e
  criar, pois muda a identidade).
- `DeleteBadgeEndpoint`: `EndpointCache::forget($endpoint->url)`, depois
  `delete()`.
- Exportação (`ExportUserData`) inclui `endpoints` com nome + URL de origem
  (dado do dono, download explícito) e as URLs públicas; anonimização
  (`AnonymizeUser`) apaga as linhas e esquece as chaves de cache.
- Toast de sucesso/erro segue o padrão das logos/presets.

## Props, UI e i18n

`BadgeForgeToolProps`:

- `limits.endpointBadges = { nameMaxLength, urlMaxLength }`;
- raiz `endpointBadges = [{ id, name, svgUrl, pngUrl, createdAt }]` (consulta
  com `select` explícito, sem `url`, `QueryException` → lista vazia);
- `management.canCreateEndpointBadges = $canManage` (verificado e ativo; Free
  pode criar — "apenas cadastrados").

Arquivos:

- `resources/js/tools/badgeforge/EndpointBadgesPanel.vue` — lista, copiar
  SVG/PNG, renomear, excluir, criar; espelha `PersonalBadgesPanel.vue`.
- `resources/js/tools/badgeforge/EndpointBadgeDialog.vue` — nome + URL, aviso
  de que a URL fica no servidor.
- `resources/js/pages/dashboard/BadgeForge.vue` — painel depois do
  `PersonalBadgesPanel`, âncora `#endpoint-badges`.
- Builder (`resources/js/tools/badgeforge/BadgeForge.vue` ou página da tool) —
  cartão CTA com `data-test="badge-endpoint-cta"` só quando `canManage`,
  apontando para a dashboard.
- `resources/js/tools/badgeforge/types.ts` + `resources/js/types` — novos
  tipos; `tests/Fixtures/badgeforge/tool-props.json` regravado (convidado) e
  testes de contrato Pest/Vitest atualizados.
- i18n nos 5 idiomas (`en`, `es`, `fr`, `pt_BR`, `pt_PT`):
  `tools.badgeforge.endpoint.*`, `validation.badge_endpoint.*`,
  `docs.badgeforge.endpoint.*`.

## Documentação

- `docs/adr/ADR-083-badgeforge-endpoint-json.md` — identidade ULID, contrato
  shields v1, cache e TTL.
- `docs/adr/ADR-084-badgeforge-ssrf-e-rate-limit.md` — resolução + pin,
  fail-closed, limite por dono e interpretação do "anônimo".
- `docs/SPEC-BADGEFORGE.md` §6 (rota com ULID), §7 (`badge_endpoints`), §10
  (cache), §11 (limites), §14–§17 (segurança/aceite).
- `docs/badgeforge/API.md` — endpoint entregue, contrato, erros, SSRF.
- `docs/badgeforge/OPENAPI.md` — v1.5.0 com a rota pública.
- Página `/{locale}/docs/badgeforge`: props `jsonEndpoint`,
  `endpointCacheTtl`, `endpointRateLimits` e seção "Endpoint JSON" nos 5
  idiomas.
- `docs/glossario.md`: Badge de endpoint JSON, Contrato shields v1, SSRF, DNS
  pinning, Rate limit por dono.
- `docs/acceptance/BADGEFORGE-FASE-5.md`; `CLAUDE.md` (fase atual) no fim.

## Testes

- **Unit (Pest):** `EndpointUrlGuardTest` (https, porta, userinfo, host,
  tamanho, caracteres, IP literal), `IpAddressClassifierTest` (IPv4/IPv6,
  mapeado, CIDRs), `EndpointTargetResolverTest` (todos públicos, IPv4
  preferido), `EndpointPayloadParserTest` (schema, campos ignorados, truncar),
  `EndpointHttpClientTest` (`options()` com `CURLOPT_RESOLVE`, `NOPROXY`,
  `PROTOCOLS`, sem redirect), `EndpointCacheKeyTest` (hash, sem URL).
- **Feature:** `EndpointBadgeEndpointTest` (9 casos de cache/fallback/TTL,
  404, PNG/ETag/download, rota estática vencendo), `EndpointManagementTest`
  (criar/renomear/excluir, Free e pago, 422 de SSRF/schema, rede tolerada, URL
  fora das props), `EndpointRateLimitTest` (300/3000/60, 429 com envelope e
  headers), `EndpointUrlLeakTest` (log, props, flash e cache sem URL),
  atualizações em `BadgeForgeToolPropsTest`, `BadgeForgePageTest`,
  `BadgeForgeAccountPropsTest`, exportação/anonimização,
  `TranslationsParityTest`.
- **Vitest:** `badgeforge-endpoint.test.ts` (painel, diálogo, CTA, cópia,
  URL pública, free/pago) + atualização de fixture/contrato.

`Http::fake` não exercita `CURLOPT_RESOLVE`; por isso o teste unitário de
`options()` e o smoke real são obrigatórios.

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Renomear config, seção `endpoint`, migração, model, factory, relação, testes de config |
| E2 | `SafeEndpointUrl`, guard, classifier, resolver + unit |
| E3 | Client pinado, parser, exceptions + unit |
| E4 | Cache, métricas, composer, controller, rota pública + feature |
| E5 | Rate limiter por dono + feature de 429 |
| E6 | Requests/actions/controller de gestão + feature (sem URL em props/log/flash) |
| E7 | Props, fixture, tipos, painel/diálogo/CTA, i18n, Vitest |
| E8 | ADR-083/084, SPEC, API.md, OpenAPI 1.5.0, docs page, glossário |
| E9 | Aceite, relatório, gates completos, smoke real e parada para revisão |

Gate local em cada etapa: `composer test` (Pint + PHPStan + Pest) e, quando
tocar TS, `npm run test` + `npm run types:check`. No E9, rodar todos:
`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`,
`npm run build`.

## Verificação e smoke

1. `php artisan migrate` e `migrate:rollback` (migração isolada).
2. `php artisan route:list --path=api/badges` e `--path=badgeforge/endpoints`.
3. Smoke real (`php artisan serve`, sem mocks):
   - URL shields pública (ex.:
     `https://raw.githubusercontent.com/ibrahimcesar/react-lite-youtube-embed/main/.github/badges/size-es.json`)
     → 200 SVG, `MISS` e depois `HIT`; PNG; `ETag` → 304.
   - SSRF: `127.0.0.1`, `169.254.169.254`, `10.0.0.1`, `:8443`,
     `user:pass@`, `http://` e destino que redireciona → 422 na criação.
   - Schema inválido (`https://api.github.com/repos/...`) → 422; host fora do
     ar → cria e serve fallback `unavailable`.
   - Limite: subir o servidor com `BADGEFORGE_ENDPOINT_LIMIT_FREE=5` (e
     anônimo pequeno) e confirmar 429 `rate_limited` + `X-RateLimit-*` /
     `Retry-After`; paid 3000 coberto por feature.
   - `EndpointHttpClient::options()` no Tinker mostrando `CURLOPT_RESOLVE`
     com o IP público resolvido.
   - `storage/logs/laravel.log`, props Inertia e flash sem a URL de origem;
     exportação contém só o que foi decidido.
4. `git status` revisado; **nenhum commit ou push** (regra do CLAUDE.md).

## Riscos e mitigações

- Colisão da chave `endpoint` → tratada no E1, antes de qualquer outra coisa.
- `Http::fake` não cobre pinagem → unit de `options()` + smoke real.
- Proxy de ambiente furando a pinagem → `proxy=''` + `CURLOPT_NOPROXY=*`.
- Handler sem curl → falha fechada (`require_curl`).
- IPv6 em `CURLOPT_RESOLVE` → endereço entre colchetes.
- CNAME/múltiplos IPs → todos precisam ser públicos; escolha IPv4.
- Corpo sem `Content-Length` → leitura limitada.
- 429 compartilhado pelo dono → CDN e cache absorvem; limite configurável.
- Consulta de plano por request → aceitável; sem cache para não atrasar
  downgrade/upgrade.
- URL na exportação → decisão explícita (dado do dono); nunca em props/log.
- Wayfinder precisa ser regenerado antes de `types:check`.

## Fora de escopo

Credenciais/headers na origem, `cacheSeconds`/`refresh` públicos, estilo/logo
vindos do JSON, analytics, admin, edição da URL (excluir + criar), providers
novos da F6 e OpenAPI completo da F8.

## Regra final

Ao terminar: relatório em
`docs/claude-code/reports/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase5.md`,
plano em `docs/claude-code/plans/.../20261104_..._badgeforge_fase5.md`,
`plansfacts`/`surveyfacts` da skill, e parar para revisão — sem commit/push.
