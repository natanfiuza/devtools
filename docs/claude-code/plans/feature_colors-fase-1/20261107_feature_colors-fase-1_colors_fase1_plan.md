# Plano — Colors Fase 1: grupo + HTML Color Picker

- Data: 2026-11-07
- Branch: `feature/colors-fase-1`
- Survey: `docs/survey/20261107_colors_fase1_surveyfacts.md`

## Contexto

`docs/colors/` é especificação pura. A Fase 1 entrega o grupo Colors no catálogo e o HTML Color Picker completo, 100% no navegador, em cinco idiomas, dark-first. Antes do código, a documentação é reconciliada (contradições de escala, OG, histórico, limites, arquivamento e Figma) e os 25 ADRs de Colors são renumerados para 091–115.

Fora de escopo: Palette Generator, backend/banco, persistência de conta, social, moderação, OG dinâmico de paleta e landing `/tools/colors`.

## Decisões

| Tema | Decisão |
|---|---|
| URL | Aninhada e canônica `/{locale}/tools/colors/html-color-picker` via `group` no manifest; URL plano 404 |
| Núcleo | TS próprio, sem dependência |
| Layout | Próprio do picker, tokens do DESIGN |
| i18n | `tools.html-color-picker.*` + `tools.categories.colors` |
| Gamut | Redução de croma preservando L/H, com aviso e valor fonte |
| EyeDropper | Nativo + fallback imagem/lupa 8×/16× |
| Shades/tints | 10 + 10 em OKLab |
| Persistência | `localStorage`, 50 histórico e 50 favoritos, dedupe canônico |
| Superfície | Área 2D + matiz + alpha + input nativo |
| Contraste | Fundo explícito, alpha composto, AA/AAA sem arredondamento |
| Formatos | Nove, com cópia individual |
| URL | Sem estado em query |

## Etapa 0 — Documentação

1. Salvar survey e este plano.
2. Renumerar ADRs 057→091, 058→092, 059→093, 060→094, 061→095, 062→096, 063→097, 064→098, 065→099, 066→100, 067→101, 068→102, 069→103, 070→104, 076→105, 077→106, 078→107, 079→108, 080→109, 081→110, 082→111, 083→112, 084→113, 085→114, 086→115; criar `docs/colors/README.md` com a tabela.
3. Corrigir escala (11 tons fixos; 5/7/9/11 só extração), OG (estática em `public/og` via `seo:og-image`; paleta dinâmica em `storage/app/data/og_paleta_image`), histórico (F1 local 50; backend na F5), limites (Free 25, pago sem limite funcional), arquivamento (ADR-119), Figma (W3C DTCG), plano de fases F1–F8, `ai_cache`, curtida anônima assinada, threads 2 níveis, `{locale}` e mapeamento de idiomas.
4. Criar ADRs 116 (grupo na URL), 117 (núcleo de cor/gamut), 118 (persistência local), 119 (arquivamento).
5. Atualizar glossários e prompts F1–F8.
6. Baseline `composer test` e `npm test`.

## Etapa 1 — Grupo, rota, SEO

- Manifest com `group: colors`, `category: colors`, `icon: palette`, `order: 100`.
- `ToolCategoryId` + `ToolGroupId` + `group?` em `resources/js/tools/types.ts`.
- `categories.ts`, `icons.ts`.
- Rota `tools/{group}/{slug}` nomeada `tools.show.grouped`, inserida antes de `tools/{slug}`.
- `Tools`: `group`, `findForUrl`, `groups`, `routeFor`.
- `ToolController`, `Seo`, `Sitemap`, `StructuredData` cientes do nome novo.
- `toolPath` aninhado; i18n completa nos cinco idiomas.

## Etapa 2 — Núcleo de cor (`resources/js/lib/color/`)

`types.ts`, `named.ts`, `convert.ts`, `parse.ts`, `format.ts`, `gamut.ts`, `contrast.ts`, `mix.ts`, `index.ts`. Estado autoritativo `Rgba`; OKLCH derivado; fonte fora de gamut só como metadado.

## Etapa 3 — Tool (`resources/js/tools/html-color-picker/`)

`Tool.vue`, `lib/useColorState.ts` (porta única, rascunho de texto, hue âncora, `finalize()`), `lib/storage.ts`, `lib/eyedropper.ts`, `lib/imageSampler.ts`; componentes de área, sliders, preview, input, formatos, contraste, shades/tints, histórico, favoritos, EyeDropper e amostrador de imagem. Layout desktop em duas colunas e mobile em coluna única.

## Testes

Vitest por módulo (`color-*`) e componente; Pest de catálogo/rota/SEO/sitemap/structured data; testes front de registro e home.

## Verificação

`npx vitest run`, `php artisan test --filter=...`, `npm run types:check`, `php artisan seo:og-image`, `composer test`, `npm test`, `npm run lint:check`, `npm run format:check`; checklist manual nos cinco idiomas, claro/escuro, teclado, EyeDropper nativo e fallback.

## Sequência com paradas

0 docs/baseline → 1 núcleo (tipos/conversões/nomes) → 2 parse/format → 3 gamut/contraste/mix → 4 catálogo+rota+SEO+i18n+página mínima+OG → 5 persistência/estado → 6 superfície → 7 contraste → 8 shades/histórico/favoritos → 9 EyeDropper/fallback → 10 polimento, portões e relatório em `docs/claude-code/reports/feature_colors-fase-1/`.

Nunca commitar nem fazer push; o usuário aprova commits/PR via gitpr.
