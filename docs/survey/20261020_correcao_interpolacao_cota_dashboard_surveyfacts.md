# Survey — Correção da Interpolação de Data na Cota do Dashboard (2026-10-20)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Na tela de Visão Geral do painel de usuário no card COTA a mensagem "Franquia: 24.000.000 · Usado: 17.325 até :date" o campo :date nao esta sendo substituido corrija
- **Tela / Modulo:** Painel do Usuário / Visão Geral (`/dashboard`), `resources/js/pages/Dashboard.vue`, `lang/*/account.php`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de grill, 2 decisoes consensadas, inspecao de codigo e arquivos de idioma
- **Arquivos-chave lidos:**
  - `lang/pt_BR/account.php`
  - `lang/pt_PT/account.php`
  - `lang/en/account.php`
  - `lang/es/account.php`
  - `lang/fr/account.php`
  - `lang/en/auth.php` (convencoes de interpolacao Laravel `:param` vs Vue `{param}`)
  - `resources/js/pages/Dashboard.vue`
  - `resources/js/types/account.ts`
  - `app/Http/Controllers/Account/OverviewController.php`

## 2. Decisoes das rodadas

### Rodada 1 — Convencao de interpolacao e testes
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Padrao de interpolacao da chave `quota_until` nos 5 idiomas | **Adotar `{date}` em `lang/{en,es,fr,pt_BR,pt_PT}/account.php` conforme convencao do vue-i18n** |
| Q2 | Cobertura de testes unitarios de frontend | **Criar suite de testes unitarios no Vitest (`resources/js/__tests__/dashboard-overview.test.ts`) cobrindo a interpolacao e os estados da janela de cota** |

### Defaults assumidos (nao contestados)
- Nenhum ADR necessario, pois trata-se de correcao de conformidade com convencao arquitetural ja estabelecida (chaves consumidas pelo Vue utilizam `{param}`).
- A chave `account.overview.quota_until` nao e consumida diretamente no backend PHP (onde `:date` seria esperado), apenas no componente Vue.

## 3. Relatorio de fatos levantados
### Fato 1: Convencao i18n Laravel vs vue-i18n
No projeto devtools, todas as chaves lidas pelo servidor via Laravel usam a sintaxe `:param`, enquanto chaves consumidas no frontend pelo Vue via `vue-i18n` usam `{param}`. Em `lang/*/account.php`, a chave `account.overview.quota_until` estava incorretamente utilizando `:date`, fazendo com que o `vue-i18n` nao realizasse o replace do parametro passado via `{ date: props.quota.windowEnd }`.

### Fato 2: Componente Dashboard e testes de frontend
O componente `resources/js/pages/Dashboard.vue` exibe a informacao no elemento com seletor `data-test="conta-janela"`. Atualmente nao havia teste no Vitest cobrindo este componente diretamente, o que permitiu que o bug passasse despercebido nos testes unitarios.
