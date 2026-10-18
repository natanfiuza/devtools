# Plano - Correcoes no Painel Administrativo

Branch: `develop_natan`.
Data: 2026-10-18.
Escopo: Ajustes de interface, formatacao de datas, chaves de traducao e interpolacao no painel administrativo (`/admin`).

---

## 1. Contexto e Diagnostico dos Problemas (Grill com Docs)

### 1.1. Identidade Visual do Painel Admin (Logo devtools_)
- **Problema**: O canto superior esquerdo do painel exibe apenas texto mono `devtools admin` estilizado de forma diferente do painel de usuarios.
- **Confronto com DESIGN.md e componentes existentes**:
  - O componente `AppLogo.vue` (`resources/js/components/AppLogo.vue`) encapsula a identidade padrao: icone `AppLogoIcon.vue` e o wordmark `AppWordmark.vue` com `devtools` e o cursor `_` colorido na cor `--brand`.
  - No dashboard de usuarios (`AppSidebar.vue`), a logo e renderizada via `<AppLogo />` dentro de um link para a rota do dashboard.
  - No `AdminLayout.vue`, basta importar e renderizar `<AppLogo />` dentro do `<Link :href="admin.dashboard.url()">`.

### 1.2. Erro IntlException em app/Support/Format.php:94
- **Problema**: Ao clicar no botao "Abrir" na lista de contas (`/admin/users/{user}`), ocorre a excecao:
  `IntlException app/Support/Format.php:94 datefmt_create: No such time zone: '+00:00': U_ILLEGAL_ARGUMENT_ERROR`.
- **Causa raiz**:
  - `Format::format()` instancia `new \IntlDateFormatter(self::locale(), $dateType, $timeType, $value->getTimezone()->getName())`.
  - Quando a data no Carbon possui timezone em formato de offset literal (ex.: `+00:00`, `-03:00` oriundo de ISO8601, sessao do banco de dados ou provedores sociais/pagamentos), o ICU rejeita essa string com `U_ILLEGAL_ARGUMENT_ERROR`.
  - O ICU aceita apenas identificadores IANA (ex.: `UTC`, `America/Recife`) ou offsets prefixados com `GMT` (ex.: `GMT+00:00`, `GMT-03:00`).
- **Solucao (ADR-026)**:
  - Adicionar normalizacao de timezone em `Format.php`: mapear `+00:00`, `-00:00`, `Z` para `UTC`; prefixar formatos `^[+-]\d{2}(:?\d{2})?$` com `GMT`; e aplicar fallback seguro para `UTC` caso o ICU nao reconheca o identificador.
  - Criar teste automatizado em Pest para `Format::date()` e `Format::dateTime()` com fusos em formato offset.

### 1.3. Interpolacao de Parametros no Rodape ("Total: :total", "Pagina :page de :last")
- **Problema**: No rodape das tabelas em `/admin/users` (Contas) e `/admin/subscribers` (Assinantes), os textos aparecem literais com os dois-pontos: `Total: :total` e `Página :page de :last`.
- **Causa raiz**:
  - No Laravel, parametros de traducao usam `:parametro`.
  - No frontend Vue, as strings sao consumidas pelo `vue-i18n`, que espera marcadores no formato `{parametro}` (ex.: `{total}`, `{page}`, `{last}`).
  - Em `lang/*/admin.php`, as chaves `admin.common.total` e `admin.common.page_of` foram escritas com `:total`, `:page`, `:last`. Por isso, o `vue-i18n` nao realiza a substituicao.
  - O mesmo problema ocorre em outras chaves do admin lidas no Vue, como `admin.subscriptions.deleted_tab.days_remaining` (`:days`), `admin.user.since` (`:date`), `admin.user.email_verified_at` (`:date`), `admin.user.quota.of` (`:used`, `:limit`), `admin.user.quota.window` (`:start`, `:end`) e em `lang/*/account.php` (`page_of`).
- **Solucao**:
  - Corrigir todas as chaves lidas pelo Vue em `lang/*/admin.php` e `lang/*/account.php` nos 5 idiomas (`en`, `es`, `fr`, `pt_BR`, `pt_PT`) para a sintaxe `{parametro}`.

### 1.4. Respostas as Duvidas sobre a Aba Pagamentos (Assinaturas > Pagamentos)
- **Select do filtro com "admin.payments.filter_all"**:
  - O arquivo `resources/js/pages/admin/Subscriptions.vue` referencia `t('admin.payments.filter_all')`. Essa chave nao existe em `admin.payments`.
  - A chave padrao do sistema (presente em `Payments.vue` e nos 5 idiomas de `lang/*/admin.php`) e `admin.payments.status_all`, cujo valor e **"Todos"** (em pt-BR/pt-PT/es), **"All"** (em en) e **"Tous"** (em fr).
- **Botao acima da listagem com label "admin.payments.filter_all": qual o valor que deveria ser exibido?**:
  - O `<SelectTrigger>` da biblioteca Radix/Shadcn renderiza no HTML como um `<button>`. Quando o valor selecionado e "all", ele exibia `admin.payments.filter_all`. Com a correcao para `status_all`, ele exibira **"Todos"** (no idioma pt-BR).
  - Ja o botao de acao ao lado dele (que abre o formulario de novo pagamento) usava `admin.payments.register_cta`, que tambem nao existe. O valor que deve ser exibido e **"Registrar pagamento"** (chave `admin.payments.register`), e quando aberto alterna para **"Cancelar"** (`admin.payments.cancel`).
