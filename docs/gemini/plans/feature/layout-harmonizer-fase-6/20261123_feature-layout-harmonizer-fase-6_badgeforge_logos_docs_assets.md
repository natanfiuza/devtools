# Plano de Implementação — Logos de `docs/assets/img` no Seletor e Catálogo do BadgeForge

## Descrição do Objetivo
Substituir os 23 logos placeholder em `resources/badgeforge/logos/` pelos ícones vetoriais reais correspondentes aos arquivos em `docs/assets/img/` (`*_ico.svg`), estender o catálogo PHP/TypeScript para suportar múltiplos `<path>` (com `fill-rule` e `clip-rule`), exibir a miniatura vetorial (`16×16`, `currentColor`) ao selecionar o logo nos 3 formulários do BadgeForge (`Tool.vue`, `ProviderForm.vue`, `CustomProviderForm.vue`) e atualizar os testes e fixtures douradas afetadas.

> Levantamento base: `docs/survey/20261123_badgeforge_logos_docs_assets_surveyfacts.md`. Os 2 slugs sem arquivo em `docs/assets/img/` (`mysql` e `gitpr`) permanecem inalterados.

---

## Mudanças Propostas

### 1. Catálogo de Logos (`LogoCatalog.php` e `logos.ts`)
- Atualizar `app/Support/BadgeForge/LogoCatalog.php` para extrair todos os `<path>` com `d`, `fill-rule` e `clip-rule` opcionais via `preg_match_all`, mantendo a restrição estrita às tags `<svg>` e `<path>`.
- Atualizar `resources/js/tools/badgeforge/logos.ts` espelhando a mesma leitura de múltiplos `<path>` com `fillRule` e `clipRule`.

### 2. Ativos SVG Vetoriais em `resources/badgeforge/logos/`
- Substituir os 23 `.svg` correspondentes a `docs/assets/img/*_ico.svg` por SVGs vetoriais puros (`<svg xmlns="http://www.w3.org/2000/svg" viewBox="..."><path .../></svg>`), monocromáticos e recoloríveis por `logoColor`:
  - 7 vetoriais limpos/adaptados de `docs/assets/img/`: `github`, `laravel`, `pest`, `php`, `react`, `tailwind`, `postgresql`.
  - 16 rasterizados em `docs/assets/img/` substituídos por `<path>` vetorial limpo e fiel às respectivas marcas: `composer`, `docker`, `gitlab`, `html`, `javascript`, `json`, `markdown`, `node`, `npm`, `phpunit`, `python`, `redis`, `symfony`, `typescript`, `vite`, `vue`.

### 3. Miniatura do Logo no Seletor `<Select>`
- Criar componente compartilhado `resources/js/tools/badgeforge/LogoSelect.vue` (ou `LogoSelectOptions.vue`) reutilizando `LogoPreview.vue` (`:size="16"`, `color="currentColor"`) para exibir o ícone ao lado do nome do logo no dropdown e no valor selecionado.
- Integrar em `Tool.vue`, `ProviderForm.vue` e `CustomProviderForm.vue` preservando os atributos `id` e `data-test`.

### 4. Testes e Fixtures Douradas
- Atualizar `tests/Unit/BadgeForge/LogoCatalogTest.php` para validar `viewBox` válido e `>= 1` path (em vez de `0 0 24 24` e `1` path fixo), cobrindo logos multi-path e `fillRule`.
- Regravar as fixtures em `tests/Fixtures/badgeforge/expected/*.svg` que usam `github` ou `laravel` para manter paridade byte a byte entre `StaticBadgeRendererTest` (Pest) e `badgeforge-render.test.ts` (Vitest).

---

## Verificação
- `composer test`
- `npm run test`
- `npm run types:check`
- `npm run lint`
