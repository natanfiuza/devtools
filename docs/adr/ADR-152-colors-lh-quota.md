# ADR-152 — Quota
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Free 25 itens conjuntos com Colors incluindo arquivados. Pago sem limite funcional.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

O contador único vive em `PaletteQuota::used()`, que soma
`User::colorPalettes()` e `User::layoutSystems()`; arquivados contam. O limite
continua em `colors.palettes.free_limit` (default 25) e `colors.palettes.paid_limit`
(`null` = ilimitado) — não existe segundo contador. Como o deploy copia o código
antes de `php artisan migrate --force`, `used()` e `PaletteTags::delete()` usam
`Schema::hasTable` até a migration entrar, para as telas antigas de paleta não
quebrarem na janela entre o pull e o migrate. `CreateLayoutSystem` e
`DuplicateLayoutSystem` travam a linha de `users` (`lockForUpdate`) antes da
contagem, na mesma ordem de locks de `CreatePalette`/`DuplicatePalette`
(ADR-170). O mecanismo de bloqueio por cota só é exercido quando o limite é
atingido; leitura, edição, restauração e exclusão nunca são bloqueadas
(ADR-160).
