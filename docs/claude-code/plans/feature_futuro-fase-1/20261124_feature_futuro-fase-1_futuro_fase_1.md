# Programa Futuro — F1: conversor de formatos + diff (código)

## Context

O roadmap (`docs/ROADMAP.md`, ADR-179) definiu a F1 do Programa Futuro como a fase de porte L que entrega
duas tools de cliente: `data-format-converter` (JSON ↔ YAML ↔ TOML ↔ XML) e `diff` (texto e JSON), ambas
100% no navegador. O roadmap está commitado em `feature/futuro-roadmap`; o usuário autorizou iniciar a
primeira fase. Nada existe hoje: não há lib de YAML/TOML/diff no projeto, o `ToolShell` rotula os painéis
como Entrada/Saída e o `CodeEditor` não desenha realce externo. Esta sessão implementa a F1 completa —
código, registro, i18n, testes, SEO, ADR, aceite e relatório — sem commit/push/deploy.

Branch: o usuário cria `feature/futuro-fase-1` (commit do roadmap como base). O agente **para** até a
branch existir (regra de git). Data: 2026-11-24.

## Decisões fechadas no grill (3 rodadas)

| Tema | Decisão |
| --- | --- |
| Branch | Nova `feature/futuro-fase-1`, criada pelo usuário; agente não cria, não commita |
| Conversor | Matriz completa 4×4 via **árvore intermediária** comum (4 parsers + 4 serializadores) |
| Detecção | Seleção **explícita** de entrada e saída; sem auto-detecção |
| Diff | Lado a lado, **diff próprio** (linhas à mão) + rótulos opcionais no `ToolShell` e realce no `CodeEditor` |
| Dependências | **Duas libs**: `yaml` (eemeli) e `smol-toml`; XML via `DOMParser` nativo; sem `@codemirror/merge` |
| Realce de sintaxe | **Sem pacote novo** de linguagem; YAML/TOML/XML em texto simples |
| Perda de conversão | Converte e mostra **aviso visível** listando o que o destino não representa |
| Arrays no diff JSON | Comparação **por índice** (`a[0]` × `b[0]`), documentada no aceite |
| Slugs/categoria | `data-format-converter` e `diff`, categoria existente `text`, ordem 130 e 140 |
| Testes | Motor + fluxos críticos (Vitest) + Pest de registro; verificação manual no navegador |
| ADR | Um `ADR-180` cobrindo parsers, árvore intermediária, convenção XML, diff e limites |

## Fatos do levantamento

- Infra a reusar: `components/tool/ToolShell.vue`, `ToolActionBar.vue`, `ToolButton.vue`, `CodeEditor.vue`,
  `FileDropzone.vue`; `lib/worker/client.ts` (`createWorkerClient`, latest-wins, fallback para a main
  thread) + `lib/worker/serve.ts`; `lib/file.ts` (`readTextFile`, `acceptAttribute`), `download.ts`,
  `clipboard.ts`, `bytes.ts`; `composables/useCopyFeedback`, `useFileError`, `useSplitPane`.
- `CodeEditor` aplica `extensions` **uma vez na montagem** e observa `modelValue`/`error`; hoje não expõe a
  `EditorView`. `ToolShell` tem rótulos fixos `tools.ui.input`/`tools.ui.output` no desktop e nas abas do
  mobile; `inheritAttrs: false` descarta props não declaradas.
- Registro: `manifest.json` (`slug`, `status`, `category`, `icon`, `order`, `keywords`), `Tool.vue` lazy via
  `components.ts`, ícone em `icons.ts`, categoria em `types.ts`/`categories.ts` (8 ids; `text` existe),
  chaves `tools.<slug>.name/description/keywords/intro` + UI no próprio bloco, nos 5 idiomas.
- `tests/Feature/Tools/ToolsRegistryTest.php` pina a lista exata de slugs, a ordem, as contagens e o
  sitemap — precisa passar a 17 com os dois novos slugs no fim. `SeoMetaTest` exige um PNG 1200×1200 por
  tool `available`; `php artisan seo:og-image` gera em `public/og/` (GD presente e fontes em
  `storage/app/og-fonts` — funciona localmente).
- Testes de front vivem todos em `resources/js/__tests__/*.test.ts`; Vitest em `jsdom` (DOMParser nativo);
  helper `support/langMessages.ts` carrega as traduções reais. `docs` está no `.prettierignore`.
- ADR mais alto: 179; **180 livre**. Convenção de aceite: `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md`.
- CHANGELOG usa uma entrada por track/fase com "Adicionado" — a F1 terá entrada.
- Nenhuma tool existente cobre YAML/TOML/XML nem diff; `json-viewer` já formata/valida JSON e deve ser
  reusado (`lib/json/parse.ts` → `analyzeJson` com linha/coluna) em vez de reimplementado.

