# API - AI SQL Builder

A unica API de servidor do site hoje e a do AI SQL Builder (ADR-042). Ela existe
porque esta tool depende do servidor (ADR-002): o navegador manda o schema e a
descricao, o servidor fala com o provedor de IA fora do request HTTP e a tela
consulta o resultado.

Nada do que esta aqui e API publica de terceiros: as rotas usam a **sessao do
site** e o **CSRF** do grupo `web`, e nao token de API. Endpoints publicos
continuam fora de escopo ate a F7.

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
