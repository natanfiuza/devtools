# Layout Harmonizer — F4 (persistência, compartilhamento e protótipos)

## Context

F1–F3 entregaram o motor puro, o quadro semântico e a edição manual, sem persistir nada. A F4 fecha o ciclo de produto descrito em `docs/colors/layout-harmonizer/PROMPT-Fase4-salvamento_compartilhamento_prototipos.md`: salvar o sistema com nome/descrição/tags/visibilidade, cota conjunta com as paletas, ciclo de vida com slug público, viewer público, rascunho anônimo, duplicação e protótipos reutilizados no workspace. A branch é `feature/layout-hamonizer-fase-4` (grafia da branch como está no repositório) e a data é 2026-11-19.

O problema central é que o estado efetivo da F3 só existe em memória: o `LayoutSnapshot` não guarda os textos crus da entrada, o RGB da secundária manual, nem uma forma de hidratar o composable sem regenerar. A F4 precisa persistir exatamente o que a tela mostra, reabrir sem recalcular e preservar o comportamento da F3 quando o sistema salvo for da versão atual.

Restrições em vigor: nada de commit/push (o usuário usa o `gitpr`); nenhuma instalação de dependência, migration em produção ou sobrescrita de CLAUDE.md/ADRs; toda string visível via i18n nos 5 idiomas; tools do cliente rodam 100% no navegador; migrations retrocompatíveis; não reabrir decisões de produto aprovadas; não inventar defaults silenciosos.

## Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Armazenamento | Tabela própria `layout_systems` (sem discriminador em `color_palettes`; um discriminador vazaria o sistema para curtidas/comentários/favoritos/perfil/OG, que são só de paleta) |
| Cota | Conjunta: paletas + sistemas, Free 25, arquivados contam, pago ilimitado |
| Concorrência | Mesmo lock da linha `users` usado por `CreatePalette`/`DuplicatePalette` |
| Rascunho | Só anônimo, último rascunho local até limpar, chave própria |
| Login | Pergunta antes de importar; importar hidrata sem salvar nem consumir cota |
| Salvar | Cria item e abre o editor vinculado; editar não consome, “salvar como novo” consome e vincula ao novo |
| Protótipos | Só no workspace autenticado, site/mobile existentes, mapa fixo de papéis, sem editor de papéis/exportação |
| Limites | Nome 80, descrição 500, configuráveis; tags por sistema configurável |
| Origem | FK para a paleta + crédito congelado no salvamento; fonte privada nunca aparece no público |
| Origem privada | Crédito público omitido; crédito público permanece textual mesmo se a paleta sumir (link some) |
| Slug | Nasce na primeira publicação; arquivar reserva; excluir aposenta para sempre |
| URLs antigas | Link público de item arquivado/excluído responde 404 permanente |
| SEO | Sitemap + OG estática `public/og/layout-harmonizer.png`; sem OG dinâmica por sistema |
| Entrada pela paleta | CTA no editor e na página pública da paleta, abre o tool com a **cor base** da paleta como seed editável |
| Nome sugerido | Com origem: “Sistema de layout — {nome da paleta}”; sem origem: “{estratégia} — {hex}”; sempre editável |
| Versões antigas | Editar se o snapshot for suportado; versões mais novas/desconhecidas são somente leitura |

## Escopo

**Entra:** migrations `layout_systems`, retired slugs e pivot de tags; model/actions/requests/controllers/rotas; cota conjunta; slug e ciclo de vida; crédito de origem; props do tool e páginas de lista/editor; hidratação e modo compatível; save/save-as-new; rascunho anônimo; página pública e duplicação; protótipos promovidos para `resources/js/lib/prototype/`; SEO/sitemap; i18n; ADRs e docs; testes e gates.

**Não entra:** exportação MD/CSS/ZIP (F5), OG dinâmica, curtidas/comentários/favoritos/seguir no sistema, perfil público próprio, API JSON, alterações no viewer de paleta, dependências novas, commit/push, migrations contra produção e início da F5.

## Ordem de execução e gates

| Etapa | Conteúdo | Gate ao fim |
| --- | --- | --- |
| 1 | Migrations, config, model `LayoutSystem`, relações, enum `LayoutStrategy` | `php artisan migrate` local e testes de model |
| 2 | Estado: catálogo PHP, validador, DTO, cota conjunta, slug/aposentadoria, crédito, presenter, tool props | Pest de cota/slug/estado |
| 3 | Actions, requests, controllers, rotas, `ToolProps`, SEO/sitemap | Pest de HTTP/actions/público/SEO |
| 4 | `lib/state.ts`, hidratação do `useLayoutSystem`, modo compatível, tipos TS | Vitest de estado/hidratação/congelado |
| 5 | Draft, importação, save dialog, editor vinculado, guarda de alterações | Vitest de draft/salvamento |
| 6 | Lista, editor, página pública, CTA nas paletas, duplicação | Pest/Vitest das telas |
| 7 | Promoção dos protótipos, mapa fixo e painel | Vitest de protótipo |
| 8 | i18n nos 5 idiomas e paridade | `TranslationsParityTest` e teste de i18n |
| 9 | ADRs, docs, artefatos da fase, gates completos e relatório | `composer test`, `npm run test`, `types:check`, `format:check`, `lint:check`, `build` |

