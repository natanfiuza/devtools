# Fase 5 - AI SQL Builder

Data: 2026-10-20
Branch: develop_natan
Plano: `docs/plans/develop_natan/20261020_fase5_ai_sql_builder_plansfacts.md`
Aceite: `docs/acceptance/FASE-5.md`

## Objetivo

Verificar a conclusao da F3, fazer o backfill do aceite da F4 e implementar a
proxima fase nao construida: a **F5 - AI SQL Builder**, a unica tool que
depende do servidor (excecao ao ADR-002).

## Resultado

A nona tool esta implementada e `available`: `/{locale}/tools/ai-sql-builder`
abre para visitante com CTA de login, exige conta verificada e ativa, cobra 5
usos vitalicios no Free ou tokens do plano pago, usa fila com polling, cache
global sem debito, provedor DeepSeek por cliente HTTP fino, contrato JSON e
gate lexical do SQL. O SQL nunca e executado.

- F3 confirmada: 32/32 criterios em `docs/acceptance/FASE-3.md`, com os dois
  gates de execucao rodados no inicio da sessao.
- F4 backfillada em `docs/acceptance/FASE-4.md` (a fase ja estava pronta; nao
  houve implementacao nova).
- Gates finais verdes; **smoke real com o DeepSeek pendente** porque as
  variaveis `AI_*` do `.env` local estao vazias (ver "Pendencia").

## Decisoes da sessao

1. F5 + backfill da F4, com os gates da F3 rodados primeiro.
2. API no grupo `web`, com sessao + CSRF, sem token de API.
3. Prompt hibrido: texto em `resources/prompts/<slug>.v<N>.md`, colunas
   `prompt_template`/`prompt_version` em `output_targets`, versao no hash.
4. Cliente LLM fino sobre `Http` do Laravel + DeepSeek (ADR-039).
5. Gate lexical para o alvo `sql` em vez de parser por dialeto (ADR-041).
6. Os nove alvos de saida.
7. Cache hit nunca debita; no job, a reserva e estornada (ADR-040).
8. Visitante ve a pagina com CTA de login (200), sem 404.
9. Historico fica na F7 (`history_visible = false`).
10. Politica de privacidade reescrita nos cinco idiomas como bloqueio de
    release.
11. `toolProps` na pagina generica, vazio para as tools de navegador
    (ADR-043).
12. Nenhum commit/push; o usuario aprova pelo `gitpr`.

## Implementacao por bloco

### Banco e modelos

- `output_targets` (slug, `supported_dialects`, prompt, `enabled`,
  `plan_required`, `sort`) com carga dos nove alvos por migration.
- `ai_requests` com ULID, estado, hash/versao do prompt, reserva
  (`quota_unit`, `reserved_tokens`, `reserved_uses`), payload comprimido e
  resposta; indices de dono, hash, status e dedupe.
- `ai_cache` com hash unico, payload comprimido, TTL e contadores de hit.
- `usage_ledger.ai_request_ulid` (o `ai_request_id` bigint fica obsoleto, sem
  drop).
- Enums `AiRequestStatus`, `AiFailureCode`, `QuotaUnit`, `SchemaSource`,
  `SqlDialect`; modelos e factories.

### Nucleo

- `Canonicalizer`/`CanonicalPayload` (JSON de chaves ordenadas),
  `PayloadCodec` (gzcompress + base64), `TokenEstimator`, `PromptLoader`,
  `PromptBuilder` com delimitadores anti prompt injection,
  `ContractValidator`, `SqlGuard`, `AiResult`, `AiCacheHit`,
  `AiCacheRepository`, `AiLedger`, `Quota` consumido sem alterar a regra de
  saldo derivado.

### Acoes e fila

- `StartAiQuery`: transacao com `lockForUpdate` na conta; dedupe (600 s),
  cache, limites do plano, criacao, reserva e dispatch `afterCommit`.
- `SettleAiQuery`: idempotente, ajusta o pago pelo `usage` real, nao ajusta o
  Free, limpa o payload e grava o cache depois do commit.
- `FailAiQuery`: estorno integral e `error_code`.
- `ProcessAiQuery`: somente o ULID no construtor; cache de novo; provedor;
  contrato; gate; liquidacao; logs so com metadados.
- `ai:prune-cache` e `ai:reconcile`, ambos `--dry-run`.
- Fila: `retry_after` padrao 150; ordem 60 (provider) < 90 (job) < 120
  (worker) < 150 (retry).

### Provedor e API

- `LlmProvider` + `LlmCompletion` + `DeepSeekProvider` (`POST
  {base_url}/chat/completions`, JSON mode, Bearer, timeouts, sem corpo de
  resposta em excecao).
