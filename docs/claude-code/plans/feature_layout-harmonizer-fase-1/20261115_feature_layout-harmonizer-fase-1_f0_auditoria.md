# Layout Harmonizer — plano da F0 (auditoria + plano), sem código

Data: 2026-11-15
Branch: `feature/layout-harmonizer-fase-1`
Origem: `/grill-with-docs` sobre `docs/colors/layout-harmonizer/PROMPT-INICIAL-CLAUDE.md`
Aprovação: plano aprovado pelo usuário; execução autorizada somente para a F0.

## Contexto

O pacote `docs/colors/layout-harmonizer/` (v1.0, 2026-11-09) especifica uma nova ferramenta do grupo Colors: uma cor vira um sistema semântico de layout com quadro visual, temas claro/escuro e exportações MD/CSS/ZIP. O `PROMPT-INICIAL-CLAUDE.md` pede **somente F0**: inspecionar o Colors real, apontar diferenças entre código e requisitos aprovados, listar arquivos/dependências/riscos/testes e os parâmetros técnicos a calibrar. Nenhum código de aplicação nesta sessão.

A auditoria confirmou que o pacote é só documentação (nada em `app/`, `resources/`, `routes/`, `lang/`); mas a infraestrutura que ele supõe **existe e é reutilizável**: registro de tools, picker, núcleo de cor próprio (OKLab/OKLCH/gamut/contraste), protótipos SVG, persistência de paletas com cota/slug/arquivo, viewer público, sitemap e i18n em cinco idiomas.

## Decisões fechadas no grill (2026-11-15)

| Tema | Decisão |
|---|---|
| Escopo | **Somente F0**, sem código; parar para revisão |
| Artefatos | 4 arquivos: report + plano em `docs/claude-code/`, `docs/plans/*_plansfacts.md`, `docs/survey/*_surveyfacts.md` |
| Persistência | **Nova tabela `layout_systems`** (não discriminador em `color_palettes`) |
| Viewer público | **Nova rota** `/{locale}/colors/s/{slug}` |
| Protótipos | **Promover** `tools/palette-generator/prototype/` a módulo compartilhado |
| Dependências | Núcleo de cor interno (sem lib nova); `fflate` direto só na F5 |
| Gestão do dono | Página própria `/dashboard/colors/layout-systems` |
| Features sociais | **Fora de escopo**: sem curtidas, comentários, favoritos, seguir, imagem-de-paleta, OG dinâmica; sistema público não entra em `/{locale}/u/{username}` |
| i18n | Novo namespace `layout_harmonizer` + `tools.layout-harmonizer.*` |
| Limites | Nome 80, descrição 500, configuráveis em `config/colors.php` |
| ADRs | Renumerar após **ADR-144**, minúsculo: `ADR-145-colors-lh-<escopo>.md`; copiar por fase |
| Tags | Novo pivô `color_tag_layout_system` |
| Rascunho | `localStorage`, chave `devtools.layout-harmonizer.draft.v1` |
| Docs globais | Atualizar `docs/SPEC.md`, `docs/glossario.md`, `docs/colors/README.md` |

## Entregável F0 (o que será criado/alterado ao aprovar)

1. `docs/claude-code/reports/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md`
2. `docs/claude-code/plans/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md`
3. `docs/plans/feature_layout-harmonizer-fase-1/20261115_f0_auditoria_plansfacts.md`
4. `docs/survey/20261115_layout_harmonizer_f0_surveyfacts.md`
5. `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` (tabela abaixo)
6. `docs/SPEC.md` (seção 9: track Colors → LH; catálogo da seção 5 aponta o grupo)
7. `docs/glossario.md` (seed efetiva, token semântico, on-color, bloqueio, snapshot, sistema de layout, cota conjunta, Accent)
8. `docs/colors/README.md` (linha de índice apontando `layout-harmonizer/`)

Nada de código, migration, dependência ou commit. Sem tocar nos documentos e ADRs do pacote LH.

## Diferenças: código real × requisitos aprovados

