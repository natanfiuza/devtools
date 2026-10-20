# ADR-016-aicache-md5
Status: Aceito
Data: 2026-10-05

## Decisao
Tabela ai_cache com prompt_hash CHAR(32) UNIQUE = MD5 do payload canonico (schema normalizado: trim, EOL, espacos; descricao normalizada; target; dialeto; modelo; prompt_version). MD5 serve apenas como chave de lookup, nao seguranca; no hit, comparar canonical_payload para eliminar colisao. Invalida ao mudar modelo ou prompt_version. TTL configuravel. Hit nao chama LLM. Cache e global (compartilhado), so retorna a quem enviou payload identico.

## Consequencias
Ver SPEC.md.

## Emenda da F5 (2026-10-20)

A decisao virou codigo e uma parte dela estava faltando na implementacao:

- **A comparacao de colisao passou a existir de verdade.** O
  `AiCacheRepository::find()` recebia o hash e confiava nele. Agora recebe o
  `CanonicalPayload`, decodifica o payload guardado, compara o JSON inteiro com
  `hash_equals` e so serve a resposta se forem identicos; linha com payload
  ilegivel e apagada, linha com payload diferente e ignorada. Ha teste que
  grava uma linha com o hash de outro payload e confirma que nada e servido.
- **O que entra no hash**: fonte do schema, dialeto, alvo, schema, descricao,
  modelo e versao do prompt - o payload canonico inteiro. Trocar `AI_MODEL` ou
  `prompt_version` muda o hash e nao reaproveita a resposta do modelo antigo.
- **Hit nao debita**: no POST a solicitacao nasce `done` sem ledger; no job a
  reserva e estornada (ADR-040).
- **TTL de 30 dias** (`AI_CACHE_TTL_DAYS`); a leitura ignora expirado e
  `ai:prune-cache` apaga fisicamente. `hits` e `last_hit_at` sao operacao, nao
  cobranca.
- O cache guarda o schema comprimido por todo o TTL; a solicitacao apaga o
  payload dela ao terminar. A Politica de Privacidade diz isso (ADR-004).
