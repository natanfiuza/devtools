# Criterios de aceite - FASE 5 (AI SQL Builder)

**Aceite de codigo em 2026-10-20; revalidado e fechado em 2026-10-21.** A F5
entregou a nona tool e o unico backend do site (ADR-002): conta obrigatoria,
cota, fila, provedor de IA, cache e polling. O **smoke real com o DeepSeek**
foi executado em 2026-10-21 em banco dedicado, com HTTP, sessao, CSRF, fila e
worker reais; a secao "Revalidacao 2026-10-21" registra as evidencias e fecha o
criterio 20. O roteiro original permanece abaixo como registro historico.

Fonte: `docs/SPEC.md` secao 6, `docs/API.md`, `docs/ARQUITETURA.md` e as ADRs
039 a 043, com emendas nas ADRs 002, 004, 006, 011, 016, 029 e 030.

## Escopo entregue

Formulario com quatro fontes de schema, quatro dialetos e nove alvos de saida;
canonicalizacao e hash com comparacao de colisao; dedupe de 600 s; cache global
de 30 dias sem debito; cota reservada no POST e liquidada com o `usage` real;
job que leva so o ULID; cliente fino do DeepSeek em JSON mode; validador de
contrato; gate lexical do SQL; API de sessao com CSRF, throttles, polling e
envelope de erro; tela formulario | resultado com estados de bloqueio,
andamento, sucesso, falha e timeout; home, busca, sitemap, metas e OG da nona
tool; politica de privacidade reescrita nos cinco idiomas.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | A tool exige conta com e-mail verificado e conta ativa; visitante ve a pagina com CTA de login, sem 404 | `AiApiTest` (401/403), `AiToolPropsTest` (motivos na ordem `guest`, `email_unverified`, `account_suspended`), `ai-sql-builder.test.ts` (bloqueios) |
| 2 | Free tem 5 usos vitalicios; plano pago consome franquia mensal de tokens; o limite efetivo e o menor entre o teto tecnico e o do plano | `AiActionsTest` (debito Free, reserva paga, cota esgotada), `AiToolPropsTest` (limites e cota), `QuotaTest` |
| 3 | Entrada aceita as quatro fontes, os quatro dialetos e os nove alvos; alvo desligado some da tela e do POST | `AiToolPropsTest`, `AiApiTest` (422 de dialeto/alvo), `CanonicalizerTest` |
| 4 | POST valida forma, tamanho e dialeto com 422; cota com 402; rate limit com 429 e `Retry-After` | `AiApiTest`, `AiActionsTest`, rate limiters em `AiServiceProvider` |
| 5 | Payload canonicalizado (CRLF, espacos, trim) e `prompt_hash` com fonte, dialeto, alvo, schema, descricao, modelo e versao do prompt; hit so vale com payload identico | `CanonicalizerTest`, `AiCacheRepositoryTest` (colisao de hash vira miss), `PromptLoaderTest` |
| 6 | Dedupe devolve a solicitacao em voo (202) sem novo debito e sem novo job | `AiActionsTest` ("deduplica...") |
| 7 | Cache hit **nao debita**: no POST nasce `done` sem ledger; no job a reserva e estornada | `AiActionsTest` (cache hit, estorno no job), `ProcessAiQueryTest` |
| 8 | Reserva/liquidacao: pago ajusta para cima ou para baixo, Free nao ajusta, falha estorna tudo, repeticao nao reescreve | `AiActionsTest` (matriz completa), `ProcessAiQueryTest` |
| 9 | O provedor recebe JSON mode e a resposta nao vaza em excecao/log; erros sao classificados em retentaveis e nao retentaveis | `DeepSeekProviderTest`, `ProcessAiQueryTest` (log sem schema) |
| 10 | Contrato JSON valida os oito campos, dialeto pedido, `outputs[target]`, limites e `needs_clarification` | `ContractValidatorTest`, `AiApiTest` |
| 11 | Gate lexical recusa DDL/DML, `;` interno, `INTO`/`OUTFILE`, funcoes perigosas e `/*!` do MySQL; aceita `SELECT`/`WITH` com `;` final | `SqlGuardTest` |
| 12 | API responde 200 (cache) ou 202 (fila/dedupe); `show` responde 200 em qualquer estado; solicitacao de outra conta e 404; 401/419 corretos | `AiApiTest`, `ai-api.test.ts` |
| 13 | Rate limit do POST (6/min, 60/h por conta; 30/min por IP) e do polling (120/min) | `AiApiTest` (429), `AiServiceProvider` |
| 14 | Job leva so o ULID, respeita `tries=2`, `timeout=90` e backoff; `ai:prune-cache` e `ai:reconcile` tem `--dry-run` e cobrem cache expirado/worker morto | `ProcessAiQueryTest`, `AiCommandsTest`, `ai:prune-cache --dry-run`, `ai:reconcile --dry-run` |
| 15 | Tela: formulario | resultado, estados de bloqueio/andamento/sucesso/falha/timeout, copiar/baixar, premissas/avisos/tabelas, selo de cache e `needs_clarification`; polling 1,5 s -> 5 s, timeout 120 s | `ai-sql-builder.test.ts`, `ai-polling.test.ts`, `ai-api.test.ts` |
| 16 | Toda string visivel vem do i18n nos cinco idiomas, inclusive a politica de privacidade da IA | `TranslationsParityTest`, `ai-sql-builder.test.ts`, `LegalPagesTest`; `lang/*/ai.php` |
| 17 | A nona tool aparece como `available` na home, busca, sitemap e metas, com imagem OG propria | Testes de registro/home/SEO/sitemap; `public/og/ai-sql-builder.png` 1200x1200 |
| 18 | Gates da fase | `composer test` (424 testes, 10.664 assercoes), `npm run test` (730 testes, 53 arquivos), `types:check`, `lint:check` (0 erros), `format:check`, `build`, `build:ssr`, `migrate:fresh --seed`, `route:list`, os dois `--dry-run` e `seo:og-image` |
| 19 | Nenhum commit ou push feito pelo assistente | O historico e do usuario; esta fase nao commita |
| 20 | Smoke real com o DeepSeek | **Concluido em 2026-10-21**: matriz completa em banco dedicado, com worker e HTTP reais; evidencias na secao "Revalidacao 2026-10-21" |

