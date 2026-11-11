# ADR-104 - Minhas Paletas
Status: Aceito

## Decisão
Painel com busca, filtro por tag, ordenação e ações (editar, duplicar, excluir, compartilhar).

## Consequências
Melhora gestão de paletas.

## Emenda 2026-11-11
O dashboard fica **sem prefixo de locale**: `/dashboard/colors/palettes` (ADR-022). A lista tem busca server-side por nome/descrição/tag, filtros de status (ativas/arquivadas) e tag, ordenação por data, nome, número de cores ou "Uso", direção e paginação com querystring preservada; a cota aparece no topo com o aviso de que arquivadas contam (ADR-096). Ações: editar, protótipo (`?tab=prototype`), duplicar, compartilhar, arquivar/restaurar e excluir digitando o nome exato. "Uso" é `duplications_count`, duplicações feitas por **outros** (ADR-124). O editor reusa o gerador com as cores persistidas e a aba Protótipo (ADR-123, ADR-126).
