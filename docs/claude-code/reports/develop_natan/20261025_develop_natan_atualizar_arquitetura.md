# Relatorio - atualizacao do ARQUITETURA.md

**Data:** 2026-10-25
**Branch:** `develop_natan`
**Escopo:** atualizar `docs/ARQUITETURA.md` para o runtime atual, converter o
texto para portugues com acentos, linkar as fontes canonicas em vez de
duplica-las e corrigir os mesmos fatos no `CHANGELOG.md` e no
`docs/glossario.md`.

Nenhum commit ou push foi feito; o dono revisa e aprova pelo `gitpr`.

## 1. Entregaveis

- `docs/ARQUITETURA.md`: reescrito (624 linhas), em portugues com acentos,
  mantendo o mergulho em IA + pagamentos + seguranca HTTP e acrescentando um
  mapa curto das demais superficies do servidor.
- `CHANGELOG.md`: tres correcoes pontuais, no estilo sem acentos.
- `docs/glossario.md`: seis entradas corrigidas, no estilo sem acentos.
- `docs/survey/20261025_atualizar_arquitetura_surveyfacts.md`: levantamento da
  sessao de grill.
- `docs/plans/develop_natan/20261025_atualizar_arquitetura_plansfacts.md`:
  plano da sessao.
- `docs/claude-code/plans/develop_natan/20261025_develop_natan_atualizar_arquitetura.md`:
  plano de implementacao exigido pelo `CLAUDE.md`.
- Este relatorio.

Nenhum ADR novo foi criado: o trabalho documenta decisoes ja registradas
(ADR-051, ADR-054, ADR-055, ADR-056) e comportamento atual do codigo, sem
introduzir decisao nova.

## 2. O que mudou no ARQUITETURA.md

### Intro e fontes canonicas

A introducao agora separa com precisao as superficies: o AI SQL Builder e o
Base64 Image processam no servidor (ADR-002 e ADR-055), as oito tools restantes
do registro rodam 100% no navegador, e o Contato e uma superficie publica fora
do registro de tools. O paragrafo seguinte aponta as fontes canonicas:
`API.md`, `openapi.yaml`, `RUNBOOK.md`, `guides/cron.md`,
`guides/mercado-pago.md`, `SPEC.md`, `design/DESIGN.md` e `glossario.md`.

### Mapa das superficies

Tabela com AI web, API v1, pagamentos, webhooks, Base64 Image, Contato, Admin,
i18n/prompts, SEO, deploy/operacao, contas/planos/cota e seguranca HTTP. Duas
subsecoes curtas:

- **Base64 Image (ADR-055):** limites Free/pago, arquivos original e `.txt`,
  retencao de 24 h/30 dias, poda que preserva a linha de metadados, download
  direto acima de 2 MB e ausencia de consumo de cota.
- **Contato:** pagina publica e indexavel, gravacao em `contact_messages`,
  enfileiramento de `ContactMessageReceived`, rate limit de 5/min por IP,
  honeypot `hp_website` e ausencia de tela de admin.

### IA

- Diagrama ASCII atualizado com `locale`, hash, dedupe, cache, fila, provedor,
  contrato, Gate do SQL e liquidacao.
- Tabela de fronteiras com a linha da API v1 explicitando que e outra porta
  para a mesma acao, nao uma segunda implementacao.
- Ciclo em sete passos: POST e ordem da transacao, reserva/cota, fila,
  provedor/contrato, liquidacao, polling e historico/retencao.
- Cota descrita pela ADR-054: sem coluna de saldo, Free com 5 usos vitalicios,
  pago com lotes mensais de todas as assinaturas nao estornadas, rollover,
  `ended_at` ainda creditando, `refunded_at` excluindo a linha e `remaining`
  nunca negativo.
- Cache com TTL de 30 dias, hit sem debito, cache global e os dois casos de
  corrupcao separados (hash divergente preserva a linha; payload indecifravel
  ou contrato invalido apaga).
- API v1 com os cinco endpoints e escopo por `api_token_id`.
- Fila e tempos com 60/90/120/150 s e `ai:reconcile` marcando
  `failed` + `error_code = timeout`.
- Tabela de estados `queued|processing|done|failed` e dos cinco codigos
  gravados, com a nota de que `invalid_response` e motivo de log, nao
  `error_code`.

### Pagamentos

- Quatro caminhos: venda manual, Checkout Pro, Preapproval e confirmacao de
  intencao.
- Diagrama ASCII do fluxo online (os dois caminhos que passam pelo provedor),
  com webhook, fila, busca do recurso e as acoes finais.
- Tabela de camadas incluindo venda manual, intencao e estorno.
- Um periodo por pagamento, com a ressalva de que `OpenSubscriptionPeriod` nao
  encerra a linha anterior e o caminho de aprovacao do gateway tambem nao.
- Ciclo da preapproval: autorizacao sem periodo, primeira cobranca, renovacoes,
  cancelamento sem perder o periodo pago, falha avisada uma vez e estorno
  cancelando a preapproval.
