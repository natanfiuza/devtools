# BadgeForge — Fase 5 (endpoint JSON + SSRF + limite por dono)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase5.md`
- Levantamento/plano da skill: `docs/survey/20261104_badgeforge_fase5_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase5_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-5.md`
- ADRs: `docs/adr/ADR-083-badgeforge-endpoint-json.md`,
  `docs/adr/ADR-084-badgeforge-ssrf-e-rate-limit.md`

## Objetivo

Entregar a F5 do SPEC-BADGEFORGE: o dono cadastra uma URL HTTPS que devolve
shields v1 e recebe uma URL pública opaca (`/api/badges/endpoint/{ulid}`)
consumível por `<img>` anônimo. O servidor passa a buscar um destino escolhido
pelo usuário, então a fase fecha o contrato de SSRF (validar URL, resolver DNS,
exigir todos os IPs públicos, pinar a conexão) e define o limite de leitura por
dono. A URL de origem nunca sai do servidor: não aparece em props, flash, log,
exceção, exportação legível ou chave de cache.

Decisões da sessão de grill: contrato **núcleo shields v1**; identidade **ULID
persistido**; **IP público + DNS pin**; rate limit **por dono**; UI no painel da
dashboard com CTA no builder; criação **valida SSRF/schema e tolera rede**;
cosméticos continuam na query; TTL **300 s fixo** (query ignorada).

## Resumo executivo

- `GET /api/badges/endpoint/{ulid}.{svg|png}` serve a badge de um endpoint
  cadastrado, com cache por URL canônica (`badgeforge:endpoint:v1:sha256`),
  HIT/MISS/STALE, `ETag`/304, PNG, `download=1` e fallback **200** — nunca 500.
- A origem precisa devolver `application/json` (ou `+json`) com
  `schemaVersion === 1` e `label`/`message` não vazios; `color`/`labelColor`
  do JSON são defaults e a query vence.
- SSRF: HTTPS/443, sem userinfo, sem fragmento, host ASCII, sem `localhost` ou
  host sem ponto; A+AAAA resolvidos e **todos** os IPs precisam ser públicos;
  conexão pinada por `CURLOPT_RESOLVE`, `proxy: ''`, `protocols: ['https']`,
  redirects desligados e falha fechada sem curl.
- O teto de `max_body_bytes` é aplicado durante o download por um
  `LimitedResponseSink`, inclusive em resposta chunked sem `Content-Length`.
- Rate limit é do **dono**: Free 300/min, pago 3000/min; ULID inválido ou
  desconhecido cai no anônimo 60/min por IP; 429 no envelope `rate_limited`
  com `X-RateLimit-*` e `Retry-After`.
- Gestão na dashboard (`/dashboard/badgeforge`): criar, renomear (URL
  imutável) e excluir; a URL de origem não entra em props nem no `old()` de
  um 422. Free pode cadastrar; no downgrade a lista continua somente leitura.
- Página pública de docs ganhou a seção “Endpoint JSON” nos cinco idiomas, com
  URL, contrato, TTL e limites vindos da config.
- Gates finais: Pint/PHPStan ok, Pest **1.210 testes / 20.359 asserções**,
  Vitest **949 testes / 73 arquivos**, `types:check` limpo, `lint:check` 0
  erros, `build` concluído.
- O smoke real encontrou um bug de produção que `Http::fake` não pegava: o
  Guzzle 8 desviava o request para o `StreamHandler` e descartava a pinagem
  (ver “Desvios”). Corrigido e revalidado com rede real.

## Decisões (ADR-083 e ADR-084)

### ADR-083 — badge de endpoint JSON

1. Identidade pública é ULID persistido em `badge_endpoints`; a URL de origem
   é imutável (trocar destino = excluir e cadastrar de novo).
2. Contrato é o núcleo shields v1; `logoSvg`, `namedLogo`, `style`,
   `cacheSeconds` e `link` do JSON são ignorados; cosméticos vêm da query.
3. Cache por URL canônica com chave hash, condicional por `ETag`, stale
   utilizável e fallback curto.
