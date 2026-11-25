# ADR-180 — F1 do Programa Futuro: conversor de formatos e diff
Data: 2026-11-24
Status: Aceito

## Contexto

A F1 do Programa Futuro (ADR-179) entrega duas tools 100% de cliente:
`data-format-converter` (JSON ↔ YAML ↔ TOML ↔ XML, a matriz completa dos quatro formatos) e `diff`
(texto e JSON). Nenhuma das duas tem equivalente no catálogo — o `json-viewer` valida e formata JSON,
e o `share-text` compartilha texto, mas nada converte entre formatos nem compara dois documentos.

O roadmap deixou em aberto as decisões de implementação: bibliotecas, o caminho da matriz 4×4, a
convenção do XML na árvore, o que fazer com conversões que perdem informação, como comparar JSON e como
realçar o diff na tela. As decisões abaixo foram fechadas no grill de 2026-11-24 (três rodadas,
registradas em `docs/survey/20261124_futuro_fase_1_surveyfacts.md`) e não reabrem nenhuma decisão de
produto.

Fatos do levantamento que pesaram:

- a infra de cliente já existe e cobre quase tudo: `ToolShell`/`ToolActionBar`/`ToolButton`,
  `CodeEditor`, `FileDropzone`, o worker com queda para a thread principal, `lib/file`, `download`,
  `clipboard`, `bytes` e os composables de cópia e de erro de arquivo;
- o `CodeEditor` aplica `extensions` na montagem e não expõe a `EditorView`; o `ToolShell` tem os
  rótulos fixos `tools.ui.input`/`tools.ui.output`;
- o `json-viewer` já tem `lib/json/parse.ts` com `analyzeJson` (linha e coluna); reimplementar JSON
  seria duplicar o diagnóstico;
- o registro de tools exige manifesto, ícone, categoria, i18n nos cinco idiomas, OG 1200×1200 e os
  pins de `ToolsRegistryTest`/`tools-registry.test.ts` na mesma etapa.

## Decisão

### Dependências

Duas bibliotecas, e só duas:

| Pacote | Versão | Papel |
| --- | --- | --- |
| `yaml` (eemeli) | `^2.9.1` | parser do YAML |
| `smol-toml` | `^1.9.1` | parser do TOML |

JSON usa `analyzeJson` (motor próprio do projeto); XML usa `DOMParser`, nativo. Nenhum pacote novo de
linguagem do CodeMirror: YAML, TOML e XML aparecem em texto simples no editor, como já acontece com
os formatos sem gramática no resto do catálogo.

Peso medido no build de 2026-11-24 (minificado / gzip):

| Artefato | Min | Gzip |
| --- | --- | --- |
| chunk da tela do conversor (inclui os parsers da queda na thread principal) | 127,9 kB | 40,4 kB |
| worker do conversor | 135,6 kB | 39,1 kB |
| chunk da tela do diff | 16,0 kB | 5,8 kB |
| worker do diff | 4,6 kB | 2,0 kB |

O custo é pago só por quem abre a tool: os dois chunks são lazy, como todas as tools (ADR-018). Os
parsers aparecem duas vezes (tela e worker) porque a queda para a thread principal precisa deles no
bundle da tela; é o preço de não haver um segundo caminho de conversão para o fallback.

### Matriz 4×4 por árvore intermediária

Uma árvore comum (`DataValue`: objeto com entradas, array, string, número, booleano, nulo e a data do
TOML) fica entre os formatos: **um parser por formato, um serializador por formato**, e a conversão é
sempre `origem → árvore → destino`. Quatro parsers e quatro serializadores cobrem as dezesseis
combinações, e o mesmo formato nos dois lados normaliza (JSON → JSON formata, por exemplo).

A escolha do formato de entrada e de saída é **explícita**: não há detecção automática. Um `.txt` não
diz formato nenhum, e adivinhar erraria em silêncio no caso ambíguo (um YAML que também é JSON).

### Convenção do XML

- o elemento raiz vira a chave de topo;
- atributo vira `@nome`;
- o texto de um elemento que também tem atributo ou filhos vira `#text`;
- irmãos com o mesmo nome viram array, na ordem do documento;
- declaração, comentários e instruções de processamento são descartados;
- ao escrever, uma raiz que não é objeto ganha um `<root>`; e uma chave que não é nome válido de
  elemento é um erro de destino, não um nome inventado.

### Conversões com perda

