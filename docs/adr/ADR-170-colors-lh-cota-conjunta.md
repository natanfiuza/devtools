# ADR-170 — Cota conjunta: contador único e ordem de locks
Data: 2026-11-19
Status: Aceito

## Contexto

A ADR-152 fixou a regra de negócio — Free 25 itens conjuntos com as paletas,
arquivados contando, pago sem limite funcional —, mas não dizia como o
contador é implementado, nem como duas abas ou duas requisições simultâneas
paleta × sistema não estouram a vaga. Sem um ponto único de contagem e uma
ordem fixa de locks, a cota conjunta seria apenas uma soma convencional.

## Decisão

- **Um contador só**: `PaletteQuota::used()` soma `colorPalettes()` e
  `layoutSystems()`. Não existe segundo contador nem coluna de contagem. O
  limite continua em `colors.palettes.free_limit` (default 25) e
  `colors.palettes.paid_limit` (`null` = ilimitado).
- **Arquivados contam**: `used()` conta tudo, inclusive arquivados; só a
  exclusão libera vaga.
- **Janela de deploy defensiva**: o deploy copia o código antes de rodar
  `php artisan migrate --force`, então `used()` usa
  `Schema::hasTable('layout_systems')` e `PaletteTags::delete()` usa
  `Schema::hasTable('color_tag_layout_system')` até a migration entrar. As
  telas antigas de paleta não quebram na janela entre o pull e o migrate,
  como o `Sitemap` já fazia.
- **Ordem de locks**: `CreateLayoutSystem` e `DuplicateLayoutSystem` repetem o
  padrão de `CreatePalette`/`DuplicatePalette` — `DB::transaction`,
  `User::query()->whereKey(...)->lockForUpdate()->first()` **antes** de contar
  a cota, erro `colors.validation.limit_reached` quando cheio e retry de até 3
  tentativas para colisão de slug. A ordem de locks é sempre `users` primeiro.
  `Update` não trava nem conta.
- **Downgrade**: `reached()` bloqueia apenas criação e duplicação; leitura,
  edição, restauração, exclusão e exportação continuam permitidas (ADR-160).

## Consequências

- Paletas e sistemas disputam a mesma vaga de forma serializada: duas abas
  concorrendo pela última vaga resultam em uma criação e uma recusa
  `limit_reached`, coberto por teste misto e pelo gate manual de duas abas.
- Não é possível criar classe nova de recurso sujeita à cota sem passar por
  `used()`; qualquer recurso futuro entra na mesma soma.
- A leitura da cota continua tolerante a apoiar-se em tabela inexistente
  durante o deploy, ao custo de `Schema::hasTable` nas rotas de criação.
