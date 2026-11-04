# API BadgeForge

## Estática (F1/F2 — disponível)

```text
GET /api/badges/static/{label}/{message}.{svg|png}
```

A imagem é gerada e servida pelo próprio devtools, sem sessão e sem
autenticação. A rota fica no grupo `api`, fora do prefixo `v1`, e não tem
throttle próprio.

### Caminho

- `label` e `message`: texto do badge, sem barra. `_` vira espaço, `__` vira
  `_`; a barra é escrita `%252F` (dupla codificação: o router decodifica o path
  antes do match). `-` e `.` são literais.
- A extensão é obrigatória e vale `svg` ou `png`; outro valor (ou URL sem
  extensão) responde 404. Para o PNG, o caminho termina em `.../message.png` —
  não em `.svg.png`.
- O SVG é o formato canônico. O PNG é rasterizado na primeira requisição e
  depois servido do cache em disco (ver `ADR-067` e `ADR-077`).
- Texto acima de 100 caracteres é truncado com `…`; segmento vazio dá 404.

### Query

| Parâmetro | Padrão | Regra |
|---|---|---|
| `style` | `flat` | `flat`, `flat-square`, `plastic`, `for-the-badge`, `social`; outro valor → `flat` |
| `color` | `#9f9f9f` | cor inválida → padrão |
| `labelColor` | `#555555` | cor inválida → padrão |
| `logo` | — | slug embutido ou `u_<ULID>` de logo personalizado; desconhecido/apagado → badge sem logo |
| `logoColor` | `#ffffff` | cor inválida → padrão |
| `logoSize` | 10 (14 em `for-the-badge`) | clamp 8–20 |
| `logoPosition` | `left` | `left` ou `right`; outro → `left` |
| `link` | — | só `http`/`https`; outro → sem âncora |
| `cacheSeconds` | 86400 | inteiro com clamp 300–86400; inválido → padrão; no máximo 3600 com `logo=u_...` |
| `font` | pilha do estilo | `fira-code`, `libre-franklin` ou `space-grotesk`; outro valor → pilha do estilo |
| `download` | — | `1` acrescenta `Content-Disposition: attachment`; outro valor é ignorado |

Cores aceitam nomes CSS (148), apelidos de badge (`brightgreen`, `critical`…),
hex 3/4/6/8, `rgb()`, `rgba()`, `hsl()`, `hsla()` e `transparent`. Cores com
transparência saem como `fill` + `fill-opacity`.

O estilo `social` fixa as próprias cores (`#555555` e `#007ec6`): `color` e
`labelColor` não têm efeito nele.

### Logo personalizado (`?logo=u_<ULID>`, F3)

O prefixo `u_` é um namespace reservado: o catálogo embutido nunca usa esses
slugs. O endpoint de imagem **não exige autenticação** e continua stateless —
o ULID é a referência opaca, sem nome de usuário ou caminho de conta.

- O logo é um SVG multi-path sanitizado no upload: só `<svg>`, `<g>` e `<path>`
  com `viewBox`, `d`, `fill-rule` e `clip-rule`. O arquivo cru nunca é
  persistido nem servido (ADR-078).
- Slug malformado, ULID inexistente, logo apagado ou banco indisponível →
  **200 sem logo**, nunca 4xx. Badges já publicados continuam válidos.
- Badge com logo personalizado tem `cacheSeconds` limitado a
  `custom_logos.max_cache_seconds` (3600 s), para que renomear/excluir o logo
  apareça em no máximo 1 h. O teto normal (86400 s) vale para os embutidos.
- O ETag continua sendo o sha256 do corpo servido, então a mudança do logo
  muda o ETag naturalmente.

### Exemplos

```text
/api/badges/static/build/passing.svg
/api/badges/static/build/passing.svg?style=for-the-badge&logo=github
/api/badges/static/ci%252Fcd/passing.svg?color=brightgreen
/api/badges/static/vers%C3%A3o/1.0.0.svg
/api/badges/static/build/passing.png?font=space-grotesk
/api/badges/static/build/passing.svg?download=1
/api/badges/static/build/passing.png?download=1
```

### Resposta

- `200`: `Content-Type: image/svg+xml; charset=utf-8` ou `image/png`,
  `Cache-Control: public, max-age=<cacheSeconds>`, `ETag: "<sha256>"`.
- PNG: 1× natural (fundo transparente), reduzido proporcionalmente só quando a
  largura passa de 600 px (`badgeforge.png.max_width`). A rasterização exige a
  extensão `imagick`; o arquivo fica em
  `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png` e a segunda
  requisição do mesmo SVG lê do disco.
- `download=1`: `Content-Disposition: attachment; filename="<label>-<message>.<ext>"`
  com a variante RFC 5987 (`filename*=UTF-8''...`) para acentos.
