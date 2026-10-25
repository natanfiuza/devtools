# API

O site tem duas superficies HTTP de saida, alem do webhook de entrada:

| Superficie | Prefixo | Autenticacao | Quem usa |
| --- | --- | --- | --- |
| API publica v1 | `/api/v1` | Bearer token (Sanctum PAT) | integracoes de terceiros e scripts do cliente |
| API do navegador | `/api/ai` | sessao do site + CSRF | a tela do AI SQL Builder (ADR-042) |
| Webhook da Mercado Pago | `/webhooks/mercadopago` | assinatura HMAC do provedor (ADR-047) | o provedor de pagamento |

As tres respondem JSON. Sucesso vem em `data`; erro vem em `error`, com um
`code` estavel que o cliente programa. Schema e descricao nunca entram em log,
em nenhuma das superficies.

## API publica v1

A API publica permite que um sistema externo use o AI SQL Builder sem o
navegador. Ela e assincrona como a tela: o `POST` cria a solicitacao (ou
responde do cache) e o cliente acompanha pelo `poll_url`.

Decisao completa na ADR-050.

### Autenticacao

1. Na tela **Configuracoes > Tokens de API** (`/settings/api-tokens`), crie um
   token com um nome que identifique a integracao.
2. Copie o valor na hora: ele aparece **uma unica vez**. Depois disso o banco
   so guarda o hash.
3. Envie o valor em `Authorization: Bearer <token>` em toda requisicao.

O token e um Personal Access Token do Sanctum com a ability `ai:query`. Sem
token a resposta e `401`; com token de outra ability, `403`. Um token revogado
deixa de autenticar no pedido seguinte. A revogacao tambem acontece quando a
conta e anonimizada.

Nao ha sessao, cookie nem CSRF nessa superficie; nao envie `X-XSRF-TOKEN`.
Tambem nao ha CORS aberto: `API_ALLOWED_ORIGINS` vazio (o padrao) significa que
navegador de outro dominio nao le a resposta. Chamadas servidor-a-servidor nao
dependem de CORS.

### Endpoints

A base e a URL do site mais `/api/v1`.

| Metodo | Rota | O que faz |
| --- | --- | --- |
| `GET` | `/me` | conta, plano e cota do dono do token |
| `GET` | `/output-targets` | alvos habilitados, com slug, rotulo, linguagem e dialetos |
| `POST` | `/sql-queries` | cria uma consulta: `200` em cache hit, `202` na fila |
| `GET` | `/sql-queries` | lista paginada das consultas da conta |
| `GET` | `/sql-queries/{ulid}` | estado e resultado de uma consulta da conta |

Cabecalhos esperados:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

### GET /api/v1/me

```json
{
    "data": {
        "id": 42,
        "name": "Ana Souza",
        "email": "ana@exemplo.com",
        "locale": "pt_BR",
        "plan": {
            "slug": "pro-monthly",
            "name": "Pro mensal",
            "is_free": false
        },
        "quota": {
            "unit": "tokens",
            "limit": 2000000,
            "used": 12000,
            "remaining": 1988000,
            "exhausted": false,
            "window": {
                "start": "2026-10-01T00:00:00-03:00",
                "end": "2026-11-01T00:00:00-03:00"
            },
            "next_credit_at": null
        }
    }
}
```

No Free a unidade e `uses` e a janela vem nula (`limit` e vitalicio). O papel
da conta nao aparece na resposta.

### GET /api/v1/output-targets

```json
{
    "data": [
        {
            "slug": "sql",
            "label": "SQL",
            "language": "sql",
            "supported_dialects": ["mysql", "pgsql", "sqlsrv", "sqlite"]
        }
    ]
}
```

So alvos com `enabled = true`. O `prompt_template` e o plano minimo nao saem.
Use o `slug` em `output_target` no `POST`.

### POST /api/v1/sql-queries

