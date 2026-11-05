# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona providers de cobertura e runtime ao BadgeForge
```

---

## 🎯 Resumo

Amplia o catálogo do BadgeForge de 9 para 15 métricas com cinco novos providers: **Codecov** e **Coveralls** (categoria `coverage`) e **Laravel** (versão e downloads), **PHP** e **Python** (categoria `runtime`). O objetivo é cobrir as duas lacunas de maior demanda dos usuários — percentual de cobertura e versão da stack — sem exigir autenticação, já que todas as fontes são públicas e lidas anonimamente.

A mudança também introduz suporte a **definições sem parâmetros de coordenadas**: Laravel/PHP/Python não recebem `owner/repo` na URL, então o `path_pattern` passa a funcionar como literal (`latest`) na rota pública, no parser de backend e no builder de URL do frontend.

## 🛠️ Mudanças Técnicas

- Adiciona `CodecovProviderClient`, `CoverallsProviderClient`, `LaravelProviderClient`, `PhpProviderClient` e `PythonProviderClient`, todos registrados no `ProviderClientRegistry`.
- `LaravelProviderClient` projeta **as duas métricas** (`version` e `downloads`) em toda resposta porque `v` e `dt` compartilham a mesma URL (`stats.json`) e, portanto, a mesma entrada de cache — evita HIT sem a métrica solicitada.
- Extrai versão estável do Laravel ignorando releases `-dev`; sem versão estável, `version` fica nula e `downloads` continua válido.
- Novo helper `number()` em `AbstractProviderClient` e novo `ProviderMetricFormatter::coverage()` (percentual inteiro, faixa 0–100).
- `ProviderParam::listFrom()` passa a aceitar lista vazia como válida (definição sem coordenadas); `ProviderPathParser` ganha `parseLiteral()` com validação de caracteres, tamanho e proteção contra traversal.
- `buildProviderBadgeUrl()` no frontend replica a regra do literal e recusa padrões inválidos (`..`, placeholders, espaços).
- `CreateBadgeAsset` usa `path_pattern` como path quando não há coordenadas, evitando `implode('/', [])` vazio.
- Migração aditiva `2026_11_04_000002_seed_badgeforge_f6_providers.php` com `insertOrIgnore` para os 5 providers e 6 definições, com `down()` que remove apenas o que semeou.
- Traduções atualizadas em `en`, `es`, `fr`, `pt_BR` e `pt_PT` (docs e nomes de ferramenta).
- Testes novos em unitários (clients, formatter, parser, url builder) e feature (endpoint, catálogo, seeder, gestão de badges pessoais), além de fixtures e testes de frontend.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nova migração de seed. É aditiva e idempotente — não sobrescreve ajustes administrativos (`enabled`, `cache_ttl`) já existentes. `parser_class` nasce `NULL`, conforme ADR-080.
- **Dependências/Env:** nenhuma variável de ambiente ou dependência nova. Os cinco providers são públicos e não requerem credencial (`supportsPrivate() === false`).
- **Cache/rate limit:** `laravel/v` e `laravel/dt` compartilham a mesma URL e a mesma entrada de cache — a segunda requisição deve retornar `HIT`.
- **Compatibilidade de URL:** definições sem parâmetros só aceitam exatamente o literal do catálogo; qualquer variação (`Latest`, `8.5`, `latest/extra`) retorna 404. Badges existentes não são afetadas.
- **Testes atualizados:** contagens de providers/métricas e o número de chamadas HTTP esperadas mudaram (9→15 métricas, 6→11 requisições), refletindo o novo catálogo.
- **Docs:** `docs/SPEC-BADGEFORGE.md`, `docs/acceptance/BADGEFORGE-FASE-6.md`, `docs/adr/ADR-085`, `docs/badgeforge/API.md` e `OPENAPI.md` foram atualizados para refletir os novos providers e a semântica de path literal.

close #130

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_27_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)