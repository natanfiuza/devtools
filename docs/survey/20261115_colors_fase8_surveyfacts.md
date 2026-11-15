# Survey — Colors Fase 8 (auditoria final e relatório de aceite)

Data: 2026-11-15
Branch: `feature/colors-fase-8`
Contrato: `docs/colors/PROMPT-Fase8-auditoria-final.md`
Base: `docs/colors/SPEC-COLORS.md` v1.5, ADR-091 a ADR-143

## Contexto da tarefa

As Fases 1 a 7 do grupo Colors estão implementadas. A F8 é o fechamento do
track: auditar a implementação inteira, montar a matriz requisito →
implementação → teste, classificar pendências por gravidade, corrigir só
bloqueantes e altos, provar o funcionamento no navegador e entregar aceite,
relatório e ADR. O usuário pediu explicitamente: **não fazer deploy nem push**;
o branch foi criado por ele; a fase para ao fim, sem avançar para outra.

## Decisões do grill

### Rodada 1

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Escopo da correção | Só achados **bloqueantes e altos**; médios/baixos viram tabela classificada no aceite e na ADR. |
| 2 | Flag em produção | `COLORS_COMMENTS_ENABLED` continua `false`; a F8 documenta como ligar e testa com a flag ligada só no ambiente descartável. |
| 3 | Formatação | `npm run format` no repositório inteiro (~267 arquivos), aceitando o diff, para deixar `format:check` verde. |
| 4 | Checklist manual | Executado pelo assistente com Playwright contra SQLite descartável em `%TEMP%`, com dados semeados, evidências salvas e banco apagado no fim; MySQL de desenvolvimento intocado. |

### Rodada 2

| # | Tema | Decisão |
| --- | --- | --- |
| 5 | Entregáveis | `docs/acceptance/COLORS-FASE-8.md` + relatório da fase + ADR-144 (auditoria, exportação/anonimização e itens deferidos), no molde da ADR-090/F9 do BadgeForge. |
| 6 | Banco local | SQLite dedicado e descartável, sem tocar no MySQL de desenvolvimento. |
| 7 | Branch | A branch nova já foi criada pelo usuário. |
| 8 | Pendências | Lista classificada na ADR-144 (e espelhada no aceite). |
| 9 | Exportação (ADR-028) | Ampliar `ExportUserData` com bloco `colors` — alto. |
| 10 | Anonimização | Apagar dados pessoais e relações de saída; manter paletas, comentários e denúncias com autoria `NULL` — alto. |
| 11 | Docs raiz | Incluir Colors em README, ARQUITETURA, CHANGELOG, SPEC e nova seção §11 do RUNBOOK; `docs/API.md` fica fora. |
| 12 | Teste do admin | Adicionar `data-test` em `CommentReports.vue` e criar `admin-comment-reports.test.ts`. |

### Rodada 3 — confirmação de fechamento (2026-11-15, após a F8)

| # | Tema | Decisão |
| --- | --- | --- |
| 13 | Fechamento da fase | F8 conta como concluída **como está**: as pendências médias P1–P6 seguem deferidas e registradas no aceite/ADR-144; nenhuma alta ou bloqueante aberta. |
| 14 | Flag em produção | `COLORS_COMMENTS_ENABLED` permanece `false` neste fechamento; ligar é decisão operacional futura (worker + chave de IA), documentada no RUNBOOK §11. |