## Modelo de dados

### Migrations (novas tabelas, retrocompatíveis)

`2026_11_19_000001_create_layout_systems_table.php` espelha as convenções de `color_palettes`:

```php
$table->ulid('id')->primary();
$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
$table->foreignUlid('duplicated_from_id')->nullable()
    ->constrained('layout_systems')->nullOnDelete();
$table->foreignUlid('source_palette_id')->nullable()
    ->constrained('color_palettes')->nullOnDelete();

$table->string('name', 80);
$table->text('description')->nullable();
$table->string('visibility', 16)->default('private');
$table->json('state');
$table->json('source')->nullable(); // credito congelado da origem

$table->unsignedSmallInteger('schema_version')->default(1);
$table->unsignedSmallInteger('algorithm_version')->default(1);
$table->unsignedSmallInteger('suggestions_version')->default(1);
$table->unsignedSmallInteger('snapshot_version')->default(1);
$table->unsignedSmallInteger('tokens_count')->default(66);
$table->unsignedInteger('duplications_count')->default(0);

$table->string('slug', 60)->nullable()->unique();
$table->string('archived_slug', 60)->nullable()->unique();
$table->timestamp('archived_at')->nullable();
$table->timestamps();

$table->index(['user_id', 'archived_at']);
$table->index(['user_id', 'updated_at']);
$table->index(['visibility', 'archived_at']);
```

`2026_11_19_000002_create_layout_system_retired_slugs_table.php` guarda só `id`, `slug` (60, unique) e timestamps. `2026_11_19_000003_create_color_tag_layout_system_table.php` é o pivot com chave composta e cascatas, no padrão de `color_palette_color_tag`.

### Shape de `layout_systems.state`

```json
{
  "stateVersion": 1,
  "inputs": {
    "seedText": "#3b82f6",
    "backgroundText": "",
    "secondaryText": "",
    "strategy": "analogous"
  },
  "secondary": null,
  "snapshot": { "snapshotVersion": 1, "schemaVersion": 1, "algorithmVersion": 1,
                "suggestionsVersion": 1, "target": "aa", "seed": { },
                "strategy": "analogous", "secondarySource": "auto",
                "themes": [ ], "overrides": [ ], "locks": [ ],
                "acceptedAdjustments": [ ], "diagnostics": [ ] }
}
```

- `snapshot` é exatamente o `LayoutSnapshot` da F3 (`resources/js/tools/layout-harmonizer/lib/snapshot.ts`), sem mudar o contrato.
- `inputs` preserva os textos crus para reabrir o que foi digitado, inclusive alpha e sintaxes alternativas.
- `secondary` é o RGB efetivo da secundária manual; `null` quando automática. É a fonte de verdade da assinatura de regeneração.
- `target` fica só em `snapshot.target`. `viewMode` **não** é persistido; é estado local do workspace.
- Tamanho estimado: 20–25 KB. Teto técnico de 256 KB em `colors.layout_systems.state_max_bytes`.

### Validação do estado no servidor

`app/Support/Colors/LayoutSystemState.php` valida em `withValidator`/`after`:

- `stateVersion`, `snapshotVersion` e `schemaVersion` iguais aos atuais (1).
- `algorithmVersion`/`suggestionsVersion` inteiros entre 1 e 999 (o servidor não adivinha versões futuras).
- Exatamente dois temas (`light`/`dark`), 33 tokens cada, os 33 ids do catálogo sem repetição e com a família correta; 16 checks por tema, os mesmos pares de `LAYOUT_CONTRAST_PAIRS`, cada veredito com `ratio`/`aaNormal`/`aaaNormal`/`aaLarge`/`aaaLarge`.
- Canais `rgba` em faixa, alpha 0–1, `hex` `/^#[0-9a-f]{6}$/`, `oklch` numérico, `source` no enum conhecido, `clipped` booleano, `theme` do token igual ao tema; `acceptedAdjustments` com `theme`/`token`/`target`/`from`/`to` no formato de `LayoutAcceptedAdjustment`.
- `overrides`/`locks`/`acceptedAdjustments` até 66; `diagnostics` até 100.
- `inputs` strings até o limite configurado (120); `strategy` no enum `App\Enums\LayoutStrategy`; `snapshot.strategy` igual a `inputs.strategy`.
- `secondarySource === 'manual'` exige `secondary` não nulo; `'auto'` exige `null`.
- Seed efetiva opaca; se a original tem alpha, `background` obrigatório.
- `json_encode($state)` abaixo de `state_max_bytes`; falha de codificação rejeita.
- O servidor **deriva** `schema_version`, `algorithm_version`, `suggestions_version`, `snapshot_version` e `tokens_count` do snapshot validado. O cliente nunca envia colunas de versão separadas.

