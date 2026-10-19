# Plano: Cadastro e Login com Google e Tutorial de Integracao

- **Data**: 2026-10-19
- **Branch**: `develop_natan`
- **Contexto**: Implementacao e refinamento do cadastro e login com conta Google no Login e Cadastro, alem da criacao do tutorial oficial de integracao com o Google OAuth 2.0.
- **Referencias**: `docs/SPEC.md`, `docs/adr/ADR-022-auth-sob-prefixo-de-idioma.md`, `docs/adr/ADR-023-login-social-e-conta-sem-senha.md`, `docs/design/DESIGN.md`, `docs/guides/google-analytics.md`.

---

## 1. Diagnostico e Alinhamento

### Estado Atual no Repositorio
1. **Infraestrutura Socialite pronta**: O backend (`SocialAuthController`, `SocialProviders`, rotas `/auth/{provider}/redirect` e `/auth/{provider}/callback`) ja esta integrado ao Laravel Socialite para Google e GitHub.
2. **Conta sem senha**: A conta criada via Google nasce sem senha com e-mail verificado automaticamente (`ADR-023`). Contas com mesmo e-mail verificado sao vinculadas com seguranca.
3. **Botoes presentes mas genericos**: O componente `SocialAuthButtons.vue` ja esta presente em `Login.vue` e `Register.vue`, porem usa rotulo neutro fixo ("Continuar com {provider}") e URL sem informacao da tela de origem.
4. **Redirecionamento rigido em falhas**: Quando o Google nao esta configurado no `.env` ou a autenticacao falha, o backend volta sempre para `route('login')`. O visitante que clicou em `Register.vue` era levado para `Login.vue`. Alem disso, `Register.vue` nao possui a prop `status` para exibir alertas do servidor.
5. **Tutorial ausente**: Nao ha guia documentando o passo a passo no Google Cloud Console, criacao de credenciais OAuth 2.0 Web, URLs de origem/callback e configuracao do `.env`.

### Decisoes Alinhadas no Grill
1. **Manter Google e GitHub com textos contextuais**:
   - Tela de Login: "Entrar com Google" / "Entrar com GitHub".
   - Tela de Cadastro: "Cadastrar com Google" / "Cadastrar com GitHub" (ou equivalente traduzido).
   - Manter identidade visual monocromatica exigida pelo `DESIGN.md`.
2. **Retorno contextual em caso de erro ou provedor indisponivel**:
   - Origem no cadastro (`from=register`): volta para a tela de cadastro e exibe a mensagem de status no card.
   - Origem no login (`from=login` ou omisso): volta para a tela de login exibindo a mensagem.
3. **Guia completo de integracao**:
   - Criar `docs/guides/google-auth.md` em portugues sem acentos cobrindo Google Cloud Console, Tela de Consentimento, credenciais Web, URLs (local e producao), `.env` e resolucao de problemas (`redirect_uri_mismatch`, app nao verificado, etc.).

---

## 2. Etapas de Execucao

### Etapa 1: Arquitetura e Decisao (ADR)
- Criar `docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`:
  - Formalizar a emenda a ADR-023: passagem de parametro de origem `?from=register` para `/auth/{provider}/redirect` e persistencia na sessao `auth.social.from`.
  - Redirecionamento em `backWithStatus()` para a rota de origem (`register` ou `login`).
  - Suporte a mensagens flash `status` na tela de cadastro (`Register.vue`).
  - Textos contextuais dos botoes sociais por tela.
- Atualizar `docs/glossario.md` se necessario.

### Etapa 2: Backend e Internacionalizacao
1. **`app/Providers/FortifyServiceProvider.php`**:
   - Ajustar `registerView` para injetar `'status' => $request->session()->get('status')` (padronizado com as demais telas de autenticacao).
2. **`app/Http/Controllers/Auth/SocialAuthController.php`**:
   - No metodo `redirect()`: ler `$request->query('from')` e salvar na sessao `auth.social.from` (`register` ou `login`).
   - No metodo `backWithStatus()`: redirecionar para a rota armazenada na sessao (`session('auth.social.from') === 'register' ? 'register' : 'login'`).
3. **Traducoes em `lang/{locale}/auth.php`** (5 idiomas: `pt_BR`, `pt_PT`, `en`, `es`, `fr`):
   - Adicionar chaves contextuais sob `auth.social`:
     - `pt_BR`:
       - `'login_with' => 'Entrar com {provider}'`
       - `'register_with' => 'Cadastrar com {provider}'`
     - `pt_PT`:
       - `'login_with' => 'Entrar com {provider}'`
       - `'register_with' => 'Criar conta com {provider}'`
     - `en`:
       - `'login_with' => 'Sign in with {provider}'`
       - `'register_with' => 'Sign up with {provider}'`
     - `es`:
       - `'login_with' => 'Entrar con {provider}'`
       - `'register_with' => 'Crear cuenta con {provider}'`
     - `fr`:
       - `'login_with' => 'Se connecter avec {provider}'`
       - `'register_with' => 'Créer un compte avec {provider}'`
   - Preservar `'continue_with'` existente como fallback.

