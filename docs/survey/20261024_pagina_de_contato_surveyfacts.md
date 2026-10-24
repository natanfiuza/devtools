# Survey — Pagina de Contato (2026-10-24)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Na pagina de doacao quando o Pix nao e configurado uma mensagem e exibida 'O Pix ainda nao esta configurado por aqui. Volte mais tarde ou fale com a gente pela pagina de contato.' mas nao existe uma pagina de contato crie. O email para onde deve ser enviado os dados de contato e devtools@natanfiuza.dev.br"
- **Tela / Modulo:** Pagina publica de contato (`/{locale}/contact`), Formulario de contato, Link em `Donate.vue`, Link no rodape em `PublicLayout.vue`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de grill com 4 decisoes tecnicas essenciais, inspecao autonoma de precedentes na base de codigo (rotas publicas sob `{locale}`, controllers publicos, layout publico, formulários Inertia, notificacoes/mailables e rate limiting).
- **Arquivos-chave lidos:** `routes/web.php`, `config/locales.php`, `config/legal.php`, `app/Http/Controllers/DonationController.php`, `resources/js/pages/Donate.vue`, `resources/js/layouts/PublicLayout.vue`, `resources/js/composables/useRoutes.ts`, `app/Support/Locales.php`, `app/Actions/Newsletter/SubscribeToNewsletter.php`, `app/Http/Controllers/SubscriptionIntentController.php`, `resources/js/lib/inertiaApp.ts`.

## 2. Decisoes das rodadas

### Rodada 1 — Definicao de Arquitetura e Contrato da Pagina de Contato
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Persistencia das mensagens (Banco de dados vs Apenas e-mail) | **Persistir em banco (`contact_messages`) e disparar e-mail assincrono via fila para `devtools@natanfiuza.dev.br`**. Garante resiliencia a falhas temporarias do mailer, trilha de auditoria e suporte futuro a consulta administrativa. |
| Q2 | Campos do formulario de contato | **Nome, E-mail, Assunto / Categoria e Mensagem**. Nome e e-mail obrigatorios com pre-preenchimento para usuarios autenticados; seletor com categorias (Duvida geral, Problema com doacao / pagamento, Sugestao ou melhoria, Reportar bug, Outro); mensagem obrigatoria (textarea ate 3000 caracteres com contador dinâmico). |
| Q3 | Integracao na pagina de Doacao e Navegacao Global | **Link contextual em `Donate.vue` + link no rodape de `PublicLayout.vue`**. Em `Donate.vue`, o texto "pagina de contato" vira link navegavel quando o Pix estiver indisponivel. No rodape global, adiciona-se o link "Contato" ao lado de Privacidade, Termos e Doar. |
| Q4 | Protecao anti-spam e tratamento de limite | **Rate limit de 5 envios/minuto por IP com mensagem amigavel e contador de segundos + campo honeypot oculto (`hp_website`)**. Se o honeypot for preenchido por bot, o envio e descartado silenciosamente com resposta de sucesso simulado. |

### Defaults assumidos (nao contestados)
- **Configuracao do e-mail de destino:** Seguir a regra do GEMINI.md (sem valores fixos em codigo). Criar `config/contact.php` com chave `email` alimentada por `env('CONTACT_EMAIL', 'devtools@natanfiuza.dev.br')`, adicionando ao `.env.example`.
- **Reply-To do e-mail:** O e-mail de notificacao enviado a `devtools@natanfiuza.dev.br` tera o `replyTo` configurado com o e-mail do remetente, facilitando a resposta direta.
- **Namespaces de i18n:** Registro do namespace `contact` em `config/locales.php` (para consumo no Vue) e criacao dos arquivos de traducao completos nos 5 idiomas (`pt_BR`, `pt_PT`, `en`, `fr`, `es`).
- **Layout:** Mapear a pagina `Contact` para o `PublicLayout` em `resources/js/lib/inertiaApp.ts`.
- **Rotas tipadas (Wayfinder):** Atualizar `useRoutes.ts` com a rota `routes.contact` e a acao `routes.form.contact`.

## 3. Relatorio de fatos levantados

### Fato 1: Rotas publicas e prefixos de idioma
No devtools, paginas publicas pertencem obrigatoriamente ao grupo `Route::prefix('{locale}')->whereIn('locale', Locales::slugs())`. O composable `useRoutes.ts` injeta o slug do idioma ativo automaticamente, permitindo chamar `routes.contact.value`.

### Fato 2: Tratamento de rate limit no Inertia
O middleware padrao do Laravel `throttle` devolve HTTP 429 bruto que, em requisicoes Inertia, pode disparar modais indesejados. O uso programatico de `RateLimiter::tooManyAttempts(...)` no FormRequest / Controller converte o estouro em um erro de validacao amigavel traduzido no idioma do visitante com a contagem de segundos restante.

### Fato 3: Mensagem de Pix indisponivel em Donate.vue
Atualmente, `Donate.vue` exibe uma mensagem estatica vinda de `donations.info.description`. Com a pagina de contato, o texto e dividido ou interpolado para que "pagina de contato" seja um componente `<Link>` apontando diretamente para `routes.contact.value`.
