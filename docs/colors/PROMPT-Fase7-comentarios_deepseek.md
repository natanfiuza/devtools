# Planeje a Fase 7 do Colors: comentários, moderação, denúncias e notificações.

Comentários:
- Apenas usuários logados.
- Máximo de 255 caracteres, validado no backend.
- Threads de no máximo 2 níveis (ADR-111).
- Autor pode editar e excluir seu comentário.
- Texto simples, sem HTML executável.
- Definir como preservar respostas ao excluir um comentário pai.
- Comentários editados devem passar novamente pela moderação.
- Nunca publicar uma revisão ainda não aprovada.

Moderação:
- Filtro de palavras com suporte aos cinco idiomas.
- DeepSeek em Job Laravel.
- Modelo configurável, reutilizando a integração existente.
- Não descontar da franquia de IA do usuário.
- Erros do provedor não equivalem a aprovação.
- pending, approved, rejected e flagged.
- Revisão administrativa de casos flagged e falhas persistentes.
- Conteúdo do comentário tratado como dado não confiável, nunca instrução.
- Resposta estruturada e validada.

Cache:
- Reutilizar a tabela existente `ai_cache` (singular).
- Não criar tabela paralela nem renomear a existente.
- Cache permanente: `expires_at` é obrigatório, então definir um sentinela explicitamente aprovado antes de tratar como sem expiração.
- Chave inclui finalidade colors.comment_moderation, conteúdo relevante,
  idioma, modelo e versões da política e do prompt.
- Inspecionar a estratégia de hash já existente e manter compatibilidade.
- Não aplicar remoção de acentos ou normalização que altere o significado.
- Separar cache de moderação de cache SQL e outras finalidades.
- Não reutilizar decisões feitas para contexto ou política diferentes.
- Revisões concorrentes: resultado antigo não pode aprovar edição nova.
- Evitar chamadas duplicadas para o mesmo conteúdo.
- Documentar privacidade, retenção e comportamento na exclusão de conta.

Denúncias:
- Apenas usuários logados.
- Somente comentários.
- Deduplicação e anti-spam.
- Revisão administrativa com histórico de ações.

Notificações:
- E-mail e no app.
- Curtida, comentário aprovado, resposta aprovada e novo seguidor.
- Imediatas significa enfileiradas após confirmação da transação,
  sem depender de digest.
- Reutilizar infraestrutura existente e SMTP configurado.
- Não notificar o próprio autor sobre sua própria ação.
- Deduplicar destinatários e retries.
- Silenciar notificações por paleta, respeitado no envio.
- Comentário rejeitado não gera notificação social.

Não habilite comentários públicos antes de toda a cadeia de moderação
e autorização passar nos testes.

## Trabalhe no projeto devtools existente, sem recriar sua estrutura.

Leia CLAUDE.md e todos os documentos relevantes em docs/colors/.
Inspecione o código e as convenções existentes antes de propor alterações.

Regras:
- Implemente somente a fase solicitada.
- Não sobrescreva alterações locais do usuário.
- Não execute git reset, git clean ou descarte alterações.
- Não faça deploy, push ou commit sem autorização.
- Reutilize autenticação, planos, permissões, notificações e componentes existentes.
- Toda interface deve estar traduzida em en, pt-BR, pt-PT, fr e es.
- Mantenha a especificação do Colors em docs/colors/; as decisões ficam em docs/adr/ com o prefixo `colors` (ex.: `ADR-091-colors-espacos-de-cor.md`).
- Não reutilize números de ADR já existentes no repositório.
- Atualize os documentos sem apagar decisões anteriores.
- Não invente comandos de testes: confira composer.json e package.json.

Antes de codificar:
1. Verifique o estado da fase anterior.
2. Liste requisitos e documentos ausentes.
3. Apresente arquivos afetados, dependências, riscos e testes.
4. Pare para minha aprovação.

Após a aprovação:
- Implemente em etapas pequenas.
- Execute testes relevantes, typecheck e build disponíveis.
- Informe comandos executados e resultados reais.
- Registre pendências.
- Pare ao concluir a fase; não avance automaticamente.


----

> Atenção: o prompt da proxima fase e: `docs\colors\PROMPT-Fase8-auditoria-final.md` 
