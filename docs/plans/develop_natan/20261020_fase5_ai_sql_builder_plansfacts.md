# F5 - AI SQL Builder, com backfill do aceite da F4

Sessao de grill (skill `grill-with-docs`), pedido: "Verifique se a fase 3
(docs/acceptance/FASE-3.md) foi concluida e depois continue para a proxima fase
4". Branch `develop_natan`, em 2026-10-20. Quatro rodadas de perguntas (Q1 a
Q15), todas respondidas pelo usuario, e plano aprovado antes da execucao.

**Resultado da verificacao da F3**: concluida. O aceite registra 32/32 criterios
em 2026-10-14; a auditoria de codigo confirmou 30/32 implementados, 0 ausentes e
2 apenas de execucao (gates e smoke de SSR). Os gates foram reexecutados nesta
sessao: `composer test` = 317 testes/9.673 assercoes (Pint, PHPStan e Pest
verdes) e `npm run test` = 694 testes/50 arquivos. As duas divergencias de doc
foram corrigidas: `docs/SPEC.md:21` (quatro limites + `toolProps`) e
`docs/acceptance/FASE-3.md:63` (F5/F7).

**Por que F5**: a F4 do SPEC (Auth) ja estava concluida e sem `FASE-4.md`; o
usuario escolheu fazer o backfill do aceite e seguir para a proxima fase nao
construida, a F5. O aceite da F4 agora esta em `docs/acceptance/FASE-4.md`.

**Decisoes das rodadas**: F5 + backfill F4; rodar gates antes; API com sessao e
CSRF; prompt hibrido (arquivo versionado + coluna); gate lexical de SQL; cliente
HTTP fino; todos os 9 targets; cache hit sem debito; visitante recebe 200 com
CTA de login; historico na F7; carry-overs da F4 apenas documentados; politica
de privacidade reescrita nos 5 idiomas como bloqueio de release; nenhum commit.

Plano de execucao aprovado (este arquivo), survey em
`docs/survey/20261020_fase5_ai_sql_builder_surveyfacts.md` e copia no padrao do
CLAUDE.md em
`docs/claude-code/plans/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`.

---

# Fase 5 - AI SQL Builder (e backfill do aceite da F4)

## Contexto

A F3 esta concluida: `docs/acceptance/FASE-3.md` registra 32/32 criterios verificados em 2026-10-14, e a auditoria de codigo encontrou 30/32 implementados, 0 ausentes e 2 criterios que sao apenas de execucao (gates e smoke de SSR), que serao rodados no Bloco 0. Ha duas divergencias pequenas de documentacao: `docs/SPEC.md:21` ainda diz que a pagina generica recebe "duas props" (o controller entrega quatro limites), e `docs/acceptance/FASE-3.md:63` manda upload/historico/cota para "(F4/F7)" quando o destino e F5/F7.

A "fase 4" pedida e ambigua: a F4 do SPEC (Auth) ja foi concluida e esta registrada em `docs/SPEC.md:151`, mas nao existe `docs/acceptance/FASE-4.md` - o aceite ficou dentro da FASE-0, em blocos antecipados. A proxima fase nao construida e a **F5 - AI SQL Builder**, a unica tool que ainda esta `soon` e a unica que depende de servidor (excecao ao ADR-002).

Resultado esperado: `/{locale}/tools/ai-sql-builder` funcionando para contas com e-mail verificado, com 5 usos gratuitos no Free, franquia de tokens nos planos pagos, cache sem debito, fila, polling, `output_targets`, 9 alvos de saida, politica de privacidade corrigida nos 5 idiomas e aceite da F4 preenchido.

## Decisoes fechadas no grill

