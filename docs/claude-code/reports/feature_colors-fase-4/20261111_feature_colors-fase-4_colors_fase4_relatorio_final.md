# Relatório final — Colors Fase 4: persistência, Minhas Paletas, compartilhamento e protótipos

- Data: 2026-11-11
- Branch: `feature/colors-fase-4`
- Plano: `docs/claude-code/plans/feature_colors-fase-4/20261110_feature_colors-fase-4_colors_fase4_persistencia_prototipos.md`
- Prompt da fase: `docs/colors/PROMPT-Fase4-persistencia_prototipos.md`
- Etapas: 0–11 concluídas (cada uma com checkpoint verificado)
- Observação: **nada foi commitado nem enviado** (CLAUDE.md — o usuário commita e abre o PR via gitpr)

## Visão geral

A F4 transformou o Color Palette Generator em produto, sem tirar o processamento das ferramentas do navegador:

- **Paletas salvas** por usuário autenticado, Free 25 / pago sem limite funcional, com cota aplicada no servidor sob `User::lockForUpdate()`; arquivadas contam, só a exclusão definitiva libera vaga.
- **Dois tipos de paleta**: `scale` (base + 11 tons efetivos) e `extracted` (base escolhida + 5/7/9/11 cores da imagem + origem reduzida a nome/host). As cores efetivas são persistidas e **nunca recalculadas** ao abrir.
- **Papéis semânticos** fixos (8 × claro/escuro), editáveis, persistidos na paleta e compartilhados entre site e mobile.
- **Minhas Paletas** em `/dashboard/colors/palettes` (sem prefixo de locale): busca server-side, filtros, ordenação, paginação, cota, “Uso”, tags gerenciáveis, arquivar/restaurar e excluir com nome exato.
- **Página pública** `/{locale}/colors/p/{slug}` com slug global, canonical/hreflang sem query, meta com dados públicos, sitemap, “testar nova base” local e duplicação autenticada.
- **OG dinâmica** 1200×630 desenhada no cliente, enviada por multipart autenticado, validada e versionada; serving só para paleta pública ativa, com fallback para a OG padrão.
- **Protótipos vetoriais** de site e mobile em claro/escuro, com renderer SVG próprio e PNG 1x/2x rasterizado do mesmo SVG.
- **i18n** completo nos 5 idiomas e **ADRs 123–126** + 9 emendas.

## Backend

### Dados

| Item | Conteúdo |
| --- | --- |
| Migrations | `color_palettes` (ULID, cota/uso denormalizados, `slug`/`archived_slug`, `og_version`), `color_tags` (`key` normalizada) e pivô sem posição |
| Enums/models | `PaletteKind`, `PaletteVisibility`; `ColorPalette` (casts, relações, scopes `active`/`archived`/`publiclyVisible`), `ColorTag`; factories |
| Config | `config/colors.php` com `palettes`, `tags`, `slug` + reservados, `og`, `sitemap`, `algorithm_versions`; todos com override `COLORS_*` |

### Support e actions

| Arquivo | Papel |
| --- | --- |
| `PaletteQuota` | limite Free/pago, uso contando arquivadas, `reached()`/`summary()` |
| `PaletteSlug` | slug global, reservados, colisão `-2…-5`, `claimOrGenerate` |
| `PaletteTags` | `key`, upsert, sync do pivô, reordenação, cópia na duplicação |
| `PaletteOgStorage` | caminho, gravação atômica (tmp + `File::replace`), delete/exists |
| `PaletteRules` / `PaletteData` | validação de cores/papéis/`source` por tipo e shapes |
| `PalettePresenter` | shapes de lista, editor e página pública |
| `PaletteName` | nome de cópia truncado em 80 |
| `ColorsServiceProvider` | `colors-management` (60/min) e `colors-og` (10/min) |

Actions em `app/Actions/Colors/`: `CreatePalette`, `UpdatePalette`, `DuplicatePalette`, `ArchivePalette`, `RestorePalette`, `DeletePalette`. Criação e duplicação rodam em transação com lock e retry limitado na unique do slug; `UpdatePalette` compara o estado visual e invalida a OG quando nome, descrição, tipo, versão, cores ou papéis mudam.

### HTTP

- `routes/colors.php` carregado por `routes/web.php`: dashboard sem locale e rotas públicas dentro do grupo `{locale}`; `{colorPalette}`/`{colorTag}` com `whereUlid` e `{slug}` com `[a-z0-9-]{1,60}`.
- Controllers em `app/Http/Controllers/Colors/`: `PaletteController`, `PublicPaletteController`, `PaletteDuplicationController`, `PaletteIntentController`, `PaletteTagController`, `PaletteOgController`.
- Requests em `app/Http/Requests/Colors/` (8), incluindo `UploadPaletteOgRequest` (PNG, 1200×630, teto de bytes) e `DeletePaletteRequest` (`confirmName`).
- Ownership por comparação de `user_id` com 404 uniforme; nenhuma policy nova.
- Busca com `whereLike` em nome/descrição/tags, sort com whitelist sobre colunas denormalizadas, paginação com query string.