`App\Support\Colors\LayoutSystemCatalog` concentra ids, famílias, pares e estratégias em PHP; um teste Pest de paridade lê `engine/types.ts`/`engine/tokens.ts` e falha se divergirem. `App\Enums\LayoutStrategy` espelha `LAYOUT_STRATEGIES`.

## Backend

### Model e cota

`app/Models/LayoutSystem.php`: `HasUlids`, casts de `state`/`source` para array, `visibility` para `PaletteVisibility`, datas e inteiros; relações `user`, `tags` (pivot `color_tag_layout_system`), `duplicatedFrom`, `duplicates`, `sourcePalette`; escopos `forUser`, `active`, `archived`, `publiclyVisible` (pública, não arquivada, com slug); helpers `isPublic`, `isArchived`.

`User::layoutSystems()` e `ColorTag::layoutSystems()`. `PaletteTags` ganha métodos genéricos `syncTo`/`copyTo` para `ColorPalette|LayoutSystem`; `delete()` passa a desvincular também os sistemas, sem mudar a API atual das paletas.

`PaletteQuota::used()` soma `colorPalettes()` e `layoutSystems()`. O limite continua em `colors.palettes.free_limit` (25) e `paid_limit` (`null` = ilimitado); não há segundo contador. Arquivados contam. `reached()` bloqueia apenas novas criações/duplicações; downgrade (`used > limit`) continua permitindo ler, editar, restaurar, excluir e exportar (quando a F5 existir).

Como o deploy copia o código antes de rodar `php artisan migrate --force`, `used()` usa `Schema::hasTable('layout_systems')` (e `PaletteTags::delete()` usa `Schema::hasTable('color_tag_layout_system')`) até a migration entrar: as telas antigas de paleta não podem quebrar na janela entre o pull e o migrate. O `Sitemap` já segue esse padrão.

`CreateLayoutSystem` e `DuplicateLayoutSystem` repetem o padrão de `CreatePalette`: `DB::transaction` + `User::query()->whereKey(...)->lockForUpdate()->first()` antes da contagem, erro `colors.validation.limit_reached`, e retry de até 3 para colisão de slug. A ordem de locks é sempre `users` primeiro. `Update` não trava nem conta.

### Slug e aposentadoria

`LayoutSystemSlug` espelha `PaletteSlug`:

- `base(name)`: `Str::slug`, fallback `layout-system`, corte em `colors.slug.max_length`; reservado recebe `-system`.
- `unique(base)`: `-2` a `-5` e depois sufixo aleatório.
- `taken(slug)`: consulta `layout_systems.slug`, `layout_systems.archived_slug` e `layout_system_retired_slugs.slug` (com `Schema::hasTable` defensivo). `archived_slug` é unique, então um slug arquivado nunca é reutilizado.
- Primeira publicação (criação pública ou update que torna pública) gera o slug; renomear não muda o slug.
- Tornar privado revoga a URL imediatamente; o slug fica reservado e volta a valer se o item for público de novo.
- Arquivar move `slug` para `archived_slug`, zera `slug` e revoga a URL; restaurar público devolve o slug reservado; restaurar privado mantém o slug em `archived_slug` até a publicação.
- Excluir grava `slug` e `archived_slug` em `layout_system_retired_slugs` com `insertOrIgnore` e remove a linha/pivot. ULID e slug nunca são reemitidos; o link antigo responde 404 para sempre.
- A página pública checa `isRetired` antes do `firstOrFail`; privada, arquivada, inexistente e aposentada têm o mesmo 404.

### Crédito de origem

`LayoutSystemCredit::capture(?ColorPalette $palette, ?User $owner): ?array` congela:

```json
{ "palette_name": "...", "palette_slug": "...", "palette_public": true,
  "author_name": "...", "author_username": "...", "captured_at": "ISO" }
```

- O request envia apenas `source_palette_id` (ULID); o servidor valida dono **ou** paleta pública ativa e captura o crédito. O cliente não envia `source`.
- Fonte privada: `palette_public=false`; o público nunca vê nome, link ou autor da origem.
- Fonte pública: crédito textual sempre exibido; link só quando a paleta ainda existe e continua `publiclyVisible()`. Se a paleta for excluída, o texto permanece e o link some.
- Duplicação de outra conta copia o `source` congelado, mas **zera** `source_palette_id` (a linhagem do sistema fica em `duplicated_from_id`); “salvar como novo” na mesma conta mantém o FK. O presenter só expõe a paleta quando o viewer pode acessá-la, e o público nunca recebe `source_palette_id`.
- O CTA da paleta abre `dashboard/colors/layout-systems/create?palette={ULID}`. O `LayoutSystemIntentController` valida (dono ou pública), preserva `palette` e `save` e redireciona ao tool `tools.show.grouped` (`colors/layout-harmonizer`). O mesmo intent com `?save=1` é o retorno do login do fluxo anônimo.
- Se a paleta de origem sumir entre a página e o salvamento, o request falha com erro de validação em `source_palette_id`; o banner de origem oferece “remover origem” e o sistema pode ser salvo sem crédito. Nada é gravado com crédito inventado.