- **Campo de e-mail no formulario com label "admin.payments.account_email": o que deveria ser exibido?**:
  - O componente usava `t('admin.payments.account_email')`.
  - A chave oficial nos 5 idiomas em `lang/*/admin.php` e `admin.payments.email`, cujo valor que deve ser exibido e **"E-mail da conta"** (em pt-BR e pt-PT), **"Account email"** (em en), **"Correo de la cuenta"** (em es) e **"E-mail du compte"** (em fr).

---

## 2. Arquivos Envolvidos e Modificacoes

### 2.1. Frontend Layout e Telas
1. `resources/js/layouts/admin/AdminLayout.vue`:
   - Importar `AppLogo` de `@/components/AppLogo.vue`.
   - Substituir o link do cabecalho superior esquerdo para usar `<AppLogo />` com a logo e wordmark `devtools_`.
2. `resources/js/pages/admin/Subscriptions.vue`:
   - Linha 492: trocar `admin.intents.filter_all` por `admin.intents.status_all`.
   - Linha 630: trocar `admin.payments.filter_all` por `admin.payments.status_all`.
   - Linha 662: trocar `admin.payments.register_cta` por `admin.payments.register`.
   - Linha 675: trocar `admin.payments.account_email` por `admin.payments.email`.

### 2.2. Backend Formatacao de Datas
1. `app/Support/Format.php`:
   - Adicionar normalizacao de timezone no metodo `timezone(CarbonInterface $value): string`.
   - Mapear fusos `'Z'`, `'+00:00'`, `'-00:00'`, `'+0000'` para `'UTC'`.
   - Prefixar regex `/^[+-]\d{2}(:?\d{2})?$/` com `'GMT'`.
   - Fazer fallback seguro para `'UTC'` caso `IntlTimeZone::createTimeZone()` retorne `Etc/Unknown`.
2. `tests/Feature/FormatTest.php`:
   - Criar testes para `Format::date()` e `Format::dateTime()` com fusos em formato offset (`+00:00`, `-03:00`, `UTC`, `America/Recife`, nulo).

### 2.3. Arquivos de Traducao (5 idiomas: en, es, fr, pt_BR, pt_PT)
1. `lang/{locale}/admin.php`:
   - `admin.common.total`: `'Total: {total}'` (fr: `'Total : {total}'`).
   - `admin.common.page_of`: `'{page}'` e `'{last}'`.
   - `admin.subscriptions.deleted_tab.days_remaining`: `'{days}'`.
   - `admin.user.email_verified_at`: `'{date}'`.
   - `admin.user.since`: `'{date}'`.
   - `admin.user.subscription.from_to`: `'{start}'` e `'{end}'`.
   - `admin.user.subscription.from`: `'{start}'`.
   - `admin.user.quota.of`: `'{used}'` e `'{limit}'`.
   - `admin.user.quota.remaining`: `'{remaining}'`.
   - `admin.user.quota.window`: `'{start}'` e `'{end}'`.
   - `admin.user.quota_form.help`: `'{unit}'`.
   - `admin.plans.slug_help`: `'{slug}'`.
   - `admin.plans.amount_help`: `'{currency}'`.
   - `admin.payments.amount_help`: `'{currency}'`.
2. `lang/{locale}/account.php`:
   - `account.billing.page_of`: `'{page}'` e `'{last}'`.

---

## 3. Plano de Testes e Validacao

1. **Testes Unitarios e de Integracao Backend**:
   - Rodar `vendor/bin/pest tests/Feature/FormatTest.php` e suite completa de testes Pest (`composer test`).
   - Validar estilizacao e analise estatica (`composer test` executa Pint e PHPStan).

2. **Testes Unitarios e Tipagem Frontend**:
   - Adicionar teste em `resources/js/__tests__/admin-panel.test.ts` verificando que `AdminLayout` renderiza a logo com `devtools_`.
   - Rodar `npm run test` (Vitest) para garantir que todos os testes passem.
   - Rodar `npm run types:check` (rotas e `vue-tsc`).

3. **Verificacao Manual / Navegacao**:
   - Abrir painel admin em `/admin`.
   - Validar presenca da logo `devtools_` com underscore na cor da marca no canto superior esquerdo.
   - Clicar na aba "Contas" e abrir detalhes de uma conta (verificar que nao lanca IntlException e que os textos de data e cota sao exibidos corretamente).
   - Verificar rodape da lista de Contas e de Assinantes (validar que "Total: X" e "Pagina X de Y" exibem os numeros substituidos).
   - Acessar "Assinaturas" > sub-aba "Pagamentos":
     - Select de filtro exibe "Todos" por padrao;
     - Botao acima da lista exibe "Registrar pagamento";
     - Ao clicar, o formulario abre e o campo de e-mail possui o rotulo "E-mail da conta".
