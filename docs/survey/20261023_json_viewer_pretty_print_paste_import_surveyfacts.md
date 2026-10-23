# Survey — Pretty-printing no JSON Colado ou Importado no JSON Viewer (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Em JSON Viewer implementar um "Pretty-printing / Beautifier" no código que foi colado ou importado na caixa de texto de entrada da ferramenta.
- **Tela / Modulo:** `resources/js/tools/json-viewer/Tool.vue`, `resources/js/components/tool/CodeEditor.vue`, `resources/js/lib/editor/createEditor.ts`, `resources/js/tools/json-viewer/lib/transform.ts`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de refinamento cobrindo gatilhos de auto-formatação, tratamento de JSON inválido e padrão de feedback de UI.
- **Arquivos-chave lidos:**
  - `resources/js/tools/json-viewer/Tool.vue`
  - `resources/js/tools/json-viewer/lib/transform.ts`
  - `resources/js/tools/json-viewer/lib/parseClient.ts`
  - `resources/js/lib/editor/createEditor.ts`
  - `resources/js/components/tool/CodeEditor.vue`
  - `resources/js/lib/json/parse.ts`
  - `resources/js/__tests__/json-viewer.test.ts`
  - `lang/pt_BR/tools.php`

## 2. Decisoes das rodadas

### Rodada 1 — Gatilho de auto-formatação, tratamento de erro e feedback visual
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Gatilho de auto-formatação (Auto pretty-print) | Apenas em importação de arquivo (`onFile`) e colagem direta (`paste`). Digitação manual não sofre formatação automática para não interferir com o cursor e a fluidez do usuário. |
| Q2 | Tratamento de JSON inválido ou malformado | Se o JSON colado ou importado for inválido, o texto original é preservado sem alterações e a marcação de erro (`editorError`) é exibida com linha e coluna exatas. |
| Q3 | Feedback visual de formatação | Silencioso e instantâneo no editor de entrada, sem toasts ou banners adicionais, refletindo imediatamente no CodeMirror e na árvore. |

### Defaults assumidos (nao contestados)
- Utilização de `prettyJson` / formatação com 2 espaços (`JSON_INDENT = 2`), já padronizada no JSON Viewer.
- No arquivo importado (`onFile`), o texto lido passa por tentativa de análise imediata via `analyzeJson`. Se for JSON válido, aplica o formato pretty no editor via `applyText(prettyJson(parseResult.value))`.
- No evento de colagem (`paste`) no editor de código, interceptar o conteúdo da área de transferência ou emitir evento `@paste` do CodeEditor. Se o texto colado for um JSON válido, insere ou substitui pelo JSON indentado/formatado de forma fluida.
- Suíte de testes unitários em `resources/js/__tests__/json-viewer.test.ts` atualizada para cobrir auto-formatação ao importar arquivo e ao colar.

## 3. Relatorio de fatos levantados
### Fato 1: Fluxo de Importação Atual
Em `Tool.vue`, a função `onFile` lê o arquivo através de `readTextFile` e invoca diretamente `applyText(result.text)`. `applyText` define `intake = 'now'` e dispara a análise assíncrona. Ao verificar `analyzeJson(result.text)` antes de aplicar o texto, se for válido (`ok: true`), pode-se chamar diretamente `prettyJson(parsed.value)`, garantindo que o texto já nasça formatado na caixa de entrada sem alterar o fluxo quando o JSON for inválido.

### Fato 2: Interceptação de Colagem no CodeMirror
O `CodeEditor.vue` envolve uma instância do CodeMirror 6 criada por `createEditor.ts`. O CodeMirror trata `paste` internamente nas suas transações de input. Para suportar auto-formatação ao colar sem quebrar desfazer (`Ctrl+Z`), podemos:
1. Adicionar uma extensão DOM event handler (`EditorView.domEventHandlers({ paste: ... })`) ou tratar o evento de paste no CodeEditor repassando para o componente pai / emitindo `@paste:text` ou formatando quando todo o documento ou texto colado for um JSON completo.
2. Em um editor voltado a JSON, quando o usuário cola um JSON (ou quando o documento resultante pós-colagem é um JSON válido), aplicar o pretty-print preserva o histórico de undo do editor.
