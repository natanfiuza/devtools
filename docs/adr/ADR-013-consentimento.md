# ADR-013-consentimento
Status: Aceito
Data: 2026-10-05
Atualizado: 2026-10-14 (banner, cookie e injecao do GA definidos; a parte de exclusao e exportacao de conta passou para o ADR-028)

## Decisao
**Nada de medicao antes do aceite, e o aceite e uma decisao de tres estados
resolvida no servidor. Sem cookie a decisao e nula, o banner aparece e nenhuma
tag do Google e emitida - nem no HTML do servidor nem no SSR.**

- O banner tem duas categorias: **necessarios** (sessao, idioma e tema - sem
  interruptor, porque sem eles o site nao funciona) e **medicao** (Google
  Analytics, opcional). Dois botoes de primeira classe - Aceitar e Recusar -
  mais o link da Politica de Privacidade.
- A decisao vive num cookie (`consent`, 180 dias por padrao), com dois valores
  possiveis: `granted` e `denied`. Nome e validade saem de
  `config/analytics.php` e sao a **fonte unica dos dois lados**: o servidor le a
  decisao dele e manda o nome nas props compartilhadas para o cliente gravar
  exatamente o mesmo cookie.
- Quem decide cada request e o **servidor** (`App\Support\Consent`), que manda
  `analytics.{ga4Id, consent, cookieName, cookieDays}` nas props compartilhadas.
  Assim quem ja respondeu nao ve o banner piscar - mesmo padrao do tema
  (ADR-021).
- Cookie com valor fora dos dois aceitos - antigo, editado a mao ou truncado -
  volta a decisao **nula**, e o middleware `HandleConsent` apaga o lixo do
  navegador na resposta (sem isso ele voltaria a cada visita).
- A tag do GA4 e injetada **so no cliente**, **uma vez**, e **so** com
  `consent === granted` e id configurado (`resources/js/lib/analytics.ts`, com
  `AnalyticsScript.vue`). Antes de qualquer tag entra
  `Consent Mode v2` com `denied` por padrao.
- Id vazio em `ANALYTICS_GA4_ID` significa **medicao desligada**: nem com
  aceite, nada e injetado. E assim que o ambiente de testes e o de
  desenvolvimento ficam sem medicao.
- Depois de decidir, dá para reabrir o banner pelo botao **Cookies** no rodape
  publico, e mudar a resposta.
- Exclusao e exportacao da conta **nao** estao mais neste ADR: sao o ADR-028.

## Por que
O banner e obrigacao legal (LGPD/GDPR) e a medicao e a unica coisa que este site
faz com dado de quem nao tem conta - entao ela e a que mais precisa de regra
clara. A regra e simples de dizer e dificil de fatiar: **sem aceite, nenhuma
requisicao sai para o Google**. Nao e "Consent Mode denied com a tag carregada"
- e a tag que nao existe, porque um `denied` ainda manda o ping, e uma recusa
tem que significar nada.

O que a decisao no servidor resolve e o que o cliente sozinho nao resolve: quem
respondeu ontem nao pode ver o banner aparecer por um instante enquanto o
JavaScript le o cookie. O servidor ja sabe a resposta quando monta o HTML - a
mesma razao pela qual o tema tambem vem resolvido de la.

E o estado nulo e o que faz o banner existir sem uma segunda fonte de verdade:
em vez de uma flag "banner fechado", **nao ter cookie** e o que significa "ainda
nao decidiu". Um cookie corrompido cai no mesmo lugar - e o lugar seguro.

## Consequencias
- `config/analytics.php` guarda `ga4_id` (`ANALYTICS_GA4_ID`), o nome do cookie
  e a validade (`ANALYTICS_CONSENT_DAYS`, 180). Nada de id, nome ou prazo
  escrito no codigo.
- O texto da Politica de Privacidade passou a descrever a medicao como ela e -
  cookies de medicao opcionais, recusaveis e revogaveis pelo rodape -, e a data
  de revisao (`config/legal.php`) foi atualizada junto.
- O banner aparece nos dois layouts publicos (site e telas de acesso) e nao
  bloqueia a pagina: fica no canto inferior.
- O GA nao e testavel de ponta a ponta daqui (nao ha id real em teste): a
  cobertura e por teste de componente (aceitar grava o cookie e injeta a tag uma
  unica vez; recusar nao injeta; reabrir pelo rodape volta a mostrar) e pela
  prop que o servidor manda. A conferencia com id real e manual, no devtools do
  navegador.
- A decisao e do **navegador**, nao da conta: quem troca de dispositivo decide de
  novo. Nao ha registro de consentimento no banco.
- So duas categorias. Novidade que precise de consentimento proprio (embed de
  terceiro, por exemplo) entra como terceira categoria do mesmo banner, com a
  mesma resolucao no servidor.

## Alternativas descartadas
- **Decidir so no cliente** (ler o cookie no JavaScript): o banner piscaria para
  quem ja tinha respondido, e o SSR nao teria o que renderizar.
- **Carregar o GA sempre com `Consent Mode v2` em `denied`**: e o modo
  recomendado pelo Google, mas ele ainda manda requisicao. Recusar tem que
  significar nenhuma requisicao - a diferenca e o que este ADR protege.
- **Um banner com "aceitar todos" e um link escondido para recusar** (o padrao
  que a regulacao europeia persegue): os dois botoes tem o mesmo peso, e o de
  recusar nao custa mais cliques.
- **Categoria de "preferencias" agora**: nao ha nada para configurar - idioma e
  tema sao essenciais e trocaveis pelo proprio site. Categoria sem conteudo e
  ruido.
- **Injetar a tag no `app.blade.php`**: colocaria a tag no HTML do servidor e no
  SSR, que e exatamente onde ela nao pode estar, e faria a medicao depender do
  render do servidor em vez do aceite.
- **Sem cookie e sem banner, so uma pagina de configuracoes**: ninguem visita
  uma pagina de cookies para descobrir que existe medicao.
