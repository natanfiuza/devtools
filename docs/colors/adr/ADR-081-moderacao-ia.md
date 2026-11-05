# ADR-081 - Moderação automática com IA
Status: Aceito
Data: 2026-11-03

## Decisão
Comentários passam por filtro de palavras local e IA moderadora (DeepSeek). Fila assíncrona. Cache permanente em ai_caches (nunca expira). Status: pending, approved, rejected, flagged.

## Consequências
Reduz moderação manual; exige fila e tratamento de falha da IA.
