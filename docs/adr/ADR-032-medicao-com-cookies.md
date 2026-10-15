# ADR-032-medicao-com-cookies
Status: Aceito
Data: 2026-10-15
Atualizado: -

## Decisao
**A medicao usa o GA4 padrao, com cookies, e o banner declara exatamente isso:
"paginas vistas e cookies de medicao, so depois do seu aceite". A frase que o
visitante le e uma afirmacao factual - nao um resumo de marketing -, e por isso
ela muda quando a medicao muda.**

- O que libera a medicao continua sendo o aceite (ADR-013, intacto): sem
  decisao, sem tag e sem requisicao ao Google. O que muda aqui e o que se diz ao
  visitante **depois** do aceite.
- O cookie `_ga` (identificador persistente do GA4) passa a ser consequencia
  esperada do aceite, e nao um efeito colateral a esconder. A frase anterior -
  "sem dado pessoal" em 5 idiomas - era falsa nesse ponto, e foi removida.
- Os sinais de publicidade vao desligados **no codigo**, e nao so pelo Consent
  Mode: `gtag('config', id, { allow_google_signals: false,
  allow_ad_personalization_signals: false })`. E o que sustenta o "Nada de
  anuncios" que o corpo do banner ja prometia.
- A Politica de Privacidade passa a citar o Google tambem como **medidor** (a
  secao de compartilhamento so o citava pelo login), com a data de revisao
  atualizada em `config/legal.php`.
- A area da conta e medida como o site - banner e tag sobem no `AccountLayout`.
  O **painel administrativo fica fora**: sem banner e sem tag, nem com aceite
  gravado.

## Por que
O banner dizia "Google Analytics: paginas vistas, **sem dado pessoal**" enquanto
a medicao, uma vez aceita, grava um cookie com identificador persistente. As
duas coisas nao podiam ser verdadeiras ao mesmo tempo, e a escolha era entre
mudar a frase ou mudar a medicao.

Mudar a medicao (GA4 sem armazenamento de cliente, so contagem agregada) foi
descartado: perde atribuicao de sessao e a leitura de "quais paginas ajudam", que
e justamente o que a medicao existe para responder. Um site com um unico tipo de
dado opcional - e cujo dono e quem decide - nao ganha muito em trocar um numero
por uma frase mais bonita.

Mudar a frase foi a escolha, e ela ja estava escrita do lado certo: a secao de
Cookies da Politica de Privacidade sempre descreveu "cookies de medicao
(Google Analytics), opcionais, so depois do aceite". O banner era o **outlier** -
o unico texto do site que prometia mais do que o site fazia. Depois desta
decisao, os dois falam a mesma lingua.

A promessa "Nada de anuncios" ganhou a mesma tratamento: ela dependia so do
Consent Mode (`ad_storage`, `ad_user_data` e `ad_personalization` em `denied`),
que e a negativa **do consentimento** - nao da configuracao da propriedade. Com
os dois sinais desligados no `config`, a promessa nao depende de o visitante
nunca aceitar nada.

## Consequencias
- Texto do banner em `consent.analytics_body`, nos 5 idiomas: pt_BR "Google
  Analytics: paginas vistas e cookies de medicao, so depois do seu aceite",
  pt_PT "..., so depois de aceitar", en "page views and measurement cookies,
  only after you accept", es "..., solo despues de que aceptes", fr "..., uniquement
  apres votre acceptation". As **chaves** nao mudaram - so o texto.
- A Politica de Privacidade cita o Google como medidor nos 5 idiomas, e
  `LEGAL_UPDATED_AT`/`config/legal.php` foram atualizados junto (mesma pratica do
  ADR-013).
- `AccountLayout.vue` passa a montar `ConsentBanner` e `AnalyticsScript`. O
  `AdminLayout.vue` continua sem os dois, e ha teste dos dois lados
  (`consent-layouts.test.ts`).
- O ADR-013 nao foi substituido: ele segue sendo a regra do "nada antes do
  aceite". A emenda e sobre **o que se declara**, e o texto dele foi ajustado
  no ponto que ficou defasado (a lista de layouts com banner).
- Quem ja aceitou antes desta mudanca continua aceito: o cookie `consent` nao
  mudou de nome nem de valor.

## Alternativas descartadas
- **GA4 com `client_storage: 'none'`** (sem cookie e sem identificador
  persistente): manteria a frase antiga verdadeira, mas perde atribuicao de
  sessao e a leitura de utilidade por pagina - a medicao ficaria pior para
  responder a unica pergunta que ela existe para responder.
- **Medicao cookieless de terceiro** (Plausible, Umami, Matomo): mais fiel ao
  texto antigo, mas troca de provedor, de hospedagem e de custo para resolver um
  problema que era de **redacao**, nao de coleta.
- **Deixar o texto como estava** e ligar o GA padrao: poria no ar uma afirmacao
  falsa logo acima do botao de aceitar - o pior lugar possivel para uma.
- **Medir o painel administrativo junto**: mediria o dono do site usando o
  proprio site, e poria o banner dentro de uma area interna. A conta e do
  visitante cadastrado e entra; o admin nao.
