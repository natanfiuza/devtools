# Relatório final — Colors Fase 5: biblioteca pessoal, favoritos de paletas, presets e tokens

- Data: 2026-11-14
- Branch: `feature/colors-fase-5`
- Plano do fechamento: `docs/claude-code/plans/feature_colors-fase-5/20261114_feature_colors-fase-5_stage7_docs_adr_relatorio_final.md`
- Prompt da fase: `docs/colors/PROMPT-Fase5-organizacao_desing_tokens.md`
- Etapas: 1–7 concluídas (cada uma com checkpoint verificado)
- Observação: **nada foi commitado nem enviado** (CLAUDE.md — o usuário commita e abre o PR via gitpr)

## Visão geral

A F5 organizou o que o usuário já produzia, sem tirar o processamento das ferramentas do navegador:

- **Biblioteca pessoal de cores**: histórico e favoritos passam a ser dados da conta — sem limite funcional, paginados por cursor e deduplicados pela cor canônica (ADR-127). O anônimo continua local, reduzido a **20** no histórico e **50** nos favoritos (ADR-132).
- **Biblioteca compartilhada**: Picker e Generator usam o mesmo composable, os mesmos painéis e a mesma lista; para quem está logado a conta é a fonte da verdade, com escrita otimista e reversão em falha (ADR-131).
- **Importação explícita**: o histórico local só entra na conta por ação do painel, em lotes, e o `localStorage` só é limpo depois de todos os lotes passarem (ADR-132).
- **Favoritos de paletas públicas**: vínculo `(user, palette)` único e idempotente, sem copiar a paleta, sem consumir cota e sem contar “Uso”; aba **Favoritas** em Minhas Paletas (ADR-128).
- **Presets do gerador**: apenas nome + base, por conta, sem cota de paleta; aplicar repõe a base e a escala é recalculada (ADR-129, emenda da ADR-093).
- **Tokens v1**: exportação W3C DTCG, Style Dictionary, TypeScript, CSS, SCSS e JSON nativo, com 11 primitivas e, no editor, 8 papéis × claro/escuro; versão do formato, alpha e gamut documentados (ADR-130, emenda da ADR-095).
- **Importação validada**: JSON nativo, DTCG e Style Dictionary lidos com `JSON.parse`, limites, aliases e ciclos; diagnóstico que nunca descarta o estado e aplicação só no botão **Aplicar** (ADR-133).
- **i18n** completo nos cinco idiomas e **ADRs 127–133** + cinco emendas datadas.

## Backend

### Dados

| Item | Conteúdo |
| --- | --- |
| Migrations | `color_history_entries` (unique `(user, color)`, índice `updated_at`), `color_favorites` (unique `(user, color)`, índice `created_at`), `color_palette_favorites` (unique `(user, palette)`, FK em cascata) e `color_presets` (unique `(user, name)`, `base` 9, índice `updated_at`) — só tabelas novas, sem downtime |
| Models | `ColorHistoryEntry`, `ColorFavorite`, `ColorPaletteFavorite`, `ColorPreset` (`HasUlids`, `#[Fillable]`, `scopeForUser`); relações em `User` |
| Config | `config/colors.php`: `library` (`history_per_page` 100, `favorites_per_page` 100, `import_batch` 200, anônimo 20/50), `presets` (`name_max_length` 60, `limit` `null`), `tokens.import` (512 KiB, 5000 tokens, profundidade 40, referências 10, diagnósticos 20) e `palettes.source_label_max_length` 80; todos com override `COLORS_*` |

### Support e actions

| Arquivo | Papel |
| --- | --- |
| `ColorKey` | formato canônico `#rrggbbaa` minúsculo ou `transparent`, com normalização e validação |
| `ColorPresetQuota` | teto opcional de preset, contagem por conta e resumo `{limit, used}` |
| `ColorsToolProps` | `library()` (authenticated + limites de paginação/importação) e `tokenImportLimits()` |
| `RecordColorHistory` | upsert que toca `updated_at`, com retry em corrida |
| `ImportColorHistory` | dedupe no lote + upsert em massa |
| `FavoriteColor` / `UnfavoriteColor` | favorito de cor idempotente |
| `FavoritePalette` / `UnfavoritePalette` | vínculo com paleta pública idempotente |
| `CreateColorPreset` | lock da linha da conta, teto opcional e corrida de nome único convertida em validação |

