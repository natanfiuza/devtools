# Levantamento - Navegacao, Cards, JSON Viewer, Pagina de Planos e Intencao de Assinatura

Data: 2026-10-17
Branch: develop_natan
Pedido original:
1. Remover da barra superior o link "Guia de estilo", mantendo a pagina para referencia visual.
2. Tornar os cartoes de ferramentas clicaveis em toda a area do card (nao apenas no link do titulo).
3. Corrigir o bug do JSON Viewer onde digitar ou colar texto na entrada faz sumir todo o conteudo do ToolShell, restando apenas titulo e instrucoes.
4. Tornar a secao de "Planos" uma pagina separada.
5. Alterar os precos dos planos Pro para mensal R$ 8,90 e anual R$ 89,00.
6. Criar um formulario de intencao de assinatura para usuarios cadastrados (redirecionando visitantes nao autenticados para o cadastro e depois de volta ao formulario), com escolha de plano mensal/anual, direito de reservar `@nomedeusuario`, campos de contato/cobranca e destaque das vantagens Pro.

Plano: `docs/plans/develop_natan/20261017_planos_assinatura_jsonviewer_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261017_develop_natan_planos_assinatura_jsonviewer.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

Branch `develop_natan`. Stack Laravel 13 + Inertia 3 (SSR) + Vue 3 + TypeScript estrito, i18n em 5 idiomas (`pt_BR`, `pt_PT`, `en`, `es`, `fr`), Pest 4 + Vitest 5. Fases 1, 2 e 3 aceitas, 32 ADRs registradas ate o momento.

## 2. Fatos levantados por item

### 2.1. Link "Guia de estilo" na barra superior
- Em `resources/js/layouts/PublicLayout.vue`, o link `routes.styleguide.value` aparece na navegacao desktop (linhas 92-97) e no menu mobile `Sheet` (linhas 178-187).
- A rota `Route::inertia('styleguide', 'Styleguide')->name('styleguide')` em `routes/web.php` (linha 31) e a pagina `resources/js/pages/Styleguide.vue` existem para inspecao visual dos tokens (ADR-020) e ja estao fora do `Sitemap::PAGES`.
- Remover apenas os links de `PublicLayout.vue` mantem a rota `/{locale}/styleguide` acessivel diretamente sem expor item de desenvolvimento na navegacao publica.

### 2.2. Cartoes de ferramentas clicaveis por inteiro
- Em `resources/js/components/ToolCard.vue`, o `<Link v-if="href">` envolve apenas o texto dentro de `<h3>` (linhas 45-53).
- Ferramentas com `status: 'available'` recebem `href` em `Home.vue`; ferramentas `status: 'soon'` recebem `href: null` e `opacity-60`.
- Aplicar o padrao stretched-link (`relative` + hover state no `<li data-test="tool-card-...">` quando `href` existe, e `after:absolute after:inset-0 after:rounded-xl` no `<Link>`) torna todo o cartao clicavel mantendo a semantica de lista (`<li>` -> `<h3><Link>`) sem aninhar blocos inteiros dentro de `<a>` e sem afetar os cards "Em breve".

### 2.3. Causa raiz do sumico do JSON Viewer ao digitar na entrada
- Em `resources/js/pages/tools/Show.vue`, o cabecalho (`<h1>`, descricao e introducao) fica fora de `<ClientOnly>`, enquanto `<component :is="toolComponent" />` (`ToolShell.vue` + editor + arvore) fica dentro de `<ClientOnly>`.
- Ao digitar ou colar qualquer conteudo no editor de entrada do JSON Viewer (`resources/js/tools/json-viewer/Tool.vue`), apos os 200ms de debounce, `outcome.value` e atualizado e a computed `statsMessage` (linhas 352-369) chama `formatBytes(current.stats.bytes, locale.value)`.
- Em `resources/js/lib/bytes.ts` (linha 36), `formatBytes` chama `new Intl.NumberFormat(locale, { style: 'unit', ... })` passando `locale` diretamente.
- No runtime real da aplicacao (`HandleInertiaRequests.php` + `resources/js/i18n/index.ts`), `locale.value` do `vue-i18n` usa underscore (`pt_BR`, `pt_PT`) para bater com os diretorios `lang/pt_BR` e `lang/pt_PT` do Laravel.
- A API `Intl.NumberFormat` do navegador exige tags BCP 47 com hifen (`pt-BR`, `pt-PT`) e lanca `RangeError: Incorrect locale information provided` quando recebe `'pt_BR'` ou `'pt_PT'`.
- Nos testes unitarios (`json-viewer.test.ts`), o `createI18n` de teste foi instanciado com `locale: 'pt-br'` (com hifen), mascarando o erro! Ja em `jwt-decoder/Tool.vue` (linha 127) e `timestamp-uuid/Tool.vue` (linha 80), quem escreveu `Intl.DateTimeFormat` fez `locale.value.replace('_', '-')` localmente, mas `formatBytes` em `resources/js/lib/bytes.ts` nao normalizava `locale.replace('_', '-')`.
- Quando `formatBytes` lanca `RangeError` durante o render do `Tool.vue`, o Vue desmonta toda a subarvore dentro de `<ClientOnly>`, deixando visivel na tela apenas o titulo e as instrucoes de `Show.vue`.
- Alem do JSON Viewer, qualquer outra tool que chama `formatBytes(..., locale.value)` em `pt_BR` ou `pt_PT` (como `base64-url-hash`, `jwt-decoder`, `timestamp-uuid`, `regex-tester`) sofria do mesmo risco ao exibir mensagens de tamanho/limite. Normalizar `locale.replace('_', '-')` dentro de `formatBytes` em `resources/js/lib/bytes.ts` corrige a causa raiz em todas as tools de uma vez.

### 2.4. Pagina separada de Planos (`/{locale}/pricing`) e novos precos (R$ 8,90 e R$ 89,00)
- Hoje `HomeController.php` carrega `plans` e `contactEmail` para `Home.vue`, que renderiza `PricingSection.vue` na ancora `#precos`.
- Decisao do usuario (Q1): remover completamente a secao de planos da `Home.vue` e criar a pagina publica dedicada `/{locale}/pricing` (`PricingController` + `resources/js/pages/Pricing.vue`), atualizando os links da barra superior (`PublicLayout.vue`) e do sitemap (`Sitemap::PAGES`).
- Os precos vivem na tabela `plans` (ADR-012): `pro-monthly` passa de `1900` (R$ 19,00) para `890` (R$ 8,90) e `pro-yearly` passa de `19000` (R$ 190,00) para `8900` (R$ 89,00) por migration retrocompativel.

