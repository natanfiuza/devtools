# ADR-119 - Arquivamento de paleta
Status: Aceito
Data: 2026-11-07

## Contexto
A SPEC chamava arquivar de "soft delete", confundindo arquivamento com exclusão e deixando a cota indefinida.

## Decisão
Arquivar oculta a paleta da lista padrão (um filtro próprio a mostra), faz a URL pública responder 404 e preserva a paleta para restauração. Paleta arquivada **conta** na cota Free de 25. Excluir é ação definitiva, libera cota e não pode ser desfeita.

## Consequências
Não há bypass do limite Free por arquivamento. Restaurar é gratuito. A restauração devolve a URL pública só se o slug continuar livre.