### SEO, sitemap e canonical

- `Seo` ganhou o branch da paleta pública: title/description, OG com `?v={og_version}` só quando versão e arquivo existem, senão OG padrão.
- `Locales::alternates` deixou de propagar a query string, então `?duplicate=1` não contamina canonical.
- `Sitemap::palettes` inclui só públicas ativas, por `updated_at desc`, com limite configurável e alternates por locale.
- `PublicPaletteController` responde 404 uniforme para privada, arquivada e inexistente e só entrega `ogUrl` quando há imagem; a página não tem autor nem controles sociais.

## Frontend

| Área | Arquivos |
| --- | --- |
| Reuso do gerador | `GeneratorWorkspace.vue` extraído de `Tool.vue`, com slots para a aba Protótipo; `Tool.vue` ganhou “Salvar”, rascunho e intent |
| Payload e escala congelada | `lib/palettePayload.ts`, `lib/frozenScale.ts`, `lib/paletteSave.ts` |
| Rascunho | `lib/saveDraft.ts` (`colors.palette.draft.v1`, TTL 24 h, só base/tipo/cores extraídas/aba) |
| Minhas Paletas | `PalettesIndex.vue`, `PaletteCard`, `PalettePagination`, `PaletteActions`, `DeletePaletteDialog`, `TagManagerDialog` |
| Editor | `PaletteEditor.vue`, `PaletteMetaForm`, `TagInput`, `SavePaletteDialog` |
| Página pública | `PublicPalette.vue` |
| Protótipos | `prototype/{types,defaults,fonts,renderSvg,rasterize,exportPrototype}.ts`, `templates/{site,mobile,og}.ts`, `PrototypePanel`, `PrototypePreview`, `RoleEditor`, `RoleColorField` |
| OG | `lib/paletteOg.ts`, `composables/usePaletteOg.ts`, `OgImageButton.vue` |
| Navegação/rotas | item “Minhas Paletas” em `useAccountNav`; helpers Wayfinder; `inertiaApp` mapeia `colors/PublicPalette` para `PublicLayout`; `downloadBlob` em `lib/download.ts` |

O SVG é o formato canônico dos protótipos; o PNG sai do mesmo documento, com a fonte embutida apenas na exportação (chunk `fonts-*.js` isolado, 289,73 kB). Nenhuma dependência de captura de tela.

## i18n

Namespace `colors` criado nos 5 idiomas (`lang/{en,es,fr,pt_BR,pt_PT}/colors.php`) com meta, lista, editor, página pública, OG, tags, papéis, protótipo e validações; `account.nav.palettes` e chaves de salvar/rascunho em `tools.palette-generator`. Paridade garantida por `TranslationsParityTest` e pelo teste de mensagens do Vitest.

## ADRs e documentação

Novas:

| ADR | Tema |
| --- | --- |
| 123 | persistência de paletas (schema, cores efetivas, cota com lock, arquivamento × exclusão, tags, rascunho, slug) |
| 124 | página pública, canonical/SEO/sitemap, duplicação e “uso” |
| 125 | OG dinâmica (cliente, upload validado, storage, `og_version`, serving) |
| 126 | protótipos vetoriais (papéis/temas, renderer SVG, PNG do mesmo SVG, fidelidade) |

Emendas de 2026-11-11 (decisões anteriores preservadas): ADR-096, 098, 100, 103, 104, 105, 109, 119 e 122.

Docs atualizados sem apagar decisões anteriores: `SPEC-COLORS.md`, `minhas-paletas.md`, `paleta-publica.md`, `og-image.md`, `palette-generator.md`, `import-export.md`, `glossario.md` e `docs/colors/README.md`.

## Testes

| Suíte | Resultado |
| --- | --- |
| Pest — `tests/Feature/Colors/` | `PaletteActionsTest` (12), `PaletteHttpTest` (17), `PalettePublicHttpTest` (11), `PaletteSeoTest` (7) |
| Pest — unitários de Colors | `PaletteSlugTest` (6), `PalettePresenterTest` (3), `ColorsToolPropsTest` (4), `AlgorithmVersionConfigTest` (2) |
| Vitest — novas/ampliadas | `palette-payload`, `palette-frozen-scale`, `palette-draft`, `palette-save-dialog`, `palette-editor`, `palette-list`, `palette-public`, `palette-og`, `tag-input`, `download`, `prototype-defaults`, `prototype-render` (snapshots site/mobile/OG × claro/escuro), `prototype-templates`, `prototype-rasterize` |

