# ADR-154 — Público
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Somente salvo público compartilhável; viewer Colors reutilizado.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

A página pública é `PublicLayoutSystem.vue`, servida por
`{locale}/colors/s/{slug}` (`colors.systems.show`). O viewer reutiliza
`ThemeComparison`/`ThemeBoard` com `editable=false`: cópia de HEX/OKLCH
funciona, edição, bloqueio e restauração não têm handler. O presenter público
expõe nome, descrição, tags, autor, versões, `state.snapshot`, crédito de
origem permitido e `duplications_count` — nunca `source_palette_id`, conta ou
origem privada (ADR-172). Privado, arquivado, retirado e inexistente têm o
mesmo 404. O CTA de duplicação exige conta e passa pelo intent (ADR-165). Não
há curtidas, comentários, favoritos, perfil próprio nem API JSON nesta fase.