### Actions, requests, presenters e rotas

Actions em `app/Actions/Colors/`:

| Action | Comportamento |
| --- | --- |
| `CreateLayoutSystem` | Lock + cota; valida origem; deriva versões/`tokens_count`; slug se pública; sincroniza tags; congela crédito |
| `UpdateLayoutSystem` | Dono; bloqueia arquivado (`colors.validation.archived`); atualiza metadados + state; primeira publicação gera slug; versões/contagem derivadas |
| `ArchiveLayoutSystem` | Move slug para `archived_slug`, seta `archived_at`; cota e estado preservados |
| `RestoreLayoutSystem` | Limpa `archived_at`; reivindica `archived_slug` se pública; não consome vaga |
| `DeleteLayoutSystem` | Confirma nome no request; aposenta slugs; remove pivot; libera vaga; não reutiliza ULID |
| `DuplicateLayoutSystem` | Lock + cota; copia state/versões/crédito/linhagem/tags; cópia privada; incrementa `duplications_count` só entre donos diferentes |

Requests: `StoreLayoutSystemRequest` (nome obrigatório/80, descrição 500, visibilidade, tags até o limite, state validado, `source_palette_id` nullable), `UpdateLayoutSystemRequest` (mesmas regras), `DeleteLayoutSystemRequest` (confirma nome contra a rota), `DuplicateLayoutSystemRequest` (sem corpo).

Presenters: `row()` (id, nome, descrição, visibilidade, arquivado, slug, tokens_count, duplications_count, preview de 6 HEX do snapshot, tags, datas), `editor()` (row + state, crédito, origem acessível, versões, arquivamento), `public()` (nome, descrição, tags, autor, versões, `state.snapshot`, crédito permitido, duplications_count — nunca `source_palette_id`, conta ou origem privada), `author()` no padrão de `PalettePresenter`.

Rotas em `routes/colors.php`:

- Grupo `auth, verified, active`: `dashboard/colors/layout-systems` (index), `/create` (intent), `/{layoutSystem}/edit`, e `store/update/archive/restore/destroy/duplicate` sob `throttle:colors-management`, com `whereUlid`.
- Público: `{locale}/colors/s/{slug}` (`colors.systems.show`), `{locale}/colors/s/{slug}/duplicate` GET (intent autenticado) e POST (duplicação autenticada + throttle).
- Menu da conta (`useAccountNav.ts`) ganha “Sistemas de layout”.

**Autorização:** o grupo da conta exige `auth, verified, active`; toda action resolve o recurso por `forUser` e responde **404** para item de outra conta (nunca 403, para não confirmar existência); o público só consulta `publiclyVisible` (privado, arquivado, aposentado e inexistente têm o mesmo 404); duplicar público exige conta; tags são sempre da conta; não há superfície de admin nesta fase.

`app/Support/Colors/LayoutSystemToolProps.php` (plugado em `ToolProps::for`):

```php
['authenticated' => bool, 'limits' => [...], 'quota' => ?array,
 'tags' => [...], 'origin' => ?array, 'currentVersions' => [...]]
```

`origin` resolve `?palette={ULID}` para `{id, name, author, public, seedHex}`, com `seedHex = colors.base` (cor base, nunca o papel `primary`); se a paleta não existir ou não for acessível, `origin` é `null`. O tool usa esse seed apenas quando não está importando rascunho.

SEO/sitemap: `Seo::pageMeta` trata `colors.systems.show` com nome/descrição (fallback `colors.meta.system_description` com estratégia e 66 tokens), com `Schema::hasTable('layout_systems')` defensivo; `Seo::toolSlug()`/`image()` reconhecem essa rota como a tool `layout-harmonizer` e usam `og/layout-harmonizer.png`; `Sitemap::layoutSystems()` usa `Schema::hasTable`, `publiclyVisible()`, limite `colors.layout_systems.sitemap_limit` (500) e entra em `urls()`.

Config `config/colors.php` ganha `layout_systems` (`name_max_length` 80, `description_max_length` 500, `per_page` 20, `preview_colors` 6, `sitemap_limit` 500, `state_max_bytes` 256 KB, `input_max_length` 120, `versions` com `state`/`schema`/`snapshot` = 1 e `algorithm`/`suggestions` ≥ 1), e `tags.per_layout_system` 10. **Sem** TTL de rascunho.

## Frontend

### Estado, hidratação e versões

`resources/js/tools/layout-harmonizer/lib/state.ts`:

