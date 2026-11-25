# Relatório — Programa Futuro F1: conversor de formatos e diff

Data: 2026-11-24
Branch: `feature/futuro-fase-1`
Task: `futuro_fase_1`
Origem: `/grill-with-docs` com o pedido "Verificação e commit realizados, pode iniciar a primeira fase"
(plano aprovado em `docs/claude-code/plans/feature_futuro-fase-1/20261124_feature_futuro-fase-1_futuro_fase_1.md`).

## 1. Contexto

A F1 do Programa Futuro (ADR-179) é a fase L da primeira onda: duas tools 100% de cliente.
O repositório não tinha parser de YAML/TOML, motor de diff, nem a infra de tela que as duas
precisam — o `ToolShell` rotulava os painéis como Entrada/Saída e o `CodeEditor` não desenhava
realce externo. Esta sessão entrega a fase inteira: as duas tools, a infra compartilhada, o
registro e o i18n nos cinco idiomas, os testes, o SEO, o ADR-180, o aceite e esta documentação.
Sem commit, push ou deploy.

## 2. Decisões fechadas no grill (2026-11-24, três rodadas)

| Tema | Decisão |
| --- | --- |
| Branch | `feature/futuro-fase-1`, criada pelo usuário; o agente não cria, não commita |
| Conversor | Matriz 4×4 completa via **árvore intermediária** comum (4 parsers + 4 serializadores) |
| Detecção | Seleção **explícita** de origem e destino; sem auto-detecção |
| Diff | Lado a lado, **motor próprio** (Myers) + `paneLabels` no shell e realce no editor |
| Dependências | `yaml@^2.9.1` e `smol-toml@^1.9.1`; XML por `DOMParser`; sem `@codemirror/merge` e sem pacote de linguagem |
| Perda | Converte **sempre** e avisa, agrupado por código com contagem |
| Diff JSON | Comparação semântica por caminho; arrays **por índice**; ordem de chave irrelevante |
| Slugs | `data-format-converter` e `diff`, categoria `text`, ordens 130 e 140 |
| Testes | Motor + fluxos críticos (Vitest) + Pest de registro + verificação no navegador |
| ADR | Um `ADR-180` cobrindo parsers, árvore, convenção XML, diff e limites |

## 3. Entregáveis

| # | Entregável | Arquivo | Situação |
| --- | --- | --- | --- |
| 1 | Levantamento do grill | `docs/survey/20261124_futuro_fase_1_surveyfacts.md` | criado |
| 2 | Plano da sessão | `docs/plans/feature_futuro-fase-1/20261124_futuro_fase_1_plansfacts.md` | criado |
| 3 | Cópia do plano (CLAUDE.md) | `docs/claude-code/plans/feature_futuro-fase-1/20261124_feature_futuro-fase-1_futuro_fase_1.md` | criado |
| 4 | Tool `data-format-converter` | `resources/js/tools/data-format-converter/` (12 arquivos) | criada |
| 5 | Tool `diff` | `resources/js/tools/diff/` (11 arquivos) | criada |
| 6 | Infra compartilhada | `ToolShell.vue`, `CodeEditor.vue`, `lib/editor/{highlights,theme,createEditor}.ts` | ampliada |
| 7 | Registro e i18n | `icons.ts`, `lang/*/tools.php` (5), pins em `tools-registry.test.ts`/`ToolsRegistryTest.php` | atualizado |
| 8 | SEO | `public/og/data-format-converter.png` e `public/og/diff.png` (1200×1200) + `og-default.png` | gerado |
| 9 | Dependências | `package.json`: `yaml`, `smol-toml` | adicionadas |
| 10 | Testes | 10 arquivos novos em `resources/js/__tests__/` | criados |
| 11 | ADR | `docs/adr/ADR-180-futuro-fase-1-conversor-diff.md` | criado |
| 12 | Aceite | `docs/acceptance/FUTURO-FASE-1.md` | criado |
| 13 | ROADMAP/SPEC/CHANGELOG/glossário | status F1, revisão 1.1, §5/§9, entrada da fase, 5 termos | atualizados |
| 14 | Evidência | `evidence/01..06` (capturas do navegador) | criada |

## 4. Implementação

### 4.1 Conversor de formatos

- **Árvore** (`lib/tree.ts`): `DataValue` com objeto (entradas ordenadas), array, string, número,
  booleano, nulo e a data do TOML; `fromPlain` ainda marca as perdas do que o JavaScript não
  distingue (binário do YAML, inteiro fora do `number`, `NaN`/`Infinity`).
- **Parsers** (`lib/parse.ts`): JSON por `analyzeJson` (o mesmo diagnóstico do `json-viewer`,
  linha/coluna inclusos); YAML por `parseDocument` + `toJS` (o primeiro erro do documento vira a
  posição da tela); TOML por `smol-toml`; XML por `DOMParser`. A convenção do XML está na ADR-180.
