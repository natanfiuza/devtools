# Critérios de aceite — BadgeForge FASE 5 (endpoint JSON + SSRF + limite por dono)

Gerado em 2026-11-04.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16 + plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase5.md`)
+ ADR-083 e ADR-084.

## Escopo da fase

- Endpoint JSON: tabela `badge_endpoints`, cadastro do dono em
  `/dashboard/badgeforge`, URL pública stateless
  `GET /api/badges/endpoint/{ulid}.{svg|png}`, contrato shields v1, cache por
  URL canônica com HIT/MISS/STALE, revalidação por `ETag` e fallback 200 que
  nunca vira 500 (ADR-083).
- SSRF: HTTPS/443, sem userinfo/redirect, resolução A+AAAA com todos os IPs
  públicos, conexão pinada por `CURLOPT_RESOLVE`, proxy desabilitado e falha
  fechada sem curl; `LimitedResponseSink` corta o corpo mesmo sem
  `Content-Length` (ADR-084).
- Rate limit por dono: Free 300/min, pago 3000/min, ULID desconhecido/inválido
  no anônimo 60/min por IP, 429 no envelope `ApiError` (ADR-084).
- UI: painel e diálogo de endpoints na dashboard, CTA no builder, i18n nos
  cinco idiomas.
- Documentação: SPEC §§6–7/10–11/14–17, ADR-083/084, API.md, OPENAPI.md
  v1.5.0, página pública com seção “Endpoint JSON” e glossário.

Fora da fase: credenciais/headers na origem, `cacheSeconds`/`refresh` públicos,
estilo/logo vindos do JSON, analytics (F7), providers da F6 e OpenAPI 3.1 da
F8.

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | Migração aditiva cria `badge_endpoints` (ULID, FK em cascata, `name`, `url`, único por dono+nome, índice dono+created_at) sem quebrar o que existe | `php artisan migrate`/`migrate:rollback`; `EndpointManagementTest` |
| 2 | Guard da URL recusa esquema diferente de HTTPS, porta diferente de 443, userinfo, host inválido/`localhost`/sem ponto, tamanho e caracteres de controle; canonicaliza host/porta/path/query | `EndpointUrlGuardTest` |
| 3 | Classificador normaliza IPv4 mapeado em IPv6 e usa CIDRs da config; resolver exige **todos** os IPs públicos e prefere IPv4 | `IpAddressClassifierTest`, `EndpointTargetResolverTest` |
| 4 | Conexão usa `CURLOPT_RESOLVE`, `proxy: ''`, `protocols: ['https']`, sem redirect e sem `stream` (que desvia para o StreamHandler do Guzzle 8); sem curl falha fechado | `EndpointHttpClientTest` |
| 5 | Parser aceita só shields v1 (`schemaVersion === 1`, `label`/`message` não vazios) e ignora cosméticos do JSON; `color`/`labelColor` são defaults vencidos pela query | `EndpointPayloadParserTest`, `EndpointBadgeEndpointTest` |
| 6 | Cache usa `badgeforge:endpoint:v1:sha256(url canônica)`, sem host/path na chave; dois cadastros da mesma origem compartilham entrada | `EndpointBadgeEndpointTest` |
| 7 | Rota pública alterna MISS/HIT com `Cache-Control` fixo de 300 s, ignora `?cacheSeconds=`/`?refresh=1`, serve PNG/download e 304 por `If-None-Match` | `EndpointBadgeEndpointTest` |
| 8 | Falha de origem vira stale ou fallback 200 (`label` = nome do endpoint, `message` = `endpoint.unavailable_message`); store fora do ar degrada para fetch/fallback; lock concorrente não quebra | `EndpointBadgeEndpointTest` |
| 9 | ULID desconhecido ou extensão inválida responde 404; URL guardada que não passa mais no guard responde 422 `unsafe_url`; rota estática continua vencendo | `EndpointBadgeEndpointTest` |
| 10 | Criação valida URL/SSRF e amostra: insegura ou payload fora do contrato → 422; origem fora do ar, timeout, 429 ou DNS que não resolve não bloqueia | `EndpointManagementTest` |
| 11 | Gestão: Free cria; nome único por dono; renomear não muda a URL; excluir esquece a chave de cache; endpoint de outra conta responde 404; rotas sob `auth, verified, active` + `badgeforge-management` | `EndpointManagementTest` |
| 12 | A URL de origem não aparece em props, input antigo de flash, log ou chave de cache; `#[Hidden]` no model e `dontFlash(['credential','url'])` | `EndpointManagementTest`, `BadgeForgeAccountPropsTest` |
| 13 | Rate limit é do dono: Free 300, pago 3000, anônimo 60 por IP para ULID inválido/desconhecido, contando por dono e não por ULID; 429 com `X-RateLimit-*`, `Retry-After` e envelope `rate_limited` | `EndpointRateLimitTest` |
| 14 | Props do painel incluem `endpointBadges` sem `url`, limites `endpointBadges` e `management.canCreateEndpointBadges`; downgrade mantém lista somente leitura; `QueryException` devolve lista vazia | `BadgeForgeToolPropsTest`, `BadgeForgePageTest`, `BadgeForgeAccountPropsTest` |
| 15 | UI lista/preview/copia SVG e PNG, cria, renomeia e exclui; botão de criação respeita o plano; CTA do builder só para quem gerencia; nenhum texto fixo | `badgeforge-endpoint.test.ts`, `badgeforge-component.test.ts`, `docs-badgeforge.test.ts` |
| 16 | Docs em cinco idiomas com URL pública, contrato, TTL e limites vindos da config; meta/sitemap intactos | `BadgeForgeDocsPageTest`, `docs-badgeforge.test.ts`, `TranslationsParityTest` |
| 17 | SPEC, API.md, OPENAPI.md v1.5.0 e glossário refletem a entrega | inspeção de `docs/` |
| 18 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 19 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-04

