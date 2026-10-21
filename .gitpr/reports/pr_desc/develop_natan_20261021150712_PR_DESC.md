# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona histórico de consultas na área da conta
```

---

## 🎯 Resumo

A tela de Histórico da conta existia apenas como estado vazio: não havia como o usuário revisar o que já havia executado nas ferramentas de IA. Esta mudança liga a tela ao registro real de solicitações (`AiRequest`), permitindo consultar data, ferramenta, dialeto, consumo de cota, acerto de cache e status de cada execução — além de recuperar o SQL gerado e o resumo explicativo em um modal de detalhes.

Com isso, o histórico passa a ser um recurso efetivo de auditoria e reuso: o usuário acompanha o que consumiu da franquia e reaproveita consultas anteriores sem refazer chamadas de IA.

## 🛠️ Mudanças Técnicas

- `HistoryController` passa a paginar (20 por página) as `aiRequests` do usuário autenticado, ordenadas por `created_at` desc, com eager loading de `outputTarget`, mapeando cada registro para um payload de apresentação (`tool`, `dialect`, `status`, `tokensUsed`, `cacheHit`, `sql`, `summary`, `errorCode`).
- Fallback de consumo: quando `tokens_in + tokens_out` é zero, usa-se `reserved_tokens` como valor exibido.
- `StartAiQuery` grava `history_visible = true`, fazendo as solicitações aparecerem no histórico.
- `SqlDialect` ganha o método `label()` com nomes legíveis (MySQL, PostgreSQL, SQL Server, SQLite).
- `History.vue` passa a receber props tipadas, renderizar tabela com badges de status/cache, paginação via rotas Inertia (`preserve-scroll`) e modal de detalhes com cópia de SQL para a área de transferência.
- Novos tipos `AccountHistoryItem` e `AccountHistoryProps` em `resources/js/types/account.ts`.
- Traduções completas de tabela, status, modal e paginação em `en`, `es`, `fr`, `pt_BR` e `pt_PT`, com revisão do texto de estado vazio.
- Testes: feature test cobrindo listagem paginada e isolamento entre contas, ajuste do teste de `AiActionsTest` para o novo `history_visible`, e testes de componente para estado vazio, renderização de linhas e abertura do modal.

## ⚠️ Impacto/Avisos

- **Comportamento de dados:** solicitações de IA passam a ser persistidas com `history_visible = true`. Registros antigos gravados como `false` não aparecem na tela — não há migration, mas o histórico anterior à mudança permanece oculto.
- **Sem alterações de schema, env ou dependências.** Nenhuma migration ou variável de ambiente nova é necessária.
- **Performance:** a consulta usa paginação e eager loading, mas convém observar o volume de `ai_requests` por usuário e garantir índice em `(user_id, created_at)`.
- **Privacidade:** o controller filtra por `$request->user()`, coberto por teste de isolamento entre contas; qualquer alteração futura nessa consulta deve preservar esse filtro.
- **Compatibilidade de frontend:** novas chaves de tradução exigidas pela view — idiomas fora dos cinco atualizados cairão no fallback e exibirão chaves cruas.


close #54

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_2_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)