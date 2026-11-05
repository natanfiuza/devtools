# Survey — BadgeForge: encerramento do track na Fase 9

Data: 2026-11-05
Branch: `feature/badgeforge-fase-1`
Origem: `/grill-with-docs` — "Verifique se existe mais alguma fase, se houver
pode iniciar"

## Contexto

A F9 (API de gestão completa) foi entregue na sessão anterior. A pergunta era
se existe uma fase seguinte e, se existir, iniciá-la.

## Fatos levantados no repositório

- **`docs/SPEC-BADGEFORGE.md` §16** (linhas 187–195): F1 a F9, todas com data
  de entrega; a última é `F9 ... Entregue em 2026-11-05` com aceite e
  ADR-090. Não há F10.
- **`docs/SPEC.md` §9**: fases do produto F0–F7, todas concluídas; a última
  data é 2026-10-22. O BadgeForge não aparece nessa lista — a cronologia dele
  vive no SPEC próprio.
- **ADR-090** e o aceite da F9 listam explicitamente o que ficou fora:
  presets na API, GET por ID, CSV de analytics por API, API de admin,
  webhooks/bulk, viewer embutido, meta-schema, página de status, docs
  pública/i18n e pendentes operacionais (smoke Mercado Pago, checklist de VPS,
  rotação de chaves, `TRUSTED_PROXIES`).
- **`CLAUDE.md`** declarava "Fase atual: F9 — API de gestão completa
  (entregue)".
- **`docs/SPEC.md` §11.2** mantém pendências operacionais (PHP 8.3/cron na
  VPS, rotação de SMTP/Turnstile/DeepSeek) e a pendência de DROP de
  `usage_ledger.ai_request_id`; nenhuma é fase do BadgeForge.
- O resultado da busca por "F10" em docs/ADR/planos/aceites não encontrou
  definição de fase nova, apenas a ausência.

## Conclusão do levantamento

Não existe fase após a F9. O que resta é **backlog deferido** e trabalho
operacional, ambos já documentados. Não há escopo de produto pendente que
justifique abrir uma F10.

## Decisão do usuário

> "Encerrar na F9 (Recomendado)"

Encerrar o track do BadgeForge na F9, registrar o fechamento em
`docs/SPEC-BADGEFORGE.md` e `CLAUDE.md`, deixar o backlog deferido
documentado e parar para revisão — sem código novo, sem commit/push.

## Rodadas e decisões

| # | Decisão |
|---|---|
| Q1 | Fechar o track na F9 em vez de inventar uma F10 ou iniciar trabalho avulso |
| Q2 | Registrar o encerramento no SPEC do BadgeForge e no `CLAUDE.md` |
| Q3 | Backlog deferido continua referenciado (ADR-090/aceite/relatório), não promovido a fase |
| Q4 | Sem ADR (decisão reversível e sem trade-off novo) e sem mudança no SPEC global |

## Riscos

- Deixar "Fase atual" apontando para F9 sem dizer que o track acabou induz uma
  nova sessão a procurar a fase seguinte; o texto novo elimina a ambiguidade.
- O SPEC global continua terminando na F7; qualquer trabalho futuro fora do
  BadgeForge precisa de uma definição nova no `docs/SPEC.md`, não de uma F10
  no SPEC do BadgeForge.

## Fora de escopo

Abrir F10, implementar backlog deferido, pendências operacionais e a dívida
de `format:check`.
