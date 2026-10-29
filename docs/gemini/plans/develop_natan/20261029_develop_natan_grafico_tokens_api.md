# Plano de Implementação — Gráfico de Estatísticas de Tokens de API

- **Data:** 2026-10-29
- **Branch:** `develop_natan`
- **Tarefa:** `grafico_tokens_api`
- **Documento de Survey:** `docs/survey/20261029_grafico_tokens_api_surveyfacts.md`

---

## 1. Descrição do Objetivo

Na tela de gerenciamento de **Tokens de API** (`/settings/api-tokens`), adicionar uma seção analítica com **gráfico de barras interativo** logo abaixo dos cards de contadores gerais (`api-tokens-stats`).

O gráfico permitirá acompanhar a quantidade diária de tokens de IA consumidos (`tokens_in + tokens_out`) e a quantidade de requisições realizadas por meio dos tokens de API do usuário autenticado.

### Principais Funcionalidades:
1. **Duas Visões com Alternância por Tabs/Toggle:**
   - **Total diário:** barra única por dia com o volume total de tokens de IA consumidos na conta.
   - **Por token de API:** barras empilhadas (stacked bars) decompondo o consumo de cada dia entre os tokens utilizados, com legenda de cores por token e agrupamento para tokens revogados.
2. **Seletor de Período:**
   - Inicia por padrão com o **Mês atual**.
   - Opção para selecionar **Mês anterior**.
   - Opção **Personalizado** com inputs de data inicial e data final (com teto máximo de 90 dias, respeitando a política de retenção do sistema).
3. **Interação com Tooltip Flutuante Detalhado:**
   - Ao passar o mouse (desktop) ou tocar (mobile), exibe data formatada, total de tokens IA consumidos no dia, quantidade de requisições e a fatia discriminada por token de API.
4. **Arquitetura 100% Nativa e Leve:**
   - Componente Vue SVG nativo, estilizado com Tailwind CSS 4 e tokens do shadcn-vue, sem adicionar bibliotecas pesadas de terceiros (zero impacto no SSR).
   - Recarga de dados rápida com Inertia Partial Reload (`only: ['usageStats']`).
   - Internacionalização completa em 5 idiomas (`pt_BR`, `en`, `pt_PT`, `fr`, `es`).

---

## 2. Decisões de Arquitetura e Modelagem

- **Serviço de Agregação (`App\Support\Ai\ApiTokenUsageMetrics`):**
  Isola a lógica de agregação SQL em `ai_requests` (`user_id`, `created_at`, `api_token_id`), o mapeamento com `personal_access_tokens`, a identificação de tokens revogados e o preenchimento de dias vazios com zero para continuidade da série temporal.
- **Inertia Partial Reload:**
  O controlador `ApiTokenController::index` recebe os parâmetros de período e injeta `usageStats`. Ao alterar o período na interface, o Inertia dispara `router.get` solicitando apenas a prop `usageStats`.
- **Paleta de Cores e Acessibilidade:**
  Paleta determinística de cores contrastantes baseada em CSS variables do tema (dark/light mode), com suporte a foco por teclado e labels acessíveis (ARIA).

---

## 3. Alterações Propostas

### 3.1 Backend (Laravel)

#### [NOVO] `app/Support/Ai/ApiTokenUsageMetrics.php`
- Classe dedicada à extração e formatação das métricas de uso de tokens de API.
- Métodos:
  - `forUser(User $user, string $period, ?string $startDate, ?string $endDate): array`
  - Normalização do intervalo de datas (mês atual, mês anterior ou custom limitado a 90 dias).
  - Consulta agregada:
    ```sql
    SELECT
        DATE(created_at) as usage_date,
        api_token_id,
        COUNT(*) as requests_count,
        SUM(COALESCE(tokens_in, 0) + COALESCE(tokens_out, 0)) as total_tokens
    FROM ai_requests
    WHERE user_id = :userId
      AND api_token_id IS NOT NULL
      AND created_at BETWEEN :start AND :end
    GROUP BY DATE(created_at), api_token_id
    ORDER BY usage_date ASC
    ```
  - Preenchimento de todos os dias do período (dias sem consulta recebem `totalTokens: 0, requestsCount: 0`).
  - Associação de `api_token_id` aos tokens ativos do usuário, marcando como `[Revogado]` os tokens excluídos.

