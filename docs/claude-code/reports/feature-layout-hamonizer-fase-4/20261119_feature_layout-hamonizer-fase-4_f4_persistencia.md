# Relatório — Layout Harmonizer F4 (persistência, compartilhamento e protótipos)

Data: 2026-11-19
Branch: `feature/layout-hamonizer-fase-4` (grafia da branch como está no repositório)
Fases anteriores: F1 (motor puro, 2026-11-16), F2 (interface do quadro, 2026-11-16) e
F3 (edição manual, 2026-11-18)
Autorização: grill de 2026-11-19 ("Pode iniciar a fase 4"), com decisões em
`docs/survey/20261119_layout_harmonizer_f4_surveyfacts.md`, plano de execução em
`docs/plans/feature_layout-hamonizer-fase-4/20261119_f4_persistencia_plansfacts.md`
e plano completo em
`docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261119_feature_layout-hamonizer-fase-4_f4_persistencia.md`.

## 1. Escopo entregue

A F4 persistiu o sistema de layout e fechou o ciclo de produto descrito em
`docs/colors/layout-harmonizer/PROMPT-Fase4-salvamento_compartilhamento_prototipos.md`:

- **tabela própria `layout_systems`** com o envelope `state` (entradas cruas +
  secundária efetiva + snapshot da F3), versões derivadas, tags por pivot e
  slugs (ADR-168);
- **reabrir sem recalcular**: `hydrateState` + latch `skipNext` no
  `useLayoutSystem`, com modos `live`, `compatible` e `readonly` (ADR-169);
- **cota conjunta** paletas + sistemas (Free 25, arquivados contam, pago
  ilimitado), com contador único e lock da linha `users` nos caminhos de
  criação/duplicação (ADR-152, ADR-170);
- **salvar** com nome sugerido/editável, descrição, visibilidade (privado
  padrão) e tags; editar não consome, "salvar como novo" consome e vincula ao
  novo (ADR-159);
- **rascunho anônimo** no `localStorage`, sem TTL, com importação/descarte
  explícitos após o login, sem salvar nem consumir cota (ADR-161);
- **ciclo de vida**: arquivar/restaurar/excluir, slug público que nunca é
  reemitido e ledger de slugs aposentados (ADR-164, ADR-171);
- **crédito de origem** congelado, com paleta privada nunca exposta e texto que
  sobrevive à exclusão da paleta (ADR-172);
- **duplicação** de sistema próprio ou público, sempre como cópia privada,
  com linhagem em `duplicated_from_id` (ADR-165);
- **página pública** read-only em `/{locale}/colors/s/{slug}`, com SEO,
  canonical/hreflang, OG estática e sitemap (ADR-154, ADR-174);
- **painel de protótipos** site/mobile e tema, reutilizando o renderizador da
  paleta promovido para `resources/js/lib/prototype/` (ADR-173);
- **i18n** nos 5 idiomas e **ADRs/docs** da fase.

Fora da fase, como aprovado: exportação MD/CSS/ZIP (F5), OG dinâmica,
curtidas/comentários/favoritos/seguir no sistema, perfil público próprio, API
JSON, `RoleEditor`/exportação de protótipo, dependências novas, commits e
push.

## 2. Arquivos

### Criados — backend

