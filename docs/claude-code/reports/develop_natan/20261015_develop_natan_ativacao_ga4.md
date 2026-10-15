# Relatorio - ativacao do GA4, o que o banner declara e a medicao da conta

Branch: `develop_natan`
Data: 2026-10-15
Tarefa: "No div de aceitacao de cookies para a LGPD mostra a mensagem: 'Medicao /
Google Analytics: paginas vistas, sem dado pessoal.' Como eu ativo o Google
Analytics?"
Plano: `docs/plans/develop_natan/20261015_ativacao_ga4_plansfacts.md`
Levantamento: `docs/survey/20261015_ativacao_ga4_surveyfacts.md`

**Nada foi commitado pelo assistente.** As alteracoes estao no working tree; o
commit e o PR ficam com o usuario, pelo gitpr (CLAUDE.md).

## 1. O que foi pedido

A pergunta tinha uma resposta curta: **o carregamento ja existe**. A F1
(ADR-013) entregou o ciclo inteiro - banner de duas categorias, cookie `consent`
de tres estados resolvido no **servidor**, injecao do gtag no **cliente** com
Consent Mode v2 em `denied` por padrao, reabertura pelo rodape, i18n nos 5
idiomas. Ativar e preencher `ANALYTICS_GA4_ID` (`config/analytics.php`); nao ha
codigo de carregamento a escrever.

O que a sessao virou, depois do levantamento, foi outra coisa: **tornar a
ativacao descobrivel e a frase do banner verdadeira**. Duas entregas:

1. Documentar a ativacao ponta a ponta (a chave nao aparecia em doc nenhuma).
2. Corrigir o que a medicao **declara** (o banner dizia "sem dado pessoal"; o
   `config` nao desligava os sinais de anuncio; a politica nao citava o Google
   como medidor) e estender a medicao a area da conta.

## 2. O que o levantamento achou

| Achado | Efeito |
|---|---|
| O ciclo de consentimento e de injecao ja existe inteiro (F1) | A tarefa nao era implementar GA, era documentar + corrigir o que se declara |
| `ANALYTICS_GA4_ID` nao esta no `.env` local, esta vazio no `.env.example` por projeto, o `deploy.sh` **nunca escreve** `.env` e o RUNBOOK nao listava a chave (item aberto da SPEC 11.2) | Ativar virou tarefa de **documentacao**: guia novo + RUNBOOK |
| O banner afirmava "sem dado pessoal" nos 5 idiomas, mas o GA4 padrao grava o `_ga` | O texto passa a declarar "paginas vistas e **cookies de medicao**, so depois do seu aceite" |
| `gtag('config', id)` rodava sem opcao nenhuma, com o corpo do banner prometendo "Nada de anuncios" e a politica prometendo "Nada de cookie de publicidade" | Entram `allow_google_signals: false` e `allow_ad_personalization_signals: false` |
| A Politica de Privacidade citava o Google so pelo **login** | A secao "Com quem compartilhamos" passa a cita-lo como **medidor**, com bump da data |
| `AccountLayout.vue` nao montava nem banner nem tag (o `AdminLayout` tambem nao, e essa ausencia esta certa) | O par banner + tag sobe na conta; o admin fica fora |

A Politica de Privacidade **ja** descrevia a medicao corretamente (cookies de
medicao opcionais, so depois do aceite, revogaveis pelo rodape) - o banner era o
outlier, nao o contrario.

## 3. Metodo

- **Tres rodadas de grill** (Q1 a Q11), com a fronteira recalculada depois de
  cada resposta. Todos os fatos foram levantados por mim, antes de perguntar.
- **Tres exploracoes paralelas** (sub-agentes) mais leitura direta: o ciclo no
  PHP e no Vue, os textos de i18n e legais nos 5 idiomas, e os layouts e testes.
- A premissa da primeira rodada ("a frase pode ser promessa sem mecanismo")
  estava **errada** e foi corrigida no meio do caminho: o mecanismo existe desde
  a F1. As rodadas 2 e 3 giraram em cima das lacunas reais (docs, veracidade do
  texto, area da conta).

## 4. O que mudou

### 4.1 Codigo

- **`resources/js/lib/analytics.ts`**: constante `NO_ADS` (`allow_google_signals`
  e `allow_ad_personalization_signals` em `false`) passada ao
  `gtag('config', id, NO_ADS)`. O Consent Mode ja nega publicidade nos dois
  estados; isto e o cinto alem do suspensorio - a promessa do banner deixa de
  depender so do consentimento.