### HTTP

- `routes/colors.php`: sete rotas da biblioteca em `/api/tools/colors/*` (`history` GET/POST/DELETE, `history/import`, `favorites` GET/POST/DELETE), quatro de preset (`presets` GET/POST/PATCH/DELETE) e duas de favorito de paleta (`palettes/{palette}/favorite` POST/DELETE), todas sob `auth`, `verified` e `active`; escritas com `throttle:colors-library`.
- Controllers em `app/Http/Controllers/Api/Colors/`: `ColorHistoryController`, `ColorFavoriteController`, `PaletteFavoriteController`, `ColorPresetController`.
- Requests em `app/Http/Requests/Colors/`: histórico (store/import/delete), favorito de cor (store/delete em lote), preset (store/update) e atualização de nome.
- `PaletteController@index` aceita `?tab=favorites`, entrega o paginador `favorites` só com paletas `publiclyVisible()` e ordena por `created_at desc`; `PublicPaletteController@show` entrega `favorited` e `canFavorite`.
- Regras: visitante 401; recurso de outra conta 404 com `error.code = not_found`; própria paleta favoritada 403 com `error.code = forbidden` (via `AccessDeniedHttpException`); privada, arquivada ou desconhecida 404 uniforme.
- Não existe endpoint de importação de tokens: o arquivo de tokens nunca sai do navegador.

## Frontend

| Área | Arquivos |
| --- | --- |
| Cliente da API | `lib/colors/api.ts` (JSON, CSRF do cookie `XSRF-TOKEN`, `same-origin`, métodos GET/POST/PATCH/DELETE) |
| Biblioteca compartilhada | `lib/colors/library.ts` (`useColorLibrary`): anônimo só `localStorage` com limites do servidor; logado com cursor, escrita otimista, rollback, `importLocalHistory()` em lotes |
| Painéis compartilhados | `components/colors/HistoryPanel.vue`, `FavoritesPanel.vue`, `ImportLocalHistoryDialog.vue` — usados pelo Picker e pelo Generator; painéis antigos do picker removidos |
| Favoritos de paletas | `FavoritePaletteCard.vue`, aba em `pages/dashboard/colors/PalettesIndex.vue`, estrela em `pages/colors/PublicPalette.vue` |
| Presets | `components/colors/PresetDialog.vue`; `GeneratorWorkspace.vue` expõe `baseHex()`/`applyBaseHex()`; botão só para conta em `Tool.vue` |
| Exportação de tokens | `lib/tokenExporters.ts` (DTCG, Style Dictionary, TypeScript), `lib/exporters.ts` (formatos, papéis, arquivos), `components/ExportPanel.vue` (8 abas) |
| Importação validada | `lib/tokenImport.ts` (parser puro), `components/colors/TokenImportDialog.vue`, wiring em `Tool.vue` e `PaletteEditor.vue` |
| Rascunho | `lib/saveDraft.ts`/`PaletteDraft` com `scale?`/`roles?` opcionais e assinatura; `GeneratorWorkspace.applyImported`, `restoreDraft` e watcher da base por chave canônica |
| Tipos | `resources/js/types/colors.ts`: `ColorLibraryToolProps`, `PaletteTab`, `FavoritePaletteRow`, `ColorPreset*`, `TokenImportLimits`, `favorited`/`canFavorite` |

Nenhuma dependência nova. O SVG e o PNG dos protótipos continuam como na F4; todas as ferramentas processam 100% no navegador, e só as ações de conta falam com o servidor.

## i18n

Namespaces existentes ampliados nos cinco idiomas (`en`, `es`, `fr`, `pt_BR`, `pt_PT`):

- `tools.shared.library.*` (painéis, importar, limpar, carregar mais) e `tools.palette-generator.tabs.library`;
- `colors.favorites.*` (aba, vazio, remover, data) e `colors.public.favorite|favorited|favorite_error`;
- `colors.presets.*` e as validações `preset_*`;
- `tools.palette-generator.export.{dtcg,style_dictionary,typescript,semantic_included}`;
- `tools.shared.token_import.*` (prévia, aplicar, aplicado, 21 erros + 4 avisos do parser).

