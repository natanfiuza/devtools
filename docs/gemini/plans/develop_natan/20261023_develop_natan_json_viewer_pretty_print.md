# Plano de Implementação — Pretty-printing no JSON Colado ou Importado no JSON Viewer

**Branch:** `develop_natan`  
**Data:** 2026-10-23  
**Arquivo do Plano:** `docs/gemini/plans/develop_natan/20261023_develop_natan_json_viewer_pretty_print.md`  
**Documento de Fatos Survey:** `docs/survey/20261023_json_viewer_pretty_print_paste_import_surveyfacts.md`

---

## 1. Objetivo
Implementar a funcionalidade de **Pretty-printing / Beautifier automático** para o JSON Viewer (`resources/js/tools/json-viewer/Tool.vue`):
- Ao **importar um arquivo** (`.json` ou `.txt` válido), formatar o conteúdo antes de injetá-lo na caixa de texto.
- Ao **colar conteúdo** no editor de texto, se o texto resultante for um JSON sintaticamente válido, aplicar automaticamente o pretty-print (indentação padronizada de 2 espaços).
- Se o conteúdo colado ou importado for um JSON inválido, manter o conteúdo original intacto para permitir visualização de erros e correção na linha/coluna exatas.
- Digitação manual continua livre de formatação automática para não interferir na posição do cursor.

---

## 2. Arquitetura e Componentes Envolvidos

1. **`resources/js/tools/json-viewer/Tool.vue`**:
   - No método `onFile(file: File)`:
     - Ao ler o conteúdo com sucesso, verificar via `analyzeJson(result.text)` se é válido.
     - Se `ok: true`, obter o texto formatado via `prettyJson(parsed.value)` e passar para `applyText`.
     - Se inválido, passar o texto original para `applyText` mantendo a indicação de erro.
   - No tratamento de colagem (paste):
     - Receber evento `@paste` emitido pelo editor (ou interceptação configurada).
     - Quando o conteúdo colado (ou o documento resultante da colagem) for um JSON válido, formatar o documento automaticamente via `applyText(prettyJson(parsed.value))` ou via método `rewrite({ output: 'pretty' })`.
2. **`resources/js/components/tool/CodeEditor.vue` e `resources/js/lib/editor/createEditor.ts`**:
   - Permitir opcionalmente a detecção de paste via `domEventHandlers({ paste: ... })` ou repasse do evento de paste para o componente pai.
3. **`resources/js/tools/json-viewer/lib/transform.ts`**:
   - Reutilizar `prettyJson(value)` (2 espaços).
4. **Testes Unitários (`resources/js/__tests__/json-viewer.test.ts`)**:
   - Testar que a importação de arquivo com JSON válido insere o texto formatado no editor.
   - Testar que a importação de arquivo com JSON inválido preserva o texto original e acusa erro.
   - Testar que a colagem de JSON válido aplica auto-formatação.

---

## 3. Etapas de Execução

### Fase 1: Suporte a Auto-formatação na Importação de Arquivo
- Atualizar `onFile` em `Tool.vue` para tentar o parse local via `analyzeJson`.
- Se válido, formatar com `prettyJson`.
- Se inválido, manter texto original.

### Fase 2: Suporte a Auto-formatação ao Colar
- Configurar evento de colagem (`paste`) no `CodeEditor.vue` / `createEditor.ts` repassando o evento ou o texto colado.
- Em `Tool.vue`, no listener de `@paste`, se o texto for JSON válido, aplicar o pretty-print sem perda de histórico.

### Fase 3: Validação de Testes e Tipagem
- Atualizar e adicionar testes unitários em `resources/js/__tests__/json-viewer.test.ts`.
- Rodar `npm run test` para assegurar 100% de testes passando.
- Rodar `npm run types:check` para garantir conformidade estrita do TypeScript.
- Rodar `composer test` para conformidade do backend/estilo.

### Fase 4: Documentação e Relatório Final
- Gerar relatório detalhado em `docs/gemini/reports/develop_natan/20261023_develop_natan_json_viewer_pretty_print.md`.
