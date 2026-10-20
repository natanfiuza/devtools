# Guia - Autenticacao com GitHub (OAuth Apps)

Como configurar e ativar o cadastro e login com a conta GitHub no site. **Todo o codigo necessario ja existe na aplicacao** (Laravel Socialite, `SocialAuthController`, `SocialProviders`, ADR-023 e ADR-036): ativar o GitHub exige apenas registrar o OAuth App no GitHub Developer Settings e preencher **duas variaveis** no arquivo `.env`. Nao ha codigo PHP ou TypeScript para escrever. (Para configurar o Google, consulte [docs/guides/google-auth.md](google-auth.md)).

---

## 1. Principios e Arquitetura

O login social no DevTools segue quatro regras arquiteturais (ver `docs/adr/ADR-023-login-social-e-conta-sem-senha.md` e `docs/adr/ADR-036-retorno-contextual-auth-social-e-botoes-especificos.md`):

1. **Endpoint de maquina unico**: As rotas `/auth/{provider}/redirect` e `/auth/{provider}/callback` ficam propositalmente **fora** do prefixo de idioma (`/{locale}`). Isso significa que a URL de callback e cadastrada de forma limpa, sem depender do idioma de navegacao do usuario (ADR-022).
2. **Conta sem senha como estado legitimo**: Quem cria a conta pelo GitHub nasce com `users.password` nulo e `email_verified_at` confirmado imediatamente. O usuario pode utilizar o site normalmente e, se desejar alterar dados sensiveis em `settings/security`, podera definir sua primeira senha diretamente na tela de confirmacao (ADR-023).
3. **Vinculo seguro por e-mail verificado**: O Laravel Socialite solicita o escopo `user:email` por padrao e consulta a API de e-mails do GitHub (`/user/emails`), selecionando exclusivamente o e-mail primario e verificado (`primary = true` e `verified = true`). Se ja existir uma conta local com o mesmo e-mail, o vinculo e realizado com seguranca. Se o GitHub nao devolver nenhum e-mail verificado, o vinculo e recusado para impedir tomada indevida de conta.
4. **Fallback inteligente para o nome**: Quando o perfil do GitHub nao possui o campo de nome publico preenchido (`name`), o sistema utiliza automaticamente o nome de usuario (`nickname`/handle) ou, em ultimo caso, o proprio e-mail.
5. **Botoes sempre visiveis e retorno contextual**: Os botoes do GitHub continuam visiveis mesmo que as chaves nao estejam preenchidas no `.env`. Caso o visitante clique sem as credenciais configuradas, o sistema nao lanca erro 500: retorna com aviso amigavel para a tela de origem (quem clicou em cadastro volta para cadastro com mensagem de status, quem clicou em login volta para login) (ADR-036).

---

## 2. Passo a Passo no GitHub Developer Settings

> **Regra fundamental do GitHub OAuth**: Diferente do Google Cloud Console (que permite cadastrar multiplas origens e URIs de redirecionamento no mesmo aplicativo), o GitHub permite **apenas uma Authorization callback URL por OAuth App**.
>
> Por essa razao, a recomendacao oficial e criar **dois OAuth Apps separados**:
> 1. Um app para desenvolvimento local (ex: `DevTools Local`).
> 2. Um app para o ambiente de producao (ex: `DevTools Producao`).

### Passo 1: Acessar a Area de Developer Settings