### Etapa 3: Frontend (Vue 3 + TypeScript)
1. **`resources/js/components/SocialAuthButtons.vue`**:
   - Adicionar prop tipada `action?: 'login' | 'register'` (padrao `'login'`).
   - Gerar a URL do link com `?from=register` quando `action === 'register'` utilizando a API do Wayfinder:
     `redirect({ provider }, { query: { from: 'register' } }).url`.
   - Selecionar o texto do botao via `t(props.action === 'register' ? 'auth.social.register_with' : 'auth.social.login_with', { provider: ... })`.
2. **`resources/js/pages/auth/Login.vue`**:
   - Definir `<SocialAuthButtons action="login" />`.
3. **`resources/js/pages/auth/Register.vue`**:
   - Adicionar `status?: string` em `defineProps`.
   - Renderizar paragrafo `<p v-if="status" class="mb-5 rounded-md border border-brand/40 bg-brand/5 px-3 py-2 text-sm" role="status" aria-live="polite">{{ status }}</p>`.
   - Definir `<SocialAuthButtons action="register" />`.

### Etapa 4: Tutorial Oficial de Integracao Google
- Criar `docs/guides/google-auth.md` (portugues sem acentos):
  1. **Introducao e Principios**: Explicar a arquitetura sem senha, seguranca na confirmacao de e-mail e que o codigo ja esta pronto, bastando as credenciais no `.env`.
  2. **Passo 1: Criar Projeto no Google Cloud Console** (<https://console.cloud.google.com>).
  3. **Passo 2: Configurar a Tela de Consentimento OAuth**:
     - Tipo de usuario: Externo.
     - Dados do app: Nome, e-mail de suporte, dominio do site, links de politica de privacidade e termos.
     - Escopos minimos: `.../auth/userinfo.email`, `.../auth/userinfo.profile`, `openid`.
     - Usuarios de teste: inclusao dos e-mails autorizados enquanto o app estiver em status "Em teste".
     - Publicacao: transicao para "Em producao".
  4. **Passo 3: Criar Credencial OAuth 2.0 (Aplicativo da Web)**:
     - Origens JavaScript autorizadas:
       - Local: `http://localhost:8000`, `http://127.0.0.1:8000`
       - Producao: `https://devtools.natanfiuza.dev.br`
     - URIs de redirecionamento autorizados:
       - Local: `http://localhost:8000/auth/google/callback`, `http://127.0.0.1:8000/auth/google/callback`
       - Producao: `https://devtools.natanfiuza.dev.br/auth/google/callback`
     - Obter Client ID e Client Secret.
  5. **Passo 4: Configurar no Servidor / Ambiente Local**:
     - Preenchimento no `.env`: `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`.
     - Comando `php artisan optimize:clear && php artisan optimize` (ou `config:clear`).
  6. **Passo 5: Como Testar e Validar os Fluxos**:
     - Cadastro e Login com conta nova (nascimento sem senha).
     - Vinculo com conta existente com e-mail confirmado.
     - Definicao de primeira senha em `/settings/security`.
  7. **Passo 6: Troubleshooting / Problemas Comuns**:
     - `redirect_uri_mismatch`: verificacao de protocolo, porta e path.
     - "App nao verificado": como prosseguir no modo teste ou solicitar verificacao.
     - `access_denied` / cancelamento pelo visitante.
     - Rotacao de chaves.

### Etapa 5: Testes Automatizados e Verificacao Completa
1. **Backend (Pest)**:
   - Atualizar `tests/Feature/Auth/SocialLoginTest.php`:
     - Testar que `/auth/{provider}/redirect?from=register` sem credenciais redireciona de volta para `/register` com a mensagem flash `status`.
     - Testar que retorno com erro no callback redireciona para `/register` quando originado do cadastro.
   - Executar `php artisan test tests/Feature/Auth/SocialLoginTest.php`.
   - Executar `php artisan test tests/Feature/Auth/RegistrationTest.php`.
2. **Frontend (Vitest)**:
   - Atualizar `resources/js/__tests__/auth-login.test.ts` para testar o rotulo "Entrar com Google".
   - Criar `resources/js/__tests__/auth-register.test.ts` para testar `Register.vue`:
     - Exibicao de mensagem `status`.
     - Rotulos dos botoes com "Cadastrar com Google" e "Cadastrar com GitHub".
     - Links apontando para `/auth/{provider}/redirect?from=register`.
   - Executar `npm run test`.
   - Executar `npm run types:check`.
   - Executar `composer test`.