Paridade garantida por `TranslationsParityTest`; os testes do Vitest cobrem o dicionário e todos os códigos do parser nos cinco idiomas. Nenhuma string visível hardcoded.

## ADRs e documentação

Novas:

| ADR | Tema |
| --- | --- |
| 127 | biblioteca pessoal de cores (histórico/favoritos da conta, chave canônica, sem limite funcional, cursor, escrita otimista) |
| 128 | favoritos de paletas públicas (vínculo sem cópia, 403/404, aba Favoritas e reaparecimento) |
| 129 | presets como entrada da geração (nome + base, sem cota de paleta) |
| 130 | exportação de tokens v1 (primitivas + semânticas, versão, alpha/gamut, DTCG/SD/TS) |
| 131 | biblioteca de cores compartilhada entre Picker e Generator (conta × local, escrita otimista) |
| 132 | histórico anônimo reduzido (20/50) e importação explícita do histórico local |
| 133 | importação validada de tokens (parser puro, limites, diagnóstico, rascunho estendido) |

Emendas de 2026-11-14 (decisões anteriores preservadas): ADR-093 (preset = nome + base), ADR-095 (tokens v1), ADR-096 (anônimo 20/50 e rascunho com escala/papéis), ADR-118 (conta como fonte da verdade) e ADR-123 §7 (rascunho com `scale?`/`roles?` e assinatura).

Docs atualizados sem apagar decisões anteriores: `SPEC-COLORS.md` (v1.2), `README.md` (Fases 1–5, ADR-091 a ADR-133, emendas), `glossario.md` (termos novos e revisados), `html-color-picker.md`, `palette-generator.md`, `minhas-paletas.md`, `paleta-publica.md`, `import-export.md` e `export-formats.md`.

## Testes

| Suíte | Resultado |
| --- | --- |
| Pest — `tests/Feature/Colors/` | `ColorLibraryHttpTest` (14), `PaletteFavoriteHttpTest` (9), `ColorPresetHttpTest` (13), além dos testes da F4 mantidos |
| Pest — unitários de Colors | `ColorsToolPropsTest` (bloco `library` e `tokens`), `TokenImportFixturesTest` (11, fixtures compartilhadas validadas contra `PaletteRules`) |
| Vitest — novas/ampliadas | `color-library`, `color-storage`, `palette-list`, `palette-public`, `palette-presets`, `palette-token-exporters`, `palette-export-panel`, `palette-exporters`, `palette-generator`, `palette-editor`, `palette-draft`, `token-import` (parser, diálogo e workspace) |

As fixtures de tokens em `tests/Fixtures/colors/tokens/**` são compartilhadas por Vitest e Pest, com payloads esperados validados por `PaletteRules` — o PHP não reimplementa o parser.

## Verificação

| Comando | Resultado |
| --- | --- |
| `composer test` | Pint ok; PHPStan **0 erros**; Pest **1585 testes / 28338 asserções — verdes** (1 deprecação em `vendor/pestphp`) |
| `npm run test` | **120 arquivos, 1471 testes — verdes** (249,51 s) |
| `npm run types:check` | Wayfinder + `vue-tsc --noEmit` **sem erros** |
| `npm run lint:check` | **0 erros**, 1191 avisos (baseline pré-existente do repositório) |
| `npm run build` | build de produção concluído (exit 0; somente avisos de tamanho de chunk/plugins) |
| `npm run format:check` | falha pré-existente em **248 arquivos** (Prettier conflita com `vue/html-indent`); não é gate do repositório |
| `npm run build:ssr` | build SSR concluído (exit 0) |

Na primeira execução do `composer test`, o passo Pest foi interrompido pelo **timeout de processo do Composer (300 s)**, não por falha de teste; a reexecução com `COMPOSER_PROCESS_TIMEOUT=1200 composer test` terminou verde (82,9 s de Pest). Nenhum código foi alterado nesta etapa.

## Matriz manual de aceite

Execução manual pendente pelo usuário; cada linha deve ser marcada no PR.

