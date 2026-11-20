# Relatório — Layout Harmonizer F5 (exportações Markdown, CSS e ZIP)

Data: 2026-11-20
Branch: `feature/layout-hamonizer-fase-4` (grafia da branch como está no repositório)
Fases anteriores: F1 (motor puro, 2026-11-16), F2 (interface do quadro,
2026-11-16), F3 (edição manual, 2026-11-18) e F4 (persistência, 2026-11-19)
Autorização: grill de 2026-11-20 ("Inicie a fase 5"), com decisões em
`docs/survey/20261120_layout_harmonizer_f5_surveyfacts.md`, plano de execução em
`docs/plans/feature_layout-hamonizer-fase-4/20261120_f5_exportacoes_plansfacts.md`
e plano completo em
`docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261120_feature_layout-hamonizer-fase-4_f5_exportacoes.md`.

## 1. Escopo entregue

A F5 fechou o contrato de `PROMPT-Fase5-markdown_css_zip.md` e
`EXPORTACOES.md`: exportar o snapshot **já exibido** como Markdown, CSS e ZIP,
100% no navegador, sem endpoint, sem chamada de IA e sem regenerar nenhuma cor.

- **Exportador puro** em `resources/js/tools/layout-harmonizer/export/`
  (`types`, `options`, `filename`, `messages`, `colors`, `css`, `markdown`,
  `warnings`, `zip`), sem rota, action, job ou migration nova (ADR-175).
- **Markdown de instruções para IA** nos cinco idiomas: título e finalidade,
  metadados (idioma, estratégia, meta, seed original/efetiva/fundo de
  composição, secundária, versões), origem condicional, instruções, temas,
  33 tokens × 2 temas com finalidade/HEX/OKLCH/proveniência/bloqueio, regras de
  aplicação, 16 pares de contraste por tema (razão, AA e AAA), avisos sempre
  presentes e nota de geração (ADR-148, ADR-177).
- **CSS com fallback progressivo**: base HEX e `rgba()` originais (alpha
  preservado) e bloco `@supports (color: oklch(0 0 0))` redeclarando em OKLCH;
  claro no escopo puro e em `[data-theme="light"]`, escuro só em
  `[data-theme="dark"]`; escopo `:root` ou uma classe e prefixo `lh` editável,
  validados com tetos de `config/colors.php`; comentários sanitizados (ADR-155,
  ADR-176).
- **ZIP determinístico** com `fflate ^0.8.3` (dependência direta): sempre
  `design-instructions.md` e `colors.css`, UTF-8, `mtime` fixo
  (`2026-01-01T00:00:00Z`) e nível 6; pacote `{slug}-layout.zip`.
- **Card `LayoutExportPanel.vue`** ao fim do painel: anônimo vê o card
  bloqueado com CTA de login; autenticado escolhe idioma/prefixo/escopo e baixa
  os três formatos; `pendingRegeneration` bloqueia com o mesmo aviso do
  salvamento; compatível/readonly/arquivado exportam com aviso e versões.
  A página pública não exporta para ninguém (ADR-154/ADR-175).
- **Idioma por subtree do servidor**: `LayoutSystemExportMessages::all()`
  entrega os cinco locales (o cliente só recebe o locale ativo), com paridade
  garantida pelos testes de tradução.
- **ADRs, glossário e parâmetros**: 148 e 155 copiados com seção de
  implementação; novos 175, 176 e 177; `EXPORTACOES.md`, `ADR-MAPEAMENTO.md`,
  `docs/colors/README.md`, `docs/SPEC.md` §5/§9, `docs/glossario.md` e
  `PARAMETROS.md` atualizados.

Fora da fase, como aprovado: protótipo editável/exportável, exportação pela
página pública ou por API, cópia integral do arquivo (só download), qualquer
servidor de exportação, commits e push.

## 2. Arquivos

### Criados — exportador e UI