O corpo e o mesmo da API do navegador (secao seguinte): `schema_source`,
`dialect`, `output_target`, `schema` e `description`, com os mesmos limites de
plano e as mesmas mensagens de validacao.

**200 OK** - cache hit: a solicitacao nasceu `done`, `cache_hit = true`, sem
debito de cota.

**202 Accepted** - nova ou deduplicada para uma que ja estava em voo. Acompanhe
pelo `poll_url`.

```json
{
    "data": {
        "id": "01JCX9Y8Z1Q2W3E4R5T6Y7U8I9",
        "status": "queued",
        "cache_hit": false,
        "result": null,
        "tokens": { "in": null, "out": null },
        "poll_after_ms": 1500,
        "quota": {
            "unit": "uses",
            "limit": 5,
            "used": 1,
            "remaining": 4,
            "exhausted": false,
            "window": { "start": null, "end": null }
        },
        "error_code": null,
        "output_target": "sql",
        "poll_url": "https://devtools.natanfiuza.dev.br/api/v1/sql-queries/01JCX9Y8Z1Q2W3E4R5T6Y7U8I9",
        "created_at": "2026-10-22T10:15:00-03:00"
    }
}
```

- `poll_url` e absoluto e nulo em `done`/`failed`: e o sinal para parar de
  consultar.
- `output_target` repete o alvo pedido.
- `result` tem o mesmo contrato do LLM documentado abaixo.

### GET /api/v1/sql-queries

Lista as consultas criadas pela conta dona do token (tanto pela API quanto pela
tela web do AI SQL Builder), da mais nova para a mais antiga. Consultas de
outra conta nao aparecem.

| Parametro | Padrao | Regra |
| --- | --- | --- |
| `status` | vazio | `queued`, `processing`, `done` ou `failed`; outro valor e `422` |
| `page` | 1 | pagina da lista |

O tamanho da pagina vem de `API_PER_PAGE` (20) e e limitado a 100 pelo servidor.

```json
{
    "data": [ { "id": "...", "status": "done", "result": {} } ],
    "meta": { "current_page": 1, "per_page": 20, "total": 1, "last_page": 1 },
    "links": { "next": null, "previous": null },
    "quota": { "unit": "uses", "limit": 5, "used": 1, "remaining": 4, "exhausted": false, "window": { "start": null, "end": null } }
}
```

### GET /api/v1/sql-queries/{ulid}

Responde `200` em qualquer estado, com o mesmo objeto do `POST`. O `poll_url`
some (`null`) quando a solicitacao termina, inclusive em `failed` - nesse caso
leia `error_code`.

Um ULID inexistente ou de outra conta responde `404`: o 404 nao confirma que o
id existe.

### Cota

A cota e a do plano, a mesma da tela; nao existe cota paralela para a API.

- Free: `uses` vitalicios; cada solicitacao que chega ao provedor debita 1.
- Planos pagos: `tokens` acumulativos (2.000.000 de tokens creditados a cada mes iniciado de assinatura paga nao estornada, mais concessoes manuais; no plano anual `next_credit_at` informa a data do proximo lote mensal dentro do ano).
- Cache hit nao debita. Solicitacao deduplicada nao debita de novo.
- Cota esgotada responde `402` com `details.unit` e `details.required`, antes
  de enfileirar qualquer coisa.

O estado atual pode ser lido a qualquer momento em `GET /me` ou no campo
`quota` de qualquer resposta de consulta.

### Limites de taxa

Os limites sao **por token** e contam as duas rotas separadamente:

| Rota | Limite padrao | Variavel |
| --- | --- | --- |
| `POST /sql-queries` | 30/min e 300/h | `API_RATE_POST_PER_MINUTE`, `API_RATE_POST_PER_HOUR` |
| Leitura (`GET`) | 120/min | `API_RATE_READ_PER_MINUTE` |

O `429` traz `Retry-After` em segundos e o `code` `rate_limited`. Os numeros sao
de `config/api.php` e mudam por `.env`, sem deploy.

