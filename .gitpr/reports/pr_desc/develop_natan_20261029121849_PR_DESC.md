# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona gráfico de consumo de tokens de API com IA
```

---

## 🎯 Resumo

Os usuários não tinham visibilidade de quanto consumiam dos tokens de IA por token de API, o que dificultava identificar tokens dispendiosos, justificar limites de uso e diagnosticar picos de gasto. Esta mudança entrega um gráfico de consumo diário na tela de configurações de tokens de API, com filtros por período (mês atual, mês anterior e intervalo customizado) e visão alternável entre total diário e detalhamento empilhado por token. Tokens revogados são agrupados em uma série própria, preservando a integridade dos totais históricos mesmo após a revogação.

## 🛠️ Mudanças Técnicas

- Cria `App\Support\Ai\ApiTokenUsageMetrics`, serviço de agregação que consulta `ai_requests` agrupando por dia e `api_token_id`, com fallback para `reserved_tokens` quando não há tokens medidos.
- Adiciona suporte a três períodos (`current_month`, `previous_month`, `custom`) com limite máximo de 90 dias para intervalos customizados, inversão automática de datas e fallback silencioso para mês atual em entradas inválidas.
- Normaliza tokens removidos/inexistentes em uma série agregada `revoked`, ordenada ao final da legenda.
- Expõe `usageStats` via `ApiTokenController@index` aceitando os parâmetros `period`, `start_date` e `end_date` na query string.
- Cria o componente `ApiTokenUsageChart.vue` com gráfico SVG acessível (eixo Y arredondado, labels adaptativos para evitar sobreposição), tooltip interativo por dia, alternância de visão e controles de data customizada que emitem `periodChange`.
- Integra o gráfico em `ApiTokens.vue` realizando recarregamento parcial via `router.get` com `only: ['usageStats']`, evitando re-render completo da página.
- Adiciona tipos `ApiTokenUsageStats`, `ApiTokenDailyUsage`, `ApiTokenUsageBreakdown`, `ApiTokenUsagePeriod` e `ApiTokenUsageTokenItem`.
- Traduz as strings do gráfico em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Adiciona testes unitários do serviço, teste de feature do endpoint (períodos e resumo) e testes de componente (renderização, alternância de visão, período customizado e estado vazio).

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migration criada. A funcionalidade depende da tabela `ai_requests` já existente e das colunas `api_token_id`, `tokens_in`, `tokens_out`, `reserved_tokens` e `created_at`. Índices em `(user_id, created_at)` e `api_token_id` são recomendados para volumes altos.
- **Ambiente:** nenhuma variável de ambiente nova.
- **Dependências:** nenhuma dependência adicionada; reutiliza `@lucide/vue`, `vue-i18n` e componentes de UI existentes.
- **API/Interface:** `GET /settings/api-tokens` passa a aceitar `period`, `start_date` e `end_date` e a retornar a prop `usageStats`; consumidores que validam o payload do Inertia de forma estrita precisam considerar a nova prop.
- **Performance:** a consulta é agregada no banco, mas o intervalo customizado pode varrer até 90 dias de registros por usuário.
- **UX:** o endpoint continua acessível apenas ao usuário autenticado; a consulta é sempre filtrada por `user_id`, mantendo o isolamento dos dados entre contas.

close #111

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_4_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)