#### [MODIFICAR] `app/Http/Controllers/Settings/ApiTokenController.php`
- Injetar o resultado de `ApiTokenUsageMetrics::forUser` na resposta do Inertia como prop `usageStats`.

---

### 3.2 Frontend (Vue 3 + TypeScript)

#### [NOVO] `resources/js/components/settings/ApiTokenUsageChart.vue`
- Componente do card analítico completo contendo:
  - Header com título, subtítulo descritivo e controles (Select de período, inputs customizados se selecionado, e Tabs/Toggle "Total" / "Por token").
  - Container SVG responsivo calculando eixos X e Y dinamicamente.
  - Barras com transições suaves e rects empilhados.
  - Tooltip flutuante com cálculo de posicionamento relativo.
  - Legenda dos tokens ativos/revogados presentes no período.
  - Estado vazio estilizado quando não houver consumo no período.

#### [MODIFICAR] `resources/js/pages/settings/ApiTokens.vue`
- Importar e renderizar `<ApiTokenUsageChart>` logo abaixo de `<!-- Contadores acumulados da API -->` (`api-tokens-stats`).
- Tratar o evento de troca de período disparando `router.get` com `only: ['usageStats']`, `preserveState: true` e `preserveScroll: true`.

#### [MODIFICAR] `resources/js/types/index.d.ts` (ou criar arquivo específico)
- Tipagem TypeScript para `ApiTokenUsageStats`, `ApiTokenDailyUsage`, `ApiTokenBreakdownItem`, `ApiTokenInfo`.

---

### 3.3 Internacionalização (i18n)

#### [MODIFICAR] `lang/{pt_BR,en,pt_PT,fr,es}/settings.php`
- Adicionar chaves sob `api_tokens.usage_chart`:
  - `title`, `description`
  - `views`: `total`, `by_token`
  - `periods`: `current_month`, `previous_month`, `custom`
  - `custom`: `start_date`, `end_date`, `apply`, `invalid_range`, `max_range_hint`
  - `tooltip`: `date`, `total_tokens`, `requests`, `share`
  - `legend`: `revoked_tokens`
  - `empty`: `title`, `description`

---

## 4. Plano de Verificação

### 4.1 Testes Automatizados
- **Testes Backend (Pest):**
  - `tests/Unit/Support/Ai/ApiTokenUsageMetricsTest.php`:
    - Verifica cálculo correto de `tokens_in + tokens_out`.
    - Verifica preenchimento contínuo de dias sem consultas.
    - Verifica rotulação correta de tokens revogados.
    - Verifica teto de 90 dias no intervalo customizado.
  - `tests/Feature/Settings/ApiTokenTest.php`:
    - Verifica se a prop `usageStats` é entregue no Inertia.
    - Verifica navegação com parâmetros `period=previous_month` e `period=custom`.
  - Execução: `vendor/bin/pest tests/Feature/Settings/ApiTokenTest.php tests/Unit/Support/Ai/ApiTokenUsageMetricsTest.php`
- **Testes Frontend (Vitest):**
  - `resources/js/__tests__/api-token-usage-chart.test.ts`:
    - Testa renderização do card e SVG.
    - Testa alternância das abas "Total" e "Por token".
    - Testa abertura dos inputs no período customizado e disparo de evento.
    - Testa exibição do tooltip com as métricas.
  - Execução: `npm run test`
- **Verificação de Tipos e Estilo:**
  - `npm run types:check`
  - `npm run lint:check`
  - `composer test`

### 4.2 Verificação Manual
1. Acessar `/settings/api-tokens` no navegador.
2. Confirmar que o gráfico carrega logo abaixo dos cards com o mês atual pré-selecionado.
3. Passar o mouse nas barras e inspecionar o tooltip (total de tokens, requisições e tokens discriminados).
4. Alternar entre as abas "Total diário" e "Por token de API", observando a legenda e as cores empilhadas.
5. Selecionar "Mês anterior" e verificar a recarga rápida do gráfico.
6. Selecionar "Personalizado", definir intervalo de datas e clicar em "Aplicar".
7. Verificar temas dark e light (modo escuro/claro).
