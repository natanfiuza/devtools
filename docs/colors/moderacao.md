# Moderação

## Fluxo
1. Usuário logado envia comentário (max 255 chars).
2. Filtro local de palavras.
3. Se passar, envia para fila de moderação DeepSeek.
4. Cache em `ai_cache` (singular; tabela existente do projeto).
5. IA retorna approved/rejected/flagged.
6. Se flagged, admin revisa.

## Status
- pending
- approved
- rejected
- flagged

## IA
- Provider: DeepSeek (modelo configurável; nunca fixo no código).
- Cache "permanente": a coluna `expires_at` é obrigatória, então a Fase 7 precisa definir um valor sentinela aprovado. Até lá, não trate como permanente.
- Fallback: se IA falhar, marcar pending para revisão manual.
