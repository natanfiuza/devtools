# Criterios de aceite — FASE 0 (Fundacao)

Gerado em 2026-10-13, no inicio da fase (regra do `docs/SPEC.md` secao 9).
Fonte: `docs/SPEC.md` secao 9 (F0) + `docs/design/DESIGN.md` (entregaveis) + decisoes do grilling.

## Escopo da fase

Projeto Laravel 13 + starter Vue sobre `laravel/vue-starter-kit` (`dev-main`), TypeScript estrito, tokens do DESIGN.md, shadcn-vue, i18n em 5 idiomas, layout, tema claro/escuro, `deploy.sh`, supervisor, Pest e Vitest.

## Criterios

| # | Criterio | Como verificar |
|---|---|---|
| 1 | O app sobe local do zero seguindo o `README.md` (composer install, npm install, `.env`, migrate, dev) | Seguir o README numa copia limpa |
| 2 | `composer test` verde com **Pest 4** | `composer test` |
| 3 | Paridade de chaves entre os 5 arquivos de idioma | teste Pest `lang/*` |
| 4 | Resolucao de locale e redirect de `/` para `/en` | testes Pest de rota |
| 5 | `npm run test` verde com **Vitest 5** | `npm run test` |
| 6 | Contraste **AA** dos pares de tokens nos dois temas | teste Vitest que calcula o ratio WCAG |
| 7 | `npm run build` e `npm run build:ssr` sem erro | os dois comandos |
| 8 | `vue-tsc --noEmit` limpo com TS estrito | `npm run types:check` |
| 9 | As 5 URLs (`/en`, `/pt-br`, `/pt-pt`, `/fr`, `/es`) respondem; `/` redireciona para `/en`; seletor troca e persiste; `hreflang` no head | smoke manual + teste Pest |
| 10 | Tema claro/escuro/sistema funciona, persiste e **nao pisca** ao recarregar | smoke manual (recarregar em cada tema) |
| 11 | `/styleguide` renderiza os componentes do DESIGN nos dois temas | navegar em `/styleguide` |
| 12 | Zero string hardcoded nas telas (tudo via i18n) | revisao + busca por texto literal nos componentes |
| 13 | Nada de modelo/preco/limite de IA no codigo | revisao (regra do CLAUDE.md) |
| 14 | `deploy/deploy.sh` (build no servidor, atomico, PATH explicito) e `deploy/supervisor/devtools-ssr.conf` prontos | revisao dos arquivos + RUNBOOK |
| 15 | `README.md` e `docs/RUNBOOK.md` escritos | revisao |
| 16 | Docs atualizados: `SPEC.md` §11, `ADR-014`, `ADR-021`, `DESIGN.md`, `CHANGELOG.md` | revisao |
| 17 | **Nenhum commit feito pelo assistente** | `git log` / `git status` |

## Fora do escopo da F0

- Registro de tools, home real, busca Cmd+K, banner LGPD, GA4, sitemap e OG images (F1).
- JSON Viewer e CodeMirror (F2).
- Auth/OAuth/verificacao (F4) — o scaffold do Fortify entra, mas a fase de auth e a F4.
- Deploy real no VPS: feito pelo usuario, guiado pelo RUNBOOK.

## Resultado (2026-10-13)

