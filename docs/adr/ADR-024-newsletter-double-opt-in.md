# ADR-024-newsletter-double-opt-in
Status: Aceito
Data: 2026-10-13

## Decisao
**A newsletter so entra em vigor com duplo opt-in, e nenhuma mudanca de estado
acontece por GET.**

- Inscricao: `POST /{locale}/newsletter` (rodape publico) e o checkbox
  **desmarcado por padrao** no cadastro. Grava a linha como pendente
  (`confirmed_at` nulo), gera token e manda o e-mail de confirmacao.
- **O link do e-mail nao confirma nada.** `GET /{locale}/newsletter/confirm/{token}`
  abre uma pagina com o botao; quem confirma e o
  `POST /{locale}/newsletter/confirm/{token}`.
- O descadastro espelha o mesmo desenho: pagina com botao no GET, mudanca no
  POST, e o link continua valido depois de descadastrado (a pagina mostra o
  estado, nao um erro).
- Token e `bin2hex(random_bytes(32))`, unico por inscrito, **regenerado a cada
  envio**.
- Reinscricao de quem se descadastrou **volta para pendente**: a confirmacao
  antiga e limpa junto, senao o e-mail novo pediria um clique que nao muda nada.
- Inscrito ja confirmado que pede de novo **nao recebe e-mail** (a resposta diz
  que ja esta inscrito).
- O e-mail vai na fila e **no idioma do inscrito**, e o link carrega esse idioma
  na URL.
- `POST` de inscricao, confirmacao e descadastro com `throttle:10,1`.
- As paginas de confirmar e descadastar sao `noindex` (nao tem conteudo proprio).

## Por que
O token viaja por e-mail, e um e-mail passa por scanner de link e prefetch do
navegador antes de qualquer pessoa clicar. Se o `GET` confirmasse, o antivirus do
provedor ou o preview do cliente de e-mail inscreveriam gente que nunca pediu —
e, pior, **descadastrariam** quem so abriu a mensagem. Com o estado mudando no
`POST`, o que se gasta sozinho e uma abertura de pagina, que nao decide nada.

## Consequencias
- Duas rotas por operacao (`newsletter.confirm` / `newsletter.confirm.store`,
  `newsletter.unsubscribe` / `newsletter.unsubscribe.store`) e duas telas
  (`newsletter/Confirm.vue`, `newsletter/Unsubscribe.vue`) que renderizam o
  estado: `pending`, `confirmed`/`unsubscribed` ou `invalid`.
- **Token invalido nao e tela de erro**: volta para a home do idioma com aviso
  (`Inertia::flash('toast')`). O slug do idioma (`pt-br`) e nao o codigo
  (`pt_BR`) — passar o codigo monta uma URL que nao existe.
- **Descadastrado volta como pendente** e recebe e-mail novo. Sem limpar
  `confirmed_at`, o clique no link novo nao teria efeito e a pessoa estaria na
  lista sem ter confirmado nada — o duplo opt-in viraria formalidade.
- Descadastrar **nao apaga a linha**: o e-mail fica marcado
  (`unsubscribed_at`) para nao reenviar para quem ja disse nao, e o estado
  `isConfirmed()` (`confirmed_at` preenchido e `unsubscribed_at` nulo) e o que a
  lista de envio deve consultar.
- **A origem guardada e a da primeira vez** (`source`: `footer` ou `register`):
  reinscricao nao reescreve de onde a pessoa veio originalmente.
- O locale do inscrito pode ser atualizado no reenvio (`$locale` da acao): o
  segundo pedido pode vir de outra versao do site.
- Fila obrigatoria: o e-mail e `ShouldQueue`. Sem worker rodando, a inscricao
  fica pendente para sempre — operacao coberta por `docs/RUNBOOK.md`.
- SMTP e por `.env` (`MAIL_MAILER`), configuravel para trocar de provedor sem
  codigo.

## Alternativas descartadas
- **Confirmar no `GET`**: e o desenho classico e foi o motivo do ADR. Scanner de
  link corporativo abre **todos** os links da mensagem, o que descadastraria
  assinantes em massa sem nenhum humano envolvido.
- **Sem duplo opt-in** (so gravar o e-mail): lista cheia de endereco errado ou de
  terceiro, e o dominio queima reputacao de envio sem ter aceitacao de ninguem.
- **Token estatico por inscrito**: um vazamento vira confirmacao/descadastro
  permanente de terceiros; regenerar a cada envio limita o estrago a um e-mail.
- **Apagar a linha ao descadastrar**: a pessoa volta a ser "elegivel" e o proximo
  cadastro reenvia para quem ja recusou duas vezes.
- **Confirmar direto no `POST` da inscricao** (link como unico caminho): sem o
  passo intermediario nao ha como mostrar o estado atual ao usuario, e um token
  expirado ou ja usado viraria erro cru.
