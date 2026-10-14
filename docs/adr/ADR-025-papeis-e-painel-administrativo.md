# ADR-025-papeis-e-painel-administrativo
Status: Aceito
Data: 2026-10-14

## Decisao
**Existem dois papeis - `user` e `admin` -, eles vivem numa coluna de `users`, e
toda acao sobre a conta de outra pessoa passa por uma policy que deixa rastro.**

- `users.role` (`string(16)`, default `user`, indexado) com o enum
  `App\Enums\UserRole` (`user` | `admin`). Sem pacote de permissoes.
- A porta do painel e o Gate `access-admin` (`$user->isAdmin()`), aplicado no
  grupo de rotas `/admin` junto de `auth`, `verified` e `active`. O que se pode
  fazer sobre **uma conta** e a `UserPolicy`: `viewAny`/`view`/`update` para
  qualquer admin; `manage` (suspender, excluir, entrar como) recusa admin e
  recusa a propria conta; `changeRole` vale sobre qualquer conta **menos a
  propria**; `impersonate` e `manage` sem conta suspensa.
- O primeiro admin nasce por comando, nao por seeder:

  ```bash
  php artisan devtools:admin devtools@natanfiuza.dev.br
  php artisan devtools:admin devtools@natanfiuza.dev.br --revoke
  ```

  O comando e idempotente (rodar duas vezes nao muda nada e nao duplica
  auditoria), aceita e-mail inexistente com erro claro e registra a mudanca em
  `admin_actions` com `meta.via = cli`.
- Toda acao administrativa grava uma linha em `admin_actions`: `actor_id`
  (nulo no comando, `nullOnDelete` no resto), `action` (enum
  `AdminActionType`), `target_type`/`target_id`, `meta` (JSON), `ip` e
  `created_at`. **O conteudo de schema ou prompt nunca entra**, nem no `meta`.
- Suspensao e `users.suspended_at` + middleware `active` em toda rota
  autenticada: suspender **derruba a sessao aberta** em vez de so barrar o
  proximo login (login por senha e social recusam conta suspensa).
- Impersonation guarda o id do admin em sessao (`Impersonation::SESSION_KEY`),
  marca a sessao como do alvo e mostra uma faixa no topo de toda tela
  autenticada. A saida (`DELETE /impersonation`) fica **fora** do grupo do
  painel: durante a impersonation a sessao e a do alvo, que nao e admin e por
  isso nao passaria pelo Gate.
- As nove acoes sobre uma conta sao uma rota cada
  (`users/{user}/verify-email`, `resend-verification`, `send-password-reset`,
  `role`, `suspend`, `unsuspend`, `plan`, `quota` e `DELETE users/{user}`) e
  cada uma declara sua autorizacao no controlador.

## Por que
O site tem um administrador. Um pacote de permissoes (papeis, permissoes,
caches, telas) resolveria um problema que nao existe, e a autorizacao ficaria
espalhada em anotacoes em vez de num lugar so - a `UserPolicy` cabe na tela e
diz exatamente quem pode o que.

Separar `manage` de `changeRole` e a diferenca entre **mexer na conta** e
**mexer no papel**: rebaixar a si mesmo e trancar a porta por dentro, e
suspender/excluir um admin (ou a propria conta) deixaria o site sem ninguem
capaz de administra-lo. Ja promover e rebaixar outro admin precisa ser possivel
- e por ali que se deixa de ser admin.

O primeiro admin vem de comando porque nao existe tela antes de existir admin,
e porque o deploy roda `migrate --force`, **nao** `db:seed`: um seeder nao
rodaria sozinho e a conta ficaria sem porta de entrada. Foi o que fechou a
pendencia da SPEC secao 11.2.

Suspender derrubando a sessao e a diferenca entre uma suspensao real e uma
suspensao decorativa: quem ja estava dentro continuaria usando o site ate o
cookie vencer.

## Consequencias
- `admin_actions` e a fonte da tela de Auditoria (`/admin/audit`), com filtro
  por tipo e paginacao. Auditoria **nao** e log de aplicacao: nada ali e escrito
  em arquivo ou em `storage/logs`.
- Conta suspensa que tenta entrar recebe aviso traduzido (`auth.suspended`), e
  nao um 403 cru; conta suspensa que estava logada cai no login no primeiro
  request autenticado.
- O middleware `active` foi adicionado a **todas** as rotas autenticadas,
  inclusive settings e a area da conta - nao so ao painel.
- A impersonation e deliberadamente assimetrica: o admin ve o painel do alvo,
  nao o painel administrativo. A faixa nao pode ser dispensada (so sair).
- `admin_actions.actor_id` e `nullOnDelete`: excluir a conta de um admin nao
  apaga a historia do que ele fez.
- Toda acao nova sobre conta precisa de: rota propria, `Gate::authorize`, linha
  de auditoria e teste. E o padrao que as nove seguiram.

## Alternativas descartadas
- **`spatie/laravel-permission`**: pacote inteiro para dois papeis fixos; traria
  tabelas, cache e telas que ninguem vai usar.
- **`users.is_admin` booleano**: vira uma coluna nova a cada papel seguinte; o
  enum da um nome ao conceito e um lugar para `isAdmin()`.
- **Seeder para o primeiro admin**: nao roda no deploy (`migrate --force`) e
  exigiria um comando depois de qualquer forma.
- **Um terceiro papel** (suporte, financeiro): ninguem pediu; o dia em que
  pedirem, o enum e a policy sao o lugar de acrescentar.
- **Suspender sem derrubar a sessao**: a suspensao so valeria no proximo login,
  que e o caso raro - o usuario suspenso por abuso normalmente esta usando o
  site agora.
- **403 com 404 disfarçado** (esconder a existencia do painel): o painel nao e
  segredo, e o 403 honesto ajuda quem foi rebaixado por engano a entender o que
  aconteceu.
- **Auditoria em arquivo de log**: `admin_actions` e consultavel, filtravel e
  sobrevive ao rotate dos logs; e o painel precisa dela como dado.
