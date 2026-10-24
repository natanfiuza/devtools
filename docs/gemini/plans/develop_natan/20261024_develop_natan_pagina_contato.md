# Plano de Implementacao — Pagina de Contato

Data: 2026-10-24
Branch: `develop_natan`
Status: Pronto para execucao

## 1. Visao Geral e Objetivo
Criar a pagina publica de contato (`/{locale}/contact`), integrando-a com o aviso de Pix indisponivel em `Donate.vue` e adicionando o link institucional no rodape global em `PublicLayout.vue`.
Os envios de contato serao validados, salvos na tabela `contact_messages` e despachados assincronamente por e-mail para `devtools@natanfiuza.dev.br` (configuravel via `CONTACT_EMAIL`), com protecao anti-spam via honeypot e rate limit amigavel com contagem de segundos.

## 2. Arquitetura e Decisoes
1. **Configuracao e Variaveis de Ambiente:**
   - Criar `config/contact.php` contendo `'email' => env('CONTACT_EMAIL', 'devtools@natanfiuza.dev.br')`.
   - Adicionar `CONTACT_EMAIL=devtools@natanfiuza.dev.br` ao `.env.example`.
2. **Banco de Dados (Persistencia):**
   - Migration `create_contact_messages_table`:
     - `id` (bigint auto-increment)
     - `user_id` (foreignId nulo para visitantes anonimos, cascadeOnDelete)
     - `name` (string 100)
     - `email` (string 255)
     - `subject` (string 100 — categoria da mensagem)
     - `message` (text)
     - `ip_address` (string 45 nulo)
     - `user_agent` (text nulo)
     - `created_at` e `updated_at`
   - Model `App\Models\ContactMessage`.
3. **Mailable Assincrono:**
   - Criar `App\Mail\ContactMessageReceived` implementando `ShouldQueue`.
   - Template Markdown `resources/views/mail/contact-message.blade.php`.
   - Remetente do sistema, `to: config('contact.email')`, `replyTo: $contactMessage->email`, assunto com identificador claro.
4. **Backend e Validacao:**
   - Controller `App\Http\Controllers\ContactController`:
     - `index(Request $request)`: renderiza `Contact` no Inertia passando dados do usuario caso autenticado (`name`, `email`).
     - `store(StoreContactMessageRequest $request, CreateContactMessage $action)`: valida, checa rate limit, filtra honeypot, salva via action, despacha e-mail e redireciona de volta com flash toast de sucesso.
   - Request `App\Http\Requests\StoreContactMessageRequest`:
     - Rate limit customizado (5 tentativas por 60 segundos por IP). Se estourar, lanca `ValidationException` com a mensagem em `:seconds`.
     - Validacao: `name` (required, string, max 100), `email` (required, email, max 255), `subject` (required, in: duvida, problema_doacao, sugestao, bug, outro), `message` (required, string, max 3000), `hp_website` (nullable).
   - Action `App\Actions\Contact\CreateContactMessage`:
     - Cria o registro no banco e despacha o job de e-mail `Mail::to(config('contact.email'))->queue(new ContactMessageReceived($message))`.
5. **Rotas e Wayfinder:**
   - Em `routes/web.php` (dentro de `Route::prefix('{locale}')->whereIn('locale', Locales::slugs())`):
     - `Route::get('contact', [ContactController::class, 'index'])->name('contact');`
     - `Route::post('contact', [ContactController::class, 'store'])->name('contact.store');`
   - Em `resources/js/lib/inertiaApp.ts`:
     - Mapear `'Contact'` para `PublicLayout`.
   - Em `resources/js/composables/useRoutes.ts`:
     - Adicionar `contact: ComputedRef<string>` e `form.contact: FormAction`.
6. **Frontend (Vue 3 + Tailwind + shadcn-vue):**
   - Pagina `resources/js/pages/Contact.vue`:
     - Cabecalho com titulo e subtitulo.
     - Formulario com cards estilizados, campos Nome, E-mail, Assunto (Select), Mensagem (textarea com contador ate 3000 caracteres), honeypot oculto.
     - Exibicao de erros de validacao via `InputError.vue`.
     - Banner de feedback de envio com sucesso.
   - Integracao em `resources/js/pages/Donate.vue`:
     - No bloco `!available`, o texto referencia a pagina de contato com link clicavel `<Link :href="routes.contact.value">`.
   - Integracao em `resources/js/layouts/PublicLayout.vue`:
     - Adicionar link de contato no rodape com atributo `data-test="footer-contact"`.
7. **Internacionalizacao (i18n):**
   - Registrar namespace `contact` em `config/locales.php` na chave `'namespaces'`.
   - Criar `lang/{locale}/contact.php` para os 5 idiomas (`pt_BR`, `pt_PT`, `en`, `fr`, `es`).
   - Atualizar `lang/{locale}/donations.php` para decompor a frase com link para contato.
   - Atualizar `lang/{locale}/app.php` para incluir `app.footer.contact`.
   - Atualizar `lang/{locale}/mail.php` para incluir o template do e-mail de contato recebido.

## 3. Etapas de Execucao

### Etapa 1: Configuracao e Banco de Dados
- Criar `config/contact.php` e atualizar `.env.example`.
- Criar migration `database/migrations/xxxx_xx_xx_create_contact_messages_table.php`.
- Criar Model `app/Models/ContactMessage.php`.

### Etapa 2: Mailable e Traducao de E-mail
- Criar `app/Mail/ContactMessageReceived.php`.
- Criar view Blade `resources/views/mail/contact-message.blade.php`.
- Adicionar chaves de e-mail em `lang/{locale}/mail.php`.

### Etapa 3: Backend e Validacao
- Criar `app/Http/Requests/StoreContactMessageRequest.php` com logica de rate limit amigavel e honeypot.
- Criar `app/Actions/Contact/CreateContactMessage.php`.
- Criar `app/Http/Controllers/ContactController.php`.
- Registrar rotas em `routes/web.php`.

### Etapa 4: Internacionalizacao (Frontend & Backend)
- Atualizar `config/locales.php` incluindo `'contact'`.
- Criar `lang/pt_BR/contact.php`, `lang/en/contact.php`, `lang/pt_PT/contact.php`, `lang/es/contact.php` e `lang/fr/contact.php`.
- Atualizar `donations.php` e `app.php` nos 5 idiomas.

### Etapa 5: Frontend e Integracoes
- Mapear layout em `resources/js/lib/inertiaApp.ts`.
- Atualizar `resources/js/composables/useRoutes.ts`.
- Criar `resources/js/pages/Contact.vue`.
- Atualizar `resources/js/pages/Donate.vue` para linkar a pagina de contato.
- Atualizar `resources/js/layouts/PublicLayout.vue` com o link de contato no rodape.

### Etapa 6: Testes e Verificacao de Qualidade
- Criar teste de Feature Pest: `tests/Feature/ContactTest.php` cobrindo exibicao da pagina, envio com sucesso, persistencia em banco, disparo do mailable pela fila, bloqueio silencioso por honeypot, e erro amigavel de rate limit.
- Criar teste Vitest: `resources/js/__tests__/Contact.test.ts` cobrindo renderizacao do formulario e validacoes.
- Executar suite de qualidade:
  - `composer test` (Pint, PHPStan, Pest)
  - `npm run test` (Vitest)
  - `npm run types:check` (Wayfinder e vue-tsc)
