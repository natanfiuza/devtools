# Survey — Historico de Consultas e Consumo no Painel do Usuario (2026-10-20)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "A tela de Histórico do painel de usuário a listagem de historico não esta sendo exibida, corriga"
- **Tela / Modulo:** Painel do Usuario (`/dashboard/history`) — `App\Http\Controllers\Account\HistoryController`, `resources/js/pages/dashboard/History.vue`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** Inspecao direta de rotas, controllers, models (`AiRequest`, `UsageLedger`), testes e translations em 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`). 1 rodada de alinhamento com resolucao de 3 questoes essenciais.
- **Arquivos-chave lidos:**
  - `app/Http/Controllers/Account/HistoryController.php`
  - `resources/js/pages/dashboard/History.vue`
  - `resources/js/pages/dashboard/Billing.vue` (referencia de UI/tabela/paginacao)
  - `app/Models/AiRequest.php`
  - `app/Models/UsageLedger.php`
  - `app/Actions/Ai/StartAiQuery.php`
  - `tests/Feature/AccountPanelTest.php`
  - `resources/js/__tests__/account-panel.test.ts`
  - `lang/pt_BR/account.php` (e demais idiomas)

## 2. Decisoes das rodadas

### Rodada 1 — Escopo, Visibilidade e Interacao
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Escopo dos dados do historico | **Opcao C (Combinado)**: Historico de consultas IA (`AiRequest`) com dados de status, dialeto, cota e tokens/usos consumidos do livro-razao (`UsageLedger`). |
| Q2 | Criterio de visibilidade (`history_visible`) | **Opcao A (Recomendado)**: Listar as consultas do usuario logado (`user_id`), garantindo que novas solicitacoes gravem `history_visible = true` e retroativamente permitindo que o usuario visualize seu historico. |
| Q3 | Interatividade na UI | **Opcao A (Recomendado)**: Tabela paginada (20 por pagina) com badges de status, dialeto, tokens/usos, data/hora e botao/modal de detalhes para inspecao do SQL gerado e resumo da consulta. |

### Defaults assumidos (nao contestados)
- Idiomas: todas as strings adicionadas em `lang/{en,pt_BR,pt_PT,es,fr}/account.php` sem texto hardcoded.
- Sem commit/push pelo agente (regra estrita do `GEMINI.md`).
- Testes automatizados cobrindo backend (Pest em `tests/Feature/AccountPanelTest.php`) e frontend (Vitest em `resources/js/__tests__/account-panel.test.ts`).
- Uso dos componentes shadcn-vue ja presentes (`Dialog`, `Badge`, `Button`).

## 3. Relatorio de fatos levantados
### Fato 1: Estado vazio original era intencional da F0/F4, mas a F5 ja gerou dados reais
Originalmente, a tela exibia apenas o card vazio avisando que as ferramentas chegariam na fase seguinte. Como o AI SQL Builder foi implementado e entregue na F5, ha registros de `AiRequest` e `UsageLedger` gerados, mas o controller ainda nao os consultava nem enviava props ao Inertia.

### Fato 2: Visibilidade de Historico (`history_visible`)
No schema de `ai_requests`, a coluna `history_visible` tinha default `false`. Para que o usuario veja as consultas feitas na ferramenta, a query no `HistoryController` filtra por `user_id` e exibe as consultas, e `StartAiQuery` passa a marcar novas solicitacoes com `history_visible = true`.

### Fato 3: Componente de Modal e Paginacao
O projeto ja dispoe de componentes `Dialog` (`DialogContent`, `DialogHeader`, `DialogTitle`, etc.) e do contrato de paginacao padronizado (`Paginator<T>`), o mesmo utilizado em `Billing.vue`.
