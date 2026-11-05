# Moderação

## Fluxo
1. Usuário logado envia comentário (max 255 chars).
2. Filtro local de palavras.
3. Se passar, envia para fila de moderação DeepSeek.
4. Cache permanente em ai_caches.
5. IA retorna approved/rejected/flagged.
6. Se flagged, admin revisa.

## Status
- pending
- approved
- rejected
- flagged

## IA
- Provider: DeepSeek.
- Cache permanente (nunca expira).
- Fallback: se IA falhar, marcar pending para revisão manual.
