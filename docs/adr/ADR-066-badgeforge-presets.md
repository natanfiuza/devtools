# ADR-066: Presets
Status: Aceito

## Decisão
Usuários cadastrados podem salvar presets reutilizáveis.

## Consequências
Melhora UX sem publicar badges.

## Nota da F3 (2026-11-02)

A F3 concretizou esta decisão na ADR-079: o preset é um **snapshot
normalizado do formulário** (`badge_presets.payload`), não um badge publicado;
não tem URL pública, cache nem analytics. Limite em
`config/badgeforge.php` (`presets.limit = null` — ilimitado para conta
verificada), órfão calculado na leitura (`logoOrphaned`) e gestão no builder e
em `/dashboard/badgeforge`.
