# ADR-052: Headers de seguranca, CSP por nonce, TrustProxies e Turnstile no cadastro

Status: Aceito
Data: 2026-10-22
Relacionados: ADR-013 (medicao e consentimento), ADR-022 (autenticacao e
cadastro), ADR-027 (privacidade e conta), ADR-035 (fotos de perfil), ADR-050
(API publica v1)

## Contexto

O site nao emitia nenhum cabecalho de seguranca: sem CSP, um script injetado
executaria com a mesma origem e leria a sessao; sem HSTS, um primeiro acesso
em `http` podia ser interceptado; e atras do nginx o Laravel via o IP e o
esquema do proxy, nao os do visitante, o que quebrava canonical, sitemap, OG e
o limite por IP.

Havia restricoes concretas para a CSP:

- o tema aplica a cor antes do primeiro paint por um `<script>` inline no
  Blade;
- os assets de desenvolvimento usam HMR, que injeta scripts inline;
- o Google Analytics e carregado dinamicamente depois do aceite (ADR-013);
- o widget do Turnstile carrega script e iframe de
  `challenges.cloudflare.com`.

O cadastro era o unico formulario publico que cria conta sem nenhuma barreira
anti-bot. Ligar Turnstile em login, recuperacao de senha e contato ampliaria o
atrito e o numero de pontos de falha externa.

## Decisao

### 1. Headers de navegador em um middleware global

`SecurityHeaders` roda em toda resposta e emite:

| Header | Valor |
| --- | --- |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` |
| `X-Frame-Options` | `DENY` |
| `Strict-Transport-Security` | `max-age` de `HSTS_MAX_AGE` (1 ano) com `includeSubDomains`, **so em requisicao segura** |
| `Content-Security-Policy` | politica por nonce, conforme o modo |

`frame-ancestors 'none'` e `X-Frame-Options: DENY` cobrem o mesmo risco em
navegador moderno e antigo. Nao ha `report-uri`: o projeto nao tem coletor de
relatorios, e um endpoint publico de CSP viraria superficie de abuso.

### 2. CSP com nonce por resposta e tres modos

- O middleware gera `Str::random(32)`, chama `Vite::useCspNonce($nonce)` e
  compartilha o valor com o Blade. O script inline do tema e os tags do
  `@vite` saem com o mesmo nonce daquela resposta; nonce nunca e reutilizado.
- Diretivas: `default-src 'self'`; `script-src` com `'self'`, o nonce,
  `googletagmanager.com` e `challenges.cloudflare.com`; `style-src 'self'
  'unsafe-inline'`; `img-src 'self' data:` e os hosts de analytics;
  `font-src 'self' data:`; `connect-src` com analytics e Turnstile;
  `frame-src` so Turnstile; `frame-ancestors 'none'`; `base-uri 'self'`;
  `form-action 'self'`; `object-src 'none'`; e `upgrade-insecure-requests`
  quando a requisicao e segura.
- `CSP_MODE` tem tres valores: `enforce` (default de producao), `report-only`
  (registra sem bloquear) e `off` (default local, por causa do HMR). O modo e
  lido por request, entao a volta para `report-only` e operacional, sem deploy.
- `style-src 'unsafe-inline'` e deliberado: o `<style>` do tema e o bloco de
  cor por papel usam estilo inline. Nao ha `unsafe-inline` em `script-src`.

### 3. Proxies confiaveis, HTTPS e cookie seguro

- `TrustProxies` estende o middleware do framework e le
  `security.trusted_proxies` (de `TRUSTED_PROXIES`, CSV de IPs/CIDR) **a cada
  request**; a classe do framework fixa a lista no boot e ignoraria mudanca de
  config em teste. Cabecalhos aceitos: `X-Forwarded-For`, `-Proto`, `-Host` e
  `-Port`.
- Com `TRUSTED_PROXIES` vazio, o Laravel ignora `X-Forwarded-*` e usa a
  conexao direta. No VPS com nginx no proprio host, o valor de producao e
  `127.0.0.1`.
- `URL::forceScheme('https')` quando `security.force_https` (default de
  producao) garante canonical, OG, sitemap, robots e links de e-mail em
  `https`, mesmo se a requisicao chegar ao PHP como `http`.
- `SESSION_SECURE_COOKIE` default `true` quando `APP_ENV=production`; o cookie
  continua `http_only` e `same_site=lax`. `Strict` quebraria o retorno do
  Checkout Pro da Mercado Pago.

### 4. Turnstile apenas no cadastro, desligado sem chaves

- `ValidTurnstile` e uma regra **implicita**: roda mesmo sem o campo, entao
  payload sem token nao passa. Ela so valida quando
  `services.turnstile.secret_key` existe; sem a chave, passa e o widget nao
  aparece. O estado e explicito e documentado no RUNBOOK.
- Com chave, a regra chama a siteverify via `Http::asForm()` com timeout de 5
  s, envia `secret`, `response` e `remoteip`, e exige `success === true`.
- Falha fechada: token ausente, invalido, resposta inesperada ou servico fora
  do ar recusam com a mesma mensagem generica. O segredo nunca e logado.
- `TurnstileWidget.vue` faz render explicito com a `sitekey`, grava o token no
  formulario e trata expiracao/erro; a pagina de cadastro so o inclui quando a
  sitekey existe.

## Consequencias

### Positivas

- Script injetado nao executa sem o nonce da resposta, e iframe de terceiro
  nao enquadra o site.
- Canonical, OG, sitemap e limite por IP passam a ver o visitante real atras do
  proxy.
- CSP quebrada tem rollback operacional (`CSP_MODE=report-only`) sem deploy de
  codigo.
- O cadastro ganha barreira anti-bot sem tocar nos outros fluxos; sem chaves,
  nada muda para quem desenvolve.

### Negativas / trade-offs

- `style-src` aceita estilo inline; endurecer exigiria nonce/hash em todo
  estilo do shadcn-vue e do tema.
- A politica precisa acompanhar qualquer novo host externo (script, conexao,
  imagem ou frame).
- HSTS so sai quando o proxy esta configurado e a requisicao chega segura;
  `TRUSTED_PROXIES` errado esconde o problema.
- Turnstile adiciona dependencia externa e latencia ao cadastro quando ligado;
  a indisponibilidade da Cloudflare bloqueia novos cadastros (falha fechada).
- O cookie `secure` exige HTTPS em producao; rodar producao em `http` quebra o
  login.

## Alternativas descartadas

- **CSP em `report-only` por padrao**: nao protege; o default e `enforce` e o
  `report-only` e rollback.
- **Hash em vez de nonce**: o script inline muda a cada build/deploy e o hash
  exigiria reescrever o Blade.
- **`style-src` com nonce**: quebraria estilos dinamicos do shadcn-vue e do
  tema sem ganho proporcional.
- **Confiar em `X-Forwarded-*` de qualquer origem**: permitiria spoof de IP e
  esquema; a lista e fechada.
- **TrustProxies fixo no boot**: impediria testar e mudar a lista sem deploy.
- **Turnstile em login e recuperacao**: atrito e pontos de falha sem ameaca
  equivalente; o cadastro e o alvo.
- **Turnstile obrigatorio com chave ausente**: deixaria o cadastro quebrado no
  local e em qualquer ambiente sem as chaves.
