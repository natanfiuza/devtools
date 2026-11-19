# ADR-165 — Duplicação
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Cadastrado pode duplicar público dentro da quota; crédito público, fontes privadas não expostas.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

`DuplicateLayoutSystem` trava a linha de `users`, conta a cota conjunta e copia
`state`, versões derivadas, crédito congelado, tags e linhagem
(`duplicated_from_id`); a cópia nasce privada, com nome próprio e sem slug até
a primeira publicação. Duplicar pela página pública passa pelo intent
autenticado (`{locale}/colors/s/{slug}/duplicate` GET → login → POST com
throttle) e abre o editor da cópia. Entre contas diferentes o `source_palette_id`
é zerado (a linhagem fica em `duplicated_from_id`); na mesma conta ele é
mantido. Só a duplicação entre contas diferentes incrementa
`duplications_count`. A origem privada nunca chega ao público (ADR-172). Cota
cheia bloqueia a duplicação com `colors.validation.limit_reached`.
