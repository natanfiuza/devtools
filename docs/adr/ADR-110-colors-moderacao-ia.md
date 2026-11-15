# ADR-110 - Moderação automática com IA
Status: Aceito
Data: 2026-11-03

## Decisão
Comentários passam por filtro de palavras local e IA moderadora (DeepSeek). Fila assíncrona. Cache permanente em `ai_cache` (a tabela real é singular; nunca expira). Status: pending, approved, rejected, flagged.

## Consequências
Reduz moderação manual; exige fila e tratamento de falha da IA.

## Emenda 2026-11-07
A tabela é `ai_cache`, não `ai_caches`. Como `expires_at` é obrigatório hoje, "permanente" exige uma decisão de valor sentinela na F7.

## Emenda 2026-11-15

O sentinela de "permanente" é `expires_at = NULL`: a coluna passou a ser nullable, a migração existing data intacta e a poda comum ignora `NULL`. O cache ganhou `purpose` (default `ai`) e a moderação usa `colors.comment_moderation`, com comparação do payload inteiro além do MD5. O filtro local é síncrono e bloqueante; a IA nunca é chamada com texto que ele barrou. Falha do provedor, contrato inválido ou esgotamento de retries resultam em `flagged` (nunca aprovação) e o job lê só `commentId` + `revision`, aplicando o resultado por UPDATE condicional. Implementação em ADR-139/ADR-140.
