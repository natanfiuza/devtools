# Levantamento — BadgeForge Fase 7

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Pedido: `/grill-with-docs` "Continue para a proxima fase 7"
- SPEC: `docs/SPEC-BADGEFORGE.md` §16 — providers customizados + analytics
  completo + admin.

## Contexto da tarefa

F1–F6 estão entregues. A F7 fecha o escopo funcional antes da F8 (OpenAPI 3.1 e
hardening). Hoje o catálogo de providers é global e sem dono, nenhuma rota grava
visualização, não há tabela/model de analytics, não existe provider criável pelo
usuário e o `/admin` não tem nada de BadgeForge.

## Fatos levantados

### Estado do analytics

- `badge_asset_views` não existe: sem migration, model, relação ou coluna de
  contador. Só está descrita em `docs/SPEC-BADGEFORGE.md` §7/§12 e no
  ADR-063 (duas tabelas).
- Nenhum dos quatro controllers de imagem grava visualização: `StaticBadgeController`,
  `ProviderBadgeController`, `PersonalBadgeController`, `EndpointBadgeController`.
- Não há UI, rota, prop ou teste de analytics; não há gráfico no dashboard.
- Não existe `ip_hash` nem `user_agent_hash`; o padrão de HMAC com `APP_KEY` é
  `ProviderCredential::fingerprint()`.
- Não há GeoIP; produção usa nginx/trusted proxies e Cloudflare só para
  Turnstile (sem `CF-IPCountry`).
- CSV só existe em `Admin\SubscriberController::export` (streamDownload +
  fputcsv).
- Plano: `User::effectivePlan()`/`activeSubscription()`; “pago” = plano não
  Free; gate de exemplo `EnsureHistoryHasPaidPlan` (403).

### Estado dos providers

- `badge_providers` e `badge_provider_definitions` são globais; `parser_class`
  é inerte; `client_key` resolve o client no `ProviderClientRegistry`;
  `extractor_key` escolhe a métrica.
- `ProviderCatalog` é singleton com `RESERVED_SLUGS=['static','endpoint']`.
- `ProviderUrlBuilder` já valida HTTPS, mesmo host, sem userinfo/porta e
  params; `ProviderPathParser` e `ProviderParam` servem de referência para o
  parse de path.
- O stack F5 tem `EndpointUrlGuard`, `EndpointTargetResolver`,
  `EndpointHttpClient` pinado por `CURLOPT_RESOLVE`, JSON-only, body cap,
  sem redirect, fail-closed sem curl; `EndpointMetrics` tem a máquina de
  estados HIT/MISS/STALE/fallback que o custom precisa espelhar.
- Não há limite, config ou CRUD de provider customizado.

### Dashboard, builder e admin

- `/dashboard/badgeforge` devolve `toolProps`, `toolUrl` e `upgradeUrl`; página
  em seções verticais; painéis `PersonalBadgesPanel` e `EndpointBadgesPanel`.
- Builder em `resources/js/tools/badgeforge/Tool.vue`, com `ModeTabs` apenas
  `static|provider`.
- Admin em `/admin`, `can:access-admin`, páginas em `pages/admin/`, nav fixa em
  `AdminLayout.vue`, `AdminTable.vue` tipado com `id: number`, auditoria via
  `RecordsAdminActions` + `AdminActionType`.
- i18n: namespaces `tools`, `account`, `admin`, `docs`, `validation`; paridade
  cobrada por `TranslationsParityTest` nos cinco idiomas.
- Agendamento: `bootstrap/app.php` lê `task_schedules` ativos e aceita cron,
  `hourly`, `dailyAt:HH:MM` e `everyMinute`; seeds por migration usam
  `insertOrIgnore` por `command`.
- Sem biblioteca de gráfico; o precedente é o SVG manual
  `ApiTokenUsageChart.vue`.

## Rodadas do grill

1. Escopo: os três blocos; provider custom por mapeamento JSON declarativo;
   analytics só de imagens com dono; bruto 90d + agregado diário.
2. Identidade: URL opaca por ULID; sem credenciais na origem; painel + URL +
   builder logado; admin com catálogo + custom + volume.
3. Analytics: básico 30d / pago 7–90; registro síncrono best-effort em 200 e
   304; GeoLite2 local opcional; várias métricas por provider com limite
   configurável.
4. Schema: tabela polimórfica; custom também grava analytics; CSV sem
   identificadores; JSON Pointer RFC 6901.
5. Validação/edição: probe bloqueia shape e tolera rede; origem imutável,
   métricas editáveis; agregado 400d; admin vê template/pointer.
6. Superfície: web + CSV; seção na dashboard; docs públicos com seção curta;
   série do dono lê bruto, agregado alimenta o admin.
7. Fechamento: dimensão `definition` no analytics; conjunto de métricas fixo;
   downgrade serve mas não edita; `example_path` obrigatório no probe.

## Restrições do projeto

- Nada de commit/push; parar para revisão ao fim da fase.
- Migrations retrocompatíveis; deploy roda `migrate --force`.
- Toda string via i18n nos cinco idiomas; limites em config; sem log de schema/
  prompt/URL de origem.
- ADR para decisão relevante, termo novo no glossário, relatório e plano nos
  caminhos padrão.
