# Relatorio - papeis, modelo comercial e paineis (admin e usuario)

Branch: `master`
Data: 2026-10-14
Tarefa: "Crie os papeis necessarios para Admin, Usuarios e um dashboard
administrativo para gerenciar cadastros pagamentos etc.. e um dashboard de
usuario. De continuidade as proximas fases do projeto" + "Na tela de cadastro
adicione a escolha do idioma principal do usuario, crie um campo em users para
isso" (grill de 13 decisoes)
Plano: `docs/plans/master/20261014_papeis_paineis_plansfacts.md`
Levantamento: `docs/survey/20261014_papeis_paineis_surveyfacts.md`

## 1. O que foi entregue

O pedido eram papeis e dois paineis. O grill mostrou que nenhum dos dois existia
sozinho: o painel administrativo precisava do que administrar (o modelo
comercial, que era F6) e o painel do usuario precisava de uma area autenticada
que deixasse de ser o placeholder do starter kit. O escopo fechado cobriu os
tres, em 6 blocos, todos concluidos.

| # | Bloco | Estado |
|---|---|---|
| 1 | Papeis e autorizacao: enum, migracao, gate, policy, comando, auditoria, testes | concluido |
| 2 | Modelo comercial: `plans`, `subscriptions`, `payments`, `usage_ledger`, cota, testes | concluido |
| 3 | Idioma da conta: `users.locale`, escolha no cadastro, resolucao, perfil, testes | concluido |
| 4 | Painel administrativo: rotas, controladores, telas, i18n 5 idiomas, testes | concluido |
| 5 | Painel do usuario: navegacao unica, visao geral, historico, faturas, exportar/excluir | concluido |
| 6 | Documentacao e fechamento: ADRs 025 a 028, glossario, SPEC, DESIGN, CHANGELOG, aceite | concluido |

## 2. Decisoes novas (ADRs)

- **ADR-025 - papeis e painel administrativo**: dois papeis (`user`/`admin`) em
  `users.role` com enum proprio, sem pacote; Gate `access-admin` + `UserPolicy`
  como unico lugar da autorizacao; primeiro admin por
  `php artisan devtools:admin {email} [--revoke]` (idempotente); auditoria em
  `admin_actions`; suspensao derrubando a sessao aberta; impersonation com faixa
  de aviso e saida fora do grupo do painel.
- **ADR-026 - idioma da conta x idioma de navegacao**: `users.locale` e
  preferencia duravel (cadastro e perfil), o cookie registra a navegacao; rota
  prefixada manda, e sem prefixo a ordem e cookie -> conta -> `Accept-Language`
  -> `en`.
- **ADR-027 - venda manual**: assinatura concedida e pagamento registrado fora
  do gateway, com preco e limites na tabela `plans` (carregada por migracao) e
  vigencia resolvida na leitura; `gateway`/`gateway_ref` ja prontos para a F6.
- **ADR-028 - anonimizacao e exportacao**: excluir a conta apaga o dado pessoal
  e preserva `payments`/`subscriptions` (guarda fiscal, garantida por
  `restrictOnDelete`); exportar e download imediato de um JSON, sem arquivo
  parado no servidor.

## 3. Banco de dados

Nove migracoes novas (`2026_10_14_000001` a `000009`), todas aditivas e
retrocompativeis (coluna com default, colunas anulaveis, tabelas novas) - o
deploy roda `migrate --force` a cada minuto e nao pode ter downtime:

| Migracao | O que faz |
|---|---|
| `add_role_to_users_table` | `role` string(16), default `user`, indexado |
| `create_admin_actions_table` | auditoria: ator, acao, alvo, `meta` json, IP |
| `create_plans_table` | catalogo: preco, moeda, intervalo, limites, `active`, `sort` |
| `seed_plans_catalog` | carga inicial dos 3 planos com `insertOrIgnore` |
| `create_subscriptions_table` | vinculo conta-plano com periodo e `gateway` |
| `create_payments_table` | cobranca registrada, com `restrictOnDelete` em `user_id` |
| `create_usage_ledger_table` | livro-razao do consumo (`ai_request_id` sem FK: a tabela nasce na F5) |
| `add_suspended_at_to_users_table` | suspensao |
| `add_anonymized_at_to_users_table` | marca da conta removida |

O catalogo entra por migracao e nao por seeder porque o deploy **nao roda**
`db:seed`: sem ele o site subiria sem plano nenhum (mesmo raciocinio do primeiro
admin, que por isso vem de comando).

