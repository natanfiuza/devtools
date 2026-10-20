# Plano: Cadastro e Login com GitHub e Tutorial de Integracao

- **Data**: 2026-10-19
- **Branch**: `develop_natan`
- **Contexto**: Implementacao e validacao do cadastro e login com a conta GitHub nas telas de Login e Cadastro, acompanhado da criacao do tutorial oficial de integracao com o GitHub OAuth Apps em `docs/guides/github-auth.md`.
- **Referencias**: `docs/SPEC.md`, `docs/adr/ADR-022-auth-sob-prefixo-de-idioma.md`, `docs/adr/ADR-023-login-social-e-conta-sem-senha.md`, `docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`, `docs/guides/google-auth.md`, `docs/RUNBOOK.md`.

---

## 1. Diagnostico e Levantamento do Codigo Existente

A analise minuciosa do repositorio revelou que a base tecnica para o GitHub OAuth ja foi previamente implementada junto com a arquitetura social unificada (ADR-023 e ADR-036):

1. **Frontend (`resources/js/components/SocialAuthButtons.vue`)**:
   - Ja declara `providers: Provider[] = ['google', 'github']`.
   - Ja renderiza o icone SVG monocromatico oficial do GitHub.
   - Aplica rotulos contextuais: "Entrar com GitHub" em `Login.vue` (`action="login"`) e "Cadastrar com GitHub" em `Register.vue` (`action="register"`).
   - Aponta para rotas de maquina com parametro de contexto: `/auth/github/redirect` (login) e `/auth/github/redirect?from=register` (cadastro).
2. **Backend (`app/Http/Controllers/Auth/SocialAuthController.php` e `App\Support\SocialProviders`)**:
   - Rotas `/auth/{provider}/redirect` e `/auth/{provider}/callback` com limitador de taxa (`throttle:10,1`).
   - `SocialProviders::isConfigured('github')` valida presenca de `GITHUB_CLIENT_ID` e `GITHUB_CLIENT_SECRET`.
   - `SocialProviders::emailIsVerified('github', $socialUser)` checa e-mail retornado. O driver do Laravel Socialite solicita o escopo `user:email` por padrao e consulta a API do GitHub buscando o e-mail primario e verificado.
   - Criacao de conta sem senha (`users.password = null`, `email_verified_at = now()`) e vinculo com `social_accounts`.
   - Redirecionamento contextual em caso de erro/indisponibilidade: retorna a tela de origem (`register` ou `login`) com mensagem de status no idioma do usuario.
3. **Internacionalizacao (i18n)**:
   - Chaves `auth.social.providers.github`, `auth.social.login_with` e `auth.social.register_with` ja existem e estao traduzidas nos 5 idiomas suportados (`pt_BR`, `pt_PT`, `en`, `es`, `fr`).
4. **Configuracao e Variaveis**:
   - `config/services.php` ja mapeia `github` para `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` e `GITHUB_REDIRECT_URI` (padrao `/auth/github/callback`).
   - `.env.example` ja contem as variaveis comentadas `GITHUB_CLIENT_ID` e `GITHUB_CLIENT_SECRET`.
5. **Testes Unitarios e Funcionais**:
   - Vitest: `auth-login.test.ts` e `auth-register.test.ts` testam os botoes do GitHub.
   - Pest: `SocialLoginTest.php` cobre 14 cenarios incluindo GitHub (redirecionamento, credenciais ausentes, e-mail nao informado, autorizacao negada, criacao de conta e idioma negociado).
6. **Lacuna Identificada**:
   - Ausencia do guia completo `docs/guides/github-auth.md` documentando o passo a passo de criacao do OAuth App no GitHub Developer Settings, diferencas de callback entre local e producao, escopos, configuracao do `.env` e resolucao de problemas (troubleshooting).

---

## 2. Peculiaridades Criticas do GitHub OAuth vs Google Cloud

Ao documentar a integracao com o GitHub, as seguintes diferencas em relacao ao Google Cloud Console devem ser destacadas no guia:

1. **Callback URL Unica por OAuth App**:
   - Ao contrario do Google Cloud Console (que permite cadastrar multiplas origens e URIs de redirecionamento no mesmo Client ID), o GitHub OAuth App permite **apenas uma Authorization callback URL** por aplicativo.
   - Portanto, a boa pratica recomendada e criar dois apps no GitHub:
     - `DevTools Local` (callback: `http://localhost:8000/auth/github/callback`)
     - `DevTools Producao` (callback: `https://devtools.natanfiuza.dev.br/auth/github/callback`)
2. **Escopo `user:email` e E-mail Privado**:
   - O Laravel Socialite solicita automaticamente o escopo `user:email` para consultar `https://api.github.com/user/emails`.
   - Caso o usuario utilize a protecao de privacidade de e-mail do GitHub ("Keep my email addresses private"), a API retorna o endereco mascarado `@users.noreply.github.com` como primario e verificado. O sistema aceita normalmente como e-mail valido.
3. **Nome de Usuario vs Nome de Exibicao**:
   - Nem todo usuario do GitHub preenche o nome publico no perfil (`name`). O `SocialAuthController` trata isso fazendo fallback para o `nickname` (username do GitHub) e, em ultimo caso, para o e-mail.

---

## 3. Plano de Acao

### Etapa 1: Validacao da Fiacão Existente no Codigo
- Executar suite completa de testes frontend (`npm run test`) e backend (`composer test`) para garantir que toda a logica de GitHub login/cadastro permanece 100% verde.
- Verificar se ha necessidade de qualquer ajuste de interface ou configuracao adicional.

### Etapa 2: Criacao do Guia Oficial `docs/guides/github-auth.md`
Elaborar `docs/guides/github-auth.md` em portugues sem acentos seguindo o padrao de `docs/guides/google-auth.md`:
1. **Principios e Arquitetura**:
   - Rotas de maquina unicas (`/auth/github/redirect`, `/auth/github/callback`) fora do prefixo de idioma.
   - Conta sem senha como estado legitimo (ADR-023).
   - Vinculo seguro por e-mail verificado (`user:email`).
   - Botoes sempre visiveis com retorno contextual em falhas (ADR-036).
2. **Passo a Passo no GitHub Developer Settings**:
   - Navegacao: `Settings > Developer Settings > OAuth Apps > New OAuth App`.
   - Campos obrigatorios: Application Name, Homepage URL, Authorization callback URL.
   - Recomendacao de aplicativos separados para desenvolvimento local e producao.
   - Geracao do Client Secret e copia segura.
3. **Configuracao no `.env`**:
   - Variaveis `GITHUB_CLIENT_ID` e `GITHUB_CLIENT_SECRET`.
   - Limpeza de cache de configuracao (`php artisan config:clear` / `optimize:clear`).
4. **Fluxos de Teste e Validacao**:
   - Cadastro de nova conta pelo GitHub (`/register`).
   - Login com conta existente (`/login`).
   - Vinculo com conta tradicional pre-existente.
   - Definicao de primeira senha em `/settings/security`.
   - Comportamento com e-mails privados `@users.noreply.github.com`.
5. **Resolucao de Problemas (Troubleshooting)**:
   - Erro `redirect_uri_mismatch`.
   - Mensagem "O GitHub nao informou um e-mail".
   - Mensagem "Entrar com GitHub ainda nao esta disponivel".
   - Cancelamento ou recusa de consentimento pelo visitante.
   - Rotacao e revogacao de Client Secret sem downtime.

### Etapa 3: Referencias e Documentacao Cruzada
- Adicionar link para `docs/guides/github-auth.md` em `docs/RUNBOOK.md` (secao de variaveis de ambiente de producao / login social).
- Atualizar documentacao pertinente.

### Etapa 4: Verificacao e Qualidade Final
- Executar `composer test` (Pint, PHPStan, Pest).
- Executar `npm run test` (Vitest).
- Executar `npm run types:check` (vue-tsc).
- Gerar relatorio final em `docs/gemini/reports/develop_natan/20261019_develop_natan_cadastro_login_github_tutorial.md`.
