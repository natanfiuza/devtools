# ADR-042: Contrato HTTP da IA - sessao, CSRF, polling e erros

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-002 (excecao de servidor), ADR-008 (abuso e custo), ADR-019 (filas), ADR-040 (ciclo da solicitacao)
Emendado por: ADR-050 (API publica v1 como superficie separada, com Sanctum)

## Contexto

O navegador precisa enviar schema e descricao e receber um resultado que so
existe alguns segundos depois. Havia quatro desenhos possiveis:

1. **API publica com token** (sanctum ou chave por conta);
2. **rota do grupo `web`, com sessao e CSRF**, como qualquer formulario do
   site;
3. **request sincrono** que segura a conexao ate o provedor responder;
4. **WebSocket/SSE** para empurrar o resultado.

O site inteiro ja usa sessao (Inertia), nao tem API publica (ela esta prevista
para a F7) e a tool so pode ser usada por conta verificada e ativa. Um token
proprio criaria um segundo sistema de autenticacao, com revogacao, rotacao e
armazenamento no cliente, para uma API que so o proprio site consome.

## Decisao

### 1. Rota do grupo `web`, sem token

As rotas vivem em `routes/web.php`, sem prefixo de idioma e sob o grupo
`auth`, `verified` e `active`:

```text
POST /api/ai/queries          CreateAiQuery
GET  /api/ai/queries/{ulid}   ShowAiQuery
```

- Autenticacao: o **cookie de sessao** do site; o cliente usa
  `credentials: same-origin`.
- CSRF: `POST` exige o token do grupo `web`. O cliente le o cookie
  `XSRF-TOKEN`, aplica `decodeURIComponent` **uma vez** e envia em
  `X-XSRF-TOKEN`; o `419` continua sendo tratado explicitamente.
- Formato: JSON em tudo; o cliente manda `Accept: application/json` e
  `X-Requested-With: XMLHttpRequest`, que e o que faz o Laravel responder 401
  JSON em vez de redirecionar para o login.
- Dono: sempre `user_id`. Solicitacao de outra conta responde **404**, nao 403
  - o 404 nao confirma que o ULID existe.
- `whereUlid('aiRequest')` recusa id malformado antes de chegar ao banco.

### 2. Dois status de sucesso

- **200 OK**: o cache respondeu na hora; a solicitacao ja nasceu `done` e o
  `result` vem no corpo.
- **202 Accepted**: a solicitacao esta na fila **ou** foi deduplicada para uma
  que ja estava em voo; o cliente acompanha pelo `id`.

O `GET` responde **200 em qualquer estado** (`queued`, `processing`, `done`,
`failed`), porque a tela quer desenhar o estado, e nao tratar erro. O
`poll_after_ms` (1500 ms) e dica do servidor; o cliente dobra o atraso ate
5000 ms e desiste em 120 s, com botao para consultar de novo - desistir de
esperar nao cancela a solicitacao no servidor.

### 3. Envelope unico

Sucesso:

```json
{ "data": { "id": "...", "status": "queued", "cache_hit": false,
            "result": null, "tokens": { "in": null, "out": null },
            "poll_after_ms": 1500, "quota": {}, "error_code": null } }
```

Erro:

```json
{ "error": { "code": "quota_exceeded", "message": "...", "details": {} } }
```

`code` e o contrato que a tela traduz (`ai.errors.<code>`); `message` e
fallback tecnico em ingles, e **nao** deve ser exibida crua. `details` e
opcional. O corpo do provedor nunca entra aqui.

### 4. Status e codigos

| Status | `code` | Causa |
| ------ | ------ | ----- |
| 401 | `unauthenticated` | sem sessao |
| 403 | `forbidden` | e-mail nao verificado |
| 403 | `account_suspended` | conta suspensa (a sessao cai no mesmo request) |
| 404 | `not_found` | solicitacao inexistente/de outra conta, alvo desligado |
| 419 | `csrf_token_mismatch` | CSRF ausente/expirado |
| 422 | `validation_error`, `schema_too_large`, `description_too_long`, `unsupported_dialect` | forma ou limite |
| 402 | `quota_exceeded` | cota do plano |
| 429 | `rate_limited` | limite de taxa, com `Retry-After` |
| 503 | `ai_unavailable` | provedor nao configurado |

Depois do enfileiramento, a falha vira `status: "failed"` com um `error_code`
(`provider_unavailable`, `invalid_response`, `unsafe_sql`, `invalid_contract`,
`timeout`, `internal`) - o POST ja respondeu 202, entao nao ha como mudar o
status dele.

### 5. Rate limit sem Turnstile

O limite protege custo e abuso sem CAPTCHA, que atrapalharia quem usa a tool de
verdade:

| Rota | Limite |
| ---- | ------ |
| POST | 6/min e 60/h por conta; 30/min por IP |
| GET (polling) | 120/min por conta |

Os numeros vivem em `config/ai.php` (`AI_RATE_*`), mudam por `.env` e sao
aplicados por rate limiters nomeados; o 429 traz `Retry-After`.

## Consequencias

### Positivas

- Um sistema de autenticacao so: nao ha token para vazar, revogar ou guardar no
  `localStorage`.
- CSRF continua valendo para a operacao que gasta dinheiro.
- O formato `data`/`error` deixa a tela simples: ela le `data.status` e
  `ai.errors.<code>`, sem interpretar mensagem.
- O 404 para solicitacao alheia nao vaza existencia de ids.
- O polling e stateless e sobrevive a recarga da pagina (basta guardar o ULID).

### Negativas / trade-offs

- Esta API nao serve terceiros; a API publica com token e uma superficie
  separada (`/api/v1`, ADR-050), com contrato assincrono equivalente.
- Sem SSE/WebSocket: a resposta tem ate 1,5-5 s de atraso no pior caso, e a
  tela precisa do timeout de 120 s.
- O 419 depende de o cliente ler o cookie cru e decodificar uma vez; errar isso
  (decodificar duas vezes) e uma fonte classica de bug, coberta por teste.
- Rate limit por IP atras de proxy exige `TrustProxies` configurado no VPS para
  nao contar todo mundo como o mesmo IP.

## Alternativas descartadas

- **Sanctum/API token**: segundo sistema de autenticacao para um cliente unico
  (o proprio site), com custo de seguranca e de UX sem ganho.
- **Request sincrono**: o provedor pode levar 60 s; prenderia o PHP-FPM e o
  usuario veria timeout do nginx antes da resposta.
- **SSE/WebSocket**: infraestrutura nova (servidor de eventos, proxy, reconexao)
  para um resultado que aparece em segundos; o polling cabe na F5.
- **Turnstile/CAPTCHA**: atrito para usuario legitimo; rate limit por conta e
  IP resolve o abuso previsivel nesta escala.
- **403 para solicitacao alheia**: confirmaria que o id existe.
