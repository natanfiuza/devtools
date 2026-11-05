# OpenAPI BadgeForge (resumo)

A superfície das F1–F7 está implementada: badges estáticas, PNG/fontes, logos
personalizados (`logo=u_<ULID>`), badges de provider (nove providers, quinze
definições, incluindo cobertura e versões de runtime), badges pessoais, a
badge de endpoint JSON e a badge de provider customizado. O OpenAPI completo
em 3.1 é entregue na F8 (`ADR-071`). A gestão de logos, presets, badges
pessoais, endpoints e providers customizados é web/Inertia autenticada e não
faz parte desta API; analytics na F7 é superfície web + CSV (ADR-086).

```yaml
openapi: 3.1.0
info:
  title: BadgeForge API
  version: 1.7.0
paths:
  /api/badges/static/{label}/{message}.{ext}:
    get:
      summary: Static badge
      description: >
        Gera o SVG do badge ou o PNG rasterizado. Item público, sem sessão e
        sem autenticação. O PNG é cacheado em disco pelo sha256 do SVG.
      parameters:
        - name: label
          in: path
          required: true
          schema: { type: string }
        - name: message
          in: path
          required: true
          schema: { type: string }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
        - name: style
          in: query
          schema:
            type: string
            enum: [flat, flat-square, plastic, for-the-badge, social]
            default: flat
        - name: color
          in: query
          schema: { type: string, default: "#9f9f9f" }
        - name: labelColor
          in: query
          schema: { type: string, default: "#555555" }
        - name: logo
          in: query
          description: >
            Slug de logo embutido ou `u_<ULID>` de logo personalizado
            (namespace `u_` reservado). Desconhecido, malformado ou apagado é
            ignorado: o badge sai sem logo, 200.
          schema: { type: string }
        - name: logoColor
          in: query
          schema: { type: string, default: "#ffffff" }
        - name: logoSize
          in: query
          schema: { type: integer, minimum: 8, maximum: 20 }
        - name: logoPosition
          in: query
          schema: { type: string, enum: [left, right], default: left }
        - name: link
          in: query
          description: Envolve o SVG em <a>; só http/https.
          schema: { type: string, format: uri }
        - name: cacheSeconds
          in: query
          description: >
            Clamp 300–86400. Com `logo=u_...`, o teto cai para
            `custom_logos.max_cache_seconds` (3600).
          schema: { type: integer, minimum: 300, maximum: 86400, default: 86400 }
        - name: font
          in: query
          description: Fonte empacotada; desconhecida cai na pilha do estilo.
          schema:
            type: string
            enum: [fira-code, libre-franklin, space-grotesk]
        - name: download
          in: query
          description: "`1` responde como anexo (Content-Disposition)."
          schema: { type: string, enum: ["1"] }
      responses:
        "200":
          description: Badge image
          headers:
            Cache-Control:
              schema: { type: string, example: "public, max-age=86400" }
            ETag:
              schema: { type: string }
            Content-Disposition:
              description: Presente só com download=1; inclui filename* RFC 5987.
              schema: { type: string }
          content:
            image/svg+xml: {}
            image/png:
              description: 1x natural, transparente; largura máxima de 600 px.
        "304":
          description: Not modified (If-None-Match igual ao ETag)
        "404":
          description: Segmento vazio, extensão ausente ou diferente de svg/png
        "503":
          description: PNG pedido sem a extensão imagick (código image_unavailable)
  /api/badges/{provider}/{definition}/{path}.{ext}:
    get:
      summary: Dynamic provider badge
      description: >
        Lê a API pública do provider de forma anônima e responde sempre com
        uma imagem. A resposta do provider fica em cache por
        `providers.cache_ttl` (300 s por padrão); falha ou rate limit servem a
        última resposta boa (stale) e, sem ela, um fallback 200. Item privado
        não é servido por esta rota: use uma badge pessoal (ADR-081).
      parameters:
        - name: provider
          in: path
          required: true
          schema:
            type: string
            enum: [github, npm, packagist, docker, codecov, coveralls, laravel, php, python]
        - name: definition
          in: path
          required: true
          schema:
            type: string
            enum: [stars, forks, issues, license, v, dm, dt, pulls, coverage]
        - name: path
          in: path
          required: true
          description: >
            Coordenadas do item, no formato da definição — `{owner}/{repo}`,
            `{package}` (aceita escopo com `/`), `{vendor}/{package}` ou
            `{namespace}/{repository}`. Nas definições de versão de runtime
            (laravel, php e python) o valor é o literal `latest`; qualquer
            outro literal responde 404. Cobertura (codecov e coveralls) é o
            percentual inteiro da branch padrão.
          schema: { type: string }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
        - name: style
          in: query
          schema: { type: string, default: flat }
        - name: color
          in: query
          schema: { type: string }
        - name: labelColor
          in: query
          schema: { type: string }
        - name: logo
          in: query
          schema: { type: string }
        - name: logoColor
          in: query
          schema: { type: string }
        - name: logoSize
          in: query
          schema: { type: integer, minimum: 8, maximum: 20 }
        - name: logoPosition
          in: query
          schema: { type: string, enum: [left, right] }
        - name: link
          in: query
          schema: { type: string, format: uri }
        - name: font
          in: query
          schema: { type: string, enum: [fira-code, libre-franklin, space-grotesk] }
        - name: download
          in: query
          schema: { type: string, enum: ["1"] }
      responses:
        "200":
          description: Badge image (inclusive stale e fallback)
          headers:
            Cache-Control:
              schema: { type: string, example: "public, max-age=300" }
            ETag:
              schema: { type: string }
            X-Badge-Cache:
              description: HIT, MISS, STALE ou FALLBACK.
              schema: { type: string }
            Warning:
              description: '110 - "Response is Stale" quando a resposta é stale.'
              schema: { type: string }
          content:
            image/svg+xml: {}
            image/png: {}
        "304":
          description: Not modified (If-None-Match igual ao ETag)
        "404":
          description: Provider, definição ou path desconhecido/inválido
        "503":
          description: PNG pedido sem a extensão imagick
  /b/{username}/{ulid}.{ext}:
    get:
      summary: Personal badge image
      description: >
        Imagem stateless de uma badge pessoal (ADR-081). A URL leva apenas o
        username e o ULID; nenhuma credencial aparece nela. A imagem é
        regenerada quando `expires_at` vence; `?refresh=1` é ignorado — o
        dono força uma nova geração com `POST /badgeforge/badges/{ulid}/refresh`.
      parameters:
        - name: username
          in: path
          required: true
          schema: { type: string }
        - name: ulid
          in: path
          required: true
          description: ULID opaco da badge (não é o id público do banco).
          schema: { type: string }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
      responses:
        "200":
          description: Badge image
          headers:
            Cache-Control:
              schema: { type: string, example: "public, max-age=3600" }
            ETag:
              schema: { type: string }
          content:
            image/svg+xml: {}
            image/png: {}
        "304":
          description: Not modified
        "404":
          description: Badge inexistente ou username que não é o dono
        "503":
          description: PNG pedido sem a extensão imagick
  /api/badges/endpoint/{ulid}.{ext}:
    get:
      summary: JSON endpoint badge
      description: >
        Badge de um endpoint JSON previamente cadastrado pelo dono (ADR-083).
        A URL é stateless e anônima; o ULID é a única identidade pública e a URL
        de origem nunca aparece nela. A origem precisa devolver shields v1
        (`schemaVersion: 1`, `label`/`message` não vazios, `color`/`labelColor`
        opcionais como defaults). A resposta fica em cache por URL canônica
        (`endpoint.cache_ttl`, 300 s); `?cacheSeconds=` e `?refresh=1` são
        ignorados. Falha de origem vira STALE ou fallback 200, nunca 500. O
        fetch é pinado no IP público validado (SSRF, ADR-084), e o throttle é
        por dono do endpoint.
      parameters:
        - name: ulid
          in: path
          required: true
          description: ULID opaco do endpoint cadastrado.
          schema: { type: string, pattern: "^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$" }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
        - name: style
          in: query
          schema: { type: string, default: flat }
        - name: label
          in: query
          description: Sobrescreve o `label` devolvido pela origem.
          schema: { type: string }
        - name: color
          in: query
          description: Vence o `color` default da origem.
          schema: { type: string }
        - name: labelColor
          in: query
          description: Vence o `labelColor` default da origem.
          schema: { type: string }
        - name: logo
          in: query
          schema: { type: string }
        - name: logoColor
          in: query
          schema: { type: string }
        - name: logoSize
          in: query
          schema: { type: integer, minimum: 8, maximum: 20 }
        - name: logoPosition
          in: query
          schema: { type: string, enum: [left, right] }
        - name: link
          in: query
          schema: { type: string, format: uri }
        - name: font
          in: query
          schema: { type: string, enum: [fira-code, libre-franklin, space-grotesk] }
        - name: download
          in: query
          schema: { type: string, enum: ["1"] }
      responses:
        "200":
          description: Badge image (inclusive stale e fallback)
          headers:
            Cache-Control:
              schema: { type: string, example: "public, max-age=300" }
            ETag:
              schema: { type: string }
            X-Badge-Cache:
              description: HIT, MISS ou STALE. O fallback (sem valor bom) também sai como MISS.
              schema: { type: string }
            Warning:
              description: '110 - "Response is Stale" quando a resposta é stale.'
              schema: { type: string }
          content:
            image/svg+xml: {}
            image/png: {}
        "304":
          description: Not modified (If-None-Match igual ao ETag)
        "404":
          description: ULID desconhecido ou extensão diferente de svg/png
        "422":
          description: URL guardada não passa mais no guard de SSRF (código unsafe_url)
        "429":
          description: Rate limit do dono do endpoint (envelope rate_limited)
        "503":
          description: PNG pedido sem a extensão imagick
  /api/badges/custom/{ulid}/{definition}/{path}.{ext}:
    get:
      summary: Custom provider badge
      description: >
        Badge de um provider criado pelo usuário (ADR-087). O provider mapeia,
        de forma declarativa, campos de um JSON público para métricas via JSON
        Pointer (RFC 6901) e formatador `text|number|percent`; não há código,
        credencial ou header configurável do usuário. A URL é stateless e
        anônima; o ULID é a única identidade pública e o `url_template` nunca
        aparece nela, em props, CSV, log ou auditoria (só o dono vê o pointer e
        o admin vê template/pointer na própria tela). A resposta do provider
        fica em cache por `custom_providers.cache_ttl` (300 s), com stale de
        86400 s e fallback de 60 s; `?cacheSeconds=` e `?refresh=1` são
        ignorados. Diferente do endpoint da F5, qualquer falha no serving
        (guard, DNS, fetch, JSON, pointer) vira STALE ou fallback 200, nunca
        422 ou 500. O fetch é pinado no IP público validado (SSRF, ADR-084) e
        o throttle é por dono do provider.
      parameters:
        - name: ulid
          in: path
          required: true
          description: ULID opaco do provider customizado.
          schema: { type: string, pattern: "^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$" }
        - name: definition
          in: path
          required: true
          description: Slug de uma métrica declarada na criação do provider.
          schema: { type: string, pattern: "^[a-z0-9][a-z0-9-]*$" }
        - name: path
          in: path
          required: true
          description: >
            Segmentos do `path` no mesmo número e ordem de `path_params` do
            provider (ex.: `{owner}/{repo}`). Cada segmento aceita
            `[A-Za-z0-9._~-]{1,200}`; vazio, `.` e `..` são inválidos e a URL
            responde 404.
          schema: { type: string }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
        - name: style
          in: query
          schema: { type: string, default: flat }
        - name: label
          in: query
          description: Sobrescreve o `label` da métrica.
          schema: { type: string }
        - name: color
          in: query
          schema: { type: string }
        - name: labelColor
          in: query
          schema: { type: string }
        - name: logo
          in: query
          schema: { type: string }
        - name: logoColor
          in: query
          schema: { type: string }
        - name: logoSize
          in: query
          schema: { type: integer, minimum: 8, maximum: 20 }
        - name: logoPosition
          in: query
          schema: { type: string, enum: [left, right] }
        - name: link
          in: query
          schema: { type: string, format: uri }
        - name: font
          in: query
          schema: { type: string, enum: [fira-code, libre-franklin, space-grotesk] }
        - name: download
          in: query
          schema: { type: string, enum: ["1"] }
      responses:
        "200":
          description: Badge image (inclusive stale e fallback)
          headers:
            Cache-Control:
              schema: { type: string, example: "public, max-age=300" }
            ETag:
              schema: { type: string }
            X-Badge-Cache:
              description: HIT, MISS ou STALE. O fallback (sem valor bom) também sai como MISS.
              schema: { type: string }
            Warning:
              description: '110 - "Response is Stale" quando a resposta é stale.'
              schema: { type: string }
          content:
            image/svg+xml: {}
            image/png: {}
        "304":
          description: Not modified (If-None-Match igual ao ETag)
        "404":
          description: >
            ULID desconhecido, provider desabilitado pelo admin, definição
            inexistente, path fora do contrato ou extensão diferente de svg/png
        "429":
          description: Rate limit do dono do provider customizado (envelope rate_limited)
        "503":
          description: PNG pedido sem a extensão imagick
```
