# API BadgeForge

## Estática (F1 — disponível)

```text
GET /api/badges/static/{label}/{message}.svg
```

O SVG é gerado e servido pelo próprio devtools, sem sessão e sem autenticação.
A rota fica no grupo `api`, fora do prefixo `v1`, e não tem throttle próprio.

### Caminho

- `label` e `message`: texto do badge, sem barra. `_` vira espaço, `__` vira
  `_`; a barra é escrita `%252F` (dupla codificação: o router decodifica o path
  antes do match). `-` e `.` são literais.
- A extensão `.svg` é obrigatória. Sem extensão ou com `.png` a resposta é 404
  (`.png` entra na F2, ver `ADR-067`).
- Texto acima de 100 caracteres é truncado com `…`; segmento vazio dá 404.

### Query

| Parâmetro | Padrão | Regra |
|---|---|---|
| `style` | `flat` | `flat`, `flat-square`, `plastic`, `for-the-badge`, `social`; outro valor → `flat` |
| `color` | `#9f9f9f` | cor inválida → padrão |
| `labelColor` | `#555555` | cor inválida → padrão |
| `logo` | — | slug fora do registro embutido → ignorado |
| `logoColor` | `#ffffff` | cor inválida → padrão |
| `logoSize` | 10 (14 em `for-the-badge`) | clamp 8–20 |
| `logoPosition` | `left` | `left` ou `right`; outro → `left` |
| `link` | — | só `http`/`https`; outro → sem âncora |
| `cacheSeconds` | 86400 | inteiro com clamp 300–86400; inválido → padrão |

Cores aceitam nomes CSS (148), apelidos de badge (`brightgreen`, `critical`…),
hex 3/4/6/8, `rgb()`, `rgba()`, `hsl()`, `hsla()` e `transparent`. Cores com
transparência saem como `fill` + `fill-opacity`.

O estilo `social` fixa as próprias cores (`#555555` e `#007ec6`): `color` e
`labelColor` não têm efeito nele.

### Exemplos

```text
/api/badges/static/build/passing.svg
/api/badges/static/build/passing.svg?style=for-the-badge&logo=github
/api/badges/static/ci%252Fcd/passing.svg?color=brightgreen
/api/badges/static/vers%C3%A3o/1.0.0.svg
```

### Resposta

- `200`: `Content-Type: image/svg+xml; charset=utf-8`,
  `Cache-Control: public, max-age=<cacheSeconds>`, `ETag: "<sha256>"`.
- `304`: `If-None-Match` igual ao ETag; sem corpo e sem `Content-Type`.
- `404`: segmento vazio, extensão ausente ou diferente de `svg` (envelope
  `ApiError`).
- O documento tem `role="img"`, `aria-label="label: message"` e `<title>` com o
  mesmo texto. Nada é registrado em log além de IDs e classes de exceção.

## Provider (F4 — planejado)

```text
GET /api/badges/{provider}/{path}.svg
```

Exemplos previstos:

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
- Packagist não hospeda pacote privado: o acesso é sempre público.
- Cache padrão de 300 s, com `stale-if-error` para não devolver erro ao `<img>`
  quando o provider falhar ou limitar.

## Endpoint JSON (F5 — planejado)

```text
GET /api/badges/endpoint.svg?url=https://...
```

Requer autenticação. HTTPS obrigatório, SSRF bloqueado, TTL mínimo 300 s.

## Pessoal (F3+ — planejado)

```text
GET /b/{username}/{ulid}.svg
```

Apenas usuários pagos criam. A URL carrega só o ULID opaco; o PAT de item
privado nunca aparece nela.

## Metadados e gestão (fases futuras)

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