| Arquivo | Conteúdo |
|---|---|
| `database/migrations/2026_11_19_000001_create_layout_systems_table.php` | tabela, FKs, versões, slug, índices |
| `database/migrations/2026_11_19_000002_create_layout_system_retired_slugs_table.php` | ledger de slugs aposentados |
| `database/migrations/2026_11_19_000003_create_color_tag_layout_system_table.php` | pivot de tags |
| `app/Models/LayoutSystem.php` | ULID, casts, relações, escopos `forUser`/`active`/`archived`/`publiclyVisible` |
| `app/Enums/LayoutStrategy.php` | espelho de `LAYOUT_STRATEGIES` com `labelKey()` |
| `database/factories/LayoutSystemFactory.php` | fixture com estados pública/arquivada |
| `app/Support/Colors/LayoutSystemState.php` | validação completa do `state` v1 |
| `app/Support/Colors/LayoutSystemCatalog.php` | ids, famílias, pares e estratégias em PHP |
| `app/Support/Colors/LayoutSystemSlug.php` | base/unique/taken com defensiva de schema |
| `app/Support/Colors/LayoutSystemCredit.php` | captura congelada do crédito |
| `app/Support/Colors/LayoutSystemData.php` | DTO da action |
| `app/Support/Colors/LayoutSystemPresenter.php` | `row`, `editor`, `public`, `author` |
| `app/Support/Colors/LayoutSystemToolProps.php` | props do tool (cota, limites, tags, origem, versões) |
| `app/Actions/Colors/{Create,Update,Archive,Restore,Delete,Duplicate}LayoutSystem.php` | ciclo completo |
| `app/Http/Requests/Colors/{Store,Update,Delete,Duplicate}LayoutSystemRequest.php` | validação e confirmação de nome |
| `app/Http/Controllers/Colors/LayoutSystemController.php` | índice, editor, store/update/archive/restore/destroy |
| `app/Http/Controllers/Colors/LayoutSystemIntentController.php` | `create?palette=` e duplicação após login |
| `app/Http/Controllers/Colors/LayoutSystemDuplicationController.php` | duplicação autenticada e pública |
| `app/Http/Controllers/Colors/PublicLayoutSystemController.php` | página pública com 404 uniforme |

### Criados — frontend

| Arquivo | Conteúdo |
|---|---|
| `resources/js/types/layoutSystem.ts` | props/tipos das telas e do tool |
| `resources/js/tools/layout-harmonizer/lib/state.ts` | `LayoutSystemStateV1`, build/hydrate/mode/boards/materialize |
| `resources/js/tools/layout-harmonizer/lib/controller.ts` | interface `LayoutController` compartilhada |
| `resources/js/tools/layout-harmonizer/lib/frozen.ts` | quadro congelado do modo compatível |
| `resources/js/tools/layout-harmonizer/lib/regenerate.ts` | regeneração explícita (locks viram overrides) |
| `resources/js/tools/layout-harmonizer/lib/draft.ts` | rascunho puro, storage injetável |
| `resources/js/tools/layout-harmonizer/lib/save.ts` | payload, nome sugerido, truncamento, fingerprint |
| `resources/js/tools/layout-harmonizer/lib/inputs.ts` | normalização das entradas cruas |
| `resources/js/tools/layout-harmonizer/lib/layoutRoleMap.ts` | mapa fixo de papéis → protótipo |
| `resources/js/tools/layout-harmonizer/composables/useFrozenLayout.ts` | workspace do modo compatível |
| `resources/js/tools/layout-harmonizer/composables/useLayoutDraft.ts` | gravação com debounce |
| `resources/js/tools/layout-harmonizer/composables/useUnsavedGuard.ts` | guarda de navegação e recarregamento |
| `resources/js/tools/layout-harmonizer/components/SaveLayoutSystemDialog.vue` | salvar / salvar como novo |
| `resources/js/tools/layout-harmonizer/components/ImportDraftDialog.vue` | importar/descartar rascunho |
| `resources/js/tools/layout-harmonizer/components/LayoutSystemOriginBanner.vue` | origem da paleta e remover |
| `resources/js/tools/layout-harmonizer/components/LayoutSystemPrototypePanel.vue` | site/mobile e tema |
| `resources/js/components/colors/LayoutSystemCard.vue` | card com 6 HEX, 66 tokens, tags |
| `resources/js/components/colors/LayoutSystemActions.vue` | arquivar/restaurar/excluir/link |
| `resources/js/components/colors/LayoutSystemMetaForm.vue` | metadados do sistema |
| `resources/js/components/colors/DeleteLayoutSystemDialog.vue` | exclusão com nome digitado |
| `resources/js/pages/dashboard/colors/LayoutSystemsIndex.vue` | lista, filtros, ordenação, paginação e cota |
| `resources/js/pages/dashboard/colors/LayoutSystemEditor.vue` | editor vinculado com ações |
| `resources/js/pages/colors/PublicLayoutSystem.vue` | viewer público read-only |
| `resources/js/lib/prototype/` | renderizador promovido (inclui `copy.ts`) |

### Modificados