- `LAYOUT_STATE_VERSION = 1` e `LayoutSystemStateV1` (shape acima).
- `buildLayoutState(inputs, secondary, snapshot)` monta o payload; não reconstrói snapshot.
- `hydrateState(state)` valida shape/versões e devolve `{inputs, secondary, snapshot, editState, mode}`; se `seedText`/`backgroundText`/`secondaryText` não parsearem para a cor gravada no snapshot, substitui o texto por `formatHex` da cor efetiva, para a hidratação nunca regenerar um quadro diferente do salvo.
- `hydrateMode`: `live` (todas as versões atuais), `compatible` (state/snapshot/schema atuais e algorithm/suggestions menores) ou `readonly` (shape/versão mais nova ou desconhecida).
- `boardsFromSnapshot(snapshot)`: converte arrays de tokens/checks para `Record` sem chamar o motor.
- `materializeResult(snapshot)`: só no modo `live`; devolve `LayoutSystemResult` com as versões atuais.
- `hydrateEditState(snapshot)`: overrides, locks e aceites a partir do snapshot.

`useLayoutSystem(options?: { initial?: LayoutSystemInit; seedText?: string })`:

- `LayoutSystemInit = { state: LayoutSystemStateV1; mode: 'live' }`.
- Antes do `watch` imediato, preenche picker/textos/estratégia/secundária/meta/result/editState e `lastSignature`; uma flag `skipNext` consome a primeira execução do watch. Sem o latch, a montagem regeneraria o quadro e poderia descartar overrides.
- `seedText` (sem `initial`) preenche a seed no setup e deixa o watch gerar uma vez — é o caminho do CTA da paleta.
- Expõe `secondaryColor` (RGB efetivo) para o salvamento. O resto do comportamento F3 fica intacto: assinatura, descarte, locks, aceites, meta fora do watch.
- Nenhum acesso a storage no composable; o teste F3 continua válido.

### Modo compatível (versões antigas suportadas)

`lib/frozen.ts` + `composables/useFrozenLayout.ts` implementam a mesma interface de workspace (`LayoutController`) para `mode='compatible'`:

- `boardsFromSnapshot` alimenta `ThemeComparison`.
- Editar token atualiza `rgba`/`hex`/`oklch` e **todos** os checks do tema com o `evaluateContrast` já exportado pelo motor (`engine/contrast.ts`), sem gerar tokens e sem mudar versões. O override entra em `snapshot.overrides`/`editState`.
- Lock/unlock atualiza só `snapshot.locks`; o valor exibido não muda.
- Restaurar token/tema/sistema fica **desabilitado** (o snapshot não guarda o valor automático anterior); a UI explica e oferece “Regenerar com a versão atual”.
- Sugestões ficam **desabilitadas** no modo compatível (o algoritmo de sugestões pode ter mudado); a UI informa. O modo ainda permite editar/lock/salvar o que está exibido.
- Alterar seed/estratégia/secundária marca `pendingRegeneration`; o quadro não muda e o salvamento fica bloqueado até a decisão explícita.
- “Regenerar com a versão atual” pede confirmação, gera com as entradas atuais carregando os tokens bloqueados como overrides e descartando manuais não bloqueados; o editor remonta o workspace com o novo estado `live` (via `key`), e o salvamento passa a gravar as versões atuais.
- `readonly` (shape/versão desconhecida ou mais nova): o editor mostra o quadro somente leitura (`editable=false`), mantém metadados/ciclo de vida, duplicação e, quando o shape é conhecido, “Regenerar com a versão atual”; não aceita edição de token.

### Ferramenta, salvamento e editor

`LayoutWorkspace.vue` aceita `initial`, `origin`, `authenticated`, `limits`, `quota`, `currentVersions` e é a única camada que conhece os dois composables: ambos devolvem o mesmo `LayoutController` que o template já consome hoje (picker, estratégia, textos, `result`, `seed`, avisos, `target`, `editState`, `suggestions`, `snapshot`, `editable` e ações). O `result` do controller usa uma visão estrutural `LayoutViewResult` com versões `number` (o `LayoutSystemResult` é compatível), para o quadro congelado não fingir ser da versão atual. Com `initial` construído pelo pai, o workspace escolhe `useLayoutSystem` (live) ou `useFrozenLayout` (compatible); `viewMode` continua local.

`Tool.vue`:

- Recebe `toolProps`; monta `initial` de rascunho importado ou `seedText` da origem.
- Exibe o banner de origem (nome da paleta, link para o editor/página pública, ação “remover origem” que limpa a query).
- Botão “Salvar” quando há quadro válido: anônimo grava o rascunho e navega ao intent `create?save=1` (preservando `palette`); autenticado abre o save dialog.
- Após login, se houver rascunho `pending`, abre o diálogo Importar/Descartar **antes** do save dialog. Importar hidrata sem salvar/consumir cota; se veio de `?save=1`, o save dialog abre depois.
- Guarda de alterações para autenticado (sem rascunho) e no editor.

