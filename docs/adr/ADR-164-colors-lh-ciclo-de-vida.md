# ADR-164 — Ciclo de vida
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Arquivar retira público/sitemap e conta quota; restaurar possível. Excluir definitivo confirma e não reutiliza ID.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

`ArchiveLayoutSystem` move `slug` para `archived_slug`, seta `archived_at` e
revoga a URL; o item sai do sitemap mas continua ocupando a cota.
`RestoreLayoutSystem` limpa `archived_at` e, se o item é público, devolve o slug
reservado. `DeleteLayoutSystem` exige o nome digitado
(`DeleteLayoutSystemRequest`), grava `slug` e `archived_slug` em
`layout_system_retired_slugs` com `insertOrIgnore`, remove a linha e libera a
vaga; o ULID e o slug nunca são reemitidos (ADR-171). Arquivar, restaurar e
excluir não recalculam o quadro — o snapshot gravado é preservado. O guarda de
alterações não intercepta essas ações de ciclo de vida.
