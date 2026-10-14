# ADR-022-auth-sob-prefixo-de-idioma
Status: Aceito
Data: 2026-10-13

## Decisao
**Toda tela de autenticacao fica sob `/{locale}`** — login, cadastro, esqueci a
senha, redefinir senha, verificar e-mail, confirmar senha e logout. O caminho e
declarado em `config/fortify.php` (`paths`), com o `{locale}` validado pelo
`Route::pattern('locale', ...)` global do `AppServiceProvider`. Os nomes de rota
**nao mudam**: continua `route('login')`, `route('password.reset')` e assim por
diante.

**Endpoint de maquina fica fora do prefixo** — `/auth/{provider}/redirect`,
`/auth/{provider}/callback`, `/api`, `/webhooks` e `/admin`. O idioma de uma
volta ao provedor viaja na sessao, nao na URL.

## Por que
A tela de login e uma pagina do site: quem chega pelo Google precisa entrar numa
tela no idioma dele, e a URL precisa ser compartilhavel e coerente com o resto do
site. Ja a URL de callback e cadastrada **uma vez** no console do provedor; se
ela carregasse o idioma, seriam cinco (ou N) URLs de callback para manter em cada
provedor — e um idioma novo no site viraria uma tarefa de configuracao externa.

## Consequencias
- **`URL::defaults(['locale' => ...])` virou infraestrutura critica.** O
  `ResolveLocale` grava o default a cada request, e e ele que faz `route('login')`
  continuar funcionando sem argumento. Gerar URL antes de o idioma ser resolvido
  lanca `UrlGenerationException`.
- **Ordem de middleware e a ordem de prioridade, nao a do grupo `web`.** O
  `Authenticate` esta na lista `$middlewarePriority` do framework, entao o
  middleware de rota `auth` **passa na frente** dos middlewares anexados ao grupo
  `web` — e o `ResolveLocale` era um deles. Na pratica, o visitante sem sessao
  que abria `/dashboard` era redirecionado para o login **antes** de o idioma
  existir: o `route('login')` do redirect estourava ou saia sem o `{locale}`.
  A correcao e `prependToPriorityList(before: AuthenticatesRequests::class,
  prepend: ResolveLocale::class)` no `bootstrap/app.php`, que posiciona o
  `ResolveLocale` logo depois do `StartSession` (de onde saem a sessao e o cookie
  de idioma) e antes do `Authenticate`.
- A suite de testes fixa `URL::defaults(['locale' => Locales::fallback()])` no
  `TestCase::setUp`, senao os testes herdados da F0 que chamam `route('login')`
  sem argumento quebram. O request de verdade sobrescreve o default com o idioma
  resolvido da URL.
- **Acesso sem prefixo da certo 404, nao redirect.** `/login`, `/register` e
  `/dashboard` continuam existindo como caminho (dashboard e settings nao tem
  prefixo, por decisao da F0), mas `/{locale}/...` fora dos slugs conhecidos nao
  casa com nenhuma rota.
- Telas de acesso e paginas de token entram em `noindex, follow` + canonical e
  **sem** hreflang (nao tem conteudo proprio para indexar); as paginas legais
  seguem indexaveis com hreflang normal (`App\Support\Seo`).
- O `guest` do Fortify age normalmente dentro do prefixo: usuario autenticado que
  abre `/pt-br/login` e levado ao dashboard.

## Alternativas descartadas
- **Callback sob o prefixo**: cadastro de callback por idioma em cada provedor, e
  um idioma novo no site exigiria mexer no console do Google e do GitHub. Custo
  recorrente por um ganho nulo — a URL de callback nao e vista por ninguem.
- **Deixar as telas de acesso sem prefixo** (estado da F0): era a excecao que
  ninguem conseguia enunciar ("as paginas publicas sao prefixadas, menos o
  login"). Visitante estrangeiro entrava por tela em ingles e o link de
  redefinir senha voltava para `/{locale}` — dois idiomas na mesma jornada.
- **Renomear as rotas para `locale.login`**: o Fortify nomeia as rotas que
  registra e usa esses nomes por dentro (redirects, listeners, `guest`/`auth`);
  renomear significaria reimplementar o provider inteiro para ganhar nada.
- **Guardar o idioma na URL de callback** (`/auth/{provider}/callback?locale=fr`):
  o parametro extra nao e devolvido de forma confiavel pelos provedores, e o
  `state` ja e o lugar certo para carregar contexto da ida — a sessao ja faz isso
  de graca.
