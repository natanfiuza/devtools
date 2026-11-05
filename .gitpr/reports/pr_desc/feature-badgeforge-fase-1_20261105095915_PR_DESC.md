# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona API JSON de gestão do BadgeForge e hardening
```

---

## 🎯 Resumo

Fecha a superfície de máquina do BadgeForge (Fase 8): contas pagas passam a gerenciar logos e badges pessoais por Bearer token, sem abrir o painel, e o catálogo de providers/estilos é servido em JSON cacheável para alimentar o builder do cliente. O `ai:query` do AI SQL Builder permanece ortogonal — grupos de rota separados impedem herança de escopo entre as superfícies. Junto com a API, entra o hardening operacional (ADR-089) que evita o pior cenário para uma imagem pública: falha de banco virar 404 em CDN e a badge sumir do README do usuário até o cache expirar.

## 🛠️ Mudanças Técnicas

- **API de gestão `/api/v1/badgeforge/*` (ADR-088)**: catálogo público (`/providers`, `/styles`) com `Cache-Control: public` e ETag, listagem/criação/exclusão de logos e badges pessoais (paginação 1–100, `per_page` default 20). Recurso de outra conta responde 404 `not_found`, nunca 403; credencial e paths nunca voltam no payload.
- **Abilities ortogonais**: `badgeforge:read` e `badgeforge:write` no `ApiTokenController`, com allowlist em `StoreApiTokenRequest` e checkboxes na tela `/settings/api-tokens`. Tokens novos mantêm `ai:query` como default; abilities do BadgeForge não são concedidas retroativamente a tokens existentes.
- **Rate limits dedicados**: `api-write` (30/min por token, `API_RATE_WRITE_PER_MINUTE`) para POST/DELETE da gestão; `badgeforge-images` (6000/min por IP, `BADGEFORGE_SERVING_LIMIT_PER_MINUTE`) para imagens anônimas (estática, provider, pessoal) contra varredura, sem substituir o limite por dono de endpoint/custom.
- **Hardening (ADR-089)**: `/up` substituído por `HealthController` que sonda banco, cache e disco do PNG — 200 `up` ou 503 `down` com mapa de checks e sem vazar exceção. `QueryException` na superfície de imagem vira 503 `badge_unavailable` com `Retry-After: 60` e `Cache-Control: no-store`; personal badge passa a responder no envelope `ApiError` (`not_found` / `badge_unavailable`) em vez de corpo vazio. Throttle da gestão diferencia GET (export de analytics) — recusa em JSON com `Retry-After` — de ações de diálogo, que mantêm o toast Inertia.
- **CSP própria para SVG**: imagens `image/svg+xml` recebem `default-src 'none'; style-src 'unsafe-inline'`, porque a política da aplicação não vale para um documento carregado sozinho.
- **OpenAPI 3.1.0 v1.8.0**: `docs/badgeforge/openapi.yaml` (1155 linhas) servida em `/openapi/badges.yaml` com `%APP_URL%` resolvido server-side e cache de 24h; `OpenApiController::yaml()` extraído como helper. Teste de contrato (`OpenApiContractTest`) valida `$ref`s e paridade bidirecional spec↔rotas, incluindo nomes reais dos placeholders de path.
- **Refactor**: `ParseCustomLogoUpload` extraído como action compartilhada entre o controller web e a API — o SVG cru nunca sai do parser, só `viewBox`, paths normalizados e `size_bytes` chegam ao banco.
- **Docs**: nova seção "API JSON" em `BadgeForgeDocs.vue` com base URL, escopos e links para `/settings/api-tokens` e para a spec; traduções em en/es/fr/pt_BR/pt_PT.
- **CI**: `.github/workflows/ci.yml` com gates de install (sem lockfile, conforme ADR-014), audits, Pint+PHPStan+Pest, ESLint, Vue TSC, Vitest e build. `format:check` fica explicitamente de fora por dívida pre-existente mensurável (162 arquivos, inclusive `Dashboard.vue`), documentada no header — não via `continue-on-error` que mascararia falhas novas.

## ⚠️ Impacto/Avisos

- **Banco de dados**: sem migrations nesta fase. Nenhuma alteração de schema.
- **Envs novas (todas com default)**: `API_RATE_WRITE_PER_MINUTE` (30), `BADGEFORGE_CATALOG_CACHE_SECONDS` (300), `BADGEFORGE_SERVING_LIMIT_PER_MINUTE` (6000). Deploy sem configurar mantém comportamento esperado.
- **Dependência nova**: `symfony/yaml ^7.4` em `require-dev` (parse da spec pelo teste de contrato).
- **Mudança de contrato observável**: personal badge em `/b/{user}/{ulid}.{ext}` deixa de responder 404 com corpo vazio e passa a devolver o envelope JSON (`error.code`). Consumidores `<img>` não são afetados (status permanece 404); quem lia o body precisa tratar o envelope. Falha de banco na superfície de imagem agora é 503, nunca 404 — CDNs que fazem negative-cache precisam respeitar o `no-store` para não guardar a falha.
- **Health check**: `GET /up` faz round-trip em DB, cache e disco do PNG. Balanceador/cron de deploy deve aceitar timeout maior que o anterior.
- **Throttle**: imagens anônimas passam a ter cap por IP; tráfego acima de 6000/min por IP recebe 429 `rate_limited`. É teto contra varredura, não limite de uso normal — ajustável por env sem deploy de código.

close #134

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_44_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)