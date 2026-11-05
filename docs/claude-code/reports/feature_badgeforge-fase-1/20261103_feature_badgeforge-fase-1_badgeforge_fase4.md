# BadgeForge — Fase 4 (providers + badges pessoais + PAT + docs públicas)

- Data: 2026-11-03 (execução concluída em 2026-11-04)
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261103_feature_badgeforge-fase-1_badgeforge_fase4.md`
- Levantamento/plano da skill: `docs/survey/20261103_badgeforge_fase4_surveyfacts.md`,
  `docs/plans/feature_badgeforge-fase-1/20261103_badgeforge_fase4_plansfacts.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-4.md`
- ADRs: `docs/adr/ADR-080-badgeforge-camada-de-providers.md`,
  `docs/adr/ADR-081-badgeforge-badges-pessoais-e-pat.md`,
  `docs/adr/ADR-082-badgeforge-docs-publicas-e-i18n.md`

## Objetivo

Entregar a F4 do SPEC-BADGEFORGE: badges dinâmicas de GitHub, npm, Packagist e
Docker Hub, badges pessoais com PAT por badge e a página pública de
documentação, nos cinco idiomas. A imagem continua stateless e consumível por
`<img>`; o item público é lido anonimamente e o item privado usa o PAT do
usuário pagador, cifrado por badge. O builder estático continua 100% no
navegador.

Decisões da sessão de grill: execução contínua E1→E9; **9 métricas**
(`github/stars|forks|issues|license`, `npm/v|dm`, `packagist/dt|v`,
`docker/pulls`); `badge_assets` sem `slug`, com `user_id` obrigatório;
`source_path` vira `svg_path`/`png_path`; storage `personal/{username}/{ulid}`;
smoke com rede real.

## Resumo executivo

- `GET /api/badges/{provider}/{definition}/{path}.{svg|png}` serve as 9
  métricas anonimamente, com cache por URL resolvida, condicional,
  `stale-if-error` e fallback 200. Falha do provider **nunca** vira 500.
- As 4 definições do GitHub compartilham **uma** chamada e **uma** entrada de
  cache; npm `v`/`dm` e Packagist `dt`/`v` têm entradas próprias.
- O catálogo vive em `badge_providers`/`badge_provider_definitions`; o
  comportamento HTTP é código (`client_key`/`extractor_key`). `parser_class`
  nasceu `NULL` e nunca é instanciada.
- `/b/{username}/{ulid}.{svg|png}` é stateless, sem sessão/CSRF/auth, com
  `ETag`/304, PNG, `download=1` e 404 de corpo vazio (nunca a página Inertia).
- O PAT usa cast `encrypted`, é `hidden`, write-only na UI e nunca entra em
  URL, log, props, flash, cache ou exportação; a chave de cache leva só o
  HMAC-SHA256 (`credential_fingerprint`).
- Só conta paga cria badge pessoal; no downgrade as existentes continuam
  servindo e podem ser copiadas/excluídas, e criar/renomear/trocar PAT/refresh
  responde 422 `paid_only`.
- `POST /badgeforge/badges/{id}/refresh` é a forma autenticada de atualizar;
  `?refresh=1` na URL pública é ignorado.
- O builder ganhou abas estático/provider, preview por `<img>`, diálogo e
  painel de badges pessoais, tudo com i18n nos cinco idiomas.
- `/{locale}/docs/badgeforge` documenta providers, badges pessoais/PAT, cache,
  erros e segurança, com tabela gerada do catálogo, meta de servidor, sitemap
  e link no rodapé.
- Gates finais: Pint/PHPStan ok, Pest **1.090 testes / 19.801 asserções**,
  Vitest **940 testes**, `types:check` limpo, `lint:check` 0 erros, `build`
  concluído.
- Smoke com rede real: 8 métricas retornaram valor e 1 revelou um bug real
  (Packagist `dt`), corrigido e revalidado (ver "Desvios").

## Decisões (ADR-080, ADR-081 e ADR-082)

### ADR-080 — camada de providers

1. Metadados no banco, comportamento no código. `client_key` resolve o cliente
   HTTP no registry; `extractor_key` escolhe a métrica no payload projetado.
   `parser_class` é inerte.
2. Item público sempre anônimo; `ProviderCredential` só existe no caminho da
   badge pessoal.
3. Cache `badgeforge:provider:sha256(provider|URL|public|fingerprint)` com
   payload **projetado** (sem descrição/README), condicional com `If-None-Match`,
   stale utilizável e fallback curto.
4. TTL de resposta = `min(provider TTL, max_cache_seconds)`; `?cacheSeconds=` e
   `?refresh=1` ignorados.
5. Rota de provider registrada depois da estática; `static` é slug reservado;
   path/URL inválidos e provider desconhecido → 404 `ApiError`.
6. Limites em config; o endpoint de downloads do Packagist usa `stats.json`
   justamente para caber em `max_body_bytes`.

### ADR-081 — badges pessoais e PAT

1. `badge_assets` com `user_id` obrigatório, sem `slug`; URL é username +
   ULID; `source_path` do SPEC virou `svg_path`/`png_path`.
2. Storage relativo em `personal/{username}/{ulid}.{ext}`, escrita atômica e
   prefixo conferido na leitura.
3. PAT cifrado por badge, fingerprint HMAC na chave de cache, `$dontFlash` nos
   FormRequests e nenhuma rota de "remover credencial".
4. Criação provider sem fetch; primeiro `GET` público gera; refresh é POST
   autenticado do dono; downgrade serve/copia/exclui mas bloqueia edição.
5. Prune de órfãos sob `personal/` e exportação/anonimização sem credencial.

### ADR-082 — docs públicas e i18n

1. `/{locale}/docs/badgeforge` no grupo de idioma, com meta de servidor,
   sitemap, `useRoutes`, rodapé e `resolveLayout`.
2. Tabela de providers gerada de `ProviderCatalog::forDocs()`; snippets
   montados no cliente a partir do catálogo e dos endpoints.
3. Namespace `docs` nos cinco idiomas, coberto por `TranslationsParityTest` e
   `i18n-messages.test.ts`.
4. Exceção explícita: label/message da imagem são neutros de idioma; interface
   e documentação são traduzidas.

## O que foi entregue, por etapa

| Etapa | Entrega |
|---|---|
| E1 | 3 migrações aditivas + seeder idempotente, 3 models, 3 factories, config `providers`/`personal_badges` |
| E2 | `ProviderCatalog`, `ProviderPair`, `ProviderParam`, `ProviderPathParser`, `ProviderUrlBuilder`, `ProviderClientRegistry` |
| E3 | 4 clientes + `AbstractProviderClient`, `ProviderFetchRequest`, `ProviderPayload`, `ProviderCredential`, `ProviderFetchException`, `ProviderMetricFormatter` |
| E4 | `ProviderMetrics`, `ProviderCache`, `ProviderCacheKey`, `CachedProviderPayload`, `ProviderBadgeComposer`, `ProviderBadgeController`, `BadgeImageResponder`, `BadgeSpecBuilder` |
| E5 | `Create/Update/SetCredential/Refresh/DeleteBadgeAsset`, FormRequests, `BadgeAssetStorage`, `PersonalBadgeGuard`, `PersonalBadgeRenderer`, `routes/public.php`, refresh POST, prune, exportação/anonimização |
| E6 | Props exatas (`providers`, endpoints, `personalBadges`, `canCreate...`), fixture regravada, tipos TS, sem colunas sensíveis na consulta |
| E7 | `ModeTabs`, `ProviderForm`, `ProviderPreview`, `PersonalBadgeDialog`, `PersonalBadgesPanel`, `lib/providerUrl.ts`, dashboard, i18n 5 idiomas |
| E8 | `BadgeForgeDocsController`, `BadgeForgeDocs.vue`, namespace `docs`, SEO/sitemap/rodapé, tutoriais, API.md e OPENAPI.md v1.4.0 |
| E9 | ADR-080/081/082, emendas em ADR-002/064/068/069/071, SPEC §2/§6–§11/§14/§16/§17, glossário, CLAUDE.md, aceite, relatório e gates |

## Arquivos principais

### PHP

- `app/Http/Controllers/BadgeForge/ProviderBadgeController.php`
- `app/Http/Controllers/BadgeForge/PersonalBadgeController.php`
- `app/Http/Controllers/BadgeForge/PersonalBadgeManagementController.php`
- `app/Http/Controllers/BadgeForgeDocsController.php`
- `app/Actions/BadgeForge/{Create,Update,SetCredential,Refresh,Delete}BadgeAsset.php`
- `app/Models/{BadgeProvider,BadgeProviderDefinition,BadgeAsset}.php`
- `app/Support/BadgeForge/Providers/*` (catálogo, parser, URL, registry,
  clientes, cache, métricas, formatter, credencial)
- `app/Support/BadgeForge/Personal/*` (storage, guard, renderer)
- `app/Support/BadgeForge/{BadgeImageResponder,BadgeSpecBuilder}.php`
- `database/migrations/2026_11_03_00000{1..4}_*.php`
- `routes/public.php`, `bootstrap/app.php`, `routes/api.php`,
  `routes/badgeforge.php`, `routes/web.php`

### TypeScript/Vue

- `resources/js/tools/badgeforge/{ModeTabs,ProviderForm,ProviderPreview,PersonalBadgeDialog,PersonalBadgesPanel}.vue`
- `resources/js/tools/badgeforge/lib/providerUrl.ts`
- `resources/js/pages/BadgeForgeDocs.vue`
- `resources/js/pages/dashboard/BadgeForge.vue`
- `resources/js/composables/useRoutes.ts`, `resources/js/lib/inertiaApp.ts`,
  `resources/js/layouts/PublicLayout.vue`

### Docs

- `docs/adr/ADR-080`, `ADR-081`, `ADR-082`
- `docs/SPEC-BADGEFORGE.md`, `docs/glossario.md`, `CLAUDE.md`
- `docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md`
- `docs/tutorial/{github,npm,docker}-token.md`
- `docs/acceptance/BADGEFORGE-FASE-4.md`

## Testes

### Pest (F4)

- Unit: `ProviderPathParserTest`, `ProviderUrlBuilderTest`,
  `ProviderClientTest`, `ProviderClientRegistryTest`,
  `ProviderCacheKeyTest`, `ProviderMetricFormatterTest`,
  `ProviderCredentialTest`, `BadgeAssetStorageTest`.
- Feature: `ProviderBadgeEndpointTest`, `ProviderCatalogTest`,
  `ProviderSeederTest`, `PersonalBadgeManagementTest`,
  `PersonalBadgeServingTest`, `BadgeAssetCredentialLeakTest`,
  `PersonalBadgePruneTest`, `BadgeForgeDocsPageTest`, além de atualizações em
  `BadgeForgePageTest`, `BadgeForgeAccountPropsTest`, `SeoMetaTest`,
  `SitemapTest`, `TranslationsParityTest` e exportação/anonimização.

### Vitest (F4)

- `badgeforge-provider.test.ts` — URL das 9 métricas, escopo npm, cosméticos,
  ausência de `cacheSeconds`, preview por `<img>`.
- `badgeforge-personal.test.ts` — free/pago, PAT write-only, URL/cópia,
  refresh por POST, exclusão.
- `docs-badgeforge.test.ts` — 4 providers/9 métricas, exemplos, URL pessoal,
  TTL interpolado, CTAs e textos de erro/segurança.
- Atualizações em `badgeforge-component.test.ts` e `badgeforge-account.test.ts`.

## Gates (2026-11-04)

```text
composer test      Pint ok · PHPStan 0 erros · Pest 1.090 testes / 19.801 asserções
npm run test       Vitest 940 testes / 72 arquivos
npm run types:check  limpo
npm run lint:check   0 erros · 1.180 avisos pré-existentes de indentação
npm run build        concluído
```

### Rotas

```text
GET  api/badges/static/{label}/{message}.{ext}        badges.static
GET  api/badges/{provider}/{definition}/{path}        badges.provider
GET  b/{username}/{badgeAsset}.{ext}                  badges.personal
GET  {locale}/docs/badgeforge                         badgeforge-docs
POST badgeforge/badges                                badgeforge.badges.store
PATCH badgeforge/badges/{badgeAsset}                  badgeforge.badges.update
PATCH badgeforge/badges/{badgeAsset}/credential       badgeforge.badges.credential
POST badgeforge/badges/{badgeAsset}/refresh           badgeforge.badges.refresh
DELETE badgeforge/badges/{badgeAsset}                 badgeforge.badges.destroy
```

## Smoke com rede real

Servidor local (`php artisan serve`, 2026-11-04), sem mocks:

- 9 URLs de provider → 200 SVG. GitHub stars/forks/issues/license: primeira
  `MISS`, as seguintes `HIT` (entrada compartilhada por URL).
- Valores observados: npm `v/vue` = `3.5.44`; npm `dm/vue` = `69.6M`;
  docker `pulls/library/nginx` = `13.5B`; packagist `v/laravel/framework` =
  `v13.40.0`; packagist `dt/laravel/framework` = `611.530.347` após a correção.
- `/api/badges/static/build/passing.svg` → 200 (estática vence a rota de
  provider).
- `?refresh=1&cacheSeconds=86400` na URL pública → `max-age=300`,
  `X-Badge-Cache: HIT` (parâmetros ignorados).
- Provider desconhecido e path inválido → 404 `ApiError`; PNG → 200.
- Badge pessoal temporária (`smokef4`, criada via action e removida no fim):
  SVG/PNG, `ETag` → 304, `download=1`, `max-age=3600`, sem `Set-Cookie`,
  username errado → 404 e provider pessoal buscando o GitHub real.
- Docs → 200 nos cinco idiomas, com `<title>` traduzido; sitemap listou as
  cinco URLs.
- `POST .../refresh` como convidado sem CSRF → 419 (artefato do `curl`); o
  redirecionamento ao login é coberto pelo teste de feature.

## Desvios e problemas encontrados

1. **Packagist `dt` estourava `max_body_bytes` (corrigido).** O template
   `https://packagist.org/packages/{vendor}/{package}.json` devolve 7,7 MB
   para `laravel/framework` (todas as versões), acima do teto de 1 MiB; a
   imagem caía em fallback `unavailable`. A definição passou a usar
   `.../{vendor}/{package}/stats.json` (~14 KB, mesmo `downloads.total`) e o
   cliente lê `downloads` no topo com fallback para `package.downloads`. O
   teste de seeder prende a URL enxuta. ADR-080 e `SPEC` atualizados; a base
   local foi ajustada à mão porque a migração já tinha rodado.
2. **`docs/colors/` não rastreado**: diretório de outra tarefa (SPEC-COLORS),
   já presente antes da fase; não foi tocado.
3. **419 no refresh via curl**: falta de token CSRF no smoke, não um defeito
   de autorização; o comportamento de convidado é testado no feature.
4. **`open_issues_count` do GitHub inclui PRs**: documentado na página
   pública; não foi "corrigido" com segunda chamada.

## Riscos residuais

- API do provider pode mudar de forma: a projeção estrita transforma a
  mudança em fallback, mas cada provider depende de shape conhecido.
- Trocar `APP_KEY` invalida os PATs; o usuário precisa re-credencializar.
- Renomear o username muda a URL pública (o path do arquivo é congelado); a
  documentação avisa.
- CDN pode segurar badge pessoal excluída até o teto de 1 h.
- O primeiro acesso a uma badge provider pessoal é síncrono.

## Próximos passos (fora desta fase)

- F5: endpoint JSON com proteção SSRF e limites por plano.
- F6: providers Codecov/Coveralls/Laravel/PHP/Python.
- F7: providers customizados, analytics/`badge_asset_views` e admin.
- F8: OpenAPI completo e hardening.

## Git

- Nenhum commit ou push foi executado (regra do `CLAUDE.md`).
- `git status` revisado; as mudanças ficam para o usuário revisar e commitar
  pelo `gitpr`.