## Entregáveis e arquivos

### 1. Dependências (autorizadas no grill, registradas na ADR-180)

- `npm install yaml smol-toml` (dependencies). Pesos medidos no build e registrados na ADR.
- Nenhum pacote de linguagem do CodeMirror; nenhum `@codemirror/merge`.

### 2. Componentes compartilhados (adições compatíveis)

- `resources/js/components/tool/ToolShell.vue`: prop opcional `paneLabels?: { input?: string; output?: string }`;
  cabeçalhos e abas mobile usam o rótulo quando vier, senão `tools.ui.input`/`tools.ui.output` (default
  preserva as 15 tools).
- `resources/js/components/tool/CodeEditor.vue`: prop opcional `lineHighlights?: LineHighlight[]`
  (`{ line: number; tone: 'added' | 'removed' | 'changed' }`, linha 0-based) aplicada por `StateField` +
  `StateEffect` num watcher; `defineExpose({ view })` para o diff fazer rolagem sincronizada e navegação.
- `resources/js/lib/editor/createEditor.ts` (ou `theme.ts`): campo de estado + `setLineHighlights(view, …)` e
  classes `.cm-diff-added/-removed/-changed` com tokens do tema (claro/escuro).

### 3. Tool `data-format-converter` (`resources/js/tools/data-format-converter/`)

- `manifest.json` (slug, `available`, `text`, ícone `arrow-left-right`, ordem 130, keywords), `Tool.vue`,
  `types.ts`, `lib/formats.ts`, `lib/tree.ts` (modelo `DataValue`), `lib/parse.ts` (JSON via `analyzeJson`;
  YAML via `yaml`; TOML via `smol-toml`; XML via `DOMParser`), `lib/serialize.ts` (JSON, YAML, TOML e XML
  escrito à mão com indentação 2), `lib/lossy.ts`, `lib/convert.ts` (puro: parse → árvore → serialize +
  perdas), `lib/convert.worker.ts` + `lib/convert.workerHost.ts`, `lib/examples.ts`.
- UI: selects de entrada/saída (mesmo formato permitido = normalizar), botão trocar, exemplo, abrir arquivo
  (`.json/.yaml/.yml/.toml/.xml/.txt`), copiar e baixar resultado; erro com linha/coluna no gutter; aviso de
  perdas no status; worker acima de 128 KiB, com fallback na main thread.
- Convenção XML (ADR-180): elemento raiz vira a chave de topo; atributos `@nome`; texto com
  atributos/filhos `#text`; irmãos repetidos viram array; declaração e comentários descartados.
- Perdas avisadas: `null` omitido (TOML), datas TOML viram texto, comentários/ordem YAML não preservados,
  números TOML sem equivalente exato, chaves inválidas no destino.

### 4. Tool `diff` (`resources/js/tools/diff/`)

- `manifest.json` (ícone `diff`, ordem 140), `Tool.vue`, `types.ts`, `lib/lines.ts` (Myers com corte de
  prefixo/sufixo e orçamento de edições; ao estourar, diff grosseiro por blocos com aviso), `lib/jsonDiff.ts`
  (achata por caminho, arrays por índice, ordem de chaves ignorada), `lib/report.ts` (relatório texto:
  hunks no modo texto, lista de caminhos no modo JSON), `lib/diff.worker.ts` + host, `lib/examples.ts`.
- UI: `ToolShell` com `paneLabels` Original/Modificado e dois `CodeEditor` (um por painel), modo
  texto/JSON, abrir arquivo em cada lado, navegação anterior/próxima diferença (rolagem sincronizada),
  resumo no status, copiar e baixar relatório `.txt`; no modo JSON, lista semântica de caminhos abaixo da
  casca; worker acima de 128 KiB.

### 5. Registro, i18n e SEO

- `resources/js/tools/icons.ts`: `arrow-left-right`, `diff` (lucide).
- `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`: blocos completos `tools.data-format-converter.*` e `tools.diff.*`
  (nome em inglês como as demais; toda string visível traduzida).
- `tests/Feature/Tools/ToolsRegistryTest.php`: lista/ordem/contagem/sitemap passam a 17.
- `php artisan seo:og-image` → `public/og/data-format-converter.png`, `public/og/diff.png` (+ default
  atualizado). `SeoMetaTest` passa a cobri-los automaticamente.

### 6. Documentação

- `docs/adr/ADR-180-futuro-fase-1-conversor-diff.md` (formato da casa; alternativas: js-yaml, @iarna/toml,
  parsers próprios, `@codemirror/merge`, hub JSON).
- `docs/acceptance/FUTURO-FASE-1.md` no formato do aceite (escopo, fora de escopo, decisões, arquivos,
  matriz requisito → implementação → teste, gates reais, checklist i18n/SEO/registro, pendências,
  deploy/rollback — sem migration, status).
