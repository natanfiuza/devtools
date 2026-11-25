# Critérios de aceite — Programa Futuro FASE 1 (conversor de formatos e diff)

Gerado em 2026-11-24.
Fonte: `docs/ROADMAP.md` §3.3 (ADR-179), `docs/adr/ADR-180-futuro-fase-1-conversor-diff.md`,
levantamento em `docs/survey/20261124_futuro_fase_1_surveyfacts.md` e plano em
`docs/claude-code/plans/feature_futuro-fase-1/20261124_feature_futuro-fase-1_futuro_fase_1.md`.

## Escopo da fase

- **`data-format-converter`**: a matriz completa JSON ↔ YAML ↔ TOML ↔ XML por árvore
  intermediária, com formato de origem e destino escolhidos na tela (sem detecção automática),
  conversão no mesmo formato como normalização, conversões com perda sempre convertidas e avisadas com
  contagem, exemplos, abrir arquivo, copiar e baixar; acima de 128 KiB o trabalho vai para o worker.
- **`diff`**: modo texto (Myers com corte de prefixo/sufixo e orçamento de edições, queda posicional
  avisada) e modo JSON (comparação semântica por caminho, arrays por índice, ordem de chave
  irrelevante), dois painéis com rótulos próprios, navegação entre diferenças com rolagem
  sincronizada, resumo, relatório para copiar e baixar; worker com o mesmo limite.
- **Infra compartilhada**: `ToolShell` com rótulos de painel opcionais (`paneLabels`) e `CodeEditor`
  com realce por linha (`lineHighlights`) e a `EditorView` exposta, sem mudar o comportamento das 15
  tools anteriores.
- **Registro, i18n e SEO**: os dois manifestos na categoria `text` (ordens 130 e 140), ícones,
  blocos completos nos cinco idiomas, pins do registro e as duas imagens OG 1200×1200.
- **Testes**: motor das duas tools (matriz, convenção do XML, perdas, Myers, JSON semântico,
  relatório), fluxos de tela, os dois componentes compartilhados e a regressão das 15 tools.
- **Documentação**: ADR-180, este aceite, ROADMAP (status e revisão), SPEC §5/§9, CHANGELOG,
  glossário, levantamento, plano e relatório.
- **Gates reais** e verificação no navegador; **nenhum** commit, push ou deploy.

Fora da fase (decisões já registradas): as demais fases do programa; detecção automática de formato;
realce de sintaxe de YAML/TOML/XML no editor; merge de três vias; diff de array por LCS; histórico,
conta, cota, retenção ou qualquer endpoint; novas dependências além das duas aprovadas; deploy.

## Matriz requisito → implementação → teste → situação

