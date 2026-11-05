# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: implementa Fase 7 do BadgeForge com analytics e providers
```

---

## 🎯 Resumo

Implementa a Fase 7 do BadgeForge em duas frentes complementares: providers JSON customizados (ADR-087) e analytics de visualizações (ADR-086). O objetivo é permitir que usuários criem providers próprios com URL template seguro e que donos de badges acompanhem métricas de visualização sem expor PII, com separação entre planos Free e pago e administração apenas agregada.

## 🛠️ Mudanças Técnicas

- Providers customizados: actions de criar/editar/excluir com validação de template HTTPS, placeholders de path, JSON Pointers (RFC 6901) e probe SSRF reutilizando o guard do endpoint (rede fora não bloqueia; shape inválido retorna 422).
- Models ULID `BadgeCustomProvider` e `BadgeCustomDefinition` com `url_template` oculto, cache de métricas com ETag/stale/fallback versionado por `metrics_version` e rate limiter por dono (free/pago/anônimo).
- Middleware `badgeforge.paid` para escrita no plano pago; `destroy` permanece liberado no downgrade.
- Analytics: models `BadgeView` e `BadgeViewDaily`, recorder best-effort com HMAC de IP/UA (sem PII em claro), leitor de país GeoLite2 opcional via MaxMind e serviços `OwnerAnalytics` (Free x pago, janelas 7/30/90 e CSV sem PII) e `AdminAnalytics` (somente agregado diário).
- Comandos `badgeforge:analytics:aggregate` e `badgeforge:analytics:prune` agendados por migração, com retenção de 90 dias para dados brutos e 400 dias para agregados.
- Migrations para `badge_custom_providers`, `badge_custom_definitions`, `badge_views`, `badge_view_dailies` e seeds de schedule.
- Configurações em `config/badgeforge.php` com novas envs: `BADGEFORGE_CUSTOM_PROVIDERS_LIMIT`, `BADGEFORGE_CUSTOM_LIMIT_*`, `BADGEFORGE_GEOIP_DB`; `url_template` incluído em `dontFlash`.
- Rotas públicas `/api/badges/custom/{ulid}/{metric}/{path}.{svg|png}`, rotas de gestão, export CSV e rotas admin (catálogo de providers, providers customizados e volume).
- Frontend: painel de analytics com gráfico SVG, painel/diálogo/formulário/preview de providers customizados, modo custom no builder, novas páginas admin e navegação, com traduções em pt_BR, pt_PT, en, es e fr.
- Factories, testes PHP/Vitest e ajuste em `AdminTable` para aceitar id string.
- Testes cobrem admin (auditoria via `AdminAction`, bloqueio de visitante/usuário comum e ausência de exclusão de providers no admin), pipeline de analytics (agregação idempotente, prune, agendamento, HMAC e tolerância a falhas), dashboard/CSV sem PII, janelas Free 30/pago 7-30-90, cache MISS/HIT/STALE com revalidação por ETag, fallback 200, 404 para entradas inválidas, precedência sobre rota genérica, rate limit por dono, validação de template/URL, probe DNS contra IP privado e ocultação da origem em props/sessão/toArray; inclui testes unitários de formatadores, path params, chave de cache, template de URL, JSON Pointer, hash de privacidade, extração de referer e leitor GeoIP.

## ⚠️ Impacto/Avisos

- **Banco de dados**: novas migrations criam tabelas de providers customizados, definitions, views e agregados diários, além de seeds de schedule; requer `migrate`.
- **Variáveis de ambiente**: novas chaves `BADGEFORGE_CUSTOM_PROVIDERS_LIMIT`, `BADGEFORGE_CUSTOM_LIMIT_*` e `BADGEFORGE_GEOIP_DB` (opcional) precisam ser configuradas por ambiente.
- **Dependência**: adiciona `maxmind-db/reader` ao Composer para leitura opcional de GeoLite2; exige `composer install/update`.
- **Privacidade**: analytics não armazena IP/User-Agent em claro; CSV e dashboard não expõem PII.
- **Permissões**: escrita de providers customizados exige plano pago (`badgeforge.paid`); exclusão continua permitida após downgrade. O admin não exclui providers, apenas audita e consulta agregados.

close #132

---

[![GitPR](https://img.shields.io/badge/GitPR-4_errors_%C2%B7_181_warnings-red)](https://gitpr.natanfiuza.dev.br/)