1. **F5 + backfill da F4**: criar `docs/acceptance/FASE-4.md` documentando o que ja existe e seguir para a F5.
2. **Gates da F3 primeiro**: rodar `composer test` e `npm run test` no Bloco 0; corrigir as duas divergencias de doc.
3. **API com sessao + CSRF**: rotas em `routes/web.php` (middleware web), sem tokens de API. `fetch` com `credentials: same-origin` e `X-XSRF-TOKEN` lido do cookie, decodificado uma vez. Sem Turnstile na F5; rate limit por usuario e IP.
4. **Prompt hibrido**: texto versionado em `resources/prompts/<target_slug>.v<N>.md`; banco guarda `prompt_template` e `prompt_version`; a versao entra no MD5.
5. **Cliente HTTP fino**: `DeepSeekProvider` sobre `Http` do Laravel; sem o Laravel AI SDK.
6. **Gate lexical de SQL**: remover strings/comentarios, exigir uma unica instrucao `SELECT`/`WITH`, bloquear DDL/DML, `;` interno e funcoes perigosas. Vale para o alvo `sql`; os alvos de ORM passam pelo validador de contrato. Isso emenda o SPEC (que dizia "parser") e o ADR-006.
7. **Todos os 9 targets**: sql, laravel_query_builder, eloquent, sqlmodel, sqlalchemy, django_orm, prisma, drizzle, typeorm.
8. **Cache hit nao debita**: cria uma linha `done` com `cache_hit=true` e devolve 200. Cache miss no Free debita 1 uso; no pago debita a estimativa em tokens; o job ajusta pelo `usage` real.
9. **Visitante recebe 200** na pagina da tool, com formulario desabilitado e CTA de login; sem 404 e sem redirect.
10. **Historico fica na F7**: `history_visible` nasce `false`; nenhuma tela de historico na F5.
11. **Politica de privacidade e bloqueio de release**: `lang/{locale}/legal.php` hoje afirma que nada sai do navegador. Reescrever nos 5 idiomas, citar o provedor de IA atual (DeepSeek), o cache e a retencao, e subir `config/legal.php` (`updated_at`).
12. **Carry-overs da F4 apenas documentados**: `users.appearance` somente leitura; 2FA/passkeys reabilitados no commit `e11dcce` sem aceite formal; mensagens de validacao ja fechadas em 2026-10-18.
13. **Git**: nenhum commit ou push; o usuario aprova cada commit/PR pelo gitpr.

## Blocos de execucao

### Bloco 0 - Verificacao e saneamento

- Rodar `composer test` e `npm run test`; registrar os totais.
- Corrigir `docs/SPEC.md:21` (quatro limites + `toolProps`) e `docs/acceptance/FASE-3.md:63` (F5/F7).
- Criar `docs/acceptance/FASE-4.md` no formato da FASE-3: criterios do SPEC secao 7, evidencias existentes, data 2026-10-20, status "concluida (backfill)", carry-overs acima. Adicionar "aceite em docs/acceptance/FASE-4.md" na linha F4 do SPEC.
- Salvar plano/survey desta sessao nos caminhos do fim deste arquivo.

### Bloco 1 - Banco, modelos e enums

Migrations novas, todas aditivas e retrocompativeis:

| Migration | Conteudo |
|---|---|
| `2026_10_20_000001_create_output_targets_table.php` | `id`, `slug` unique, `label`, `language`, `supported_dialects` json, `prompt_template`, `enabled` default true, `plan_required` FK `plans` nullable `nullOnDelete`, `sort`, timestamps |
| `2026_10_20_000002_seed_output_targets.php` | 9 alvos: slugs do SPEC; todos os 4 dialetos; `prompt_template=<slug>.v1.md`; `enabled=true`; `plan_required=null`; `sort` 10..90 |
| `2026_10_20_000003_create_ai_requests_table.php` | `id` ULID char(26) PK; `user_id` FK cascade; `output_target_id` FK restrict; `status`, `dialect`, `schema_source`, `prompt_hash` char(32), `prompt_version`, `prompt_template`, `model`, `estimated_tokens`, `reserved_tokens`, `reserved_uses`, `quota_unit`, `tokens_in/out` nullable, `cache_hit` bool, `error_code` nullable, `history_visible` default false, `canonical_payload` longtext nullable, `response_json` longtext nullable, timestamps; indices `(user_id, created_at)`, `prompt_hash`, `(status, created_at)` |
| `2026_10_20_000004_create_ai_cache_table.php` | `id`, `prompt_hash` char(32) unique, `canonical_payload` longtext, `response_json` longtext, `model`, `prompt_version`, `tokens_in/out`, `hits` default 0, `last_hit_at` nullable, `created_at`, `expires_at`; indice em `expires_at` |
| `2026_10_20_000005_add_ai_request_ulid_to_usage_ledger_table.php` | `ai_request_ulid` char(26) nullable + indice. O `ai_request_id` bigint legado fica intocado e marcado como obsoleto; limpeza na F7 |

