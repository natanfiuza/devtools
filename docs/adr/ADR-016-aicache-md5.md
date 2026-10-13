# ADR-016-aicache-md5
Status: Aceito
Data: 2026-10-05

## Decisao
Tabela ai_cache com prompt_hash CHAR(32) UNIQUE = MD5 do payload canonico (schema normalizado: trim, EOL, espacos; descricao normalizada; target; dialeto; modelo; prompt_version). MD5 serve apenas como chave de lookup, nao seguranca; no hit, comparar canonical_payload para eliminar colisao. Invalida ao mudar modelo ou prompt_version. TTL configuravel. Hit nao chama LLM. Cache e global (compartilhado), so retorna a quem enviou payload identico.

## Consequencias
Ver SPEC.md.
