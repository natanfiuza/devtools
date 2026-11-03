# OpenAPI BadgeForge (resumo)

A superfície da F1/F2 está implementada, com o `logo=u_<ULID>` da F3; os
demais caminhos aparecem como planejados. O OpenAPI completo em 3.1 é
entregue na F8 (`ADR-071`). A gestão de logos e presets da F3 é web/Inertia
autenticada e não faz parte desta API.

```yaml
openapi: 3.1.0
info:
  title: BadgeForge API
  version: 1.3.0
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
  /api/badges/{provider}/{path}.{ext}:
    get:
      summary: Dynamic provider badge (F4 — planejado)
      description: >
        Item público é lido anonimamente na API pública do provider. Item
        privado exige usuário pagador e PAT do próprio usuário, cifrado e
        vinculado ao badge pessoal (ADR-068); a URL leva apenas o ULID.
      parameters:
        - name: provider
          in: path
          required: true
          schema: { type: string }
        - name: path
          in: path
          required: true
          schema: { type: string }
        - name: ext
          in: path
          required: true
          schema: { type: string, enum: [svg, png] }
      responses:
        "200":
          description: Badge image
          content:
            image/svg+xml: {}
            image/png: {}
  /api/badges/endpoint.{ext}:
    get:
      summary: JSON endpoint badge (F5 — planejado)
      parameters:
        - name: url
          in: query
          required: true
          schema: { type: string, format: uri }
      responses:
        "200":
          description: Badge image
          content:
            image/svg+xml: {}
            image/png: {}
```
