# BadgeForge — Encerramento do track na Fase 9

Plano aprovado em 2026-11-05. Origem do grill:
`docs/survey/20261105_badgeforge_encerramento_surveyfacts.md`.
Registro resumido: `docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_encerramento_plansfacts.md`.

## Contexto

A verificação da cronologia do BadgeForge confirmou que **não existe fase
depois da F9**: `docs/SPEC-BADGEFORGE.md` §16 termina na F9 (entregue em
2026-11-05) e o roadmap global (`docs/SPEC.md` §9) termina na F7 (concluída em
2026-10-22). Não há F10 em ADR, plano, aceite ou backlog. O que resta é
trabalho **deferido**: presets, GET por ID, CSV de analytics por API, API de
admin, webhooks/bulk, viewer embutido, meta-schema, página de status, features
adiadas durante F3–F7, pendências operacionais e a dívida de `format:check` —
todo ele já registrado como fora de escopo pela F9.

O usuário decidiu **encerrar o track do BadgeForge na F9**, deixando o backlog
deferido documentado. A tarefa é documental: não há código novo.

## Decisões

1. **Não abrir F10** e **não criar ADR**: encerrar um track concluído é
   reversível, não surpreendente e não resulta de um trade-off novo (os três
   critérios de ADR não se aplicam).
2. Registrar o encerramento nos dois pontos que hoje declaram a fase atual:
   `docs/SPEC-BADGEFORGE.md` §16 e a seção BadgeForge do `CLAUDE.md`.
3. O backlog deferido **não** é promovido a fase: continua onde já está
   (ADR-090, aceite e relatório da F9, surveys e planos da fase), referenciado
   pelo fechamento.
4. **Sem mudança em `docs/SPEC.md`**: o §9 lista fases do produto (F0–F7), não
   do BadgeForge; a cronologia do BadgeForge sempre viveu no SPEC próprio.
5. Sem ADR e sem termo novo de glossário.

## Mudanças

| Arquivo | Mudança |
| --- | --- |
| `docs/SPEC-BADGEFORGE.md` §16 | Parágrafo após a linha da F9 declarando o track encerrado, sem F10, e apontando o backlog deferido para ADR-090, aceite e relatório da F9 |
| `CLAUDE.md` (seção BadgeForge) | "Fase atual" passa a declarar o track encerrado na F9 em 2026-11-05, sem fase seguinte |

Nada em `app/`, `routes/`, `tests/`, `lang/`, migrations ou
`docs/badgeforge/openapi.yaml` é tocado.

## Artefatos da sessão

- plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_encerramento.md`
- plansfacts: `docs/plans/feature_badgeforge-fase-1/20261105_badgeforge_encerramento_plansfacts.md`
- surveyfacts: `docs/survey/20261105_badgeforge_encerramento_surveyfacts.md`
- relatório: `docs/claude-code/reports/feature_badgeforge-fase-1/20261105_feature_badgeforge-fase-1_badgeforge_encerramento.md`

## Verificação

- `git diff -- docs/SPEC-BADGEFORGE.md CLAUDE.md` mostra só as linhas de
  encerramento.
- `grep -rn "F10" docs/ CLAUDE.md` encontra apenas o texto do encerramento
  dizendo que não há F10.
- `grep -n "Fase atual" CLAUDE.md` fica consistente com
  `docs/SPEC-BADGEFORGE.md` §16 e com o aceite da F9.
- Como não há código alterado, os gates da F9 (Pest 1.468/24.593, Vitest
  983/76, tipos, lint, build) continuam válidos; nenhum teste precisa ser
  repetido.
- `git status` revisado; **nenhum commit nem push**.

## Fora de escopo

Abrir F10 (produto, API/admin ou operacional), implementar qualquer item
deferido, pagar a dívida de `format:check` ou alterar o SPEC global.

## Regra final

Ao terminar, gerar os artefatos acima, revisar o diff e parar para revisão.
**Nenhum commit nem push.**