- **Backend**: `config/colors.php` (`layout_systems`, `tags.per_layout_system`),
  `app/Models/User.php`, `app/Models/ColorTag.php`,
  `app/Support/Colors/PaletteQuota.php` (soma conjunta),
  `app/Support/Colors/PaletteTags.php` (`syncTo`/`copyTo`),
  `app/Support/Seo.php` (meta da rota, tool slug e OG),
  `app/Support/Sitemap.php` (`layoutSystems()`), `app/Support/ToolProps.php`,
  `routes/colors.php`.
- **Frontend**: `LayoutWorkspace.vue` (controller + protótipo),
  `Tool.vue` (rascunho, origem, salvar), `useLayoutSystem.ts` (`initial` e
  `skipNext`), `LayoutHarmonizerPanel.vue`, `PaletteActions.vue` e
  `PublicPalette.vue` (CTA "criar sistema de layout"), `PalettePagination.vue`,
  `useAccountNav.ts`, `PaletteEditor.vue`, `paletteOg.ts` e os imports da
  imagem da paleta (`image/buildImage.ts`, `image/exportImage.ts`).
- **i18n**: `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` (`save`, `draft`, `origin`,
  `lifecycle`, `prototype`, `guard`), `colors.php` (`systems.*`,
  `meta.system_description`, `actions.create_layout_system`, cota neutra em
  `list.description*`) e `account.php` (`nav.layout_systems`).

## 3. Modelo de dados e backend

- `layout_systems` espelha as convenções de `color_palettes`: ULID, FK do dono
  com cascade, `duplicated_from_id` e `source_palette_id` com
  `nullOnDelete`, `state`/`source` em JSON, versões, `tokens_count`,
  `duplications_count`, `slug`/`archived_slug`/`archived_at` e índices por
  dono/visibilidade/data. O pivot de tags e o ledger de slugs são tabelas
  separadas, todas retrocompatíveis (só criam).
- `LayoutSystemState` valida o shape v1 por completo (temas, 33 tokens,
  famílias, 16 checks, cores, fonte, limites de overrides/locks/aceites,
  secundária coerente, tamanho) e o servidor deriva as colunas de versão e
  `tokens_count` — o cliente nunca envia versões separadas.
- `PaletteQuota::used()` soma paletas e sistemas, com `Schema::hasTable`
  defensivo na janela entre deploy e migrate; `Create`/`Duplicate` travam a
  linha de `users` antes da contagem e erram `colors.validation.limit_reached`
  quando cheio.
- Slug: nasce na primeira publicação, renomear não muda, arquivar move para
  `archived_slug`, excluir grava em `layout_system_retired_slugs` e nada é
  reemitido; a rota pública consulta o ledger antes do `firstOrFail`.
- Crédito: só `source_palette_id` vem do cliente; o servidor valida dono ou
  pública, congela nome/slug/visibilidade/autor e nunca expõe a FK no
  presenter público.
- SEO: `Seo::systemMeta` usa nome/descrição do dono com fallback
  `colors.meta.system_description` (estratégia + 66 tokens, defensivo a
  tabela inexistente), OG estática `og/layout-harmonizer.png`, canonical e
  hreflang sem query; `Sitemap::layoutSystems()` limita a 500 públicos ativos.

## 4. Frontend

- `hydrateState` devolve entradas/secundária/snapshot/modo; textos que não
  parseiam para a cor gravada são normalizados para o HEX efetivo, para o
  quadro reaberto ser idêntico ao salvo. O `initial` preenche o composable e um
  latch consome a primeira execução do `watch` — sem remontar o quadro.
- Modo compatível (`useFrozenLayout`): edição atualiza `rgba`/`hex`/`oklch` e
  os checks do tema com o `evaluateContrast` do motor, preservando versões;
  restaurar e sugestões desabilitados; entradas marcam regeneração pendente e
  bloqueiam o salvamento; regenerar gera com a versão atual levando locks como
  overrides. Modo `readonly` mostra o quadro sem edição, com ciclo de vida e
  duplicação.
- Salvamento: modal com nome sugerido (paleta ou estratégia+seed), visibilidade
  privada por padrão, tags, erros do servidor e cota; "salvar como novo"
  duplica e redireciona ao novo editor.
- Rascunho: gravação com debounce só para anônimo, chave
  `devtools.layout-harmonizer.draft.v1`, sem TTL; `pending`/`imported`/
  `refused`; shape inválido é removido, versão desconhecida só permite
  descartar; importar depois do login hidrata sem salvar.
