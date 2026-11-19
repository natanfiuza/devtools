# ADR-159 — Cadastro de item
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Nome obrigatório sugerido/editável, descrição/tags opcionais, privado padrão.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

`StoreLayoutSystemRequest`/`UpdateLayoutSystemRequest` validam nome obrigatório
(`colors.layout_systems.name_max_length`, default 80), descrição opcional
(`description_max_length`, default 500), visibilidade (`private` default) e
tags até `colors.tags.per_layout_system`; o estado é validado por
`LayoutSystemState` (ADR-168). O nome sugerido pela função pura
`lib/save.ts` vem da origem (`save.name_from_palette`) ou da estratégia/seed
(`save.name_from_strategy`), é truncado no limite e sempre editável no
`SaveLayoutSystemDialog`. O cliente envia só `source_palette_id`; o crédito é
capturado no servidor (ADR-172).