- `docs/ROADMAP.md`: F1 → `concluída em AAAA-MM-DD` + link do aceite; revisão 1.1 datada.
- `docs/SPEC.md` §9: acrescentar a F1 concluída ao bullet do Programa Futuro, no padrão dos outros tracks.
- `CHANGELOG.md`: entrada da F1 ("Adicionado": conversor, diff, infra de realce/rótulos).
- `docs/glossario.md`: `Árvore intermediária` e `Diff semântico`.
- Artefatos da sessão: `docs/survey/{data}_futuro_fase_1_surveyfacts.md`,
  `docs/plans/feature_futuro-fase-1/{data}_futuro_fase_1_plansfacts.md`, cópia em
  `docs/claude-code/plans/…`, relatório em `docs/claude-code/reports/feature_futuro-fase-1/…`.

### 7. Testes (Vitest, em `resources/js/__tests__/`)

- `data-format-converter-convert.test.ts` (12 pares, normalização, erros por linha/coluna), `…-xml.test.ts`
  (convenção `@`/`#text`/arrays), `…-lossy.test.ts` (perdas reais), `…-tool.test.ts` (colar, arquivo, copy,
  i18n via `langMessages`).
- `diff-lines.test.ts` (hunks, navegação, orçamento/fallback), `diff-json.test.ts` (caminhos, arrays por
  índice, chave reordenada não é mudança), `diff-report.test.ts`, `diff-tool.test.ts` (dois painéis, rótulos,
  copy/download).
- `tool-shell-labels.test.ts` e `code-editor-highlights.test.ts` (defaults antigos intactos + novo API).

## Etapas

| # | Conteúdo | Bloqueio/verificação |
| --- | --- | --- |
| 0 | **Parar** até o usuário criar `feature/futuro-fase-1`; salvar surveyfacts, plansfacts e cópia do plano | `git branch --show-current` na branch certa |
| 1 | Instalar `yaml` + `smol-toml`; escrever ADR-180 | `npm ls yaml smol-toml`; ADR no formato da casa |
| 2 | `ToolShell` paneLabels + `CodeEditor` lineHighlights/expose + testes | testes novos e os 163 existentes verdes |
| 3 | Tool `data-format-converter` completa (motor, worker, UI, i18n, exemplos) + testes | `npm run test` |
| 4 | Tool `diff` completa + testes | `npm run test` |
| 5 | Manifests, ícones, categorias, pins do registro, i18n dos 5 locales, OG | `composer test`, PNGs 1200×1200 |
| 6 | Aceite, ADR, ROADMAP/SPEC/CHANGELOG/glossário, relatório | docs consistentes e links válidos |
| 7 | Gates finais e parada sem commit/push/deploy | resultados reais no aceite/relatório |

## Verificação

1. `npm run test` (novos + 163 antigos), `composer test` (Pint, PHPStan, Pest), `npm run types:check`,
   `npm run format:check`, `npm run lint:check`, `npm run build` — resultados reais registrados.
2. `php artisan seo:og-image`; `getimagesize()` 1200×1200 nos dois PNGs; `SeoMetaTest` verde.
3. Playwright manual (evidência no aceite): JSON→YAML e YAML→TOML com aviso de perda; XML→JSON com
   `@attr`/`#text`; diff texto com navegação e rolagem sincronizada; diff JSON com caminhos; mobile com abas
   Original/Modificado; troca de idioma nos 5 locales; tema claro/escuro.
4. `git status`/`git diff --name-only`: só código de tools, componentes compartilhados, i18n, testes,
   config/package, `public/og` e docs — sem migrations, sem `.gitpr/`, sem tocar em F6.
5. Conteúdo de entrada nunca vai a log; nenhuma requisição de tool ao servidor; strings só via i18n.

## Riscos

- **Peso das libs**: medir no build e registrar na ADR; se algum parser explodir o bundle, reavaliar antes de
  seguir (parar e reportar, não trocar sozinho).
- **Myers em entradas patológicas**: orçamento de edições + fallback grosseiro avisado; testes de caso
  patológico.
- **DOMParser**: mensagem de `parsererror` varia entre navegador e jsdom — testar a convenção e o código de
  erro, nunca o texto do parser.
- **Componentes compartilhados**: adições opcionais com defaults; testes garantem que as 15 tools e o
  mobile seguem com Entrada/Saída.
- **Pins do registro**: atualizar slugs/ordem/contagem/sitemap na mesma etapa dos manifests.
- **Rede/npm**: se a instalação falhar, parar e pedir intervenção (nenhuma dependência alternativa sem ADR).

## Parada

Ao fim: aceite, ADR, docs e relatório escritos; **sem commit, push ou deploy**. O usuário revisa e commita
pelo gitpr. O status da F1 no roadmap só muda para concluída depois dos gates reais.
