# Colors — Fase 4: persistência, Minhas Paletas, compartilhamento e protótipos

- Data: 2026-11-10
- Branch: `feature/colors-fase-4` (a partir do `main` com a F3 mergeada em `9881cb4`)
- Prompt: `docs/colors/PROMPT-Fase4-persistencia_prototipos.md`
- Status: aprovado; executar em etapas com checkpoint

## Contexto

A F3 entregou harmonias e extração de imagem no gerador, sem persistência. A F4 cria a camada de conta: paletas salvas por usuário (Free 25 / pago ilimitado), Minhas Paletas com busca no servidor, página pública por slug com SEO/OG, duplicação e protótipos site/mobile em claro/escuro com SVG vetorial e PNG 1x/2x.

## Decisões

- Cota: arquivadas contam; só a exclusão libera. “Uso” = duplicações feitas por outros.
- Exclusão permanente com nome digitado; arquivamento separado e reversível.
- `kind=scale` (base + 11 tons efetivos) ou `kind=extracted` (base + 5/7/9/11 + origem sem imagem); cores efetivas persistidas.
- 8 papéis × claro/escuro persistidos e editáveis.
- Slug gerado na primeira publicação, único global, imutável, 404 para privada/arquivada; arquivar libera e restaurar reivindica.
- OG gerada no cliente, upload validado, storage `og_paleta_image`, serve só pública ativa, fallback padrão.
- Duplicação autenticada sob lock, privada, sem OG, com proveniência e cota.
- Página pública sem autor/protótipo/social, com experimento local de nova base.
- Tags por usuário, renomeáveis e reordenáveis.
- Anônimo guarda rascunho em `sessionStorage` antes do login.
- Dashboard sem prefixo de locale (ADR-022); corrigir SPEC/minhas-paletas.
- Protótipo com renderer SVG próprio; PNG rasteriza o mesmo SVG com fonte embutida.

## Dados

Migrations `color_palettes`, `color_tags`, pivô. `color_palettes` com ULID, `user_id`, `duplicated_from_id`, `name`, `description`, `visibility`, `kind`, `algorithm_version`, `colors`, `roles`, `source`, `colors_count`, `duplications_count`, `slug`, `archived_slug`, `archived_at`, `og_version` e índices de lista. `color_tags` com `key` normalizada e posição. `config/colors.php` com limites/OG/slug/sitemap/versões. Enums, models e factories.

## Backend

Support `PaletteQuota`, `PaletteSlug`, `PaletteTags`, `PaletteOgStorage`, `PaletteRules`, `PalettePresenter`, `PaletteName`; actions de criar/editar/duplicar/arquivar/restaurar/excluir; `ColorsServiceProvider` para limiters. Rotas de dashboard sem locale e públicas sob `{locale}`; ownership 404; lista com `whereLike`, filtro, sort whitelist e paginação. SEO/canonical/sitemap/layout/404. OG com validação, storage atômico, `og_version` e serving restrito.

## Frontend

Extrair `GeneratorWorkspace.vue`; `frozenScale`, `palettePayload`, `saveDraft`; tipos e componentes de paleta/tags/meta/exclusão; páginas `PalettesIndex`, `PaletteEditor`, `PublicPalette`; protótipos em `prototype/` com templates site/mobile/OG, defaults, fontes embutidas, `renderSvg`, `rasterize` e exportação.

## Testes

Pest: CRUD, cota, concorrência opt-in MySQL, visibilidade, busca, arquivamento, exclusão, duplicação, tags, OG, SEO e intents; unit slug/presenter/versões. Vitest: payload, escala congelada, rascunho, modal, editor, lista, pública, OG, tags e snapshots dos protótipos.

## Etapas

0. Baseline/docs. 1. Dados. 2. Support/actions. 3. HTTP. 4. SEO/i18n backend. 5. Base do frontend. 6. Gerador/salvar. 7. Minhas Paletas/editor. 8. Página pública. 9. Protótipos. 10. OG cliente. 11. ADRs 123–126, docs e relatório.

Sem commit/push; o usuário aprova cada um via gitpr.
