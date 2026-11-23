# Survey — Implementação dos logos de `docs/assets/img` no seletor e catálogo do BadgeForge (2026-11-23)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** Implemente todos os logo em `docs\assets\img` nos logos ao selecionar o logo em badgeforge
- **Tela / Módulo:** BadgeForge (`resources/js/tools/badgeforge/Tool.vue`, `ProviderForm.vue`, `CustomProviderForm.vue`), catálogo de logos (`config/badgeforge.php`, `app/Support/BadgeForge/LogoCatalog.php`, `resources/js/tools/badgeforge/logos.ts`) e ativos em `resources/badgeforge/logos/*.svg`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `feature/layout-harmonizer-fase-6`
- **Método:** 2 rodadas de entrevista (5 decisões de fronteira), inspeção direta dos 23 SVGs de `docs/assets/img/`, dos 25 SVGs de `resources/badgeforge/logos/`, do catálogo PHP/TS, do renderer e dos testes.
- **Arquivos-chave lidos:**
  - `config/badgeforge.php`
  - `app/Support/BadgeForge/LogoCatalog.php`
  - `app/Support/BadgeForge/Logo.php`
  - `app/Support/BadgeForge/LogoPath.php`
  - `app/Support/BadgeForge/StaticBadgeRenderer.php`
  - `resources/js/tools/badgeforge/logos.ts`
  - `resources/js/tools/badgeforge/lib/render.ts`
  - `resources/js/tools/badgeforge/Tool.vue`
  - `resources/js/tools/badgeforge/ProviderForm.vue`
  - `resources/js/tools/badgeforge/CustomProviderForm.vue`
  - `tests/Unit/BadgeForge/LogoCatalogTest.php`
  - `tests/Unit/BadgeForge/StaticBadgeRendererTest.php`
  - `resources/js/__tests__/badgeforge-render.test.ts`

## 2. Decisões das rodadas

### Rodada 1 — Escopo e Formato Técnico
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Onde aplicar os logos de `docs/assets/img/` | **Substituir os placeholders de `resources/badgeforge/logos/` pelos logos reais e exibir a miniatura do logo no `<Select>` em `Tool.vue`, `ProviderForm.vue` e `CustomProviderForm.vue`** |
| Q2 | Como tratar os 16 SVGs com PNG Base64 e os vetoriais multi-elemento/coloridos | **Padronizar os 23 logos em `resources/badgeforge/logos/` para SVG vetorial puro (`<svg>` + `<path d>`), monocromático e recolorível por `logoColor`** |

### Rodada 2 — Vetorização, Seletor e Contrato de Testes
| # | Decisão | Resolução |
|---|---|---|
| Q3 | Representação da miniatura no `<Select>` | **Exibir o próprio SVG vetorial de `resources/badgeforge/logos/` via `currentColor` (`16x16`), adaptando-se ao tema claro/escuro e mantendo paridade 1:1 com a badge** |
| Q4 | Origem dos vetores `<path>` para os 16 logos rasterizados em `docs/assets/img/` | **Usar ícones vetoriais limpos e geométricos (`<path>` puro, estilo monocromático fiel às marcas de `docs/assets/img/`) para os 16 rasterizados e limpar/adaptar os 7 vetoriais para `<svg>` + `<path>` puro** |
| Q5 | Suporte a múltiplos `<path>` no `LogoCatalog.php` / `logos.ts` e fixtures | **Estender `LogoCatalog.php` e `logos.ts` para ler todos os `<path>` (preservando `fill-rule` e `clip-rule` opcionais), atualizar `LogoCatalogTest.php` e regravar as fixtures `tests/Fixtures/badgeforge/expected/*.svg` afetadas** |

### Defaults assumidos (não contestados)
- Os 2 slugs registrados em `config/badgeforge.php` que **não** constam em `docs/assets/img/` (`mysql` e `gitpr`) permanecem como estão (`gitpr.svg` já é o logo real; `mysql.svg` permanece inalterado).
- Nenhuma nova ADR é necessária, pois o comportamento segue o contrato existente de logo embutido (ADR-074 e ADR-075), apenas aproveitando o suporte a múltiplos `LogoPath` já existente em `Logo.php` e `render.ts`.

## 3. Relatório de fatos levantados
### Inventário de `docs/assets/img/` vs `resources/badgeforge/logos/`
- `docs/assets/img/` contém **23 arquivos `*_ico.svg`**, todos mapeáveis diretamente para slugs já existentes em `config/badgeforge.php` (`node-js_ico.svg` → `node`, `tailwind_css_ico.svg` → `tailwind`, demais `<slug>_ico.svg` → `<slug>`).
- **16 dos 23 arquivos** (`composer`, `docker`, `gitlab`, `html`, `javascript`, `json`, `markdown`, `node-js`, `npm`, `phpunit`, `python`, `redis`, `symfony`, `typescript`, `vite`, `vue`) são PNGs rasterizados embutidos via `<defs><image href="data:image/png;base64,..."/></defs><use .../>`.
- **7 dos 23 arquivos** são vetoriais: `github` (1 `<path>`), `laravel` (1 `<path>` + `fill-rule="evenodd"`), `pest` (1 `<path>` + `<title>`), `php` (3 `<path>`), `react` (1 `<path>` + 1 `<circle>`), `tailwind_css` (2 `<path>` + `<clipPath>`) e `postgresql` (9 `<path>` com múltiplos `fill` e `stroke`).
- Hoje, os 24 arquivos em `resources/badgeforge/logos/` (exceto `gitpr.svg`) são placeholders geométricos simples em `viewBox="0 0 24 24"` com 1 `<path>`.

### Restrições do catálogo e paridade PHP/TS
- `LogoCatalog.php` e `logos.ts` só aceitam tags `<svg>` e `<path>`, e hoje usam `preg_match` / `RegExp.exec` capturando apenas o primeiro `<path>`.
- `Logo.php`, `LogoPath.php`, `StaticBadgeRenderer.php` e `render.ts` já suportam `paths: LogoPath[]` com `fillRule` (`nonzero`/`evenodd`) e `clipRule` (`nonzero`/`evenodd`), recoloridos por `logoColor`.
- `LogoCatalogTest.php` valida `viewBox === '0 0 24 24'` e `paths` com contagem `1`; `StaticBadgeRendererTest.php` e `badgeforge-render.test.ts` validam paridade byte a byte contra `tests/Fixtures/badgeforge/expected/*.svg` (que usam `github` e `laravel`).
- Os únicos seletores de logos embutidos na UI ficam em `Tool.vue` (Estática), `ProviderForm.vue` (Provider) e `CustomProviderForm.vue` (Provider Customizado).
