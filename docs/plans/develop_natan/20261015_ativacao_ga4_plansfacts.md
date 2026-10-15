# Ativacao do GA4 e o que o banner declara sobre a medicao

Sessao de grill (skill `grill-with-docs`), pedido: "No div de aceitacao de
cookies para a LGPD mostra a mensagem: 'Medicao / Google Analytics: paginas
vistas, sem dado pessoal.' Como eu ativo o Google Analytics?". Branch
`develop_natan`. Tres rodadas de perguntas (**Q1 a Q11**), todas respondidas pelo
usuario. Fatos em `docs/survey/20261015_ativacao_ga4_surveyfacts.md`.

## Context

A pergunta tinha uma resposta curta que o repo nao dava em lugar nenhum: **o
carregamento ja existe** (F1, ADR-013) - banner, cookie `consent` de tres estados
resolvido no servidor, injecao do gtag no cliente com Consent Mode v2 em `denied`
por padrao. Ativar e preencher `ANALYTICS_GA4_ID`; nada de codigo de carregamento
a escrever.

O que faltava era: (a) **documentacao** - a chave nao estava no RUNBOOK, o
`.env.example` esta vazio por projeto e o `deploy.sh` nunca toca no `.env`, entao
ninguem descobre isso lendo o repo; (b) **veracidade** - o banner afirmava "sem
dado pessoal" em 5 idiomas enquanto o GA4 padrao grava o cookie `_ga` depois do
aceite, e o `gtag('config', id)` rodava sem opcao nenhuma, deixando os sinais de
publicidade ligados apesar do "Nada de anuncios" no corpo do banner; (c) uma
**lacuna na politica** - a secao "Com quem compartilhamos" citava o Google so pelo
login, sem dizer que a medicao envia dados a ele.

## Decisoes (tres rodadas)

| # | Decisao |
|---|---|
| Q1 | Implementar **e** documentar (nao so responder) |
| Q2 | A propriedade GA4 ainda **nao existe**: o usuario cria, o guia conduz |
| Q3 | GA4 **padrao, com cookies**, depois do aceite - e o texto do banner passa a declarar isso |
| Q4 | Medicao **so em producao**; local, teste e CI sem id |
| Q5 | Medir publico + acesso + **conta**; **admin fica fora** |
| Q6 | "So producao" garantida por **env**, sem gate de codigo |
| Q7 | Texto do banner: "paginas vistas e cookies de medicao, so depois do seu aceite" |
| Q8 | **ADR nova** (ADR-032) + termo no glossario |
| Q9 | Banner **tambem** na area da conta (sem botao de reabrir, como o AuthLayout) |
| Q10 | Passo a passo em **guia proprio** (`docs/guides/`) + link no RUNBOOK |
| Q11 | Politica de Privacidade cita o Google como medidor + bump da data |

## Arquivos

| Arquivo | Mudanca |
|---|---|
| `resources/js/lib/analytics.ts` | `NO_ADS` (`allow_google_signals`, `allow_ad_personalization_signals` em `false`) no `gtag('config', id, NO_ADS)` |
| `lang/{pt_BR,pt_PT,en,es,fr}/app.php` | `consent.analytics_body` sem "sem dado pessoal" (chaves inalteradas) |
| `lang/{pt_BR,pt_PT,en,es,fr}/legal.php` | secao `sharing` cita o Google medidor |
| `config/legal.php` | `LEGAL_UPDATED_AT` -> `2026-10-15` |
| `resources/js/layouts/account/AccountLayout.vue` | monta `ConsentBanner` + `AnalyticsScript` |
| `resources/js/__tests__/consent-banner.test.ts` | assercao das opcoes de anuncio no comando `config` |
| `resources/js/__tests__/consent-layouts.test.ts` | **novo**: conta mede (banner + tag so com aceite), admin nao injeta nada |
| `docs/guides/google-analytics.md` | **novo**: criar a propriedade, ligar, conferir, o que nao ligar, rotacao |
| `docs/adr/ADR-032-medicao-com-cookies.md` | **novo**: a frase do banner e afirmacao factual |
| `docs/adr/ADR-013-consentimento.md` | `Atualizado` + a lista de layouts com banner (estava defasada) |
| `docs/RUNBOOK.md` | `ANALYTICS_GA4_ID` no `.env` de producao, secao 9, rotacao (secao 7) e linha de diagnostico |
| `docs/glossario.md` | termo **Medicao** |
| `docs/SPEC.md` | secoes 8, 10 e 11 (a rotacao do GA4 sai da lista de pendencias) |
| `CHANGELOG.md`, `docs/acceptance/FASE-1.md` | entrada da tarefa e emenda da fase |

## Verificacao

1. `composer test` e `npm run test` verdes.
2. Local, com id de teste: aceitar injeta a tag uma vez; recusar nao injeta;
   `dataLayer[0]` e o `consent default` negado, antes de tudo.
3. Area da conta: banner aparece, tag so com aceite. Admin: nem banner nem tag.
4. HTML do servidor sem GA em nenhum estado - o `ConsentTest` ja afirma.
5. A conferencia com **id real** continua sendo do usuario (o procedimento agora
   esta escrito, secao 3 do guia).

## Riscos

- O id real ainda nao existe: a conferencia de ponta a ponta fica pendente e o
  unico jeito de fecha-la e criar a propriedade no Google.
- O guia recomenda desligar o **Enhanced measurement** (vem ligado e coleta
  scroll e cliques de saida, alem das "paginas vistas" que o banner declara) -
  e escolha de console, nao de codigo: se ficar ligado, a coleta passa a ser
  maior do que a frase.
- A area da conta passa a ser medida: quem ja tinha aceitado o banner passa a
  ser medido tambem nas telas internas, sem nova decisao (o cookie `consent` e
  do navegador e nao mudou).

## Pendencias

- O commit e o PR sao do usuario (nada e commitado pelo assistente).
- Criar a propriedade GA4 e preencher `ANALYTICS_GA4_ID` no `.env` de producao
  (secao 1 e 2 do guia).