| # | Cenário | Passos | Esperado | Status |
| --- | --- | --- | --- | --- |
| 1 | Histórico anônimo | Sem sessão, usar 25 cores diferentes no Picker | Guarda as 20 mais recentes, sem duplicar formatações da mesma cor | pendente |
| 2 | Favoritos anônimos | Favoritar 55 cores no Picker | Guarda 50, sem duplicata e sem requisição ao servidor | pendente |
| 3 | Nenhuma requisição anônima | Abrir Picker e Generator sem sessão, usar biblioteca | Nenhuma chamada a `/api/tools/colors` | pendente |
| 4 | Histórico da conta | Logado, usar várias cores e abrir “carregar mais” | Histórico sem teto funcional, paginado por cursor, mais recente primeiro | pendente |
| 5 | Compartilhamento Picker ↔ Generator | Favoritar uma cor no Picker e abrir a biblioteca do Generator (e o inverso) | A mesma lista aparece nas duas tools | pendente |
| 6 | Importação explícita | Anônimo com histórico local, entrar e importar pelo painel | Lotes enviados, contagem conferida e `localStorage` limpo só no fim | pendente |
| 7 | Importação interrompida | Simular falha no meio da importação | Histórico local permanece intacto e o erro é exibido | pendente |
| 8 | Login não mescla | Entrar com histórico local e sem clicar em importar | A conta não ganha as cores locais automaticamente | pendente |
| 9 | Favoritar paleta pública | Abrir pública de outra conta e clicar na estrela duas vezes | Alterna e persiste; repetir não duplica; página mostra `aria-pressed` correto | pendente |
| 10 | Restrições de favorito | Favoritar a própria paleta; tentar privada, arquivada e slug inexistente | Própria: 403 `forbidden`; demais: 404 uniforme | pendente |
| 11 | Aba Favoritas | Abrir Minhas Paletas → Favoritas | Lista as públicas favoritadas; filtros comuns ficam ocultos; paginação própria | pendente |
| 12 | Favorita despublicada | Despublicar/arquivar a origem | Some da aba sem apagar o vínculo; ao republicar, reaparece | pendente |
| 13 | Favorito não é cópia | Favoritar e conferir Minhas Paletas e “Uso” da origem | Nenhuma paleta criada, cota intacta, “Uso” não muda | pendente |
| 14 | Presets | Criar, renomear, aplicar e excluir um preset no gerador | Nome único por conta; aplicar troca a base e recalcula a escala; excluir com confirmação | pendente |
| 15 | Preset não consome cota | Criar presets e conferir a cota de paletas | Cota de paletas não muda; teto opcional de preset respeitado se configurado | pendente |
| 16 | Exportar tokens | Exportar DTCG, Style Dictionary, TypeScript, CSS, SCSS e JSON no gerador e no editor | Gerador só com primitivas; editor com 8 papéis × claro/escuro; versão 1 nos metadados/cabeçalhos | pendente |
| 17 | Alpha e gamut | Exportar base com alpha e base com tom cortado | Alpha em HEX8 onde suportado (`rgba()` em CSS/SCSS/Tailwind v3); gamut e `clippedTones` documentados | pendente |
| 18 | Importar os três formatos | Importar JSON nativo, DTCG e Style Dictionary exportados | Prévia correta; **Aplicar** reconstrói base, 11 tons e papéis (editor) | pendente |
| 19 | Erros de importação | Testar JSON inválido, formato ambíguo, arquivo acima do limite, ciclo de alias e papéis incompletos | Diagnóstico com código; estado atual preservado; **Aplicar** desabilitado com erro | pendente |
| 20 | Ida e volta | Exportar e importar de novo no editor e no gerador | Base, 11 tons e papéis preservados; no gerador só as primitivas | pendente |
| 21 | Rascunho com escala/papéis | Anônimo importar tokens e clicar em Salvar | Login retoma escala e papéis; mudar base/preset/extração descarta os papéis pela assinatura | pendente |
| 22 | Editor arquivado | Abrir paleta arquivada | “Importar tokens” indisponível; leitura somente até restaurar | pendente |
| 23 | Idiomas | Percorrer biblioteca, favoritos, presets e importação nos 5 idiomas | 100% traduzido, sem chave crua nem texto fixo | pendente |
| 24 | Responsivo/tema/teclado | 375/768/1024/1280 px, claro/escuro e navegação por teclado | Sem overflow horizontal; contraste e foco visíveis nos painéis e diálogos | pendente |

