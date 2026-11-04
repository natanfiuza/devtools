# Critérios de aceite — BadgeForge FASE 4 (providers + badges pessoais + PAT + docs)

Gerado em 2026-11-03.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16 + plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261103_feature_badgeforge-fase-1_badgeforge_fase4.md`)
+ ADR-080, ADR-081 e ADR-082.

## Escopo da fase

- Providers GitHub/npm/Packagist/Docker: catálogo em tabelas, clientes em
  código, `GET /api/badges/{provider}/{definition}/{path}.{svg|png}` anônimo,
  cache por URL resolvida com payload projetado, condicional, stale-if-error
  e fallback 200 (ADR-080).
- Badges pessoais: tabela `badge_assets`, URL stateless
  `/b/{username}/{ulid}.{svg|png}`, criação só para conta paga, PAT cifrado
  por badge, refresh por POST autenticado, prune e anonimização (ADR-081).
- UI: abas estático/provider no builder, diálogo e painel de badges pessoais,
  gestão em `/dashboard/badgeforge`, i18n nos cinco idiomas.
- Documentação pública: `/{locale}/docs/badgeforge`, namespace `docs`, SEO,
  sitemap, rodapé, tutoriais, API.md e OpenAPI v1.4.0 (ADR-082).

Fora da fase: endpoint JSON/SSRF (F5), analytics/`badge_asset_views`/
exportação CSV (F7), providers customizados e admin (F7), providers
Codecov/Coveralls/Laravel/PHP/Python (F6), refresh forçado por não-dono,
edição de provider/params de badge já criada.

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | Migrações aditivas criam `badge_providers`, `badge_provider_definitions` e `badge_assets` com FKs em cascata, únicos e índices previstos; seeder roda por migração (`insertOrIgnore`), idempotente | `php artisan migrate`/`migrate:rollback`; `ProviderSeederTest` |
| 2 | O catálogo tem 4 providers e 9 definições; `client_key`/`extractor_key` resolvem no registry; `parser_class` é NULL e nunca instanciado; `static` é slug reservado | `ProviderSeederTest`, `ProviderClientRegistryTest`, `ProviderCatalogTest`, `ProviderRouteConflict` (em `ProviderBadgeEndpointTest`) |
| 3 | Path inválido, traversal, controle, tamanho excessivo, contagem errada e dois `allowSlash` são recusados; npm com escopo e `vendor/package` funcionam | `ProviderPathParserTest` |
| 4 | URL builder substitui placeholders com encoding, exige HTTPS e host do template, rejeita userinfo/porta/redirect e placeholder desconhecido | `ProviderUrlBuilderTest` |
| 5 | As 9 métricas saem com `Http::fake`; chamada pública não envia `Authorization`; GitHub stars/forks/issues/license fazem **uma** chamada e compartilham cache; npm `v`/`dm` têm entradas separadas | `ProviderBadgeEndpointTest`, `ProviderClientTest`, `ProviderCacheKeyTest` |
| 6 | 500/429/401/403/404/timeout/JSON inválido/oversize do provider viram stale ou fallback 200, nunca 500; `Retry-After` limita o TTL de fallback; store de cache fora do ar degrada para fetch/fallback | `ProviderBadgeEndpointTest` |
| 7 | `?refresh=1` e `?cacheSeconds=` são ignorados na rota pública; TTL de resposta é limitado por `personal_badges.max_cache_seconds`; headers `X-Badge-Cache` e `Warning: 110` corretos | `ProviderBadgeEndpointTest` |
| 8 | `/api/badges/static/...` continua vencendo a rota de provider; provider/definição desconhecidos/desabilitados e path inválido respondem 404 no envelope `ApiError` | `ProviderBadgeEndpointTest`, `StaticBadgeEndpointTest` |
| 9 | Pago cria badge static e provider; provider é criado **sem fetch**; Free recebe 422 `paid_only` na criação/refresh/edição mas vê a lista existente; convidado é mandado ao login; usuário sem username recebe 422 | `PersonalBadgeManagementTest` |
| 10 | `private=1` exige credencial e `supports_private`; Packagist e npm `dm` recusam com `private_unsupported`; credencial com `private=0` recusa com `credential_not_allowed`; Docker exige `credential_username`; badge alheia responde 404 | `PersonalBadgeManagementTest` |
| 11 | `/b/...` é stateless (sem cookie/sessão), serve o username+ULID corretos, responde 404 de corpo vazio para username errado/excluído/banco fora, serve PNG/download e respeita `expires_at`; `?refresh=1` é ignorado | `PersonalBadgeServingTest` |
| 12 | Regeneração que falha serve o arquivo anterior ou fallback; `DecryptException`/`APP_KEY` trocada não vira 500; troca de PAT invalida arquivo e força nova geração; refresh devolve `{url, pngUrl, generatedAt}` sem token | `PersonalBadgeServingTest`, `BadgeAssetCredentialLeakTest` |
| 13 | PAT ausente de props, resposta, flash, exceção, log, exportação, cache e `toArray()`; valor no banco difere do token; chave de cache leva só fingerprint | `BadgeAssetCredentialLeakTest`, `ProviderCredentialTest`, `ProviderCacheKeyTest` |
| 14 | Storage usa `personal/{username}/{ulid}.{ext}` relativo, escrita atômica, rejeita path fora do prefixo e username inválido; prune remove órfão antigo, preserva referenciado e respeita `--dry-run` | `BadgeAssetStorageTest`, `PersonalBadgePruneTest` |
| 15 | Props exatas para convidado/Free/pago: catálogo público sempre presente, `personalBadges` presente para o dono (inclusive Free), sem credencial, consulta não seleciona colunas sensíveis e `QueryException` devolve lista vazia | `BadgeForgeToolPropsTest`, `BadgeForgePageTest`, `BadgeForgeAccountPropsTest` |
| 16 | Exportação inclui metadados das badges pessoais sem credencial/fingerprint; anonimização apaga linhas e arquivos | `AccountPanelTest`, `ProfileUpdateTest` |
| 17 | Builder: aba provider monta URL para as 9 métricas e nunca manda `cacheSeconds`; preview é `<img>`; modo estático continua sem requisição por digitação; PAT é password write-only e limpo; free vê CTA e lista somente leitura | `badgeforge-provider.test.ts`, `badgeforge-personal.test.ts`, `badgeforge-component.test.ts` |
| 18 | Docs em `/{locale}/docs/badgeforge` nos cinco idiomas: catálogo, 9 métricas, exemplos, URL pessoal, TTL, erros, segurança, CTAs e links de rodapé; meta de servidor, sitemap e `docs` no namespace | `BadgeForgeDocsPageTest`, `docs-badgeforge.test.ts`, `SeoMetaTest`, `SitemapTest`, `TranslationsParityTest` |
| 19 | API.md e OPENAPI.md v1.4.0 documentam provider e badge pessoal como entregues; tutoriais apontam para a página e para a revogação real | inspeção de `docs/badgeforge/` e `docs/tutorial/` |
| 20 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 21 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-04

- `composer test`: Pint ok, PHPStan 0 erros, Pest **1.090 testes / 19.801
  asserções**.
- `npm run test`: Vitest **940 testes / 72 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: 0 erros; 1.180 avisos de indentação pré-existentes no
  repositório (nenhum erro nos arquivos da F4, exceto os dois
  `vue/one-component-per-file` intencionais do teste).
- `npm run build`: concluído (aviso de chunk grande já conhecido).
- Rotas: `api/badges/static/{label}/{message}.{ext}` + provider (2),
  `b/{username}/{badgeAsset}.{ext}` (1), `{locale}/docs/badgeforge` (1) e
  5 rotas de gestão em `badgeforge/badges` (store/update/credential/refresh/destroy).
- Smoke com rede real (`php artisan serve`, 2026-11-04):
  - 9 URLs de provider responderam **200 SVG**; GitHub stars/forks/issues/
    license compartilharam a entrada (`MISS` seguido de `HIT`).
  - `npm/v/vue` retornou `3.5.44`; `npm/dm/vue` `69.6M`; `docker/pulls/library/nginx`
    `13.5B`; `packagist/v/laravel/framework` `v13.40.0`.
  - **Desvio encontrado e corrigido**: `packagist/dt` respondia fallback
    `unavailable` porque o JSON completo do pacote tem 7,7 MB e estoura
    `providers.max_body_bytes` (1 MiB). A definição passou a usar
    `https://packagist.org/packages/{vendor}/{package}/stats.json` (~14 KB) e
    o cliente lê `downloads.total` no topo; o smoke passou a devolver
    `611.530.347`. Teste de seeder guarda a URL enxuta.
  - `/api/badges/static/build/passing.svg` respondeu 200 (rota estática vence
    a de provider); `?refresh=1&cacheSeconds=86400` na URL pública devolveu
    `max-age=300` e `X-Badge-Cache: HIT` (parâmetros ignorados).
  - Provider/definição desconhecidos e path inválido → **404** `ApiError`;
    PNG → `200 image/png`.
  - Badge pessoal criada via action para o smoke (usuário temporário
    `smokef4`, removido depois): SVG/PNG, `ETag` → **304**, `download=1`,
    `max-age=3600`, sem `Set-Cookie`, username errado → **404** e provider
    pessoal buscando o GitHub de verdade.
  - Docs `/{locale}/docs/badgeforge` → 200 nos cinco idiomas, com título
    traduzido; sitemap listou as cinco URLs.
  - `POST /badgeforge/badges/{id}/refresh` como convidado sem token CSRF
    respondeu 419 (artefato do `curl`); o redirecionamento ao login é coberto
    pelo teste de feature.
- `git diff tests/Fixtures/badgeforge/expected`: vazio — fixtures históricas
  intactas.
- `git status` revisado; nenhum commit/push executado. O diretório
  `docs/colors/` já estava não rastreado e é de outra tarefa, não desta fase.

## Notas e desvios do plano

- `badge_assets` sem `slug`, com `user_id` obrigatório e `svg_path`/`png_path`
  no lugar de `source_path` (ADR-081; SPEC §7 atualizado).
- Storage em `personal/{username}/{ulid}.{ext}` (não na raiz), para não
  colidir com `static/` e permitir conferir o prefixo.
- A criação de badge provider não faz fetch; o primeiro `GET` público gera a
  imagem. Um provider fora do ar não impede criar.
- `?refresh=1` na URL pública e na badge pessoal é ignorado; atualizar é POST
  autenticado do dono.
- O endpoint de downloads do Packagist foi trocado para `stats.json` durante o
  smoke (ver acima); a API pública documentada é a mesma.
- A doc de provider é uma página única por idioma, com seções por provider, em
  vez de uma rota por provider: o catálogo do banco alimenta as seções e evita
  URLs vazias.
