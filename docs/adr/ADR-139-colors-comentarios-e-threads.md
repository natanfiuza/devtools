# ADR-139 - Comentários, threads e visibilidade
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-111 (comentários em threads, emenda), ADR-110 (moderação IA, emenda), ADR-114 (denúncia, emenda), ADR-135 (curtidas), ADR-140 (moderação e cache), ADR-142 (notificações), ADR-143 (kill switch)

## Contexto
A ADR-111 decidiu threads de no máximo dois níveis, só para logados, 255 caracteres e edição/exclusão pelo autor. A F7 implementa a superfície, mas o prompt da fase trazia uma tensão explícita: **nunca apresentar uma revisão não aprovada como aprovada**. Ao mesmo tempo, esconder todo comentário `pending` até a IA responder tornaria a conversa lenta e daria à IA o poder de sumir com o texto por indisponibilidade; gravar e só mostrar depois exigiria uma segunda tela ou polling.

Também era preciso decidir o que acontece quando uma edição é rejeitada, o que sobra de um comentário-pai excluído e como o autor removido aparece sem quebrar a thread.

## Decisão

### 1. Dois níveis, impostos no servidor
`parent_id` só aponta para uma raiz da mesma paleta. Responder a uma resposta responde 422; o formulário de resposta só existe na raiz. A raiz é paginada (`comments_page`), as respostas vêm em ordem crescente com limite configurável (`colors.comments.replies_limit`).

### 2. `pending` é visível, com selo “em análise”
O comentário nasce `pending` e aparece imediatamente para todos com o selo de análise; `flagged` usa o mesmo selo. A leitura da decisão 1 da fase é: **nunca exibir como aprovado/definitivo**, não “nunca mostrar”. O autor vê o próprio estado na mesma thread, e o texto só ganha aparência de publicação quando o status vira `approved`.

### 3. `body`, `approved_body` e a edição rejeitada
`body` é a revisão atual; `approved_body` guarda a última versão aprovada. Editar volta o comentário a `pending`, limpa `moderated_at`/`moderation_reason`, carimba `edited_at` e despacha nova moderação. Se a edição for rejeitada, o apresentador volta a mostrar `approved_body`; sem versão aprovada anterior, o comentário some (tombstone se tiver respostas). O texto rejeitado nunca é exibido.

### 4. `revision` invalida resultado obsoleto
Cada escrita de conteúdo incrementa `revision`. O resultado da moderação só é aplicado por `UPDATE ... WHERE id = ? AND revision = ? AND status = pending`; um job antigo não aprova uma revisão nova nem desfaz uma decisão do admin (que também incrementa a revisão).

### 5. Exclusão e tombstone
Excluir é soft delete e só o autor ou o admin fazem. A raiz excluída com respostas continua aparecendo como “comentário removido”; a resposta excluída some. O texto e a linha permanecem no banco para o admin e para a auditoria. O admin mantém o registro inclusive depois da exclusão.

### 6. Autor removido não apaga a conversa
Anonimizar a conta mantém o texto e apresenta o autor como “conta removida”; o purge definitivo deixa `user_id` nulo (`nullOnDelete`) e o mesmo rótulo. O apresentador nunca devolve `user_id`, e-mail, `moderation_reason`, `revision` nem `approved_body` como campo separado.

### 7. Superfície
- `POST /api/tools/colors/palettes/{palette}/comments` — `palettes.comments.store`
- `PATCH/DELETE /api/tools/colors/comments/{comment}` — `comments.update`/`comments.destroy`
- escrita exige login, conta verificada e ativa; leitura é pública quando a flag está ligada
- a thread só chega nas props da página pública com `comments !== null`; visitante lê, não escreve

## Consequências
- A conversa aparece sem esperar a IA, e a aprovação apenas remove o selo.
- A edição nunca deixa o texto rejeitado visível, mas também não rebaixa a versão aprovada.
- O servidor é a única autoridade sobre profundidade, autoria e estados; a UI só reflete.
- O texto permanece no banco com exclusão e com conta removida, o que é necessário para thread e auditoria.
- O apresentador público é o único caminho de saída do corpo para o navegador; campos internos ficam fora do payload.
- A flag desligada deixa a página sem thread e os endpoints em 404 uniforme (ADR-143).

## Alternativas rejeitadas
- **Esconder `pending` até a aprovação**: contradiz o selo “em análise” aprovado e faz a thread depender da latência da IA.
- **Mostrar o texto rejeitado com marca**: expõe conteúdo vetado e contraria a decisão de reverter para a versão aprovada.
- **Editar em cima de `body` sem `approved_body`**: perderia a última versão publicada e não haveria para onde voltar.
- **Hard delete**: quebraria respostas e apagaria o histórico necessário ao admin.
- **Permitir resposta a resposta com achatamento**: muda o modelo de thread sem decisão própria e confunde a notificação de resposta.
