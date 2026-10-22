# ADR-051: Historico de consultas - opt-in, plano pago, retencao e exclusao

Status: Aceito
Data: 2026-10-22
Relacionados: ADR-015 (privacidade do schema), ADR-028 (exportacao e
anonimizacao), ADR-040 (ciclo da solicitacao), ADR-041 (gate do SQL), ADR-050
(API publica v1)

## Contexto

A F5 gravou `ai_requests.history_visible` em toda solicitacao e abriu a tela de
Historico para qualquer conta. O schema e a descricao ficam comprimidos na
linha enquanto a solicitacao esta em voo, e o resultado final continua la
depois de `done`; ou seja, o historico guarda o dado mais sensivel do produto
por tempo indeterminado e sem escolha explicita de quem o gerou.

A F7 precisava decidir quatro coisas que se puxam:

1. o historico e opt-in ou opt-out, e vale para quem;
2. o que acontece com as linhas ja existentes quando a preferencia nasce;
3. por quanto tempo a linha fica guardada;
4. o que "apagar historico" apaga - e o que **nao** pode apagar.

Havia tambem a API v1 entrando na mesma fase. A lista da API nao pode depender
do toggle da tela: o cliente que criou a consulta precisa poder consulta-la
mesmo que a conta nao queira ve-la na interface.

## Decisao

### 1. Opt-in global, desligado por padrao, so para planos pagos

- `users.history_enabled` (boolean, default `false`) e a unica fonte da
  preferencia. `StartAiQuery` grava `history_visible = $user->history_enabled`
  nas duas criacoes (fila e cache hit); nada mais escreve essa coluna.
- A tela de Historico e as rotas de preferencia/exclusao exigem plano pago
  (`EnsureHistoryHasPaidPlan`). O Free ve o estado bloqueado com CTA de
  upgrade, sem consultar linhas.
- Desligar o toggle **nao apaga** o que ja existe: so impede novas gravacoes.
  A UI diz isso explicitamente e oferece "Apagar historico" ao lado.
- Usuarios que ja tinham alguma linha `history_visible = true` recebem a
  preferencia ligada no backfill da migration, para nao perder o que ja viam.
  O default da coluna em `ai_requests` continua `false`.

### 2. Exclusao so de linhas terminais

`ClearHistory` apaga as linhas do usuario em `done`/`failed`. Uma solicitacao
`queued`/`processing` ainda e dona da reserva de cota e de um job; apaga-la
deixaria o worker sem registro para liquidar ou estornar. O usuario pode
apagar de novo depois que ela terminar.

O `usage_ledger` **nunca** e tocado pela exclusao nem pela retencao: ele e
contabilidade de cota, nao historico de conteudo. O `ai_cache` tambem fica:
e cache global por `prompt_hash`, compartilhado entre contas, e nao guarda
vinculo com a solicitacao que o gerou.

### 3. Retencao de 90 dias, configuravel

- `config/ai.php` expoe `ai.history.retention_days`
  (`AI_HISTORY_RETENTION_DAYS`, padrao 90).
- O comando `history:prune` apaga somente terminais com
  `created_at < now()->subDays($days)`, aceita `--days` e `--dry-run` e nunca
  trata zero como "apagar tudo" (`max(1, ...)`).
- A rotina e semeada em `task_schedules` as `45 3 * * *`, sem parametros: o
  padrao vem do config, e o agendador a executa pela fila como as demais
  (ADR-021).

### 4. Exportacao, anonimizacao e API

- `ExportUserData` inclui o historico do usuario (alvo, fonte, dialeto, status,
  schema, descricao, resultado, tokens, cache hit e data), sem segredo nenhum.
- `AnonymizeUser` apaga `ai_requests` do usuario e revoga **todos** os tokens
  Sanctum, alem do que a ADR-028 ja fazia.
- A lista e o `show` da API v1 filtram por `api_token_id`, nao por
  `history_visible`: o canal que criou a consulta a le independentemente do
  toggle da tela (ADR-050). A tela web continua lendo so
  `history_visible = true`.

## Consequencias

### Positivas

- O dado sensivel so fica visivel na tela por escolha da conta, e some no
  prazo maximo de 90 dias sem acao manual.
- O Free nao acumula schema de graca; o historico vira argumento de upgrade.
- A contabilidade continua integra: apagar historico nao afeta cota, e a
  retencao nao cria buraco no ledger.
- Exportar e anonimizar seguem a mesma regra do resto da conta, sem caminho
  paralelo.

### Negativas / trade-offs

- O historico deixa de existir para o Free, inclusive como conveniencia.
- Uma exclusao durante uma consulta em voo deixa a linha para tras ate o job
  terminar; a UI nao promete o contrario.
- A retencao e uma varredura diaria, nao um TTL por linha: uma linha pode
  viver ate ~24 h alem do prazo.
- O backfill liga a preferencia para quem ja tinha historico; quem quiser
  sair precisa desligar e apagar explicitamente.

## Alternativas descartadas

- **Opt-out global (ligado por padrao)**: manteria a coleta de schema sem
  escolha, que e justamente o problema.
- **Historico para o Free**: contraria o modelo comercial e aumenta o custo e
  o risco do dado sensivel.
- **Apagar tambem `queued`/`processing`**: quebraria a liquidacao e o estorno
  da reserva.
- **Retencao resolvida na leitura**: a linha continuaria no banco; apagar de
  verdade exige rotina.
- **Apagar `usage_ledger` junto**: a cota seria recalculada para tras e o
  usuario ganharia consumo de volta ao apagar historico.
- **Lista da API presa ao toggle**: impediria o proprio cliente de reler a
  consulta que criou.
