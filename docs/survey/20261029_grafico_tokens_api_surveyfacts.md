# Survey — Gráfico de Estatísticas de Tokens de API (2026-10-29)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** "No Painel de usuários na tela Tokens de API adicione um grafico abaixo do cards, para que seja possivel ver as estatistica de acesso por token, exibindo cada consulta com data e quantidade de token e um grafico por dia onde mostra a quantidade de tokens ia consumidos total e por token de api em um grafico de barras. O grafico vai exibir uma visao com o total de tokens api utilizados por dia e outra visao por dia informando deste total qual a quantidade de tokens ia utilizados por token de api, o grafico deve abrir com o mes corrente e um select para selecionar mes anterior e custom para definir um periodo de datas"
- **Tela / Módulo:** Painel de Configurações do Usuário > Tokens de API (`/settings/api-tokens`), arquivos `resources/js/pages/settings/ApiTokens.vue` e `app/Http/Controllers/Settings/ApiTokenController.php`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Método:** 2 rodadas de perguntas e respostas técnicas, esgotamento da fronteira de decisões, inspeção estrita de código e schemas do banco.
- **Arquivos-chave lidos:**
  - `resources/js/pages/settings/ApiTokens.vue`
  - `app/Http/Controllers/Settings/ApiTokenController.php`
  - `app/Models/AiRequest.php`
  - `database/migrations/2026_10_22_000003_add_api_token_id_to_ai_requests_table.php`
  - `database/migrations/2026_10_25_000001_add_api_request_counters_to_tokens_and_users.php`
  - `app/Http/Controllers/Account/HistoryController.php`
  - `config/ai.php`
  - `lang/pt_BR/settings.php` e arquivos de idiomas em `lang/{locale}/settings.php`

## 2. Decisões das rodadas

### Rodada 1 — Visualização, Dados e Experiência do Usuário
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Detalhamento das consultas individuais | **Apenas o gráfico** (sem tabela separada de consultas individuais). A visualização do consumo diário e das métricas de acesso é concentrada inteiramente no componente do gráfico. |
| Q2 | Alternância entre as visões do gráfico | **Tabs / Toggle integrado** no cabeçalho do card do gráfico (`[Total diário \| Por token de API]`). Na visão "Por token", o gráfico exibe barras empilhadas (stacked bars) com legenda de cores por token. |
| Q3 | Abordagem técnica para o Gráfico | **Componente Vue SVG nativo** reativo, desenvolvido com Tailwind 4 e design tokens do shadcn-vue. Zero dependências externas, leve, 100% compatível com SSR e temas dark/light. |
| Q4 | Estratégia de carregamento do período | **Nativo do Inertia** via `router.get` com `preserveState: true`, `preserveScroll: true` e Partial Reload (`only: ['dailyUsage', 'period']`). |

### Rodada 2 — Interação, Filtros e Casos de Borda
| # | Decisão | Resolução |
|---|---|---|
| Q5 | Interação com a barra do dia e Tooltip detalhado | **Tooltip flutuante detalhado** com data, total de tokens IA do dia, quantidade de requisições e lista de consumo por token de API. No mobile, toque fixa o tooltip na barra. |
| Q6 | Tratamento de tokens de API revogados | **Agrupamento sob "Tokens revogados"** com cor neutra nas barras empilhadas, garantindo que o total diário bata exatamente com o consumo real da conta. |
| Q7 | Filtro de período e Modo Personalizado | **Mês atual (default), Mês anterior e Personalizado** (dois inputs `type="date"` com botão "Aplicar"), limitando o período personalizado a no máximo 90 dias (tempo padrão de retenção de histórico do sistema). |

### Defaults assumidos (não contestados)
- As strings visíveis serão adicionadas em todos os 5 idiomas suportados (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) em `lang/{locale}/settings.php`.
- O consumo de tokens de IA é apurado a partir de `ai_requests` do usuário autenticado onde `api_token_id IS NOT NULL`, somando `COALESCE(tokens_in, 0) + COALESCE(tokens_out, 0)` (com fallback para `reserved_tokens` caso ambos sejam 0).
- Os dias sem requisições dentro do período selecionado serão preenchidos com valor 0 na série temporal para manter o eixo temporal contínuo e consistente.

## 3. Relatório de fatos levantados

### Estrutura dos Dados e Tabela `ai_requests`
- `ai_requests` contém as colunas `user_id`, `api_token_id`, `tokens_in`, `tokens_out`, `reserved_tokens`, `status` e `created_at`.
- Na tabela `personal_access_tokens`, quando um token é revogado, o registro é removido fisicamente pelo método `delete()`.
- O agrupamento de dados no backend deve usar agregação SQL agrupada por `DATE(created_at)` e `api_token_id`, associando com `personal_access_tokens` via `leftJoin` para obter os nomes dos tokens ativos e rotular os ausentes como revogados.

### Performance e Agregação
- Como a consulta é filtrada por `user_id` e intervalo de `created_at`, o volume de dados agregado por dia (máximo 90 dias) é pequeno (< algumas dezenas de linhas agregadas), o que torna a resposta via Inertia Partial Reload praticamente instantânea (< 50ms).
- O SVG nativo renderizado diretamente pelo Vue aproveita a reatividade sem sobrecarregar o DOM, calculando alturas e eixos com facilidade matemática em `computed`.