4. TTL de resposta fixo em `endpoint.cache_ttl` (300 s); `?cacheSeconds=` e
   `?refresh=1` são ignorados, como no provider.
5. Criação valida URL/SSRF, resolve DNS e busca uma amostra; insegurança ou
   contrato inválido → 422; rede fora/timeout/429/DNS sem resposta → cadastro
   segue.
6. Falha no serve → STALE (com `Warning: 110`) ou fallback 200; store fora do
   ar degrada para fetch direto; lock concorrente não quebra.

### ADR-084 — SSRF e rate limit

1. Guard recusa esquema ≠ HTTPS, porta ≠ 443, userinfo, fragmento, host
   inválido/`localhost`/sem ponto, tamanho e caracteres de controle, e
   canonicaliza host/porta/path/query.
2. Classificador usa CIDRs da config, normaliza IPv4 mapeado em IPv6 e exige
   global unicast; resolver exige **todos** os IPs públicos e prefere IPv4.
3. Conexão pinada no IP validado via `CURLOPT_RESOLVE`; proxy desabilitado e
   redirects recusados na borda; sem curl falha fechado.
4. `LimitedResponseSink` corta o corpo no download mesmo sem `Content-Length`.
5. Limite por dono com decisão feita no limiter, que roda antes do binding e
   recebe o ULID cru; sem cache de plano para upgrade/downgrade valerem na
   hora.
6. “Anônimo” no SPEC significa leitor sem conta; o limite é do dono do
   endpoint, não do IP de quem lê o README.

## O que foi entregue, por etapa

| Etapa | Entrega |
|---|---|
| E1 | config `static_endpoint` renomeado + seção `endpoint`, migração `badge_endpoints`, `BadgeEndpoint` (ULID, `#[Hidden]`), factory, relação em `User` |
| E2 | `SafeEndpointUrl`, `EndpointUrlGuard`, `EndpointUrlException`, `IpAddressClassifier`, `HostResolver`/`DnsHostResolver`, `EndpointTargetResolver`, `ResolvedEndpoint` |
| E3 | `EndpointHttpClient`, `EndpointFetchRequest/Result/Exception/Reason`, `EndpointPayloadParser`, `LimitedResponseSink`, `ResponseBodyTooLarge` |
| E4 | `EndpointCache`, `EndpointCacheKey`, `CachedEndpointPayload`, `EndpointMetrics(Result)`, `EndpointBadge(Composer)`, `EndpointBadgeController`, rota pública |
| E5 | `RateLimiter::for('badgeforge-endpoint')`, throttle na rota, sem namespace próprio de exceção |
| E6 | `Store/UpdateBadgeEndpointRequest`, `Create/Update/DeleteBadgeEndpoint`, `EndpointManagementController`, rotas, `dontFlash(['url'])`, exportação/anonimização |
| E7 | props `endpointBadges`/`limits`/`management.canCreateEndpointBadges`, fixture, tipos TS, `EndpointBadgesPanel`, `EndpointBadgeDialog`, CTA no builder, i18n 5 idiomas |
| E8 | ADR-083/084, SPEC §§6–7/10–11/14–17, `API.md`, `OPENAPI.md` v1.5.0, seção na página de docs, glossário |
| E9 | Aceite, relatório, gates completos, smoke real, `CLAUDE.md` e parada para revisão |

## Arquivos principais

### PHP

- `app/Http/Controllers/BadgeForge/EndpointBadgeController.php`
- `app/Http/Controllers/BadgeForge/EndpointManagementController.php`
- `app/Actions/BadgeForge/{Create,Update,Delete}BadgeEndpoint.php`
- `app/Http/Requests/BadgeForge/{Store,Update}BadgeEndpointRequest.php`
- `app/Models/BadgeEndpoint.php`
- `app/Support/BadgeForge/Endpoint/*` (guard, classifier, resolver, cliente,
  parser, cache, métricas, composer, sink limitado)
- `app/Support/BadgeForge/BadgeForgeToolProps.php`
- `app/Providers/BadgeForgeServiceProvider.php`
- `database/migrations/2026_11_04_000001_create_badge_endpoints_table.php`
- `database/factories/BadgeEndpointFactory.php`
- `bootstrap/app.php`, `routes/api.php`, `routes/badgeforge.php`

