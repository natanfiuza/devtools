# Auditoria da F1 e fechamento das pendencias encontradas

Sessao de grill (skill `grill-with-docs`), pedido: "verifique se a fase 1 foi
toda executada docs/acceptance/FASE-1.md". Branch `develop_natan`. O plano foi
aprovado pelo usuario (as 7 perguntas da fronteira, todas "Recomendado") antes
da execucao.

## Context

A F1 esta commitada (`1f00431`) e o `docs/acceptance/FASE-1.md` diz "OK" nos 15
criterios. O pedido e uma **verificacao independente** do auto-relato. Quatro
auditorias de leitura em paralelo + execucao real dos gates mostraram: 12
criterios verificados, o estado "todas `soon`" superado pela F2 por desenho,
**um defeito real de consentimento**, o gate do Vitest **nao deterministico**,
o smoke SSR sem reverificacao e uma perda acidental na SPEC (itens 3 a 8 do
MVP) causada pelo diff (nao commitado) da F2. Levantamento completo em
`docs/survey/20261014_auditoria_fase1_surveyfacts.md`.

**Resultado esperado**: F1 com as pendencias fechadas (defeito corrigido, gate
deterministico, docs corrigidos), com os gates verdes e o smoke reverificado,
sem commit (regra do CLAUDE.md).

## Decisoes confirmadas pelo usuario (todas "Recomendado")

| # | Decisao |
|---|---|
| 1 | Corrigir a re-aceitacao do consentimento agora, com teste proprio |
| 2 | Timeout explicito no teste de import pesado do registro (reavaliar `pool: 'vmThreads'` depois, se preciso) |
| 3 | Restaurar os itens 3 a 8 do MVP na SPEC secao 5 |
| 4 | Adendo datado "Revisao independente" no `FASE-1.md`, sem reescrever a tabela historica |
| 5 | Conferencia manual do GA com id real no `.env` local (usuario executa) |
| 6 | Reverificar o smoke SSR de forma independente |
| 7 | Menores: `Esc` vira i18n; contrato de `hidden` documentado (sem codigo); sem ADR retroativo; sem backfill de survey |

## Blocos de execucao

| # | Bloco | Estado |
|---|---|---|
| 0 | Auditoria (4 agentes + gates) e rodada de perguntas | concluido |
| 1 | `lib/analytics.ts`: reafirmar `consent update granted` quando a tag ja existe | concluido |
| 2 | `consent-banner.test.ts`: teste da sequencia aceitar -> recusar -> aceitar | concluido |
| 3 | `tools-registry.test.ts`: timeout explicito (20s) no teste de import sob demanda | concluido |
| 4 | `docs/SPEC.md`: restaurar os itens 3 a 8 do MVP | concluido |
| 5 | `Esc` -> `app.search.key_escape` nos 5 idiomas + `CommandPalette.vue` | concluido |
| 6 | Contrato de `hidden` no ADR-007 e na SPEC secao 4 | concluido |
| 7 | `CHANGELOG.md`: secao "Corrigido" da F2 e a frase do bloco da F1 sobre o nome das tools (o smoke mostrou que tres nomes sao traduzidos) | concluido |
| 8 | Gates: `composer test`, `npm run test` 3x, `types:check`, `build`, `build:ssr` | concluido |
| 9 | Smoke SSR reverificado (SQLite descartavel + SSR) | concluido |
| 10 | Adendo no `FASE-1.md`, survey, plano e relatorio | concluido |

## Arquivos criticos

- `resources/js/lib/analytics.ts` - correcao (o early-return da injecao unica
  passa a reafirmar o `granted` no Consent Mode).
- `resources/js/__tests__/consent-banner.test.ts` - teste novo.
- `resources/js/__tests__/tools-registry.test.ts` - timeout explicito.
- `lang/{en,es,fr,pt_BR,pt_PT}/app.php` + `resources/js/components/CommandPalette.vue`
  - `app.search.key_escape` (`Échap` em frances).
- `docs/SPEC.md` (secao 5 restaurada; contrato de `hidden` na secao 4),
  `docs/adr/ADR-007-registro-de-tools.md`, `CHANGELOG.md`.
- `docs/acceptance/FASE-1.md` - adendo "Revisao independente (2026-10-14)".

## Riscos / pontos de atencao

- O defeito do criterio 6 **falhava para o lado seguro** (nao media), mas
  contradizia o ADR-013 e o interruptor da tela: a correcao so religa o que a
  propria decisao do visitante pediu.
- O gate do Vitest nao era defeito do codigo testado, e sim timeout de import
  sob carga - por isso o conserto e o timeout explicito, nao afrouxar a suite.
- **Nenhum commit**: o usuario commita via gitpr. Lembrar que
  `tests/Fixtures/tools/hidden-tool/` esta untracked e precisa entrar no commit.
- A conferencia manual do GA (devtools, id real) fica com o usuario: em teste e
  em dev nao ha id, entao a medicao fica desligada de proposito.
