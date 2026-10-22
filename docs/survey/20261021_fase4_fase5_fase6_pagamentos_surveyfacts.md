# Levantamento - FASE 4 (auditoria), FASE 5 (fechamento) e FASE 6 (pagamentos)

Data: 2026-10-21
Branch: `develop_natan`
Pedido original: "Verifique se a fase 4 (docs/acceptance/FASE-4.md) foi
concluida e depois continue para a proxima fase 5".
Plano: `docs/plans/develop_natan/20261021_fase4_fase5_fase6_pagamentos_plansfacts.md`
Copia no padrao do CLAUDE.md:
`docs/claude-code/plans/develop_natan/20261021_develop_natan_fase4_fase5_fase6_pagamentos.md`

## 1. Estado do repositorio no inicio

- Branch `develop_natan`; HEAD no inicio da execucao `eadc2d6` ("feat: inclui
  metadados como comentarios no download do AI SQL"). O usuario commita por
  fora; o assistente nao commita nem faz push.
- Working tree com apenas dois arquivos nao rastreados de relatorios do
  `gitpr` e o `plansfacts` desta sessao.
- `.env` local com `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` preenchidas
  (verificacao booleana, sem imprimir valores). `MERCADO_PAGO_*` e
  `DONATIONS_PIX_*` ainda nao existem no `.env`.
- `pdo_sqlite` e `sqlite3` disponiveis (usados no banco dedicado do smoke).
- `bacon/bacon-qr-code` v3.1.1 ja instalado como dependencia transitiva do
  Fortify; sera declarado explicitamente no `composer.json`.
- Edge headless em `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`
  para as capturas de tela do smoke.

## 2. FASE 4 (Auth) - o que o aceite registra

- `docs/acceptance/FASE-4.md` foi criado por backfill em 2026-10-20, com 15
  criterios e dois carry-overs: 2FA sem teste dedicado e `users.appearance`
  somente leitura. Passkeys desligadas de proposito.
- O aceite aponta testes por criterio (`AuthenticationTest`,
  `RegistrationTest`, `LocalizedAuthPagesTest`, `EmailVerificationTest`,
  `VerificationNotificationTest`, `PasswordResetTest`,
  `PasswordConfirmationTest`, `SocialLoginTest`, `SetPasswordTest`,
  `TranslationsParityTest`, `SecurityTest`, `ProfileUpdateTest`,
  `ProfilePhotoTest`, `AppearanceTest`, `AccountPanelTest`,
  `AdminAccountActionsTest`, `AdminRoleTest`, `AdminPanelTest`,
  `AdminImpersonationTest`, `NewsletterTest`, `LegalPagesTest`,
  `LocaleRoutingTest`, `MailLocaleTest`).
- A auditoria desta sessao reexecuta os gates e confere cada criterio no codigo;
  corrige apenas bloqueios.

## 3. FASE 5 (AI SQL Builder) - o que o aceite registra

- `docs/acceptance/FASE-5.md` aceita 19 criterios de codigo em 2026-10-20 e
  deixa o criterio 20 (smoke real com DeepSeek) pendente por falta de chave.
- As `AI_*` agora estao preenchidas no `.env` local; o smoke sera feito em
  banco dedicado, com worker, HTTP, sessao e CSRF reais.
- Duas divergencias conhecidas:
  - o historico de consultas foi entregue antecipadamente (commit `7f0fc2d`,
    `history_visible => true`, tela `dashboard/History`), mas SPEC e aceite
    ainda dizem F7;
  - `HistoryController` le `result['summary']`, campo que o contrato do LLM
    nao possui; a tela nunca mostra resumo.

## 4. FASE 6 (pagamentos) - estado do codigo

Existe hoje:

- catalogo de planos (`plans`), assinaturas (`subscriptions`), pagamentos
  manuais (`payments`), ledger de cota, e-mails de pagamento confirmado e
  estornado, telas de billing e de admin de pagamentos;
- `GrantPlan`, `RegisterPayment` e `RefundPayment` operam sem gateway;
- `Plan::periodEndFrom()` usa `addMonth()`/`addYear()` (estoura em 31/01);
- `Subscription::isCurrent()`/`scopeCurrent()` ignoram o inicio do periodo e
  nao distinguem linha futura de linha vigente - com periodos empilhados, a
  linha futura seria escolhida como ativa e a cota reiniciaria cedo;
- `User::activeSubscription()` ordena so pelo inicio, sem garantir que a linha
  cobre "agora".

Nao existe hoje:

- contrato `PaymentGateway`, cliente Mercado Pago, `checkout_sessions`,
  `donations`, `webhook_events`, rotas de webhook, comandos de expiracao/poda,
  tela de doacao, aba de doacoes no admin e tela de eventos de webhook.

## 5. Mercado Pago - fatos confirmados na documentacao

- Checkout Pro: `POST /checkout/preferences`, redirect para `init_point`.
- Preapproval sem plano: `POST /preapproval`, `auto_recurring` mensal ou anual,
  `status=pending`, `init_point`; cartao apenas; primeira cobranca cerca de 1h
  depois da autorizacao.
- Pix dinamico: `POST /v1/payments` com `payment_method_id=pix`; resposta em
  `point_of_interaction.transaction_data.qr_code`, `qr_code_base64` e
  `ticket_url`.
- Webhooks: topicos exatamente `payment`, `subscription_preapproval` e
  `subscription_authorized_payment`; recursos `/v1/payments/{id}`,
  `/preapproval/{id}` e `/authorized_payments/{id}`.
- Assinatura: `x-signature` no formato `ts=...,v1=...`; manifesto
  `id:{data.id minusculo};request-id:{x-request-id};ts:{ts};`; HMAC-SHA256;
  comparar em tempo constante; responder 2xx rapido; a MP reenvia.
- `subscription_authorized_payment`: `processed`, `waiting for gateway`,
  `recycling` (recusado, retentavel); depois de 4 falhas o processado fica com
  pagamento recusado; 3 parcelas rejeitadas cancelam a assinatura.
- Estorno: `POST /v1/payments/{id}/refunds`; cancelamento de preapproval: `PUT
  /preapproval/{id}` com `status=canceled`.

## 6. Decisoes do usuario nesta sessao

- Auditar a F4 inteira e corrigir so bloqueios.
- F5: smoke real completo em banco dedicado, com evidencia e limpeza.
- Corrigir o `HistoryController` agora e alinhar docs sobre o historico.
- F6: Checkout Pro para avulso; Preapproval sem plano para recorrencia;
  `/subscribe` com tres caminhos; `checkout_sessions` separada de `payments`;
  uma linha por periodo empilhada; autorizacao da preapproval nao libera
  acesso; "Pagar agora" com recorrencia ativa no mesmo plano cancela a
  preapproval; segunda recorrencia no mesmo plano bloqueada; cancelamento pelo
  usuario; falha de renovacao com e-mail unico; estorno pela API da MP; doacao
  Pix dinamico com fallback estatico; admin com doacoes e webhooks; `/donate`
  indexavel e `/subscribe` noindex.
- Sem credenciais MP, o smoke real da MP fica como criterio pendente no aceite
  da F6, com roteiro para o dono.

## 7. Evidencias coletadas durante a execucao

### FASE 4 (auditoria)

- Auditoria completa sem implementacao nova; nenhum bloqueio.
- Testes direcionados da fase: **209 testes, 8.371 assercoes**, verdes.
- Carry-overs conferidos: `users.appearance` somente leitura; 2FA sem teste
  dedicado; passkeys desligadas; `verified` nas rotas autenticadas (o POST de
  `/subscribe` usa so `auth` + `active`).
- Evidencia anexada em `docs/acceptance/FASE-4.md` ("Auditoria 2026-10-21").

### FASE 5 (fechamento)

- Os 19 criterios reauditados; o criterio 20 (smoke real) foi executado com
  DeepSeek real em SQLite dedicado, com HTTP/sessao/CSRF/fila reais.
- Matriz de 17 passos registrada em `docs/acceptance/FASE-5.md`; a busca pelo
  marcador do schema em `laravel.log` e no log do worker deu 0 ocorrencias.
- `HistoryController` corrigido: filtra `history_visible = true` e deriva o
  resumo de `clarification`/`assumptions`/`tables_used`/`sql`, sem
  `result['summary']`; `AccountPanelTest` atualizado.
- Cleanup do smoke confirmado; banco `devtools` intocado.

### FASE 6 (implementacao e aceite)

- 32 migrations rodam em SQLite dedicado `storage/smoke_f6.sqlite` (32/32),
  incluindo as seis novas da fase; o banco `devtools` nao recebeu nenhuma.
- `task_schedules` semeia `payments:expire` (`*/5 * * * *`) e `webhooks:prune`
  (diario 03:30, `--days=30`), sem duplicar linhas existentes.
- Gates finais (2026-10-21):

```text
composer test        -> 560 testes, 560 passaram, 12.902 assercoes (Pint ok, PHPStan 7 com 0 erros)
npm run test         -> 55 arquivos, 749 testes, 749 passaram
npm run types:check  -> vue-tsc limpo
npm run lint:check   -> 0 erros (9.943 avisos de estilo pre-existentes)
npm run format:check -> Prettier ok
npm run build        -> ok
npm run build:ssr    -> ok
payments:expire --dry-run -> 0 checkout(s) e 0 doacao(oes)
webhooks:prune --dry-run  -> 0 evento(s) com mais de 30 dias
```

- Rotas conferidas por `route:list`: `subscribe` (4), `donate` (3),
  `webhooks` (2), `dashboard/billing` (2), `admin/payments` (3).
- Ajustes de verificacao: `AdminPanelTest` espera seis formas de pagamento;
  `admin-panel.test.ts` espera nove secoes; `DonationController@store` monta o
  array tipado para o PHPStan 7; `PaymentController` declara os genericos de
  `LengthAwarePaginator`; `account-panel.test.ts` normalizado pelo Prettier.
- Smoke real da Mercado Pago **pendente**: sem credenciais no ambiente. O
  roteiro do dono (tunel HTTPS, sandbox, Pix/cartao, preapproval mensal/anual,
  cancelamento, falha, estorno, replay, doacao, fallback) esta no
  `docs/acceptance/FASE-6.md` como criterio 27. Nenhum token foi impresso ou
  gravado.
- Cleanup: `storage/smoke_f6.sqlite` removido; `php artisan config:clear`
  executado; nenhum commit ou push.