| Requisito | O que existe hoje | Lacuna |
|---|---|---|
| Tool registrada em `/tools/colors/layout-harmonizer` | Registro por `resources/js/tools/<slug>/{manifest.json,Tool.vue}`; rota genérica `tools.show.grouped` (ADR-116) | Sem diretório/manifest; criar na F2 |
| Picker reutilizado | `components/colors/ColorPickerControls.vue` + `useColorState` (HEX/RGB/HSL/OKLCH/OKLab, alpha, eyedropper) | Reuso direto; nada a criar |
| Conversões, gamut, contraste | `resources/js/lib/color/` (OKLab Ottosson, `mapOklchToSrgb` por busca binária, `contrastRatio`, `compositeOver`) | Falta camada de tokens/motor (F1) |
| Protótipos site/mobile | `tools/palette-generator/prototype/` (SVG puro, 8 `PaletteRole`, PNG/SVG) | Está preso ao palette-generator e só conhece 8 papéis; promover + adaptar |
| Persistência de sistema (2 temas, overrides, locks, sugestões, snapshot) | `color_palettes` só tem `kind` `scale\|extracted`; `roles` = 8 papéis × 2 temas | Nova tabela + estado versionado próprio (F4) |
| Cota conjunta 25 Free | `PaletteQuota` conta só `colorPalettes()`; lock do usuário em Create/Duplicate | Somar `layout_systems` na cota (F4) |
| Slug público imutável | `PaletteSlug` lê `colors.slug.reserved` e a tabela de paletas | Extrair base comum e criar reservados/tabela do LH (F4) |
| Viewer público consigo | `PublicPaletteController` + `pages/colors/PublicPalette.vue`, 404 uniforme, OG | Nova rota/página/controller; sem OG (F4) |
| Sitemap | `Sitemap::palettes()` com teto de config | Adicionar `layoutSystems()` com teto próprio (F4) |
| Minhas paletas/tags | `/dashboard/colors/palettes`, pivô `color_palette_color_tag` | Página própria + pivô novo (F4) |
| Rascunho anônimo | `lib/saveDraft.ts` usa `sessionStorage` e é acoplado a paleta | `localStorage` versionado próprio + importação consentida (F2) |
| Exportações MD/CSS/ZIP | Exportadores CSS/SCSS/JSON/DTCG/Tailwind de paleta; **sem MD, sem ZIP**, sem `data-theme`/fallback OKLCH para tokens de layout | Criar exportadores LH; `fflate` só na F5 |
| i18n 5 idiomas | Namespaces registrados em `config/locales.php`; paridade testada | Novo namespace `layout_harmonizer` (F2) |
| Gating de acesso | Tool é pública; dashboard sob `auth,verified,active`; quota por action | Exportação só autenticada (gate de produto no cliente); cópia individual livre |
| ADRs | 22 `ADR-LH-*` fora de `docs/adr`; último global é `ADR-144` | Copiar renumerando por fase (tabela abaixo) |

## Modelo de dados proposto (F4)

Migration **aditiva**, sem tocar `color_palettes`:

`layout_systems`: `ulid id` PK; `user_id` FK cascade; `duplicated_from_id` self FK nullOnDelete; `name(80)`, `description(text, null)`; `visibility(10, default private)`; `slug(60, nullable unique)`; `archived_slug(60, nullable)`; `archived_at`; `schema_version`, `algorithm_version` (smallint); `state` JSON (seed original/efetiva, alpha+fundo, estratégia, secondary manual, tokens por tema, overrides/locks, ajustes aceitos, sugestões, diagnósticos); `tokens_count`; `duplications_count` default 0; timestamps; índices espelhando `color_palettes` (`(user_id, archived_at)`, `(user_id, updated_at)`, `(user_id, name)`, `(visibility, archived_at, updated_at)`).

`color_tag_layout_system`: `foreignUlid layout_system_id` + `foreignUlid color_tag_id`, PK composta, cascade.

