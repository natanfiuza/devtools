# Critérios de aceite — BadgeForge FASE 7 (providers customizados, analytics e admin)

Gerado em 2026-11-04.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16, plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase7.md`),
ADR-086 e ADR-087.

## Escopo da fase

- **Providers customizados**: o plano pago cria um provider declarativo
  (`url_template` + `path_params` + métricas com JSON Pointer RFC 6901 e
  formatador `text|number|percent`) sem código, credencial ou auth na origem.
  A imagem pública é stateless e opaca em
  `/api/badges/custom/{ulid}/{definition}/{path}.{svg|png}`; `custom` é slug
  reservado e a rota é registrada antes da genérica de provider. Template,
  slugs e conjunto de métricas são imutáveis (trocar = excluir e recriar) e o
  update bumpa `metrics_version` no namespace de cache. O cadastro faz probe
  com `example_path` (shape/URL insegura → 422; rede tolerada). O serving reusa
  o stack SSRF da F5 e **qualquer** falha vira STALE/fallback 200.
- **Analytics**: só imagens com dono (badge pessoal, endpoint e provider
  custom) gravam, em 200 e 304, uma linha por acesso, de forma síncrona e
  best-effort. `badge_views` polimórfica com `definition` + `badge_view_dailies`
  com dimensões `''` e unique. Privacidade por HMAC com `APP_KEY`, UA truncado,
  referer só host e GeoLite2 local opcional. Free 30 dias; pago 7/30/90 com
  série, referrers, países, formatos, taxa de cache e CSV sem PII; bruto 90
  dias / diário 400 dias; agregação horária e poda diária em `task_schedules`.
- **Admin**: `/admin/badgeforge/providers`, `/custom-providers` e
  `/analytics?window=7|30|90|400`, atrás de `can:access-admin`, com auditoria.
  O admin vê o template/pointer do provider customizado e desabilita (URL →
  404), mas não exclui.
- i18n nos cinco idiomas; ADR-086 substitui a ADR-063; ADR-087; SPEC §2/§6/§7/§8/
  §10/§11/§12/§14/§15/§16/§17; `API.md`; `OPENAPI.md` 1.7.0; glossário;
  `CLAUDE.md` e `RUNBOOK.md`.

Fora da fase: badge pessoal usando provider custom, adicionar/remover métricas
depois da criação, credencial/auth na origem, API JSON de analytics, GeoIP
remoto, exportação do admin e OpenAPI 3.1 completo (F8).

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | Migrations aditivas criam `badge_custom_providers`, `badge_custom_provider_definitions`, `badge_views` e `badge_view_dailies` sem tocar tabelas existentes; `down()` remove só o que a fase criou | `migrate:rollback --step=4` + `migrate --force`; inspeção do schema |
| 2 | O seed grava `badgeforge:analytics:aggregate` (`13 * * * *`) e `...:prune` (`17 4 * * *`) com `insertOrIgnore`; reaplicar não duplica e `down()` remove só esses comandos | `AnalyticsScheduleSeedTest` |
| 3 | Criar/editar exige `badgeforge.paid` (Free 403) e respeita `custom_providers.limit` por dono (`null` = ilimitado); excluir continua permitido após downgrade | `CustomProviderManagementTest` |
| 4 | Template precisa declarar exatamente os placeholders de `path_params` na mesma ordem; renderiza com `rawurlencode`; path com segmentos fora de `[A-Za-z0-9._~-]{1,200}` (ou `.`/`..`) é recusado | `CustomProviderUrlTemplateTest`, `CustomPathParamsTest`, `CustomProviderManagementTest` |
| 5 | JSON Pointer implementa RFC 6901 (`~0`/`~1`, índice sem zero à esquerda) e só aceita escalar; ausente/objeto/array viram `null` e a imagem faz fallback | `JsonPointerTest`, `CustomProviderBadgeTest` |
| 6 | `text|number|percent` formatam como os providers globais; `percent` fica em 0–100 e shape inválido não vira 500 | `CustomMetricFormatterTest` |
| 7 | `example_path` faz probe: URL insegura/redirect/JSON inválido/pointer ausente ou não escalar → 422 com `validation.badge_custom_provider.*`; timeout/DNS/5xx/429 são tolerados e o cadastro salva | `CustomProviderManagementTest` |
| 8 | Update recusa `url_template` e slug (`prohibited`), aceita nome/label/pointer/formatter e incrementa `metrics_version`; o conjunto de métricas não muda | `CustomProviderManagementTest` |
| 9 | Rota `GET /api/badges/custom/{ulid}/{definition}/{path}.{ext}` é registrada **antes** de `/api/badges/{provider}/...`; `custom` está em `RESERVED_SLUGS` e não resolve como provider global | `CustomProviderRouteTest`; `route:list --path=api/badges` |
| 10 | Serving responde 200 com `X-Badge-Cache` MISS → HIT, suporta 304 condicional, PNG e `download=1`; ULID desconhecido, provider desabilitado, definição inexistente, path inválido e extensão fora de svg/png respondem 404 | `CustomProviderBadgeTest`; smoke real |
| 11 | Qualquer falha no serving (guard, DNS, fetch, JSON, pointer) vira STALE/fallback 200 — nunca 422/500; `?cacheSeconds=` e `?refresh=1` são ignorados e a chave de cache leva ULID, `metrics_version` e `sha256(url)` | `CustomProviderBadgeTest`, `CustomProviderCacheKeyTest`; smoke real |
| 12 | Rate limit é por dono (Free 300/min, pago 3000/min) e ULID inválido/desconhecido cai no anônimo 60/min por IP | `CustomProviderRateLimitTest` |
| 13 | Só badge pessoal, endpoint e provider custom gravam visualização, em 200 e 304, uma linha por acesso; estática e provider global não gravam; `definition` só vai preenchido no custom | `BadgeAnalyticsRecordingTest`; smoke real |
| 14 | `ip_hash`/`user_agent_hash` são HMAC-SHA256 com `APP_KEY`; UA cru truncado; referer só host; GeoIP opcional devolve `null` sem arquivo/corrompido/IP privado; falha de analytics nunca quebra a imagem | `PrivacyHashTest`, `RefererDomainTest`, `MaxMindCountryReaderTest`, `BadgeAnalyticsRecordingTest` |
| 15 | Free vê 30 dias (totais e únicos por imagem + conta); pago vê 7/30/90 com série diária zero-filled, top referrers, países, formatos e taxa de cache; período inválido cai no default, nunca 422 | `OwnerAnalyticsTest`, `AnalyticsDashboardTest` |
| 16 | CSV do pago streama as colunas `viewed_at, subject_name, subject_type, definition, format, style, cache, country, referer_domain`, sem hash/IP/UA; Free recebe 403 | `AnalyticsExportTest` |
| 17 | Agregação reconstrói os últimos 7 dias de forma idempotente (apaga e reinsere o dia), preserva dia sem bruto e nunca soma únicos entre dias; poda remove bruto > 90d e dailies > 400d com `--dry-run` | `AnalyticsAggregationTest`, `AnalyticsPruneTest`; smoke real |
| 18 | Admin exige `can:access-admin`; liga/desliga provider e definição do catálogo, lista custom providers com dono/métricas/template/pointer, desabilita (URL → 404) sem excluir e mostra volume 7/30/90/400 só das dailies; toda ação é auditada sem URL de origem | `AdminBadgeForgeTest`; smoke real |
| 19 | Builder/painel ganham o modo `custom` só para plano pago, com criação/edição/exclusão/cópia de URL, CTA de upgrade no Free, preview no navegador e painel de analytics com gráfico acessível | `badgeforge-custom.test.ts`, `badgeforge-analytics.test.ts`, `admin-badgeforge.test.ts`; fixture regenerada |
| 20 | Docs pública ganha a seção de provider customizado nos cinco idiomas, com endpoint vindo do config | `docs-badgeforge.test.ts`, `BadgeForgeDocsPageTest`, `TranslationsParityTest` |
| 21 | SPEC, ADR-086/ADR-087 (e ADR-063 marcada como substituída), API.md, OPENAPI.md 1.7.0, glossário, `CLAUDE.md` e `RUNBOOK.md` refletem a entrega | inspeção de `docs/` |
| 22 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 23 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-04

- `composer test`: Pint ok, PHPStan 0 erros (nível 7), Pest **1.371 testes /
  22.003 asserções**.
- `npm run test`: Vitest **981 testes / 76 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: **0 erros**; 1.182 avisos de indentação pré-existentes.
  A primeira passada acusou 1 erro — `selectedDefinition` sem uso em
  `CustomProviderForm.vue` — corrigido removendo o computed e o import.
- `npm run build`: concluído (exit 0; aviso de timings do rolldown já
  conhecido).
- Migrações: `migrate:rollback --step=5` removeu as cinco migrations da F7 e
  `migrate --force` reaplicou; o seed deixou **2** agendamentos, sem duplicar.
- Rotas: `route:list --path=api/badges` mostra `custom/...` antes de
  `{provider}/...`; `endpoint` e `static` seguem como antes.
- Smoke com rede real (`php artisan serve`, 2026-11-04), provider
  `https://api.github.com/repos/{owner}/{repo}` e métrica `stars` em
  `/stargazers_count`:
  - primeira leitura **200** `X-Badge-Cache: MISS` (`stars: 0`, o valor real do
    repositório); segunda **200** `HIT`; `If-None-Match` → **304**.
  - PNG **200** `image/png` (60×20); `download=1` →
    `Content-Disposition: attachment; filename="stars-0.svg"` com variante
    RFC 5987.
  - definição inexistente, path com um segmento, extensão `gif` e path com
    `..%2F` → **404**; provider desabilitado no banco → **404**; reabilitado
    volta a **200**.
  - origem trocada para `https://api.github.invalid/...` → **200** fallback com
    `unavailable` e `Cache-Control: max-age=60` (`X-Badge-Cache: MISS`), sem
    500/422; o template original foi restaurado depois.
  - `badge_views`: só o provider custom gravou (estática e provider global
    ficaram fora), com `definition=stars`, `format` svg/png e `cache_hit`
    correspondente; `badgeforge:analytics:aggregate --days=1` gerou 2 linhas
    diárias e `...:prune --dry-run` não removeu nada.
  - `storage/logs/laravel.log` não recebeu a URL de origem nem o ULID do
    provider; os dados do smoke foram apagados ao final.