| Arquivo | Conteúdo |
|---|---|
| `resources/js/tools/layout-harmonizer/export/types.ts` | `LayoutExportInput`, `LayoutExportFile`, idiomas, opções e guarda `isLayoutExportLanguage` |
| `resources/js/tools/layout-harmonizer/export/options.ts` | defaults (`lh`, `:root`), regex e validação de prefixo/escopo |
| `resources/js/tools/layout-harmonizer/export/filename.ts` | `sanitizeExportName`, nomes de arquivos e do ZIP |
| `resources/js/tools/layout-harmonizer/export/messages.ts` | lookup no subtree, interpolação `{chave}` e escapes (Markdown, célula, comentário CSS) |
| `resources/js/tools/layout-harmonizer/export/colors.ts` | HEX/`rgba()`/`oklch()` do snapshot, alpha preservado |
| `resources/js/tools/layout-harmonizer/export/warnings.ts` | avisos medidos contra `snapshot.target` (AA → `aaNormal`, AAA → `aaaNormal`), bloqueios, manuais, ajustes aceitos, clipados e modos |
| `resources/js/tools/layout-harmonizer/export/css.ts` | `buildLayoutCss` |
| `resources/js/tools/layout-harmonizer/export/markdown.ts` | `buildLayoutMarkdown` |
| `resources/js/tools/layout-harmonizer/export/zip.ts` | `buildLayoutZip` com `fflate` |
| `resources/js/tools/layout-harmonizer/components/LayoutExportPanel.vue` | card de exportação (anônimo, autenticado, avisos de estado) |

### Criados — backend

| Arquivo | Conteúdo |
|---|---|
| `app/Support/Colors/LayoutSystemExportMessages.php` | subtree `tools.layout-harmonizer` necessário nos cinco locales |
| `tests/Unit/Colors/LayoutSystemExportMessagesTest.php` | paridade das folhas e conteúdo exigido |

### Criados — testes

| Arquivo | Casos |
|---|---|
| `resources/js/__tests__/layout-harmonizer-export-options.test.ts` | limites 32/64 e regex do grill |
| `resources/js/__tests__/layout-harmonizer-export-css.test.ts` | estrutura, `@supports`/ordem, alpha, prefixo/escopo, sanitização, versões, avisos, determinismo |
| `resources/js/__tests__/layout-harmonizer-export-markdown.test.ts` | seções, 33 tokens × 2 temas, origem, avisos, escape e os 5 idiomas |
| `resources/js/__tests__/layout-harmonizer-export-zip.test.ts` | `unzipSync`, nomes fixos, UTF-8, conteúdo e bytes determinísticos |
| `resources/js/__tests__/layout-harmonizer-export-panel.test.ts` | anônimo/CTA, downloads, validação, `pendingRegeneration`, idioma, avisos e origem |
| `resources/js/__tests__/layout-harmonizer-export-consistency.test.ts` | quadro === MD/CSS e sentinela provando que não regenera |

### Alterados

| Arquivo | Mudança |
|---|---|
| `package.json` | `fflate ^0.8.3` como dependência direta |
| `config/colors.php` | `layout_systems.export.prefix_max_length` (32) e `scope_max_length` (64) |
| `app/Support/Colors/LayoutSystemToolProps.php` | props `exportMessages` e `exportLimits` |
| `app/Http/Controllers/Colors/LayoutSystemController.php` | `exportMessages`/`exportLimits` no editor |
| `app/Http/Controllers/Colors/LayoutSystemIntentController.php` | `export=1` volta ao tool liberando o card, sem abrir o modal de salvar |
| `resources/js/types/layoutSystem.ts` | `LayoutSystemExportMessages`, `LayoutSystemExportLimits` |
| `resources/js/tools/layout-harmonizer/Tool.vue` | repassa mensagens/limites/origem |
| `resources/js/tools/layout-harmonizer/LayoutHarmonizerPanel.vue` | monta o card com snapshot, nome sugerido e estado do controller |
| `resources/js/pages/dashboard/colors/LayoutSystemEditor.vue` | card no painel e no caminho arquivado/readonly; origem só do crédito congelado público |
| `lang/{en,es,fr,pt_BR,pt_PT}/tools.php` | grupo `export` (painel, `md.*`, `css.*`, avisos e erros) |
| `resources/js/__tests__/support/langMessages.ts` | helper `layoutExportMessages()` |
| `resources/js/__tests__/layout-harmonizer-i18n.test.ts` | chaves estáticas, placeholders e proibições do grupo `export` |
| `resources/js/__tests__/layout-system-editor.test.ts` | card no editor, origem congelada e aviso readonly no arquivado |
| `resources/js/__tests__/layout-system-public.test.ts` | página pública sem card |
| `tests/Feature/Colors/LayoutSystemHttpTest.php` | props novas e intent `export=1` |
| `tests/Feature/Tools/ToolPageTest.php` | props novas no tool anônimo/autenticado |

