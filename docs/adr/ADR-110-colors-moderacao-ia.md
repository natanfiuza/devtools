# ADR-110 - Moderação automática com IA
Status: Aceito
Data: 2026-11-03

## Decisão
Comentários passam por filtro de palavras local e IA moderadora (DeepSeek). Fila assíncrona. Cache permanente em `ai_cache` (a tabela real é singular; nunca expira). Status: pending, approved, rejected, flagged.

## Consequências
Reduz moderação manual; exige fila e tratamento de falha da IA.

## Emenda 2026-11-07
A tabela é `ai_cache`, não `ai_caches`. Como `expires_at` é obrigatório hoje, "permanente" exige uma decisão de valor sentinela na F7.
