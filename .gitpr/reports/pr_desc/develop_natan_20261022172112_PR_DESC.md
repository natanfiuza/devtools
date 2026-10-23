# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona explicação didática e dicas por alvo no AI SQL Builder
```

---

## 🎯 Resumo

O resultado gerado pelo AI SQL Builder entregava apenas o código SQL e metadados superficiais (premissas, avisos e tabelas). Faltava contexto sobre *como* a consulta foi construída, o que obrigava o desenvolvedor a interpretar o SQL manualmente — especialmente em joins, filtros e agrupamentos não triviais.

Esta mudança faz o modelo retornar uma **explicação didática** da consulta e exibe, na interface, uma **dica de boas práticas específica do alvo** selecionado (SQL puro, Eloquent, Laravel Query Builder, SQLModel, SQLAlchemy, Django ORM, Prisma, Drizzle, TypeORM). O objetivo é acelerar o entendimento e reduzir erros comuns de performance no uso diário da ferramenta.

## 🛠️ Mudanças Técnicas

- **Contrato de saída**: `AiResult` passa a expor o campo opcional `explanation` (`string|null`), incluído em `toArray()`.
- **Validação**: `ContractValidator::explanation()` aceita apenas string, aplica `trim`, converte vazio em `null` e rejeita valores acima de 4000 caracteres (`explanation_not_string` / `explanation_too_long`).
- **Prompt**: `PromptBuilder` instrui o modelo a gerar uma explicação concisa (1–2 parágrafos curtos) destacando joins, filtros, agrupamentos e decisões de design, no mesmo idioma da requisição; em `needs_clarification = true`, a explicação deve vir vazia.
- **Internacionalização**: novas chaves `ai.result.explanation`, `ai.result.tip` e seção `ai.tips.*` com uma dica por alvo em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **UI (`ResultPanel.vue`)**: bloco de metadados migrado de `<dl>` para contêiner rolável (`max-h-72 overflow-y-auto`), com nova seção de explicação e destaque visual de dica (ícone `Lightbulb`, `data-test="ai-target-tip"`). A dica é resolvida dinamicamente por `activeSlug` e ocultada quando a chave de tradução inexiste.
- **Download**: `formatDownload.ts` inclui as seções de explicação e dica no cabeçalho do arquivo exportado, respeitando conteúdo vazio/nulo.
- **Tipos e testes**: `AiQueryResult` recebe `explanation?`; adicionados testes de contrato (normalização, tipo inválido, limite de tamanho), de prompt, de `ResultPanel` e de formatação de download.

## ⚠️ Impacto/Avisos

- **Contrato de IA**: o esquema de resposta esperado pelo modelo ganhou um novo campo. Provedores ou fixtures que ainda não o retornam continuam funcionando (campo opcional), mas a explicação só aparecerá após o modelo adotar o novo formato.
- **Limite de tamanho**: explicações acima de 4000 caracteres são rejeitadas como contrato inválido — pode gerar falha visível ao usuário caso o modelo seja prolixo.
- **Banco de dados**: nenhuma migração, alteração de schema ou de dados.
- **Variáveis de ambiente**: nenhuma adição ou alteração.
- **Dependências**: nenhuma nova; apenas reutilização do pacote `@lucide/vue` para o ícone `Lightbulb`.
- **Traduções**: alvos futuros exigirão a chave correspondente em `ai.tips.*`; sem ela, a dica simplesmente não é exibida (degradação silenciosa).
- **Layout**: a área de metadados agora é rolável e substitui a estrutura de lista de definições — ajustes de CSS/aparência em telas pequenas devem ser verificados.


close #62

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)