# ADR-083: Badge de endpoint JSON

Status: Aceito
Data: 2026-11-04
Relacionados: ADR-002 (processamento local), ADR-068 (acesso a providers), ADR-072 (badge como imagem), ADR-076 (cache e endpoint stateless), ADR-080 (camada de providers), ADR-082 (docs públicas), ADR-084 (SSRF e rate limit)

## Contexto

O SPEC §6 descreve a F5 como `GET /api/badges/endpoint.{svg|png}?url=...`,
“apenas cadastrados”. As duas metades não convivem: uma URL que aceita o
destino no parâmetro é um proxy de fetch aberto, não uma badge de um endpoint
cadastrado. Quem consome a imagem é um `<img>` anônimo em README, então
também não há sessão para dizer “este leitor cadastrou este endpoint”.

Faltava decidir quatro coisas: onde mora a identidade pública, qual é o
contrato JSON aceito, como o resultado é cacheado e o que acontece quando a
origem cai. Reusar `ProviderMetrics`/`ProviderCache` não resolve: o provider
tem catálogo e payload projetado por métrica; o endpoint recebe **qualquer**
JSON do usuário e o contrato é outro.

## Decisão

### 1. Identidade persistida, URL opaca

Tabela aditiva `badge_endpoints`: `id` ULID, `user_id` obrigatório com FK em
cascata, `name` (80), `url` (2048), timestamps; único por `(user_id, name)` e
índice `(user_id, created_at)`. Não há coluna de segredo nem de payload: o
banco guarda apenas a URL canônica de origem.

A rota pública é `GET /api/badges/endpoint/{ulid}.{svg|png}`, stateless como
a badge pessoal (`/b/`). O ULID é a identidade: o leitor não autentica e o
cadastro acontece em `/dashboard/badgeforge`. ULID desconhecido responde 404
pelo `ApiError`; extensão fora de `svg|png` idem.

A URL de origem é `#[Hidden]` no model, nunca é selecionada nas props, entra
em `$exceptions->dontFlash(['url'])` e nunca vai para log, exceção, cache
legível ou cópia de flash. Exportação (`ExportUserData`) é a exceção
explícita: é dado do dono, baixado por ele.

### 2. Contrato shields v1

O corpo aceito é um objeto JSON, com `Content-Type: application/json` (ou
`+json`) e profundidade limitada por `endpoint.max_json_depth`:

```json
{
  "schemaVersion": 1,
  "label": "deploys",
  "message": "green",
  "color": "brightgreen",
  "labelColor": "blue"
}
```

- `schemaVersion` precisa ser exatamente `1`; versão futura é recusada.
- `label` e `message` são strings obrigatórias, não vazias depois do `trim`.
- `color`/`labelColor` são opcionais e, quando presentes, viram **defaults**:
  a query explícita (`?color=`, `?labelColor=`) vence, como no resto do builder.
- Os demais campos do formato shields (`logoSvg`, `namedLogo`, `style`,
  `cacheSeconds`, `link`) são ignorados: cosméticos vêm da query, e cache é
  do servidor.
- Lista, escalar, JSON inválido, corpo acima de `endpoint.max_body_bytes`,
  content-type fora do aceito e redirect viram `invalid_payload` na criação.

O payload projetado (`EndpointPayload`) carrega só label, message, color e
labelColor. Nenhum corpo de origem é persistido.

### 3. Cache por URL canônica, nunca por ULID

A chave é `badgeforge:endpoint:v1:` + `sha256(url canônica)`. Dois cadastros
da mesma URL compartilham a entrada, e a chave não revela host nem path.

`EndpointMetrics` responde HIT, MISS ou STALE com `Cache::lock` por chave:
no MISS resolve DNS e busca; com `ETag` da origem faz requisição condicional
e um `304` renova `fetched_at`; falha com entrada utilizável devolve STALE +
`Warning: 110`; sem entrada, fallback 200 com `label = name` do cadastro e
`message = endpoint.unavailable_message`. A imagem nunca responde 500 por
origem fora do ar. Cache store indisponível degrada para fetch direto e,
se este falhar, fallback.

### 4. Cache e query ignorados na rota pública

TTL configurado em `endpoint.cache_ttl` (300 s), limitado por
`endpoint.max_cache_ttl` (3600 s); `stale_ttl` guarda o último valor bom e
`fallback_ttl` é o TTL do fallback. `?cacheSeconds=` e `?refresh=1` são
ignorados, como na rota de provider: a origem é externa e o dono não deve
poder forçar fetch a cada leitura.

### 5. Ciclo de vida

- **Criar**: valida URL (ADR-084), resolve DNS e busca uma amostra. URL
  insegura ou payload fora do contrato → 422 `validation.badge_endpoint.*`.
  Origem fora do ar, timeout, 429 ou DNS que não resolve **não** bloqueiam:
  a imagem passa a servir stale/fallback até a origem voltar. Nenhum corpo é
  guardado.
- **Renomear**: só `name`. Trocar a URL muda a identidade e o cache; a saída
  é excluir e cadastrar de novo.
- **Excluir**: esquece `EndpointCacheKey::for(url)` e apaga a linha.
- **Exportar**: nome, URL de origem (dado do dono) e URLs públicas; sem corpo
  de origem.
- **Anonimizar**: passa pelo mesmo `DeleteBadgeEndpoint`, para a chave de
  cache sair junto com a linha.
- **Downgrade**: não afeta endpoint; não há limite de quantidade. Quem não
  é verificado ou está suspenso vê a lista sem poder cadastrar/renomear.

## Consequências

### Positivas

- Não existe proxy aberto: só destinos previamente cadastrados são buscados.
- O leitor continua anônimo (README não tem sessão) sem que a origem vaze.
- Cache compartilhado por URL canônica reduz fetch externo e absorve o donor.
- Falha de origem nunca quebra README: stale ou fallback 200.

### Negativas / Trade-offs

- Trocar a origem exige excluir e recriar (o ULID público muda).
- O cadastro faz um fetch síncrono de amostra; origem lenta adiciona latência
  ao submit (limitada pelos timeouts de `EndpointHttpClient`).
- O dono não controla o TTL; a atualização depende de cache/replay.
- O contrato é estrito: um serviço que devolve outro formato precisa de um
  passo intermediário que produza shields v1.

## Alternativas descartadas

- **`?url=` na rota pública (SPEC literal)**: proxy de fetch aberto e
  inconciliável com “apenas cadastrados”.
- **Guardar o corpo de origem para servir offline**: seria persistir dado de
  terceiro cujo formato e conteúdo não controlamos; o cache projetado já
  cobre stale.
- **Reusar `ProviderMetrics`/`ProviderCache`**: o provider tem definição,
  extração por métrica e payload projetado próprio; generalizar agora
  arriscaria a camada que a F4 estabilizou.
- **Chave de cache pelo ULID**: dois cadastros da mesma origem fariam fetch
  duplicado, e excluir um invalidaria o outro.
- **Aceitar redirect**: abriria a porta para o destino validado redirecionar
  a um IP interno (ADR-084).