## 4. Rotas

- `routes/admin.php` (`/admin`, sem prefixo de idioma, ADR-022): grupo com
  `auth`, `verified`, `active` e `can:access-admin` - Visao geral, Contas
  (lista, detalhe, as nove acoes e `impersonate`), Assinantes (lista, export
  CSV, reinscrever, descadastrar), Planos (lista, editar), Pagamentos (lista,
  registrar, estornar) e Auditoria. A saida da impersonation
  (`DELETE /impersonation`) fica **fora** do grupo: durante a impersonation a
  sessao e a do alvo, que nao e admin.
- Area da conta: `/dashboard` (visao geral), `/dashboard/history`,
  `/dashboard/billing` e `GET /settings/export`, com as URLs de settings
  preservadas.
- Todas as rotas autenticadas ganharam o middleware `active` (suspensao vale na
  hora, e nao so no proximo login).

## 5. Telas

- **Painel administrativo**: `layouts/admin/AdminLayout.vue` (sidebar de 6
  secoes) + `pages/admin/` (Dashboard, Users, UserDetail, Subscribers, Plans,
  Payments, Audit), com `components/admin/AdminTable.vue` (tabela densa do
  DESIGN: borda 1px, sem zebra, estados de vazio e carregando),
  `PlanForm.vue` e `ImpersonationBanner.vue`.
- **Painel do usuario**: `layouts/account/AccountLayout.vue` substitui o layout
  de settings do starter kit; `Dashboard.vue` reescrito (plano, cota,
  assinatura e vencimento, com `OverviewController` no lugar do
  `Route::inertia`); `pages/dashboard/History.vue` e `Billing.vue`;
  `components/ExportData.vue` no dialogo de exclusao.
- **i18n**: namespaces `admin` e `account` nos 5 idiomas, com
  `TranslationsParityTest` exigindo paridade imediata e os testes de componente
  usando traducao marcadora.
- **Limpeza**: 8 arquivos mortos do starter kit apagados (`AppLayout.vue`,
  `app/AppHeaderLayout.vue`, `app/AppSidebarLayout.vue`, `AppHeader.vue`,
  `Breadcrumbs.vue`, `NavFooter.vue`, `PlaceholderPattern.vue` e
  `settings/Layout.vue`).

## 6. Bugs encontrados e corrigidos

Nenhum bug de aplicacao nesta rodada - a suite passou de ponta a ponta em cada
bloco. O que apareceu foi de **documentacao** e de **ferramenta**:

1. **ADR-026 afirmava o contrario do codigo**: o texto dizia que o cadastro
   validava o idioma com `Rule::in`; `CreateNewUser` e tolerante de proposito
   (codigo ausente ou desconhecido cai no idioma da pagina, para ninguem perder
   a conta por causa de um select). So o **perfil** valida
   (`sometimes`, `Rule::in(Locales::codes())`). Corrigido no ADR depois de ler o
   codigo, antes de fechar o bloco.
2. **Armadilha do prefixo `/dashboard`** (nao era bug, virou teste): a sidebar
   marca o item aberto por igualdade de caminho e o `<h1>` por prefixo com o
   maior casamento, entao `/dashboard/history` acende so o Historico, e
   `?page=2` nao troca a secao (a URL e normalizada para o pathname).
3. **Cast `hashed` + `bcrypt()` = hash duplo** (achado no smoke): o `User` tem
   `'password' => 'hashed'`, entao o script que criava a conta do smoke com
   `bcrypt(...)` gravava um hash do hash e o login falhava. E exatamente a
   pedra do relatorio de 2026-10-14 sobre o tinker: a senha vai **em texto
   puro** e o cast faz o hash.
4. **`eslint` fora de controle (achado para decisao do usuario)**: o repo tem
   3876 problemas no baseline - 4 erros `vue/no-reserved-component-names` em
   arquivos de teste da F0 que nao toquei e 3569 avisos `vue/html-indent`.
   Provei que e pre-existente lintando `SidebarProvider.vue`, identico ao HEAD
   (58 avisos). O eslint **nao** e gate do projeto (`composer test` roda Pint +
   PHPStan + Pest); ligar o lint como gate exigiria uma limpeza grande ou
   afrouxar a regra de indentacao. Fica registrado, sem mudanca feita.

## 7. Desvios do plano (conscientes)

