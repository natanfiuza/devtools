# Survey: atualizar docs/ARQUITETURA.md

- Data: 2026-10-25
- Branch: `develop_natan`
- Pedido: "atualize o arquivo `docs/ARQUITETURA.md`"
- Método: sessão de grill em duas rodadas, com verificação de fatos no código.

## Contexto

O `docs/ARQUITETURA.md` foi escrito em 2026-10-20 e expandido em 2026-10-22
(commit `5875d42`). Depois disso entraram ADR-051 (histórico opt-in), ADR-054
(cota acumulativa), ADR-055 (Base64 Image no servidor), ADR-056 (prompts e
cache por locale), a página de contato e correções recentes do Mercado Pago.
O documento ficou desatualizado em pontos de arquitetura, não só de redação.

## Decisões das rodadas

### Rodada 1

1. **Escopo**: manter o mergulho em IA + pagamentos + segurança HTTP e
   acrescentar um mapa curto das demais superfícies do servidor.
2. **Duplicação**: linkar as fontes canônicas (`API.md`, `openapi.yaml`,
   `RUNBOOK.md`, `guides/*.md`, `DESIGN.md`, `glossario.md`) e guardar no ARQ
   apenas desenho, fronteiras, invariantes e o "porquê".
3. **Estilo**: converter o ARQUITETURA.md inteiro para português com acentos,
   mantendo ADRs como texto `ADR-0xx`.
4. **Arquivos**: `docs/ARQUITETURA.md`, `CHANGELOG.md` e `docs/glossario.md`;
   os dois últimos com correções pontuais e estilo sem acentos preservado.

### Rodada 2

5. **Mapa**: Base64 Image em subseção curta, Contato em um parágrafo,
   Histórico como subseção da IA; Admin/SEO/i18n/deploy só como linhas de
   tabela com links.
6. **Pagamentos**: acrescentar Estornos, Ciclo da preapproval, confirmação de
   intenção como quarto caminho e a ausência de conciliação.
7. **Diagramas**: manter os dois ASCII atualizados e adicionar uma tabela
   compacta de estados/códigos de falha da IA.
8. **CHANGELOG/glossário**: corrigir apenas os fatos errados, sem seção pós-F7
   e sem conversão integral para acentos.

## Fatos verificados

### Tools e superfícies

- São 10 tools: oito rodam no navegador (`base64-url-hash`, `json-to-code`,
  `json-viewer`, `jwt-decoder`, `regex-tester`, `sql-formatter`,
  `timestamp-uuid`, `markdown-to-pdf`) e duas usam o servidor:
  `ai-sql-builder` e `base64-image`.
- O Contato (`/{locale}/contact`) é superfície pública fora do registro de
  tools.
- Rotas web relevantes: `/api/ai/queries`, `/api/ai/history`,
  `/api/ai/queries/{ulid}`, `/api/tools/base64-image/*`,
  `/tools/base64-image/{download,preview}`, `/subscribe/checkout`,
  `/webhooks/mercadopago`, `/admin/*`.

### IA

- `StartAiQuery` valida o dialeto **antes** da transação; depois resolve o
  locale, carrega o prompt, canonicaliza, monta as mensagens e estima tokens.
- Na transação: trava `users`, faz dedupe por `user_id` + `api_token_id` +
  `prompt_hash` + pendentes dentro de `AI_INFLIGHT_TTL_SECONDS`, consulta o
  cache, valida limites do plano, cria a solicitação e grava o débito.
- O dispatch de `ProcessAiQuery` acontece depois do commit; cache hit e
  dedupe não criam job.
- O payload canônico tem `locale`; o hash é MD5 do JSON com chaves ordenadas.
  Cache é isolado por locale, mas o hit no POST não grava `locale` na linha.
- `Quota` não tem coluna de saldo. Free: `lifetime_free_uses` (5 na carga
  inicial) menos débitos, mais estornos/concessões; reserva de 1 uso. Pago:
  limite = lotes mensais de todas as assinaturas não estornadas (1 por mês
  iniciado, até 12 no anual) + grants; consumo = todos os débitos de tokens
  menos estornos de falha; rollover; `remaining = max(0, limit - used)`;
  `window` é o período vigente apenas informativo; `next_credit_at` aponta o
  próximo lote.