- Guarda de alterações compara com a baseline e intercepta navegação do
  Inertia e `beforeunload`, liberando o salvamento confirmado.
- Lista/editor/pública: busca, filtros por status e tag, ordenação, paginação
  server-side, cota "X de 25 itens"; editor com cabeçalho de ações; pública
  read-only como vitrine do snapshot com CTA de duplicação.
- Protótipos: `lib/prototype/` promovido sem `git add`/`git mv`, `copy.ts`
  extraído do painel da paleta, mapa fixo de 8 papéis e painel no workspace
  autenticado (sem `RoleEditor`/exportação).

## 5. i18n

- Novos grupos em `tools.php`: `save`, `draft`, `origin`, `lifecycle`,
  `guard` (além de `prototype`, da etapa 7), com variáveis `{max}`, `{limit}`,
  `{name}`, `{strategy}`, `{hex}`, `{template}` e `{theme}`.
- Em `colors.php`: `systems.{list,editor,public,delete,actions,strategy,toasts}`,
  `meta.system_description` (placeholders Laravel `:strategy`/`:tokens`) e
  `actions.create_layout_system`; `list.description`/`description_unlimited`
  foram reescritos com `{used}`/`{limit}` — antes usavam `:used`/`:limit`, que
  o vue-i18n não interpola (bug latente corrigido).
- `account.nav.layout_systems` nos 5 idiomas.
- Verificação automática: um script temporário (fora do repositório) cruzou
  179 chaves usadas pelas telas/do código com os 5 locales — 0 ausentes.
  `TranslationsParityTest` (5 testes) e `layout-harmonizer-i18n.test.ts`
  (3 testes) passam; o bloco `tools.layout-harmonizer` não tem `@`/`|` crus.

## 6. ADRs e documentação

- **Copiados com numeração global** (conteúdo preservado + seção
  "Implementação (F4)"): ADR-152 (quota), ADR-154 (público), ADR-159
  (cadastro), ADR-160 (downgrade), ADR-161 (rascunho), ADR-164 (ciclo de vida)
  e ADR-165 (duplicação). Os originais locais permanecem em
  `docs/colors/layout-harmonizer/adr/` até o aceite da fase, como nas fases
  anteriores.
- **Novos**: ADR-168 (persistência e snapshot), ADR-169 (edição
  compatível/congelada), ADR-170 (cota conjunta: contador único e locks),
  ADR-171 (slug aposentado), ADR-172 (crédito de origem), ADR-173 (protótipos
  no workspace) e ADR-174 (viewer público, rota e SEO).
- Atualizados: `ADR-MAPEAMENTO.md` (status e contagem), `docs/colors/README.md`,
  `docs/SPEC.md` (§5 e §9), `docs/glossario.md` (7 termos novos) e
  `PARAMETROS.md` (parâmetros e versões da persistência, modo compatível).

## 7. Testes

### Pest

- `LayoutSystemStateTest` — fixture v1 válida e rejeições (versão, tokens,
  cores, secundária, tamanho).
- `LayoutSystemCatalogTest` — paridade PHP ↔ TS de ids/famílias/pares.
- `LayoutSystemSlugTest` — base/unique/taken, reservados e aposentados.
- `LayoutSystemQuotaTest` — cota conjunta, arquivados, liberação e lock.
- `LayoutSystemActionsTest` — create/update/archive/restore/delete/duplicate,
  crédito e linhagem.
- `LayoutSystemHttpTest` — auth, 404 de outro dono, filtros, limites, toasts.
- `LayoutSystemPublicHttpTest` — público/privado/arquivado/retirado,
  props do público, origem pública/privada/excluída, duplicação.
- `LayoutSystemSeoTest` — meta com e sem descrição, OG estática, canonical e
  sitemap.
- Atualizados: `TranslationsParityTest` e `ToolPageTest`.

### Vitest

- `layout-harmonizer-state` — round-trip com textos crus/alpha, secundária,
  target, overrides/locks/aceites e `materializeResult`.
- `layout-harmonizer-hydration` — não regenera na montagem, baseline e
  descarte.
- `layout-harmonizer-frozen` — patch de token só mexe no tema/checks, versões
  preservadas, restore/sugestões bloqueados e regeneração explícita.