Modelos: `OutputTarget`, `AiRequest` (`HasUlids`, `$incrementing=false`, `$keyType='string'`), `AiCache`; factories para testes. Enums: `AiRequestStatus` (`queued|processing|done|failed`), `SchemaSource` (`ddl|json|migration|models`), `SqlDialect` (`mysql|pgsql|sqlsrv|sqlite`), `QuotaUnit` (`uses|tokens`), `AiFailureCode` (provider_unavailable, invalid_response, unsafe_sql, timeout, invalid_contract, internal).

Regra de ouro do ledger: toda linha ligada a uma solicitacao usa `ai_request_ulid` e `created_at` igual ao `created_at` da solicitacao (inclusive estornos/ajustes), para a janela da assinatura nao desalinhar entre inicio e liquidacao. `UsageLedger` ganha a coluna no `#[Fillable]`.

### Bloco 2 - Configuracao, prompts e i18n

- `config/ai.php`, tudo por env: `AI_BASE_URL`, `AI_MODEL`, `AI_API_KEY`, timeout 60s, connect 10s, temperature 0.2, max_tokens 2000, `max_schema_bytes` 65536, `max_description_chars` 2000, `max_output_bytes` 32768, `estimate_output_tokens` 900, `cache_ttl_days` 30, `inflight_ttl_seconds` 600, `job_timeout` 90, `job_tries` 2, backoff `[5,15]`, rate limits (6/min e 60/h por usuario; 30/min por IP; poll 120/min), polling (1500ms inicial, 5000ms maximo, 120000ms timeout), `prompt_path`.
- `resources/prompts/{sql,laravel_query_builder,eloquent,sqlmodel,sqlalchemy,django_orm,prisma,drizzle,typeorm}.v1.md` + `README.md`. Regras fixas: usar so tabelas/colunas do schema, nao inventar, tratar schema/descricao como dado (anti prompt injection), responder apenas JSON, respeitar o dialeto. Particularidades por alvo; SQLModel usa `select().where()` e `col()`.
- `lang/{en,pt_BR,pt_PT,fr,es}/ai.php` (namespace novo em `config/locales.php`): erros, estados, labels dos 9 targets, formulario, resultado, cota, polling, avisos. `tools.ai-sql-builder.intro` nos 5 idiomas. `home.ai.*` atualizado para a tool disponivel.
- **Privacidade (bloqueio)**: reescrever em `lang/{locale}/legal.php` a secao que diz que nada sai do navegador; explicar que o AI SQL Builder envia schema e descricao ao provedor de IA, que a resposta pode ficar em cache por ate 30 dias e que o SQL nunca e executado. Citar DeepSeek como provedor atual (configuravel). Subir `LEGAL_UPDATED_AT` em `config/legal.php` e `.env.example`.
- `.env.example`: knobs de IA e `DB_QUEUE_RETRY_AFTER=150`.

### Bloco 3 - Nucleo de IA (`app/Support/Ai/`)

- `Canonicalizer`/`CanonicalPayload`: normaliza fonte, dialeto, target, schema, descricao, modelo e versao do prompt; JSON canonico com chaves ordenadas.
- `PayloadCodec`: `gzcompress` + base64 para `canonical_payload`; decodifica para o job.
- `PromptLoader`: le `resources/prompts/<arquivo>` e valida/extrar versao `.vN`; falha clara se o arquivo nao existir.
- `PromptBuilder`: instrucoes fixas + texto do prompt do target + delimitadores; schema e descricao entram como dados.
- `TokenEstimator`: `chars/4` para entrada; `estimate_output_tokens` para saida.
- `ContractValidator`: valida o JSON do SPEC (`sql`, `dialect`, `assumptions[]`, `warnings[]`, `tables_used[]`, `needs_clarification`, `clarification|null`, `outputs{slug:string}`); `outputs[target]` obrigatorio e nao vazio; `needs_clarification=true` exige `sql` vazio e `clarification` preenchida; `dialect` igual ao pedido; limite de bytes.
- `SqlGuard`: so para o alvo `sql`; remove strings e comentarios, divide por `;` (o ponto e virgula final e permitido; `;` interno com duas instrucoes nao), rejeita DDL, DML, `into outfile`, `load_file`, `pg_sleep`, `xp_cmdshell`, `sleep(`, `benchmark(`; aceita somente `SELECT`/`WITH`.
- `AiResult`, `AiCacheHit`, `AiCacheRepository` (busca por hash+expiracao, upsert atomico, incremento de `hits`).
- Exceptions em `app/Exceptions/Ai/` sem corpo de resposta do provedor e sem schema/prompt na mensagem.

