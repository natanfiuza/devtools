# Relatório — BadgeForge Fase 1: núcleo SVG estático

- **Branch:** `develop_natan`
- **Data:** 2026-10-31
- **Origem:** sessão `/grill-with-docs` + plano aprovado em `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_fase1.md`
- **Decisões:** ADRs 062–076 (`docs/adr/`)
- **Fase:** F1 de `docs/SPEC-BADGEFORGE.md` (§16)

## 1. Resumo

A Fase 1 do BadgeForge saiu do papel: o devtools gera e serve badges estáticas em
SVG pelo próprio Laravel, sem dependência de provider, sem PNG e sem código do
Shields.io. O endpoint público é

```text
GET /api/badges/static/{label}/{message}.svg
```

com cinco estilos (`flat`, `flat-square`, `plastic`, `for-the-badge`, `social`),
parser completo de cores (nomes CSS, apelidos de badge, hex 3/4/6/8, `rgb(a)`,
`hsl(a)`, `transparent`), 24 logos embutidos, `logoSize`/`logoPosition`/`link` e
`cacheSeconds`, além de `ETag` sha256 + `304` + `Cache-Control`.

A página `/{locale}/tools/badgeforge` monta o badge **100% no navegador**: um
espelho TypeScript do renderer PHP desenha o preview em `<img>` (data URI, sem
requisição) e as quatro abas de snippet (URL, Markdown, HTML, reStructuredText)
saem das mesmas funções puras. A tool entrou no catálogo (card, busca, sitemap,
OG próprio) e no i18n dos cinco idiomas, com a categoria nova `badges`
("Imagens"/"Images").

A paridade servidor ↔ cliente é garantida por **21 fixtures-ouro** comparadas
byte a byte no Pest e no Vitest e por um `tool-props.json` único que impede
limites e defaults divergirem.

## 2. Correção do modelo de acesso a providers

O plano original previa tokens globais por provider em `.env`
(`BADGEFORGE_*_TOKEN`) e tutoriais mandando editar o `.env` do servidor. O
usuário corrigiu o modelo durante o grill:

- **Item público** (repositório, pacote, imagem pública): o servidor consulta a
  API pública do provider **anonimamente**. Não existe token global em `.env`,
  `config/` ou banco. O rate limit anônimo é absorvido por cache + requisição
  condicional + `stale-if-error`.
- **Item privado**: apenas usuário **pagador** e apenas em **badge pessoal**. O
  PAT é do usuário, com escopo mínimo, informado na UI da conta, **cifrado em
  repouso** e vinculado à definição do badge; a URL pública leva só o ULID
  opaco. Nunca em URL, log, payload de erro ou exportação; revogável e apagado
  com o badge e com a conta.
- **Packagist não hospeda pacote privado**: o tutorial de PAT do Packagist foi
  removido.

A F1 não chama provider e não lê token, então o código não mudou por causa
dessa correção; o que mudou foi a documentação (ADR-068 reescrito, SPEC §9,
`docs/badgeforge/API.md`, `OPENAPI.md` e os três tutoriais).

## 3. O que foi entregue

### 3.1 Backend

| Arquivo | Papel |
|---|---|
| `config/badgeforge.php` | Endpoint, defaults, limites (texto 100, logo 8–20, cache 300–86400, link 2048) e registro dos 24 logos. Nenhum limite no código. |
| `app/Support/BadgeForge/BadgeSpec.php` | Value object do badge já normalizado. |
| `app/Support/BadgeForge/BadgeRequest.php` | Path + query → spec: decode de `_`/`__`/`%252F`, truncagem por code points, fallbacks e clamps. |
| `app/Support/BadgeForge/Color.php` + `ColorValue.php` | Parser de cor → `{hex, alpha}` e atributos `fill`/`fill-opacity`. |
| `app/Support/BadgeForge/FontMetrics.php` | Largura de texto a partir de `font-metrics.json`. |
| `app/Support/BadgeForge/Style.php` + `StyleCatalog.php` | Estilos e geometria lidos de `styles.json`. |
| `app/Support/BadgeForge/Logo.php` + `LogoCatalog.php` | Slug → `viewBox` + `<path d>`; arquivo só com um `<path>`. |
| `app/Support/BadgeForge/Svg.php` | Escape XML e allowlist de tags/atributos. |
| `app/Support/BadgeForge/StaticBadgeRenderer.php` | Único dono do documento SVG. |
| `app/Support/BadgeForge/BadgeForgeToolProps.php` | Catálogo e limites para a tela (fixture comum com o TS). |
| `app/Http/Controllers/BadgeForge/StaticBadgeController.php` | Spec → render → sha256 → `isNotModified` → 200/304. |
| `routes/api.php` | Bloco `badges`, fora do `v1` e sem Sanctum/throttle, com os três `where` explícitos. |