Integrações: `User::layoutSystems()`; `ColorsQuota`/`PaletteQuota::used()` soma as duas tabelas sob o mesmo lock de usuário; `LayoutSystemSlug` sobre uma base `ColorsSlug` extraída de `PaletteSlug` (comportamento de paleta inalterado); `Sitemap::layoutSystems()` + `colors.layout_systems.sitemap_limit`; rotas em `routes/colors.php`.

## Mapa de ADRs (renumeração após ADR-144, cópia por fase)

| LH | Novo arquivo | Fase |
|---|---|---|
| 001 Identidade | `ADR-145-colors-lh-identidade.md` | F0 |
| 002 Quadro semântico | `ADR-146-colors-lh-quadro-semantico.md` | F1 |
| 003 Geração | `ADR-147-colors-lh-geracao.md` | F1 |
| 004 Exportações | `ADR-148-colors-lh-exportacoes.md` | F5 |
| 005 Seed | `ADR-149-colors-lh-seed.md` | F1 |
| 006 Processo | `ADR-150-colors-lh-processo.md` | F0 |
| 007 Bloqueios | `ADR-151-colors-lh-bloqueios.md` | F3 |
| 008 Quota | `ADR-152-colors-lh-quota.md` | F4 |
| 009 Entradas | `ADR-153-colors-lh-entradas.md` | F2 |
| 010 Público | `ADR-154-colors-lh-publico.md` | F4 |
| 011 CSS | `ADR-155-colors-lh-css.md` | F5 |
| 012 Harmonias | `ADR-156-colors-lh-harmonias.md` | F1 |
| 013 Variantes | `ADR-157-colors-lh-variantes.md` | F1 |
| 014 Contraste | `ADR-158-colors-lh-contraste.md` | F1 (retomada na F3 para AA/AAA) |
| 015 Cadastro | `ADR-159-colors-lh-cadastro.md` | F4 |
| 016 Downgrade | `ADR-160-colors-lh-downgrade.md` | F4 |
| 017 Rascunho | `ADR-161-colors-lh-rascunho.md` | F2 |
| 018 Copiar cor | `ADR-162-colors-lh-copiar-cor.md` | F2 |
| 019 Accent | `ADR-163-colors-lh-accent.md` | F1 |
| 020 Ciclo de vida | `ADR-164-colors-lh-ciclo-de-vida.md` | F4 |
| 021 Duplicação | `ADR-165-colors-lh-duplicacao.md` | F4 |
| 022 Temas | `ADR-166-colors-lh-temas.md` | F1 |

Decisões novas do grill (tabela própria, rota dedicada, promoção dos protótipos, cota conjunta, `fflate`) viram **ADR-167+**, criados na fase em que são implementados (F2/F4/F5), não em F0.

## Calibração — eixos e candidatos (aprovação na F1, nada implementado)

Eixos: L (luminosidade OKLCH) por papel/tema; C (croma) por família e teto de gamut; H de feedback fixo; mapeamento para sRGB; variantes (variant ≠ hover ≠ active); feedback estável; composição de alpha; sugestões AA/AAA; fixtures.

Regras já decididas: Primary = seed efetiva exata até aceite explícito; claro e escuro gerados juntos; análoga +30°, complementar +180°, triádica +120/+240°, monocromática com H constante variando L/C; feedback independente da seed; variantes separadas de hover/active; seed acromática não inventa matiz.

Candidatos **provisórios** (todo número abaixo é proposta a calibrar e aprovar na F1):