## Evidencias dos gates (2026-10-20)

```text
composer test      -> 424 testes, 424 passaram, 10.664 assercoes (Pint + PHPStan 7)
npm run test       -> 53 arquivos, 730 testes, 730 passaram
npm run types:check-> vue-tsc limpo (Wayfinder regenerado)
npm run lint:check -> 0 erros (8.508 avisos pre-existentes de estilo)
npm run format:check -> "All matched files use Prettier code style!"
npm run build      -> ok (cliente)
npm run build:ssr  -> ok (bootstrap/ssr/ssr.js)
migrate:fresh --seed -> 26 migrations + seed, ok
route:list --path=api/ai -> 2 rotas (POST e GET)
ai:prune-cache --dry-run -> 0 expiradas
ai:reconcile --dry-run   -> 0 presas
seo:og-image       -> 10 PNGs 1200x1200, incluindo og/ai-sql-builder.png
```

## Smoke real (pendente de chave)

O dono deve preencher **localmente** `AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY`
no `.env`; a chave nao vai para o chat, para o git, para issue ou para log.
Depois:

1. `php artisan optimize:clear && php artisan optimize` e subir
   `php artisan queue:work --tries=2 --timeout=90` em um terminal.
2. Conta Free verificada: POST com DDL valido -> 202; GET ate `done`; conferir
   SQL `SELECT` e tokens no resultado.
3. Repetir o mesmo POST -> 200, `cache_hit=true` e **nenhuma** linha nova de
   debito em `usage_ledger`.
4. Schema acima do limite -> 422; descricao acima do limite -> 422; 6 POSTs
   em um minuto -> 429; esgotar os 5 usos -> 402.
5. Conta paga: conferir reserva, estorno da diferenca no sucesso e estorno
   integral com chave invalida (trocar a chave temporariamente em uma copia do
   `.env` e restaurar depois).
6. Solicitacao de outra conta -> 404; POST sem sessao -> 401; POST sem CSRF ->
   419; visitante na pagina -> 200 com CTA de login.
7. Procurar o texto marcado do schema em `storage/logs/laravel.log` e no log do
   worker: nao pode aparecer.
8. Conferir `public/og/ai-sql-builder.png`, as metas da pagina e o sitemap.
9. Revisao manual no navegador: mobile empilhado, teclado, leitor de tela,
   copiar/baixar e timeout com "consultar novamente".

O que **ja** esta coberto por teste automatizado: todos os status, a matriz do
ledger, o cache hit sem debito, a dedupe, o contrato, o gate e o polling. O
smoke existe para validar a chave e o provedor reais, que nenhum teste toca
(`Http::fake()` em todos).

## Fora de escopo e riscos

- O historico de consultas foi **adiantado** em 2026-10-21 (commit `7f0fc2d`,
  tela `dashboard/History`, filtro `history_visible`): a F5 entrega a lista
  somente leitura. O opt-in do usuario, a retencao de 90 dias e o "apagar
  historico" continuam na F7.
- Gateway de pagamento e renovacao: F6.
- Em producao, o `.env` precisa de `DB_QUEUE_RETRY_AFTER=150` e do worker
  reiniciado; sem isso, um job de 90 s pode rodar duas vezes (RUNBOOK secao 5).
