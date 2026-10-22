# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: inclui metadados como comentários no download do AI SQL Builder
```

---

## 🎯 Resumo

Ao baixar um resultado gerado pelo AI SQL Builder, o arquivo entregue continha apenas o código, perdendo o contexto que o próprio painel exibe na tela (premissas assumidas pela IA, avisos e tabelas utilizadas). Isso obrigava o desenvolvedor a voltar à interface para reconstruir esse contexto antes de reutilizar o código. Esta mudança passa a embutir esses metadados como um cabeçalho de comentários no topo do arquivo baixado, respeitando a sintaxe de comentário do alvo selecionado.

## 🛠️ Mudanças Técnicas

- Adiciona o módulo `formatDownload.ts` com o helper `formatDownloadContent`, que monta um cabeçalho com premissas, avisos e tabelas usadas a partir do resultado.
- Aplica comentários estilo Python (`#`) para alvos `sqlmodel`, `sqlalchemy` e `django_orm`.
- Aplica blocos estilo C (`/* ... */`) para os demais alvos (SQL, Eloquent, Laravel Query Builder, Drizzle, TypeORM, Prisma).
- Omite seções vazias e retorna o código original intacto quando não há metadados a incluir.
- Atualiza `ResultPanel.vue` para formatar o conteúdo antes de chamar `downloadText`, reutilizando as chaves de i18n existentes para os rótulos.
- Adiciona testes unitários para o formatador (`ai-sql-builder-download.test.ts`) cobrindo os casos de ausência de metadados, cabeçalho completo, alvo Python e seções parciais.
- Adiciona teste de integração no `ResultPanel` verificando que o download inclui os metadados e o código.
- Ajustes de formatação (quebra de linha) em templates do `ResultPanel.vue`, sem impacto funcional.

## ⚠️ Impacto/Avisos

- **Sem impacto em banco de dados, variáveis de ambiente ou dependências.**
- Mudança de comportamento perceptível ao usuário: o conteúdo do arquivo baixado agora difere do texto exibido em tela quando existem premissas, avisos ou tabelas — válido para todos os alvos suportados.
- Alvos Python usam `#`; os demais usam `/* */`. Novos alvos adicionados no futuro precisam ser classificados em `PYTHON_TARGETS` ou cairão no formato C-style por padrão.
- O helper depende das chaves de i18n `ai.result.assumptions`, `ai.result.warnings` e `ai.result.tables`, que passam a influenciar o conteúdo dos arquivos exportados.

close #56

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)