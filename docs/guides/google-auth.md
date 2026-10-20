# Guia - Autenticacao com Google (OAuth 2.0)

Como configurar e ativar o cadastro e login com a conta Google no site. **Todo o codigo necessario ja existe na aplicacao** (Laravel Socialite, `SocialAuthController`, `SocialProviders`, ADR-023 e ADR-036): ativar o Google exige apenas criar o app OAuth 2.0 no Google Cloud Console e preencher **duas variaveis** no arquivo `.env`. Nao ha codigo PHP ou TypeScript para escrever. (Para configurar o GitHub, consulte [docs/guides/github-auth.md](github-auth.md)).

---

## 1. Principios e Arquitetura

O login social no DevTools segue quatro regras arquiteturais (ver `docs/adr/ADR-023-login-social-e-conta-sem-senha.md` e `docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`):

1. **Endpoint de maquina unico**: As rotas `/auth/{provider}/redirect` e `/auth/{provider}/callback` ficam propositalmente **fora** do prefixo de idioma (`/{locale}`). Isso significa que voce cadastra a URL de callback **uma unica vez** no console do Google por ambiente (local e producao), independentemente de o site suportar cinco idiomas (ADR-022).
2. **Conta sem senha como estado legitimo**: Quem cria a conta pelo Google nasce com `users.password` nulo e `email_verified_at` confirmado imediatamente. O usuario pode utilizar o site normalmente e, se desejar alterar dados sensiveis em `settings/security`, podera definir sua primeira senha diretamente na tela de confirmacao (ADR-023).
3. **Vinculo seguro por e-mail verificado**: Se ja existir uma conta local com o mesmo e-mail, o vinculo a conta Google so ocorre porque o Google atesta `email_verified = true` no payload OAuth. Se um provedor nao confirmar o e-mail, o vinculo e recusado para impedir tomada indevida de conta.
4. **Botoes sempre visiveis e retorno contextual**: Os botoes do Google continuam visiveis mesmo que as chaves nao estejam preenchidas no `.env`. Caso o visitante clique sem as credenciais configuradas, o sistema nao lanca erro 500: retorna com aviso amigavel para a tela de origem (quem clicou em cadastro volta para cadastro, quem clicou em login volta para login) (ADR-036).

---

## 2. Passo a Passo no Google Cloud Console

### Passo 1: Criar ou Selecionar o Projeto

1. Acesse o console do Google Cloud: <https://console.cloud.google.com>.
2. Faca login com a conta Google institucional ou responsavel pelo projeto.
3. No topo da pagina, clique no seletor de projetos e escolha **Novo projeto** (New Project).
4. Defina o **Nome do projeto** (exemplo: `devtools-natanfiuza`).
5. Clique em **Criar** (Create) e selecione o projeto recem-criado.

---

### Passo 2: Configurar a Tela de Consentimento OAuth

Antes de criar credenciais de cliente, o Google exige a configuracao basica da tela que o usuario ve ao autorizar o login:

1. No menu lateral esquerdo, navegue ate **APIs e servicos** (APIs & Services) -> **Tela de consentimento OAuth** (OAuth consent screen).
2. Em **Tipo de usuario** (User Type), selecione **Externo** (External) para permitir acesso de qualquer usuario com conta Google. Clique em **Criar** (Create).
3. Na aba **Informacoes do aplicativo**:
   - **Nome do aplicativo**: `DevTools` (ou o nome da sua instalacao).
   - **E-mail para suporte do usuario**: selecione o seu e-mail da lista.
   - **Logotipo do app**: opcional.
   - **Dominio do aplicativo** (obrigatorio para producao):
     - Pagina inicial: `https://devtools.natanfiuza.dev.br`
     - Link da politica de privacidade: `https://devtools.natanfiuza.dev.br/pt-br/privacy`
     - Link dos termos de servico: `https://devtools.natanfiuza.dev.br/pt-br/terms`
   - **Dominios autorizados**: adicione `natanfiuza.dev.br`.
   - **Dados de contato do desenvolvedor**: informe o e-mail de contato tecnico.
   - Clique em **Salvar e continuar**.
4. Na aba **Escopos** (Scopes):
   - Clique em **Adicionar ou remover escopos** (Add or remove scopes).
   - Marque os tres escopos basicos e nao sensiveis:
     - `.../auth/userinfo.email` (Ver seu endereco de e-mail primario)
     - `.../auth/userinfo.profile` (Ver suas informacoes pessoais)
     - `openid` (Associar voce ao seu perfil pessoal no Google)
   - Clique em **Atualizar** e em seguida em **Salvar e continuar**.