- A chave antiga do DeepSeek deve ser revogada depois do smoke da nova.
- Nenhum commit ou push: o usuario revisa e aprova pelo `gitpr`.

## Revalidacao 2026-10-21

Os 19 criterios de codigo foram reauditados pelos testes da fase e pelo smoke
real. O criterio 20 foi cumprido com DeepSeek real. A chave nunca foi impressa
em chat, log ou documento.

### Gates da revalidacao

```text
composer test       -> 440 testes, 440 passaram, 10.973 assercoes (Pint + PHPStan 0)
npm run test        -> 730 testes, 53 arquivos
npm run types:check -> vue-tsc limpo
AiApiTest + AiActionsTest + AiToolPropsTest + ProcessAiQueryTest            -> 107 testes verdes
AccountPanelTest (historico corrigido)                                      -> 14 testes, 237 assercoes
```

### Smoke real (criterio 20)

Ambiente: SQLite dedicado `storage/smoke.sqlite` (`pdo_sqlite`), `migrate:fresh
--seed --force`, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`,
`MAIL_MAILER=array` e `CACHE_STORE=file` injetados por variavel de processo.
`php artisan serve` em `127.0.0.1:8000` e `queue:work --tries=2 --timeout=90`
com a mesma configuracao. Login pela rota Fortify real (`/en/login`) com cookie
jar e `X-XSRF-TOKEN`; nenhuma sessao foi forjada. Banco `devtools` intocado.

| Passo | Evidencia | Resultado |
| --- | --- | --- |
| 1 | Login da conta Free verificada | sessao valida; `/dashboard` 200 |
| 2 | POST DDL valido | 202 `queued`, ULID `01m5geak6bzc4fk0hs6g3wnjq6`, cache miss, cota 1/5 |
| 3 | GET ate estado terminal | 200 `done`, SQL `SELECT ... JOIN`, tokens in 694 / out 423 |
| 4 | Repetir o mesmo POST | 200 `cache_hit=true`; ledger da conta continuou com 1 linha |
| 5 | Schema de 48.907 bytes (limite Free 8.192) | 422 `schema_too_large` |
| 6 | Conta paga: 7 POSTs distintos e rapidos | 6x 202 e 7o 429 com `Retry-After: 53` |
| 7 | Conta Free 2: 5 payloads distintos + 6o | 5x 202 e 6o 402 `quota_exceeded` |
| 8 | Conta paga: reserva e liquidacao | reserva 1.561; sucesso 705+767=1.472, estorno 89 (net 1.472). Segunda consulta reservou 1.556 e gastou 1.572: debito extra de 16. Ajuste para baixo **e** para cima comprovados |
| 9 | Worker com `AI_API_KEY=invalid-key-for-smoke` (variavel de processo, `.env` intacto) | POST 202 com debito 1.551; log `ProviderException reason=http_401 retryable=false`; request `failed`/`provider_unavailable`; ledger debito 1.551 + estorno 1.551 (net 0); cota voltou de 4.595 para 3.044; `failed_jobs` 0 (falha tratada, sem retry) |
| 10 | GET de consulta de outra conta | 404 |
| 11 | POST sem sessao (com CSRF valido) | 401 |
| 12 | POST de conta nao verificada | 403 |
| 13 | POST com cookie e sem `X-XSRF-TOKEN` | 419 |
| 14 | GET da tool como visitante | 200; `toolProps.canUse={"allowed":false,"reason":"guest"}`; CTA "Sign in to use the AI SQL Builder" |
| 15 | Busca por `SMOKE_MARKER_20261021` em `laravel.log` e nos logs dos workers | 0 ocorrencias |
| 16 | OG, metas e sitemap | `public/og/ai-sql-builder.png` (42.571 bytes); `og:image ...?v=2`; 5 URLs no sitemap; hreflang e canonical corretos |
| 17 | Revisao visual | Edge headless via CDP: desktop 1440x1400 e mobile 390x1200 (`mobile=true`, viewport meta honrado). Medido `scrollWidth=390`, **sem overflow horizontal**; CTA visivel e empilhado |

O passo 9 usou um worker separado com a chave invalida apenas no ambiente do
processo; os workers e o servidor foram encerrados ao fim e a chave real voltou
a valer no worker seguinte. `failed_jobs=0` porque a falha 401 e nao
retentavel: `ProcessAiQuery` registra `provider_unavailable`, estorna a reserva
e encerra o job sem relancar a excecao.

### Limpeza

`storage/smoke.sqlite`, cookie jars, payloads e respostas temporarias foram
removidos; `php artisan config:clear` executado; servidor, workers e Edge
headless encerrados; banco `devtools` nao foi tocado. Os logs de smoke foram
mantidos apenas ate a extracao destas evidencias.
