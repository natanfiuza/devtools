# BadgeForge — Fase 7: providers customizados + analytics + admin

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- SPEC: `docs/SPEC-BADGEFORGE.md` §16
- Levantamento: `docs/survey/20261104_badgeforge_fase7_surveyfacts.md`
- Plano da skill: `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase7_plansfacts.md`

## Contexto

F1–F6 entregues. A F7 fecha o escopo funcional: providers customizados (pago),
analytics das imagens com dono e área admin do BadgeForge. O catálogo global não
muda; provider customizado ganha tabelas, URL opaca e reuso do stack SSRF da F5.
Comportamento continua em código (`parser_class` inerte), imagem nunca vira 500
e limites ficam em `config/badgeforge.php`.

## Decisões

### Custom providers

- Pago apenas; limite configurável (default 10, `null` ilimitado); Free 403.
- URL template + params; métrica = label + JSON Pointer RFC 6901 + formatador
  `text|number|percent`; sem credenciais.
- `/api/badges/custom/{ulid}/{definition}/{path}.{ext}`, stateless e opaca.
- Painel + builder logado; fora de badge pessoal e do catálogo/docs globais.
- Template imutável; label/pointer/formatter editáveis; slug e conjunto fixos.
- Probe com `example_path`; shape inválido 422, rede tolerada.
- SSRF F5, cache 300/86400/60, rate limit 3000/300/60 por dono/anônimo.
- Downgrade serve (limite Free), edita não, exclui sim.
- Admin vê origem/pointer e desabilita; não exclui.

### Analytics

- Só badge pessoal, endpoint e custom gravam; estática/provider global não.
- `badge_views` polimórfica + `badge_view_dailies`, com `definition`.
- Registro síncrono best-effort em 200 e 304; sem dedupe; únicos por `ip_hash`.
- HMAC IP/UA, UA truncado, GeoLite2 local opcional, referer só host.
- Free 30d (totais/únicos); pago 7/30/90 (série, referrers, países, formatos,
  cache, CSV sem identificadores, lote).
- Bruto 90d / agregado 400d; comandos horário e diário via `task_schedules`.
- Web + CSV; seção na dashboard; docs públicos com seção curta.

### Admin

- `/admin/badgeforge/*`: catálogo, custom providers (origem visível, toggle) e
  volume global 7/30/90/400; nav, i18n e auditoria.

## Schema e config

Migrations `2026_11_04_000003` a `000007`: tabelas de custom provider e
definições, `badge_views`, `badge_view_dailies` e seed dos agendamentos.
Config nova `custom_providers` e `analytics`; composer `maxmind-db/reader`.

## Backend

- Models/enums/factories de custom provider e sujeito de visualização.
- `Support/BadgeForge/Custom`: template, params, JSON Pointer, formatter, probe,
  cache, metrics, guard e cache key.
- `EndpointHttpClient::fetchBody()` reaproveita a pinagem SSRF.
- CRUD autenticado (create/update/delete) + requests + gate `badgeforge.paid`.
- Controller público com fallback 200 e limiter `badgeforge-custom`; rota custom
  antes da genérica; `custom` reservado.
- `Support/BadgeForge/Analytics`: hashes, referer, GeoIP, recorder, resolver,
  `OwnerAnalytics`, CSV; escrita nos três controllers.
- Commands aggregate/prune.
- Admin: três controllers, rotas, audit actions.

## Frontend

Tipos e props de custom/analytics; `customProviderUrl`; painel e diálogo de
custom; modo `custom` no builder; painel de analytics com gráfico SVG; três
páginas admin; `AdminTable` aceita ULID.

## i18n e docs

Chaves novas em `tools`, `account`, `admin`, `docs` e `validation` nos cinco
idiomas. ADR-086 (analytics), ADR-087 (custom providers), SPEC §§2/7/8/10/11/12/
14/15/16/17, API.md, OPENAPI.md 1.7.0, glossário, CLAUDE.md e RUNBOOK.md.

## Etapas

E1 schema/config; E2 primitivos; E3 CRUD; E4 serving; E5 gravação; E6 consultas
e UI; E7 comandos; E8 admin; E9 frontend custom; E10 i18n/docs; E11 gates, smoke,
aceite e relatório. Parar para revisão, sem commit/push.

## Verificação

`composer test` por etapa; TS com `npm run test` e `npm run types:check`. Fim:
`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`,
`npm run build`; smoke real (custom MISS→HIT, 404/422/stale, analytics gravado,
CSV sem PII, admin toggles, aggregate/prune), logs sem URL/PII e `git status`.