Converter **sempre** e avisar, em vez de recusar: o que o destino não representa (o `null` no TOML, a
data do TOML fora dele, o comentário, o inteiro maior que o `number` do JavaScript, o conteúdo misto
do XML) vira um aviso visível, agrupado por código com contagem. O motor devolve códigos; a frase é
i18n. Nenhuma perda é silenciosa e nenhuma conversão é aplicada "pela metade" sem dizer o que ficou de
fora.

### Diff

- **Texto**: Myers (1986) sobre linhas, com corte de prefixo/sufixo iguais antes da conta. O resultado
  são hunks com linha de origem e de destino; um par removido+adicionado na mesma região vira "alterado"
  na tela. O orçamento é `MAX_EDITS = 2000` (~32 MB de pico de `Int32Array` no pior caso): acima
  disso, o diff vira por posição e o resultado sai marcado `truncated`, com aviso na tela e no relatório.
- **JSON**: comparação semântica. Cada documento é achatado em folhas com caminho (`$.a`, `$.a[0]`,
  `$["chave estranha"]`); objeto e array vazios são folhas (`{}`/`[]`), para esvaziar um vasilhame não
  desaparecer. A ordem das chaves não é diferença. Arrays são comparados **por índice** — sem
  heurística de LCS de itens, que prometeria "movido" onde o dado não tem identidade.
- **Relatório**: o mesmo formato para copiar e baixar — cabeçalho com modo e lados, resumo com
  contagens, hunks no dialeto `@@ -a +b @@` no modo texto e um caminho por linha no modo JSON.

### Infra compartilhada (adições compatíveis)

- `ToolShell` ganhou `paneLabels?: { input?: string; output?: string }`; sem a prop, os 15 tools
  anteriores seguem com Entrada/Saída — inclusive as abas do mobile.
- `CodeEditor` ganhou `lineHighlights?: LineHighlight[]` (linha 0-based; tons `added`, `removed`,
  `changed`) por `StateField` + `StateEffect`, e expõe a `EditorView` para o diff rolar os dois painéis
  em sincronia e navegar entre diferenças. Sem a prop, o comportamento antigo é idêntico.

## Consequências

- Dezesseis conversões e dois modos de diff sem servidor: nada do que se cola sai do navegador, e não
  há cota, retenção ou log de conteúdo.
- O limite de entrada continua sendo o do shell (`maxInputBytes`); acima de 128 KiB o trabalho vai
  para o worker, com a queda para a thread principal quando o navegador não tem `Worker`.
- Dois pacotes novos em `dependencies`, ambos lazy no bundle; a ADR registra o peso para a próxima
  revisão de dependências.
- O diagnóstico de JSON continua sendo o do `json-viewer`; o conversor e o modo JSON do diff reusam
  `analyzeJson` e não reimplementam linha/coluna.
- Limitação registrada: `JSON.parse` reordena chaves que parecem inteiro. O conversor herda a ordem
  normalizada do motor, e o diff não é afetado porque compara por caminho, não por posição de chave.
- O diff grosseiro é honesto mas não é mínimo: a tela e o relatório dizem isso (`truncated`), e o
  aceite registra o caso patológico que o exercita.
- Alterar a convenção do XML, a regra de perda, o dialeto de caminho do JSON ou o orçamento do Myers
  exige emenda a esta ADR.

## Alternativas descartadas

- **`js-yaml`**: mais antiga e sem os tipos do YAML 1.2 que a `yaml` cobre; o parse com posição e os
  tipos de data seriam trabalho extra.
- **`@iarna/toml`**: sem manutenção ativa comparável; `smol-toml` é menor, tipada e cobre o TOML 1.0.
- **Parsers próprios de YAML/TOML**: YAML é grande demais para reimplementar com segurança, e um
  parser parcial erraria em silêncio — o oposto do que a tool promete.
- **Hub em JSON** (tudo passa por JSON): perderia o tipo data do TOML e faria a perda virar do
  formato, não da conversão; e JSON não representa o que o XML representa.
- **`@codemirror/merge`**: traria merge de três vias e o vocabulário do editor para um diff que a
  gente quer em forma de dados (hunks, caminhos, relatório); o diff próprio é pequeno e testável.
- **Diff semântico de array por LCS de itens**: prometeria "movido" para itens sem identidade, o que
  engana mais do que o índice honesto.
- **Recusar conversões com perda**: obrigaria a pessoa a descobrir sozinha qual campo sumiu; converter
  e listar o que ficou de fora é mais útil e igualmente explícito.
