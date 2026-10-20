# Levantamento - FASE 5 (AI SQL Builder) e backfill da F4

Data: 2026-10-20
Branch: `develop_natan`
Pedido original: "Verifique se a fase 3 (docs/acceptance/FASE-3.md) foi
concluida e depois continue para a proxima fase 4".
Plano: `docs/plans/develop_natan/20261020_fase5_ai_sql_builder_plansfacts.md`
Copia no padrao do CLAUDE.md:
`docs/claude-code/plans/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`

## 1. Estado do repositorio no inicio

- Working tree limpo, branch `develop_natan`, HEAD `a35d79c` ("Adiciona Guia de
  implementacao do Github Social"). Commits recentes incluem a troca de
  `html2pdf` por `html2canvas-pro`, o login social contextual e a tool
  `markdown-to-pdf`.
- Gates da F3 reexecutados: `composer test` = 317 testes/9.673 assercoes (Pint,
  PHPStan e Pest verdes); `npm run test` = 694 testes/50 arquivos. A FASE-3
  registrava 268/7.166 e 46/659 na data do aceite; os numeros cresceram com o
  trabalho posterior, sem regressao.
- Registro de tools: 8 tools `available` (incluindo `markdown-to-pdf`, que entrou
  depois da F3) e `ai-sql-builder` como unica `soon`.
- `docs/SPEC.md` secao 9: F0-F4 concluidas; F5 e a proxima. A F4 (Auth) nao tem
  `docs/acceptance/FASE-4.md`; o aceite esta em blocos antecipados da FASE-0.

## 2. Verificacao da F3

- `docs/acceptance/FASE-3.md` registra 32/32 criterios verificados em
  2026-10-14, com os gates da epoca e o smoke de SSR.
- Auditoria de codigo: 30/32 criterios comprovados por codigo/teste; 0 ausentes
  ou parciais; 2 criterios sao apenas de execucao (gates e smoke de SSR), que
  foram reexecutados no Bloco 0 desta sessao.
- Divergencias de documentacao encontradas e corrigidas:
  - `docs/SPEC.md:21` dizia "As props sao duas" (o controller entrega quatro
    limites desde a F3).
  - `docs/acceptance/FASE-3.md:63` mandava upload/historico/cota para "(F4/F7)";
    o destino correto e F5/F7.

## 3. F4 (Auth) - por que um backfill

A F4 do SPEC esta marcada como concluida, mas o aceite foi registrado dentro da
FASE-0, em dois blocos: auth/login em 2026-10-13 e papeis/modelo comercial/paineis
em 2026-10-14. O usuario escolheu criar `docs/acceptance/FASE-4.md` para
consolidar criterios, provas e carry-overs, sem reimplementar nada.

Fatos verificados para o backfill:

- 2FA esta **ligado** no `config/fortify.php` (`twoFactorAuthentication` com
  `confirm` e `confirmPassword`), com as colunas na migration
  `2026_10_18_000002_add_two_factor_columns_to_users_table.php`; nao ha teste
  dedicado. O reabilitado veio no commit `e11dcce`.
- Passkeys continuam **desligadas**: `AppServiceProvider` chama
  `Passkeys::ignoreRoutes()` e o pacote `@laravel/passkeys` permanece porque o
  Fortify o exige.
- Mensagens de validacao nos 5 idiomas existem (`lang/*/validation.php`), o que
  encerra o carry-over da FASE-0.
- `users.appearance` continua somente leitura (o tema vive no cookie).
- Testes de auth existentes: `AuthenticationTest`, `RegistrationTest`,
  `SocialLoginTest`, `SetPasswordTest`, `EmailVerificationTest`,
  `VerificationNotificationTest`, `PasswordResetTest`,
  `PasswordConfirmationTest`, `LocalizedAuthPagesTest`, `SecurityTest`,
  `ProfileUpdateTest`, `ProfilePhotoTest`, `AppearanceTest`, `AccountPanelTest`,
  `NewsletterTest`, `LegalPagesTest`, `LocaleRoutingTest` e `MailLocaleTest`.

## 4. Fatos do repositorio que moldaram o plano

- **ADR-038 ja existe** (`ADR-038-implementacao-google-analytics-ga4.md`); as
  ADRs novas da F5 comecam em 039.
- **Politica de privacidade contradiz a F5**: `lang/en/legal.php` afirma que as
  tools rodam no navegador e que "what you paste into a tool ... is not sent to
  our servers". O AI SQL Builder envia schema e descricao ao provedor. A
  correcao nos 5 idiomas e bloqueio de release.
- **Ledger**: `usage_ledger.ai_request_id` e `unsignedBigInteger` e nunca foi
  escrito; `ai_requests.id` e ULID no SPEC. A solucao aditiva e
  `ai_request_ulid` char(26), mantendo a coluna legada para limpeza na F7.
- **Quota**: `App\Support\Quota` so le o estado (debitos - creditos) e nao tem
  coluna de saldo; a F5 consome via ledger.
- **Fila**: `config/queue.php` usa `retry_after=90`; o supervisor usa
  `--timeout=120`. Para o job de 90s nao ser reprocessado, o default vai para
  150 e o `.env` de producao precisa acompanhar. Ordem: 60 (provider) < 90
  (job) < 120 (worker) < 150 (retry_after).
- **Pagina generica**: `ToolController` entrega `slug` e quatro limites;
  `Show.vue` passa os quatro a todo `Tool.vue`. Para o AI, a extensao e um
  pacote `toolProps` (objeto vazio nas outras tools), sem pagina/rota nova.
- **Home**: o destaque do AI existe em `Home.vue` com badge "Coming soon",
  preview estatico e CTAs de cadastro/pricing; ao virar `available`, o badge, o
  texto e o CTA precisam mudar.
- **`byteLength`** ainda mora em `resources/js/lib/json/parse.ts` e e importado
  por `sql-formatter` e `timestamp-uuid`; o lugar certo e `lib/bytes.ts`.
- **Comando OG**: `php artisan seo:og-image` gera um PNG por tool `available`;
  o PNG vai comitado e o comando nao roda em producao.
- **Testes que fixam `soon`**: `ToolsRegistryTest`, `SitemapTest`,
  `SeoMetaTest`, `tools-registry.test.ts`, `home.test.ts` e `tool-page.test.ts`
  precisam ser atualizados quando o manifest virar `available`.

## 5. Rodadas de grill

Quatro rodadas, Q1 a Q15, todas respondidas pelo usuario.

| # | Pergunta | Decisao |
|---|---|---|
| 1 | Fazer F5 + backfill da F4, ou so a F4? | F5 + backfill da F4 |
| 2 | Rodar os gates da F3 agora ou depois? | Rodar `composer test` e `npm run test` no Bloco 0 |
| 3 | Corrigir as divergencias de doc? | Sim |
| 4 | Autenticacao da API | Sessao + CSRF (rotas web), sem tokens de API |
| 5 | Onde ficam os prompts? | Primeira resposta: coluna + versao; corrigida na Q8 |
| 6 | Validacao do SQL | Gate lexical (nao parser por dialeto) |
| 7 | Ha chave DeepSeek para o smoke? | Sim, o usuario digita no `.env` local e roda o smoke |
| 8 | Prompts: arquivo ou coluna? | Hibrido: arquivo versionado `resources/prompts/<slug>.vN.md` + `prompt_template`/`prompt_version` no banco |
| 9 | Rate limit e Turnstile | Rate limit por usuario/IP; sem Turnstile na F5 |
| 10 | Quantos output targets? | Todos os 9 do SPEC |
| 11 | Laravel AI SDK ou HTTP fino? | Cliente HTTP fino (DeepSeek) |
| 12 | Cache hit no POST | Cria linha `done` e devolve 200, sem debito |
| 13 | Visitante na pagina da tool | 200 com formulario desabilitado e CTA de login |
| 14 | Historico | Fica na F7; `history_visible` nasce `false` |
| 15 | Carry-overs da F4 | Apenas documentar (appearance, 2FA/passkeys, validacao) |

## 6. Contradicoes e correcoes durante o grill

- **Q5 x SPEC**: o usuario escolheu "coluna + versao", mas o SPEC secao 6 exige
  `resources/prompts/<target_slug>.v<N>.md`. Resolvido na Q8 com o hibrido:
  texto no arquivo versionado, caminho e versao no banco, versao no hash.
- **Parser x gate lexical**: o SPEC dizia "parser"; a decisao e lexical. Fica
  registrada como emenda deliberada ao SPEC e ao ADR-006, justificada pelo custo
  de um parser por dialeto e pelo fato de o SQL nunca ser executado.
- **ULID x bigint**: em vez de alterar a coluna legada, entra
  `ai_request_ulid`; a limpeza fica para a F7.
- **Privacidade**: o texto legal atual e falso para a F5; a reescrita nos 5
  idiomas e bloqueio de release.
- **ADR-038**: ja ocupado pelo GA4; ADRs novas comecam em 039.

## 7. Plano de execucao (resumo)

- **Bloco 0**: gates, saneamento de docs, FASE-4, plano/survey.
- **Bloco 1**: 5 migrations aditivas (`output_targets`, seed dos 9 alvos,
  `ai_requests` ULID, `ai_cache`, `ai_request_ulid` no ledger), modelos,
  enums e factories.
- **Bloco 2**: `config/ai.php` por env, 9 prompts `.v1.md`, `lang/*/ai.php`,
  `tools.ai-sql-builder.intro`, `home.ai.*`, privacidade nos 5 idiomas,
  `.env.example`.
- **Bloco 3**: canonicalizador, codec comprimido, prompt loader/builder,
  estimador, contrato, gate lexical, cache e exceptions.
- **Bloco 4**: `StartAiQuery`, `SettleAiQuery`, `FailAiQuery`, com lock na
  conta, dedupe, reserva/liquidacao e ledger com `created_at` da solicitacao.
- **Bloco 5**: contrato `LlmProvider`, DTO e `DeepSeekProvider` sobre `Http`.
- **Bloco 6**: `ProcessAiQuery` (ULID no construtor, timeout 90, tries 2),
  `ai:prune-cache`, `ai:reconcile`, fila com `retry_after=150`.
- **Bloco 7**: rotas `/api/ai`, `StoreAiQueryRequest`, controller, resource,
  erros, rate limit e CSRF.
- **Bloco 8**: `ToolProps` e `toolProps` no `ToolController`/`Show.vue`.
- **Bloco 9**: `Tool.vue` (form/result), `SchemaForm`, `ResultPanel`,
  `StateNotice`, `api.ts`, `polling.ts`, `useAiQuery.ts`, tipos e testes.
- **Bloco 10**: home, manifest `available` por ultimo, OG e testes de registro.
- **Bloco 11**: SPEC, API, ARQUITETURA, RUNBOOK, DESIGN, glossario, ADRs
  039-043, FASE-5, CHANGELOG, README e relatorio final.

## 8. Verificacao prevista

- `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`,
  `npm run format:check`, `npm run build`, `npm run build:ssr`,
  `php artisan migrate:fresh --seed`, `php artisan route:list --path=api/ai`,
  `php artisan ai:prune-cache --dry-run`, `php artisan ai:reconcile --dry-run`
  e `php artisan seo:og-image`.
- Smoke real com a chave DeepSeek no `.env` local: 202 + polling, cache hit 200
  sem debito, limites 422, cota 402, rate 429, ownership 404, CSRF 419,
  visitante 200, logs sem schema, PNG da tool e revisao manual no navegador.

## 9. Riscos e limites

- A politica de privacidade e bloqueio de release; sem ela a F5 nao publica.
- `retry_after` da fila precisa ser atualizado no VPS; senao um job de 90s pode
  ser reprocessado.
- O cache guarda schema comprimido por ate 30 dias; a solicitacao limpa o
  payload ao terminar.
- O gate lexical nao e um parser; aceita apenas `SELECT`/`WITH` apos remover
  strings/comentarios, mas nao promete validacao semantica por dialeto.
- Historico, retencao de 90 dias, 2FA/passkeys e `users.appearance` ficam fora
  da F5.
- Sem commit/push: o usuario revisa e aprova cada commit/PR pelo gitpr.

## 10. Artefatos

- Plano: `docs/plans/develop_natan/20261020_fase5_ai_sql_builder_plansfacts.md`
- Copia no padrao do CLAUDE.md:
  `docs/claude-code/plans/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`
- Survey: este arquivo.
- Aceites: `docs/acceptance/FASE-4.md` e `docs/acceptance/FASE-5.md`.
- Relatorio final:
  `docs/claude-code/reports/develop_natan/20261020_develop_natan_fase5_ai_sql_builder.md`.
