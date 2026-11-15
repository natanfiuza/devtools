# ADR-141 - Denúncias de comentário
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-114 (denúncia, emenda), ADR-139 (comentários), ADR-140 (moderação), ADR-025 (painel administrativo)

## Contexto
A ADR-114 decidiu que contas logadas denunciam comentários e que o admin revisa, com status `pending`/`resolved`/`dismissed`. A F7 precisava fechar três pontos que a decisão antiga não cobria: o que impede denúncia repetida, o que acontece quando a denúncia foi dispensada e o comentário volta, e o que a denúncia faz com o alvo (nada?) até o admin decidir.

## Decisão

### 1. Quem denuncia e o que pode ser denunciado
Só conta logada, verificada e ativa. O comentário precisa estar visível numa paleta pública; o próprio autor não denuncia o próprio comentário. Motivo é enum fixo — `spam`, `harassment`, `hate`, `sexual`, `other` —, sem texto livre, para não abrir um segundo campo de conteúdo sem moderação.

### 2. Uma linha por (comentário, denunciante)
Unique em `(comment_id, user_id)`; a corrida é resolvida por transação + captura do unique. Repetir com denúncia `pending` ou `resolved` responde 422 `report_duplicate` sem criar linha. Se a anterior estava `dismissed`, a **mesma** linha volta a `pending` com motivo e `reported_at` novos. Denúncias não geram notificação neste ciclo: a fila é administrativa.

### 3. Denúncia não esconde nem altera o comentário
A denúncia só entra na fila do admin; ela não muda status, não esconde o texto e não dispara nova moderação. Quem decide continua sendo o admin (ou a moderação, quando o comentário ainda está pendente).

### 4. Rejeitar ou excluir resolve as pendentes
`ResolveColorCommentReports` é chamado pela moderação do admin ao rejeitar ou excluir: o alvo deixou de existir como publicado e a denúncia não precisa de decisão individual. A resolução automática não grava `resolved_by`; a resolução e a dispensa feitas pelo admin gravam.

### 5. Painel
`/admin/comment-reports` lista por status com o texto do comentário (inclusive excluído, para contexto), resolve ou dispensa pessoas, e audita `comment_report.resolved`/`comment_report.dismissed` com ids no `meta` — nunca o texto. Encerrar a denúncia não modera o comentário: as ações de aprovar/rejeitar/excluir ficam na tela de moderação (ADR-140).

### 6. Antiabuso
Throttle dedicado `colors-reports`; escrita exige a flag de comentários ligada (ADR-143) e responde 404 uniforme com ela desligada.

## Consequências
- Denúncia repetida é barata de barrar e não cria histórico duplicado.
- Reabrir uma dispensada preserva a linha, a data e o histórico de resolução anterior.
- O admin tem duas filas distintas (denúncias e moderação) sem acoplamento escondido.
- Rejeitar/excluir limpa a fila de denúncias sem passar por cada linha.
- Sem notificação ao denunciante, denunciar não vira canal de resposta.

## Alternativas rejeitadas
- **Permitir várias denúncias por par**: inflava a fila e permitia denúncia em massa por uma conta só.
- **Criar linha nova ao reabrir**: perdia o histórico e multiplicava registros para o mesmo par.
- **Esconder o comentário ao ser denunciado**: dá a qualquer logado o poder de sumir com o texto sem decisão.
- **Notificar o denunciante**: transforma a denúncia em conversa e expõe o autor da ação.
- **Motivo em texto livre**: cria conteúdo não moderado no próprio fluxo de moderação.