- **Comentários contados por formato**: YAML pela visita do documento, TOML por uma varredura de
  strings em quatro estados, XML pela caminhada do DOM. O aviso só existe quando o texto tinha
  comentário, com a contagem real.
- **Serializadores** (`lib/serialize.ts`): JSON e XML escritos à mão (indentação 2, ordem
  preservada); YAML e TOML das bibliotecas. O TOML recusa raiz não-tabela e array misto com o
  caminho (`a.b`); o XML recusa nome inválido e atributo não-escalar.
- **Motor** (`lib/convert.ts`): `origem → árvore → destino`, com as perdas da origem (parser) e as
  do destino (serializador) somadas e agrupadas por código.
- **Tela** (`Tool.vue`): dois selects (mesmo formato permitido = normalizar), trocar, exemplo por
  formato, abrir arquivo (json/yaml/yml/toml/xml/txt) por seletor e arrastar-e-soltar, copiar,
  baixar com a extensão do destino, erro com linha/coluna no gutter e o aviso de perdas no status.
  Acima de 128 KiB vai para o worker; sem `Worker`, a thread principal faz o mesmo trabalho.

### 4.2 Diff

- **Texto** (`lib/lines.ts`): Myers (1986) sobre linhas com corte de prefixo/sufixo, orçamento de
  `MAX_EDITS = 2000`; acima, o resultado é posicional e sai marcado `truncated`, com aviso na tela
  e no relatório. Pares removido+adicionado na mesma região viram “alterado”.
- **JSON** (`lib/jsonDiff.ts`): cada documento é achatado em folhas com caminho (`$.a`, `$.a[0]`,
  `$["chave estranha"]`); objeto e array vazios são folhas; arrays por índice; ordem de chave
  irrelevante.
- **Relatório** (`lib/report.ts`): cabeçalho com modo e lados, resumo com contagens, hunks no
  dialeto `@@ -a +b @@` (texto) ou um caminho por linha (JSON).
- **Tela** (`Tool.vue`): `ToolShell` com `paneLabels` Origem/Destino (Original/Modificado no
  mobile), um `CodeEditor` por painel, navegação anterior/próxima com rolagem sincronizada,
  resumo no status, copiar e baixar o relatório; no modo JSON, a lista de caminhos abaixo da casca.
  Mesmo limite de 128 KiB e worker.

### 4.3 Infra compartilhada

- `ToolShell`: prop opcional `paneLabels` (cabeçalhos e abas do mobile); sem ela, Entrada/Saída
  como antes. O arquivo foi reformatado pelo Prettier (2 → 4 espaços) na mesma edição.
- `CodeEditor`: prop `lineHighlights` (linha 0-based, tons `added`/`removed`/`changed`) por
  `StateField` + `StateEffect`, watcher `deep` para a lista nova e `defineExpose({ view })` para a
  rolagem sincronizada. Sem a prop, o editor desenha o mesmo de antes.
- `lib/editor/highlights.ts` e os três tons em `theme.ts` por `color-mix` sobre os tokens, o que
  repinta sozinho na troca de tema.

## 5. Testes

Dez arquivos novos em `resources/js/__tests__/`:

| Arquivo | Cobre | Testes |
| --- | --- | --- |
| `data-format-converter-convert.test.ts` | matriz, normalização, erros | 18 |
| `data-format-converter-xml.test.ts` | convenção `@`/`#text`/arrays, volta | 15 |
| `data-format-converter-lossy.test.ts` | perdas reais, comentário contado, `#` em string/bloco | 11 |
| `data-format-converter-tool.test.ts` | tela, arquivo, copiar/baixar, i18n, worker | 26 |
| `diff-lines.test.ts` | Myers, hunks, orçamento/`truncated` | 14 |
| `diff-json.test.ts` | caminhos, arrays por índice, ordem de chave | 14 |
| `diff-report.test.ts` | relatório dos dois modos | 9 |
| `diff-tool.test.ts` | dois painéis, rótulos, navegação, cópia/download | 21 |
| `tool-shell-labels.test.ts` | padrão intacto + rótulos novos | 5 |
| `code-editor-highlights.test.ts` | realce, troca de lista, sem prop | 7 |

Total: **140 testes novos**. Os testes de mesmo formato conferem o valor relido pelo parser de
destino (YAML/TOML não têm forma contratual); JSON e XML comparam letra a letra, porque a ordem e
a indentação são decisão.

## 6. Evidência no navegador

Chromium real do Playwright contra o build servido local. Capturas em `evidence/`:

| # | Evidência | Resultado observado |
| --- | --- | --- |
| 01 | `01-conversor-xml-para-json.png` | XML → JSON com `@env`, `db` como array e `@host`; **sem** aviso de comentário em XML sem comentário |
| 02 | `02-conversor-yaml-para-toml-com-perdas.png` | YAML → TOML com avisos reais de comentário e de `null`, com contagem |
| 03 | `03-diff-texto-rolagem-sincronizada.png` | realce `changed`/`added`; “Próxima diferença” levou os dois painéis a 979/979 |
| 04 | `04-diff-json-caminhos.png` | `$.flags.debug`, `$.tags[1]` e `$.owner`; chave reordenada não é diferença |
| 05 | `05-diff-mobile-abas.png` | abas Origem/Destino e Original/Modificado com o conteúdo certo |
| 06 | `06-diff-tema-escuro.png` | `html.dark`, fundo `rgb(11, 12, 15)`, realce legível |

