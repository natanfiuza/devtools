# Relatorio: Cadastro e Login com Google e Tutorial Oficial de Integracao

- **Data**: 2026-10-19
- **Branch**: `develop_natan`
- **Tarefa**: `cadastro_login_google_tutorial`
- **Status**: Concluido com sucesso
- **Referencias**: `docs/adr/ADR-022-auth-sob-prefixo-de-idioma.md`, `docs/adr/ADR-023-login-social-e-conta-sem-senha.md`, `docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`, `docs/guides/google-auth.md`.

---

## 1. Resumo da Entrega

Implementado o refinamento do fluxo de cadastro e login com a conta Google (e GitHub), tornando a apresentacao dos botoes sociais contextual a cada tela, garantindo que retornos de falha ou indisponibilidade voltem a tela de origem (com exibicao de mensagens de status em destaque no cadastro) e disponibilizado o guia oficial completo de integracao no Google Cloud Console em `docs/guides/google-auth.md`.

---

## 2. Modificacoes Realizadas

### 2.1 Decisao de Arquitetura (ADR-036)
- **`docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`** (novo):
  - Formalizada a emenda a ADR-023.
  - Definida a passagem da origem `?from=register` para a rota de maquina `/auth/{provider}/redirect`, persistida na sessao `auth.social.from`.
  - Definido o retorno contextual com status para `register` ou `login` em caso de provedor nao configurado ou recusa de autorizacao.
  - Definidos os rotulos contextuais de botoes sociais por tela.

### 2.2 Backend e Internacionalizacao (5 Idiomas)
- **`app/Providers/FortifyServiceProvider.php`**:
  - A visualizacao `Fortify::registerView` passou a injetar `'status' => $request->session()->get('status')`, igualando-se as telas de `Login`, `ForgotPassword` e `VerifyEmail`.
- **`app/Http/Controllers/Auth/SocialAuthController.php`**:
  - `redirect(Request $request, string $provider)`: le `$request->query('from')` e grava `auth.social.from` (`'register'` ou `'login'`) na sessao.
  - `backWithStatus(string $key, string $provider)`: redireciona para a rota guardada na sessao (`session('auth.social.from') === 'register' ? 'register' : 'login'`) com a mensagem flash traduzida.
- **`lang/{locale}/auth.php`** (`pt_BR`, `pt_PT`, `en`, `es`, `fr`):
  - Adicionadas as chaves `auth.social.login_with` e `auth.social.register_with` nos 5 idiomas:
    - `pt_BR`: "Entrar com {provider}" / "Cadastrar com {provider}"
    - `pt_PT`: "Entrar com {provider}" / "Criar conta com {provider}"
    - `en`: "Sign in with {provider}" / "Sign up with {provider}"
    - `es`: "Entrar con {provider}" / "Crear cuenta con {provider}"
    - `fr`: "Se connecter avec {provider}" / "Créer un compte avec {provider}"
  - Mantida `continue_with` como fallback seguro.

### 2.3 Frontend (Vue 3 + TypeScript)
- **`resources/js/components/SocialAuthButtons.vue`**:
  - Adicionada prop `action?: 'login' | 'register'` (padrao `'login'`).
  - Link gerado com `?from=register` quando `action === 'register'`, usando a tipagem e geracao de rotas do Wayfinder (`redirect({ provider }, { query: { from: 'register' } }).url`).
  - Rotulo renderizado dinamicamente via `login_with` ou `register_with` com queda para `continue_with`.
- **`resources/js/pages/auth/Login.vue`**:
  - Parametrizado `<SocialAuthButtons action="login" />`.
- **`resources/js/pages/auth/Register.vue`**:
  - Adicionada prop `status?: string`.
  - Renderizado bloco de alerta `<p v-if="status" ... role="status" aria-live="polite">{{ status }}</p>` para exibir avisos do servidor.
  - Parametrizado `<SocialAuthButtons action="register" />`.

### 2.4 Tutorial Oficial de Integracao Google (OAuth 2.0)
- **`docs/guides/google-auth.md`** (novo, sem acentos):
  - Guia passo a passo completo estruturado similarmente a `google-analytics.md`:
    1. Principios arquiteturais (rotas de maquina, conta sem senha, vinculo seguro e retorno contextual).
    2. Passo 1: Criacao/selecao de projeto no Google Cloud Console.
    3. Passo 2: Configuracao da Tela de Consentimento OAuth (tipo Externo, dados do app, escopos basicos `email`, `profile` e `openid`, usuarios de teste e publicacao).
    4. Passo 3: Criacao do ID do cliente OAuth 2.0 (Aplicativo da Web), com origens e URIs de redirecionamento autorizadas para desenvolvimento local (`http://localhost:8000/auth/google/callback` e `http://127.0.0.1:8000/auth/google/callback`) e producao (`https://devtools.natanfiuza.dev.br/auth/google/callback`).
    5. Passo 4: Variaveis de ambiente no `.env` (`GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`) e comando de limpeza de cache de configuracao.
    6. Passo 5: Roteiro de testes de validacao (cadastro de conta nova, login de conta existente, vinculo com conta local por e-mail confirmado e definicao de senha).
    7. Passo 6: Troubleshooting detalhado (`redirect_uri_mismatch`, tela de app nao verificado, servico indisponivel por falta de chaves, recusa/cancelamento pelo visitante e rotacao de segredos sem indisponibilidade).

---

## 3. Testes Automatizados e Qualidade

1. **Testes de Backend (Pest)**:
   - `SocialLoginTest.php`: adicionados testes para redirecionamento a `register` com aviso quando o provedor nao esta configurado e para redirecionamento a `register` quando o provedor recusa a autorizacao.
   - Resultado: 14 testes passando em `SocialLoginTest.php`, 20 testes passando em `RegistrationTest.php`.
   - Suíte geral de testes do Laravel: 317 testes passando, 9.673 assercoes (`composer test` verde com Pint e PHPStan sem erros).
2. **Testes de Frontend (Vitest)**:
   - `auth-login.test.ts`: atualizado para testar os rotulos "ENTRAR COM GOOGLE" e "ENTRAR COM GITHUB".
   - `auth-register.test.ts` (novo): criado com 7 testes cobrindo renderizacao de status, textos e rotulos "CADASTRAR COM GOOGLE" e geracao de links com `?from=register`.
   - Resultado: 50 arquivos de teste passando, 693 testes aprovados (`npm run test` verde).
3. **Checagem de Tipos (TypeScript)**:
   - `npm run types:check`: Wayfinder e `vue-tsc` sem erros (0 erros).

---

## 4. Proximos Passos (Usuario)

- Criar as credenciais OAuth 2.0 no Google Cloud Console seguindo o guia em [`docs/guides/google-auth.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/guides/google-auth.md).
- Preencher as variaveis `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` no arquivo `.env` local e/ou de producao.
- Efetuar commit e pull request manual utilizando o `gitpr`.
