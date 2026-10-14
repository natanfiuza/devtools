# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: expõe limiar de virtualização e mantém árvore defasada
```

---

## 🎯 Resumo

Tira da tela duas decisões que não são dela e melhora a experiência de quem está corrigindo um JSON inválido:

- O limiar a partir do qual a árvore passa a renderizar só a janela visível deixa de ser a constante `VIRTUALIZE_FROM = 2000` no código do cliente e passa a ser configuração (`tools.limits.virtualize_from`, via `TOOLS_VIRTUALIZE_FROM`), entregue pelo servidor junto do teto de entrada. Limite não mora no código (CLAUDE.md) — e agora dá para ajustá-lo por ambiente sem recompilar assets.
- Ao quebrar o documento, a árvore da última versão válida **permanece na tela**, marcada como defasada (aviso + opacidade reduzida) e com o erro de linha/coluna visível no rodapé. Quem está corrigindo a sintaxe não perde a referência do que o documento era (SPEC seção 5). Documento vazio ou sem nenhuma versão válida anterior limpa a árvore.

Além disso, os atalhos anunciados passam a corresponder ao que realmente funciona: `Cmd/Ctrl+Enter` formata, `Cmd/Ctrl+F` busca e `Esc` limpa a busca (antes a faixa anunciava `Ctrl+Z`/`Ctrl+V`, que não eram registrados pela tela).

## 🛠️ Mudanças Técnicas

- **Config/Server**: novo `tools.limits.virtualize_from` em `config/tools.php` e `TOOLS_VIRTUALIZE_FROM` no `.env.example` (padrão 2000); `ToolController` passa `virtualizeFrom` no payload Inertia, ao lado de `maxInputBytes`.
- **Propagação da prop**: `Show.vue` → componente da tool → `json-viewer/Tool.vue` → `JsonTree.vue`, que decide `virtual` comparando o total de linhas com `props.virtualizeFrom`. `lib/window.ts` fica apenas com geometria (altura de linha e overscan).
- **Árvore defasada**: novo `shallowRef lastValid` em `Tool.vue` guardando o último `AnalyzeSuccess`; `stale` é derivado (sem sucesso atual, texto não vazio, existe última válida). `nodes` cai para `lastValid.nodes` quando defasado; o rodapé prioriza erro de arquivo → feedback → erro que gerou a defasagem → estatísticas. `resetDocument()` zera `lastValid`.
- **Atalhos do editor**: `createEditor` monta uma lista `command` de `KeyBinding` da tela posicionada **antes** de `closeBracketsKeymap`/`defaultKeymap`/`historyKeymap` (a primeira tecla que atender fica com o evento), o que faz `Mod-Enter` formatar em vez de inserir linha em branco. `JsonEditor.vue` emite `format`, ligado à ação de formatação em `Tool.vue`.
- **Esc na busca**: listener de `keydown` na `window` (registrado/removido no ciclo de vida do componente) limpa `query` apenas quando há busca ativa; o `kbd` "Esc" aparece colado ao campo.
- **i18n**: adicionada a chave `tools.ui.states.stale_tree` e substituídos os rótulos `undo`/`paste` por `format`/`clear` em `pt_BR`, `pt_PT`, `en`, `es` e `fr`.
- **Testes**: novo `json-editor-keys.test.ts` monta o CodeMirror real (com ajustes de `Range.getClientRects` para o jsdom) e cobre a fila de teclas; `json-viewer.test.ts` ganha casos de virtualização pelo limiar da config, árvore defasada, Esc limpando a busca e formatação via evento do editor; `tool-page.test.ts` e o feature test PHP passam a validar as duas props de limite; `json-window.test.ts` deixa de cobrar o limiar (agora é config).
- **Documentação**: CHANGELOG, SPEC, DESIGN, glossário, aceitação da Fase 2 e relatórios de verificação atualizados.

## ⚠️ Impacto/Avisos

- **Env nova**: `TOOLS_VIRTUALIZE_FROM` (inteiro, padrão 2000). Sem ela nada quebra, mas instalações que queiram outro limiar precisam ajustar o `.env` e limpar config cache.
- **Mudança de comportamento visível**: com documento inválido a árvore antiga continua renderizada (antes a área de saída mostrava apenas o estado de erro). É intencional e coberto por teste.
- **Atalhos exibidos mudaram**: `Ctrl+Z`/`Ctrl+V` saem da faixa de atalhos (seguem funcionando pelo keymap do CodeMirror, mas não são mais anunciados); entram `Cmd/Ctrl+Enter` e `Esc`.
- **Sem migrações de banco, sem novas dependências** (nenhuma adição em `package.json`/`composer.json`).
- Atenção ao registrar novos `KeyBinding` da tela: a ordem da lista importa — atalhos do produto devem vir antes dos keymaps do motor para não serem engolidos.


close #10

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)