### Bloco 4 - Acoes transacionais

`StartAiQuery` (transacao):
1. `lockForUpdate` na linha do usuario.
2. Dedupe: mesma conta + `prompt_hash` em `queued|processing` dentro de `inflight_ttl` -> devolve a solicitacao existente (202), sem debito.
3. Cache hit -> cria `ai_requests` `done`, `cache_hit=true`, tokens da cache, **sem ledger**; devolve 200.
4. Valida limites do plano (`max_schema_bytes`, `max_prompt_chars`, `lifetime_free_uses`, `monthly_tokens`) e dialeto suportado pelo target.
5. Cria `ai_requests` `queued`; grava `canonical_payload` comprimido; debita: Free `uses=1`, pago `tokens=estimativa`.
6. Devolve `StartResult` (id, status, cache_hit, quota).

`SettleAiQuery` (transacao): `lockForUpdate` no usuario e depois na solicitacao (sempre nessa ordem); se terminal, nao faz nada; grava tokens reais, `response_json`, status `done`, limpa `canonical_payload`; pago ajusta `tokens` (debita a diferenca para cima ou estorna para baixo); Free nao ajusta; grava/renova cache. `FailAiQuery`: estorna a reserva (uses ou tokens), status `failed`, `error_code`, limpa payload.

Matriz do ledger:

| Evento | Free | Pago |
|---|---|---|
| POST cache hit | nada | nada |
| POST cache miss | debito `uses=1` | debito `tokens=E` |
| Job cache hit | estorno `uses=1` | estorno `tokens=E` |
| Job sucesso | nada | debita `A-E` se `A>E`; estorna `E-A` se `A<E` |
| Falha | estorno `uses=1` | estorno `tokens=E` |
| Duplicado/terminal | nada | nada |

`E` = tokens de entrada estimados + `estimate_output_tokens`; `A` = `tokens_in + tokens_out` do provedor. `needs_clarification` e `done` e consome a reserva.

### Bloco 5 - Provedor

`app/Contracts/Ai/LlmProvider.php`, `LlmCompletion` (conteudo, tokens_in/out, modelo) e `app/Services/Ai/DeepSeekProvider.php`:
- `POST {AI_BASE_URL}/chat/completions`, Bearer `AI_API_KEY`, `response_format: {type: json_object}`, timeout/connect da config.
- Mapeia 429/5xx/408 para retryable; 401/403/400/422 para nao-retryable; nunca inclui o corpo da resposta na excecao.
- `AiServiceProvider` registra o binding, os rate limiters e as rotas de throttle.

### Bloco 6 - Job e comandos

`app/Jobs/ProcessAiQuery.php`: construtor recebe **somente o ULID**; `tries=2`, `timeout=90`, `backoff=[5,15]`, `failOnTimeout`; no `handle`, recarrega a solicitacao, sai se terminal, consulta cache de novo (hit -> estorna e conclui), chama o provedor, valida contrato + `SqlGuard`, liquida. `failed()` chama `FailAiQuery`. Logs somente metadados (id, status, tokens, code), nunca schema/prompt.

Comandos:
- `ai:prune-cache` (`--dry-run`): apaga cache expirado.
- `ai:reconcile` (`--dry-run`): encontra `queued|processing` presos alem de `job_timeout + folga` e falha/estorna. Cobre worker morto sem `failed()`.
- Sem scheduler novo; documentar cron no RUNBOOK.