| Papel | Claro L / C | Escuro L / C |
|---|---|---|
| background | 0.985 / 0.006 (H seed) | 0.160 / 0.015 (H seed) |
| surface | 1.000 / 0.003 | 0.200 / 0.018 |
| surface-variant | 0.955 / 0.010 | 0.245 / 0.020 |
| hairline | 0.900 / 0.012 | 0.330 / 0.022 |
| on-background | 0.220 / 0.015 | 0.955 / 0.006 |
| on-surface | 0.260 / 0.015 | 0.930 / 0.008 |
| on-surface-variant | 0.480 / 0.020 | 0.740 / 0.018 |
| primary | seed exata (L/C/H) | seed exata |
| primary-variant | L − 0.10 (piso 0.30), C × 0.90 | L + 0.10 (teto 0.85), C × 0.90 |
| primary-hover / active | L − 0.05 / − 0.10 | L + 0.05 / + 0.10 |
| secondary (offset) | L seed / C × 0.85 | idem |
| accent (triádica) | L seed / C seed, H + 120° | idem |
| link / hover / active | L seed / −0.05 / −0.10 | L seed / +0.05 / +0.10 |
| focus-ring | 0.55 / 0.15 | 0.75 / 0.15 |
| error (H 25°) | 0.55 / 0.19 | 0.68 / 0.18 |
| success (H 145°) | 0.55 / 0.15 | 0.72 / 0.15 |
| warning (H 75°) | 0.75 / 0.16 | 0.80 / 0.15 |
| info (H 245°) | 0.55 / 0.14 | 0.72 / 0.14 |
| on-* (conteúdo) | branco (L 1.0) ou escuro (L 0.18), o de maior razão | idem |
| superfícies/textos | C limitado a ≤ 0.012 / ≤ 0.020 (não herda croma da seed) | idem |

- **Gamut**: reusar `mapOklchToSrgb` (busca binária de C, 24 iterações, ε 1e-3); cores derivadas mapeadas, seed original preservada sem mapeamento.
- **Seed acromática** (C < 0.02, preto/branco): superfícies neutras com H documentado (0°) e C 0; sem matiz aleatório.
- **Alpha**: compor `compositeOver` sobre fundo escolhido; guardar original e efetiva; todas as contas usam a efetiva.
- **Contraste**: classificar sem arredondar; AA 4.5/3, AAA 7/4.5; UI mostra 1–2 casas.
- **Fixtures F1** (4 estratégias × 2 temas cada): `#3B82F6`, `#10B981`, `#EF4444`, `#F59E0B`, `#7C3AED`, `#000000`, `#FFFFFF`, `#808080`, `oklch(0.70 0.35 20)` (fora do gamut), `rgba(59,130,246,0.6)` sobre `#FFFFFF`. Cada snapshot: HEX + OKLCH + `clipped` + pares de contraste.

## Plano de arquivos por fase

**F1 — motor puro** (sem UI/persistência)
- Criar `resources/js/tools/layout-harmonizer/engine/`: `types.ts`, `tokens.ts` (catálogo de 33 tokens), `params.ts` (tabela versionada acima), `generate.ts`, `strategies.ts`, `contrast.ts`, `alpha.ts`, `index.ts` — funções puras, sem Vue.
- Reusar `@/lib/color` (`rgbToOklch`, `mapOklchToSrgb`, `contrastRatio`, `compositeOver`, `parseColor`).
- Testes em `resources/js/__tests__/layout-harmonizer-{engine,strategies,contrast,gamut,alpha,fixtures}.test.ts`.
- Docs: copiar ADR-146/147/149/156/157/158/163/166; criar `docs/colors/layout-harmonizer/PARAMETROS.md` com a tabela aprovada.

**F2 — UI e registro**
- Criar `resources/js/tools/layout-harmonizer/{manifest.json,Tool.vue,LayoutWorkspace.vue}` + componentes (`SeedPicker`, `StrategySelect`, `ThemeBoard`, `TokenGroup`, `TokenCell`, `ThemeComparison`, `FeedbackPreview`) e composables (`useLayoutSystem`, `useLayoutDraft`).
- `manifest.json`: slug `layout-harmonizer`, `status: available`, `category/group: colors`, `order: 120`, `icon: layout-panel-left`; adicionar o ícone em `resources/js/tools/icons.ts`.
- Promover `resources/js/tools/palette-generator/prototype/*` → `resources/js/lib/prototype/*` (atualizar imports no palette-generator e nos testes); adaptador LH → 8 `PaletteRole` em `resources/js/tools/layout-harmonizer/prototype/roleMap.ts`.
- Backend só de props: `app/Support/Colors/LayoutHarmonizerToolProps.php` + entrada em `app/Support/ToolProps.php`; bloco `layout_systems` em `config/colors.php`.
- i18n: `lang/{en,pt_BR,pt_PT,fr,es}/layout_harmonizer.php` + namespace em `config/locales.php`; `tools.layout-harmonizer.*` em `lang/*/tools.php`.
- Testes: novos testes de componente + atualizar `tools-registry.test.ts`, `tool-page.test.ts`, `ToolsRegistryTest.php`, `ToolPageTest.php`.
- Docs: ADR-153/161/162; ADR novo da promoção dos protótipos.

