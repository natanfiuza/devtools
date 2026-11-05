# ADR-089: BadgeForge F8 — hardening da superfície pública

Status: Aceito
Data: 2026-11-05
Relacionados: ADR-052 (headers e CSP), ADR-071 (documentação pública e OpenAPI), ADR-076 (cache e endpoint stateless), ADR-081 (badges pessoais), ADR-083 (endpoint JSON), ADR-084 (SSRF e rate limit), ADR-087 (providers customizados), ADR-088 (API de gestão)

## Contexto

A F8 do SPEC §16 pede "hardening" sem definir o que ele significa. O
levantamento da fase encontrou cinco lacunas concretas na superfície que já
está pública:

1. `/api/badges/static`, `/api/badges/{provider}` e `/b/{username}/{ulid}` não
   têm throttle nenhum; um raspador troca o CDN e passa a usar o site como
   gerador de SVG.
2. O SVG é servido como documento: sem CSP própria, um SVG aberto direto no
   navegador (não em `<img>`) executa script com a política do site.
3. A falha de infraestrutura não é uniforme: endpoint e provider custom viram
   **500** quando o banco cai, `/b/` virava **404 de corpo vazio** e só o PNG
   sem Imagick tinha envelope.
4. O `badgeforge-management` responde 429 com toast Inertia mesmo no export
   CSV de analytics, que é um GET de download e nunca renderiza a página.
5. `/up` só provava que o PHP respondeu: banco, cache e disco do PNG podiam
   estar fora sem o balanceador perceber.

## Decisão

### 1. Cap de imagens por IP, separado dos limites por dono

Novo rate limiter `badgeforge-images` em static, provider e `/b/`, com
`badgeforge.serving.rate_limit.per_minute`
(`BADGEFORGE_SERVING_LIMIT_PER_MINUTE`, default **6000/min**) e decay de 60 s,
chaveado por IP. O número é alto de propósito: um README popular atrás de
GitHub Camo ou de proxy compartilhado concentra muitos leitores no mesmo IP.

Endpoint JSON e provider customizado **mantêm** o limite por dono da F5/F7
(Free 300/min, pago 3000/min, anônimo 60/min por IP). O cap de imagens não os
substitui; eles já tinham throttle e o eixo dele é o dono, não o leitor.

O 429 sai no envelope `rate_limited` com `X-RateLimit-*` e `Retry-After`.

### 2. CSP fechada no SVG, independente do `CSP_MODE`

Toda resposta com `Content-Type: image/svg+xml` recebe
`Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`, com
`style-src` inline porque a apresentação é por atributo/estilo embutido. A
política da aplicação (nonce, GA, Turnstile) **não** é aplicada à imagem, e a
imagem não obedece a `report-only`/`off` do site: a regra vale sempre.

### 3. Superfície de erro uniforme: 404 é recurso, 503 é infraestrutura

Nas três rotas de imagem com estado (endpoint, custom e `/b/`):

- Recurso inexistente, ULID inválido ou dono errado → **404** `not_found` no
  envelope `ApiError`. Um 404 é cacheável pelo CDN como "não existe" de
  propósito.
- `QueryException` (banco ou infraestrutura fora) → **503**
  `badge_unavailable`, com `Retry-After: 60` e `Cache-Control: no-store`, para
  CDN e `<img>` não guardarem o erro no lugar da imagem.
- PNG pedido em servidor sem Imagick continua **503** `image_unavailable`.

O mapeamento é só `QueryException`, escopado em `api/badges/*` e `b/*`; não
existe `catch (Throwable)`. Endpoint e custom continuam com a regra de que
falha da **origem** vira STALE/fallback 200: o 503 é para a nossa
infraestrutura, não para a de terceiros.

### 4. `/b/` no envelope, inclusive pelo fallback do site

O `render` de exceções passa a cobrir `b/*`, e o `Route::fallback` do site
(que renderiza a página Inertia de erro) deixa o caminho `b/*` seguir para o
handler. Sem isso, um 404 de imagem saía como HTML — e um `<img>` não tem o
que fazer com HTML.