### Documentação

`docs/adr/ADR-148-colors-lh-exportacoes.md` e
`docs/adr/ADR-155-colors-lh-css.md` (cópias locais + "Implementação (F5)");
`docs/adr/ADR-175-colors-lh-exportacao-local.md`,
`ADR-176-colors-lh-css-fallback.md` e
`ADR-177-colors-lh-markdown-ia.md` (novos);
`docs/colors/layout-harmonizer/EXPORTACOES.md` (contrato implementado),
`ADR-MAPEAMENTO.md` (status/contagem F5), `PARAMETROS.md`
("Parâmetros das exportações (F5)" e versão "Exportações 1"),
`docs/colors/README.md`, `docs/SPEC.md` §5/§9 ("F5 concluída em 2026-11-20") e
`docs/glossario.md` (exportação local, prefixo CSS, escopo CSS, documento de
instruções).

## 3. Decisões da fase (resumo)

| Tema | Decisão |
|---|---|
| Onde gera | Cliente, sem endpoint; snapshot exibido é a única fonte |
| ZIP | `fflate` direto, nomes fixos, `mtime`/nível fixos, `{slug}-layout.zip` |
| Anônimo | Card bloqueado com CTA `create?export=1` (não abre o modal de salvar) |
| Página pública | Nenhuma exportação para ninguém |
| Fallback | HEX/`rgba()` + `@supports (color: oklch(0 0 0))` |
| Escopo/prefixo | `:root` ou classe simples; prefixo kebab-case; tetos 32/64 |
| Idiomas | Seletor com default no locale ativo; subtree do servidor nos 5 |
| Origem no MD | Só crédito congelado público; nunca paleta ao vivo nem link |
| Estados | Exporta live/compatível/readonly/arquivado; só `pendingRegeneration` bloqueia |
| Avisos | Falha de contraste nunca bloqueia; aparece nos dois arquivos |

## 4. Testes e gates (executados em 2026-11-20)

| Gate | Resultado |
|---|---|
| `composer test` | Pint passed; PHPStan 0 erros; Pest **1835 testes / 33132 asserções**, todos passando |
| `npm run test` | **160 arquivos / 1917 testes**, todos passando |
| `npm run types:check` | Wayfinder + `vue-tsc --noEmit` sem erros |
| `npm run format:check` | Prettier verde após formatar 11 arquivos novos |
| `npm run lint:check` | exit 0, 0 erros; warnings são o baseline do repo (206 no componente novo, mesma ordem dos demais `.vue`) |
| `npm run build` | exit 0 |

Nota: o `composer test` emitiu 1 depreciação interna do Pest
(`DatasetsRepository`) e um aviso de UTF-8 do Pango no ambiente; nenhum dos dois
vem do código da fase.

## 5. Verificação manual

Os sete passos da seção "Verificação manual" do plano (CTA de login, exportação
sem salvar, editor e arquivado, aviso de contraste, `pendingRegeneration`,
prefixo/escopo e ZIP, 5 idiomas sem requisição de rede) **não foram executados
em navegador nesta sessão**; ficam para a revisão do usuário. O comportamento
correspondente está coberto pelos testes de componente e de consistência.

## 6. Pendências e riscos residuais

- O card remonta no `:key` da regeneração e volta idioma/prefixo ao default —
  comportamento aceito e documentado (ADR-175).
- O gate de exportação é de produto, não DRM: as cores continuam inspecionáveis
  no DevTools, como o contrato admite.
- Determinismo do ZIP é testado no mesmo processo, comparando duas gerações e
  o conteúdo descompactado — não com bytes dourados (a data DOS depende de
  fuso).
- Nenhuma migration, endpoint, dependência além do `fflate` autorizado ou
  alteração de ADR aprovado.

## 7. Parada

F5 encerrada. **Sem commit/push** (o usuário commita e abre o PR pelo gitpr) e
**sem iniciar a F6**, conforme o plano aprovado. É preciso intervenção manual do
usuário para revisar e commitar.