`SaveLayoutSystemDialog.vue` (modelado em `SavePaletteDialog.vue`): nome obrigatório e editável, descrição, visibilidade (privado padrão), tags; modos “Salvar” e “Salvar como novo” (na criação só “Salvar”); envia `{name, description, visibility, tags, state, source_palette_id?}`; mostra erros do servidor e `limit_reached`.

Sugestão de nome (função pura em `lib/save.ts`):

- origem: `t('tools.layout-harmonizer.save.name_from_palette', { name })`;
- sem origem: `t('tools.layout-harmonizer.save.name_from_strategy', { strategy, hex })`;
- “salvar como novo”: nome atual + sufixo de cópia (`colors.palette.copy_suffix`);
- truncar no limite configurado, sempre editável.

`LayoutSystemEditor.vue` (página `dashboard/colors/LayoutSystemEditor`):

- Cabeçalho com nome, badges, ações (salvar, salvar como novo, duplicar, arquivar/restaurar, excluir, copiar link público/ver público), voltar.
- `LayoutWorkspace` hidratado; salvar faz PATCH do state+metadados e atualiza a baseline; “salvar como novo” faz POST e redireciona ao novo editor.
- Excluir exige digitar o nome; arquivar/restaurar usam o padrão `PaletteActions`/`DeletePaletteDialog`.
- `useUnsavedGuard`: compara o estado atual com a baseline, registra `router.on('before')` (removendo no unmount) e `beforeunload`, com diálogo próprio; ignora navegações de salvamento já confirmadas.

### Lista e página pública

`LayoutSystemsIndex.vue` espelha `PalettesIndex`: busca, status ativo/arquivado, tag, ordenação (updated_at/created_at/name/duplications_count), paginação server-side, vazio, cota “X de 25 itens”. `LayoutSystemCard` mostra 6 cores do snapshot, 66 tokens, tags e badges. `PaletteCard` não é alterado.

`PublicLayoutSystem.vue`:

- Layout público Colors com autor, tags, datas, estratégia, seed, versões e 66 tokens.
- Quadros somente leitura via `ThemeComparison`/`ThemeBoard` com `editable=false`; cópia de HEX/OKLCH funciona; nenhum handler de edição.
- Crédito conforme `LayoutSystemCredit`; sem curtidas/comentários/favoritos/seguir/perfil/API.
- CTA “Duplicar” (login quando anônimo) usa o intent GET e devolve à página com `?duplicate=1`; o POST duplica como privado e leva ao editor da cópia.
- Canonical/hreflang iguais em todos os locales; OG estática.

CTA nas paletas: `PaletteActions.vue` e `PublicPalette.vue` ganham “Criar sistema de layout” apontando para o intent com `?palette={id}`. O tool abre com a **cor base** (`colors.base`) no campo da seed, editável, e o banner de origem. Anônimo passa pelo login e volta.

### Rascunho anônimo

`lib/draft.ts` (puro, storage injetável, guarda `typeof window`):

- Chave `devtools.layout-harmonizer.draft.v1` em `localStorage`.
- Envelope `{ version: 1, savedAt, status: 'pending'|'imported'|'refused', decidedAt, originPaletteId, state }`.
- **Sem TTL**: o rascunho vive até limpar/importar/descartar. Shape inválido é ignorado e removido; shape válido com versões não suportadas não é apagado: o diálogo explica e oferece apenas “Descartar”.
- `readLayoutDraft`, `writeLayoutDraft`, `markImported`, `markRefused`, `clearLayoutDraft`.

`useLayoutDraft(snapshotRef, enabled)` grava com debounce de 500 ms apenas anônimo, com quadro válido e sem aviso; um novo trabalho anônimo depois de `imported`/`refused` volta a `pending`. No carregamento anônimo, o último rascunho salvo é hidratado automaticamente, exceto quando o status é `refused`; autenticado só pergunta quando o status é `pending` (e, com `?save=1`, o diálogo de salvar abre depois do Importar/Descartar). Importar não salva e mantém o rascunho; descartar limpa. O composable `useLayoutSystem` continua sem storage.

### Protótipos

- Mover `resources/js/tools/palette-generator/prototype/` para `resources/js/lib/prototype/` com movimentação de arquivos **sem `git add`/`git mv`** e atualizar imports (`paletteOg.ts`, `PaletteEditor.vue`, `image/buildImage.ts`, `image/exportImage.ts`, testes). Nenhuma mudança de comportamento.
- Extrair o `sample` de `PrototypePanel.vue` para `lib/prototype/copy.ts` (`prototypeSampleCopy(t)`), reutilizado pelo painel da paleta e pelo LH.
- `lib/layoutRoleMap.ts`: mapa fixo `primary→primary`, `secondary→secondary`, `accent→accent`, `background→background`, `surface→surface`, `on-background→text`, `on-surface-variant→text_muted`, `hairline→border`.
- `LayoutSystemPrototypePanel.vue`: seletor site/mobile e tema, `buildPrototype` + `PrototypePreview`; consome os tokens do snapshot exibido (live ou compatível); sem `RoleEditor`, sem exportação, só no workspace autenticado.

