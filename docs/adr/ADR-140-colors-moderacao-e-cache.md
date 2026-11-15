# ADR-140 - Moderação IA, cache e falhas
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-110 (moderação IA, emenda), ADR-139 (comentários), ADR-003 (LLM provider), ADR-034 (fila/worker), ADR-008 (abuso e custo)

## Contexto
A ADR-110 fixou filtro local, IA DeepSeek, fila e cache permanente, mas deixou duas lacunas: `ai_cache.expires_at` era obrigatório, então “permanente” não tinha representação, e a falha da IA não tinha destino (aprovar por engano, reenfileirar para sempre ou sumir com o comentário). A tabela também é compartilhada com o AI SQL Builder, então qualquer cache novo precisava ser separado sem criar esquema paralelo.

## Decisão

### 1. Filtro local bloqueante e sem eco
Antes de gravar, o comentário passa pelo `WordFilter`, síncrono. As listas ficam em `resources/colors/moderation/{locale}.txt` (uma expressão por linha, `#` comenta, inglês sempre mesclado como fallback), com caminho configurável e cache em memória; diretório ausente não derruba a requisição. A comparação normaliza NFKC, caixa, zero-width e pontuação e casa **sequências exatas de tokens** (nunca substring, nunca regex de conteúdo). Um acerto responde 422 genérico `comments_blocked` sem gravar nem revelar o termo; a IA só vê o que passou.

### 2. IA como dado não confiável, contrato fechado
O prompt versionado `resources/prompts/colors_comment_moderation.v1.md` instrui que o comentário é dado, nunca instrução, e pede JSON estrito `{decision: approved|rejected, category: ...|null}`. O validador aceita só o contrato: decisão prevista, categoria prevista, sem campos extras, sem `flagged` vindo do modelo. Resposta fora do contrato é falha, não aprovação.

### 3. Sem cota do usuário
O job chama o `LlmProvider` diretamente, sem `StartAiQuery` e sem `usage_ledger`: moderar não consome a cota de IA da conta. O modelo vem de `colors.comments.moderation.model` (fallback `AI_MODEL`), nunca fixo no código.

### 4. `ai_cache` compartilhada com `purpose`
- migração adiciona `purpose` (default `ai`, indexado) e torna `expires_at` nullable;
- `NULL` é o sentinela de **permanente**, e a poda comum o ignora;
- `ModerationPayload` usa `purpose = colors.comment_moderation`, conteúdo exato (trim), locale, modelo, versão do prompt e da política, com JSON canônico e MD5 como chave;
- `ModerationCacheRepository` busca por `prompt_hash + purpose + (NULL ou futuro)` e compara o payload inteiro com `hash_equals` antes de devolver — MD5 nunca é a única prova; linha corrompida é apagada;
- `COLORS_COMMENT_CACHE_TTL_DAYS` transforma o permanente em TTL; o expurgo é `ai:prune-cache --purpose=colors.comment_moderation --all`.

### 5. Falha nunca vira aprovação
- provedor não configurado (`AiUnavailableException`): `flagged` com motivo `provider_unconfigured`;
- erro retryable: relança até `job_tries`, depois `flagged` (`provider_unavailable`);
- contrato inválido ou prompt ilegível: `flagged` (`invalid_contract`);
- exceção inesperada: `failed()` marca `flagged` se a revisão ainda estiver pendente, depois de esgotar as tentativas.

`flagged` é visível com o mesmo selo “em análise” e entra na fila do admin. Nunca há reenfileiramento infinito.

### 6. Job idempotente por revisão
`ModerateColorComment` carrega apenas `commentId` + `revision`, é `ShouldQueueAfterCommit` e `ShouldBeUnique` por `comment:revision`. Revisão mudou, comentário sumiu, foi excluído ou saiu de `pending`: o job sai sem fazer nada. `ApplyModerationResult` aplica o resultado por UPDATE condicional e reivindica o aviso com `notified_at`.

### 7. Admin tem a última palavra
A tela `/admin/comments` vê o texto em qualquer estado, aprova, rejeita ou exclui; toda decisão incrementa `revision` e é auditada sem o corpo (só ids, `from`/`to`). As mesmas regras do resultado automático valem para o admin.

### 8. Privacidade, retenção e logs
O cache guarda o texto exato do comentário e, sem TTL configurado, de forma permanente; isso fica documentado, com TTL opcional e expurgo por purpose. Logs e auditoria nunca recebem corpo, prompt, resposta crua do modelo, cookie anônimo ou e-mail.

## Consequências
- Comentário nunca “passa” por falha técnica: fica pendente para um humano.
- A tabela de cache continua única; o `purpose` separa usos e permite expurgo cirúrgico.
- O sentinela `NULL` elimina a linha “eterna por acidente”: a poda só apaga o que expira.
- O filtro local é conservador por token, com risco de falso positivo controlado por revisão versionada das listas.
- Sem worker, comentários ficam em análise; nada quebra e nada é aprovado.
- O cache permanente é o ponto de retenção principal da fase e tem caminho explícito de remoção.

## Alternativas rejeitadas
- **Tabela de cache própria**: duplicaria esquema, poda e política do `ai_cache`.
- **Sentinel de data distante (`9999-12-31`)**: mente sobre o domínio do campo e quebra na poda/consulta por data.
- **Aprovar em falha da IA**: contraria a moderação e a decisão de nunca publicar por engano.
- **Reenfileirar indefinidamente**: custo e latência sem limite, com o comentário parado para sempre no melhor caso.
- **Usar a cota de IA do autor**: moderar é obrigação da plataforma, não consumo do recurso.
- **Substring no filtro local**: falso positivo em palavras comuns (ex.: “class” × “ass”).