| # | Criterio | Resultado |
|---|---|---|
| 1 | App sobe do zero pelo README | Escrito e conferido contra o fluxo real; a prova "do zero" depende do banco `devtools` no MariaDB local (SQL no README), que e acao do usuario |
| 2 | `composer test` verde (Pint + PHPStan nivel 7 + Pest 4) | OK — 55 testes, 775 assercoes |
| 3 | Paridade de chaves entre os 5 idiomas | OK — `TranslationsParityTest` (chaves iguais nos 5, nada vazio, nomes de idioma completos) |
| 4 | Resolucao de locale e redirect de `/` | OK — `LocaleRoutingTest` (header, cookie, conta, query preservada, 404 fora da lista, cookie gravado ao visitar `/es`) |
| 5 | `npm run test` verde (Vitest 5) | OK — 59 testes em 3 arquivos |
| 6 | Contraste AA nos dois temas | OK — pior par 4.55 no claro e 4.68 no escuro; anel de foco 4.55 / 10.58 |
| 7 | `npm run build` e `npm run build:ssr` | OK — 5.6s e 3.0s |
| 8 | `vue-tsc --noEmit` limpo (TS estrito) | OK |
| 9 | 5 URLs, redirect de `/`, seletor, hreflang | OK — testes + smoke com SSR ligado: `/` -> 302 `/en`, `/de` -> 404, hreflang dos 5 + `x-default`, canonical |
| 10 | Tema claro/escuro/sistema sem flash | OK — `AppearanceTest` + smoke: cookie `dark` da `data-appearance="dark" class="dark"`; sem cookie, `system` com o `matchMedia` inline |
| 11 | `/styleguide` nos dois temas | Rota OK (renderiza nos testes); conferencia visual fica para a revisao |
| 12 | Zero string hardcoded nas telas da F0 | OK nas telas da F0 (Home, Styleguide, layout, header, footer, settings/Appearance); as telas de auth do kit seguem em ingles (carry-over da F4, abaixo) |
| 13 | Nada de modelo/preco/limite de IA no codigo | OK — so `config/` e `.env` |
| 14 | `deploy.sh` + supervisor prontos | OK — revisados; o deploy em si so roda no VPS |
| 15 | README e RUNBOOK escritos | OK |
| 16 | Docs atualizados | OK — SPEC (§2, §3, §9, §10, §11), ADR-014 revisado, ADR-021 novo, DESIGN (AA + fontes), CHANGELOG novo |
| 17 | Nenhum commit feito pelo assistente | OK — `git log` so tem o `first commit` do usuario |

Smoke com SSR ligado (`INERTIA_SSR_ENABLED=true`, SQLite descartavel em
diretorio temporario, removido depois): as 5 home pages renderizam no
servidor (~26 KB de HTML contra ~13 KB sem SSR), com traducao no HTML
("Ferramentas" no pt-BR) e **zero erro no log do SSR**. Dois bugs de SSR
foram encontrados e corrigidos nesse smoke: o glob de paginas apontava para o
diretorio errado (invisivel no cliente, porque o plugin do Inertia injeta o
`resolve`) e a diretiva `v-focus` so estava registrada no cliente.

## Antecipado em 2026-10-13 (bloco de auth e login social)

O ciclo de autenticacao entrou junto da F0, por decisao do grill: telas de acesso
traduzidas nos 5 idiomas e reconstruidas no design system, auth sob `/{locale}`
(ADR-022), login social Google/GitHub com conta sem senha (ADR-023), newsletter
com duplo opt-in (ADR-024) e as paginas de Privacidade e Termos.
Plano e levantamento: `docs/plans/master/20261013_auth_login_plansfacts.md` e
`docs/survey/20261013_auth_login_surveyfacts.md`; relatorio em
`docs/claude-code/reports/master/20261013_master_auth_login.md`.

