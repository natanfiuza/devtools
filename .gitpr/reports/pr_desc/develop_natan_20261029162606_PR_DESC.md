# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
refactor: aplica correções de lint e reforça tipagem do gráfico de tokens
```

---

## 🎯 Resumo

Corrige apontamentos de lint e reforça a segurança de tipos do gráfico de consumo de tokens, evitando falhas silenciosas quando o mapa de cores ou os dados de barra do tooltip não estão disponíveis. Não há mudança de comportamento esperado para o usuário final — apenas maior previsibilidade e resiliência da UI e do código de métricas.

## 🛠️ Mudanças Técnicas

- Remove import não utilizado de `Carbon` em `ApiTokenUsageMetrics` e padroniza `selectRaw` com aspas simples.
- Adiciona *fallbacks* explícitos (`TOKEN_COLORS[0]` e `#6366f1`) na resolução de cores por token em `ApiTokenUsageChart.vue`, cobrindo arrays de cores vazios.
- Amplia o tipo `StackSegment['tokenId']` para aceitar `'total'`, alinhando o tipo ao uso real nos segmentos empilhados.
- Divide a guarda do `tooltipStyle` em duas verificações, tratando separadamente `hoveredDayIndex` nulo e barra inexistente.
- Reformata a expressão de `transform` do tooltip para leitura mais clara.
- Substitui FQCN `\App\Models\AiRequest` por import em `ApiTokenSettingsTest` e remove linha em branco excedente em `ApiTokenUsageMetricsTest`.
- Inclui relatórios de linter, descrição de PR e análise de gráfico de tokens em `docs/gemini/reports` e `.gitpr/reports`.

## ⚠️ Impacto/Avisos

- Sem alterações de banco de dados, migrations, variáveis de ambiente ou dependências.
- Mudanças restritas a refatoração, tipagem e correções de estilo; comportamento funcional do gráfico e das métricas preservado.
- O novo fallback de cor garante que tokens sempre recebam uma cor válida, evitando erros de renderização em cenários de configuração incompleta.

close #113

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)