**F3 — edição, bloqueios, contraste**
- `engine/overrides.ts`, `engine/suggestions.ts`; `TokenEditor.vue`, `SuggestionDialog.vue`, `ContrastWarnings.vue`; meta AA/AAA só diagnosticando (nunca reescreve).
- Testes de lock por token/tema, aceite que mantém lock, restauração explícita, manuais preservados.
- Docs: ADR-151.

**F4 — dados, viewer, quota**
- Migrations acima; `app/Models/LayoutSystem.php`; `app/Support/Colors/{LayoutSystemState,LayoutSystemPresenter,LayoutSystemSlug,LayoutSystemName}.php`; base `ColorsSlug`.
- Actions: `CreateLayoutSystem`, `UpdateLayoutSystem`, `DuplicateLayoutSystem`, `ArchiveLayoutSystem`, `RestoreLayoutSystem`, `DeleteLayoutSystem`.
- Controllers/requests: `LayoutSystemController`, `LayoutSystemDuplicationController`, `LayoutSystemIntentController`, `PublicLayoutSystemController`, requests correspondentes.
- Rotas em `routes/colors.php`: dashboard `/dashboard/colors/layout-systems*` (auth, `throttle:colors-management`) e público `/{locale}/colors/s/{slug}` (+ `duplicate` GET/POST).
- Cota: `PaletteQuota::used()` soma tabelas (docblock atualizado) ou `ColorsQuota` equivalente; lock do usuário preservado.
- `Sitemap.php`: `layoutSystems()` com `colors.layout_systems.sitemap_limit`.
- Páginas: `resources/js/pages/colors/LayoutSystemsIndex.vue`, `LayoutSystemEdit.vue`, `PublicLayoutSystem.vue`; cards/dialog de salvar/excluir; reusar `TagInput`.
- Testes Pest: actions, HTTP dashboard, público 404, cota conjunta/downgrade/arquivo, sitemap, slug, presenter; factorie `LayoutSystemFactory`.
- Docs: ADR-152/154/159/160/164/165 + ADR novo da tabela própria.

**F5 — exportações locais**
- `resources/js/tools/layout-harmonizer/export/{markdown,css,zip}.ts`; `ExportPanel.vue`; gate de autenticação no cliente (snapshot congelado, sem regenerar).
- Declarar `fflate` direto em `package.json` (MIT 0.8.3 já em `node_modules`) — com autorização e ADR antes do `npm install`.
- Testes: MD/CSS/ZIP do mesmo snapshot, prefixo/escopo inválidos, fallback HEX antes de OKLCH, avisos preservados.
- Docs: ADR-148/155 + ADR do `fflate`.

**F6 — aceite**
- Matriz requisito → implementação → teste de `TESTES.md`; `composer test`, `npm run test`, `npm run types:check`, `npm run build`; runbook; atualização de docs; sem deploy/commit.

## Riscos e mitigação