### 2.5. Formulario de intencao de assinatura, reserva de `@nomedeusuario` e painel Admin
- Hoje `PricingSection.vue` usa `<a :href="'mailto:' + contactEmail">` nos planos pagos.
- O novo fluxo substitui o `mailto:` por um formulario autenticado de intencao de assinatura (`/subscribe?plan=pro-monthly` ou `/subscribe?plan=pro-yearly`):
  - **Visitante nao cadastrado**: ao clicar em assinar um plano Pro na pagina de Planos, o controlador de assinatura guarda a URL pretendida na sessao (`redirect()->setIntendedUrl(...)`) e redireciona para a tela de cadastro (`/{locale}/register`). Apos concluir o cadastro (ou login por senha/social), `redirect()->intended(...)` leva o usuario diretamente ao formulario de assinatura com o plano pre-selecionado.
  - **Rota autenticada sem exigir verificacao previa do e-mail no primeiro instante**: usa middleware `['auth', 'active']` (igual a `settings/profile`) para que quem acabou de criar a conta no cadastro caia direto no formulario sem ficar preso na barreira de verificacao de e-mail antes de enviar sua intencao.
  - **Campos do formulario** (decisoes Q3, Q4 e A1):
    1. Escolha do plano (`pro-monthly` R$ 8,90/mes ou `pro-yearly` R$ 89,00/ano, com destaque de 2 meses gratis no anual).
    2. `@nomedeusuario` exclusivo (`username`, formato `^[a-z0-9_]{3,30}$`, unico em `users.username`), salvo imediatamente na conta do usuario e tambem editavel em `/settings/profile` (A2).
    3. Telefone / WhatsApp (`phone`).
    4. CPF / CNPJ (`document`).
    5. Forma de pagamento preferida (`payment_method`: `pix`, `credit_card`, `boleto`).
    6. Observacoes opcionais (`notes`).
  - **Vantagens Pro destacadas na pagina de planos e no formulario** (decisao Q4):
    1. Reserva exclusiva de `@nomedeusuario` na plataforma (para futuros links compartilhados e perfil).
    2. Historico de consultas salvo no AI SQL Builder (retencao de 90 dias).
    3. Suporte prioritario e acesso antecipado a novas ferramentas e alvos de saida de IA.
    4. Desconto de 2 meses gratis no plano anual (R$ 89/ano vs R$ 106,80).
    5. Cota mensal de 2.000.000 de tokens e limites ampliados (schema de 64 KB e prompt de 4.000 caracteres).
  - **Persistencia, notificacao e Admin** (decisoes Q2 e A3):
    - Nova tabela `subscription_intents` (`user_id`, `plan_id`, `username`, `phone`, `document`, `payment_method`, `notes`, `status` [`pending`, `fulfilled`, `canceled`], `handled_by`, `handled_at`, timestamps).
    - Notificacao por e-mail enviada para `Legal::contactEmail()` (se configurado) quando uma nova intencao e registrada.
    - Nova secao no painel Admin (`/admin/intents`) com filtro por status e acoes para marcar como atendido (`fulfilled`) ou cancelado (`canceled`), registrando auditoria em `admin_actions`.
    - Integracao com LGPD (ADR-028): `ExportUserData` passa a incluir `username` e `subscription_intents`; `AnonymizeUser` limpa `username = null` e remove as `subscription_intents` da conta anonimizada (pois contem telefone e CPF/CNPJ).
