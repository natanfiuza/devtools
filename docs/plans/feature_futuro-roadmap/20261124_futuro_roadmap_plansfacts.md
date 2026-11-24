# Plano — Programa Futuro (planejamento do roadmap)

- Data: 2026-11-24
- Branch: `feature/futuro-roadmap`
- Origem: grill de 2026-11-24 (4 rodadas) — decisões em
  `docs/survey/20261124_futuro_roadmap_surveyfacts.md`
- Entrega: **docs-only** (roadmap, emendas na SPEC, ADR-179, glossário, README); sem código, sem commit.

## Decisões fechadas

| Tema | Decisão |
| --- | --- |
| Entregável | Roadmap + SPEC mínima; sem implementação |
| Critérios | Reuso de infra, demanda, custo client-only, sinergia, risco; porte desempata |
| Ondas/fases | 5 ondas, 7 fases `FUTURO-FASE-N`, aceite `docs/acceptance/FUTURO-FASE-N.md` |
| Fonte de verdade | `docs/ROADMAP.md`; SPEC §5/§9/§10 apontam |
| Produto | F5 histórico, F6 API v2, F7 anúncios: grill + ADR de entrada |
| Dependências | Só no ADR da fase; roadmap nunca escolhe lib |
| URLs | Planas `/{locale}/tools/{slug}`; sem `group`; sem `soon` |
| Calendário | Sem datas; ordem + porte S/M/L |
| ADR | `ADR-179` para o programa; fases 180+ |
| Protocolo | Fase inteira descrita no ROADMAP §4 |

## Entregáveis

1. `docs/ROADMAP.md` — cabeçalho (versão 1.0, 2026-11-24); §1 propósito/escopo; §2 critérios; §3 ondas,
   tabela de fases e escopo por ferramenta (com não-objetivos e slugs provisórios); §4 protocolo de fase;
   §5 fases de produto e critérios de entrada; §6 manutenção; §7 revisões.
2. `docs/adr/ADR-179-futuro-programa.md` — contexto, decisão (fonte única, critérios, ondas/fases, produto,
   dependências, registro/URL, protocolo, artefatos), consequências, alternativas descartadas.
3. `docs/SPEC.md` — §5 Futuro substituído por 3 linhas com link; bullet do programa na §9; `docs/ROADMAP.md`
   na lista da §10; três perguntas abertas na §11.2 (F5–F7). §11.1 intocada.
4. `docs/glossario.md` — `Programa Futuro`, `Onda`, `Porte (S/M/L)`, no estilo sem acentos do arquivo.
5. `README.md` — uma linha na lista de documentos.
6. Artefatos: surveyfacts, este plansfacts, cópia em `docs/claude-code/plans/feature_futuro-roadmap/`,
   relatório em `docs/claude-code/reports/feature_futuro-roadmap/`. Sem CHANGELOG.

## Etapas

| Etapa | Conteúdo | Verificação |
| --- | --- | --- |
| 0 | Salvar surveyfacts/plansfacts/cópia do plano | caminhos exatos |
| 1 | `docs/ROADMAP.md` | seções 1–7, 7 fases, 12 candidatos, sem datas/libs |
| 2 | `ADR-179` | número livre, formato da casa |
| 3 | SPEC (§5, §9, §10, §11.2), glossário, README | 4 hunks; §11.1 intacta |
| 4 | Relatório | matriz entregável → arquivo → situação |
| 5 | Parada | sem commit/push/deploy |

## Verificação

- `git diff --name-only` só com docs (SPEC, ROADMAP, ADR-179, glossário, README) e artefatos de sessão.
- `git status --short` sem `.gitpr/` nem arquivos da F6.
- `ls docs/adr | grep '^ADR-179'` exatamente um arquivo (rechecar no fim).
- Links: ROADMAP → ADR-179 e SPEC; SPEC → ROADMAP; acceptance/ADRs futuros como padrão, não link.
- Consistência: F1–F7, 9 tools + 3 frentes = 12, S/M/L, `FUTURO-FASE-N.md`, slug plano, sem `group`,
  sem `soon`, nenhuma dependência escolhida.
- Gates de código não exigidos (nenhum PHP/TS; docs no `.prettierignore`).

## Riscos

Drift SPEC ↔ ROADMAP (SPEC mínima); ADR-179 tomado por outra branch (rechecar e renumerar);
terminologia "fase" ambígua (sempre "fase do Programa Futuro"); links para arquivos futuros (citar como
padrão); frase de anúncios da §8 revisada só na F7.

## Parada

Relatório escrito ao fim; sem commit, push ou deploy; nenhuma fase do programa iniciada.