- `POST /api/ai/queries` (200 cache / 202 fila ou dedupe) e `GET
  /api/ai/queries/{ulid}` (200 em qualquer estado; 404 para outra conta).
- Envelope `{data}`/`{error:{code,message,details}}`, `ApiError`, rates por
  conta e IP, `Retry-After`.

### Frontend

- `Tool.vue` em layout formulario | resultado, com `StateNotice` para guest,
  e-mail nao verificado, suspensao, cota, indisponibilidade e falha.
- `SchemaForm.vue`: abas de fonte, editor CodeMirror (SQL/JSON/texto),
  dialeto, alvo, descricao com contador em code points e bloqueio no limite.
- `ResultPanel.vue`: abas de alvo, `<pre>` com rolagem, copiar/baixar,
  premissas/avisos/tabelas, tokens, cache hit e `needs_clarification`.
- `api.ts` (CSRF uma decodificacao), `polling.ts` (1,5 s ate 5 s, timeout
  120 s), `useAiQuery.ts` (maquina de estados e cancelamento).
- Home, busca, sitemap, metas e OG refletindo a nona tool; `manifest.json`
  virou `available`.

### i18n, privacidade e docs

- `lang/{en,pt_BR,pt_PT,fr,es}/ai.php` e textos de home/tools/legal; paridade
  garantida por teste.
- Politica de Privacidade: schema e descricao vao ao provedor, cache de ate 30
  dias, SQL nunca executado, data legal atualizada.
- `docs/SPEC.md` secao 6 atualizada; `docs/API.md`, `docs/ARQUITETURA.md`,
  ADRs 039-043, emendas 002/004/006/011/016/029/030; RUNBOOK, DESIGN,
  glossario, README e CHANGELOG.

## Correcao relevante

`AiCacheRepository::find()` confiava so no MD5, contrariando o ADR-016. Passou
a receber o `CanonicalPayload`, decodificar o payload guardado e comparar o
JSON inteiro com `hash_equals`; payload ilegivel apaga a linha, payload
diferente e ignorado. Teste novo cobre a colisao (linha com o hash de outro
payload nao e servida), e os chamadores/testes foram atualizados.

## Gates (2026-10-20)

| Gate | Resultado |
| ---- | --------- |
| `composer test` | 424 testes, 424 passaram, 10.664 assercoes; Pint e PHPStan 7 sem erro |
| `npm run test` | 53 arquivos, 730 testes, 730 passaram |
| `npm run types:check` | `vue-tsc` limpo (Wayfinder regenerado) |
| `npm run lint:check` | 0 erros (8.508 avisos pre-existentes de estilo) |
| `npm run format:check` | Prettier ok |
| `npm run build` | ok |
| `npm run build:ssr` | ok (`bootstrap/ssr/ssr.js`) |
| `php artisan migrate:fresh --seed` | 26 migrations + seed, ok |
| `php artisan route:list --path=api/ai` | 2 rotas |
| `php artisan ai:prune-cache --dry-run` | 0 expiradas |
| `php artisan ai:reconcile --dry-run` | 0 presas |
| `php artisan seo:og-image` | 10 PNGs 1200x1200, incluindo `og/ai-sql-builder.png` |

## Pendencia: smoke real

`AI_BASE_URL`, `AI_MODEL` e `AI_API_KEY` estao **vazias** no `.env` local (as
tres linhas existem, sem valor). A chave deve ser digitada localmente pelo
dono - nunca no chat, no git ou em log. Sem ela, o provedor nao e chamado e o
smoke nao valida o DeepSeek real.

Roteiro em `docs/acceptance/FASE-5.md`: subir o worker, POST Free 202 ->
`done`, repetir para 200 com cache hit sem debito novo, 422/402/429, conta
paga com estorno, 401/403/404/419, log sem o texto do schema, OG/metas e
revisao manual no navegador. O codigo e os contratos ja estao cobertos por
teste; o smoke comprova a chave e o provedor.

## Riscos e proximos passos

- Producao precisa de `DB_QUEUE_RETRY_AFTER=150` e `queue:restart`; senao um
  job de 90 s pode rodar duas vezes.
- A chave antiga do DeepSeek deve ser revogada depois do smoke da nova.
- Cache guarda o schema comprimido por ate 30 dias; a politica ja informa.
- F6 (gateway) e F7 (historico, retencao, API publica) ficam fora desta fase.
- O working tree inclui, alem da F5, alteracoes nao commitadas de trabalhos
  anteriores (ex.: ADR-038/GA, auth social, Markdown para PDF); nada foi
  commitado ou enviado.

## Git

Nenhum commit ou push foi feito. O usuario revisa e aprova cada commit/PR pelo
`gitpr`.