- `composer test`: Pint ok, PHPStan 0 erros, Pest **1.210 testes / 20.359
  asserções**.
- `npm run test`: Vitest **949 testes / 73 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: 0 erros; 1.180 avisos de indentação pré-existentes.
- `npm run build`: concluído (aviso de chunk grande já conhecido).
- Rotas: `api/badges/endpoint/{badgeEndpoint}.{ext}` com
  `throttle:badgeforge-endpoint` e as três de gestão
  (`badgeforge/endpoints` store/update/destroy) sob o grupo autenticado.
- Smoke com rede real (`php artisan serve`, 2026-11-04):
  - Endpoint cadastrado com `https://cdn.jsdelivr.net/gh/ibrahimcesar/react-lite-youtube-embed@main/.github/badges/size-es.json`
    (shields v1, `application/json`): SVG **200**, `X-Badge-Cache: MISS` e
    depois `HIT`, `Cache-Control: max-age=300`, `ETag` → **304**, PNG
    **200 image/png** com `download=1`.
  - ULID desconhecido → **404** `not_found`; extensão `.gif` → **404**;
    URL guardada trocada para `http://` → **422** `unsafe_url`.
  - Host sem DNS → cadastro aceito e imagem **200** com
    `"down: unavailable"` e `max-age=60` (fallback).
  - SSRF na criação → **422** com motivo traduzido para `http://`,
    userinfo, porta 8443, `127.0.0.1`, `169.254.169.254`, `10.0.0.1` e
    `localhost`; `text/plain`, JSON fora do contrato e redirect → **422**.
  - Limites com `BADGEFORGE_ENDPOINT_LIMIT_FREE=10` e
    `BADGEFORGE_ENDPOINT_LIMIT_ANONYMOUS=2`: o terceiro request anônimo e o
    request seguinte ao limite do dono responderam **429** `rate_limited`
    com `X-RateLimit-Limit/Remaining/Reset` e `Retry-After`; a contagem por
    dono (não por ULID) ficou coberta pelo teste de feature.
  - `EndpointHttpClient::options()` no Tinker mostrou
    `cdn.jsdelivr.net:443:151.101.193.229`, `proxy: ''`,
    `protocols: ['https']` e redirects desligados.
  - `storage/logs/laravel.log`, props de `BadgeForgeToolProps` e chaves do
    store não contêm a URL de origem (a chave é
    `badgeforge:endpoint:v1:<sha256>`).
  - Rota estática continuou respondendo 200 para `/api/badges/static/...`.
  - Usuário e endpoints do smoke foram removidos e o cache foi limpo ao final.
- `git status` revisado; nenhum commit/push executado.

## Notas e desvios do plano

- **Bug real encontrado no smoke**: o Guzzle 8.2 escolhe o `StreamHandler`
  quando o request leva `stream: true`, e esse handler ignora as opções de
  curl — a pinagem nunca aconteceria e a criação falhava com
  `InvalidArgumentException`. Correções: o request não usa mais `stream`; o
  teto de corpo passou a ser um `LimitedResponseSink`; `proxy`, `protocols` e
  `allow_redirects` são opções do Guzzle (o 8 recusa `CURLOPT_NOPROXY`,
  `CURLOPT_PROTOCOLS` e `CURLOPT_FOLLOWLOCATION` no bloco `curl`).
- A ADR-084 foi atualizada com o bloco de opções realmente usado e com o
  motivo do sink próprio.
- O fallback sai como `X-Badge-Cache: MISS` (payload nulo + TTL curto), não
  como um quarto estado; a documentação foi corrigida para não prometer
  `FALLBACK`.
- Nenhum limite de quantidade de endpoints: “apenas cadastrados” é a regra e
  todo Free pode cadastrar; o limite de leitura é por dono.
- A URL de origem é imutável: trocar destino é excluir e cadastrar de novo,
  porque isso muda o cache e a identidade pública.