5. Na aba **Usuarios de teste** (Test users):
   - Enquanto o aplicativo estiver com status de publicacao "Em teste" (Testing), **apenas** usuarios cadastrados nesta lista poderao efetuar login.
   - Adicione o seu e-mail pessoal e os e-mails que farao testes locais.
   - Clique em **Salvar e continuar**.
6. Conclua a revisao e volte ao painel.
7. **Publicacao para producao**:
   - Quando o site estiver pronto para o publico geral, volte a tela de consentimento e clique em **Publicar aplicativo** (Publish app). Como utilizamos apenas escopos basicos (`email`, `profile`, `openid`), **nao ha necessidade de auditoria externa de seguranca (CASA)**.

---

### Passo 3: Criar as Credenciais OAuth 2.0 (Aplicativo da Web)

1. No menu lateral, clique em **Credenciais** (Credentials).
2. Na barra superior, clique em **+ Criar credenciais** (+ Create Credentials) -> **ID do cliente OAuth** (OAuth client ID).
3. Em **Tipo de aplicativo** (Application type), selecione **Aplicativo da Web** (Web application).
4. No campo **Nome**, informe: `DevTools Web Client`.
5. Em **Origens JavaScript autorizadas** (Authorized JavaScript origins), adicione:
   - Para desenvolvimento local:
     - `http://localhost:8000`
     - `http://127.0.0.1:8000`
   - Para producao:
     - `https://devtools.natanfiuza.dev.br`
6. Em **URIs de redirecionamento autorizados** (Authorized redirect URIs), adicione:
   - Para desenvolvimento local:
     - `http://localhost:8000/auth/google/callback`
     - `http://127.0.0.1:8000/auth/google/callback`
   - Para producao:
     - `https://devtools.natanfiuza.dev.br/auth/google/callback`
   > **Atencao com a URI de callback**: O Google faz comparacao binaria exata de string. O protocolo (`http` local vs `https` producao), a porta (ex. `:8000`) e a ausencia de barra no final (`/`) devem ser rigorosamente respeitados.
7. Clique em **Criar** (Create).
8. Uma janela modal sera exibida com:
   - **ID do cliente** (`Client ID`): formato `XXXXXXXXXX-xxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com`
   - **Segredo do cliente** (`Client Secret`): formato `GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx`
9. Copie ambos os valores com seguranca.

---

## 3. Configurar no Arquivo `.env`

No seu ambiente de desenvolvimento ou no servidor de producao (`/var/www/devtools/.env`), insira as variaveis:

```dotenv
# Login Social - Google OAuth 2.0
GOOGLE_CLIENT_ID=seu_client_id_aqui.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=seu_client_secret_aqui
```

> **Nota sobre o redirect**: A variavel `GOOGLE_REDIRECT_URI` e opcional, pois o arquivo `config/services.php` ja define `/auth/google/callback` como valor padrao. Caso sua aplicacao utilize um dominio ou porta customizada, voce pode declarar explicitamente:
> `GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback`

Apos editar o `.env`, aplique as alteracoes de configuracao:

```bash
php artisan optimize:clear && php artisan optimize
```

No ambiente de desenvolvimento local, basta executar:

```bash
php artisan config:clear
```

---

## 4. Como Testar e Validar os Fluxos

### Teste 1: Cadastro com conta nova via Google
1. Abra o navegador em janela anonima (para garantir que nao haja sessao ativa).
2. Acesse a tela de cadastro: `http://localhost:8000/pt-br/register`.
3. Observe que o botao exibe o rotulo **"Cadastrar com Google"** (ou no idioma correspondente).
4. Clique no botao do Google. Voce sera redirecionado para a tela de login do Google.
5. Selecione sua conta Google e aprove as permissoes.
6. A aplicacao recebera a resposta em `/auth/google/callback` e redirecionara voce autenticado para `/dashboard`.
7. No banco de dados:
   - Uma nova linha em `users` tera sido criada com `password = null`, `email_verified_at` preenchido e o nome vindo do perfil Google.
   - Uma linha na tabela `social_accounts` tera sido gravada com `provider = 'google'`, associando o ID exclusivo do Google ao `user_id`.

### Teste 2: Login com conta ja existente
1. Efetue logout da conta.
2. Acesse a tela de login: `http://localhost:8000/pt-br/login`.
3. Observe que o botao exibe o rotulo **"Entrar com Google"**.
4. Clique no botao. Como o consentimento ja foi concedido anteriormente, o Google autoriza de imediato e o sistema redireciona direto para o `/dashboard`.