Fila: `config/queue.php` passa a default `retry_after=150` (`DB_QUEUE_RETRY_AFTER`); ordem 60 (provider) < 90 (job) < 120 (worker) < 150 (retry_after). Dispatch com `->afterCommit()`. O `.env` de producao precisa de `DB_QUEUE_RETRY_AFTER=150`; anotar no RUNBOOK. Supervisor existente ja usa `--timeout=120 --tries=2`; nao mudar.

### Bloco 7 - API

Rotas em `routes/web.php`, fora do prefixo de idioma:

```php
Route::middleware(['auth', 'verified', 'active'])->prefix('api/ai')->name('api.ai.')->group(function () {
    Route::post('/queries', [AiQueryController::class, 'store'])
        ->middleware('throttle:ai-post')->name('queries.store');
    Route::get('/queries/{aiRequest}', [AiQueryController::class, 'show'])
        ->whereUlid('aiRequest')->middleware('throttle:ai-poll')->name('queries.show');
});
```

- `StoreAiQueryRequest`: `schema_source` no enum, `dialect` no enum, `output_target` existe/`enabled`, `schema` e `description` obrigatorios; limites globais; limites do plano validados na acao.
- `Api/AiQueryController`: `store` 200 (cache hit) ou 202 (queued/dedupe); `show` 200 para qualquer status, 404 se a solicitacao nao for da conta; ownership sempre por `user_id`.
- `AiQueryResource`: `{data:{id,status,cache_hit,result,tokens,poll_after_ms,quota,error_code}}`; `result` com o contrato do SPEC; `error` `{code,message,details}`.
- Status: 401 nao autenticado, 403 sem verificacao/conta suspensa, 419 CSRF, 422 validacao, 402 cota esgotada, 429 rate limit, 404 nao encontrada, 503 IA indisponivel; 200 cache hit/consulta terminal, 202 na fila.
- Codigos sao o contrato; a UI traduz por `ai.*`. Mensagem do servidor e fallback tecnico. CSRF: `api.ts` le `XSRF-TOKEN`, `decodeURIComponent` uma vez, envia `X-XSRF-TOKEN`; `Accept: application/json`.
- Rate limiters nomeados; resposta 429 com `Retry-After`.

### Bloco 8 - Props da pagina generica

`ToolController` passa a enviar `toolProps`, alem dos quatro limites. `app/Support/ToolProps.php` devolve `(object) []` para tools sem servidor e um pacote para `ai-sql-builder`:
- `available` (provider/chave configurados), `canUse` (`{allowed, reason}`: guest, email_unverified, account_suspended, quota_exhausted, unavailable), `quota` (unit, limit, used, remaining, exhausted, window), `targets` (slug, label, language, dialects), `sources`, `dialects`, `limits` (schema/descricao), `poll` (initial/max/timeout), `endpoints`.

`Show.vue` ganha `toolProps?: Record<string, unknown>` e faz `v-bind="toolProps"`. Os componentes que nao declaram essas props recebem `{}` (sem atributos extras). O `Tool.vue` do AI declara todas as props que consome e usa `inheritAttrs: false` para os quatro limites que nao lhe dizem respeito.

### Bloco 9 - Frontend da tool

`resources/js/tools/ai-sql-builder/`:
- `Tool.vue`: layout **formulario | resultado** (nao usa `ToolShell`; e a segunda excecao de layout, depois do Regex Tester - documentar no DESIGN). Empilha em telas estreitas.
- `SchemaForm.vue`: abas de fonte (DDL, JSON, migration Laravel, SQLModel), `CodeEditor` para o schema (SQL/JSON com as extensoes ja instaladas; texto puro nos outros), `Select` de dialeto, `Select` de target (9), textarea de descricao com contador, botao gerar. Contadores e limites do plano; bloqueia acima do limite.
- `ResultPanel.vue`: abas de targets devolvidos; `<pre>` mono com rolagem, copiar (`useCopyFeedback`), baixar (`downloadText`), premissas, avisos, tabelas usadas, tokens, `needs_clarification` com a pergunta do modelo.
- `StateNotice.vue`: guest (CTA login), e-mail nao verificado (CTA reenviar), conta suspensa, cota esgotada (CTA pricing), IA indisponivel, erro, timeout.
- `api.ts`: fetch com CSRF; normaliza 401/403/419; envelope de erro; `postQuery`, `getQuery`.
- `polling.ts`: funcao pura de atraso (1.5s -> 5s, timeout 120s); `useAiQuery.ts`: maquina de estados (idle, submitting, queued, polling, done, failed, timeout), cancela no unmount, botao "consultar novamente" apos timeout.
- `types.ts`: contrato do SPEC + props.
- Sem dependencia nova. Reusar `ToolButton`, `Button`, `Badge`, `Select`, `Tabs`, `Spinner`, `Tooltip`, `useCopyFeedback`, `downloadText`, `formatBytes`.
- Mover `byteLength` de `lib/json/parse.ts` para `lib/bytes.ts` e reexportar no caminho antigo (ou atualizar os imports de `sql-formatter` e `timestamp-uuid`).

