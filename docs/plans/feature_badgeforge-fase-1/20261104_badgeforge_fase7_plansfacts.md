# Plano de execução — BadgeForge Fase 7

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- SPEC: `docs/SPEC-BADGEFORGE.md` §16 (F7 = providers customizados +
  analytics completo + admin)
- Levantamento: `docs/survey/20261104_badgeforge_fase7_surveyfacts.md`

## Objetivo

Fechar o escopo funcional do BadgeForge antes da F8 (OpenAPI 3.1/hardening):
permitir que o usuário pago defina providers JSON próprios, registrar e exibir
analytics das imagens com dono e dar ao admin visão/moderação do catálogo, dos
providers customizados e do volume.

## Decisões fechadas

### Providers customizados

- Só pago cria/edita (limite `custom_providers.limit`, default 10, `null` =
  ilimitado); Free 403; excluir permitido.
- Mapeamento declarativo: URL template + params de path; por métrica, `label`,
  JSON Pointer RFC 6901 e formatador `text|number|percent`. Sem auth/código.
- URL pública opaca `/api/badges/custom/{ulid}/{definition}/{path}.{ext}`.
- Painel + builder logado do pago; não entra em badge pessoal, catálogo global
  ou docs de providers.
- Template imutável; nome/label/pointer/formatter editáveis; slug e conjunto de
  métricas fixos após a criação.
- Probe com `example_path`: shape inválido → 422; falha de rede não bloqueia.
- Reuso do SSRF da F5 (guard + resolver + `fetchBody` pinado); cache 300/86400/
  60; rate limit por dono (3000 pago, 300 Free, 60 anônimo por IP).
- Downgrade: continua servindo no limite Free; editar 403; excluir liberado.
- Admin vê template/pointer, desabilita (URL 404), nunca exclui.

### Analytics

- Só imagens com dono: badge pessoal, endpoint e provider custom.
- `badge_views` polimórfica + `badge_view_dailies`, com dimensão `definition`.
- Registro síncrono best-effort, conta 200 e 304, sem dedupe; únicos por
  `ip_hash`.
- HMAC-SHA256 com `APP_KEY` para IP/UA; UA cru truncado; país via GeoLite2 local
  opcional; referer só host.
- Free: 30d, totais e únicos por imagem; pago: 7/30/90, série, referrers,
  países, formatos, cache, CSV sem identificadores e lote.
- Retenção bruto 90d / agregado 400d; comandos horário e diário via
  `task_schedules`.
- Web + CSV apenas; seção na dashboard; docs públicos com seção curta.

### Admin

- `/admin/badgeforge/*`: catálogo (provider/definição enabled), custom providers
  (origem visível, toggle enabled, sem delete) e volume global (7/30/90/400).
- Nav, i18n nos 5 idiomas e auditoria.

## Schema

- `badge_custom_providers`: ULID, user_id, name, url_template (hidden),
  path_params JSON, example_path, metrics_version, enabled.
- `badge_custom_provider_definitions`: ULID, provider_id, slug, label, pointer,
  formatter, sort.
- `badge_views`: sujeito polimórfico, user_id, definition, hash de IP/UA, UA cru,
  referer_domain, country, format, style, cache_hit, viewed_at.
- `badge_view_dailies`: mesmas dimensões + day, views, unique_visitors,
  cache_hits; dimensões com default `''` para o único.
- Seed de `task_schedules` para aggregate e prune.

## Etapas

1. E1 — migrations, models, enums, factories, config e `maxmind-db/reader`.
2. E2 — template, params, JSON Pointer, formatter, probe, cache/`fetchBody` +
   unit tests.
3. E3 — CRUD, requests, controller, rotas de gestão, limite/gate pago.
4. E4 — serving público, limiter, ordem de rota e reserva de `custom`.
5. E5 — hashes, GeoIP, referer e gravação nos três controllers.
6. E6 — `OwnerAnalytics`, prop, painel/gráfico e CSV.
7. E7 — aggregate/prune + agendamento.
8. E8 — admin completo.
9. E9 — builder/painel custom, Vitest e fixture.
10. E10 — i18n e documentação (ADR-086, ADR-087, SPEC, API, OPENAPI 1.7.0,
    glossário, CLAUDE, RUNBOOK).
11. E11 — gates, smoke real, aceite, relatório e parada para revisão.

## Verificação

`composer test` por etapa; ao tocar TS, `npm run test` e `npm run types:check`.
No fim: `composer test`, `npm run test`, `npm run types:check`,
`npm run lint:check`, `npm run build`, smoke com rede real (custom MISS→HIT,
404/422, stale 200, analytics gravado, CSV sem PII, toggles do admin, aggregate/
prune) e `git status` sem commit/push.

## Riscos

Colisão de rota `custom`, 500 no serving (vira fallback), pinagem SSRF,
insert no hot path, único com NULL, ordem agregação/poda, GeoIP opcional,
ordem do fixture do tool-props, cache após editar métrica, exposição de origem,
worker de fila e downgrade.