### i18n

- `lang/*/tools.php`: grupos `save`, `draft`, `origin`, `lifecycle`, `prototype`, `a11y` adicionais.
- `lang/*/colors.php`: `systems.*` (lista, editor, pública, meta, ações, validação, toasts, estratégias), `meta.system_description`, `account.nav.layout_systems`; textos de cota neutros (“itens”) em `PalettesIndex`.
- 5 idiomas: `en`, `pt_BR`, `pt_PT`, `fr`, `es`. Sem `@`/`|` crus; paridade garantida por `TranslationsParityTest` e `layout-harmonizer-i18n.test.ts`.

## Arquivos principais

**Criar — backend:** migrations 000001–000003; `app/Models/LayoutSystem.php`; `app/Enums/LayoutStrategy.php`; `app/Support/Colors/{LayoutSystemSlug,LayoutSystemState,LayoutSystemCatalog,LayoutSystemData,LayoutSystemPresenter,LayoutSystemCredit,LayoutSystemToolProps}.php`; `app/Actions/Colors/{Create,Update,Archive,Restore,Delete,Duplicate}LayoutSystem.php`; `app/Http/Requests/Colors/{Store,Update,Delete,Duplicate}LayoutSystemRequest.php`; `app/Http/Controllers/Colors/{LayoutSystemController,LayoutSystemIntentController,LayoutSystemDuplicationController,PublicLayoutSystemController}.php`.

**Criar — frontend:** `resources/js/types/layoutSystem.ts`; `resources/js/tools/layout-harmonizer/lib/{state,frozen,draft,save,layoutRoleMap}.ts`; `composables/{useFrozenLayout,useLayoutDraft,useUnsavedGuard}.ts`; `components/{SaveLayoutSystemDialog,LayoutSystemPrototypePanel,LayoutSystemOriginBanner}.vue`; `resources/js/components/colors/{LayoutSystemCard,LayoutSystemActions,LayoutSystemMetaForm,DeleteLayoutSystemDialog}.vue`; páginas `dashboard/colors/{LayoutSystemsIndex,LayoutSystemEditor}.vue`, `colors/PublicLayoutSystem.vue`; `resources/js/lib/prototype/` (movido).

**Modificar:** `config/colors.php`, `User`, `ColorTag`, `PaletteQuota`, `PaletteTags`, `ToolProps`, `Seo`, `Sitemap`, `routes/colors.php`, `useAccountNav`, `PaletteActions`, `PublicPalette`, `useLayoutSystem`, `LayoutWorkspace`, `Tool.vue`, `PalettesIndex`, imports dos protótipos, i18n, testes de sitemap/tool/paridade.

**Sem tocar:** viewer de paleta, curtidas/comentários/favoritos, o motor (`engine/` fica intacto; só reutilizamos `evaluateContrast`, já exportado), dependências, CLAUDE.md e ADRs existentes (só emendas/cópias previstas), commit/push.

## Testes

**Pest:** `LayoutSystemStateTest` (fixture v1 válida; rejeições de versão, tokens, cores, secundária, tamanho); `LayoutSystemHttpTest` (auth/404 de outro dono, filtros, store/update, limites, lifecycle, toasts); `LayoutSystemQuotaTest` (24+1 bloqueia qualquer tipo, arquivados contam, excluir libera, downgrade permite editar, lock compartilhado); `LayoutSystemActionsTest` (slug, aposentadoria, restore, duplicação, crédito); `LayoutSystemPublicHttpTest` (público/privado/arquivado/retirado, props, origem pública/privada/excluída, duplicação); `LayoutSystemSeoTest` (meta, OG estática, canonical/hreflang); paridade de estratégias/catálogo TS↔PHP. Atualizar `SitemapTest`, `ToolPageTest`, `TranslationsParityTest`.

**Vitest:** `layout-harmonizer-state` (round-trip com textos crus/alpha, secundária efetiva, target, overrides/locks/aceites, `materializeResult`); `layout-harmonizer-hydration` (não regenera na montagem, baseline/assinatura, descarte correto ao mudar seed); `layout-harmonizer-frozen` (patch de token só mexe no tema/checks, versões preservadas, restore/sugestões bloqueados, regeneração explícita); `layout-harmonizer-draft` (chave, sem TTL, shape inválido, status, debounce, SSR-safe); `layout-harmonizer-save` (payload, nome sugerido, save-as-new, guarda); `layout-harmonizer-prototype-map`; `layout-harmonizer-public-viewer` (somente leitura, cópia, sem recursos sociais/exportação). Atualizar testes de protótipo e i18n.

