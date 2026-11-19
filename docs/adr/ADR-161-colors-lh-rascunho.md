# ADR-161 — Rascunho
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Último local até limpar; login pergunta importar, sem save/quota automática.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F4, 2026-11-19)

`lib/draft.ts` (puro, com storage injetável e guarda de `window`) grava um
envelope `{version, savedAt, status, decidedAt, originPaletteId, state}` na
chave `devtools.layout-harmonizer.draft.v1` do `localStorage`. Sem TTL: o
rascunho vive até ser importado, descartado ou substituído; a gravação acontece
com debounce de 500 ms apenas anônimo, com quadro válido e sem aviso.
`status` alterna entre `pending`, `imported` e `refused`. No carregamento
anônimo o último rascunho salvo é hidratado automaticamente, exceto quando o
status é `refused`; autenticado só pergunta quando o status é `pending`, e
importar hidrata sem salvar nem consumir cota. Shape inválido é descartado;
shape válido com versões não suportadas não é apagado — o diálogo explica e
oferece apenas descartar. O composable `useLayoutSystem` continua sem acesso a
storage.