### Erros

| Status | `code` | Quando |
| --- | --- | --- |
| 401 | `unauthenticated` | token ausente, invalido ou revogado |
| 403 | `forbidden` | token sem a ability `ai:query` |
| 403 | `account_suspended` | conta suspensa, mesmo com token valido |
| 404 | `not_found` | consulta inexistente ou de outro token; alvo desligado no `POST` |
| 422 | `validation_error` | corpo invalido (`details.errors`) |
| 422 | `schema_too_large`, `description_too_long`, `unsupported_dialect` | limites e dialetos do plano/alvo |
| 402 | `quota_exceeded` | cota do plano esgotada (`details.unit`, `details.required`) |
| 429 | `rate_limited` | limite de taxa, com `Retry-After` |
| 503 | `ai_unavailable` | provedor de IA nao configurado |

`419` nao existe nessa superficie: nao ha CSRF. O envelope e o mesmo da API do
navegador (abaixo), e o corpo do provedor nunca aparece na resposta.

### Versionamento e depreciacao

- A versao mora no caminho (`/api/v1`). Mudanca compativel - campo novo,
  endpoint novo - continua em `v1`; o cliente deve ignorar campos que nao
  conhece.
- Mudanca que quebra o contrato (campo removido ou com outro significado,
  status diferente para o mesmo caso) exige `/api/v2`. A `v1` continua
  respondendo durante a janela de migracao, anunciada no `CHANGELOG.md` e
  neste documento.
- `code` de erro e status HTTP sao o contrato estavel; `message` e fallback
  tecnico e pode mudar sem versao.

### Exemplo (curl)

```bash
BASE="https://seu-dominio.com.br/api/v1"
TOKEN="cole-o-token-criado-na-tela"

# 1. Cria a consulta
curl -sS -X POST "$BASE/sql-queries" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -d '{
        "schema_source": "ddl",
        "dialect": "mysql",
        "output_target": "sql",
        "schema": "CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));",
        "description": "Liste os usuarios ativos ordenados por nome."
    }'

# 2. Acompanha ate o poll_url virar null (done ou failed)
curl -sS "$BASE/sql-queries/<ulid>" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json"
```

O `202` pode ser seguido imediatamente; respeite `poll_after_ms` como primeiro
atraso e aplique backoff proprio depois. Desistir de esperar nao cancela a
solicitacao no servidor.

## API do navegador (AI SQL Builder)

A API do navegador existe porque esta tool depende do servidor (ADR-002): o
navegador manda o schema e a descricao, o servidor fala com o provedor de IA
fora do request HTTP e a tela consulta o resultado. Ela usa a **sessao do site**
e o **CSRF** do grupo `web`; nao aceita token de API.

- Rotas: `POST /api/ai/queries` e `GET /api/ai/queries/{ulid}`.
- Formato: JSON em tudo; o cliente manda `Accept: application/json` e
  `X-Requested-With: XMLHttpRequest`.
- Autenticacao: cookie de sessao do Laravel (`credentials: same-origin`).
- CSRF: o token vai no cabecalho `X-XSRF-TOKEN`, lido do cookie `XSRF-TOKEN`
  (decodificado **uma** vez, como o navegador grava).
- Idioma: a API nao tem prefixo de idioma; o texto de interface e traduzido na
  tela por `ai.*`, a partir do `code` do erro.

## POST /api/ai/queries

Cria uma solicitacao (ou reaproveita uma igual) e devolve o estado da conta.

### Corpo

```json
{
    "schema_source": "ddl",
    "dialect": "mysql",
    "output_target": "sql",
    "schema": "CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120));",
    "description": "Liste os usuarios com mais de 18 anos, ordenados por nome."
}
```