## Pendências e observações

- Matriz manual acima ainda não executada (não há ambiente autenticado nesta sessão).
- **Teste opt-in de concorrência MySQL** (`COLORS_RUN_MYSQL_CONCURRENCY`) herdado da F4 continua não implementado: os locks e retries estão no código e as suítes cobrem os caminhos sequenciais (upsert, favorito idempotente, teto e unique de preset). Se o usuário quiser a prova com duas conexões, fica como item isolado.
- `npm run format:check` mantém a divergência pré-existente entre Prettier e `vue/html-indent` (248 arquivos no baseline atual); não é gate do repositório e não bloqueia a fase.
- Fora de escopo por decisão: API do Figma (exportação é JSON baixado); importação de TypeScript/CSS/SCSS/Tailwind; paleta anônima no backend; endpoint de importação; mesclar histórico local no login.
- As ações de conta (biblioteca, presets, favorito de paleta, importação do histórico) são a exceção documentada à regra de que as ferramentas rodam 100% no navegador.
- **Sem commit e sem push**, conforme CLAUDE.md; o PR e cada commit seguem pelo gitpr com aprovação do usuário.

## Arquivos

Novos — backend: migrations `color_history_entries`, `color_favorites`, `color_palette_favorites` e `color_presets`; models `ColorHistoryEntry`, `ColorFavorite`, `ColorPaletteFavorite` e `ColorPreset`; actions `RecordColorHistory`, `ImportColorHistory`, `FavoriteColor`, `UnfavoriteColor`, `FavoritePalette`, `UnfavoritePalette` e `CreateColorPreset`; controllers `ColorHistoryController`, `ColorFavoriteController`, `PaletteFavoriteController` e `ColorPresetController`; requests de histórico/favoritos/presets; `ColorKey`, `ColorPresetQuota`; `ColorPresetFactory`; testes `ColorLibraryHttpTest`, `PaletteFavoriteHttpTest`, `ColorPresetHttpTest` e `TokenImportFixturesTest` + fixtures `tests/Fixtures/colors/tokens/`.

Novos — frontend: `lib/colors/{api,library}.ts`; `components/colors/{HistoryPanel,FavoritesPanel,ImportLocalHistoryDialog,FavoritePaletteCard,PresetDialog,TokenImportDialog}.vue`; `tools/palette-generator/lib/{tokenExporters,tokenImport}.ts`; testes `color-library`, `palette-presets`, `palette-token-exporters`, `palette-export-panel` e os testes da importação (parser, diálogo e workspace).

Novos — docs: `docs/adr/ADR-127…133`, planos das Etapas 4–7 em `docs/claude-code/plans/feature_colors-fase-5/`, relatórios das Etapas 1–6 e este relatório final.

Alterados: `app/Models/User.php`, `config/colors.php`, `routes/colors.php`, `app/Support/Colors/ColorsToolProps.php`, `app/Support/ToolProps.php`, `app/Http/Controllers/Colors/{PaletteController,PublicPaletteController}.php`, `app/Support/Colors/{PaletteRules,PalettePresenter}.php`, `lang/*/colors.php` e `lang/*/tools.php`, `resources/js/types/colors.ts`, `resources/js/lib/{exporters,saveDraft}.ts`, `resources/js/tools/palette-generator/{Tool,GeneratorWorkspace}.vue`, `resources/js/tools/palette-generator/components/ExportPanel.vue`, `resources/js/tools/html-color-picker/**`, `pages/dashboard/colors/{PalettesIndex,PaletteEditor}.vue`, `pages/colors/PublicPalette.vue`, testes ampliados (`palette-generator`, `palette-exporters`, `palette-editor`, `palette-list`, `palette-public`, `palette-draft`, `color-storage`, `ColorsToolPropsTest`, `ToolPageTest`), ADRs 093/095/096/118/123 e os nove documentos de `docs/colors/`.
