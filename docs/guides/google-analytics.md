# Guia - Google Analytics (GA4)

Como ligar a medicao do site. **O carregamento ja existe no codigo** (ADR-013 e
ADR-032): ativar e criar a propriedade no Google e preencher **uma variavel** no
`.env` de producao. Nao ha codigo para escrever.

Tres regras valem em todos os passos daqui:

- **So producao.** O id vive no `.env` do VPS. Em local, em teste e em CI a
  variavel fica vazia - id vazio significa medicao desligada, **mesmo com
  aceite**.
- **Nada de medicao antes do aceite.** A tag so e injetada no cliente depois que
  o visitante aceita "Medicao" no banner; recusar significa **zero requisicao**
  ao Google (ADR-013).
- **O banner e uma promessa.** Ele diz "paginas vistas e cookies de medicao, so
  depois do seu aceite": tudo que for ligado na console precisa caber nessa
  frase (secao 4).

## 1. Criar a propriedade (uma vez, na console)

1. Abrir <https://analytics.google.com> com a conta Google do site.
2. **Admin** (engrenagem, canto inferior esquerdo) -> **Create** -> **Property**.
3. Nome da propriedade (ex. `devtools.natanfiuza.dev.br`), fuso horario
   `(GMT-03:00) Sao Paulo` e moeda `Real brasileiro (BRL)`.
4. Plataforma **Web**; URL do site `https://devtools.natanfiuza.dev.br` e nome do
   stream (ex. `Site`).
5. **Admin -> Data collection and modification -> Data streams** -> o stream
   recem-criado. O **Measurement ID** (`G-XXXXXXXXXX`) fica no canto superior
   direito da tela do stream.

**Nao cole o bloco "Google tag (gtag.js)"** que a console oferece para colar no
HTML. A tag deste site entra pelo cliente, uma unica vez e so depois do aceite
(`resources/js/lib/analytics.ts`). Aquele bloco no `app.blade.php` poria medicao
no HTML do servidor e no SSR - exatamente o que o ADR-013 proibe.

## 2. Ligar no servidor

No `.env` **de producao** (`/var/www/devtools/.env`):

```dotenv
ANALYTICS_GA4_ID=G-XXXXXXXXXX
```

E aplicar, porque `.env` novo em config velha nao vale nada:

```bash
php artisan optimize:clear && php artisan optimize
```

O id e lido por `config/analytics.php` e chega ao navegador como a prop
compartilhada `analytics.ga4Id`. O deploy seguinte roda `optimize` sozinho, mas
nao o `optimize:clear` - a troca de `.env` e manual.

A validade do cookie de consentimento (180 dias) sai de
`ANALYTICS_CONSENT_DAYS`; so mude se houver razao para mudar.

## 3. Conferir

1. Abrir `https://devtools.natanfiuza.dev.br/pt-br` numa **aba anonima** (sem
   cookie `consent`).
2. DevTools -> **Network**, filtrar por `googletagmanager`: **nenhuma** requisicao
   enquanto o banner estiver sem resposta. Recusar: continua nenhuma. Aceitar:
   uma requisicao `gtag/js?id=G-...`, **uma vez so** (reabrir o banner pelo
   rodape e aceitar de novo nao cria uma segunda).
3. Ainda no **Network**, filtrar por `collect`: depois do aceite sai um
   `.../g/collect?...` (`region1.google-analytics.com` ou
   `www.google-analytics.com`) com status **204** - e o **hit** (a pagina vista)
   chegando ao GA. Na query, o `gcs` diz o que o Consent Mode liberou:
   `G100` e tudo negado (a tag carrega, mas so sai ping sem cookie - "hit"
   nenhum, que e o desenho de quem nao aceitou). Aqui o aceite nega publicidade
   por desenho, entao **nao** espere `G111`: o valor normal tem a medicao
   liberada e a publicidade negada (algo como `G101`).
4. DevTools -> **Application -> Cookies**: `consent=granted` e, depois que a tag
   roda, os cookies do GA (`_ga` e `_ga_<id do stream>`).
