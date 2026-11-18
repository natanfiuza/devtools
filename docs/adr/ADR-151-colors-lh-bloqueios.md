# ADR-151 — Bloqueios
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
Por token/tema; aceite explícito de ajuste mantém bloqueio no valor novo.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Implementação (F3, 2026-11-18)

Implementado em `resources/js/tools/layout-harmonizer/lib/editState.ts`: o
bloqueio é por token e por tema, guarda o valor efetivo no momento em que foi
ligado (override de origem `lock`) e sobrevive à troca de seed e de estratégia.
Desligar o bloqueio remove apenas o override de origem `lock` — uma cor editada
ou um ajuste aceito no mesmo token continuam. O aceite de uma sugestão em um
token bloqueado grava o novo valor e mantém o bloqueio (ADR-167).
