# Critérios de aceite — BadgeForge FASE 9 (API de gestão completa)

Gerado em 2026-11-05.
Fonte: `docs/SPEC-BADGEFORGE.md` §16, plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_fase9.md`),
ADR-090 e a emenda da ADR-088.

## Escopo da fase

- **Espelho do painel na API de gestão**: `/api/v1/badgeforge/*` passa a
  cobrir o que o painel `/dashboard/badgeforge` faz, sem presets e sem GET por
  ID. Entram:
  - badges pessoais: `PATCH` do nome, `PATCH .../credential` (write-only) e
    `POST .../refresh` síncrono;
  - logos: `PATCH` de rename;
  - endpoints JSON: `GET`/`POST`/`PATCH`/`DELETE`;
  - providers customizados: `GET`/`POST`/`PATCH`/`DELETE`;
  - analytics: `GET /analytics?window=` com o shape do `OwnerAnalytics` sob
    `data`, **sem `exportUrl`** (CSV segue só na web).
- **PATCH espelha a web**, não JSON Merge Patch: usa os mesmos FormRequests e
  aceita a representação dos campos mutáveis (`name` em badge/logo/endpoint;
  `name` + `metrics` completo no custom provider, com `url_template`
  `prohibited` e conjunto de slugs fixo).
- **Regras herdadas das actions**, sem segunda implementação: plano pago,
  username, quota de logos, PAT cifrado, SSRF/probe de endpoint, probe e
  `metrics_version` do custom provider, limiter de refresh, agregação de
  analytics.
- **Abilities e limites reusados**: `badgeforge:read` + `api-read` nas
  leituras, `badgeforge:write` + `api-write` nas escritas; refresh empilha
  `badgeforge-refresh` (10/min por conta). Nenhuma ability nova.
- **Contrato**: OpenAPI **1.9.0** (aditivo), `ADR-090`, emenda na ADR-088,
  atualização de `API.md`, `OPENAPI.md`, SPEC §§12/14/16/17, glossário,
  `CLAUDE.md` e `RUNBOOK.md`. Sem mudança de UI pública ou i18n.
- **Erros**: envelope `ApiError` (`data`/`meta`/`links` no sucesso); dono
  errado → 404 `not_found`; validação/plano de badge → 422
  `validation_error`; plano pago de custom provider → 403 `forbidden`;
  throttle → 429 `rate_limited` com headers; analytics com bruto ausente →
  503 `badge_unavailable` com `Retry-After: 60` e `no-store`.

Fora da fase: presets na API, GET por ID, CSV de analytics por API, API de
admin, webhooks/bulk, viewer embutido, meta-schema, página de status, mudanças
na docs pública/i18n e os pendentes operacionais (smoke Mercado Pago, checklist
de VPS, rotação de chaves, `TRUSTED_PROXIES`).

## Critérios

| # | Critério | Como verificar |
| --- | --- | --- |
| 1 | `docs/badgeforge/openapi.yaml` é **1.9.0** e documenta as treze operações novas (PATCH de badge, credencial, refresh, PATCH de logo, CRUD de endpoint, CRUD de custom provider e analytics), com `OwnerAnalytics`, `BadgeUnavailable` e placeholders reais | `OpenApiContractTest`; inspeção do YAML |
| 2 | Todo path+method novo da API de gestão aparece em `route:list`, todo path documentado resolve e os nomes de parâmetro batem (`badgeAsset`, `badgeCustomLogo`, `badgeEndpoint`, `badgeCustomProvider`) | `OpenApiContractTest`; `route:list --path=api/v1/badgeforge` |
| 3 | `PATCH /badges/{badgeAsset}` renomeia badge estática e de provider, aceita o nome inalterado, recusa nome de outra conta (404), token somente leitura (403) e Free (422 `validation_error`) | `ManagementApi/BadgeWriteApiTest` |
| 4 | `PATCH /badges/{badgeAsset}/credential` grava o PAT cifrado, nunca o ecoa, invalida SVG/PNG em cache e recusa badge estática/Free (422) | `ManagementApi/BadgeWriteApiTest`; smoke real |
| 5 | `POST /badges/{badgeAsset}/refresh` devolve `url`/`pngUrl`/`generatedAt`; estática/Free → 422; dono errado → 404; estouro → 429 `rate_limited` com `Retry-After` e `X-RateLimit-Limit: 10`; dois tokens da conta dividem a cota | `ManagementApi/BadgeRefreshApiTest`; smoke real |
| 6 | `PATCH /logos/{badgeCustomLogo}` renomeia via `RenameCustomLogo` (mesma action da web), recusa duplicado (422), dono errado (404) e devolve só metadados | `ManagementApi/LogoWriteApiTest`; smoke real |
| 7 | Endpoints: `GET` paginado só do dono, `POST` liberado no Free com probe/SSRF, `PATCH` de nome, `DELETE` com `{id,deleted:true}`; a URL de origem nunca aparece em resposta | `ManagementApi/EndpointApiTest`; smoke real |
| 8 | Custom providers: `GET` com `definitions` e sem `url_template`; Free em `POST`/`PATCH` → 403 `forbidden`; pago cria/edita; `url_template` no PATCH → 422; conjunto de slugs fixo; downgrade permite `DELETE`; dono errado → 404 | `ManagementApi/CustomProviderApiTest`; smoke real |
| 9 | `GET /analytics` devolve `isPaid`/`window`/`windows`/`totals`/`subjects`/`days`/`referers`/`countries`/`formats`/`cache` sob `data`, sem `exportUrl`; Free 30 dias; pago aceita 7/30/90 e cai em 30; isolamento por dono; bruto ausente → 503 `badge_unavailable` (`Retry-After: 60`, `no-store`, sem `SQLSTATE`) | `ManagementApi/AnalyticsApiTest`; smoke real |
| 10 | Cada verbo novo respeita a fronteira `badgeforge:read` × `badgeforge:write` (e `ai:query` continua ortogonal) | `ManagementApi/AbilityIsolationTest` |
| 11 | Erros seguem o envelope `ApiError`: 422 `validation_error` (`error.details.errors.{campo}`), 403 `forbidden`, 404 `not_found`, 429 `rate_limited`, 503 `badge_unavailable` | os testes acima |
| 12 | SPEC §§12/14/16/17, ADR-090, emenda ADR-088, emenda ADR-071, `API.md`, `OPENAPI.md`, glossário, `CLAUDE.md` e `RUNBOOK.md` refletem a entrega | inspeção de `docs/` |
| 13 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`, `npm run build`, `composer audit`, `npm audit --audit-level=high` | os comandos |
| 14 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-05

- `composer test`: Pint ok, PHPStan **0 erros** (nível 7), Pest **1.468 testes /
  24.593 asserções** (1 deprecation pré-existente).
- `npm run test`: Vitest **983 testes / 76 arquivos**.
- `npm run types:check`: limpo.
- `npm run lint:check`: **0 erros**; 1.182 avisos históricos de indentação.
- `npm run build`: concluído (exit 0).
- Audits: `composer audit` sem avisos; `npm audit --audit-level=high` sem
  vulnerabilidades altas (restam 14 moderadas, fora do limiar).
- Rotas: `route:list --path=api/v1/badgeforge` mostra **21 rotas** — as oito da
  F8 mais as treze da F9, com os nomes exatos dos bindings.
- Smoke com rede real (servidor embutido do PHP com o router do `artisan
  serve`, SQLite descartável migrado, plano `pro-monthly`, tokens
  descartáveis; banco, tokens, arquivos e diretório temporário apagados ao
  final) — **SMOKE OK**, todas as asserções verdes:
  - Badge estática: `POST` → **201** com `url`; `PATCH` → **200** com o nome
    novo; `GET /b/smokeowner/{ulid}.svg` → **200**; `GET /analytics` → **200**
    com `totals.views = 1`, `window = 30` e sem `exportUrl`.
  - Badge privada: `POST` com PAT fictício → **201** (`credentialSet: true`);
    `PATCH .../credential` → **200**; o valor nunca aparece no corpo bruto.
  - Badge pública: `POST refresh` → **200** com `url`, `pngUrl` e
    `generatedAt` (fetch real do GitHub).
  - Logo: `POST` multipart → **201**; `PATCH` → **200**; paths do SVG ausentes
    da resposta.
  - Endpoint: `POST` com o JSON real do shields → **422** `validation_error`
    (o probe buscou e recusou por falta de `schemaVersion`, como o contrato
    exige); `POST` com host que não resolve (tolerado) → **201**; `GET
    ?per_page=1` com `meta`; `PATCH` → **200**; `DELETE` → `{deleted:true}`;
    origem nunca devolvida.
  - Custom provider: `POST` com `https://img.shields.io/badge/{label}-passing-green.json`
    e `path_params: ["label"]` → **201** (probe real aceito); lista com
    `definitions` e sem `urlTemplate`; `PATCH` de nome/label → **200**;
    `DELETE` → `{deleted:true}`.
  - Autorização: Free em `POST` custom provider → **403** `forbidden`; token de
    outra conta em `PATCH` de badge alheia → **404** `not_found`; token
    `badgeforge:read` em escrita → **403**.
- `git status` revisado; nenhum commit/push executado.

### Desvios registrados

- O JSON público do shields (`.json`) não carrega `schemaVersion`, então um
  endpoint apontado para ele é corretamente recusado com 422; o caminho de
  sucesso do `POST` no smoke foi coberto com um host que não resolve (falha de
  rede tolerada pela action). O probe com payload válido (`schemaVersion: 1`) é
  coberto por `Http::fake` em `EndpointApiTest`/`EndpointManagementTest`.
- O provider customizado usa a mesma origem shields e aceitou o probe real,
  porque o contrato dele é um JSON arbitrário com JSON Pointer, não o shields
  v1 do endpoint.
- O servidor do smoke foi iniciado por `php -S` com o mesmo router do
  `artisan serve`, para que o processo filho fosse encerrável e não deixasse
  porta aberta no Windows. O comportamento HTTP é o mesmo.