- O prop compartilhado saiu como `auth.isAdmin` (booleano), e nao
  `auth.user.is_admin`: o papel cru nao vai para o frontend - o que as telas
  decidem e "pode ver o painel?". Coerente com o `#[Hidden]` do modelo.
- O DESIGN previa tres cards na visao geral; ficaram **quatro** (plano, cota,
  assinatura, vencimento) e sem barra de progresso: assinatura e vencimento sao
  perguntas separadas da cota, e o numero e o dado. O mockup do DESIGN foi
  atualizado para o que foi entregue.
- A visao geral nao recalcula nada: `OverviewController` entrega o estado pronto
  (plano, cota, assinatura, vencimento e contagem de pagamentos) e o componente
  so formata.

## 8. Verificacao executada

| Gate | Resultado |
|---|---|
| `composer test` (config:clear + Pint + PHPStan nivel 7 + Pest 4) | OK - 220 testes, 4660 assercoes |
| `npm run test` (Vitest 5) | OK - 105 testes em 7 arquivos |
| `npm run types:check` (wayfinder + `vue-tsc --noEmit`) | OK |
| `npm run build` | OK |
| `npm run build:ssr` | OK |
| `prettier --check resources/js` | OK (16 arquivos reformatados durante o bloco) |

## 9. Smoke de SSR

Banco SQLite descartavel, `php artisan serve` com `INERTIA_SSR_ENABLED=true` e
`node bootstrap/ssr/ssr.js` no 13714. O admin nasceu pelo proprio comando
(`devtools:admin`, rodado duas vezes: a segunda responde "ja esta como admin") e
a sessao foi aberta por POST em `/pt-br/login` (302 para `/dashboard`).

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
| `/settings/security` | 302 | - | - |

Conferido no HTML do servidor: as seis secoes do painel, `Voltar ao site`, os
quatro cards da visao geral (`Gratuito`, `Vitalício`, `Sem assinatura ativa`,
`Próximo vencimento`, `Ver faturas`) e o preco vindo da tabela em `/admin/plans`
(`29,90`, `299,00`, `Preço`). O 302 de `/settings/security` e o esperado (area
sensivel pede a senha antes de abrir). Log do SSR limpo nas telas da aplicacao -
o unico erro registrado foi da sonda do proprio smoke (um POST sintetico com
componente inexistente) - e o `storage/logs/laravel.log` nao recebeu linha nova
durante o smoke.

## 10. Nao verificado daqui

- O visual fino das telas (densidade da tabela administrativa, estados de foco e
  o comportamento em telas estreitas): revisao do usuario.
- O gateway (F6), o deploy no VPS e as credenciais reais de OAuth/SMTP.
- A decisao sobre o eslint como gate (achado 4 da secao 6).

## 11. Carry-overs

- **F4**: gravar `users.appearance` pela conta (a coluna so e lida hoje);
  mensagens de validacao nos 5 idiomas; decidir se 2FA/passkeys voltam.
- **F1**: home real, registro de tools, Cmd+K, banner LGPD, GA4, sitemap, OG images.
- **F2**: `--elevated` como cor de pontuacao do JSON no DESIGN (SPEC secao 11.2).
- **F5**: consumo real da cota (o debito no livro-razao e as telas de IA do
  painel), coluna de dialetos em `output_targets` e a escolha entre AI SDK e
  cliente HTTP fino.
- **F6**: gateway (interface `PaymentGateway` + Mercado Pago), doacao, webhooks
  e a renovacao da franquia a cada periodo (hoje conceder de novo e ato manual).
- **F7**: historico de uso real, SEO final e hardening.
- **Operacao (usuario)**: banco `devtools` no MariaDB local, PHP 8.3 e usuario do
  cron no VPS, `composer self-update`, rotacao de chaves e credenciais de OAuth.

## 12. Estado do repositorio

- **Nenhum commit feito pelo assistente** (regra do `CLAUDE.md`): tudo esta na
  arvore, para o usuario commitar pelo gitpr. `git log` segue com os 2 commits
  do usuario (o ultimo em 2026-10-13 21:55).
- A arvore tem 96 arquivos novos e 87 modificados. **Atencao**: a lista de nao
  rastreados mistura este bloco com arquivos do bloco de auth que ficaram fora
  daquele commit (ex.: `app/Http/Controllers/LegalController.php`,
  `resources/js/pages/Privacy.vue`, `docs/adr/ADR-022` a `024`,
  `tests/Feature/NewsletterTest.php`). Vale conferir o `git status` inteiro
  antes de commitar, para nao deixar o bloco anterior para tras.