Também verificado, sem captura: os cinco idiomas (rótulos, status e frases de perda), entrada de
192.781 bytes pelo worker (188,3 kB de 5 MB, saída correta) e **zero** requisição não estática
depois de digitar.

## 7. Gates reais

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formatação PHP | `composer test` (Pint) | verde |
| Análise estática PHP | `composer test` (PHPStan) | verde, 0 erros |
| Suíte PHP | `php artisan test` | **1857 testes, 1855 passaram, 2 skipped, 33857 asserções**, exit 0, 315 s |
| Suíte JS | `npx vitest run` em dois shards | **173 arquivos, 2087 testes, 0 falhas** |
| Tipos | `npm run types:check` | exit 0 |
| Formatação JS | `npm run format:check` | exit 1 em 251 arquivos **pré-existentes**; nenhum da F1 |
| Lint | `npm run lint:check` | exit 0, 0 erros, 694 avisos de `vue/html-indent` |
| Build | `npm run build` | exit 0, `✓ built in 1m37s` |

Detalhes dos dois desvios de execução:

- **PHP**: o `composer test` abortou a suíte no timeout de 300 s do Composer (Pint e PHPStan já
  verdes); a suíte rodou pelo mesmo comando do script, `php artisan test`, com 315 s.
- **JS**: a máquina ficou com 0,03 GB de RAM livre. O `npx vitest run` de uma vez só (7 forks)
  estourou o limite de 20 s em 1 a 4 montagens do Layout Harmonizer, que não usam nada da F1 e
  passam sozinhas (`layout-harmonizer-workspace.test.ts`: 14/14). Rodando em dois shards — 35
  arquivos `layout-*` com `--maxWorkers=2` (399/399) e os outros 138 de uma vez (1688/1688) — a
  cobertura é a mesma e fica verde.

## 8. Desvios e correções

| ID | Achado | Correção |
| --- | --- | --- |
| C1 | O conversor avisava “Comments are not kept: 1.” em **toda** origem não-JSON, mesmo sem comentário | o aviso passou a vir do parser, que conta os comentários que o texto realmente tem (visita do documento no YAML, varredura de strings no TOML, caminhada do DOM no XML); `convert.ts` não soma mais por formato; três testes de regressão (contagem real, sem comentário, `#` dentro de string/bloco), todos os 70 testes do conversor verdes, `types:check` e build verdes, e o aviso sumiu no navegador |
| F1 | Prettier pendente em `parse.ts` e no teste de perdas | `prettier --write` só nos arquivos da fase |
| F2 | Comentário com “até” acentuado, fora do ASCII dos demais comentários | reescrito |
| F3 | Suíte cheia com timeouts de 20 s em montagens pesadas do Layout Harmonizer sob a carga dos forks | os arquivos passam sozinhos (workspace 14/14); sem relação com as tools novas; a suíte foi rodada em dois shards e ficou verde (2087/2087), como em §7 |

## 9. Riscos e pendências

- **`format:check` pré-existente**: a fase não pinta 223 arquivos do repositório; o resultado
  completo está em §7 e o aceite registra a pendência.
- **`98 byte` em inglês**: unidade curta de byte do `Intl` é invariante; herdada, afeta todas as
  tools; fora da F1.
- **Peso das libs**: medido e registrado na ADR-180; o custo é pago só por quem abre as tools.
- **`JSON.parse` reordena chaves inteiras**: limitação registrada na ADR-180, coberta por teste no
  conversor; o diff não é afetado.
- **`.playwright-mcp/`**: scratch local com as capturas brutas; não está no `.gitignore` (aceite
  P3). As evidências úteis foram copiadas para o relatório.

## 10. Verificação de git e parada

- Branch confirmada: `git branch --show-current` → `feature/futuro-fase-1`.
- Alterados (rastreados): `ToolShell`, `CodeEditor`, `lib/editor/{highlights,theme,createEditor}.ts`,
  `icons.ts`, `package.json`, `lang/*/tools.php` (5), os dois pins de registro, `public/og-default.png`
  e a documentação (CHANGELOG, ROADMAP, SPEC, glossário).
- Novos: as duas pastas de tool, 10 arquivos de teste, `lib/editor/highlights.ts`, os dois PNGs OG,
  ADR-180, aceite, survey, plano, cópia do plano, este relatório e `evidence/`.
- **Sem migration, sem rota, sem endpoint, sem config, sem `.env`**: nada de servidor.
- **Nenhum commit, push ou deploy.** A F1 fica para revisão; o usuário commita e abre o PR pelo
  gitpr.