1. Acesse o GitHub: <https://github.com>.
2. Faca login com a conta responsavel pela organizacao ou pelo projeto.
3. No canto superior direito, clique na sua foto de perfil e selecione **Settings** (Configuracoes).
4. Na barra lateral esquerda, role ate o final e clique em **Developer settings** (ou acesse diretamente <https://github.com/settings/developers>).
5. No menu lateral, clique em **OAuth Apps**.

---

### Passo 2: Criar o App para Desenvolvimento Local

1. Clique no botao **New OAuth App** (ou **Register a new application**).
2. Preencha os campos com os dados do ambiente local:
   - **Application name**: `DevTools Local`
   - **Homepage URL**: `http://localhost:8000` (ou `http://127.0.0.1:8000`, conforme o seu ambiente)
   - **Application description**: `Ambiente de desenvolvimento local do DevTools` (opcional)
   - **Authorization callback URL**: `http://localhost:8000/auth/github/callback`
     > **Atencao**: A URL deve corresponder exatamente ao host e porta em que o Laravel esta rodando localmente, sem barra `/` extra ao final.
3. Clique em **Register application**.
4. Na pagina do aplicativo recem-criado:
   - Copie o **Client ID** (formato similar a `Ov23liXXXXXXXXXX` ou 20 caracteres hexadecimais).
   - Em **Client secrets**, clique em **Generate a new client secret**.
   - O GitHub podera solicitar a confirmacao de senha ou autenticacao em dois fatores (2FA).
   - Copie o **Client Secret** imediatamente. O GitHub **nunca mais** exibira esse valor por questoes de seguranca.

---

### Passo 3: Criar o App para Producao

1. Retorne a pagina de **OAuth Apps** (<https://github.com/settings/developers>) e clique em **New OAuth App**.
2. Preencha os campos com os dados de producao:
   - **Application name**: `DevTools`
   - **Homepage URL**: `https://devtools.natanfiuza.dev.br`
   - **Application description**: `Caixa de ferramentas para desenvolvedores: conversores, validadores e utilitarios client-side.`
   - **Authorization callback URL**: `https://devtools.natanfiuza.dev.br/auth/github/callback`
3. Opcionalmente, faca upload do logotipo oficial da aplicacao em **Application logo** para transmitir confianca aos usuarios na tela de consentimento.
4. Clique em **Register application**.
5. Em **Client secrets**, clique em **Generate a new client secret** e copie o valor gerado com seguranca.

---

## 3. Configurar no Arquivo `.env`

No seu ambiente de desenvolvimento local ou no servidor de producao (`/var/www/devtools/.env`), preencha as variaveis correspondentes:

```dotenv
# Login Social - GitHub OAuth
GITHUB_CLIENT_ID=seu_client_id_aqui
GITHUB_CLIENT_SECRET=seu_client_secret_aqui
```

> **Nota sobre o redirect**: A variavel `GITHUB_REDIRECT_URI` e opcional, pois o arquivo `config/services.php` ja define `/auth/github/callback` como valor padrao. Caso sua aplicacao utilize um dominio ou porta customizada, voce pode declarar explicitamente:
> `GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback`

Apos editar o `.env`, aplique as alteracoes de configuracao:

No ambiente de producao:
```bash
php artisan optimize:clear && php artisan optimize
```

No ambiente de desenvolvimento local:
```bash
php artisan config:clear
```

---

## 4. Como Testar e Validar os Fluxos

### Teste 1: Cadastro com conta nova via GitHub
1. Abra o navegador em janela anonima (para garantir que nao haja sessao ativa).
2. Acesse a tela de cadastro: `http://localhost:8000/pt-br/register`.
3. Observe que o botao exibe o rotulo **"Cadastrar com GitHub"** (ou no idioma selecionado).
4. Clique no botao do GitHub. Voce sera redirecionado para a tela de autorizacao do GitHub.
5. Autorize o aplicativo.
6. A aplicacao recebera o retorno em `/auth/github/callback` e redirecionara voce autenticado para `/dashboard`.
7. No banco de dados:
   - Uma nova linha em `users` tera sido criada com `password = null`, `email_verified_at` preenchido e o nome vindo do perfil (ou nickname).
   - Uma linha na tabela `social_accounts` tera sido gravada com `provider = 'github'`, associando o ID exclusivo do GitHub ao `user_id`.

### Teste 2: Login com conta ja existente
1. Efetue logout da conta.
2. Acesse a tela de login: `http://localhost:8000/pt-br/login`.
3. Observe que o botao exibe o rotulo **"Entrar com GitHub"**.
4. Clique no botao. Como o consentimento ja foi concedido previamente, o GitHub autoriza imediatamente e o sistema redireciona direto para o `/dashboard`.

### Teste 3: Vinculo com conta tradicional pre-existente
1. Crie previamente uma conta por e-mail e senha no site usando o mesmo e-mail primario da sua conta GitHub.
2. Faca logout e clique em "Entrar com GitHub" ou "Cadastrar com GitHub".
3. Como o GitHub confirma a autenticidade do e-mail primario (`verified = true`), o sistema automaticamente vincula o GitHub a conta local pre-existente e efetua o login. A senha anterior permanece intacta e valida.

### Teste 4: Definir primeira senha
1. Com uma conta criada exclusivamente pelo GitHub (sem senha), navegue ate `Configuracoes > Seguranca` (`/settings/security`).
2. O sistema aciona a confirmacao de seguranca do Laravel Fortify.
3. Como a conta nao possui senha anterior, a tela apresenta o formulario adaptado **"Defina sua senha"** em vez de solicitar uma senha atual inexistente (ADR-023).
4. Informe e confirme a nova senha. A conta passa a ter senha registrada e a area sensivel e liberada.

### Teste 5: Usuario com e-mail privado no GitHub
1. Se o usuario possui a opcao "Keep my email addresses private" ativada nas configuracoes de e-mail do GitHub, a API devolve um e-mail do tipo `ID+username@users.noreply.github.com`.
2. Como esse e-mail e marcado como verificado pelo GitHub, a conta e criada com sucesso usando esse endereco para comunicacao.

---

## 5. Resolucao de Problemas (Troubleshooting)

### Erro `redirect_uri_mismatch`
- **Sintoma**: Ao clicar no botao do GitHub, a tela exibe `The redirect_uri MUST match the registered callback URL for this application`.
- **Causa**: A URL gerada pela aplicacao difere daquela cadastrada no campo **Authorization callback URL** do OAuth App no GitHub.
- **Como resolver**:
  1. Verifique a URL na barra de enderecos no momento do redirecionamento (parametro `redirect_uri=...`).
  2. Certifique-se de que no GitHub Developer Settings a URI cadastrada seja exatamente igual (ex.: `http://localhost:8000/auth/github/callback` vs `http://127.0.0.1:8000/auth/github/callback`).
  3. Confira se nao ha barra `/` extra ao final da URL.
  4. Em producao atras de proxy reverso (Nginx/Cloudflare), certifique-se de que os cabecalhos `X-Forwarded-Proto` e `X-Forwarded-Host` estao sendo repassados para que o Laravel monte a URL com `https://`.

### Mensagem "O GitHub nao informou um e-mail"
- **Sintoma**: O usuario e devolvido para a tela de login ou cadastro com o aviso: "O GitHub nao informou um e-mail. Crie uma conta com e-mail e senha."
- **Causa**: O usuario possui uma conta no GitHub sem nenhum e-mail marcado como verificado, ou recusou o escopo de leitura de e-mail.
- **Como resolver**:
  - O usuario deve acessar <https://github.com/settings/emails> e verificar ao menos um endereco de e-mail na sua conta GitHub antes de efetuar login social.

### Mensagem "Entrar com GitHub ainda nao esta disponivel"
- **Sintoma**: Ao clicar no botao do GitHub no site, o visitante retorna a tela de login ou cadastro com essa mensagem de aviso.
- **Causa**: As variaveis `GITHUB_CLIENT_ID` ou `GITHUB_CLIENT_SECRET` estao vazias ou ausentes no `.env`, ou a configuracao em cache esta desatualizada.
- **Como resolver**:
  1. Verifique se ambas as chaves estao preenchidas no `.env`.
  2. Execute `php artisan config:clear` (ou `php artisan optimize:clear`).

### Mensagem "Nao foi possivel concluir a entrada com GitHub"
- **Sintoma**: O visitante e devolvido para a tela de origem com mensagem de falha.
- **Causa**: O visitante clicou em "Cancel" na tela de autorizacao do GitHub, o token de estado expirou, ou ocorreu erro transiente de conexao SSL com a API do GitHub.
- **Como resolver**:
  - Trata-se de uma saida normal tratada com seguranca pelo `SocialAuthController`. O usuario pode simplesmente tentar novamente.

### Rotacao de Chaves sem Downtime
Para trocar o segredo do cliente (`Client Secret`) periodicamente ou em caso de suspeita de vazamento:
1. No GitHub Developer Settings, acesse **OAuth Apps** e selecione o aplicativo.
2. Em **Client secrets**, clique em **Generate a new client secret**.
3. O GitHub passara a exibir **dois** secrets ativos simultaneamente.
4. Atualize a variavel `GITHUB_CLIENT_SECRET` no `.env` do servidor e rode:
   ```bash
   php artisan optimize:clear && php artisan optimize
   ```
5. Apos validar que o login com GitHub continua funcionando perfeitamente, retorne ao GitHub Developer Settings e clique no icone de lixeira ao lado do segredo antigo para revoga-lo.