O documento SVG é de uma linha, determinístico, com `role="img"`,
`aria-label="label: message"` e `<title>` iguais; logo interno com
`aria-hidden="true"` e `focusable="false"`; `link` (só http/https) envolve o
conteúdo em `<a target="_blank" rel="noopener noreferrer">`.

### 3.2 Ativos compartilhados

- `resources/badgeforge/styles.json` — geometria dos 5 estilos.
- `resources/badgeforge/font-metrics.json` — larguras de glifo.
- `resources/badgeforge/colors.json` — 148 nomes CSS, apelidos e aliases.
- `resources/badgeforge/logos/*.svg` — 24 desenhos autorais, só `<path d>`.

Os três JSONs são lidos pelo PHP e importados pelo TypeScript; os logos são lidos
do disco no servidor e importados crus (`import.meta.glob`) no cliente.

### 3.3 Frontend

| Arquivo | Papel |
|---|---|
| `resources/js/tools/badgeforge/manifest.json` | `available`, categoria `badges`, ícone `image`, ordem 95. |
| `types.ts` | Tipos de props, form e spec. |
| `lib/color.ts` / `lib/metrics.ts` / `lib/styles.ts` | Espelhos de cor, métricas e estilos. |
| `lib/render.ts` | Espelho do renderer; mesmo documento, byte a byte. |
| `lib/url.ts` | URL canônica: encode de segmentos e query só com o que difere do padrão. |
| `lib/snippets.ts` | URL, Markdown, HTML e reStructuredText com os escapes certos. |
| `builder.ts` | Estado e normalização do formulário (clamps, uppercase, truncagem, social, link). |
| `logos.ts` | Catálogo de logos a partir dos SVGs crus. |
| `Tool.vue` | Formulário | preview + 4 abas com `useCopyFeedback`; nenhuma requisição. |

### 3.4 Catálogo, i18n e SEO

- `ToolCategoryId` ganhou `badges`; `categories.ts` ganhou o chip novo.
- `lang/{en,es,fr,pt_BR,pt_PT}/tools.php`: `categories.badges` e o bloco
  `badgeforge.*` completo, com chaves idênticas nos cinco idiomas.
- Listas fixas atualizadas: `ToolsRegistryTest`, `SitemapTest`,
  `tools-registry.test.ts`, `home.test.ts` (7 → 8 chips), `command-palette.test.ts`.
- `php artisan seo:og-image` gerou `public/og/badgeforge.png` (1200×1200) e
  regenerou a imagem padrão com o nome novo.

### 3.5 Documentação

- ADRs do BadgeForge renumerados de 041–050 (colidiam com ADRs existentes) para
  **062–071**, com H1 no formato da casa.
- Novos ADRs **072–076**: nome e escopo, formatos, estilos, render PHP + espelho
  TS, cache e endpoint stateless.
- **ADR-068** reescrito: acesso público anônimo + PAT do usuário pagador para
  item privado.
- `CLAUDE.md` (faixa 062–076, F1 entregue), `docs/SPEC-BADGEFORGE.md`
  (§3, §6, §8, §9, §10, §14, §15, §16, §17), `docs/badgeforge/API.md` e
  `docs/badgeforge/OPENAPI.md`.
- Tutoriais `github-token.md`, `npm-token.md` e `docker-token.md` reescritos
  como guias do usuário pagador para item privado; `packagist-token.md`
  removido; `instalar-imagick-ubuntu.md` intocado (F2).
- `docs/glossario-badgeforge.md` fundido em `docs/glossario.md` e removido.
- `docs/design/DESIGN.md` com o parágrafo da página BadgeForge.
- `docs/acceptance/BADGEFORGE-FASE-1.md`.
- `storage/framework/badgeforge-gen.php` (script temporário que gerou as
  fixtures) removido.

## 4. Testes

### Pest

