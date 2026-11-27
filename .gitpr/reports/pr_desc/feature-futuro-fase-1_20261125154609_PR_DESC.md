# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona conversor de formatos e diff com realce no editor
```

---

## 🎯 Resumo

Entrega duas novas ferramentas ao usuário final — **Data Format Converter** (JSON ↔ YAML ↔ TOML ↔ XML) e **Diff** (texto e JSON semântico) — junto com a infraestrutura de editor que ambas exigem. O objetivo é oferecer conversão e comparação previsíveis, com transparência explícita sobre o que é perdido em cada conversão (comentários, `null`, `NaN`/`Infinity`, tipos binários) e sem adivinhar formatos pela extensão do arquivo, conforme ADR-180.

## 🛠️ Mudanças Técnicas

- **Conversor de formatos**: motor puro `texto → árvore → texto` com árvore intermediária própria (`objeto como lista de pares`, preservando ordem e neutralizando `__proto__`), parsers por formato (JSON, `yaml`, `smol-toml`, `DOMParser`) com linha/coluna quando disponível, e serializadores — JSON e XML escritos à mão para não reordenar chaves nem perder `NaN`/`Infinity`.
- **Sistema de perdas agregadas por código** (`null-dropped`, `comments-dropped`, `date-as-text`, `binary-as-base64`, `non-finite-*`, `root-wrapped`, `mixed-content-flattened`, etc.): a conversão sempre ocorre e o que ficou de fora é reportado em painel próprio.
- **Tela do conversor** com `ToolShell`, debounce de 200 ms, seletores explícitos de formato de entrada/saída, manutenção da última saída válida marcada como defasada em caso de erro e delegação a Web Worker acima de 128 KiB (com fallback para a thread principal).
- **Diff por linhas** (`lib/lines.ts`): Myers com corte de prefixo/sufixo comum, orçamento `MAX_EDITS=2000`, normalização CRLF e fallback posicional sinalizado como `truncated`.
- **Diff semântico de JSON** com caminhos no dialeto JSONPath (`{}`/`[]` como folha, arrays comparados por índice) e gerador de relatório textual no formato `@@`/`-`/`+` com rótulos i18n injetados.
- **Tela do diff**: dois painéis lado a lado (`CodeEditor` + `FileDropzone`), alternância texto/JSON, execução inline abaixo de 128 KiB e via worker acima, navegação entre hunks com rolagem sincronizada proporcional, realce de linhas, cópia/download de relatório e status unificado.
- **Infraestrutura de editor**: novo `lib/editor/highlights.ts` (`StateField` + `StateEffect` para tons `added`/`removed`/`changed`), regras `.cm-diff-*` no tema via `color-mix` sobre tokens, prop `lineHighlights` no `CodeEditor` (com `watch` `deep`) e exposição de `view` via `defineExpose`.
- **`ToolShell`** ganha a prop opcional `paneLabels` (fallback Entrada/Saída), usada pelo diff para rotular Original/Modificado no desktop e nas abas do mobile.
- **i18n**: novas chaves `data-format-converter` e `diff` em `en`, `es`, `fr`, `pt_BR` e `pt_PT` (formatos, erros de TOML/XML, avisos de perda, modos, rótulos e resumo de contagens).
- **Registry e ícones**: manifestos das tools (`data-format-converter`; `diff` na categoria `text`, ordem 140), ícones `arrow-left-right` e `diff`, e atualização do registry de tools MVP.
- **Testes**: matriz de conversão, perdas, convenção XML, telas do conversor e do diff, diff por linhas (mínimo de edições validado contra LCS por força bruta), diff JSON, relatório, canais de realce do editor, rótulos da casca e `ToolsRegistryTest` com os novos slugs.

## ⚠️ Impacto/Avisos

- **Novas dependências de runtime**: `smol-toml ^1.9.1` e `yaml ^2.9.1` — base dos parsers/serializadores dos formatos suportados.
- **Bundle**: acréscimo de um chunk de worker (conversão e diff) além do código das telas; execução acima de 128 KiB sai da thread principal.
- **Sem mudanças de banco de dados ou variáveis de ambiente.**
- **Comportamento**: a conversão nunca falha por perda de informação — sempre converte e avisa; formatos de entrada/saída são escolhidos explicitamente pelo usuário (ADR-180), sem inferência por extensão.
- **Diff truncado**: entradas acima do orçamento de edições retornam resultado sinalizado como `truncated`, o que deve ser comunicado na UI e no relatório.

close #177

---

[![GitPR](https://devtools.natanfiuza.dev.br/api/badges/static/GitPR/4_errors_%C2%B7_0_warnings.svg?color=red&logo=gitpr)](https://gitpr.natanfiuza.dev.br/)