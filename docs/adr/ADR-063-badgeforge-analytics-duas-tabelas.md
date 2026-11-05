# ADR-063: Analytics em duas tabelas
Status: Substituído pelo [ADR-086](ADR-086-badgeforge-analytics.md) (F7, 2026-11-04) — a tabela `badge_asset_views` nunca foi criada; o desenho final é polimórfico e inclui endpoint e provider customizado.

## Decisão
badge_assets registra a badge e arquivos; badge_asset_views registra cada acesso.

## Consequências
Analytics sem duplicar metadados; permite agregações e exportação.
