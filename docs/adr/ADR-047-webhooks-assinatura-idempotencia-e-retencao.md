# ADR-047: Webhooks com assinatura, idempotencia e busca no provedor antes de agir

Status: Aceito
Data: 2026-10-21
Relaciona: ADR-017 (PaymentGateway), ADR-019 (filas), ADR-040 (reserva e liquidacao), ADR-045 (Checkout Pro e Preapproval), ADR-046 (periodos)

## Contexto

A Mercado Pago avisa o resultado de um checkout, de uma preapproval e de cada cobranca recorrente por notificacao HTTP. O corpo da notificacao **nao e** o pagamento: e um envelope que aponta para um recurso (`data.id`), e pode chegar repetido, atrasado, fora de ordem ou forjado. Nada no projeto recebia webhook ate a F6.

Tres erros seriam graves:

- **confiar no corpo**: um requisitante poderia dizer "pagamento aprovado de R$ 500" sem dinheiro;
- **processar no request**: a API do provedor pode demorar; se o request estoura o tempo e o provedor reenvia, o efeito acontece duas vezes;
- **reprocessar replay**: o mesmo aviso liberaria o mesmo periodo ou a mesma doacao duas vezes.

## Decisao

1. **Rota fora do app**: `POST /webhooks/mercadopago` (`webhooks.mercadopago`), sem `auth`, sem locale e isenta de CSRF (`bootstrap/app.php`). O provedor nao tem sessao nem cookie.
2. **Assinatura obrigatoria**: `x-signature` (`ts=<ts>,v1=<hmac>`) + `x-request-id` + `data.id`. O manifesto e exatamente `id:<data.id em minusculas>;request-id:<x-request-id>;ts:<ts>;`, HMAC-SHA256 com `MERCADO_PAGO_WEBHOOK_SECRET` e comparacao em `hash_equals`. Sem segredo configurado: **503** (o recebimento esta desligado, nao "inválido"); assinatura ausente ou invalida: **401**. Nao ha janela de validade de timestamp: replay e contido pela chave unica do evento e pela idempotencia das acoes, e um limite de tempo quebraria retries legitimos do provedor.
3. **Persistir antes de agir**: o payload completo vai para `webhook_events`, com unique `(gateway, event_id)`. O request responde **201** com o evento guardado, **200** para duplicata (`UniqueConstraintViolationException`) e **200** para corpo que nao da para processar; so entao `ProcessWebhookEvent::dispatch($id)->afterCommit()`.
4. **Corpo nunca e verdade**: o controller usa apenas `data.id` e o topico. O job chama `GET /v1/payments/{id}`, `GET /preapproval/{id}` ou `GET /authorized_payments/{id}` e age sobre a resposta da API. Se o recurso nao existe ou diverge, a acao para.
5. **Topicos conhecidos**: `payment`, `subscription_preapproval`, `subscription_authorized_payment`. Qualquer outro vira `ignored`, sem efeito, para o catalogo nao crescer por acidente. O topico tambem e lido de `type` ou `topic`, porque o provedor usa os dois formatos.
6. **Job com retry e estado**: `ProcessWebhookEvent` marca `processing`, incrementa `attempts`, tem 3 tentativas com backoff 10/60/300 s e timeout de 60 s. Falha grava `failed` com a mensagem (sem corpo de resposta nem segredo) e re-lanca para a fila tentar; sucesso grava `processed` e `processed_at`. Evento ja `processed`/`ignored` nao roda de novo.
7. **Acoes idempotentes por referencia**: aprovar um pagamento grava `payments` com unique `(gateway, gateway_ref)`; abrir periodo checa a linha existente antes de criar; confirmar doacao so transita `pending -> paid`; estornar so age em pagamento ainda nao estornado. Reprocessar o mesmo evento, ou receber `payment` e `subscription_authorized_payment` da mesma cobranca, nao duplica nada.
8. **Retencao de 30 dias**: o payload cru serve para auditoria, depuracao e reprocessamento nesse periodo. `webhooks:prune {--days=30} {--dry-run}` apaga **somente** `webhook_events`; `payments`, `subscriptions` e `donations` nunca sao tocados pela poda. `--days` tem piso de 1 para que um `0` acidental nao apague tudo.
9. **Leitura no admin**: `admin/webhooks` lista topicos, identificadores, status, tentativas, erro e o payload completo, com filtros. E **somente leitura**: nao existe botao de reprocessar. Reprocessar seria uma segunda porta para liberar acesso; a unica porta e o job, e o evento com falha pode ser reenviado pelo provedor.
10. **Sem segredo nao ha webhook**: sem `MERCADO_PAGO_WEBHOOK_SECRET` o endpoint responde 503 e os caminhos online nem aparecem. Nenhuma notificacao e aceita sem verificacao.

