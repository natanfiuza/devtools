# Moderação

Implementado na Fase 7 (2026-11-15, ADR-140). Vale para criação e edição de
comentário; toda revisão editada volta para a fila.

## Fluxo

1. Conta logada, verificada e ativa envia ou edita um comentário (até 255 caracteres).
2. Filtro local de palavras, síncrono e bloqueante: se barrar, responde 422
   genérico (`comments_blocked`) sem gravar nada e sem revelar o termo.
3. O comentário é gravado `pending` e um job `ModerateColorComment` vai para a
   fila depois do commit.
4. O job procura o resultado no cache; sem cache, chama a IA moderadora.
5. O resultado é aplicado por `UPDATE ... WHERE id = ? AND revision = ? AND
   status = 'pending'`, então resultado de revisão antiga não aprova/edita a
   revisão nova.
6. Aprovado grava `approved_body` e dispara a notificação uma vez (se o
   `notified_at` ainda estiver nulo).

## Filtro local

- Arquivos versionados em `resources/colors/moderation/{locale}.txt`, um termo
  por linha, `#` como comentário; diretório configurável em
  `colors.comments.moderation.word_lists_path`.
- Sempre mescla o inglês como fallback; arquivo ausente ou linha inválida não
  derruba a requisição.
- Normaliza NFKC, minúsculas, remove zero-width e controle, troca pontuação por
  espaço e compara **n-gramas de tokens** — nunca substring. O termo encontrado
  não é devolvido nem registrado.

## Status

- `pending` — aguardando a IA ou o admin; visível com selo "em análise".
- `approved` — texto definitivo; é o que fica em `approved_body`.
- `rejected` — texto escondido; exibe `approved_body` quando existe, senão some.
- `flagged` — falha da IA, contrato inválido ou retries esgotados; visível com o
  mesmo selo e na fila do admin. **Nunca** vira aprovação automática.

## IA

- Provedor DeepSeek, modelo em `colors.comments.moderation.model` (fallback
  `AI_MODEL`); nada fixo no código.
- O comentário é **dado não confiável** no prompt: nunca instrução. A resposta é
  JSON estrito `{decision: approved|rejected, category: spam|harassment|hate|sexual|other|null}`,
  validada por contrato; campos extras, `flagged` vindo do modelo ou valor fora
  do contrato invalidam a resposta.
- A chamada é direta (`LlmProvider::complete()`), sem `StartAiQuery`, sem
  `usage_ledger` e sem consumir cota da conta.
- Nunca registre o corpo do comentário, o prompt ou a resposta crua em log.

## Cache

- Mesma tabela `ai_cache`, separada por `purpose = colors.comment_moderation`.
- "Permanente" é `expires_at = NULL`; `COLORS_COMMENT_CACHE_TTL_DAYS` define uma
  validade opcional.
- O `find` compara o payload canônico inteiro com `hash_equals` (não só o MD5),
  filtra pelo `purpose` e apaga linha corrompida.
- Expurgo de um uso inteiro:
  `php artisan ai:prune-cache --purpose=colors.comment_moderation --all`.
- A tabela é compartilhada com o SQL builder; o `purpose` na coluna **e** no
  payload é o que separa os usos.

## Falhas e admin

- Erro retryable relança até `job_tries`; timeout, provedor indisponível,
  contrato inválido ou retries esgotados marcam `flagged`. O `failed()` do job
  também marca se a revisão ainda estiver `pending`.
- Admin em `/admin/comments` filtra por `pending`, `flagged`, `rejected`,
  `approved` e `all`, busca por texto, e pode aprovar, rejeitar ou excluir.
- Essas ações usam UPDATE condicional com incremento de `revision`, o que
  invalida jobs em voo; todas são auditadas com `RecordsAdminActions` e a
  auditoria não carrega o texto do comentário.
- Aprovar um comentário rejeitado o traz de volta; rejeitar resolve as denúncias
  pendentes.

## Privacidade

- O cache permanente guarda o texto exato do comentário enquanto a linha existir;
  o TTL e o expurgo por `purpose` são a saída.
- Anonimização apaga preferências e mutes da conta; comentários e denúncias
  permanecem com o texto e autoria "conta removida"; no purge definitivo
  `user_id`, `reports.user_id` e `reports.resolved_by` viram NULL.
