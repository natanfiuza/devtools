# ADR-096 - Limites de paletas
Status: Aceito

## Decisão
Cadastrado free: 25 paletas. Pago: sem limite funcional. Anônimo: apenas localStorage.

## Consequências
Limite aplicado no backend; UI informa o limite. Paleta arquivada continua contando na cota Free (ADR-119); excluir é definitivo e libera a cota.

## Emenda 2026-11-11
A F4 implementa a cota em `config/colors.php` (`free_limit` 25, `paid_limit` null = ilimitado, com `COLORS_*`), contando **todas** as paletas do usuário em `PaletteQuota::used()`. Criar e duplicar rodam em transação com `User::lockForUpdate()` antes da contagem, para duas abas não passarem do teto; estourar responde erro de validação no campo `palette` (ADR-123). Não existe paleta anônima: quem não tem conta guarda um **rascunho em `sessionStorage`** (ADR-123) e só vira paleta depois do login. Os testes cobrem 25 → 26ª recusada, arquivadas contando e exclusão liberando; a concorrência real é opt-in em MySQL.

## Emenda 2026-11-14
A F5 mantém a regra: o anônimo continua apenas com `localStorage`, agora com histórico reduzido a 20 e favoritos em 50 (ADR-132), e não existe paleta anônima. O rascunho de paleta continua no `sessionStorage`, mas passa a aceitar `scale?` e `roles?` opcionais para preservar uma escala importada e suas semânticas através do login (ADR-123, §7, emenda; ADR-133) — ainda com TTL de 24 h, validação tolerante e sem imagem ou URL completa. A cota de paletas não muda e não é afetada pela biblioteca de cores nem pelos presets, que têm limites próprios.
