# ADR-103 - Tags
Status: Aceito

## Decisão
Tags renomeáveis e reordenáveis por usuário.

## Consequências
Melhora organização e busca.

## Emenda 2026-11-11
A tag deixa de ser texto solto e vira **entidade do usuário**: `color_tags` (name 30, `key` = `lower(squish(name))`, `position`) com unique `(user_id, key)` e pivô `color_palette_color_tag` sem posição — a ordem é a global do usuário. Renomear reflete em todas as paletas; reordenar é por usuário. Limites em `colors.tags` (10 por paleta). Duplicar faz upsert nas tags **do duplicador** (ADR-123).