| # | Requisito | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Duas dependências aprovadas, lazy, peso registrado | `package.json` (`yaml@^2.9.1`, `smol-toml@^1.9.1`), `Tool.vue` lazy | `npm ls`; pesos no `ADR-180` e neste aceite | auto + manual | OK |
| 2 | `ToolShell` com rótulos de painel opcionais | `resources/js/components/tool/ToolShell.vue` | `tool-shell-labels.test.ts` | auto | OK |
| 3 | `CodeEditor` com realce por linha e `EditorView` exposta | `CodeEditor.vue`, `lib/editor/highlights.ts`, `lib/editor/theme.ts`, `createEditor.ts` | `code-editor-highlights.test.ts` | auto | OK |
| 4 | Matriz 4×4 por árvore intermediária | `lib/tree.ts`, `lib/parse.ts`, `lib/serialize.ts`, `lib/convert.ts` | `data-format-converter-convert.test.ts` | auto | OK |
| 5 | Seleção explícita de origem/destino e normalização de mesmo formato | `lib/formats.ts`, `Tool.vue` | `data-format-converter-convert.test.ts`, `-tool.test.ts` | auto | OK |
| 6 | Convenção do XML nos dois sentidos | `lib/parse.ts`, `lib/serialize.ts` | `data-format-converter-xml.test.ts` | auto | OK |
| 7 | Conversões com perda convertidas e avisadas, com contagem real | `lib/lossy.ts`, `lib/parse.ts`, `lib/serialize.ts`, `lib/convert.ts` | `data-format-converter-lossy.test.ts`, `-xml.test.ts`, `-convert.test.ts` | auto | OK |
| 8 | Worker acima de 128 KiB com queda para a thread principal | `lib/convert.worker.ts`, `lib/convert.workerHost.ts`, `lib/diff.worker.ts`, `lib/diff.workerHost.ts` | `-tool.test.ts`, `diff-tool.test.ts`; navegador | auto + manual | OK |
| 9 | Diff de texto por Myers com orçamento e `truncated` | `tools/diff/lib/lines.ts`, `lib/diff.ts` | `diff-lines.test.ts` | auto | OK |
| 10 | Diff de JSON semântico por caminho, arrays por índice | `tools/diff/lib/jsonDiff.ts`, `lib/diff.ts` | `diff-json.test.ts` | auto | OK |
| 11 | Relatório para copiar e baixar nos dois modos | `tools/diff/lib/report.ts` | `diff-report.test.ts` | auto | OK |
| 12 | Tela do conversor (exemplos, arquivo, trocar, copiar, baixar, erro com linha/coluna) | `tools/data-format-converter/Tool.vue` | `data-format-converter-tool.test.ts` | auto | OK |
| 13 | Tela do diff (rótulos, dois painéis, navegação, rolagem, resumo, abas mobile) | `tools/diff/Tool.vue` | `diff-tool.test.ts`; navegador | auto + manual | OK |
| 14 | Registro: manifestos, ícones, categoria e ordem, pins de slug | `manifest.json` das duas tools, `icons.ts`, `tools-registry.test.ts`, `ToolsRegistryTest.php` | Pest `ToolsRegistryTest`; `tools-registry.test.ts` | auto | OK |
| 15 | i18n nos cinco idiomas, sem texto fixo | `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` | `-tool.test.ts`, `diff-tool.test.ts`, `code-editor-highlights.test.ts`; Pest `TranslationsParityTest` | auto | OK |
| 16 | SEO: OG 1200×1200 das duas tools, sitemap e card | `public/og/data-format-converter.png`, `public/og/diff.png` | Pest `SeoMetaTest`, `ToolsRegistryTest` | auto + manual | OK |
| 17 | Privacidade: nada do conteúdo sai do navegador | `Tool.vue` das duas tools (nenhum `fetch`/`axios`) | navegador (rede vazia fora dos estáticos); grep | auto + manual | OK |
| 18 | Nenhuma regressão nas 15 tools e no resto do site | suíte inteira, `types:check`, `build` | gates abaixo | auto | OK (com observações) |

## Resultados reais dos gates (2026-11-24)

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formatação PHP | `composer test` (Pint) | verde (`{"tool":"pint","result":"passed"}`) |
| Análise estática PHP | `composer test` (PHPStan) | verde (`{"tool":"phpstan","result":"passed","errors":0}`) |
| Suíte PHP | `php artisan test` | **1857 testes, 1855 passaram, 2 skipped, 33857 asserções**, exit 0, 315 s |
| Suíte JS | `npx vitest run`, em dois shards | **173 arquivos, 2087 testes passaram, 0 falhas** (35 arquivos de Layout, 399 testes, com 2 workers; os outros 138 arquivos, 1688 testes) |
| Tipos | `npm run types:check` | exit 0 (Wayfinder + `vue-tsc --noEmit`) |
| Formatação | `npm run format:check` | exit 1 em **251 arquivos pré-existentes**, **nenhum da F1**; os arquivos da fase passam no `prettier --check` direto |
| Lint | `npm run lint:check` | exit 0 — **0 erros**, 694 avisos (`vue/html-indent` em templates Vue) |
| Build | `npm run build` | exit 0, `✓ built in 1m37s`; pesos na ADR-180 |

Pesos de build medidos depois da correção (min / gzip): chunk da tela do conversor 127,9 kB / 40,4 kB;
worker do conversor 135,6 kB / 39,1 kB; chunk da tela do diff 16,0 kB / 5,8 kB; worker do diff
4,6 kB / 2,0 kB.

Observações reais dos gates:

- Os 2 skips da suíte PHP são os dois casos de `LayoutSystemConcurrencyTest` opt-in, como na F6.
- `composer test` roda Pint e PHPStan (os dois verdes) e depois a suíte com o timeout de processo de
  300 s do Composer; nesta máquina a suíte leva 315 s e o Composer aborta **depois** do Pint/PHPStan.
  A suíte foi rodada pelo comando equivalente `php artisan test` (o mesmo do script), com o
  resultado acima; nenhum teste foi pulado ou filtrado.
- A máquina chegou a **0,03 GB de RAM livre** durante a sessão. Com o paralelismo default do Vitest
  (7 forks), o `npx vitest run` de uma vez só estourou o `testTimeout` de 20 s em 1 a 4 montagens
  pesadas do Layout Harmonizer (66 tokens por montagem), que **não usam** nada da F1; cada arquivo
  que falhou passa sozinho (por exemplo, `layout-harmonizer-workspace.test.ts`: 14/14). A suíte foi
  então rodada em dois shards sequenciais (os 35 arquivos `layout-*` com `--maxWorkers=2` e todos os
  demais de uma vez), cobrindo os 173 arquivos e 2087 testes sem falha.
- `format:check` já falhava antes da fase no repositório inteiro (251 arquivos, todos com o template
  em 2 espaços que o Prettier configurado não aceita); os arquivos da F1 foram formatados e passam
  no `prettier --check` direto. Não houve reformatação em massa.
- Os 694 avisos de lint são `vue/html-indent` nos templates (a mesma classe que a F6 registrou como
  pré-existente); os quatro `.vue` da fase respondem por 538 deles e **0 erros**. Os arquivos da F1
  seguem o Prettier, que a F6 adotou como padrão do repositório.

## Verificação no navegador (Playwright)

Feita com o Chromium real do Playwright contra o build servido local (`php artisan serve`), com
capturas em `.playwright-mcp/` (scratch local, fora do versionamento; cópias no relatório).

| Verificação | Resultado observado |
| --- | --- |
| Conversor XML → JSON | `{"config":{"@env":"dev","db":[{"@host":"localhost"},{"@host":"replica"}]}}` na saída, com a convenção `@`/`#text`; **sem** aviso de comentário num XML sem comentário |
| Conversor YAML → TOML | saída TOML válida; avisos reais de `comments-dropped` (com a contagem do texto) e `null-dropped` (contagem dos nulos) |
| Aviso de perda só quando perdeu | o mesmo XML sem comentário não mostra aviso; o YAML com `#` mostra |
| Diff de texto | realce com as classes `changed`/`added` nos dois painéis; “Próxima diferença” levou a rolagem a 979/979 nos dois lados |
| Diff de JSON | caminhos `$.flags.debug`, `$.tags[1]` e `$.owner`; chave reordenada não aparece como diferença |
| Mobile | abas Origem/Destino no conversor e Original/Modificado no diff, com o conteúdo certo em cada aba |
| Cinco idiomas | rótulos dos painéis, status e frases de perda conferidos em `en`, `pt-BR`, `pt-PT`, `fr` e `es` |
| Tema escuro | `html.dark` aplicado, fundo `rgb(11, 12, 15)`, realce legível nos dois painéis |
| Worker | entrada de 192.781 bytes (188,3 kB de 5 MB) processada com a saída correta |
| Rede | nenhuma requisição não estática depois de digitar (worker, clipboard e download são locais) |

## Checklist manual

Executar depois do deploy candidato (ou build local servido); marcar cada item.

1. Abrir `/{locale}/tools/data-format-converter` nos cinco idiomas: nome, descrição, exemplos e
   rótulos traduzidos; o card da home e o sitemap listam a tool.
2. Converter um exemplo em cada uma das dezesseis combinações; conferir o valor de volta no formato
   de destino e que “trocar” inverte origem e destino.
3. Converter JSON com `null` para TOML e conferir o aviso com a contagem; converter YAML com `#` e
   conferir o aviso de comentário; converter XML sem comentário e conferir que **não** há aviso.
4. Colar um XML com atributos, `#text` e irmãos repetidos; conferir `@`/`#text`/array na saída JSON e
   fazer a volta para XML.
5. Abrir um arquivo de cada formato pelo seletor e por arrastar-e-soltar; conferir extensões aceitas,
   copiar e baixar o resultado com o nome e a extensão do destino.
