# ADR-096 - Limites de paletas
Status: Aceito

## Decisão
Cadastrado free: 25 paletas. Pago: sem limite funcional. Anônimo: apenas localStorage.

## Consequências
Limite aplicado no backend; UI informa o limite. Paleta arquivada continua contando na cota Free (ADR-119); excluir é definitivo e libera a cota.

## Emenda 2026-11-11
A F4 implementa a cota em `config/colors.php` (`free_limit` 25, `paid_limit` null = ilimitado, com `COLORS_*`), contando **todas** as paletas do usuário em `PaletteQuota::used()`. Criar e duplicar rodam em transação com `User::lockForUpdate()` antes da contagem, para duas abas não passarem do teto; estourar responde erro de validação no campo `palette` (ADR-123). Não existe paleta anônima: quem não tem conta guarda um **rascunho em `sessionStorage`** (ADR-123) e só vira paleta depois do login. Os testes cobrem 25 → 26ª recusada, arquivadas contando e exclusão liberando; a concorrência real é opt-in em MySQL.