Verificação de estado que embasou a rodada: F1–F7 estão merged em
`origin/main` (PRs #140 a #161); a F8 está implementada com gate completo verde,
porém **sem commit/PR** — o commit e o PR são do usuário, pelo gitpr; não existe
`PROMPT-Fase9` e o track termina na F8.

## Fatos levantados (verificados em código/doc)

### Direito do titular

- `app/Actions/Account/ExportUserData.php` exportava BadgeForge, AI, pagamentos
  e uso — **nenhum** dado de Colors, embora o ADR-028 dê esse direito e o
  BadgeForge já cumprisse. A F7 deixou o ponto explicitamente para a F8.
- `app/Actions/Account/AnonymizeUser.php` apagava preferências e mutes de
  Colors, mas deixava histórico, favoritos de cor, presets, favoritos de
  paleta, curtidas, follows de saída e tags ligados à conta anonimizada; e
  mantinha `user_id`/`resolved_by` em comentários e denúncias.
- FKs de comentários, curtidas e favoritos de paleta são `cascadeOnDelete`:
  apagar paleta na anonimização destruiria dados de outras contas.

### Defeitos altos encontrados na auditoria

- `resources/js/tools/palette-generator/components/ExtractionPanel.vue` tinha
  `formatBytes` local chamando `new Intl.NumberFormat(locale.value)` com o
  código de pasta do Laravel (`pt_BR`/`pt_PT`). Tag BCP 47 usa hífen, então o
  `Intl` lança `RangeError` e o painel inteiro da aba de extração deixa de
  renderizar. O helper compartilhado `resources/js/lib/bytes.ts` já converte
  `_` para `-`; o defeito era o duplicado local.
- `lang/*/notifications.php` usava `:actor`/`:palette` (sintaxe do Laravel) no
  bloco `kinds.*`, consumido pelo `t()` do vue-i18n, que interpola com
  `{nome}`. Resultado: `:actor` e `:palette` literais na tela. O teste da
  página fingia as traduções nessa sintaxe, por isso passava.

### Documentação e operação

- Não existia `docs/acceptance/COLORS-*`; a última ADR era a 143.
- `README.md`, `docs/ARQUITETURA.md`, `CHANGELOG.md` e `docs/SPEC.md` não
  citavam Colors; o `RUNBOOK.md` não tinha seção de operação do grupo.
- Linhas desatualizadas em `docs/colors/` (sino "inexistente", faixa de ADRs,
  cabeçalhos "Fora desta fase", SPEC com F8 em aberto).
- `npm run format:check` falhava em ~267 arquivos (pré-existente ao track).
- `CommentReports.vue` não tinha teste Vitest (só `CommentModeration.vue`).
- SPEC §2 documenta `/{locale}/tools/colors` como landing do grupo, mas não
  existe rota web para ela (as tools ficam em `.../html-color-picker` e
  `.../palette-generator`).

### Ambiente e mecânica verificados

- i18n: pastas do Laravel `pt_BR, pt_PT, en, es, fr`; APIs do navegador
  precisam de BCP 47 (`pt-BR`). Interpolação do vue-i18n é `{nome}`; a do
  Laravel é `:nome`.
- O cookie `locale` é HttpOnly; só o servidor o escreve (via rota localizada).
  Para trocar idioma no Playwright é preciso `context.addCookies`.
- SSR: `config/inertia.php` usa `INERTIA_SSR_ENABLED` e
  `http://127.0.0.1:13714`; com `public/hot` velho o Inertia aponta para um
  endpoint Vite morto e o SSR desliga em silêncio.
- `bootstrap/app.php` renderiza `Error` por `$exceptions->respond()` fora do
  pipeline, sem as props compartilhadas: 404 de rota do dashboard mostra chaves
  cruas de i18n.
- Admin audit: `admin_actions` grava `comment_report.resolved|dismissed` com
  meta `{id, comment_id, from, to}` — nunca o corpo do comentário.
- Fila: quatro jobs `ShouldQueueAfterCommit`; `MAIL_MAILER=log` grava o e-mail
  no idioma do destinatário sem o corpo.
- SQLite não implementa `lockForUpdate()` de fato e ignora `after()`; a cota 25
  é garantida por código + MySQL.

## Referências internas

- ADR-028 (exportação de dados), ADR-090 (aceite F9 BadgeForge como molde),
  ADR-139 a ADR-143 (F7), ADR-144 (esta auditoria).
- Evidências: `docs/claude-code/reports/feature_colors-fase-8/evidence/`.
