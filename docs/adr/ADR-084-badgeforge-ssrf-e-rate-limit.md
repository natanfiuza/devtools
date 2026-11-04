# ADR-084: SSRF e rate limit do endpoint JSON

Status: Aceito
Data: 2026-11-04
Relacionados: ADR-068 (acesso a providers), ADR-080 (camada de providers), ADR-083 (badge de endpoint JSON)

## Contexto

A F5 transforma uma URL escolhida pelo usuário em destino de fetch do
servidor. Sem defesa, o dono cadastra `http://169.254.169.254/...`,
`https://localhost/...` ou um host público que resolve para `10.0.0.1` e usa
a badge como sonda da rede interna — o cenário clássico de SSRF. Validar a
URL uma vez não basta: DNS pode mudar entre a validação e a conexão
(rebinding), e redirects podem trocar o destino depois do check.

O SPEC §11 também prometia 60/300/3000 por minuto para “anônimo/cadastrado/
pago”, mas a imagem é consumida por `<img>` sem sessão. Era preciso definir o
que “anônimo” significa quando a identidade pública é um ULID.

## Decisão

### 1. Validação da URL antes de qualquer conexão

`EndpointUrlGuard` recusa, com motivo traduzido:

- vazio, acima de `endpoint.max_url_length`, caractere de controle/espaço/
  `\` ou fragmento (`#`);
- `parse_url` inválido, esquema diferente de `https`;
- `user`/`pass` na URL (userinfo);
- host que não seja ASCII `[a-z0-9.-]` (aceita `xn--`), `localhost`, ou host
  sem ponto que não seja IP literal;
- porta ausente ou diferente de 443.

A URL é então canonicalizada: host minúsculo sem ponto final, porta omitida,
path vazio vira `/`, query preservada. A forma canônica é a chave de cache e
o que o banco guarda.

### 2. Resolução DNS e classificação de todos os IPs

`EndpointTargetResolver` resolve A e AAAA e exige que **todos** os endereços
sejam públicos; basta um privado para recusar. `IpAddressClassifier`
normaliza IPv4 mapeado em IPv6 (`::ffff:`) antes do teste e usa as listas
`endpoint.blocked_ipv4_cidrs`/`blocked_ipv6_cidrs` da config (loopback,
privadas, link-local, CGNAT, documentação, multicast, reserved, ULA...).
IPv6 exige global unicast (`require_ipv6_global_unicast`). IP literal é
classificado direto, sem resolver.

Resolve-se preferindo o primeiro IPv4; sem IPv4, o primeiro IPv6.

### 3. Conexão pinada no IP validado

O fetch usa `Http::withOptions()` com:

```php
'allow_redirects' => false,
'proxy' => '',
'protocols' => ['https'],
'curl' => [
    CURLOPT_RESOLVE => ["{$host}:443:{$ip}"], // IPv6 entre colchetes
],
```

O `CURLOPT_RESOLVE` fixa a conexão no IP que passou na classificação, então
um DNS rebinding entre o check e o connect não troca o destino. `proxy: ''`
desliga a seleção de proxy do Guzzle **e** o fallback para `HTTP(S)_PROXY` do
ambiente, que furaria a pinagem sem deixar rastro. No Guzzle 8, proxy,
redirects e protocolos são opções próprias do cliente e os `CURLOPT_*`
equivalentes (`NOPROXY`, `FOLLOWLOCATION`, `PROTOCOLS`) são recusados no bloco
`curl`; `protocols => ['https']` faz o papel do `CURLPROTO_HTTPS`. Redirects
são recusados na borda (`withoutRedirecting()` + `allow_redirects=false`): 3xx
vira `redirected`.

O Guzzle 8 escolhe o `StreamHandler` quando o request leva `stream: true`, e
esse handler ignora opções de curl; por isso a resposta usa um sink próprio
(`LimitedResponseSink`) em vez de `stream`, mantendo o handler curl e o teto
de `max_body_bytes` mesmo em resposta chunked sem `Content-Length`.

Sem `ext-curl` o cliente **falha fechado**: `endpoint.require_curl` manda
responder `curl_unavailable`, nunca cair num handler que não pina o IP. O
mesmo vale para timeout de conexão (`connect_timeout`) e de leitura
(`timeout`).

### 4. Falhas e o que elas significam

| Situação | Motivo | Na criação | No serving |
|---|---|---|---|
| 3xx | `redirected` | 422 | stale/fallback |
| 429 | `rate_limited` (+`Retry-After`) | tolerado | stale/fallback |
| 5xx/rede/timeout | `unavailable`/`timeout` | tolerado | stale/fallback |
| JSON fora do contrato/content-type | `invalid_payload` | 422 | stale/fallback |
| corpo acima do limite | `too_large` | 422 | stale/fallback |

A criação tolera falha de rede para não impedir o cadastro de uma origem
temporariamente fora; falha de **segurança ou contrato** é definitiva.

### 5. Rate limit por dono

`RateLimiter::for('badgeforge-endpoint')`:

- dono Free → `endpoint.rate_limit.free` (300/min);
- dono pago → `endpoint.rate_limit.paid` (3000/min);
- ULID inválido, desconhecido ou banco fora → `endpoint.rate_limit.anonymous`
  (60/min) por IP;
- janela `endpoint.rate_limit.decay_seconds` (60 s);
- chave `badgeforge-endpoint:user:<id>` ou `...:guest:<ip>`.

O `ThrottleRequests` roda **antes** do `SubstituteBindings`, então o limiter
recebe o ULID cru: ele valida com `Str::isUlid`, carrega o endpoint com o
dono e consulta o plano na hora. Não há cache do plano, para upgrade e
downgrade valerem imediatamente. Consultar o plano é tolerante: qualquer
`Throwable` cai no anônimo em vez de 500.

Sem `->response()` próprio: o `ThrottleRequestsException` é convertido pelo
`ApiError` no envelope `429 rate_limited` com `X-RateLimit-*` e
`Retry-After`.

“Anônimo” no SPEC significa **leitor sem conta**; o destino, porém, sempre
tem dono. O limite é do dono do endpoint, não do IP de quem lê o README — do
contrário um README popular derrubaria a imagem para todos.

## Consequências

### Positivas

- Rede interna, metadata e loopback ficam fora do alcance mesmo com DNS
  rebinding e redirect.
- Proxy de ambiente não fura a pinagem.
- Sem curl a rota degrada para fallback, nunca abre exceção.
- Um endpoint quente no limite do dono não afeta o Free nem o anônimo.

### Negativas / Trade-offs

- Cada MISS faz 2–3 queries para resolver a URL e o plano; o CDN e o cache
  absorvem. Não cachear o plano é deliberado.
- Origens que legitimamente redirecionam (CDNs, `github.com` → `raw.`) não
  servem; o dono precisa cadastrar a URL final.
- IPv6-only com rede que não alcança o endereço escolhido cai em fallback.
- `CURLOPT_RESOLVE` não é exercitado por `Http::fake`; a pinagem é coberta
  por teste unitário de `options()` e por smoke real.

## Alternativas descartadas

- **Blocklist só na URL**: host público que resolve para IP privado passaria;
  e DNS pode mudar depois do check.
- **Resolver e conectar sem pinar o IP**: janela de rebinding entre a
  decisão e o connect.
- **Seguir redirects com revalidação**: mais superfície, mais estados; uma
  badge não precisa de redirect para funcionar.
- **Limite global por IP**: um README popular estouraria o limite de todos
  os leitores e quebraria a imagem sem relação com o dono.
- **Cachear o plano do dono**: atrasaria a mudança de limite em upgrade e
  downgrade.
