# ADR-160 — Downgrade
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Preservar dados/leitura/exportação e impedir novas criações acima da quota.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

Com `used > limit`, `PaletteQuota::reached()` bloqueia apenas novas criações e
duplicações de sistema (erro `colors.validation.limit_reached`, exibido no
diálogo de salvar). Ler, abrir o editor, arquivar/restaurar, excluir, duplicar
um item já existente dentro de outra conta quando cabível, e exportar (F5)
continuam permitidos. Nenhum item é escondido ou apagado pelo downgrade.