| Campo           | Tipo   | Regra                                                                                              |
| --------------- | ------ | -------------------------------------------------------------------------------------------------- |
| `schema_source` | string | `ddl`, `json`, `migration` ou `models` (enum `SchemaSource`).                                      |
| `dialect`       | string | `mysql`, `pgsql`, `sqlsrv` ou `sqlite` (enum `SqlDialect`); precisa estar em `supported_dialects` do alvo. |
| `output_target` | string | Slug de `output_targets` com `enabled = true`. Os nove da carga inicial estao na secao 6 do SPEC.   |
| `schema`        | string | Obrigatorio. O limite efetivo e o menor entre `AI_MAX_SCHEMA_BYTES` (64 KB) e `plans.max_schema_bytes` (8 KB no Free). |
| `description`   | string | Obrigatorio. O limite efetivo e o menor entre `AI_MAX_DESCRIPTION_CHARS` (4000) e `plans.max_prompt_chars` (500 no Free). |

O schema e a descricao **nunca** sao gravados em log. O limite de tamanho do
schema e contado em bytes; o da descricao, em caracteres.

### Respostas

**200 OK** - cache hit: a resposta ja existia e a solicitacao nasceu `done`,
**sem debito** de cota.

**202 Accepted** - solicitacao nova na fila, ou deduplicada para uma que ja
estava em voo (mesma conta, mesmo `prompt_hash`, dentro de 600 s). Nos dois
casos, acompanhe por `GET /api/ai/queries/{id}`.

```json
{
    "data": {
        "id": "01JCX9Y8Z1Q2W3E4R5T6Y7U8I9",
        "status": "queued",
        "cache_hit": false,
        "result": null,
        "tokens": { "in": null, "out": null },
        "poll_after_ms": 1500,
        "quota": {
            "unit": "uses",
            "limit": 5,
            "used": 1,
            "remaining": 4,
            "exhausted": false,
            "window": { "start": null, "end": null }
        },
        "error_code": null
    }
}
```

- `id`: ULID da solicitacao (26 caracteres). Use este valor no polling.
- `status`: `queued`, `processing`, `done` ou `failed`.
- `cache_hit`: `true` quando a resposta veio do cache - no POST ou no job.
- `result`: o contrato do LLM (abaixo) quando `done`; `null` enquanto nao
  terminar e em `failed`.
- `tokens`: `in`/`out` reais depois da liquidacao; `null` antes dela.
- `poll_after_ms`: primeiro atraso sugerido pelo servidor; `null` em estado
  terminal.
- `quota`: estado **atual** da conta na leitura. No Free a unidade e `uses`
  (limite vitalicio); nos planos pagos e `tokens`, com a janela da assinatura.
- `error_code`: um dos codigos de falha (abaixo) quando `failed`.

### Contrato de `result`

```json
{
    "sql": "SELECT id, name FROM users WHERE age > 18 ORDER BY name",
    "dialect": "mysql",
    "assumptions": ["A coluna age existe em users."],
    "warnings": [],
    "tables_used": ["users"],
    "needs_clarification": false,
    "clarification": null,
    "outputs": {
        "sql": "SELECT id, name FROM users WHERE age > 18 ORDER BY name"
    }
}
```

- `outputs[output_target]` e obrigatorio quando `needs_clarification` e `false`.
- `needs_clarification = true` vem com `sql` vazio e `clarification`
  preenchida: o modelo precisa de mais informacao. O estado e `done` e a
  reserva **e consumida** (o trabalho foi feito), como qualquer resposta.
- `dialect` tem de ser o dialeto pedido; qualquer outra forma vira
  `invalid_contract` e a solicitacao termina `failed` com estorno.

## GET /api/ai/queries/{ulid}

Devolve a solicitacao da conta autenticada. Responde **200 em qualquer estado**
(o status e o dado que a tela espera), com o mesmo envelope do POST.

- `404` quando o ULID nao existe **ou** pertence a outra conta: o dono e sempre
  `user_id`, e o 404 nao confirma que o id existe.
- `poll_after_ms` sugere 1500 ms enquanto a solicitacao nao termina; a tela
  dobra o atraso ate 5000 ms e desiste em 120 s, com botao para consultar de
  novo.

