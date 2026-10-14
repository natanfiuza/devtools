# ADR-023-login-social-e-conta-sem-senha
Status: Aceito
Data: 2026-10-13

## Decisao
**Login social com Google e GitHub** (Laravel Socialite), e **conta sem senha**
como estado legitimo do usuario.

- O vinculo vive na tabela `social_accounts` (`provider` + `provider_id`
  unicos, `user_id`). A mesma conta pode ter Google e GitHub.
- **Vinculo com conta existente so quando o provedor confirma o e-mail.** Google
  diz isso em `email_verified`; no GitHub a conta so existe com e-mail
  confirmado e o escopo `user:email` devolve o primario verificado. Provedor que
  nao confirma o e-mail **nao vincula**: volta ao login com aviso.
- Conta nova nasce com `users.password` **nulo** (`email_verified_at` preenchido
  so quando o provedor confirma).
- Quem nao tem senha define a primeira em `POST /{locale}/user/password/set`
  (`password.set`), a partir da **propria tela de confirmar senha** do Fortify,
  e cai em `settings/security` com a area sensivel ja liberada
  (`auth.password_confirmed_at`).
- Botao social fica **sempre visivel**, mesmo com o provedor sem credencial no
  `.env`; o clique volta ao login com um aviso, em vez de estourar erro.
- O e-mail local e reenviado pelo Fortify normalmente: conta social entra no
  mesmo fluxo de verificacao.

## Por que
"Entrar com o Google" sem senha e o caminho de menor atrito — e um usuario que
existe so por ali nao e um caso de borda, e o estado normal de uma fatia da
base. Tratar `password` como obrigatorio forcaria senha aleatoria (que a pessoa
nao conhece) ou um cadastro pela metade.

## Consequencias
- **`users.password` passou a aceitar nulo** — migration retrocompativel. Todo
  codigo que assume senha precisa perguntar antes (`User::hasPassword()`).
- **A area sensivel tem duas portas.** O Fortify manda para a tela de confirmar
  senha quando o usuario tenta mexer em settings/security; quem nao tem senha
  ve nessa tela o formulario de **definir**. Sem isso, a conta social ficaria
  presa: a tela pediria uma senha que nunca existiu, para sempre.
- **Definir senha e uma vez so.** `SetPasswordController` responde 403 para quem
  ja tem senha — trocar senha e em `settings/security`, informando a atual.
- **A janela de verificacao se abre com o e-mail do provedor.** `email_verified_at`
  e preenchido quando o provedor confirma, inclusive para conta pre-existente
  que ainda nao tinha verificado o e-mail. Isso move a confianca do e-mail local
  para o provedor — aceito porque so acontece com e-mail confirmado dos dois
  lados.
- **Recusa sem verificacao e uma saida normal**, nao um erro: a pessoa pode
  entrar com a senha e verificar o e-mail local por conta propria. Basta existir
  uma conta local com o mesmo e-mail e um provedor permissivo para o vinculo
  virar tomada de conta, entao a porta fecha.
- Provedor sem credencial nao esconde o botao: some a tentacao de descobrir por
  tentativa e erro qual ambiente tem chave configurada.
- `SocialProviders` (config `services.*`) e a lista fechada: `whereIn` nas rotas
  impede que `{provider}` vire qualquer coisa. Um provedor novo e uma linha
  aqui, credencial no `.env` e o botao na tela.

## Alternativas descartadas
- **Confiar no e-mail que o provedor devolve sem checar verificacao**: um
  provedor permissivo (ou uma conta criada com e-mail alheio la) viraria login
  na conta de outra pessoa aqui.
- **Senha aleatoria para conta social**: o usuario teria uma senha que nao
  conhece; "esqueci a senha" viraria o caminho padrao para definir uma — mesmo
  efeito do fluxo atual, com um estado invalido no banco.
- **Login por magic link no lugar do definir senha**: mais uma tela, mais um
  e-mail, mais um token para expirar, e nao resolve nada que o definir senha nao
  resolva.
- **Tabela `passwords` separada de `users`**: nao ha segundo fator nem historico
  para justificar; uma coluna anulavel e um `hasPassword()` resolvem.
- **Esconder o botao quando falta credencial**: geraria "o botao do Google
  sumiu" como bug de configuracao, em vez de mensagem clara na tela.