6. Abrir `/{locale}/tools/diff`: rótulos Origem/Destino, exemplos, resumo, navegação anterior/próxima
   com rolagem sincronizada e relatório copiado e baixado.
7. No modo JSON, chave reordenada não é diferença; item de array trocado aparece pelo índice; objeto
   esvaziado aparece como diferença.
8. Entrada maior que 128 KiB nas duas tools (a mesma de 188,3 kB); conferir o processamento no worker
   e o tamanho exibido.
9. Mobile: as abas Origem/Destino e Original/Modificado mostram o conteúdo certo; tocar em uma
   diferença leva à linha.
10. Tema escuro e claro, e as 15 tools anteriores com Entrada/Saída e sem realce.

## Desvios registrados e correções

| ID | Achado | Correção |
| --- | --- | --- |
| C1 | O conversor avisava “Comments are not kept: 1.” em toda origem não-JSON, mesmo sem comentário | o parser passou a contar os comentários que o texto realmente tem (visita do documento `yaml`, varredura de strings do TOML, caminhada do DOM no XML); `convert.ts` deixou de somar por formato; testes de regressão para “com contagem real”, “sem comentário” e “`#` dentro de string/bloco” |
| F1 | Prettier pendente em `parse.ts` e no teste de perdas | `prettier --write` só nos arquivos da fase |
| F2 | Comentário em ASCII com “até” acentuado (fora do padrão do arquivo) | reescrito sem acento |

## Pendências

| ID | Pendência | Gravidade | Impacto | Evidência | Ação |
| --- | --- | --- | --- | --- | --- |
| P1 | `format:check` falha em 251 arquivos pré-existentes (o repositório inteiro, inclusive tools intocadas) | Baixa | O gate não fica verde; os arquivos da F1 passam no Prettier | `npx prettier --check .` → 251 arquivos; os caminhos da F1 não aparecem; `prettier --check` direto nos da F1 → “All matched files use Prettier code style!” | Reformatação em massa num trabalho separado; a fase não pinta 251 arquivos alheios |
| P2 | A unidade curta de byte em inglês imprime “98 byte of 5 MB” (`Intl` mantém `byte` invariante) | Baixa | Cosmético, em todas as tools com contador de bytes; anterior à F1 | `lib/bytes.ts` compartilhado; visível no rodapé do conversor em `en` | Tratar num ajuste do `lib/bytes.ts` (todas as tools), fora da F1 |
| P3 | `.playwright-mcp/` com as capturas é scratch local e não está no `.gitignore` | Baixa | Risco de commit acidental de evidência | `git status` mostra o diretório inteiro como não rastreado | Decidir se o diretório entra no `.gitignore`; o conteúdo útil foi copiado para o relatório |
| P4 | `JSON.parse` reordena chaves que parecem inteiro | Baixa | A ordem dessas chaves no conversor segue o motor; o diff não é afetado (compara por caminho) | registrado na ADR-180 e coberto por teste | Nenhuma ação: limitação documentada |

## Deploy e rollback

- **Deploy**: caminho normal do cron (RUNBOOK §2). Não há migration, job, comando nem `.env`: as duas
  tools são só código de cliente, i18n, manifestos e imagens OG. O deploy publica os assets novos e o
  sitemap passa a listar as duas URLs.
- **Nenhum dado novo**: nada é persistido; desinstalar a superfície não deixa resíduo em banco ou
  storage.
- **Rollback**: voltar a release anterior; as tools somem do catálogo e do sitemap junto com o código.
  Não há estado a migrar de volta.
- **Desligar sem rollback**: não há flag; como não há dependência de servidor, o caminho é a release.

## Decisões não aprovadas (para o pedido)

Nenhuma decisão de produto precisou ser reaberta. As escolhas de implementação que o roadmap deixou em
aberto (bibliotecas, árvore intermediária, convenção do XML, regra de perda, Myers com orçamento, JSON
por caminho) foram fechadas no grill de 2026-11-24 e estão na ADR-180; o aceite não acrescentou
decisão nova. A observação do `98 byte` em inglês (P2) e o `format:check` pré-existente (P1) são
herdados do repositório, registrados aqui e não foram corrigidos em massa dentro da fase.