5. Console do GA -> **Reports -> Realtime**: a visita aparece depois do aceite.
6. **View-source** da pagina (HTML do servidor): nenhuma ocorrencia de
   `googletagmanager` nem de `gtag(`, em nenhum estado do banner. E o que o
   `ConsentTest` afirma a cada rodada de teste.

### Se o teste do proprio Google falhar

O assistente de configuracao ("Teste sua tag") e o Tag Assistant abrem o site
numa **sessao nova, sem o cookie `consent`**. Sessao nova significa banner - e
sem aceite nao ha tag, por desenho (ADR-013). O teste falha dizendo que nao
encontrou a tag mesmo com tudo certo, e **isso e o esperado**: e exatamente o
que qualquer visitante novo ve.

Para conferir de verdade, em ordem de forca da prova:

1. **Aceite o banner na sessao que o Google esta observando** e teste de novo - o
   cookie fica naquela sessao. No Tag Assistant
   (<https://tagassistant.google.com>) quem navega e voce, entao da para aceitar
   no meio do caminho.
2. **Network** do DevTools: `googletagmanager.com/gtag/js?id=G-...` respondendo
   **200**, uma unica vez, depois do aceite (passo 2 acima).
3. **Reports -> Realtime** do GA4: a visita aparece em ~10 s. Quando o teste do
   assistente nao fecha, e por aqui que o proprio Google manda conferir.

Se nem com aceite a tag carregar, dois suspeitos, nesta ordem: o `G-...` do `src`
da tag nao e o mesmo Measurement ID do stream aberto na console (dois streams na
mesma propriedade), e `.env` novo com config velha (`optimize:clear && optimize`,
secao 2).

**O teste tambem e instavel com o aceite feito**, e as duas razoes sao do
desenho, nao um defeito:

- Quando o site e carregado **dentro de um iframe** (o Tag Assistant faz isso na
  tela dele), o cookie `consent` **nao acompanha** a requisicao: ele e
  `SameSite=Lax` (`resources/js/lib/cookies.ts`), e cookie Lax nao vai em
  contexto de terceiro. Sem cookie, o servidor monta a pagina como "ainda nao
  decidiu" - banner, sem tag. Em **aba propria** (navegacao top-level) o cookie
  vai junto e a tag entra. A mesma configuracao passa ou falha conforme o
  contexto em que o teste carregou a pagina naquela rodada.
- A tag entra **depois da hidratacao** - e o cliente que injeta, por desenho. Uma
  verificacao que escaneia cedo demais nao a encontra; a que reescaneia depois
  encontra.

Por isso o teste do Google nao e criterio: **se o Tempo real mostra a visita, a
medicao esta funcionando.** Para o teste ficar verde, use o Tag Assistant com a
pagina numa aba normal, aceitando o banner ali.

**"Tag detectada, zero hits" e sintoma, nao ruido.** A prova de que o hit saiu e
a requisicao `/g/collect` no Network (passo 3, status **204**) e o Tempo real
(passo 5). Sem nenhuma das duas depois do aceite, dois suspeitos, nesta ordem:

1. **Formato dos comandos na fila do `dataLayer`:** o gtag.js despacha so o item
   que for um objeto `arguments` e ignora um Array **em silencio** - tag
   detectada, nenhum erro no console, nenhum hit. Foi esse o defeito corrigido em
   2026-10-16; o contrato esta na secao 6 e o teste afirma o formato.
2. **Bundle JS antigo ainda publicado no VPS (`public/build`):** se o commit da
   correcao ja entrou no `main` mas o navegador continua recebendo o `app-*.js`
   anterior (`function ...(...e){...dataLayer.push(e)}`), o deploy parou antes da
   troca do symlink ou a copia `deploy/deploy-instaled.sh` esta defasada. Confira
   o `Last-Modified` de `/build/manifest.json` e rode
   `sudo /var/www/devtools/deploy/deploy-instaled.sh --force` (ver
   `deploy/deploy-instaled.md` e `docs/RUNBOOK.md` secao 2).

## 4. O que NAO ligar na console

| O que | Onde | Por que |
|---|---|---|
| Google Signals | Admin -> Data collection -> Google signals | Cruzaria a visita com contas logadas no Google; o banner promete "Nada de anuncios" |
| Personalizacao de anuncios | Admin -> Data collection | Nao ha publicidade neste site - so medicao |
| Eventos extras do Enhanced measurement (scrolls, cliques de saida, busca no site, video e downloads) | Admin -> Data streams -> stream -> Enhanced measurement (engrenagem) | Vem **ligado** por padrao e coleta mais do que "paginas vistas". **Mantenha o Enhanced measurement ligado so com "Page views" ("Page changes based on browser history events") marcado** - e assim que o GA4 mede as trocas de pagina do Inertia (`history.pushState`) sem um segundo `gtag('config')` - e **desligue os outros 5 interruptores** |
| User-ID / dados fornecidos pelo usuario | Admin -> Data collection | O site nao manda identificador de usuario para o GA |
| Retencao de dados | Admin -> Data collection -> Data retention | Deixar em **14 meses** (o maximo): menos dado guardado, mesma leitura |

O endereco IP nao e registrado pelo GA4 - e o comportamento padrao, nao ha o que
configurar.

Os dois sinais de publicidade tambem vao desligados **no codigo**
(`allow_google_signals` e `allow_ad_personalization_signals` em
`lib/analytics.ts`): a configuracao da console e o cinto, a do codigo e o
suspensorio.

## 5. Rotacao do id

Propriedade comprometida, stream recriado ou mudanca de conta:

1. Criar a propriedade/stream novo (secao 1) e copiar o id novo.
2. Trocar `ANALYTICS_GA4_ID` no `.env` do VPS e rodar
   `php artisan optimize:clear && php artisan optimize`.

Os dados antigos ficam na propriedade antiga - nao ha historico para migrar. O
`deploy.sh` **nao toca no `.env`** (de proposito, ADR-014): a troca e manual e
cabe numa janela de segundos.

## 6. Onde isso vive no codigo

| Peca | Arquivo |
|---|---|
| Id, nome e validade do cookie | `config/analytics.php` |
| Id resolvido (vazio vira `null`) | `app/Support/Analytics.php` |
| Decisao de tres estados, lida do cookie | `app/Support/Consent.php`, `app/Http/Middleware/HandleConsent.php` |
| Props compartilhadas | `app/Http/Middleware/HandleInertiaRequests.php` |
| Injecao da tag (Consent Mode v2) | `resources/js/lib/analytics.ts` |
| Componente sem marcacao que chama a injecao | `resources/js/components/AnalyticsScript.vue` |
| Banner | `resources/js/components/ConsentBanner.vue` |
| Onde o par e montado | `PublicLayout.vue`, `AuthLayout.vue` e `account/AccountLayout.vue` (**nao** no `AdminLayout`) |

### A fila do gtag: `arguments`, nunca Array

Os comandos (`consent`, `js`, `config`) vao para `window.dataLayer`, e o gtag.js
os le quando carrega. O que ele **despacha** e so o item que for um objeto
`arguments` (`[object Arguments]`, ou um objeto com `callee`); um Array comum cai
no ramo dos "campos de modelo" e e ignorado - tag carregada, nenhum erro no
console, nenhum hit. Quem escreve `dataLayer.push([...])` acha que enfileirou um
comando e nao enfileirou nada.

Por isso o shim em `lib/analytics.ts` usa `arguments` **de proposito** (o
`eslint-disable` de `prefer-rest-params` existe por isso) e por isso o tipo de
`window.dataLayer` nao aceita Array: as duas coisas barram a "simplificacao" que,
em 2026-10-16, deixou a medicao zerada com a tag carregando normalmente. A
regressao esta presa no teste "enfileira os comandos como objetos `arguments`"
(`resources/js/__tests__/consent-banner.test.ts`).

## 7. Desligar

Esvaziar `ANALYTICS_GA4_ID` e rodar `php artisan optimize:clear && php artisan optimize`.
A proxima pagina ja sai sem tag; quem estiver com a pagina aberta so para de
reportar no proximo carregamento (tirar o `<script>` do `<head>` nao desfaz o
que o gtag.js ja carregou - quem para o envio e o Consent Mode).