## Por que

- **Assinatura antes de tudo**: sem ela, qualquer um liberaria plano chamando a rota; e o primeiro filtro, e nao uma checagem dentro do job.
- **Responder rapido e processar na fila**: o provedor tem timeout curto e reenvia o que nao recebe 2xx; guardar e enfileirar mantem a resposta em milissegundos e o trabalho pesado fora do request.
- **Fetch-before-fulfill**: o corpo diz "aconteceu um pagamento", nao "o pagamento de R$ X foi aprovado". So a API sabe valor, moeda e estado; validar contra o que foi criado impede liberar acesso por um envelope adulterado ou desatualizado.
- **Idempotencia em duas camadas**: a chave do evento barra replay exato; as referencias (`chk_`, `don_`, `gateway_ref`) barram o mesmo fato chegando por dois caminhos. Uma camada so deixaria brecha para o caso classico `payment` + `subscription_authorized_payment`.
- **Retencao curta**: payload de webhook tem dado pessoal e cresce rapido; 30 dias cobrem a janela de investigacao de uma cobranca. O registro fiscal (`payments`) e o periodo (`subscriptions`) ficam para sempre porque sao a historia financeira.
- **Sem janela de timestamp**: retry do provedor pode chegar minutos depois e precisa valer; replay nao e bloqueado por relogio, e sim por unique e idempotencia.
- **Admin so le**: investigar e uma coisa, conceder e outra; misturar as duas criaria um caminho de acesso que nao passa pelo pagamento.

## Consequencias

- `webhook_events` guarda o payload de toda notificacao autenticada; a tabela precisa da poda diaria (agendada em `task_schedules` as 03:30).
- Toda acao de webhook depende da API do provedor estar respondendo; falha transitoria vira retry, falha persistente fica `failed` e visivel no admin.
- Um evento `failed` nao e reprocessado por botao: o provedor reenvia, ou o dono investiga pelo payload e pelo runbook.
- A rota e publica e isenta de CSRF; a superficie e a assinatura e o rate limit do proprio provedor. Nao ha throttle nosso nela, porque o provedor reenvia em rajada e um 429 faria perder notificacao legitima.
- O topico desconhecido fica `ignored` em vez de erro: novos topicos do provedor nao viram ruido em producao.
- Testes de webhook assinam o corpo com o segredo de teste, cobrem replay, assinatura ausente/invalida, fetch obrigatorio, cada topico e a poda.

## Alternativas descartadas

- **Processar no controller**: simples, mas acopla o tempo de resposta ao provedor e abre a janela de efeito duplicado no retry.
- **Confiar no corpo do webhook**: dispensaria a chamada de API, mas valor, moeda e estado viriam de fora; um envelope forjado liberaria plano.
- **Evento sem payload guardado**: menos dado sensivel, mas sem auditoria e sem como explicar ao suporte o que exatamente chegou.
- **Botao de reprocessar no admin**: conveniente para operacao, mas seria uma segunda porta de acesso; o reenvio do provedor e o caminho.
- **Janela de 5 minutos no timestamp**: bloquearia replay antigo, mas derrubaria retries legitimos e exigiria relogio sincronizado; a idempotencia ja resolve.
- **Retencao indefinida**: cresce sem limite e guarda dado pessoal sem finalidade; 30 dias cobrem a janela util.
- **Throttle proprio na rota**: o provedor pode notificar em rajada; um 429 nosso perderia a notificacao e o reenvio viria junto com o throttled.
