# ADR-LH-004 — Exportações
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
MD, CSS HEX/OKLCH fallback, ZIP para autenticados sem salvar; anônimos não exportam.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.
