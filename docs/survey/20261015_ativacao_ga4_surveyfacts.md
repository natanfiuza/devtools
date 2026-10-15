# Levantamento - Ativacao do GA4 e o que o banner declara sobre a medicao

Data: 2026-10-15
Branch: develop_natan
Pedido original: "No div de aceitacao de cookies para a LGPD mostra a mensagem:
'Medicao / Google Analytics: paginas vistas, sem dado pessoal.' Como eu ativo o
Google Analytics?"
Plano: `docs/plans/develop_natan/20261015_ativacao_ga4_plansfacts.md`
Relatorio: `docs/claude-code/reports/develop_natan/20261015_develop_natan_ativacao_ga4.md`

## 1. Estado do repositorio no inicio (levantado, nao assumido)

Branch `develop_natan`, HEAD `abc1742` ("fix: corrige comando git pull no script
de deploy"), arvore limpa fora de um `.gitpr/reports/pr_desc/` nao rastreado.
Laravel 13 + Inertia v3 (SSR) + Vue 3 + TypeScript estrito, i18n em 5 idiomas
(`pt_BR`, `pt_PT`, `en`, `es`, `fr`), Pest + Vitest, MariaDB. F1 aceita em
2026-10-14 (`docs/acceptance/FASE-1.md`), com uma revisao independente no mesmo
dia e 30 ADRs escritos (o ultimo, ADR-031).

## 2. O objeto explorado: o ciclo de consentimento e o GA4

**Tudo o que "ativar o Google Analytics" precisa ja existe desde a F1**, por
decisao do ADR-013:

| Peca | Onde |
|---|---|
| Id do stream e nome/validade do cookie | `config/analytics.php` (`ANALYTICS_GA4_ID`, `ANALYTICS_CONSENT_DAYS`) |
| Leitura da decisao no servidor | `app/Support/Consent.php`, `app/Support/Analytics.php` |
| Apagar cookie corrompido na resposta | `app/Http/Middleware/HandleConsent.php` |
| Prop compartilhada `analytics` | `app/Http/Middleware/HandleInertiaRequests.php` |
| Injecao da tag (cliente, uma vez) | `resources/js/lib/analytics.ts` + `components/AnalyticsScript.vue` |
| Banner e estado | `components/ConsentBanner.vue` + `composables/useConsent.ts` |
| Montagem | `layouts/PublicLayout.vue:269-270` (site) e `layouts/AuthLayout.vue` |
| Cookie `consent` fora da criptografia | `bootstrap/app.php` (`encryptCookies(except:)`) |

O que **nao** existe e o que estava errado:

1. **`ANALYTICS_GA4_ID` nao esta no `.env` local**, esta vazio no `.env.example`
   por projeto e o `deploy/deploy.sh` **nunca toca no `.env`** (ele faz
   `git pull`, cache, migrate, sem escrever variavel). Nao havia como descobrir a
   chave lendo o repositorio: ela nao aparecia no `RUNBOOK.md` e a rotacao do GA4
   era item aberto na SPEC 11.2.
2. **`gtag('config', id)` rodava sem opcao nenhuma** - os sinais de publicidade
   do GA4 ficavam no padrao (ligados) apesar de o corpo do banner prometer "Nada
   de anuncios" e a Politica de Privacidade prometer "Nada de cookie de
   publicidade".
3. **O banner afirmava "sem dado pessoal"** nos 5 idiomas, mas o GA4 padrao grava
   o `_ga` (identificador persistente de cliente) depois do aceite.
4. **A Politica de Privacidade citava o Google so pelo login**: a secao "Com quem
   compartilhamos" nao dizia que a medicao tambem envia dado ao Google.
5. **`AccountLayout.vue` nao montava nem o banner nem a tag**: quem entrava na
   area da conta nao via banner e nao era medido - "todas as paginas" nao valia.
   O `AdminLayout.vue` tambem nao monta, e essa ausencia e correta.

A Politica de Privacidade **ja descrevia a medicao corretamente** (cookies de
medicao opcionais, so depois do aceite, revogaveis pelo rodape): o banner era o
outlier, nao o contrario.

## 3. Fatos que mudaram a decisao

| Fato | Efeito |
|---|---|
| O carregamento ja existe (F1) | A tarefa deixou de ser "implementar GA" e virou "documentar a ativacao + corrigir o que a medicao declara" |
| A chave nao esta em doc nenhuma e o deploy nao escreve `.env` | Ativar virou tarefa de **documentacao** (guia + RUNBOOK), nao de codigo |
| O banner dizia "sem dado pessoal" enquanto o GA4 grava `_ga` | O texto passa a declarar "paginas vistas e cookies de medicao, so depois do seu aceite" |
| `gtag('config', id)` sem opcoes | Entram `allow_google_signals: false` e `allow_ad_personalization_signals: false` |
| A politica so citava o Google no login | A secao de compartilhamento passa a citar o Google como medidor, com bump da data |
| `AccountLayout` sem banner nem tag | O par banner + tag passa a subir na conta; o admin fica fora |
| A conferencia manual da F1 segue pendente | Continua pendente (depende do id real), mas agora tem procedimento escrito |

## 4. Metodo

Tres exploracoes paralelas (sub-agentes) mais leitura direta: (a) o ciclo de
consentimento no PHP e no Vue, (b) i18n e textos legais nos 5 idiomas,
(c) layouts e testes. Leitura direta de `resources/js/lib/analytics.ts`,
`lang/*/app.php`, `lang/*/legal.php`, `config/legal.php`,
`resources/js/layouts/account/AccountLayout.vue` e
`resources/js/layouts/admin/AdminLayout.vue`, `docs/RUNBOOK.md`,
`docs/adr/ADR-013-consentimento.md`, `docs/glossario.md`, `docs/SPEC.md`,
`CHANGELOG.md` e `docs/acceptance/FASE-1.md`. Os testes existentes
(`consent-banner.test.ts`, `admin-panel.test.ts`, `account-panel.test.ts`) foram
lidos como molde antes de escrever o teste novo.

## 5. Decisoes das rodadas

| # | Pergunta | Resposta |
|---|---|---|
| Q1 | O que voce quer que eu faca com isso | Implementar **e** documentar |
| Q2 | A propriedade GA4 ja existe | Nao - o usuario cria, com o guia conduzindo |
| Q3 | Que medicao ligar | GA4 **padrao, com cookies**, depois do aceite (texto do banner ajustado) |
| Q4 | Onde a medicao roda | **So producao**: local, teste e CI sem id |
| Q5 | Quais areas medir | Publico + acesso + **conta**; **admin fica fora** |
| Q6 | Como garantir "so producao" | Pela **env**, sem gate de codigo |
| Q7 | O que o banner deve dizer | "paginas vistas e cookies de medicao, so depois do seu aceite" |
| Q8 | Registrar a decisao | **ADR nova** (ADR-032) + termo no glossario |
| Q9 | Banner na area da conta | **Sim**, banner tambem na conta (sem botao de reabrir, como o AuthLayout) |
| Q10 | Onde fica o passo a passo | **Guia proprio** em `docs/guides/`, ligado do RUNBOOK |
| Q11 | Politica de Privacidade | Citar o Google como medidor + bump da data de revisao |
