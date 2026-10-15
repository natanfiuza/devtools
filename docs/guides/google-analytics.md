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
3. DevTools -> **Application -> Cookies**: `consent=granted` e, depois que a tag
   roda, os cookies do GA (`_ga` e `_ga_<id do stream>`).
4. Console do GA -> **Reports -> Realtime**: a visita aparece depois do aceite.
5. **View-source** da pagina (HTML do servidor): nenhuma ocorrencia de
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

## 4. O que NAO ligar na console

| O que | Onde | Por que |
|---|---|---|
| Google Signals | Admin -> Data collection -> Google signals | Cruzaria a visita com contas logadas no Google; o banner promete "Nada de anuncios" |
| Personalizacao de anuncios | Admin -> Data collection | Nao ha publicidade neste site - so medicao |
| Enhanced measurement (scroll, cliques de saida, busca do site) | Admin -> Data streams -> stream | Vem **ligado** por padrao e coleta mais do que "paginas vistas"; desligue se quiser a coleta exatamente como o banner declara |
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

## 7. Desligar

Esvaziar `ANALYTICS_GA4_ID` e rodar `php artisan optimize:clear && php artisan optimize`.
A proxima pagina ja sai sem tag; quem estiver com a pagina aberta so para de
reportar no proximo carregamento (tirar o `<script>` do `<head>` nao desfaz o
que o gtag.js ja carregou - quem para o envio e o Consent Mode).