| # | Criterio (do plano) | Resultado |
|---|---|---|
| 1 | Telas de acesso respondem 200 e traduzem nos 5 idiomas | OK — `LocalizedAuthPagesTest` |
| 2 | Todo o auth sob `/{locale}`; `/auth/{provider}/*` fora; nomes de rota preservados | OK — `LocalizedAuthPagesTest` + `SocialAuthTest` |
| 3 | Login social: conta nova, vinculo por e-mail verificado, recusa sem verificacao, provedor sem credencial | OK — `SocialAuthTest` (`Socialite::fake`) |
| 4 | Conta sem senha define a senha e chega em `settings/security` | OK — `SetPasswordTest` |
| 5 | Newsletter: inscrever, confirmar, descadastar, duplicado, token invalido | OK — `NewsletterTest` |
| 6 | `noindex, follow` + canonical nas telas de acesso, sem hreflang; hreflang nas legais | OK — `LocalizedAuthPagesTest` + `LegalPagesTest` |
| 7 | Privacidade e Termos sob `/{locale}`, nos 5 idiomas, no rodape | OK — `LegalPagesTest` |
| 8 | Zero string hardcoded nas telas tocadas | OK — `auth-login.test.ts` e `newsletter-form.test.ts` usam traducoes marcadoras |
| 9 | `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | OK |
| 10 | Nenhum commit feito pelo assistente | OK |

Bugs encontrados e corrigidos ao escrever os testes e no smoke de SSR (detalhe
no relatorio): o link de token invalido montava a URL com o codigo do idioma em
vez do slug; o `ResolveLocale` rodava **depois** do `Authenticate` (ordem de
prioridade do framework), entao o visitante sem sessao era mandado para um login
sem `{locale}`; a reinscricao nao voltava para pendente; a data de revisao
invalida em `config/legal.php` derrubava as paginas legais; e o teste de conta
social lia o payload cru fora do contrato do Socialite.

O smoke de SSR pegou ainda um bug que a suite nao pegava: o exemplo de e-mail do
placeholder (`voce@exemplo.com`) nao compila no vue-i18n — o `@` e sintaxe de
mensagem vinculada —, e a tela de login **inteira** deixava de renderizar no
servidor. Os 20 textos afetados (5 idiomas) passaram a usar `{'@'}`, e
`TranslationsParityTest` agora falha se aparecer `@` ou `|` cru em qualquer
traducao. As traducacoes dos testes de componente sao marcadores e nao pegavam
isso: foi preciso renderizar a tela de verdade.

## Antecipado em 2026-10-14 (papeis, modelo comercial e paineis)

Bloco decidido no grill (Q1 a Q13): papeis e o painel administrativo (era F7), o
modelo comercial com venda manual (era F6) e o que faltava da F4 (telas de
settings traduzidas, exportar e excluir conta). Plano e levantamento:
`docs/plans/master/20261014_papeis_paineis_plansfacts.md` e
`docs/survey/20261014_papeis_paineis_surveyfacts.md`; relatorio em
`docs/claude-code/reports/master/20261014_master_papeis_paineis.md`.

| # | Criterio (do plano) | Resultado |
|---|---|---|
| 1 | Existe papel; usuario comum recebe 403 no `/admin` e visitante vai para o login | OK — `AdminRoleTest` |
| 2 | O primeiro admin nasce pelo comando `devtools:admin`, idempotente, e pode ser rebaixado | OK — `AdminRoleTest` + smoke real (a segunda execucao responde "ja esta como admin") |
| 3 | Toda acao administrativa deixa linha de auditoria, sem conteudo sensivel | OK — `AdminAccountActionsTest` + revisao das colunas (o `meta` so leva ids, valores e origem) |
| 4 | Planos, assinaturas, pagamentos e ledger existem; o plano efetivo respeita o vencimento | OK — `BillingTest` e `QuotaTest` (o periodo vencido deixa de ser corrente, sem estado `expirada`) |
| 5 | O admin lista e filtra contas, ve o detalhe e executa as 9 acoes, com as travas de auto-exclusao | OK — `AdminPanelTest` + `AdminAccountActionsTest` |
| 6 | Suspensao bloqueia login (senha e social) e derruba sessao ativa | OK — `AdminAccountActionsTest` |
| 7 | Impersonation entra, avisa e sai; recusa encadear, impersonar admin ou suspenso | OK — `AdminImpersonationTest` |
| 8 | O painel do usuario mostra plano, cota e faturas, com estados vazios coerentes | OK — `AccountPanelTest` + `account-panel.test.ts` |
| 9 | Exportar devolve JSON completo; excluir anonimiza e preserva pagamentos | OK — `AccountPanelTest` (download, nome do arquivo e conteudo) e `ProfileUpdateTest` (anonimizacao, sessoes e pagamento intacto) |
| 10 | Idioma escolhido no cadastro sobrevive a navegacao em outro idioma | OK — `RegistrationTest` (escolha, ausencia e codigo desconhecido) + `LocaleRoutingTest` (cookie antes da conta) |
| 11 | Zero string hardcoded nas telas novas (5 idiomas, paridade verde) | OK — `TranslationsParityTest` + `admin-panel.test.ts` e `account-panel.test.ts` com traducao marcadora |
| 12 | Gates: `composer test`, `npm run test`, `npm run types:check`, `npm run build`, `npm run build:ssr` | OK — 220 testes e 4.660 assercoes (Pint + PHPStan + Pest 4); 105 testes em 7 arquivos (Vitest 5); `vue-tsc` limpo; os dois builds sem erro |
| 13 | Smoke SSR das telas novas | OK — tabela abaixo |
| 14 | Nenhum commit feito pelo assistente | OK — as mudancas ficam na arvore, para o usuario commitar |

Smoke de SSR: banco SQLite descartavel, `php artisan serve` com
`INERTIA_SSR_ENABLED=true` e `node bootstrap/ssr/ssr.js` no 13714; o admin nasceu
pelo proprio comando `devtools:admin` e a sessao foi aberta por POST em
`/pt-br/login` (302 para `/dashboard`).

| URL | HTTP | SSR | Tamanho |
|---|---|---|---|
| `/dashboard` | 200 | sim | 57.9 KB |
| `/dashboard/history` | 200 | sim | 55.6 KB |
| `/dashboard/billing` | 200 | sim | 56.2 KB |
| `/settings/profile` | 200 | sim | 63.5 KB |
| `/admin` | 200 | sim | 44.2 KB |
| `/admin/users` | 200 | sim | 55.4 KB |
| `/admin/users/2` | 200 | sim | 55.6 KB |
| `/admin/subscribers` | 200 | sim | 48.0 KB |
| `/admin/plans` | 200 | sim | 82.4 KB |
| `/admin/payments` | 200 | sim | 48.2 KB |
| `/admin/audit` | 200 | sim | 49.6 KB |
| `/settings/security` | 302 | — | — |

Conferido no HTML renderizado pelo servidor: as seis secoes do painel
(`Visão geral`, `Contas`, `Assinantes`, `Planos`, `Pagamentos`, `Auditoria`),
`Voltar ao site`, os quatro cards da visao geral (`Gratuito`, `Vitalício`,
`Sem assinatura ativa`, `Próximo vencimento`, `Ver faturas`) e, em
`/admin/plans`, o preco vindo da tabela (`29,90`, `299,00`, `Preço`). Os 302
esperados: `/settings/security` pede a senha antes de abrir (area sensivel). Log
do SSR limpo nas telas da aplicacao — o unico erro registrado foi da sonda do
proprio smoke (um POST sintetico com componente inexistente), e o
`storage/logs/laravel.log` nao recebeu linha nova durante o smoke.

## Carry-overs

- **F4**: gravar `users.appearance` pela conta — a coluna so e lida (o tema vive
  no cookie); o i18n das telas de settings fechou em 2026-10-14.
- **F4**: mensagens de validacao nos 5 idiomas — o Laravel so traz
  `validation.php` em ingles, entao erro de formulario nas telas traduzidas sai
  em ingles (e com nome de campo em ingles). Vale para todo formulario do site.
- **F4**: decidir se 2FA/passkeys voltam — os dois foram desligados no config do
  Fortify e os testes correspondentes removidos na F0.
- **F1**: home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap, OG images.
- **F2**: `--elevated` como cor de pontuacao JSON (SPEC secao 11.2).
- **F5**: consumo real da cota (o debito no livro-razao e as telas de IA do
  painel), coluna de dialetos em `output_targets` e a escolha entre AI SDK e
  cliente HTTP fino (SPEC secao 11.2).
- **F6**: gateway (interface `PaymentGateway` + Mercado Pago), doacao, webhooks
  e a renovacao da franquia a cada periodo — hoje conceder de novo e ato manual
  (ADR-027).
- **F7**: historico de uso real, SEO final e hardening.
- **Operacao (usuario)**: criar banco/usuario `devtools` no MariaDB local;
  confirmar PHP 8.3 e usuario do cron no VPS; `composer self-update` (a versao
  2.9.x local tem advisory corrigido na 2.10.3); rotacao de chaves no VPS.
- **Operacao (usuario)**: cadastrar as credenciais do Google e do GitHub no
  `.env` e as URLs de callback (`/auth/google/callback`, `/auth/github/callback`)
  nos consoles dos provedores; sem elas o botao aparece e volta com aviso.
