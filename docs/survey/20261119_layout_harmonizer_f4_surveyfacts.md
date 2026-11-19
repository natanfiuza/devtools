# Survey — Layout Harmonizer F4 (persistência, compartilhamento e protótipos)

Data: 2026-11-19
Branch: `feature/layout-hamonizer-fase-4`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase4-salvamento_compartilhamento_prototipos.md`
Base: F1 (motor puro), F2 (tool e quadro) e F3 (edição manual) concluídas.

## Contexto da tarefa

A F3 fechou o controle manual sem persistir nada. O estado efetivo (snapshot,
overrides, locks, aceites, secundária manual e textos crus da entrada) só existe
em memória, e o `LayoutSnapshot` não guarda os textos nem uma forma de hidratar
o composable sem regenerar. O prompt da F4 pede salvar o sistema com
nome/descrição/tags/visibilidade, cota conjunta com as paletas, ciclo de vida com
slug público, página pública com apresentação do sistema, rascunho anônimo com
importação explícita no login, duplicação e protótipos reutilizados no
workspace. Fora da F4: exportação (F5), OG dinâmica, recursos sociais, perfil
próprio e API JSON.

Regras em vigor: GIT só pelo usuário (gitpr); toda string visível via i18n (5
idiomas); tools 100% no navegador; não reabrir decisões aprovadas; não inventar
defaults silenciosos; migrations retrocompatíveis.

## Decisões do grill (2026-11-18/19)

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Armazenamento | Tabela própria `layout_systems`; discriminator vazaria o sistema para recursos só de paleta |
| 2 | Rascunho | Só anônimo, último rascunho local, sem TTL, chave `devtools.layout-harmonizer.draft.v1` |
| 3 | Salvar | Cria item e abre editor vinculado; editar não consome; “salvar como novo” consome e vincula |
| 4 | Protótipos | Só workspace autenticado, site/mobile existentes, mapa fixo de papéis |
| 5 | Limites | Nome 80, descrição 500, configuráveis; tags por sistema |
| 6 | Origem | FK + crédito congelado; privada nunca aparece no público |
| 7 | Salvar como novo | Vincula ao novo item |
| 8 | Salvar anônimo | Leva ao login com retorno a `create?save=1` |
| 9 | Cota | Conjunta nas duas listas; arquivados contam |
| 10 | Versões antigas | Editar se o snapshot for suportado; mais novas/desconhecidas são somente leitura |
| 11 | Slug | Aposentar em arquivar/excluir; link antigo 404 para sempre |
| 12 | SEO | Sitemap + OG estática |
| 13 | Entrada pela paleta | CTA nas páginas da paleta |
| 14 | Nome sugerido | Origem ou estratégia + hex |

## Fatos levantados (verificados no código)

- `color_palettes` usa `foreignId('user_id')` (users são bigint), ULID como PK,
  `slug` unique nullable e `archived_slug` non-unique indexado; o padrão de
  índices inclui `user_id/updated_at`, `user_id/name` e
  `visibility/archived_at/updated_at`.
- `PaletteQuota` conta apenas `colorPalettes()`; o limite vem de
  `colors.palettes.free_limit` (`COLORS_FREE_LIMIT`, default 25) e
  `paid_limit` (`null` = ilimitado).
- `CreatePalette`/`DuplicatePalette` usam `DB::transaction` +
  `User::query()->whereKey(...)->lockForUpdate()->first()` antes da contagem.
- `PaletteSlug` gera slug na primeira publicação, não muda ao renomear, move
  para `archived_slug` ao arquivar e `taken()` só olha `slug`.
- `PaletteTags::sync/copyTo/delete` são tipados para `ColorPalette`.
- `ToolProps` não conhece `layout-harmonizer`; `Seo::pageMeta`/`image()` e
  `Sitemap` só tratam paletas e tools.
- `deploy/deploy.sh` roda `git pull` e build **antes** de
  `php artisan migrate --force` (linha 143): código novo convive com schema
  antigo na janela do deploy.
- A F3 já exporta `evaluateContrast` e `LayoutSnapshot`; o motor não precisa
  mudar.
- `useLayoutSystem` tem `watch` imediato de seed/estratégia/fundo; hidratar sem
  latch regeneraria e poderia descartar overrides.
- O pivot de tags existente é `color_palette_color_tag`; o novo espelha o
  mesmo padrão.
- `public/og/layout-harmonizer.png` existe e pode servir a OG estática.
- Os protótipos vivem em `resources/js/tools/palette-generator/prototype/`,
  com site 1200x900, mobile 390x844 e 8 papéis por tema.

## Relatório de testes da fase

Preenchido ao fim da execução (ver relatório em
`docs/claude-code/reports/feature_layout-hamonizer-fase-4/`).

## Arquivos-chave

- Migrations: `database/migrations/2026_11_19_00000{1,2,3}_*.php`.
- Domínio: `app/Support/Colors/LayoutSystem*.php`, `app/Enums/LayoutStrategy.php`.
- Backend: `app/Actions/Colors/*LayoutSystem.php`,
  `app/Http/{Requests,Controllers}/Colors/*LayoutSystem*.php`,
  `routes/colors.php`.
- Frontend: `resources/js/tools/layout-harmonizer/lib/{state,frozen,draft,save}.ts`,
  `composables/{useFrozenLayout,useLayoutDraft,useUnsavedGuard}.ts`, páginas
  `dashboard/colors/LayoutSystemsIndex.vue`, `dashboard/colors/LayoutSystemEditor.vue`
  e `colors/PublicLayoutSystem.vue`.
- Protótipos: `resources/js/lib/prototype/` (movido).