- Estornos separando o caminho do gateway (provedor primeiro, `404`/`409`
  tolerados, preapproval em best-effort) do manual (so estado local), e
  registrando que estorno de dinheiro nao devolve consumo no ledger.
- Webhooks: HMAC sobre `id`/`request-id`/`ts` sem janela de timestamp, unique
  `(gateway, event_id)`, 503 sem segredo, 401 com assinatura invalida,
  `tries=3` com backoff 10/60 e idempotencia por `gateway_ref`.
- Doacoes: Pix dinamico quando o gateway esta disponivel, fallback estatico,
  expiracao do provedor prevalecendo sobre os 60 min, confirmacao idempotente,
  pagina publica sem dados do doador e fora de `payments`/cota/fatura.
- Operacao com tempos, `payments:expire` tratando checkouts e doacoes
  pendentes, podas que so tocam as proprias tabelas e expiracao resolvida na
  leitura.

### Seguranca e dados

`SecurityHeaders` (nonce, CSP, HSTS, proxies e Turnstile) foi atualizado e
ligado ao RUNBOOK/ADR-052. A secao "O que nunca sai daqui" cobre logs sem
schema/prompt, corpo do provedor, SQL nunca executado, cache e historico
opt-in, escopo de sessao versus PAT, segredo do gateway, PII de webhook, Base64
Image e Contato.

## 3. Fatos verificados contra o codigo

- Ordem do POST em `StartAiQuery`: configuracao/modelo e dialeto antes da
  transacao; lock da conta -> dedupe (`user_id` + `api_token_id` +
  `prompt_hash`, pendentes em 600 s) -> cache -> limites -> criacao -> ledger;
  dispatch somente depois do commit.
- `CanonicalPayload` inclui `locale`; o hash e MD5 do JSON com chaves
  ordenadas; o cache compara o payload inteiro antes de servir.
- `ProcessAiQuery` recebe apenas o ULID, reidrata `canonical_payload`, consulta
  o cache de novo e so entao chama o provedor.
- `Quota`: Free com `lifetime_free_uses` (5 por padrao) e reserva de 1 uso;
  pago com lotes mensais das assinaturas nao estornadas, inclusive com
  `ended_at`, e `refunded_at` excluindo a linha; `window` e informativa.
- `ClearHistory` apaga apenas `done`/`failed`; `PruneHistory` apaga todas as
  terminais antigas, inclusive as nunca visiveis, sem tocar em ledger ou cache.
- `AiCacheRepository`: hash divergente devolve miss e preserva a linha; payload
  indecifravel ou contrato invalido apaga.
- API v1: cinco rotas, ability `ai:query`, leitura escopada por
  `user_id` + `api_token_id`.
- `ai:reconcile`: timeout do job mais `reconcile_grace_seconds` (30 s por
  padrao), marcando `failed` com `AiFailureCode::Timeout`.
- `AiFailureCode::InvalidResponse` existe no enum, mas nenhum caminho o grava;
  resposta invalida do provedor termina como `provider_unavailable`.
- Pagamentos: quatro caminhos, sem conciliacao automatica; `StartCheckout`
  cobre `one_time` e `recurring` na mesma rota e recusa recorrencia duplicada no
  mesmo plano; nos demais casos cancela preapprovals ativas antes da nova
  sessao.
- `ApproveGatewayPayment` nao verifica `expires_at`; uma aprovacao tardia ainda
  abre periodo.
- `RefundGatewayPayment` chama o provedor primeiro, tolera `404`/`409` e cancela
  a preapproval em best-effort; `RefundPayment` so e chamado direto no estorno
  manual. `RefundPayment` nao escreve no `usage_ledger`.
- Webhook: HMAC sem janela de timestamp, unique `(gateway, event_id)`,
  `ProcessWebhookEvent` com `tries=3`, backoff `[10, 60, 300]` e timeout de
  60 s (com 3 tentativas, 300 s nao chega a ser usado).
- `CreateDonation`: dinamico somente com gateway disponivel; sem e-mail da
  doacao usa `DONATIONS_PAYER_EMAIL` ou `MAIL_FROM_ADDRESS`; estatico sem
  confirmacao automatica.
- `payments:expire` so mexe em `pending` (checkouts e doacoes).
- Base64 Image: limites em `config/tools.php`, original + `.txt`, retencao
  24 h/30 dias, historico de 50 itens, sem cota.
- Contato: 5/min por IP, honeypot `hp_website`, PII em `contact_messages`, sem
  tela de admin.

## 4. Correcoes aplicadas na revisao

Alem da reescrita, a revisao contra o codigo corrigiu:

- "historia/historico de falhas" na frase da API v1 foi substituido por uma
  descricao exata: cota, cache, estados e falhas valem para as duas portas, mas
  a lista e escopada pelo token e o opt-in de historico so decide a tela web.
- A tela de Cache de IA nao "esconde do servidor": ela so nao mostra prompt,
  schema, `canonical_payload` ou resposta.
