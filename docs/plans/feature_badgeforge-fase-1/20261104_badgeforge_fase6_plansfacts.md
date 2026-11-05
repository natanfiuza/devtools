# Plano — BadgeForge F6 (Codecov/Coveralls/Laravel/PHP/Python)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Plano de execução: `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase6.md`
- Levantamento: `docs/survey/20261104_badgeforge_fase6_surveyfacts.md`
- SPEC: `docs/SPEC-BADGEFORGE.md` §16 (F6)

## Decisões fechadas

1. Laravel, PHP e Python expõem a **versão de runtime** mais recente.
2. Codecov e Coveralls expõem a cobertura da **branch padrão** (sem `?branch=`).
3. Definições sem coordenadas usam path literal `latest`.
4. Cobertura é **percentual inteiro** (`66.89 → 67%`).
5. Fontes: Codecov API v2, Coveralls `.json`, Packagist `stats.json` (Laravel),
   php.net (PHP) e endoflife.date (Python).
6. Laravel tem duas métricas (`v` e `dt`) lendo o mesmo `stats.json`.
7. Categorias novas: `coverage` e `runtime`.
8. Todos os cinco providers são públicos (`requires_auth=false`,
   `supports_private=false`).

## Catálogo

5 providers novos (codecov, coveralls, laravel, php, python) e 6 definições
novas, totalizando 9 providers e 15 definições. Migração aditiva
`2026_11_04_000002_seed_badgeforge_f6_providers.php` com `insertOrIgnore`;
a migração da F4 não é editada.

## Lacunas estruturais tratadas

- `ProviderParam::listFrom([])` passa a devolver `[]`; o parser aceita path
  literal quando a definição não tem params; `CreateBadgeAsset` usa o
  `path_pattern` literal; o builder TS emite o segmento literal.
- Formatter ganha o branch `coverage` com arredondamento inteiro e faixa 0–100.
- Laravel projeta **as duas métricas** no mesmo fetch, porque `v` e `dt`
  compartilham URL e entrada de cache (mesmo provider).

## Etapas

| Etapa | Entrega |
|---|---|
| E1 | Migração F6 + zero-param + unit tests |
| E2 | 5 clients, registry, formatter coverage + unit tests |
| E3 | Feature de catálogo/endpoint/pessoal + fixture |
| E4 | `providerUrl.ts` literal + Vitest + docs page test |
| E5 | i18n nos 5 idiomas |
| E6 | ADR-085, SPEC, API, OpenAPI 1.6.0, glossário, CLAUDE.md |
| E7 | Aceite, relatório, gates, smoke real e parada sem commit |

## Verificação

`composer test`, `npm run test`, `npm run types:check`, `npm run lint:check`,
`npm run build`; smoke real dos seis badges novos, 404 do path literal
incorreto e HIT compartilhado de `laravel/v` + `laravel/dt`.

## Restrições

Nenhum commit/push. Nenhum texto hardcoded. Nenhuma credencial nova. Falha de
provider continua stale/fallback 200; o catálogo não pode quebrar durante o
deploy (migração aditiva e `QueryException` tolerada).