- `subscriptionCredits()` inclui assinaturas `active` ou com `ended_at`, desde
  que `refunded_at` seja nulo. Uma assinatura futura ainda não credita.
- Contrato: oito campos obrigatórios (`sql`, `dialect`, `assumptions`,
  `warnings`, `tables_used`, `needs_clarification`, `clarification`,
  `outputs`) e dois opcionais (`title`, `explanation`).
- Gate do SQL só roda no alvo `sql` e quando `needs_clarification` é falso.
- `ProcessAiQuery` recebe só o ULID, reidrata o payload comprimido, consulta o
  cache de novo e só então chama o provedor. O payload é apagado em
  `SettleAiQuery`/`FailAiQuery`; `Settle` grava cache depois do commit.
- Estados: `queued`, `processing`, `done`, `failed`. Códigos gravados:
  `provider_unavailable`, `unsafe_sql`, `invalid_contract`, `timeout`,
  `internal`.
- `invalid_response` é motivo de `ProviderException` (log), não `error_code`;
  resposta inválida do provedor termina como `provider_unavailable`.
  `AiFailureCode::InvalidResponse` existe no enum, mas nenhum caminho o grava.
- `ai:reconcile` marca `failed` + `error_code=timeout` (não existe status
  `timeout`) e estorna a reserva.
- Histórico: opt-in em `users.history_enabled`, só plano pago; grava
  `title`/`prompt`/`schema` em claro quando visível; `canonical_payload` some
  no terminal; `history:prune` apaga terminais após 90 dias;
  `ClearHistory` apaga terminais sob demanda; nenhum dos dois toca em ledger
  ou cache; desligar o toggle não apaga antigos.
- Cache: TTL 30 dias; hash divergente = miss sem apagar; payload indecifrável
  ou contrato inválido = apaga a linha; hit nunca debita.
- API v1: `GET /api/v1/me`, `GET /api/v1/output-targets`,
  `GET /api/v1/sql-queries`, `POST /api/v1/sql-queries`,
  `GET /api/v1/sql-queries/{ulid}`; PAT criado em `/settings/api-tokens`,
  ability `ai:query`, leitura escopada por `api_token_id`.
- Polling web: `poll_after_ms` 1500 ms, dobra até 5000 ms, desiste em 120 s.
  API v1 devolve `poll_url` (nulo no terminal).

### Pagamentos

- Quatro caminhos: venda manual (`RegisterPayment`), Checkout Pro
  (`mode=one_time`), Preapproval (`mode=recurring`) e confirmação de intenção
  (`ConfirmIntentPayment`). Os dois online usam `POST /subscribe/checkout`.
  Não existem `StartPreapproval` nem `POST /subscribe/recurring`.
- Não há conciliação de pagamentos: o webhook é a única confirmação
  automática; o retorno do navegador não confirma nada.
- `StartCheckout` cancela preapprovals ativas antes de abrir uma nova sessão
  (exceto quando o modo recorrente tenta o mesmo plano, que é recusado).
- `ApproveGatewayPayment` confere valor/moeda com a sessão congelada, abre o
  período e registra o pagamento; idempotente por `(gateway, gateway_ref)`.
  Não checa `expires_at` da sessão.
- `OpenSubscriptionPeriod`: Free não cria; mesmo plano empilha no maior fim
  futuro; plano diferente começa agora; não encerra a linha anterior.
  `GrantPlan` encerra assinaturas quando o plano é Free ou diferente; o
  caminho de aprovação do gateway não.
- Preapproval: autorização grava `authorized_at` e limpa `expires_at`, sem
  abrir período; a primeira cobrança pode levar ~1 h; renovação aprovada
  empilha; cancelamento grava `renewal_canceled_at` sem mudar `status`; falha
  grava `renewal_failed_at` e avisa uma vez; aprovação seguinte limpa.
- Estornos: `RefundGatewayPayment` chama o provedor primeiro, tolera
  404/409 como "já estornado", cancela a preapproval em best-effort e chama
  `RefundPayment`. No webhook `refunded`/`charged_back`, pula a chamada ao
  provedor e só sincroniza. `RefundPayment` marca `refunded`, encerra
  assinatura vigente/futura com `refunded_at`, notifica uma vez e não escreve
  no ledger. `payments` não guarda data de estorno.