Testes da fase:
- Pest unitario: canonicalizador, prompt loader/versao, estimador de tokens, contrato, gate lexical (aceita `SELECT`/`WITH` com `;` final; rejeita DDL/DML/multiplas instrucoes/funcoes perigosas) e codec do payload.
- Pest de feature: guest 401, sem verificacao 403, debito Free, reserva paga, cache hit sem debito, dedupe 202, limites 422, cota 402, ownership 404, rate 429; job com sucesso/liquidacao/estorno, cache hit no job, falha, contrato invalido e timeout; comandos com `--dry-run`.
- Vitest: `api.ts` (CSRF e mapeamento de erros), `polling.ts` (agenda, backoff, timeout, cancelamento) e estados da tool (guest, sem verificacao, cota, erro); atualizar registry/home/tool-page.

### Bloco 10 - Home, registro, OG e i18n final

- `resources/js/pages/Home.vue`: o destaque do AI deixa de ser "em breve"; badge/descricao/CTA passam a refletir a tool disponivel. CTA principal abre `/{locale}/tools/ai-sql-builder` (visitante ve o CTA de login); secundario segue para pricing. Atualizar testes.
- `manifest.json`: `status: "available"` **por ultimo**, depois de todo o resto; sem isso a pagina responde e o card linka antes da hora.
- Testes que fixam `soon` (registry, sitemap, SEO, home, tool page) atualizados.
- `php artisan seo:og-image` para gerar `public/og/ai-sql-builder.png` (1200x1200, comitado). Sem GD/rede em producao.
- `tools.ai-sql-builder.intro` e textos de estado nos 5 idiomas.

### Bloco 11 - Documentacao e aceite

- `docs/SPEC.md` secao 6: gate lexical (emenda), contrato da API, campos extras de `ai_requests`, cache, fila/tempos, retencao; secao 11.2: fechar `supported_dialects` (nome da coluna) e "cliente HTTP fino"; registrar a decisao de cache hit sem debito como ja decidida.
- `docs/API.md`: endpoints, payloads, status, erros, CSRF, rate limit, exemplos.
- `docs/ARQUITETURA.md`: fluxo POST -> fila -> provider -> cache/ledger -> poll; fronteiras e o que nunca vai para log.
- `docs/RUNBOOK.md`: worker, `retry_after=150`, restart, `ai:prune-cache`, `ai:reconcile`, rotacao da chave DeepSeek, como diagnosticar 503/timeout.
- `docs/design/DESIGN.md`: layout form/result do AI Builder, estados, responsivo, acessibilidade; mockup textual.
- `docs/glossario.md`: confirmar/ajustar termos (Solicitacao de IA, AICache, Prompt hash, Cota, Ledger, Output target, Cache hit) e acrescentar "needs clarification" se faltar.
- ADRs novos (o 038 ja existe - Google Analytics; comecar em 039):
  - 039 cliente LLM fino + DeepSeek;
  - 040 ciclo da solicitacao de IA, reserva/liquidacao e cache sem debito;
  - 041 gate lexical de SQL;
  - 042 contrato HTTP da IA (sessao, CSRF, polling, erros);
  - 043 props por tool na pagina generica (`toolProps`).
  Emendas: ADR-002 (excecao servidor), ADR-004 (schema-only), ADR-006 (parser -> lexical), ADR-011 (`supported_dialects`), ADR-016 (MD5/prompt version), ADR-029 (`toolProps`), ADR-030 (OG da nona tool).
