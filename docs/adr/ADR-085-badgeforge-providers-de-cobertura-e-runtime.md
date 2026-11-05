# ADR-085: BadgeForge F6 — providers de cobertura e runtime

Status: Aceito
Data: 2026-11-04
Relacionados: ADR-068 (acesso a providers), ADR-076 (cache e endpoint stateless), ADR-080 (camada de providers), ADR-081 (badges pessoais), ADR-082 (docs públicas), ADR-083 (endpoint JSON)

## Contexto

A F4 entregou quatro providers com nove definições e fixou o contrato da
camada: o catálogo é dado (`badge_providers` / `badge_provider_definitions`),
o comportamento é código (`client_key` resolve o cliente,
`extractor_key` escolhe a métrica) e `parser_class` é inerte. O SPEC §16 pede
na F6 mais cinco providers: Codecov, Coveralls, Laravel, PHP e Python.

Dois tipos de métrica não eram representáveis nesse contrato:

1. **Cobertura** é um percentual (`66.89`), não uma contagem. O
   `ProviderMetricFormatter` só sabia formatar licença, versão e número com
   sufixo k/M/B.
2. **Versão de runtime** não tem coordenadas por item: a URL pública é uma
   constante (`latest`). O parser exigia `required_params` não vazio e o
   construtor TS só montava segmentos a partir de params, então uma definição
   sem params produzia 404/nada.

Também faltava decidir a fonte de cada número, porque os cinco providers não
têm um campo canônico comum: Codecov e Coveralls medem cobertura de repo,
Laravel tem versão e downloads, PHP tem releases oficiais e Python não expõe
um `latest` limpo no catálogo do python.org.

## Decisão

### 1. Três providers de runtime, um de cobertura por serviço

| Provider | Métrica(s) | Fonte |
|---|---|---|
| Codecov | `coverage` | `https://api.codecov.io/api/v2/github/{owner}/repos/{repo}/` (`totals.coverage`) |
| Coveralls | `coverage` | `https://coveralls.io/github/{owner}/{repo}.json` (`covered_percent`) |
| Laravel | `v`, `dt` | `https://packagist.org/packages/laravel/framework/stats.json` |
| PHP | `v` | `https://www.php.net/releases/index.php?json&max=1` |
| Python | `v` | `https://endoflife.date/api/python.json` |

Os cinco são **públicos**: `requires_auth=false`, `auth_type=null` e
`supports_private=false` em todas as definições. Não há PAT, tutorial de
credencial nem badge pessoal privada para eles.

### 2. Cobertura é o percentual inteiro da branch padrão

A URL pública não tem `?branch=`: Codecov e Coveralls leem a branch padrão do
serviço, que é a mesma que o README do projeto costuma exibir. O path é
`{owner}/{repo}`, reutilizando os patterns de `owner`/`repo` do GitHub.

O formatter ganhou `coverage`, que aceita int/float/string numérica, exige
`0 ≤ valor ≤ 100` e devolve `((int) round($valor)).'%'` (`66.89 → 67%`,
`94.148 → 94%`, `100 → 100%`). Fora do intervalo, NaN/INF ou tipo inválido
viram `null` (`INVALID_RESPONSE`) e a imagem segue para o fallback 200; um
Codecov sem dados nunca vira 500.

### 3. Versão de runtime usa path literal `latest`

Definições de versão única têm `required_params = []` (JSON `"[]"`) e
`path_pattern = 'latest'`. A URL pública é estável e documentada:

```text
/api/badges/laravel/v/latest.svg
/api/badges/php/v/latest.svg
/api/badges/python/v/latest.svg
```

O parser aceita somente `path === trim(path_pattern)` e exige que o literal
case com `\A[A-Za-z0-9._-]+\z`; literal vazio, com `{`, com barra ou dot
segment é recusado (404). `ProviderParam::listFrom([])` passa a devolver `[]`
em vez de `null`, e `CreateBadgeAsset` valida o literal no lugar de
`implode('/', [])` para que a badge pessoal também aceite zero params. No
cliente TS, `buildProviderBadgeUrl` valida o mesmo literal e devolve `''` se
ele for inválido — nenhum snippet quebrado.