### TypeScript/Vue

- `resources/js/tools/badgeforge/EndpointBadgesPanel.vue`
- `resources/js/tools/badgeforge/EndpointBadgeDialog.vue`
- `resources/js/tools/badgeforge/Tool.vue` (CTA)
- `resources/js/pages/dashboard/BadgeForge.vue`
- `resources/js/pages/BadgeForgeDocs.vue`
- `resources/js/tools/badgeforge/types.ts`

### Docs

- `docs/adr/ADR-083-badgeforge-endpoint-json.md`,
  `docs/adr/ADR-084-badgeforge-ssrf-e-rate-limit.md`
- `docs/SPEC-BADGEFORGE.md`, `docs/glossario.md`, `CLAUDE.md`
- `docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md` (v1.5.0)
- `docs/acceptance/BADGEFORGE-FASE-5.md`

## Testes

### Pest (F5)

- Unit: `EndpointUrlGuardTest`, `IpAddressClassifierTest`,
  `EndpointTargetResolverTest`, `EndpointPayloadParserTest`,
  `EndpointHttpClientTest`.
- Feature: `EndpointBadgeEndpointTest` (cache/fallback/TTL, 404, PNG/ETag/
  download, stale, revalidação, `unsafe_url`, DNS, store fora, lock, rota
  estática), `EndpointManagementTest` (gestão, SSRF na criação, tolerância de
  rede, unicidade de nome, URL fora do flash, 404 de outra conta, esquece o
  cache), `EndpointRateLimitTest` (Free, pago, anônimo, contagem por dono).
- Atualizações em `BadgeForgeToolPropsTest`, `BadgeForgePageTest`,
  `BadgeForgeAccountPropsTest`, `BadgeForgeDocsPageTest`,
  `TranslationsParityTest`, exportação/anonimização.

### Vitest (F5)

- `badgeforge-endpoint.test.ts` — painel, diálogo, CTA, cópia SVG/PNG,
  renomear/excluir, free/pago e URL pública.
- Atualizações em `badgeforge-component.test.ts`, `badgeforge-account.test.ts`,
  `docs-badgeforge.test.ts` e fixture `tool-props.json`.

## Gates (2026-11-04)

```text
composer test        Pint ok · PHPStan 0 erros · Pest 1.210 testes / 20.359 asserções
npm run test         Vitest 949 testes / 73 arquivos
npm run types:check  limpo (Wayfinder regenerado antes do vue-tsc)
npm run lint:check   0 erros · 1.180 avisos pré-existentes de indentação
npm run build        concluído
```

### Rotas

```text
GET    api/badges/endpoint/{badgeEndpoint}.{ext}   badges.endpoint
GET    api/badges/static/{label}/{message}.{ext}  badges.static
GET    api/badges/{provider}/{definition}/{path}  badges.provider
POST   badgeforge/endpoints                       badgeforge.endpoints.store
PATCH  badgeforge/endpoints/{badgeEndpoint}       badgeforge.endpoints.update
DELETE badgeforge/endpoints/{badgeEndpoint}       badgeforge.endpoints.destroy
```

## Smoke com rede real

Servidor local (`php artisan serve`, 2026-11-04), sem mocks:

- Origem `https://cdn.jsdelivr.net/gh/ibrahimcesar/react-lite-youtube-embed@main/.github/badges/size-es.json`
  (shields v1, `application/json`) cadastrada como endpoint: SVG **200** com
  `X-Badge-Cache: MISS` e depois **HIT**, `Cache-Control: max-age=300`;
  `If-None-Match` → **304**; PNG **200 image/png**; `download=1` com
  `Content-Disposition`.
- ULID desconhecido → **404** `not_found`; extensão `.gif` → **404**; URL
  guardada alterada para `http://` → **422** `unsafe_url`.
- Host sem DNS → cadastro aceito e imagem **200** com `down: unavailable`,
  `max-age=60` (fallback).
