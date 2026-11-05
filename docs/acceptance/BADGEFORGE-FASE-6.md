# Critérios de aceite — BadgeForge FASE 6 (providers Codecov/Coveralls/Laravel/PHP/Python)

Gerado em 2026-11-04.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16 + plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase6.md`)
+ ADR-085.

## Escopo da fase

- Catálogo aditivo: cinco providers (`codecov`, `coveralls`, `laravel`, `php`,
  `python`) e seis definições, somando **nove providers e quinze definições**
  com as da F4 (ADR-085).
- Cobertura: Codecov (`totals.coverage`) e Coveralls (`covered_percent`) da
  branch padrão, formatados como percentual inteiro (`66.89 → 67%`).
- Runtime: versão estável de Laravel (`versions[]`, pulando `-dev`), PHP
  (primeiro release de `php.net`) e Python (`endoflife.date`), sempre em path
  literal `latest`.
- Zero params: `required_params=[]` e `path_pattern=latest` aceitos pelo
  parser PHP, por `CreateBadgeAsset` (badge pessoal) e pelo builder TS; path
  fora do literal responde 404 / não gera snippet.
- Laravel projeta `version` e `downloads` numa única chamada, para que `v` e
  `dt` compartilhem a entrada de cache.
- Categorias novas `coverage` e `runtime`; os cinco providers são públicos
  (`supports_private=false`, sem PAT nem badge pessoal privada).
- i18n nos cinco idiomas (métricas, descrições, meta e subtítulo da página
  pública), ADR-085, SPEC §§6/8/10/14/16/17, API.md, OPENAPI.md 1.6.0,
  glossário e `CLAUDE.md`.

Fora da fase: badge por branch, EOL/versões antigas, downloads por linguagem,
providers customizados (F7), analytics (F7) e OpenAPI 3.1 completo (F8).

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | Migração aditiva cria os cinco providers e seis definições com `insertOrIgnore`, sem editar a migração da F4 e sem sobrescrever ajuste administrativo; `down()` remove só os slugs da F6 | `ProviderSeederTest`; `migrate:rollback --step=1` + `migrate --force` |
| 2 | Catálogo final tem 9 providers / 15 definições, todos com `parser_class` NULL e `client_key` conhecido | `ProviderSeederTest`, `ProviderCatalogTest` |
| 3 | Categorias `coverage` (codecov, coveralls) e `runtime` (laravel, php, python); `requires_auth` só em github/npm/docker | `ProviderSeederTest` |
| 4 | `ProviderParam::listFrom([])` devolve `[]`; parser aceita só o literal `latest` e recusa vazio, `Latest`, `latest/x`, `8.5`, `{owner}` e dot segment | `ProviderParamTest`, `ProviderPathParserTest` |
| 5 | `CreateBadgeAsset` valida o literal para definição sem params e continua recusando param extra | `PersonalBadgeManagementTest` |
| 6 | `ProviderUrlBuilder` resolve template sem placeholders; cinco clients novos projetam os shapes corretos e recusam shape inválido | `ProviderUrlBuilderTest`, `ProviderClientTest` |
| 7 | Laravel projeta `version` (primeira entrada estável) e `downloads` (`downloads.total`) na mesma chamada; sem estável, só a versão fica nula | `ProviderClientTest` |
| 8 | Cobertura vira percentual inteiro e valor fora de 0–100, NaN/INF ou tipo inválido vira `null` (fallback 200) | `ProviderMetricFormatterTest` |
| 9 | Rota pública serve as seis métricas novas com HIT/MISS/STALE, e `laravel/v/latest` + `laravel/dt/latest` compartilham uma chamada (MISS → HIT) | `ProviderBadgeEndpointTest`; smoke real |
| 10 | Path literal inválido na rota pública responde 404 `not_found`; PNG e `download=1` funcionam nas métricas novas | `ProviderBadgeEndpointTest`; smoke real |
| 11 | Badge pessoal aceita `params: []` para PHP/Laravel e recusa param extra; storage grava `[]` e não chama rede | `PersonalBadgeManagementTest` |
| 12 | Página pública lista 9 providers / 15 métricas, mostra `/api/badges/php/v/latest.svg` e as descrições novas nos cinco idiomas | `BadgeForgeDocsPageTest`, `badgeforge-provider.test.ts`, `docs-badgeforge.test.ts`, `TranslationsParityTest` |
| 13 | Builder TS monta as URLs literais e recusa literal inválido sem gerar snippet | `badgeforge-provider.test.ts` |
| 14 | Fixture `tool-props.json` regenerada do payload real (ordem e formato) e consumida por PHP e TS | `BadgeForgePageTest`, `BadgeForgeDocsPageTest` |
| 15 | SPEC, ADR-085, API.md, OPENAPI.md 1.6.0, glossário e `CLAUDE.md` refletem a entrega | inspeção de `docs/` |
| 16 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 17 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-04

- `composer test`: Pint ok, PHPStan 0 erros (nível 7), Pest **1.226 testes /
  20.522 asserções**.
- `npm run test`: Vitest **952 testes / 73 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: 0 erros; 1.180 avisos de indentação pré-existentes.
- `npm run build`: concluído (exit 0; aviso de timings do rolldown já
  conhecido).
- Migração: `migrate:rollback --step=1` removeu e `migrate --force`
  reaplicou `2026_11_04_000002_seed_badgeforge_f6_providers`; contagem final
  **9/15**.
- Rotas: `api/badges/{provider}/{definition}/{path}` inalterada; nenhuma rota
  nova (os providers entram pelo catálogo).
- Smoke com rede real (`php artisan serve`, 2026-11-04):
  - `codecov/coverage/psf/requests.svg` → **200** `coverage: 67%`, `MISS` e
    depois `HIT`.
  - `coveralls/coverage/twbs/bootstrap.svg` → **200** `coverage: 94%`.
  - `laravel/v/latest.svg` → **200** `laravel: v13.40.0` (`MISS`).
  - `laravel/dt/latest.svg` → **200** `downloads: 612M` com **`HIT`**,
    comprovando a entrada de cache compartilhada.
  - `php/v/latest.svg` → **200** `php: 8.5.12`; `python/v/latest.svg` →
    **200** `python: 3.15.2`.
  - `php/v/8.5.svg`, `php/v/Latest.svg` e `laravel/v/latest/extra.svg` →
    **404** `not_found`.
  - PNG de `python/v/latest` → **200** `image/png`; `download=1` em
    `coveralls/coverage/twbs/bootstrap.svg` → `Content-Disposition: attachment;
    filename="coverage-94.svg"` com variante RFC 5987.
  - Cache inspecionado no store: a entrada do Codecov carrega só
    `metrics.coverage`; a do Laravel carrega `version` e `downloads`, sem
    corpo cru da origem.
  - `storage/logs/laravel.log` não recebeu nenhuma linha `local.` nova no
    smoke; nenhuma URL de provider, payload ou credencial apareceu.
- `git status` revisado; nenhum commit/push executado.

## Notas e desvios do plano

- **PHPStan no client Python**: o payload do endoflife.date é uma lista, mas
  o contrato do método é `array<string, mixed>`; `$data[0]` disparava
  `nullCoalesce.offset` e tornava o `is_array` “impossível”. O client passou a
  usar `reset($data)`, como o client do PHP, sem mudar o comportamento.
- **Valores do smoke**: downloads do Laravel e versões correntes mudam com o
  tempo (o aceite registra `612M`, `v13.40.0`, `8.5.12` e `3.15.2` como os
  valores observados na data); o teste automatizado usa shapes fake e não
  depende deles.
- O subtítulo e o `meta.description` da página pública citavam só os quatro
  providers da F4; além do previsto no plano, foram atualizados para incluir
  cobertura e versões de runtime.
- Nenhuma alteração de layout: o formulário já não renderiza inputs quando a
  definição não tem params, então a UI passou a funcionar sem mudança
  estrutural.