- `layout-harmonizer-draft` — chave, sem TTL, shape inválido, status, debounce
  e SSR-safe.
- `layout-harmonizer-save` — payload, nome sugerido, save-as-new, cota e
  guarda.
- `layout-harmonizer-prototype` — mapa de papéis e painel site/mobile/tema,
  sem `RoleEditor`/exportação.
- `layout-system-list`, `layout-system-editor`, `layout-system-public` — telas.
- Atualizados: `layout-harmonizer-workspace` (timeout de montagem pesada sob
  suite paralela), `palette-list`, `palette-image-export`,
  `prototype-*`, `account-panel`.

## 8. Gates reais

| Gate | Resultado |
|---|---|
| `composer test` | **verde** — Pint OK, PHPStan 0 erros, Pest 1828/1828 (31.857 asserções; 1 deprecação de vendor) |
| `npm run test` | **verde** — 154 arquivos, 1865/1865 (Vitest 5.0.4) |
| `npm run types:check` | **verde** — Wayfinder + `vue-tsc --noEmit` sem erros |
| `npm run format:check` | **verde** — Prettier sem pendências |
| `npm run lint:check` | **verde** — 0 erros (26.803 avisos `vue/html-indent`/`max-attributes-per-line` pré-existentes no repositório) |
| `npm run build` | **verde** — build Vite concluído |

Correções feitas durante os gates:

- Pint acusou `app/Models/LayoutSystem.php` (ordem de imports, separação de
  atributos, FQCN) — corrigido pelo próprio Pint.
- O teste de SEO calculava a estratégia da descrição padrão sem o locale do
  loop, então a expectativa misturava idiomas depois que
  `colors.meta.system_description` passou a receber a estratégia; a expectativa
  agora traduz a estratégia com `$code`.
- O teste de salvamento ainda esperava a chave crua
  `tools.layout-harmonizer.save.name_required`; agora a chave existe e ele
  confere a string real ("Informe um nome.").
- `EXTRAS` provisórios em `layout-system-editor.test.ts` e
  `layout-system-public.test.ts` foram removidos (as chaves reais já existem);
  três asserções passaram a conferir o texto real.
- `layout-harmonizer-workspace.test.ts` ganhou `testTimeout` de 20 s, como os
  demais testes pesados, por flakiness de 5 s sob a suíte paralela.
- Prettier acusou 8 arquivos de etapas anteriores; `Tool.vue` tinha um import
  não usado (`browserLocalStorage`) que o ESLint flagrava como erro — ambos
  corrigidos.

## 9. Verificação manual

Executado:

- `php artisan migrate` local — as três migrations da F4 e as pendências
  anteriores do banco local foram aplicadas com sucesso.

Pendente (checklist do plano — recomendo conferir na revisão):

- rollback/re-migrate das três migrations (o batch local inclui migrations
  antigas; um `migrate:rollback` simples desfaria mais que a F4);
- duas abas disputando a última vaga de cota com `COLORS_FREE_LIMIT=2`;
- fluxo completo no navegador: anônimo → rascunho → login → importar/descartar
  → salvar; guarda de alterações no editor;
- ciclo de vida ponta a ponta (arquivar → 404 e fora do sitemap; restaurar;
  excluir com nome; recriar com o mesmo nome → slug diferente);
- período nos 5 idiomas, sem chave crua em nenhuma tela.

## 10. Pendências e próximos passos

- **F5 (exportação MD/CSS/ZIP) não foi iniciada** — conforme o plano, a fase
  para aqui.
- Exportação de protótipo e `RoleEditor` continuam fora do Layout Harmonizer.
- Os originais locais dos 7 ADRs copiados permanecem em
  `docs/colors/layout-harmonizer/adr/` até o aceite; só faltarão os ADRs 148 e
  155 (F5).
- Itens não rastreados que já estavam no diretório de trabalho
  (`docs/assets/img/*.svg`, `docs/gemini/`, survey de tutorial do BadgeForge e
  a exclusão de `docs/assets/img/composer_ico`) **não foram tocados**.

## 11. Parada

Sem commit e sem push: o usuário commita e abre o PR pelo `gitpr`, aprovando
cada um. A F4 está pronta para revisão.
