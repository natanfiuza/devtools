# Relatorio de Tarefa — Pagina de Contato

Data: 2026-10-24
Branch: `develop_natan`
Tarefa: `pagina_contato`

## 1. Descricao da Tarefa
Criar a pagina publica de contato (`/{locale}/contact`), integrando-a com o aviso de Pix nao configurado na pagina de doacao (`Donate.vue`) e adicionando o link correspondente no rodape global (`PublicLayout.vue`).
As mensagens enviadas pelo formulario sao validadas, protegidas contra spam e persistidas em banco de dados (`contact_messages`), com despacho assincrono de e-mail via fila para `devtools@natanfiuza.dev.br` (configuravel via `CONTACT_EMAIL`).

## 2. Modificacoes e Arquivos Criados

### Configuracao e Banco de Dados
- `config/contact.php`: define `CONTACT_EMAIL` com valor padrao `devtools@natanfiuza.dev.br`.
- `.env.example`: adicionada variavel `CONTACT_EMAIL`.
- `database/migrations/2026_10_24_000000_create_contact_messages_table.php`: tabela `contact_messages` com colunas `user_id` (nulo), `name`, `email`, `subject`, `message`, `ip_address`, `user_agent` e `timestamps`.
- `app/Models/ContactMessage.php`: model Eloquent com `#[Fillable]` e relacionamento com `User`.

### Notificacao e E-mail
- `app/Mail/ContactMessageReceived.php`: mailable assincrono com `ShouldQueue`, definindo remetente, assunto com prefixo do app, `replyTo` para o remetente da mensagem e template markdown.
- `resources/views/mail/contact-message.blade.php`: view markdown utilizando `@include('notifications::email')`.
- `lang/{locale}/mail.php`: chaves do e-mail de contato nos 5 idiomas (`pt_BR`, `en`, `pt_PT`, `es`, `fr`).

### Backend e Validacao
- `app/Http/Requests/StoreContactMessageRequest.php`: validacao de campos, deteccao de honeypot (`hp_website`) e rate limiting inteligente (5 tentativas por minuto por IP) exibindo erro amigavel com contagem de segundos restantes.
- `app/Actions/Contact/CreateContactMessage.php`: action responsavel por persistir a mensagem e agendar o e-mail na fila.
- `app/Http/Controllers/ContactController.php`: acoes `index` (renderiza `Contact` com dados pre-preenchidos caso usuario autenticado) e `store` (tratamento de honeypot silencioso, despacho e toast flash).
- `routes/web.php`: rotas publicas `contact` e `contact.store` dentro do grupo `{locale}`.
- `app/Support/Seo.php`: metadados SEO (titulo e descricao) mapeados para `contact`.
- `app/Support/Sitemap.php`: rota `contact` adicionada a lista de paginas indexaveis do `sitemap.xml`.

### Internacionalizacao (i18n)
- `config/locales.php`: namespace `contact` adicionado para envio ao frontend.
- `lang/pt_BR/contact.php`, `lang/en/contact.php`, `lang/pt_PT/contact.php`, `lang/es/contact.php`, `lang/fr/contact.php`: traducoes completas do formulario, campos, placeholders, categorias e mensagens de feedback.
- `lang/{locale}/app.php`: chave `app.footer.contact` adicionada nos 5 idiomas.
- `lang/{locale}/donations.php`: texto do aviso de indisponibilidade refatorado em `description_lead` e `contact_link` nos 5 idiomas.

### Frontend
- `resources/js/lib/inertiaApp.ts`: mapeamento de `Contact` para o `PublicLayout`.
- `resources/js/composables/useRoutes.ts`: adicao de `routes.contact` e `routes.form.contact`.
- `resources/js/pages/Contact.vue`: tela completa de contato com selecao de categoria, textarea com contador de caracteres (max 3000), honeypot oculto, feedback de envio e acessibilidade.
- `resources/js/pages/Donate.vue`: link clicavel para a pagina de contato no card de Pix indisponivel.
- `resources/js/layouts/PublicLayout.vue`: link "Contato" adicionado no rodape ao lado de Privacidade, Termos e Doar.

### Testes
- `tests/Feature/ContactTest.php`: testes Feature com Pest cobrindo:
  - Renderizacao nos idiomas suportados.
  - Pre-preenchimento para usuarios autenticados.
  - Envio com sucesso, persistencia em banco e mailable na fila com replyTo.
  - Validacao de campos obrigatorios.
  - Descarte silencioso via honeypot para bots.
  - Rate limiting com erro inline apos 5 tentativas.
- `resources/js/__tests__/contact.test.ts`: testes Vitest para renderizacao de campos, pre-preenchimento, submit e exibicao do banner de sucesso.

## 3. Verificacoes e Qualidade
- `vendor/bin/pest tests/Feature/ContactTest.php`: 6 testes passaram (73 assercoes).
- `vendor/bin/pint --test`: aprovado sem violacoes de estilo.
- `vendor/bin/phpstan analyse`: 0 erros encontrados.
- `npm run types:check`: Wayfinder e vue-tsc executados com sucesso sem erros de tipagem.
- `npm run test -- resources/js/__tests__/contact.test.ts`: 4 testes passaram com sucesso.
- `npm run test -- resources/js/__tests__/consent-banner.test.ts`: 11 testes passaram com sucesso.