- `304`: `If-None-Match` igual ao ETag; sem corpo e sem `Content-Type`.
- `404`: segmento vazio, extensão ausente ou diferente de `svg`/`png` (envelope
  `ApiError`).
- `503`: PNG pedido em servidor sem a extensão `imagick`; envelope
  `ApiError` com código `image_unavailable`.
- O documento SVG tem `role="img"`, `aria-label="label: message"` e `<title>`
  com o mesmo texto. Nada é registrado em log além de IDs e classes de
  exceção; o conteúdo do badge nunca entra no log da aplicação.

## Provider (F4 — entregue)

```text
GET /api/badges/{provider}/{definition}/{path}.svg
```

`{definition}` é a métrica (`stars`, `forks`, `issues`, `license`, `v`, `dm`,
`dt`, `pulls`) e `{path}` são as coordenadas do item no formato daquela
métrica. Exemplos:

```text
/api/badges/github/stars/natanfiuza/devtools.svg
/api/badges/npm/v/laravel.svg
/api/badges/packagist/dt/laravel/framework.svg
/api/badges/docker/pulls/library/nginx.svg
```

- **Item público** (repositório, pacote, imagem pública): o servidor consulta a
  API pública do provider de forma **anônima**; nenhum token global existe em
  `.env` ou config.
- **Item privado**: só para usuário pagador e só em badge pessoal. O PAT é do
  usuário, cifrado em repouso e vinculado ao badge; a URL pública leva apenas o
  ULID. Ver `ADR-068` e `docs/tutorial/`.
- Packagist não hospeda pacote privado: o acesso é sempre público; npm `dm`
  (contagem de downloads) também é sempre público.
- Cache padrão de 300 s (`badgeforge.providers.cache_ttl`), por URL resolvida:
  GitHub `stars`/`forks`/`issues`/`license` compartilham a mesma entrada (a URL
  da API é a mesma); npm `v`/`dm` e Packagist `dt`/`v` têm entradas separadas.
- `stale-if-error`: provider fora do ar, 429 ou timeout servem a última
  resposta boa com `Warning: 110` e `X-Badge-Cache: STALE`; sem resposta
  anterior, a imagem sai 200 com o rótulo da métrica e `unavailable`.
- Cosmético (`style`, `color`, `labelColor`, `logo`, `logoSize`, `font`,
  `download=1`, `png`) funciona igual à badge estática. `cacheSeconds` e
  `refresh` são **ignorados** nesta rota: o TTL é do servidor.
- Provider, definição ou path inválido/desconhecido responde **404** com o
  envelope `ApiError`. A rota estática continua vencendo: `/api/badges/static/...`
  nunca cai aqui.

## Endpoint JSON (F5 — entregue)

```text
GET /api/badges/endpoint/{ulid}.{svg|png}
```

A imagem é stateless e anônima, como a badge pessoal: o ULID é a única
identidade pública e a URL de origem **nunca** aparece nela. O cadastro é feito
pelo dono em `/dashboard/badgeforge` (nome + URL); ler o README não exige
sessão. ULID desconhecido responde **404** no envelope `ApiError` (ADR-083).

### Contrato de origem (shields v1)

O servidor busca a URL cadastrada e aceita **apenas** o núcleo shields v1:

```json
{
  "schemaVersion": 1,
  "label": "deploys",
  "message": "green",
  "color": "brightgreen",
  "labelColor": "blue"
}
```

- `schemaVersion` precisa ser exatamente `1`.
- `label` e `message` são strings obrigatórias e não vazias (após `trim`).
- `color`/`labelColor` são opcionais e viram **defaults**: a query
  (`?color=`, `?labelColor=`) vence.
- Precisam vir com `Content-Type: application/json` (ou `+json`) e corpo até
  `endpoint.max_body_bytes`; lista, escalar, JSON inválido, content-type
  diferente ou redirect são recusados.
- `logoSvg`, `namedLogo`, `style`, `cacheSeconds` e `link` do JSON são
  ignorados: cosméticos vêm da query e o TTL é do servidor.
- Cosmético (`style`, `logo`, `logoSize`, `font`, `download=1`, `png`) funciona
  como nas demais rotas. `?label=` sobrescreve o label da origem.

### Cache e falhas

- Chave `badgeforge:endpoint:v1:sha256(url canônica)` — a chave não revela host
  nem path, e dois cadastros da mesma origem compartilham a entrada.
- TTL fixo em `endpoint.cache_ttl` (300 s), limitado por `max_cache_ttl`
  (3600 s). `?cacheSeconds=` e `?refresh=1` são **ignorados**.
- `X-Badge-Cache: HIT|MISS|STALE`; quando a origem manda `ETag`, a revalidação
  usa `If-None-Match` e um `304` renova a entrada.