O snapshot determinístico do SVG (`resources/js/__tests__/__snapshots__/prototype-render.test.ts.snap`) cobre site/mobile × claro/escuro e o template da OG.

## Verificação

| Comando | Resultado |
| --- | --- |
| `composer test` | Pint ok, PHPStan 0 erros, **1534 testes / 27341 asserções — verdes** |
| `npm run test` | **110 arquivos, 1362 testes — verdes** |
| `npm run types:check` | sem erros |
| `npm run lint:check` | **0 erros** (1190 avisos pré-existentes do repositório) |
| `npm run build` | sucesso — `fonts-*.js` 289,73 kB isolado, `PaletteEditor-*.js` 33,93 kB, `PublicPalette-*.js` 9,04 kB |
| `npm run build:ssr` | sucesso |
| `npm run format:check` | falha pré-existente em 231 arquivos (Prettier conflita com `vue/html-indent`); não é gate do repositório |

Na primeira execução do `composer test` desta etapa, `AdminWebhookEventsTest::...filtra_por_situacao...` falhou com “actual size 2 matches expected size 1”; o teste passou isolado (4/4) e a suíte completa passou na re-execução. É uma instabilidade pré-existente de teste do admin, sem relação com Colors.

## Matriz manual de aceite

Execução manual pendente pelo usuário; cada linha deve ser marcada no PR.

| # | Cenário | Passos | Esperado | Status |
| --- | --- | --- | --- | --- |
| 1 | Visitante | Abrir `/dashboard/colors/palettes` sem sessão | Redireciona ao login | pendente |
| 2 | Isolamento | Outra conta abrir editor/OG de paleta alheia | 404 | pendente |
| 3 | Cota Free | Criar paletas até 25, inclusive uma arquivada, e tentar a 26ª | 25 criadas; 26ª recusada com erro claro no campo; excluir uma libera a vaga | pendente |
| 4 | Cota paga | Conta paga criar acima de 25 | Sem limite funcional | pendente |
| 5 | Salvar escala | Salvar `#6366f1` pelo gerador | Paleta `scale` com base + 11 tons; reabrir mostra exatamente as cores gravadas | pendente |
| 6 | Salvar extraída | Extrair 7 cores de uma imagem, escolher base e salvar | Paleta `extracted` com 7 cores na ordem de peso e origem nome/host; reabrir não re-quantiza | pendente |
| 7 | Extraída insuficiente | Imagem com menos cores que o pedido | Salva com aviso (`sufficient: false`), sem inventar cores | pendente |
| 8 | Renomear | Renomear paleta publicada | Slug e URL não mudam | pendente |
| 9 | Publicar/despublicar | Alternar visibilidade | Publicar gera slug; despublicar mantém slug e derruba a URL (404); republicar volta a servir | pendente |
| 10 | Arquivar | Arquivar paleta pública | Some da lista padrão, aparece no filtro “arquivadas”, URL 404, slug liberado, continua na cota | pendente |
| 11 | Restaurar | Restaurar com slug livre e depois com slug tomado por outra paleta | Primeiro volta ao mesmo slug; segundo gera novo do nome | pendente |
| 12 | Excluir | Excluir digitando o nome exato | Apaga linha e OG, libera cota e slug; nome diferente bloqueia | pendente |
| 13 | Busca/filtros/sort | Buscar por nome/descrição/tag; filtrar status/tag; ordenar por data, nome, nº de cores e “Uso” | Resultado server-side, paginado, com query string preservada | pendente |
| 14 | “Uso” | Duplicar a própria paleta e pedir a outra conta que duplique | Contador sobe só na duplicação por outro usuário; não cai ao excluir a cópia | pendente |
| 15 | Duplicação | Duplicar pública e própria | Cópia privada, sem OG, nome “(cópia)”, cores/papéis/tags/versões copiados, cota respeitada | pendente |
| 16 | Tags | Renomear, reordenar e excluir tag | Renomear reflete em todas as paletas; ordem vale para a conta; limite de 10 na paleta | pendente |
| 17 | Rascunho anônimo | Anônimo montar escala e clicar “Salvar” | Rascunho em `sessionStorage`, login, retomada após autenticar; rascunho expira em 24 h | pendente |
| 18 | OG | Salvar paleta pública e abrir o editor | Cartão 1200×630 gerado e servido com `?v=`; regenerar após editar nome/descrição/cores | pendente |
| 19 | OG inválida | Enviar imagem errada/fora do tamanho; bloquear canvas | 422 no endpoint; falha de geração não desfaz o salvamento e mostra o aviso | pendente |
| 20 | Canonical/SEO | Abrir pública com `?duplicate=1` | Canonical/hreflang sem query; meta só com dados públicos; sem autor/social | pendente |
| 21 | Sitemap | Gerar sitemap | Só paletas públicas ativas, por `updated_at`, dentro do limite | pendente |
| 22 | Protótipos | Alternar site/mobile × claro/escuro e editar papéis | Prévia muda; “Restaurar padrões” volta à tabela; papel usado no elemento certo | pendente |
| 23 | Exportar protótipo | Baixar SVG e PNG 1x/2x | SVG vetorial real; PNG 1x/2x com a mesma composição e fonte embutida | pendente |
| 24 | Edge cases de texto | Nome/tag com `<`, `&` e acentos | Escapado no SVG/meta e exibido corretamente | pendente |
| 25 | Idiomas | Percorrer fluxo nos 5 idiomas | 100% traduzido, sem texto fixo | pendente |
| 26 | Responsivo/tema/teclado | 375/768/1024/1280 px, claro/escuro e navegação por teclado | Sem overflow horizontal, contraste e foco visíveis | pendente |

