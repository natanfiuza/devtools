# Levantamento — BadgeForge F6 (Codecov/Coveralls/Laravel/PHP/Python)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Escopo: `docs/SPEC-BADGEFORGE.md` §16, linha “F6: providers
  Codecov/Coveralls/Laravel/PHP/Python.”

## Contexto

F1–F5 entregues. A F4 criou o pipeline de providers: catálogo em banco
(`badge_providers`, `badge_provider_definitions`) + clients em código
(`client_key`), parser de path, builder de URL, cache por URL resolvida,
formatter e composer. A F6 acrescenta cinco providers e duas formas de métrica
que a F4 ainda não representava: cobertura percentual e versão de runtime sem
coordenadas.

## Decisões das rodadas de grill

### Rodada 1

- **O que Laravel/PHP/Python medem?** Versão de runtime (não downloads de
  linguagem, não EOL).
- **Codecov/Coveralls cobrem branch?** Só a branch padrão.
- **Path das versões únicas?** Literal `latest`.
- **Formato da cobertura?** Percentual inteiro.

### Rodada 2

- **Fonte da versão?** Oficial + endoflife: Packagist `stats.json` (Laravel),
  php.net (PHP) e endoflife.date (Python).
- **Métricas do Laravel?** Versão + downloads.
- **Categorias do catálogo?** `coverage` e `runtime`.

## Fatos levantados

### Codecov

- URL: `https://api.codecov.io/api/v2/github/{owner}/repos/{repo}/`.
- Campo: `totals.coverage` (float). `psf/requests` respondeu `66.89`.
- Sem `branch` na URL ⇒ branch padrão.

### Coveralls

- URL: `https://coveralls.io/github/{owner}/{repo}.json`.
- Campo: `covered_percent` (float). `twbs/bootstrap` respondeu `94.148`.
- Vários repositórios sem Coveralls respondem 404 (só repositórios com dados
  respondem 200); `?branch=` existe mas não será usado.
- O `.json` representa o **último build**, que pode não ser da branch padrão —
  caveat documentado na ADR-085.

### Laravel

- `https://packagist.org/packages/laravel/framework/stats.json` (~14 KB) tem
  `downloads.total` = 611.976.413 e `versions` = `["13.x-dev","v13.40.0",...]`.
- O endpoint p2 completo tem ~1.013.238 bytes, perto do teto de 1 MiB — por
  isso `stats.json`.
- `v` e `dt` compartilham a mesma URL/entrada de cache; o client precisa
  projetar as duas métricas.

### PHP

- `https://www.php.net/releases/index.php?json&max=1` (~2.774 bytes) devolve
  um objeto com a chave do major (`"8"`) e `version` = `8.5.12`.
- No `json_decode(..., true)` a chave `"8"` vira int; usar `reset($data)`.

### Python

- `https://endoflife.date/api/python.json` (~2.922 bytes) devolve uma lista;
  o primeiro item tem `latest` = `3.15.2`.
- O catálogo do python.org não expõe um campo `latest` limpo (o filtro
  `is_latest` não funciona e a resposta passa de 100 KB).

## Impacto no código existente

- `ProviderParam::listFrom([])` devolvia `null` e escondia definições sem
  params; parser e builder TS não representavam path literal.
- `CreateBadgeAsset` montava `implode('/', [])` = `''`; badge pessoal desses
  providers falharia.
- Formatter não conhecia `coverage`.
- Testes de contagem/ordem/fixture esperam 4 providers / 9 definições / 6
  requests.

## Decisões de teste e verificação

- Contagens passam a 9 providers, 15 definições e 11 requests no teste de
  endpoint.
- O fake do Packagist separa `stats.json` do Laravel do pacote genérico para
  não conflitar.
- Smoke real dos seis badges e do path literal incorreto; conferir HIT
  compartilhado de `laravel/v` e `laravel/dt`.

## Riscos

Mudança de shape das APIs externas; chave numérica do php.net; ordem de
`versions[]`; cobertura nula; arredondamento; rate limit anônimo (absorvido
pelo cache/stale); fixture sensível à ordem de chaves.