### 5. 429 do management segue o método, não o `expectsJson()`

O limiter `badgeforge-management` divide a recusa:

- GET (export CSV de analytics) → **429 JSON** `rate_limited` com os headers
  do throttle;
- POST/PATCH/DELETE (ações de diálogo) → toast Inertia como antes.

Detectar por `expectsJson()` quebraria os testes e o fluxo real das ações:
o navegador manda `Accept: text/html` no Inertia e precisa do toast.

### 6. `/up` com dependências reais

A rota do framework é substituída por `HealthController` + `HealthProbe`, que
checam:

- `database`: `select 1`;
- `cache`: ida e volta de uma chave efêmera;
- `storage`: escrita e leitura no diretório do cache de PNG.

Saudável → **200** `{"status":"up","checks":{...}}`; qualquer falha → **503**
`{"status":"down","checks":{...}}`, com `Cache-Control: no-store`. O corpo
nunca carrega a exceção original, o `SQLSTATE` nem caminho interno. A string
`health: '/up'` continua na configuração do framework para o modo manutenção
seguir isentando a rota.

### 7. Contrato e CI

A spec OpenAPI 3.1 é servida em `/openapi/badges.yaml` e um teste de contrato
bidirecional garante paridade rota ↔ spec (ADR-071). O workflow de CI roda os
gates da casa (`composer test`, `npm run test`, `types:check`, `lint:check`,
`build`, `ci:check`) e `composer audit` + `npm audit --audit-level=high`.
Advisory sem correção disponível vira exceção documentada no workflow, nunca
`continue-on-error` no gate.

## Consequências

### Positivas

- O CDN/GitHub Camo deixa de ser contornável por varredura simples, e o dono
  do endpoint/custom mantém o limite por dono que a F5/F7 desenharam.
- Um SVG aberto direto no navegador não executa script.
- Cliente de API distingue "não existe" (404) de "tenta de novo" (503, com
  `Retry-After`) sem parsear `message`; CDN não guarda o erro.
- `/up` serve para balanceador e para o runbook: diz **qual** dependência caiu
  sem vazar detalhe.

### Negativas / Trade-offs

- Proxies compartilhados podem atingir o cap de imagem; ele é configurável por
  ambiente, e o RUNBOOK documenta o sintoma.
- 503 + `Retry-After` faz o CDN não cachear a falha; uma indisponibilidade
  breve gera mais requisições de origem do que o 404 cacheado — é o preço de
  não envenenar a imagem.
- O `/up` faz I/O (banco, cache, disco) a cada chamada; se o monitorar
  agressivamente, ele vira carga. Uso previsto é balanceador/cron, não
  `curl` em loop.
- Dividir a recusa do management por método é uma regra implícita: um novo
  endpoint GET de download herda JSON; um novo POST de diálogo herda toast.

## Alternativas descartadas

- **Um único limiter por IP para todas as rotas de imagem**: apagaria o limite
  por dono do endpoint/custom, que é o eixo correto (o dono paga pelo consumo)
  e já estava testado.
- **CSP da aplicação também no SVG**: o nonce e os hosts do site não fazem
  sentido num documento de imagem; `default-src 'none'` é mais restritiva e
  não depende de configuração.
- **503 para tudo (inclusive recurso inexistente)**: faria o CDN retentar um
  id que nunca vai existir e não diferencia erro do cliente.
- **404 para falha de banco em `/b/`** (comportamento anterior): mascara
  incidente como "badge não existe" e envenena o cache.
- **`catch (Throwable)` no controller de imagem**: engoliria bug de código
  como se fosse indisponibilidade transitória.
- **`expectsJson()` no 429 do management**: o cliente Inertia manda
  `Accept: text/html`; o toast deixaria de funcionar.
- **Health check só com `select 1`**: banco verde com cache ou disco fora
  ainda derruba PNG e analytics; as três sondas são baratas.