- **`resources/js/layouts/account/AccountLayout.vue`**: monta `<ConsentBanner />`
  e `<AnalyticsScript />` depois do `</AppContent>`, com o docblock explicando
  que a conta e medida como o site e que o admin fica fora (ADR-032).
- **`resources/js/layouts/admin/AdminLayout.vue`**: **intocado** - nem banner nem
  tag, nem com aceite.
- **`lang/{pt_BR,pt_PT,en,es,fr}/app.php`**: `consent.analytics_body` deixa de
  dizer "sem dado pessoal" e passa a declarar os cookies de medicao. **As chaves
  nao mudaram**, entao o teste de paridade segue verde sem edicao.
- **`lang/{pt_BR,pt_PT,en,es,fr}/legal.php`**: `privacy.sections.sharing.body`
  ganha a frase do Google como medidor (pt_BR: "Se voce aceitar a medicao, as
  paginas vistas tambem vao para o Google Analytics.").
- **`config/legal.php`**: `LEGAL_UPDATED_AT` -> `2026-10-15` (aparece no topo das
  paginas legais).

Nada mudou no servidor: a prop compartilhada `analytics` ja ia em todos os
requests e o cookie `consent` e global.

### 4.2 Testes

- **`resources/js/__tests__/consent-banner.test.ts`**: o teste "aceitar grava o
  cookie..." passa a afirmar tambem as duas opcoes de anuncio no comando `config`
  do `dataLayer`.
- **`resources/js/__tests__/consent-layouts.test.ts`** (**novo**, 4 testes):
  monta o `AccountLayout` e o `AdminLayout` com uma pagina Inertia falsa reativa.
  Conta mostra o banner e **nao** injeta tag sem decisao; conta injeta a tag
  depois do aceite (`consent=granted`, uma tag com o id configurado); conta nao
  injeta nada com `consent: 'denied'`; admin nao tem banner e nao injeta nada
  **nem** com `consent: 'granted'`. Usa traducao marcadora, como
  `account-panel.test.ts` e `admin-panel.test.ts`.

Os testes de servidor (`ConsentTest`) nao precisaram de mudanca: eles afirmam a
ausencia de `googletagmanager`/`gtag` no HTML em todos os estados, e nenhuma
chave de traducao mudou.

### 4.3 Documentacao

- **`docs/guides/google-analytics.md`** (**novo**, pasta nova): criar a
  propriedade GA4 e o data stream no console (com o aviso de **nao** colar o
  bloco `gtag.js` que o Google oferece) -> `ANALYTICS_GA4_ID` no `.env` de
  producao -> `php artisan optimize:clear && php artisan optimize` -> conferir
  no devtools -> **o que nao ligar** (Google Signals, personalizacao de anuncios,
  Enhanced measurement, User-ID, retencao em 14 meses) -> rotacao do id -> mapa
  de onde isso vive no codigo -> como desligar.
- **`docs/adr/ADR-032-medicao-com-cookies.md`** (**novo**): a decisao de que a
  frase do banner e **afirmacao factual** - GA4 padrao com cookie `_ga` depois do
  aceite, sinais de anuncio desligados tambem no `config`, conta medida, admin
  fora, Google declarado na politica. O ADR-013 (nada antes do aceite) nao mudou;
  mudou o que se diz ao visitante.
- **`docs/adr/ADR-013-consentimento.md`**: linha `Atualizado` e a lista de
  layouts com banner (que estava **defasada** - dizia so site e telas de acesso).
- **`docs/RUNBOOK.md`**: `ANALYTICS_GA4_ID` no bloco de `.env` de producao, a
  rotacao na secao 7 apontando para o guia, uma linha na tabela de diagnostico
  ("medicao nao aparece no GA") e a **secao 9. Medicao (Google Analytics)**.
- **`docs/glossario.md`**: termo **Medicao**.
- **`docs/SPEC.md`**: secoes 8 (a frase do banner e afirmacao factual), 10
  (pasta `docs/guides/`) e 11 (a rotacao do GA4 sai da lista de pendencias e vira
  item decidido).
- **`CHANGELOG.md`**: entrada da tarefa.
- **`docs/acceptance/FASE-1.md`**: emenda de 2026-10-15 - os criterios 5 a 7 desta
  fase seguem validos, a cobertura nova e o `consent-layouts.test.ts`, e a
  conferencia manual continua **pendente** (agora com procedimento escrito).

## 5. Decisoes (tabela)

| # | Pergunta | Resposta |
|---|---|---|
| Q1 | O que fazer com isso | Implementar **e** documentar |
| Q2 | A propriedade GA4 ja existe? | Nao - o usuario cria, com o guia conduzindo |
| Q3 | Que medicao ligar | GA4 **padrao, com cookies**, depois do aceite |
| Q4 | Onde a medicao roda | **So producao** (local, teste e CI sem id) |
| Q5 | Quais areas medir | Publico + acesso + **conta**; admin fora |
| Q6 | Como garantir "so producao" | Pela **env**, sem gate de codigo |
| Q7 | O que o banner deve dizer | "paginas vistas e cookies de medicao, so depois do seu aceite" |
| Q8 | Registrar a decisao | **ADR nova** (ADR-032) + termo no glossario |
| Q9 | Banner na area da conta | **Sim** (sem botao de reabrir, como o AuthLayout) |
| Q10 | Onde fica o passo a passo | **Guia proprio** em `docs/guides/`, ligado do RUNBOOK |
| Q11 | Politica de Privacidade | Citar o Google como medidor + bump da data |

## 6. Verificacao

- `npx vitest run` nos dois arquivos de consentimento: **14 testes** verdes.
- `npm run test`: **47 arquivos, 663 testes** verdes.
- `composer test`: Pint sem violacao, PHPStan nivel 7 **0 erros**, Pest **268
  testes / 7.166 assercoes** verdes.
- Conferido por leitura: o `dataLayer[0]` continua sendo o `consent default` com
  os quatro sinais `denied`, antes de qualquer outro comando.

O que **nao** foi verificado aqui: a medicao com um **id real** de ponta a ponta
(nao existe propriedade GA4 ainda) - e a conferencia manual que ja estava
pendente na F1 e que agora tem procedimento escrito, secao 3 do guia. Ela inclui
a sequencia "aceitar -> recusar -> aceitar", que foi o defeito corrigido na
auditoria da F1.

## 7. Arquivos no working tree

```
 M CHANGELOG.md
 M config/legal.php
 M docs/RUNBOOK.md
 M docs/SPEC.md
 M docs/acceptance/FASE-1.md
 M docs/adr/ADR-013-consentimento.md
 M docs/glossario.md
 M lang/en/app.php
 M lang/en/legal.php
 M lang/es/app.php
 M lang/es/legal.php
 M lang/fr/app.php
 M lang/fr/legal.php
 M lang/pt_BR/app.php
 M lang/pt_BR/legal.php
 M lang/pt_PT/app.php
 M lang/pt_PT/legal.php
 M resources/js/__tests__/consent-banner.test.ts
 M resources/js/layouts/account/AccountLayout.vue
 M resources/js/lib/analytics.ts
?? docs/adr/ADR-032-medicao-com-cookies.md
?? docs/guides/google-analytics.md
?? docs/plans/develop_natan/20261015_ativacao_ga4_plansfacts.md
?? docs/survey/20261015_ativacao_ga4_surveyfacts.md
?? resources/js/__tests__/consent-layouts.test.ts
```

(`.gitpr/reports/pr_desc/develop_natan_20261015154259_PR_DESC.md` tambem esta
untracked, mas e de tarefa anterior e nao faz parte desta.)

## 8. Notas de percurso

- **A premissa da primeira rodada estava errada.** Eu abri o grill supondo que a
  frase do banner pudesse ser promessa sem mecanismo; a exploracao mostrou o
  contrario - tudo implementado desde a F1. Isso foi dito ao usuario na hora e
  refez as rodadas 2 e 3 em cima das lacunas reais.
- Os avisos do IDE na `docs/SPEC.md` (`MD032`, lista sem linha em branco antes) e
  no `CHANGELOG.md` (`MD024`, varios `### Adicionado`) foram conferidos e sao do
  **estilo ja existente dos arquivos** (o CHANGELOG segue Keep a Changelog, que
  repete os titulos a cada entrega). Nao foram "corrigidos": mudar so a secao
  nova deixaria o arquivo pior.
- Os 5 textos legais foram escritos respeitando o tom de cada arquivo (pt_PT usa
  "dados pessoais" no plural, en usa "Measurement") - nenhuma chave i18n mudou de
  nome, so o valor.

## 9. Pendente

- **O commit e o PR sao do usuario** (gitpr). O assistente nao commita.
- **Criar a propriedade GA4** no console e preencher `ANALYTICS_GA4_ID` no `.env`
  de producao (secoes 1 e 2 do guia) - acao do usuario.
- **Conferencia manual com id real**: devtools, rede e cookies, nos dois estados
  (secao 3 do guia).
