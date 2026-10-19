# ADR-036: Retorno contextual no login social e rotulos especificos por tela

Status: Aceito
Data: 2026-10-19
Emenda: ADR-023 (login social e conta sem senha)

## Contexto

Na ADR-023, o login social com Google e GitHub foi integrado de forma centralizada atraves do Laravel Socialite, estabelecendo que contas nascem sem senha com e-mail verificado e que contas existentes sao vinculadas quando o provedor confirma o e-mail.

Contudo, dois pontos de atrito existiam na experiencia do usuario:
1. **Rotulo generico nos botoes sociais**: Tanto no formulário de login (`Login.vue`) quanto no de cadastro (`Register.vue`), o componente `SocialAuthButtons.vue` apresentava o texto neutro "Continuar com {provedor}". Na tela de cadastro, a expectativa do usuario e ver claramente a acao de registrar ("Cadastrar com o Google"), enquanto no login a acao e "Entrar com o Google".
2. **Redirecionamento rigido em falhas e indisponibilidade**: Ao clicar no botao social a partir da tela de cadastro (`/{locale}/register`), caso o provedor nao estivesse configurado no `.env` (ex.: Google sem credenciais) ou houvesse recusa no provedor, o metodo `backWithStatus()` do `SocialAuthController` redirecionava incondicionalmente para a tela de login (`route('login')`). Isso quebrava o fluxo mental do usuario que estava se cadastrando, deslocando-o para a tela de login. Alem disso, a tela de cadastro nao expunha a prop `status` para exibir a mensagem flash retornada pelo servidor.

## Decisao

1. **Rotulos contextuais por tela**:
   - `SocialAuthButtons.vue` passa a aceitar a propriedade tipada `action?: 'login' | 'register'`, com valor padrao `'login'`.
   - Na tela de login, o botao exibe `auth.social.login_with` ("Entrar com {provider}").
   - Na tela de cadastro, o botao exibe `auth.social.register_with` ("Cadastrar com {provider}" ou "Criar conta com {provider}").
   - A identidade visual permanece estritamente monocromatica com marcas em SVG (`simple-icons`), conforme o `DESIGN.md`.

2. **Preservacao da tela de origem no fluxo social**:
   - Quando o clique parte da tela de cadastro (`action="register"`), o link aponta para `/auth/{provider}/redirect?from=register`.
   - O `SocialAuthController::redirect` le o parametro `from` e persiste na sessao `auth.social.from = 'register'`. Quando ausente ou diferente de `register`, o padrao e `'login'`.
   - Caso o provedor esteja indisponivel ou a autorizacao falhe no callback, o metodo `backWithStatus()` consulta `session('auth.social.from')` e redireciona de volta para a rota de origem (`register` ou `login`) com a mensagem flash correspondente.
   - Em caso de sucesso na autorizacao, o fluxo segue normalmente para `intended(route('dashboard'))`, independentemente de ter iniciado no cadastro ou login.

3. **Exibicao de status no cadastro**:
   - O `FortifyServiceProvider` passa a fornecer a variavel `status` (`$request->session()->get('status')`) na closure de visualizacao `Fortify::registerView`, alinhando-se a `loginView`, `requestPasswordResetLinkView` e `verifyEmailView`.
   - O `Register.vue` passa a receber `status?: string` em suas props e renderiza o card de aviso com `role="status"` e `aria-live="polite"`.

## Consequencias

- A pessoa que tenta se cadastrar com o Google em ambiente sem chaves configuradas continua na tela de cadastro, visualizando a mensagem clara de que o servico ainda nao esta disponivel, sem ser empurrada para a tela de login.
- O contrato de internacionalizacao ganha as chaves contextuais `login_with` e `register_with` nos 5 idiomas suportados (`pt_BR`, `pt_PT`, `en`, `es`, `fr`), mantendo `continue_with` retrocompativel.
- A rota de maquina `/auth/{provider}/redirect` permanece sem prefixo de idioma (ADR-022), e o parametro `from` viaja como query string simples, resolvido de forma limpa pelo Wayfinder.
