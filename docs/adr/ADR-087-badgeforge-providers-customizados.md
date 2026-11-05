# ADR-087: BadgeForge F7 — providers customizados

Status: Aceito
Data: 2026-11-04
Relacionados: ADR-068 (acesso a providers), ADR-080 (camada de providers), ADR-083 (endpoint JSON), ADR-084 (SSRF e rate limit), ADR-086 (analytics)

## Contexto

O catálogo de providers é global (`badge_providers` sem dono) e a rota pública
`/api/badges/{provider}/{definition}/{path}` é anônima. A F7 do SPEC §16 pede
que o usuário pagante crie o próprio provider, mas sem transformar o site em
executor de código: nada de `parser_class`, script do usuário ou credencial na
origem.

O provider customizado é a generalização do endpoint JSON da F5: em vez de um
contrato shields fixo, o dono mapeia campos de um JSON público para métricas.
Também compartilha o risco: URL fornecida pelo usuário pode apontar para rede
interna.

## Decisão

### 1. Só plano pago cria e edita

Criar/editar exige `badgeforge.paid` (403 no Free). O limite é
`custom_providers.limit` (default 10; `null` = ilimitado) e conta por dono.
Excluir continua permitido após downgrade.

### 2. Mapeamento declarativo, sem código

- `badge_custom_providers`: `user_id`, `name`, `url_template`,
  `path_params` (lista ordenada de placeholders), `example_path`,
  `metrics_version`, `enabled`.
- `badge_custom_provider_definitions`: `slug`, `label`, `pointer`
  (JSON Pointer RFC 6901) e `formatter` (`text|number|percent`).

O template usa placeholders `{nome}` e precisa declarar exatamente os mesmos
placeholders de `path_params`, na mesma ordem. O servidor apenas renderiza o
template com os segmentos do path (`rawurlencode`), busca o JSON e extrai cada
pointer. Não há expressão, script, header configurável nem credencial na
origem.

### 3. URL pública opaca e stateless

```text
GET /api/badges/custom/{ulid}/{definition}/{path}.svg|png
```

Quem tem o link renderiza; a gestão é só do dono. O ULID não é derivado do
template. A rota fica registrada **antes** da rota genérica de provider, e
`custom` entra em `ProviderCatalog::RESERVED_SLUGS` para nunca virar slug de
provider global. O provider customizado não aparece no catálogo, nos docs de
providers nem pode ser usado por badge pessoal na F7.

### 4. Template e conjunto de métricas são imutáveis

Trocar o template = excluir e recriar. Nome do provider, `label`, `pointer` e
`formatter` são editáveis; o `slug` da métrica é imutável. O conjunto de
métricas é fixo na criação: adicionar/remover métrica também exige recriar.
O update recusa `url_template` e `slug` (`prohibited`) e incrementa
`metrics_version`, que entra na chave de cache para um pointer alterado nunca
servir valor antigo.

### 5. Probe no cadastro com `example_path`

O dono informa um path concreto (por exemplo `natanfiuza/devtools`). O cadastro:
parseia os segmentos, renderiza a URL, aplica o guard da F5, resolve, busca,
decodifica o JSON e extrai cada pointer.

- URL insegura, redirect, JSON inválido/content-type errado, pointer ausente ou
  não escalar → **422** com mensagem em `validation.badge_custom_provider.*`.
- Timeout, DNS que não resolve, 5xx, 429 e curl ausente são **tolerados**: o
  cadastro salva e a imagem faz fallback.

O probe roda em transação com a criação: shape inválido não deixa provider
meio-criado.

### 6. Reuso integral do stack SSRF e do cache da F5

- `EndpointUrlGuard` + `EndpointTargetResolver` + `EndpointHttpClient`
  (conexão pinada por `CURLOPT_RESOLVE`, HTTPS/443, sem userinfo, sem redirect,
  todos os IPs públicos, fail-closed sem curl).
- Cache 300 s, stale 86400 s, fallback 60 s; só os escalares extraídos são
  cacheados, no namespace
  `badgeforge:custom:v1:{ulid}:{metricsVersion}:sha256(url)`.
- Rate limit por dono (pago 3000/min, Free 300/min) e 60/min anônimo por IP
  para ULID inválido/desconhecido.

**Qualquer** falha no serving (guard, resolve, fetch, JSON, pointer) vira
stale/fallback **200**, nunca 422/500. É a diferença deliberada em relação ao
endpoint da F5, que responde 422 para uma URL já guardada considerada insegura:
aqui o link é público e a imagem não pode quebrar.

### 7. Nada de origem fora do servidor

`url_template` é `#[Hidden]`, `dontFlash`, e nunca entra em props do dono,
builder, docs, CSV, log ou auditoria. **Exceção explícita:** o dono recebe o
`pointer` porque é ele quem edita as métricas, e o admin recebe template e
pointer na tela de custom providers para investigar — fora dela não circulam.
O dono pode desabilitar a própria origem? Não: só o admin desabilita. Desabilitar
faz a URL pública responder 404; o admin **não exclui**, porque o dado é do dono.

### 8. Downgrade

A URL continua servindo no limite Free. Criar/editar responde 403; excluir é
permitido; a lista continua visível para copiar e gerenciar. Espelha a badge
pessoal e o endpoint JSON.

## Consequências

### Positivas

- O usuário resolve o caso "meu JSON, minha métrica" sem código no servidor.
- O guard e o cliente pinado da F5 são reusados, não reimplementados; os testes
  da F5 continuam verdes.
- A imagem nunca vira 500 e o link público não revela a origem.
- `metrics_version` evita cache envenenado após edição de pointer.

### Negativas / Trade-offs

- Só serve escalares; objeto/array por métrica exigiria outro desenho.
- Métricas fixas na criação tornam o ciclo "trocar uma métrica" mais caro.
- O probe depende da origem estar respondendo no cadastro; shape errado só é
  descoberto no save (rede tolerada), então um JSON que muda depois pode levar
  a imagem ao fallback.
- O admin vê a origem por decisão explícita; a superfície de auditoria não a
  registra.

## Alternativas descartadas

- **Permitir código/parser do usuário**: execução remota disfarçada de feature.
- **Provider customizado no catálogo global**: misturaria origem de usuário com
  docs públicos e badge pessoal, além de colidir com o slug `custom`.
- **Template editável**: mudaria a URL de origem e a semântica do cache sem
  bump de versão; excluir e recriar é explícito.
- **Métricas adicionáveis depois**: exigiria versionar o conjunto e a resposta
  pública; o caso é raro o bastante para recriar.
- **Credencial/auth na origem**: transformaria o site em cofre de segredo sem
  contrato de revogação; a origem precisa ser pública.
- **422 no serving para URL insegura**: revelaria decisão de segurança no link
  público e quebraria a imagem; fallback 200 é a regra.
- **Permitir badge pessoal usar provider custom na F7**: adiado para depois da
  estabilização do contrato.