- Origem fora do ar, 429 ou timeout servem o último valor bom (`STALE` +
  `Warning: 110`); sem valor bom, a imagem sai **200** com o nome do endpoint e
  a mensagem `unavailable` — nunca 500.
- Uma URL guardada que não passa mais no guard responde **422** `unsafe_url`.

### SSRF

Antes de qualquer conexão: HTTPS na porta 443, sem userinfo, host ASCII válido,
sem redirect; resolução A+AAAA e **todos** os IPs precisam ser públicos;
conexão pinada no IP validado (`CURLOPT_RESOLVE`), proxy de ambiente
desabilitado (`CURLOPT_NOPROXY=*`, `proxy: ''`) e só `CURLPROTO_HTTPS`. Sem a
extensão `curl` o fetch falha fechado (`curl_unavailable`). Na criação, URL
insegura ou payload fora do contrato vira **422**
`validation.badge_endpoint.*`; falha de rede não bloqueia o cadastro
(ADR-084).

### Rate limit

Por **dono**, não por leitor: Free 300/min, pago 3000/min, e ULID
inválido/desconhecido cai no anônimo 60/min por IP. O 429 sai no envelope
`rate_limited` com `X-RateLimit-*` e `Retry-After`.

## Gestão da conta (F3 — web/Inertia, não é API pública)

Estas rotas são da área autenticada (sessão + CSRF, `auth, verified, active`);
não há API pública de gestão na F3.

| Método | Caminho | Ação |
|---|---|---|
| GET | `/dashboard/badgeforge` | página da conta (limites, logos, presets e badges pessoais) |
| POST | `/badgeforge/logos` | upload SVG + parse (multipart) |
| GET | `/badgeforge/logos/{ulid}` | JSON `{viewBox, paths}` — só o dono |
| PATCH | `/badgeforge/logos/{ulid}` | renomear |
| DELETE | `/badgeforge/logos/{ulid}` | excluir |
| POST | `/badgeforge/presets` | criar snapshot |
| PATCH | `/badgeforge/presets/{ulid}` | renomear |
| DELETE | `/badgeforge/presets/{ulid}` | excluir |
| POST | `/badgeforge/badges` | criar badge pessoal (só plano pago) |
| PATCH | `/badgeforge/badges/{ulid}` | renomear badge |
| PATCH | `/badgeforge/badges/{ulid}/credential` | definir/trocar o PAT da badge |
| POST | `/badgeforge/badges/{ulid}/refresh` | forçar nova geração (provider) |
| DELETE | `/badgeforge/badges/{ulid}` | excluir badge e arquivos |
| POST | `/badgeforge/endpoints` | cadastrar endpoint JSON (nome + URL de origem) |
| PATCH | `/badgeforge/endpoints/{ulid}` | renomear endpoint (a URL é imutável) |
| DELETE | `/badgeforge/endpoints/{ulid}` | excluir endpoint e esquecer a chave de cache |

Regras: escritas em `throttle:badgeforge-management` (o refresh tem limiter
próprio, `badgeforge-refresh`); registro de outra conta responde **404**; SVG
inválido responde **422** com `validation.custom_logo.<code>`; limites em
`config/badgeforge.php` (Free: 2 logos; pago: ilimitado; presets: ilimitados).
Os paths nunca viajam nas props da página — são buscados sob demanda no `GET` do
logo. Badge pessoal é imutável em provider/params: para mudar, crie outra e
exclua a antiga. O PAT é write-only e nunca volta na resposta.

A página pública `/{locale}/docs/badgeforge` documenta as quatro rotas de
imagem, as nove métricas, o fluxo de credencial e o contrato do endpoint JSON
nos cinco idiomas.

## Pessoal (F4 — entregue)

```text
GET /b/{username}/{ulid}.svg
```

Apenas usuários pagos criam. A URL carrega só o ULID opaco; o PAT de item
privado nunca aparece nela. A imagem é stateless (sem sessão/CSRF), devolve
`ETag`/`304` e `Cache-Control` com teto de 3600 s
(`badgeforge.personal_badges.max_cache_seconds`). `?refresh=1` é ignorado:
o dono força uma nova geração por `POST /badgeforge/badges/{ulid}/refresh`.

## Metadados e gestão (API pública — fases futuras)

A gestão de logos e presets na F3 é web/Inertia autenticada (seção acima),
não esta API. Os caminhos abaixo seguem planejados para as fases de
providers, analytics e OpenAPI completo:

```text
GET /api/badgeforge/providers
GET /api/badgeforge/styles
GET /api/badgeforge/logos
POST /api/badgeforge/logos
DELETE /api/badgeforge/logos/{id}
POST /api/badgeforge/badges
GET /api/badgeforge/badges
GET /api/badgeforge/analytics
GET /api/badgeforge/analytics/export?format=csv
```