- `git status` revisado; nenhum commit/push executado.

## Notas e desvios do plano

- **`CustomProviderValidationTest` não existe como arquivo próprio**: os casos
  de validação (URL insegura, IP privado, redirect, JSON inválido, pointer
  ausente/não escalar, rede tolerada) ficaram em `CustomProviderManagementTest`,
  junto do CRUD. A cobertura é a mesma; só a divisão em dois arquivos não
  aconteceu.
- **`AdminTable.vue`** ganhou `id: number|string` (ULIDs) como previsto.
- **Agregação diária**: o plano falava em agregado "por dia"; a implementação
  segue literalmente o ADR-086 — uma linha por `subject/dia/definition/country/
  referer/format`, com `views`, `unique_visitors` e `cache_hits`, e nunca soma
  únicos entre dias.
- **Fallback do provider customizado**: o smoke confirmou `200` com
  `X-Badge-Cache: MISS`, mensagem `unavailable` e `Cache-Control: max-age=60`
  quando a origem não resolve — o comportamento previsto no ADR-087.
- **Lint**: `CustomProviderForm.vue` tinha um `selectedDefinition` calculado e
  nunca consumido; o gate de lint acusou e o computed/import foram removidos.
  O preview da badge customizada é montado em `CustomProviderPreview.vue`, que
  não precisa dessa seleção.
- **Contagem de testes**: o salto em relação à F6 (1.226 → 1.371 Pest; 952 →
  981 Vitest) vem dos 19 arquivos de teste novos da F7, não de testes
  removidos.