## ADRs e documentação

- Copiar os ADRs locais com numeração global: 008→`ADR-152-colors-lh-quota.md`, 010→`ADR-154-colors-lh-publico.md`, 015→`ADR-159-colors-lh-cadastro.md`, 016→`ADR-160-colors-lh-downgrade.md`, 017→`ADR-161-colors-lh-rascunho.md`, 020→`ADR-164-colors-lh-ciclo-de-vida.md`, 021→`ADR-165-colors-lh-duplicacao.md`. Os originais locais permanecem em `docs/colors/layout-harmonizer/adr/` até o aceite da fase, como nas fases anteriores.
- Novos: `ADR-168` persistência/snapshot, `ADR-169` edição compatível/congelada, `ADR-170` cota conjunta, `ADR-171` slug aposentado, `ADR-172` crédito de origem, `ADR-173` protótipos no workspace, `ADR-174` viewer público/rota/SEO.
- Atualizar `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md`, `docs/colors/README.md`, `docs/SPEC.md` (§5 e §9), `docs/glossario.md` e `docs/colors/layout-harmonizer/PARAMETROS.md` (estado, versões, limites, modo compatível).

## Riscos e mitigação

1. **Watch imediato da F3 regenerar ao hidratar** → latch `skipNext` + teste de identidade do resultado.
2. **Snapshot sem entradas cruas** → `state.inputs` + `secondary` efetivo + normalização e teste de round-trip com alpha/sintaxe alternativa.
3. **Cota corrida paleta × sistema** → mesma linha `users` travada nos quatro caminhos; teste misto e duas abas no gate manual.
4. **Slug reutilizado** → `archived_slug` unique, `taken` olhando slug arquivado/aposentado e tabela de retirados; teste de link antigo 404.
5. **Vazamento de origem privada** → crédito só exibido se `palette_public`; presenter público nunca recebe FK; teste explícito.
6. **Versão futura quebrar o editor** → `readonly` com aviso e duplicação; servidor só aceita shape v1.
7. **Modo compatível sem valor-base** → restore desabilitado com explicação e regeneração explícita; sem promessa falsa.
8. **Promoção dos protótipos quebrar imports** → mover sem git, atualizar todos os imports e testes no mesmo bloco.
9. **Payload grande** → teto 256 KB e fixture real (~25 KB).
10. **Guard de navegação global** → registrar/remover no unmount, bypass no salvamento e teste dedicado.

## Verificação manual

1. `php artisan migrate` local; conferir as três tabelas/FKs/índices; rollback dos 3 passos e migrate de novo.
2. Anônimo: gerar, editar, recarregar → rascunho restaura; Salvar → login → Importar/Descartar; importar não salva nem consome; **sem expiração** do rascunho; descartar limpa.
3. Autenticado: salvar → editor vinculado; salvar de novo → continua 1 item; “salvar como novo” → 2 itens e workspace aponta ao novo; sair com alterações → aviso.
4. Cota: criar paleta + sistema e conferir “X de 25 itens” nas duas telas; com `COLORS_FREE_LIMIT=2`, duas abas disputando a terceira vaga → uma falha com `limit_reached`.
5. Ciclo: arquivar público → 404 e fora do sitemap, cota igual; restaurar → URL volta; excluir com nome → vaga liberada e link 404; recriar com o mesmo nome → slug diferente.
6. Página pública: origem pública com crédito/link; privada sem nada; excluir a paleta de origem → texto permanece sem link; duplicação exige login, consome cota e abre o editor da cópia privada.
7. Versões: forçar `algorithmVersion`/`schemaVersion` de fixture e conferir live/compatível/readonly; regeneração explícita e salvamento preservando/trocando versões.
8. Protótipo: site/mobile e tema, cores do snapshot exibido; sem RoleEditor/exportação.
9. SEO/i18n: título/meta/canonical/hreflang nos 5 locales; OG estática; sitemap com públicas ativas; percorrer as telas nos 5 idiomas sem chave crua.

## Gates reais

```bash
composer test
npm run test
npm run types:check
npm run format:check
npm run lint:check
npm run build
# usar npm run build:ssr se o ambiente de produção da fase exigir SSR
```

Critérios: gates verdes sem resultados inventados; nenhuma dependência nova; protótipos da paleta com comportamento inalterado além do caminho de import; docs/ADRs atualizados; nenhum commit/push.

## Parada

Salvar `docs/plans/feature_layout-hamonizer-fase-4/20261119_f4_persistencia_plansfacts.md`, `docs/survey/20261119_layout_harmonizer_f4_surveyfacts.md` e o plano em `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261119_feature_layout-hamonizer-fase-4_f4_persistencia.md`; implementar por etapas; rodar os gates reais; escrever o relatório em `docs/claude-code/reports/feature_layout-hamonizer-fase-4/`; **parar para revisão**, sem commit/push e sem iniciar a F5.