- Webhooks: HMAC-SHA256 sobre `id`/`request-id`/`ts`, comparação em tempo
  constante, sem janela de timestamp; sem segredo → 503; assinatura inválida
  → 401; unique `(gateway, event_id)`; job com `tries=3`, backoff
  `[10,60,300]` (com 3 tentativas só 10 s e 60 s são usados), timeout 60 s;
  busca o recurso antes de agir; ações idempotentes por `gateway_ref`; tópico
  desconhecido `ignored`; admin somente leitura; retenção 30 dias.
- Doações: Pix dinâmico com expiração do provedor; Pix estático (`manual`) sem
  expiração e sem confirmação automática; `effectiveStatus()` vence na
  leitura; página pública por ULID não mostra dados do doador; fora de
  `payments`, cota e fatura.
- `payments:expire` só mexe em `pending`; nenhuma rotina apaga `payments`,
  `subscriptions` ou `donations`.

### Base64 Image

- Exceção documentada à ADR-002 (ADR-055): o arquivo sobe e a conversão
  acontece no PHP.
- Limites: 2 MB Free/anônimo e 25 MB pago, com `plans.max_image_upload_bytes`
  sobrepondo; valores em `config/tools.php`.
- Guarda o original e o `.txt` com a Data URI em
  `storage/app/data/base64_images/{user_id}` ou `/anonymous`.
- Linha em `base64_image_conversions` só para autenticado; anônimo só arquivo.
- Download direto do `.txt` quando pago e Data URI > 2 MB; abaixo disso a
  resposta traz `data_uri`.
- Retenção: 24 h Free/anônimo, 30 dias pago; `base64-images:prune` roda de
  hora em hora e apaga só o arquivo físico; a linha permanece com
  `file_deleted_at`; anônimos são podados por mtime.
- Histórico autenticado confere dono; apagar removes arquivos e linha (ou
  arquivos anônimos). Não consome cota de IA.

### Contato

- `/{locale}/contact` grava `contact_messages` e enfileira
  `ContactMessageReceived` para `CONTACT_EMAIL`.
- Rate limit de 5 envios/min por IP no FormRequest; honeypot `hp_website`
  descarta silenciosamente, com resposta de sucesso.
- Grava nome, e-mail, assunto, mensagem, IP, user agent e `user_id` quando
  autenticado; não há tela de admin.

## Fontes canônicas

- Contrato HTTP: `docs/API.md`, `docs/openapi.yaml`.
- Operação: `docs/RUNBOOK.md`, `docs/guides/cron.md`,
  `docs/guides/mercado-pago.md`.
- Produto e fases: `docs/SPEC.md`.
- UI: `docs/design/DESIGN.md`.
- Vocabulário: `docs/glossario.md`.
- Decisões: `docs/adr/ADR-001` a `ADR-056`.

## Riscos e pendências

- **Upgrade via gateway**: `StartCheckout` cancela preapprovals, mas
  `OpenSubscriptionPeriod` não encerra a linha anterior; a linha antiga segue
  ativa até `current_period_end` e continua creditando na cota acumulativa.
  Documentar como comportamento atual e apontar como possível inconsistência.
- **Checkout vencido**: aprovação tardia ainda abre período; `expires_at` vale
  para a tela e para `payments:expire`, não para a aprovação.
- **Drift fora de escopo**: `SPEC.md`, `API.md`, `openapi.yaml` e `DESIGN.md`
  também têm trechos antigos; não serão alterados nesta task.
- **Sem lint de documentação**: validação por revisão manual, greps e checagem
  de links.

## Arquivos a alterar

- `docs/ARQUITETURA.md` (reescrita).
- `CHANGELOG.md` (três correções pontuais).
- `docs/glossario.md` (quatro correções pontuais).
- Artefatos: este survey, `docs/plans/develop_natan/20261025_atualizar_arquitetura_plansfacts.md`,
  `docs/claude-code/plans/develop_natan/20261025_develop_natan_atualizar_arquitetura.md`
  e o relatório final.

Nenhum commit ou push será feito.