Os resultados são lidos assim:

- **Laravel**: `versions[]` é percorrido e a primeira entrada que não termina
  em `-dev` vira a versão (`v13.40.0`); `downloads.total` vira `dt` (`611M`).
- **PHP**: `reset($data)` pega o primeiro release; a chave numérica `"8"` do
  JSON vira int no decode e nunca é usada. Lê `version` (`8.5.12`).
- **Python**: `$data[0].latest` (`3.15.2`). O catálogo do python.org não
  expõe um campo `latest` limpo, e o endoflife.date é a fonte estável.

### 4. Laravel projeta as duas métricas em uma chamada

`v` e `dt` compartilham a URL resolvida e o mesmo provider slug, logo a chave
de cache é a mesma. `LaravelProviderClient` projeta **sempre**
`['version' => …, 'downloads' => …]`: a segunda definição lê o payload da
primeira em cache. `ProviderMetrics` busca
`$payload->metrics[$definition->extractor_key]`, então projetar só a métrica
pedida faria `laravel/dt` cair em "unavailable" num HIT. Não se estende o
`PackagistProviderClient` porque o pacote `laravel/framework` tem regra
própria (primeira versão estável) e o client genérico lê outro shape.

### 5. Categorias novas no catálogo

`badge_providers.category` ganha `coverage` (Codecov, Coveralls) e `runtime`
(Laravel, PHP, Python). As categorias existentes (`vcs`, `package`, `image`)
não mudam; a coluna já era string sem enum no banco.

### 6. Migração aditiva

Nova migração `2026_11_04_000002_seed_badgeforge_f6_providers.php` com
`insertOrIgnore` para os cinco providers e seis definições. Não edita a
migração da F4, não sobrescreve ajuste administrativo (`enabled`,
`cache_ttl`) e `down()` remove só os slugs desta fase. O catálogo novo soma
nove providers e quinze definições.

## Consequências

### Positivas

- O contrato da F4 continua intacto: nada de `parser_class`, nenhum cliente
  novo altera o pipeline de cache/stale/fallback.
- Zero params passou a ser representável e testado nos dois lados (PHP e TS),
  abrindo lugar para futuras métricas sem coordenadas.
- Uma chamada ao Packagist serve versão e downloads do Laravel.
- Cobertura tem formatação própria e falha de dados vira fallback, nunca 500.

### Negativas / Trade-offs

- Cobertura fica presa à branch padrão do serviço; medir outra branch exigiria
  query nova e quebra o path estável.
- O literal `latest` é fixo no catálogo: para "versão X específica" seria
  preciso outra definição/rota.
- Cinco fontes externas novas dependem de cache de 300 s e do rate limit
  anônimo de cada serviço; o stale-if-error absorve, mas o dado pode ficar até
  o TTL desatualizado.
- `supports_private=false` significa que Codecov/Coveralls de repo privado não
  são servidos por badge pessoal nesta fase.

## Alternativas descartadas

- **Downloads por linguagem / EOL no lugar de versão de runtime**: mediria
  outra coisa (populares de pacote, fim de suporte) e não o que o SPEC §16
  pede.
- **`?branch=` na URL pública**: aumenta a superfície de cache e rate limit e
  foge do que o README normalmente exibe.
- **Ler `latest` direto do python.org**: o catálogo não expõe um campo
  canônico; endoflife.date já normaliza.
- **Um client Laravel sobre o `PackagistProviderClient`**: o client genérico
  lê o JSON completo do pacote (que passa de 7 MB em pacotes grandes) e não
  tem a regra de pular `-dev`.
- **Definição sem params rejeitada com 404 universal**: impediria versão de
  runtime e manteria o builder incapaz de montar a URL.
- **Percentual com casas decimais**: polui a imagem e diverge do padrão dos
  serviços (shields mostra inteiro).