- `docs/acceptance/FASE-5.md` com criterios, evidencias dos gates e o roteiro de smoke.
- `CHANGELOG.md`, `README.md` (env de IA, worker local), `docs/guides/` se necessario.

## Arquivos criticos

- `app/Support/Quota.php` (leitura da cota; nao criar coluna de saldo)
- `app/Support/Ai/` (canonicalizacao, prompts, gate, cache)
- `app/Actions/Ai/{StartAiQuery,SettleAiQuery,FailAiQuery}.php`
- `app/Jobs/ProcessAiQuery.php`
- `app/Services/Ai/DeepSeekProvider.php`
- `app/Http/Controllers/Api/AiQueryController.php`
- `resources/js/tools/ai-sql-builder/{Tool.vue,SchemaForm.vue,ResultPanel.vue,api.ts,polling.ts}`
- `resources/js/pages/tools/Show.vue` e `resources/js/pages/Home.vue`
- `database/migrations/2026_10_20_00000{1..5}_*.php`
- `lang/{5}/ai.php`, `lang/{5}/legal.php`, `resources/prompts/*.v1.md`
- `config/ai.php`, `config/queue.php`, `.env.example`

## Verificacao

Gates obrigatorios (todos devem sair 0):

```bash
composer test
npm run test
npm run types:check
npm run lint:check
npm run format:check
npm run build
npm run build:ssr
php artisan migrate:fresh --seed
php artisan route:list --path=api/ai
php artisan ai:prune-cache --dry-run
php artisan ai:reconcile --dry-run
php artisan seo:og-image
```

Smoke real (chave DeepSeek digitada pelo usuario no `.env` local; nunca no chat):

1. Subir `php artisan queue:work --tries=2 --timeout=90` e um usuario Free verificado.
2. POST com schema DDL valido -> 202, polling ate `done`; SQL so SELECT; tokens no resultado.
3. Repetir o mesmo POST -> 200, `cache_hit=true`, **nenhuma** linha nova de debito no ledger.
4. POST com schema gigante -> 422; descricao acima do limite -> 422; cota esgotada -> 402; rate limit -> 429.
5. Conta paga: reserva estimada, sucesso com `usage` menor -> estorno da diferenca; falha do provedor (chave invalida) -> estorno integral.
6. Solicitacao de outra conta -> 404; POST sem sessao -> 401; POST sem CSRF -> 419; visitante na pagina -> 200 com CTA de login.
7. Conferir que `storage/logs/laravel.log` e o log do worker nao contem o texto marcado do schema.
8. Conferir `public/og/ai-sql-builder.png`, metas e sitemap com a tool.
9. Revisao manual no navegador: mobile empilhado, teclado, leitor de tela, copiar/baixar, timeout com "consultar novamente".

## Fora de escopo e riscos

- Historico de uso, retencao de 90 dias, apagar historico: F7. 2FA/passkeys e `users.appearance`: carry-overs documentados, sem implementacao.
- Gateway de pagamento e renovacao: F6.
- O `ai_request_id` bigint do ledger fica obsoleto nesta fase; a coluna nova e a fonte. Nao dropar agora (migration destrutiva).
- O cache guarda schema comprimido por ate 30 dias; a solicitacao limpa o payload ao terminar. A politica de privacidade precisa dizer isso.
- `retry_after` da fila em producao: se o `.env` antigo mantiver 90, um job de 90s pode ser reprocessado. Atualizar o VPS e reiniciar o worker antes do smoke de producao.
- O gate lexical e uma emenda deliberada ao "parser" do SPEC; a justificativa e o custo de uma dependencia de parser por dialeto e o fato de o SQL nunca ser executado.
- Sem commit/push: o usuario revisa e aprova.

## Artefatos da sessao

- Plano da sessao: `docs/plans/develop_natan/20261020_fase5_ai_sql_builder_plansfacts.md`
- Copia do plano no padrao do CLAUDE.md: `docs/claude-code/plans/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`
- Survey: `docs/survey/20261020_fase5_ai_sql_builder_surveyfacts.md`
- Aceites: `docs/acceptance/FASE-4.md` e `docs/acceptance/FASE-5.md`
- Relatorio final: `docs/claude-code/reports/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`