- Matriz de SSRF na criação → **422** com motivo traduzido: `http://`,
  `user:pass@`, porta 8443, `127.0.0.1`, `169.254.169.254`, `10.0.0.1`,
  `localhost`; `text/plain`, JSON fora do contrato e redirect → **422**.
- Limites com `BADGEFORGE_ENDPOINT_LIMIT_FREE=10` e
  `BADGEFORGE_ENDPOINT_LIMIT_ANONYMOUS=2`: terceiro request anônimo e o
  seguinte ao limite do dono → **429** `rate_limited` com
  `X-RateLimit-Limit/Remaining/Reset` e `Retry-After`.
- `EndpointHttpClient::options()` no Tinker: `cdn.jsdelivr.net:443:151.101.193.229`,
  `proxy: ''`, `protocols: ['https']`, redirects desligados.
- `storage/logs/laravel.log`, props Inertia e chaves do store sem a URL de
  origem; a chave é `badgeforge:endpoint:v1:<sha256>`.
- `/api/badges/static/...` continuou 200 (precedência preservada).
- Usuário e endpoints do smoke removidos e cache limpo ao final.

## Desvios e problemas encontrados

1. **Guzzle 8 ignorava a pinagem (corrigido, bug real de produção).** O
   request levava `stream: true`; com isso o Guzzle escolhe o `StreamHandler`
   em vez do handler curl e **descarta** todas as `CURLOPT_*` — a conexão
   nunca seria pinada e a criação falhava com
   `Passing the "curl" request option to the stream handler is not supported`.
   Correção: remover `stream`, usar `LimitedResponseSink` para o teto de corpo
   e mapear a exceção do sink para `too_large`. Revalidado com rede real.
2. **`CURLOPT_NOPROXY` recusado pelo Guzzle 8.** O cliente exige a opção
   `proxy` (string) no lugar; `proxy: ''` desliga proxy explícito e o
   `HTTP(S)_PROXY` do ambiente.
3. **`CURLOPT_PROTOCOLS` e `CURLOPT_FOLLOWLOCATION` recusados.** Trocados por
   `protocols: ['https']` e `allow_redirects: false`; só `CURLOPT_RESOLVE`
   permanece no bloco `curl`. ADR-084 e o teste unitário registram o bloco
   real.
4. **`FALLBACK` não existe como estado.** O fallback sai como
   `X-Badge-Cache: MISS` com payload nulo e TTL curto; OPENAPI e ADR foram
   corrigidos para não prometer um quarto valor.
5. **Fixture do smoke com `text/plain`.** `raw.githubusercontent.com` serve o
   JSON como `text/plain`, que o cliente recusa corretamente; o smoke usou
   `cdn.jsdelivr.net`, que serve `application/json`.
6. **Colisão de config `endpoint`.** O valor antigo
   `'/api/badges/static'` virou `static_endpoint`; a chave `endpoint` no
   payload de props foi preservada para não quebrar fixture/TS.
7. **Nome duplicado no smoke.** `users_email_unique` após uma criação falha
   (linha soft-deleted) exigiu `forceDelete` antes de recriar; artefato do
   ambiente, não do código.

## Riscos residuais

- `CURLOPT_RESOLVE` não é exercitado por `Http::fake`; fica coberto pelo teste
  unitário de `options()` e pelo smoke real.
- Origens que redirecionam legitimamente (CDNs, `github.com` → `raw.`) não
  servem; o dono precisa cadastrar a URL final.
- IPv6-only em rede que não alcança o endereço escolhido cai em fallback.
- Cada MISS faz 2–3 queries (endpoint, dono, plano); o CDN e o cache absorvem.
- O CDN pode segurar uma badge excluída até o TTL de 300 s.
- Mudança de contrato do JSON da origem vira stale/fallback, não erro visível.

## Próximos passos (fora desta fase)

- F6: providers Codecov/Coveralls/Laravel/PHP/Python.
- F7: providers customizados, analytics/`badge_asset_views` e admin.
- F8: OpenAPI 3.1 completo e hardening.

## Git

- Nenhum commit ou push foi executado (regra do `CLAUDE.md`).
- `git status` revisado; as mudanças ficam para o usuário revisar e commitar
  pelo `gitpr`.