### Teste 3: Vinculo com conta tradicional pre-existente
1. Crie previamente uma conta por e-mail e senha no site usando o e-mail `seu.usuario@gmail.com`.
2. Faca logout e tente entrar usando o botao "Entrar com Google" da mesma conta `seu.usuario@gmail.com`.
3. Como o Google confirma que o e-mail e autentico (`email_verified = true`), o sistema automaticamente vincula o Google a conta local pre-existente e faz o login. A senha anterior continua valida e inalterada.

### Teste 4: Definir primeira senha
1. Com uma conta criada exclusivamente pelo Google (sem senha), navegue ate `Configuracoes > Seguranca` (`/settings/security`).
2. O sistema aciona a confirmacao de seguranca do Laravel Fortify.
3. Como a conta nao possui senha anterior, a tela apresenta o formulario adaptado **"Defina sua senha"** em vez de pedir a senha atual (ADR-023).
4. Informe e confirme a nova senha. A conta passa a ter senha registrada e a area sensivel e liberada.

---

## 5. Resolucao de Problemas (Troubleshooting)

### Erro `redirect_uri_mismatch`
- **Sintoma**: O Google exibe uma tela de erro dizendo que a URI de redirecionamento nao corresponde a nenhuma das cadastradas.
- **Causa**: A URL gerada pela aplicacao difere do que foi salvo no Google Cloud Console.
- **Como resolver**:
  1. Verifique a URL que aparece na barra de navegacao no momento do erro (parametro `redirect_uri=...`).
  2. Certifique-se de que no console do Google a URI cadastrada seja exatamente igual (ex.: `http://localhost:8000/auth/google/callback` vs `http://127.0.0.1:8000/auth/google/callback`).
  3. Confira se nao ha barra `/` extra ao final da URL.
  4. Em producao atras de proxy reverso (Nginx/Cloudflare), certifique-se de que os cabecalhos `X-Forwarded-Proto` e `X-Forwarded-Host` estao sendo repassados para que o Laravel monte a URL com `https://`.

### Aviso "O Google nao verificou este app" (App not verified)
- **Sintoma**: Ao tentar autenticar, o Google exibe uma tela com aviso de seguranca.
- **Causa**: O projeto no Google Cloud Console esta com status de publicacao "Em teste" ou ainda nao foi publicado.
- **Como resolver**:
  - Em desenvolvimento: clique em **Avancado** (Advanced) e depois em **Acessar DevTools (nao seguro)** (Go to DevTools).
  - Em teste com outros desenvolvedores: certifique-se de que os e-mails deles estao cadastrados na lista de **Usuarios de teste** da tela de consentimento.
  - Para producao: va na aba **Tela de consentimento OAuth** e clique no botao **Publicar aplicativo** (Publish app).

### Mensagem "Entrar com Google ainda nao esta disponivel"
- **Sintoma**: Ao clicar no botao do Google no site, o usuario volta para a tela de login ou cadastro com essa mensagem de status.
- **Causa**: As variaveis `GOOGLE_CLIENT_ID` ou `GOOGLE_CLIENT_SECRET` estao vazias ou ausentes no `.env`, ou a configuracao em cache esta desatualizada.
- **Como resolver**:
  1. Verifique se as chaves estao preenchidas no `.env`.
  2. Execute `php artisan config:clear` (ou `php artisan optimize:clear`).

### Mensagem "Nao foi possivel concluir a entrada com Google"
- **Sintoma**: O usuario e devolvido para a tela de origem com aviso de falha.
- **Causa**: O usuario clicou em "Cancelar" na tela do Google, ou o token expirou, ou ocorreu erro de comunicacao SSL entre o servidor e o Google.
- **Como resolver**:
  - Essa e uma saida normal e tratada com seguranca pelo `SocialAuthController` (ADR-023). O usuario pode simplesmente clicar novamente para tentar.

### Rotacao de Chaves sem Downtime
Para trocar o segredo do cliente (`Client Secret`) periodicamente ou em caso de vazamento:
1. No Google Cloud Console, acesse **Credenciais** e abra o cliente OAuth existente.
2. Clique em **Adicionar segredo do cliente** (Add client secret). O Google permite manter dois segredos ativos simultaneamente.
3. Atualize a variavel `GOOGLE_CLIENT_SECRET` no `.env` do servidor e rode `php artisan optimize:clear && php artisan optimize`.
4. Apos confirmar que o login continua funcionando, retorne ao console e exclua o segredo antigo.
