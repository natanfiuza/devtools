# ADR-119 - Arquivamento de paleta
Status: Aceito
Data: 2026-11-07

## Contexto
A SPEC chamava arquivar de "soft delete", confundindo arquivamento com exclusão e deixando a cota indefinida.

## Decisão
Arquivar oculta a paleta da lista padrão (um filtro próprio a mostra), faz a URL pública responder 404 e preserva a paleta para restauração. Paleta arquivada **conta** na cota Free de 25. Excluir é ação definitiva, libera cota e não pode ser desfeita.

## Consequências
Não há bypass do limite Free por arquivamento. Restaurar é gratuito. A restauração devolve a URL pública só se o slug continuar livre.

## Emenda 2026-11-11
A F4 implementa o arquivamento com `archived_slug = slug; slug = null; archived_at = now()` em transação (ADR-123): o slug é **liberado** para outra paleta e a restauração reivindica o valor anterior se ainda estiver livre (`claimOrGenerate`), senão gera um novo a partir do nome atual; paleta que nunca foi publicada volta sem slug. Só a **exclusão definitiva** libera cota — arquivar continua contando (ADR-096, emenda). Paleta arquivada é somente leitura até restaurar.