- O limiar do `ai:reconcile` nao e "timeout + 30 s" fixo; a folga vem de
  `reconcile_grace_seconds`.
- As podas so tocam as proprias tabelas; nenhuma apaga `payments`,
  `subscriptions` ou `donations`.
- Removida a afirmacao falsa de que a Politica de Privacidade descrevia o
  historico opt-in e os 90 dias.
- Logs incluem `reason`, alem de id, tokens, `error_code` e classe da excecao.
- `StartCheckout` nao cancela sempre: recusa recorrencia duplicada no mesmo
  plano e, nos demais casos, cancela antes da nova sessao.
- Estorno manual nao passa pelo provedor; so o estorno de pagamento de gateway
  chama `RefundGatewayPayment`.
- `payments:expire` cobre checkouts e doacoes, nao apenas pagamentos.
- Doacao dinamica/estatica explicitada pela disponibilidade do gateway e pelo
  fallback de e-mail do pagador.
- O diagrama de pagamentos foi renomeado para "Fluxo online", porque venda
  manual e intencao nao passam pelo provedor.
- A confirmacao de intencao e uma acao `confirm-payment` em `/admin/intents`,
  nao uma pagina propria.
- "Uma linha por concessao" virou "uma linha por periodo pago", coerente com a
  F6 e com o `CHANGELOG`.
- O cache hit no job estorna a reserva inteira via `SettleAiQuery::fromCache`,
  sem sugerir que passa por `FailAiQuery`.
- A linha de Pagamentos no mapa ganhou `/admin/payments` e `/admin/intents`.

## 5. Verificacao executada

- Termos obsoletos (`StartPreapproval`, `subscribe/recurring`, `outras oito`,
  `soma do usage_ledger`, `janela da assinatura vigente`, `gate lexical`,
  `história de falhas`): nenhum no ARQUITETURA.md nem no glossario. As mencoes
  historicas de "gate lexical" no CHANGELOG foram preservadas por decisao da
  sessao.
- Links relativos: todos resolvem a partir de `docs/` (`API.md`,
  `openapi.yaml`, `RUNBOOK.md`, `SPEC.md`, `design/DESIGN.md`,
  `glossario.md`, `guides/cron.md`, `guides/mercado-pago.md`,
  `../config/tools.php`).
- `git diff --check`: limpo.
- Diagramas ASCII: 86 e 90 colunas no pior caso; nenhum caractere de box
  drawing Unicode.
- `docs/ARQUITETURA.md`: 624 linhas.
- Nao ha suite que valide documentacao. `composer test` e `npm run test` nao
  foram rodados porque a mudanca e somente de docs.

## 6. Riscos e pendencias

- **Troca de plano pelo gateway:** `StartCheckout` cancela a preapproval, mas
  `OpenSubscriptionPeriod` e `ApproveGatewayPayment` nao encerram a linha de
  assinatura anterior. A linha antiga pode seguir ativa ate
  `current_period_end` e creditar na cota acumulativa. O ARQ registra isso como
  comportamento atual, nao como invariante; e um possivel bug a decidir.
- **Checkout vencido:** `ApproveGatewayPayment` nao usa `expires_at`; uma
  aprovacao tardia ainda abre periodo. O prazo vale para a tela e para a
  varredura, nao para a aprovacao.
- **Sem conciliacao:** um webhook perdido fica como anomalia para o admin; nao
  ha varredura de pagamentos aprovados no provedor.
- **Politica de Privacidade:** os arquivos `lang/*/legal.php` nao mencionam o
  historico opt-in nem a retencao de 90 dias. O ARQ deixou de afirmar que
  mencionam; alinhar o texto legal ficou fora do escopo.
- **Drift fora de escopo:** `docs/SPEC.md`, `docs/API.md`,
  `docs/openapi.yaml` e `docs/design/DESIGN.md` tambem tem trechos que podem
  estar desatualizados; nao foram alterados nesta tarefa.
- **Sem lint de documentacao:** a qualidade depende de revisao manual, greps e
  checagem de links.
- **Tamanho:** o ARQ ficou com 624 linhas, acima da estimativa de 420-450 do
  plano aprovado, por causa do mapa ampliado, dos quatro caminhos de pagamento,
  dos estornos, do ciclo da preapproval e das excecoes documentadas.
- **Datas dos artefatos:** o texto do plano aprovado ainda usa `20261024`;
  survey, planos e este relatorio usam `20261025`, a data real da execucao.

## 7. Estado do git

Alterados:

- `CHANGELOG.md`
- `docs/ARQUITETURA.md`
- `docs/glossario.md`

Novos:

- `docs/survey/20261025_atualizar_arquitetura_surveyfacts.md`
- `docs/plans/develop_natan/20261025_atualizar_arquitetura_plansfacts.md`
- `docs/claude-code/plans/develop_natan/20261025_develop_natan_atualizar_arquitetura.md`
- `docs/claude-code/reports/develop_natan/20261025_develop_natan_atualizar_arquitetura.md`

Nenhum commit, nenhum push e nenhuma alteracao de codigo de aplicacao.
