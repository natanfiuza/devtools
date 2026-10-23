# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: formata JSON ao colar e abrir arquivo no viewer
```

---

## 🎯 Resumo
Este PR melhora a experiência do JSON Viewer ao formatar automaticamente JSON válido importado por colagem ou abertura de arquivo, reduzindo passos manuais e mantendo feedback claro para entradas inválidas. Também padroniza a formatação de componentes Vue e atualiza artefatos de documentação, sem alterar comportamento funcional fora do JSON Viewer.

## 🛠️ Mudanças Técnicas
- Adiciona suporte ao evento `paste` no `CodeEditor.vue`, com emissão condicionada à existência de listeners e integração ao CodeMirror via `onPaste`.
- Implementa `onPaste` no `Tool.vue` do JSON Viewer: valida o texto colado, aplica `preventDefault` quando for JSON válido, formata com `prettyJson` e substitui o conteúdo quando o editor está vazio ou insere na posição do cursor quando já há conteúdo.
- Ao abrir arquivo, valida o conteúdo com `analyzeJson` e aplica a versão formatada no editor quando válido, mantendo o texto bruto e o erro de parse quando inválido.
- Atualiza testes do `json-viewer` para cobrir auto-formatação de JSON colado, não interceptação de texto não-JSON, formatação ao abrir/selecionar arquivo e preservação de conteúdo original com erro de parse.
- Reformata componentes Vue de admin, auth, dashboard, newsletter, settings e ferramentas, ajustando indentação de 4 para 2 espaços, quebra de atributos e posicionamento de tags conforme o formatador, sem mudança de lógica.
- Atualiza documentação e artefatos de survey, plano e relatórios relacionados à funcionalidade.

## ⚠️ Impacto/Avisos
- Sem alterações em banco de dados, variáveis de ambiente ou dependências.
- O novo comportamento funcional fica restrito ao JSON Viewer: colagens de JSON válido são reformatadas automaticamente; entradas inválidas seguem o fluxo normal ou mantêm o erro de parse.
- A reformatação dos componentes Vue é cosmética e não altera props, rotas, chamadas Inertia, i18n, validações ou contratos de API.


close #66

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)