1. **Cota conjunta mexe em código existente** (`PaletteQuota` + testes de paleta): somar as duas tabelas mantém o comportamento atual enquanto não há sistemas; teste dedicado de cota conjunta.
2. **Refactor de slug** usado por Create/Update/Restore: extrair base sem mudar comportamento; `PaletteSlugTest` existente é a rede.
3. **Promoção dos protótipos** altera imports/snapshots do palette-generator: rodar os testes de protótipo na mesma etapa.
4. **Paridade i18n em 5 idiomas** (~200 chaves): `TranslationsParityTest` falha se faltar chave; entregar as cinco no mesmo commit.
5. **Snapshot congelado**: export nunca regenera; teste que compara quadro e arquivo byte a byte.
6. **Fallback CSS OKLCH**: testar HEX primeiro e OKLCH depois; navegador sem OKLCH não pode perder cor.
7. **~33 tokens × 2 temas por frame**: não recalcular a cada frame; não anunciar slider a cada frame; preservar foco/scroll.
8. **Privacidade pública**: viewer/export público não expõem overrides, locks, sugestões nem origem; 404 uniforme para privado/arquivado/inexistente.
9. **Migrations sem downtime**: só `create_*` aditivas; nada de alterar `color_palettes` ou pivô existente.
10. **Nenhum default silencioso**: a tabela numérica só vale depois da aprovação da F1; parâmetros não aprovados ficam marcados como pendentes.
11. **Dependência nova** (`fflate`): exige autorização explícita na F5 e ADR.
12. **Concorrência criar/duplicar sistema × paleta**: o lock da linha do usuário já é o padrão; teste concorrente na F4.

## Testes (proposta, por fase)

- **F1 (Vitest)**: determinismo; Primary idêntica à seed até aceite; quatro estratégias; seeds extremas/acromáticas; gamut; alpha; variantes ≠ estados; feedback estável; contraste sem arredondar; fixtures snapshot.
- **F2 (Vitest/Pest)**: registro/URL/404 do manifest; picker; quadro; troca de estratégia; i18n 5 idiomas; props do servidor.
- **F3 (Vitest)**: overrides/bloqueios por token e tema; aceite mantém lock; restaurar é explícito; AA/AAA não altera valores.
- **F4 (Pest)**: auth, dono (404), quota conjunta 25 com arquivadas, concorrência paleta×sistema, downgrade, arquivar/restaurar/excluir, duplicação de público, slug não reutilizado, viewer/sitemap/canonical, privado/arquivado 404, tags.
- **F5 (Vitest)**: MD/CSS/ZIP do mesmo snapshot; prefixo/escopo inválidos; fallback OKLCH; avisos; auth de produto.
- **F6**: matriz de aceite + `composer test`, `npm run test`, `npm run types:check`, `npm run build`; testes manuais de teclado/leitor, mobile e CSS aplicado.

## Verificação

- **F0**: revisar os 8 artefatos; `git status` deve mostrar apenas docs; nenhum código/teste alterado. Opcional: registrar baseline de `npm run test` e `composer test` no relatório, sem tratar falha pré-existente como nova.
- **F1**: `npx vitest run resources/js/__tests__/layout-harmonizer-*.test.ts` + `npm run test` + `npm run types:check`; conferir a tabela de parâmetros contra os fixtures aprovados.
- **F2**: abrir `/{locale}/tools/colors/layout-harmonizer` nos 5 idiomas; teclado/foco; `npm run test` e `composer test`.
- **F4**: `php artisan test --filter=LayoutSystem`; criar 25 sistemas + paletas e confirmar bloqueio conjunto; arquivar/restaurar/excluir; duplicar público; confirmar 404 de privado/arquivado e presença no sitemap só quando público.
- **F5**: aplicar o CSS exportado em página de teste com e sem suporte a OKLCH; abrir o ZIP e comparar com o quadro; confirmar que anônimo não exporta.
- **F6**: rodar a matriz inteira e registrar resultados reais, sem declarar concluído o que não foi testado.

## Fora de escopo (nesta feature)

Curtidas, comentários, denúncias, notificações, seguir, favoritos, imagem-de-paleta, OG dinâmica, perfil público, API JSON, IA para gerar cores ou instruções, backend para geração, deploy, commit/push (usuário commita pelo gitpr).

## Próximos passos

1. Aprovar este plano (F0).
2. Executar F0: criar os 8 artefatos e atualizar as três docs globais; parar.
3. Somente após revisão, iniciar F1 com a tabela numérica a calibrar.

> Ponto de parada: nenhuma fase avança sem aprovação explícita; cada fase termina com testes verdes e relatório próprio.
