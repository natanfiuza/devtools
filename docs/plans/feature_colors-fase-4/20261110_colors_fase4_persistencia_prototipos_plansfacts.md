# Plano — Colors Fase 4: persistência, Minhas Paletas, compartilhamento e protótipos

- Data: 2026-11-10
- Branch: `feature/colors-fase-4` (partindo do `main` com a F3 mergeada em `9881cb4`)
- Task: executar `docs/colors/PROMPT-Fase4-persistencia_prototipos.md`
- Status: plano aprovado pelo usuário; implementação por etapas com checkpoint

## Contexto

A F1–F3 entregaram o HTML Color Picker e o Color Palette Generator (escala de 11 tons, harmonias e extração de imagem), 100% no navegador e sem persistência. A F4 cria a camada de conta: salvar paletas por usuário autenticado (Free 25 / pago ilimitado), Minhas Paletas com busca no servidor, página pública por slug com SEO/OG, duplicação e protótipos de site/mobile em temas claro/escuro com exportação SVG vetorial e PNG 1x/2x.

## Decisões fechadas

| Tema | Decisão |
|---|---|
| Cota | Free 25, pago ilimitado; arquivadas contam; só exclusão definitiva libera |
| “Uso” | `duplications_count`: duplicações feitas por outros usuários; cumulativo |
| Exclusão | Permanente, exige digitar o nome exato; apaga linha + OG; libera slug e cota |
| Arquivo | Separado e reversível; arquivada responde 404, libera o slug e é somente leitura |
| Visibilidade | `private` (padrão) / `public`; privada responde 404 na URL pública |
| Modelo salvo | `kind=scale` (base + 11 tons efetivos) ou `kind=extracted` (base + 5/7/9/11 + origem sem imagem) |
| Cores efetivas | Persistidas; nunca recalculadas ao abrir |
| Papéis | 8 fixos × claro/escuro, editáveis e persistidos; site e mobile compartilham |
| Protótipo | Renderer SVG vetorial próprio; PNG 1x/2x rasteriza o mesmo SVG com fonte embutida |
| Slug | Gerado na primeira publicação, único global, imutável ao renomear, compartilhado entre locales |
| OG | Cliente gera 1200×630; upload autenticado validado; storage `og_paleta_image`; serve só pública ativa |
| Duplicação | Autenticada, respeita cota sob lock, nasce privada, sem OG, guarda proveniência |
| Página pública | Vitrine + cópia/download + “testar nova base” local + duplicar; sem autor/protótipo/social |
| Editor | `/dashboard/colors/palettes/{ulid}/edit` com o gerador + aba Protótipo |
| Tags | Entidade por usuário + pivô; renomear reflete em todas; reordenar por usuário |
| Criação | “Salvar” no gerador; anônimo guarda rascunho em `sessionStorage` e vai ao login |
| Concorrência | `DB::transaction` + `User::lockForUpdate()` + contagem; teste real opt-in em MySQL |
| URL do dashboard | Sem prefixo: `/dashboard/colors/palettes` (ADR-022); corrigir SPEC/minhas-paletas |
| Sitemap | Paletas públicas ativas, limite por config |
| Autor | Não aparece na página pública |

## Dados

Migrations aditivas: `color_palettes`, `color_tags`, pivô `color_palette_color_tag`.

`color_palettes`: ULID, `user_id` cascade, `duplicated_from_id` self-FK nullOnDelete, `name` 80, `description` 500, `visibility`, `kind`, `algorithm_version`, `colors` JSON `{base, items:[{hex, tone?}]}`, `roles` JSON 8×2, `source` JSON limitado (file/label ou url/host), `colors_count`, `duplications_count`, `slug` unique 60, `archived_slug`, `archived_at`, `og_version`; índices por usuário/ordenação e por visibilidade.

`color_tags`: ULID, `user_id`, `name` 30, `key` normalizada, `position`, unique `(user_id, key)`.

`config/colors.php` com limites, slug reservado, OG, sitemap e versões de algoritmo, todos com override por `.env`. Enums `PaletteKind`/`PaletteVisibility`; models `ColorPalette`/`ColorTag`; factories.

## Backend

Support: `PaletteQuota`, `PaletteSlug`, `PaletteTags`, `PaletteOgStorage`, `PaletteRules`, `PalettePresenter`, `PaletteName`; actions `CreatePalette`, `UpdatePalette`, `DuplicatePalette`, `ArchivePalette`, `RestorePalette`, `DeletePalette`; `ColorsServiceProvider` com limiters.

Rotas de dashboard sem prefixo (`auth, verified, active`) e públicas sob `{locale}` (`show`, `og`, intent e store de duplicação). Ownership com `abort_unless(..., 404)`, sem policies novas. Lista com busca `whereLike`, filtro de tag/status, sort whitelist, paginação e presenter.

SEO: branch em `Seo::pageMeta`/`image()` para `colors.palettes.show`, remoção da query string de `Locales::alternates`, sitemap com paletas públicas ativas, layout público por `inertiaApp.ts`, 404 para privada/arquivada.

OG: cliente renderiza e envia multipart autenticado; servidor valida PNG 1200×630 ≤ 2 MB, grava atômico, versiona em `og_version`, invalida em mudança visual, serve só pública ativa com cache.

## Frontend

Extrair `GeneratorWorkspace.vue` do `Tool.vue` (picker, abas, escala, exportações, extração, cópia) com slots para o editor; `frozenScale.ts`, `palettePayload.ts`, `saveDraft.ts`. Tipos, componentes e páginas: `PalettesIndex`, `PaletteEditor`, `PublicPalette`; modal de salvar, tags, meta, exclusão, cards, paginação e OG. Protótipos em `tools/palette-generator/prototype/` com templates site/mobile/OG, defaults, fontes embutidas, `renderSvg`, `rasterize`, `exportPrototype` e painel/editor de papéis.

## Testes

Pest: CRUD, cota, concorrência opt-in, visibilidade, busca, arquivamento/restauração, exclusão, duplicação, tags, OG, SEO, intents; unit slug/presenter/versões; atualizar SEO/sitemap/props/tool page. Vitest: payload, frozen scale, rascunho, modal, editor, lista, pública, OG, protótipos (snapshots e dimensões) e tags.

## Etapas

0. Baseline e docs. 1. Dados. 2. Support/actions. 3. HTTP. 4. SEO/i18n backend. 5. Base do frontend. 6. Gerador/salvar. 7. Minhas Paletas/editor. 8. Página pública. 9. Protótipos. 10. OG cliente. 11. ADRs/docs/relatório.

Cada etapa termina em checkpoint; nunca commitar nem push (gitpr do usuário).

## Verificação

`composer test`, `composer lint:check`, `composer types:check`, `composer ci:check`, `npm run test`, `npm run types:check`, `npm run lint:check`, `npm run format:check`, `npm run build`, `npm run build:ssr`, além da matriz manual (cota, escala/extraída, slug, arquivo, exclusão, duplicação, busca, tags, OG, protótipos, 5 idiomas, responsivo, teclado).