## Status HTTP e codigos de erro

Todo erro sai no mesmo envelope:

```json
{
    "error": {
        "code": "quota_exceeded",
        "message": "The AI quota is exhausted.",
        "details": { "unit": "uses", "required": 1 }
    }
}
```

`code` e o contrato que a tela traduz (`ai.errors.<code>`); `message` e apenas
fallback tecnico. `details` e opcional e ajuda a corrigir o pedido.

| Status | `code`                  | Quando                                                                 |
| ------ | ----------------------- | ---------------------------------------------------------------------- |
| 401    | `unauthenticated`       | Sem sessao.                                                            |
| 403    | `forbidden`             | E-mail nao verificado.                                                 |
| 403    | `account_suspended`     | Conta suspensa; a sessao e derrubada no mesmo request.                 |
| 404    | `not_found`             | Solicitacao inexistente ou de outra conta; alvo desligado no POST.     |
| 419    | `csrf_token_mismatch`   | Token CSRF ausente/expirado.                                           |
| 422    | `validation_error`      | Forma invalida (`details.errors`).                                     |
| 422    | `schema_too_large`      | Schema acima do limite do plano (ou do teto tecnico).                   |
| 422    | `description_too_long`  | Descricao acima do limite do plano.                                    |
| 422    | `unsupported_dialect`   | O alvo nao aceita aquele dialeto.                                      |
| 402    | `quota_exceeded`        | Cota esgotada; `details` traz `unit` e `required`.                     |
| 429    | `rate_limited`          | Limite de taxa; a resposta traz `Retry-After` em segundos.             |
| 503    | `ai_unavailable`        | `AI_BASE_URL`, `AI_MODEL` ou `AI_API_KEY` ausente.                     |

O 503 tambem aparece quando o provedor esta fora e a solicitacao ainda nao
existe: e melhor recusar do que enfileirar um job que so pode falhar. Falhas
que acontecem **depois** do enfileiramento nao mudam o status do POST: elas
chegam no polling como `status: "failed"` e `error_code`:

| `error_code`           | Causa                                                                 |
| ---------------------- | --------------------------------------------------------------------- |
| `provider_unavailable` | Provedor fora, chave invalida ou sem resposta valida apos as tentativas. |
| `invalid_response`     | Resposta do provedor sem o JSON esperado.                             |
| `unsafe_sql`           | O SQL do alvo `sql` nao passou no gate lexical (ADR-041).              |
| `invalid_contract`     | JSON fora do contrato (campo, dialeto, tamanho ou `outputs`).          |
| `timeout`              | Job estourou o tempo ou `ai:reconcile` achou a solicitacao presa.      |
| `internal`             | Falha inesperada (payload ilegivel, banco, bug).                       |

Em todos os `failed` a reserva e estornada integralmente; o corpo do provedor
nunca entra na resposta, no banco ou no log.

## Rate limit

| Rota   | Limite                                             |
| ------ | -------------------------------------------------- |
| POST   | 6/min e 60/h por conta, mais 30/min por IP.          |
| Polling | 120/min por conta.                                  |

O 429 traz `Retry-After`. Os numeros sao de `config/ai.php` e mudam por `.env`,
sem deploy.

## Exemplo de uso (cliente)

```ts
const csrf = decodeURIComponent(
    document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
);

const response = await fetch('/api/ai/queries', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrf,
    },
    body: JSON.stringify(input),
});

const { data } = await response.json();

// 200 -> data.result ja esta pronto; 202 -> acompanhar data.id.
```

O cliente de verdade esta em
`resources/js/tools/ai-sql-builder/api.ts`; a tela usa `postQuery` e `getQuery`,
e o envelope de erro vira `AiApiError` com `code`, `status`, `details` e
`retryAfterSeconds`.

## O que esta API nunca faz

