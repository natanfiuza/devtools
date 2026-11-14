# ADR-093 - Presets
Status: Aceito

## Decisão
Preset é configuração salva reutilizável (cor base, escala, harmonia, papéis, tema). Não é paleta publicada.

## Consequências
Permite regenerar paletas rapidamente.

## Emenda 2026-11-14
A F5 implementa o preset como **entrada da geração**: a tabela `color_presets` guarda apenas `name` + `base` (e o dono), com nome único por conta e base imutável no mesmo preset. Escala, harmonia, papéis e tema não são persistidos — a escala é recalculada com a versão atual do algoritmo, e papéis/tema continuam metadados da paleta no editor (ADR-126). Aplicar um preset troca a base; não cria paleta, não consome a cota do ADR-096 e não conta “Uso”. O teto é operacional (`colors.presets.limit`, default `null` = sem limite funcional) e vem de `config/colors.php`. Decisão detalhada na ADR-129.
