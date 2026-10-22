# ADR-050: API publica v1 - Sanctum, ability, cota do plano e rate limit por token

Status: Aceito
Data: 2026-10-22
Relacionados: ADR-040 (ciclo da solicitacao), ADR-042 (contrato HTTP da IA), ADR-008 (abuso e custo), ADR-012 (planos e cota), ADR-015 (privacidade do schema)

## Contexto

A F5 entregou o AI SQL Builder servido ao navegador com sessao e CSRF
(ADR-042), deixando a API publica para a F7. Havia tres desenhos possiveis:

1. **reaproveitar `/api/ai/*` com sessao** e pedir que integracoes externas
   simulassem um login;
2. **chave de API propria**, com tabela e middleware de autenticacao;
3. **Personal Access Token do Sanctum**, com ability e escopo por token.

A primeira opcao obrigaria um cliente de servidor a guardar cookie de sessao e
token CSRF, e a expor a API a CSRF sem necessidade. A segunda repetiria o que o
Sanctum ja entrega (hash do token, abilities, `last_used_at`, revogacao) e
precisaria de manutencao propria. O projeto nao tinha Sanctum instalado e nao
usa sessao nessa superficie.

Tambem era preciso decidir se a API teria cota paralela e como o cliente
acompanharia o resultado, que so existe alguns segundos depois.

## Decisao

### 1. Sanctum com Personal Access Token e ability `ai:query`

- `laravel/sanctum` com `stateful => []`: a superficie `/api/v1` e token-only,
  sem sessao, cookie ou CSRF. O guard continua `web`, mas sem sessao nao ha o
  que autenticar por cookie.
- Toda rota exige `auth:sanctum`, `active`, `verified` e `abilities:ai:query`.
  Token sem a ability responde `403 forbidden`; token revogado deixa de
  autenticar no pedido seguinte.
- O token e criado em `Configuracoes > Tokens de API`, nao por endpoint. O
  texto puro aparece **uma vez**, no flash do POST; o banco so guarda o hash.
  Revogar e definitivo.

### 2. Contrato assincrono reutilizado

A API nao inventa um segundo ciclo: ela usa `StartAiQuery`, `StoreAiQueryRequest`,
`Quota`, `AiError` e o mesmo `AiQueryResource`.

| Metodo | Rota | Resposta |
| --- | --- | --- |
| POST | `/api/v1/sql-queries` | `200` em cache hit, `202` na fila |
| GET | `/api/v1/sql-queries/{ulid}` | estado/resultado em qualquer estado |
| GET | `/api/v1/sql-queries` | lista paginada, filtro `status` |
| GET | `/api/v1/me` | conta, plano e cota |
| GET | `/api/v1/output-targets` | alvos `enabled` |

O `POST` devolve `poll_url` absoluto e `created_at`; `poll_url` e nulo em
`done`/`failed`. A lista e o `show` so enxergam consultas criadas **pelo mesmo
token** (`ai_requests.api_token_id`), e nao apenas pela mesma conta: dois tokens
sao dois clientes. Consulta feita pela tela tem `api_token_id` nulo e responde
404 na API.

### 3. Mesma cota do plano, sem cota paralela

O debito continua no `usage_ledger`, na unidade do plano (`uses` no Free,
`tokens` nos pagos). Cache hit e deduplicacao nao debitam; cota esgotada
responde `402` com `details.unit` e `details.required` antes de enfileirar.

### 4. Rate limit proprio, por token

A API tem limites diferentes da tela porque o padrao de chamada e outro:

| Rota | Padrao | Config |
| --- | --- | --- |
| POST | 30/min e 300/h por token | `API_RATE_POST_PER_MINUTE`, `API_RATE_POST_PER_HOUR` |
| Leitura | 120/min por token | `API_RATE_READ_PER_MINUTE` |

Os limiters (`api-sql-post`, `api-read`) chaveiam pelo id do token, com IP como
fallback. O `429` preserva `Retry-After` no envelope.

### 5. CORS fechado por padrao e documentacao sem pagina publica

- `config/cors.php` cobre `api/*`, com `API_ALLOWED_ORIGINS` vazio por padrao e
  `supports_credentials => false`. Cliente servidor-a-servidor nao depende de
  CORS; navegador de outro dominio so le se o dono liberar a origem.
- A documentacao vive em `docs/API.md` e `docs/openapi.yaml`. Nao ha pagina
  `/docs` publica: o contrato e para quem integra, nao uma superficie de SEO.

## Consequencias

### Positivas

- Um unico mecanismo de token, com hash, ability, `last_used_at` e revogacao
  pronta; a anonimizacao da conta revoga todos os tokens.
- A API nao herda CSRF nem sessao: nao ha cookie para vazar e o cliente nao
  precisa simular login.
- Cota e contabilidade continuam num lugar so (`usage_ledger`); a API nao cria
  uma segunda fonte de verdade.
- O escopo por token impede que uma integracao leia o historico de outra, mesmo
  na mesma conta.
- O envelope de erro e o mesmo da tela, entao `code`, `quota` e `poll_url` sao
  contrato unico.

### Negativas / trade-offs

- Sanctum entra no `composer.json`; o deploy precisa rodar `composer install`
  quando o hash muda (comportamento ja documentado no RUNBOOK).
- Dois rate limits para manter: o da tela (por conta/IP) e o da API (por token).
- O token em texto puro so existe uma vez; quem perde precisa revogar e criar
  outro.
- A API nao tem cancelamento de solicitacao em voo, igual a tela.

## Alternativas descartadas

- **Sessao na API**: obrigaria cookie e CSRF em integracao de servidor, e
  exporia a API a CSRF sem ganho.
- **Chave propria**: repetiria Sanctum com mais codigo e sem abilities.
- **Cota separada para API**: duas contabilidades, dois lugares para errar.
- **Rate limit unico com a tela**: o consumo de um script nao pode derrubar o
  uso da interface.
- **Pagina `/docs` publica**: superficie extra de SEO e de manutencao para um
  contrato que ja tem OpenAPI no repositorio.