## Pendências e observações

- **Teste opt-in de concorrência MySQL** (`COLORS_RUN_MYSQL_CONCURRENCY`) previsto no plano não foi implementado: o lock está no código e a suíte cobre o limite sequencial (25/26, arquivadas contam, excluir libera, duplicar respeita). Se o usuário quiser a prova com duas conexões, fica como item isolado para a F5.
- Matriz manual acima ainda não executada (não há ambiente autenticado nesta sessão).
- `npm run format:check` continua falhando em 231 arquivos por conflito Prettier × `vue/html-indent` pré-existente; não bloqueia a fase.
- Fora de escopo por decisão: tokens W3C DTCG/Style Dictionary e importação JSON (F5); curtidas/seguir/exportação tipográfica (F6); comentários/moderação/notificações (F7); API JSON; autor na página pública; protótipo público.
- As ações de conta (salvar, duplicar, arquivar, excluir, tags, OG) são a exceção documentada à regra de que as ferramentas rodam 100% no navegador.
- **Sem commit e sem push**, conforme CLAUDE.md; o PR e cada commit seguem pelo gitpr com aprovação do usuário.

## Arquivos

Novos — backend: `app/Actions/Colors/` (6), `app/Enums/PaletteKind.php`, `app/Enums/PaletteVisibility.php`, `app/Http/Controllers/Colors/` (6), `app/Http/Requests/Colors/` (8), `app/Models/ColorPalette.php`, `app/Models/ColorTag.php`, `app/Providers/ColorsServiceProvider.php`, `app/Support/Colors/` (9), `config/colors.php`, `database/factories/ColorPaletteFactory.php`, `database/factories/ColorTagFactory.php`, `database/migrations/` (3), `routes/colors.php`, testes `tests/Feature/Colors/` (4) e `tests/Unit/Colors/` (3 novos).

Novos — frontend: `resources/js/types/colors.ts`, `lib/{palettePayload,frozenScale,paletteSave,saveDraft,paletteOg,download}.ts`, `composables/usePaletteOg.ts`, `components/colors/` (9), `pages/dashboard/colors/{PalettesIndex,PaletteEditor}.vue`, `pages/colors/PublicPalette.vue`, `tools/palette-generator/GeneratorWorkspace.vue`, `tools/palette-generator/prototype/` (10 arquivos + 3 templates), testes em `resources/js/__tests__/` (14 novos + 2 ampliados + snapshot).

Novos — docs: `docs/adr/ADR-123…126`, plano em `docs/claude-code/plans/feature_colors-fase-4/`, survey `docs/survey/20261110_colors_fase4_surveyfacts.md` e os relatórios das etapas 9, 10 e este.

Alterados: `app/Models/User.php`, `app/Support/{Locales,Seo,Sitemap,ToolProps}.php`, `app/Support/Colors/ColorsToolProps.php`, `bootstrap/providers.php`, `config/locales.php`, `routes/web.php`, `lang/*/{account,tools}.php`, `resources/js/{lib/inertiaApp,lib/download,types/index}.ts`, `resources/js/composables/useAccountNav.ts`, `resources/js/tools/palette-generator/{Tool.vue,components/ExtractedSwatch.vue}`, testes `account-panel`, `palette-generator`, `ToolPageTest`, `ColorsToolPropsTest`, ADRs 096/098/100/103/104/105/109/119/122 e os 8 documentos de `docs/colors/`.