| Arquivo | Cobertura |
|---|---|
| `tests/Unit/BadgeForge/ColorTest.php` | hex, rgb(a), hsl(a), nomes, aliases, transparente, inválidos. |
| `tests/Unit/BadgeForge/FontMetricsTest.php` | avanço, acentos, glifo desconhecido, texto vazio. |
| `tests/Unit/BadgeForge/BadgeRequestTest.php` | decode, controle, truncagem, fallbacks, clamps, link, cache. |
| `tests/Unit/BadgeForge/LogoCatalogTest.php` | arquivo, `viewBox` e path único; slug inválido. |
| `tests/Unit/BadgeForge/StaticBadgeRendererTest.php` | 21 fixtures byte a byte, acessibilidade, ausência de script/on*/javascript:. |
| `tests/Unit/BadgeForge/BadgeForgeToolPropsTest.php` | payload igual ao fixture; todo logo resolve. |
| `tests/Feature/BadgeForge/StaticBadgeEndpointTest.php` | 200, headers, ETag/304, clamps, 404 (vazio, `.png`, sem extensão), `1.0.0`, `%252F`, `Log::spy()`, HEAD. |
| `tests/Feature/BadgeForge/BadgeForgePageTest.php` | 5 idiomas, componente, `toolProps` e limites vindos do config. |

### Vitest

| Arquivo | Cobertura |
|---|---|
| `resources/js/__tests__/badgeforge.test.ts` | cores, métricas, builder, URL, snippets. |
| `resources/js/__tests__/badgeforge-render.test.ts` | mesmo SVG do PHP nas 21 fixtures, determinismo, XSS, logos. |
| `resources/js/__tests__/badgeforge-component.test.ts` | estado vazio, cores, social, abas, copiar, reset. |

### Resultado

- `composer test`: **pint ok, phpstan 0 erros, Pest 820 testes / 17.497 asserções**.
- `npm run test`: **68 arquivos / 875 testes**, exit 0.
- `npm run types:check`: limpo.
- `npm run lint:check`: 0 erros (avisos restantes são a linha de base do
  repositório; os arquivos do BadgeForge não geram nenhum).
- `php artisan route:list --path=badges`: só
  `GET|HEAD api/badges/static/{label}/{message}.{ext}` (`badges.static`), sem
  middleware de autenticação.

## 5. Decisões registradas

| ADR | Tema |
|---|---|
| 062 | Persistência em disco (fases futuras) |
| 063 | Analytics em duas tabelas |
| 064 | Badges pessoais (URL só com ULID) |
| 065 | Analytics híbrido |
| 066 | Presets |
| 067 | Imagick para PNG (F2) |
| 068 | Acesso a providers: público anônimo + PAT do usuário só para privado |
| 069 | Refresh manual |
| 070 | Exportação CSV |
| 071 | Documentação pública |
| 072 | Nome e escopo |
| 073 | Formatos (SVG canônico; `.png` só na F2) |
| 074 | Estilos e geometria em `styles.json` |
| 075 | Render PHP + espelho TS com fixtures byte a byte |
| 076 | Cache HTTP, ETag/304 e endpoint stateless |

## 6. Pontos de atenção

1. **Prettier**: `npm run format:check` já falhava antes da entrega em 123
   arquivos (todos os `Tool.vue` do repositório, porque o `tabWidth: 4` do
   Prettier conflita com o template em 2 espaços que o ESLint exige). Os arquivos
   TS/JSON novos do BadgeForge estão formatados; o `Tool.vue` segue a convenção
   da casa (2 espaços no template) e entra na mesma linha de base. Não houve
   reformatação em massa.
2. **Marcas dos logos**: os 24 desenhos são autorais, inspirados em marcas de
   terceiros; revisar atribuição antes de qualquer material público mais amplo.
3. **Glifos fora do ASCII**: fallback de métrica largo evita corte, mas alarga o
   badge; limitação da F1 documentada.
4. **Escape `%252F` depende do proxy**: nginx/Cloudflare normalmente preservam
   `%25`; o smoke em produção fica no aceite da fase.
5. **Texto na URL**: o access log do proxy vê label/message, como em qualquer
   badge; a regra de não logar é do app e tem teste (`Log::spy()`).
6. **Repositório não commitado**: nenhum commit/push foi feito, conforme a regra
   inviolável do `CLAUDE.md`.

## 7. Fora de escopo (segue planejado)

PNG/Imagick (`?download=`), providers GitHub/npm/Packagist/Docker, endpoint
JSON + SSRF, logos personalizados, presets, badges pessoais, analytics,
exportação CSV, `?refresh=` e OpenAPI completo (F8).