- Nao executa o SQL gerado.
- Nao devolve o corpo cru do provedor.
- Nao grava schema, descricao ou prompt em log.
- Nao serve solicitacao de outra conta, nem para o mesmo e-mail.

## Webhook da Mercado Pago (entrada)

`POST /webhooks/mercadopago` - contrato de recebimento das notificacoes do
provedor (ADR-047). Fora do prefixo de idioma, sem `auth`, sem CSRF e sem
rate limit proprio: quem autentica e a assinatura, e o provedor reenvia em
rajada. O corpo **nao** e fonte de verdade; ele apenas aponta para o recurso.

### Cabecalhos

| Cabecalho | Uso |
| --- | --- |
| `x-signature` | `ts=<timestamp>,v1=<hmac sha256 hex>` |
| `x-request-id` | identificador da requisicao do provedor |
| `content-type` | `application/json` |

O manifesto assinado e exatamente
`id:<data.id em minusculas>;request-id:<x-request-id>;ts:<ts>;`, com
HMAC-SHA256 usando `MERCADO_PAGO_WEBHOOK_SECRET`. A comparacao e em tempo
constante (`hash_equals`). O `data.id` pode vir no corpo ou na query string.

### Corpo da notificacao

```json
{
    "id": 12345,
    "type": "payment",
    "action": "payment.updated",
    "data": { "id": "987654321" }
}
```

- `id`: identificador da notificacao; chave de idempotencia. Ausente, o
  servidor deriva um hash de `topic|data.id|request-id`.
- `type` (ou `topic`): `payment`, `subscription_preapproval`,
  `subscription_authorized_payment`. Qualquer outro vira `ignored`.
- `data.id`: recurso que sera buscado na API do provedor (`/v1/payments/{id}`,
  `/preapproval/{id}` ou `/authorized_payments/{id}`).

### Respostas do webhook

| Status | Quando | Efeito |
| --- | --- | --- |
| 201 | evento novo e assinatura valida | payload gravado em `webhook_events`, job enfileirado |
| 200 | evento ja recebido (`(gateway, event_id)` duplicado) | nada e reprocessado |
| 200 | topico sem efeito | evento guardado e marcado `ignored` pelo job |
| 401 | assinatura ausente ou invalida, ou `data.id` ausente | nada e gravado |
| 503 | `MERCADO_PAGO_WEBHOOK_SECRET` nao configurado | recebimento desligado |

O processamento acontece em `ProcessWebhookEvent` (3 tentativas, backoff
10/60/300 s, timeout 60 s). Falha persistente deixa o evento `failed` com a
mensagem (sem corpo de resposta nem segredo), visivel em `/admin/webhooks`.
O payload cru e retido por 30 dias (`webhooks:prune`); `payments`,
`subscriptions` e `donations` nunca sao apagados pela poda.

### Efeitos por topico

| Topico | Recurso | Efeito |
| --- | --- | --- |
| `payment` | `GET /v1/payments/{id}` | aprovado: confirma checkout avulso ou doacao; `refunded`/`charged_back`: sincroniza estorno local; pendente/recusado: registra sem liberar nada |
| `subscription_preapproval` | `GET /preapproval/{id}` | `authorized`: marca a sessao como autorizada e **nao** libera cota; `canceled`: encerra a renovacao |
| `subscription_authorized_payment` | `GET /authorized_payments/{id}` | aprovado: busca o pagamento, abre ou empilha o periodo; recusado: avisa a falha uma vez por periodo |

### Idempotencia

- Evento: unique `(gateway, event_id)`; replay responde 200 sem novo job.
- Pagamento: unique `(gateway, gateway_ref)`; o mesmo pagamento chegando por
  `payment` e por `subscription_authorized_payment` nao duplica linha.
- Doacao: so a transicao `pending -> paid` vale; recibo sai uma vez.
- Periodo: abrir periodo checa a linha do pagamento antes de criar.

Segredo, tokens e corpos de resposta do provedor nunca aparecem em log nem na
resposta do webhook.
