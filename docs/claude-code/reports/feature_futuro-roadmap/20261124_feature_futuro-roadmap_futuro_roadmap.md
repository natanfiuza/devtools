# Relatório — Programa Futuro: planejamento do roadmap (docs-only)

Data: 2026-11-24
Branch: `feature/futuro-roadmap`
Task: `futuro_roadmap`
Origem: `/grill-with-docs` com o pedido "Inicie o planejamento da seção Futuro em `docs/SPEC.md#L101`".

## 1. Contexto

A SPEC §5 "Futuro" era uma única linha com doze candidatas — nove tools e três frentes de produto —, sem
fase, escopo, porte ou prioridade, e o repositório não tinha documento de roadmap (a SPEC §9 fazia esse
papel de fato). O grill de 2026-11-24 (quatro rodadas, dezesseis decisões) fechou o modelo do programa;
esta sessão entrega **apenas documentos**. Nenhum código, manifesto, i18n, dependência, migration ou teste
muda.

## 2. Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Entregável | Roadmap versionado + emendas mínimas na SPEC; sem código |
| Critérios | Reuso de infra de cliente, demanda real/busca, custo só-cliente, sinergia e risco; porte só desempata; sem score numérico |
| Empacotamento | 5 ondas temáticas, 7 fases `FUTURO-FASE-N`, aceite em `docs/acceptance/FUTURO-FASE-N.md` |
| Onda 1 | Um conversor (YAML/JSON/TOML/XML) + um diff (texto/JSON) na mesma fase |
| Produto | F5 histórico, F6 API v2 e F7 anúncios **condicionadas** a grill de produto + ADR de entrada |
| Fonte de verdade | `docs/ROADMAP.md`; SPEC §5/§9/§10 só apontam |
| Dependências | O roadmap nunca escolhe biblioteca; cada fase decide no próprio ADR |
| URLs | Sempre plana `/{locale}/tools/{slug}`; sem `group`; sem manifesto `soon` antes da fase |
| Calendário | Sem datas; ordem + porte S/M/L |
| Ordem | Recomendação, não bloqueio; só F5–F7 têm gate real |
| ADR | ADR-179 registra o programa; fases usam 180+ |
| Protocolo | Escrito no ROADMAP (entrada, implementação, registro/i18n/SEO, gates, aceite, ADR/relatório, parada) |

## 3. Entregáveis

| # | Entregável | Arquivo | Situação |
| --- | --- | --- | --- |
| 1 | Levantamento e decisões do grill | `docs/survey/20261124_futuro_roadmap_surveyfacts.md` | criado |
| 2 | Plano de execução da sessão | `docs/plans/feature_futuro-roadmap/20261124_futuro_roadmap_plansfacts.md` | criado |
| 3 | Cópia do plano (regra do CLAUDE.md) | `docs/claude-code/plans/feature_futuro-roadmap/20261124_feature_futuro-roadmap_futuro_roadmap.md` | criado |
| 4 | Roadmap do programa, versão 1.0 | `docs/ROADMAP.md` (seções 1–7, 5 ondas, 7 fases, escopo por ferramenta, protocolo, gates de produto, manutenção, revisões) | criado |
| 5 | ADR de adoção | `docs/adr/ADR-179-futuro-programa.md` | criado |
| 6 | Emendas na SPEC | `docs/SPEC.md` §5 (aponta para o ROADMAP), §9 (bullet do programa), §10 (lista de documentos), §11.2 (três perguntas abertas) | 4 hunks aplicados |
| 7 | Glossário | `docs/glossario.md`: `Programa Futuro`, `Onda`, `Porte (S/M/L)` | 3 entradas |
| 8 | README | `README.md`: uma linha na lista de documentos | 1 linha |
| 9 | Relatório | este arquivo | criado |
| — | CHANGELOG | — | **sem entrada**: planejamento, não release (registrado na ADR-179) |

## 4. Conteúdo entregue no ROADMAP

- **§1 Propósito e escopo** — o que é e o que não é; regras herdadas (100% navegador, URL plana, sem
  `group`, sem `soon`, ordem recomendada, i18n nos cinco idiomas, sem commit pelo agente); fora do programa.
- **§2 Critérios** — tabela de cinco critérios qualitativos + porte como desempate.
- **§3 Ondas e fases** — 5 ondas; tabela `Fase | Onda | Escopo | Ferramentas/frente | Porte | Condição |
  Status` com F1 conversor+diff (L), F2 cron/cURL/`.env` (M), F3 DDL/seeder/EXPLAIN (L), F4 cheatsheets (S),
  F5 histórico (M), F6 API v2 (L), F7 anúncios (M); definição de S/M/L; escopo por ferramenta com Dentro,
  Fora, Reuso e "candidatas de dependência a avaliar no ADR da fase".
- **§4 Protocolo de fase** — entrada, implementação, registro/i18n/SEO, gates reais, aceite, ADR/relatório e
  parada.
- **§5 Fases de produto** — critérios de entrada obrigatórios (grill, ADR de entrada, §11.2 → §11.1, plano
  técnico) e perguntas deliberadamente abertas por fase; fase recusada fica `cancelada` sem renumeração.
- **§6 Manutenção** e **§7 Revisões** (`1.0 | 2026-11-24 | criação | ADR-179`).

## 5. Verificação

| Item | Resultado |
| --- | --- |
| Branch correta | `git branch --show-current` → `feature/futuro-roadmap` |
| Entrega docs-only | `git diff --name-only` → apenas `README.md`, `docs/SPEC.md`, `docs/glossario.md`; `git ls-files --others` → `docs/ROADMAP.md`, `docs/adr/ADR-179-futuro-programa.md`, os três artefatos da sessão | OK — nada em `app/`, `resources/`, `lang/`, `routes/`, `config/`, `tests/`, `package.json`, `composer.json` |
| F6 fora da entrega | trabalho da fase 6 do Layout Harmonizer está na branch `feature/layout-harmonizer-fase-6`, não nesta | não aparece no diff |
| `.gitpr/` fora da entrega | `?? .gitpr/reports/pr_desc/feature-layout-harmonizer-fase-6_20261123143057_PR_DESC.md` é arquivo pré-existente da outra branch; não foi tocado nem incluído | intocado |
| ADR-179 único | `ls docs/adr` com filtro `ADR-179*` devolve exatamente `ADR-179-futuro-programa.md` | OK |
| Links | ROADMAP → `./SPEC.md` e `./adr/ADR-179-futuro-programa.md`; README e SPEC → `docs/ROADMAP.md`; os três alvos existem | OK |
| Consistência | F1–F7 presentes; 9 tools + 3 frentes = 12; portes S/M/L definidos; `FUTURO-FASE-N.md`; slugs planos; sem `group`; sem `soon` criado; nenhuma biblioteca escolhida (só "candidata a avaliar") | OK |
| SPEC §11.2 | três bullets acrescentados, cada um qualificado com "do Programa Futuro" e "fechar no grill de produto e no ADR de entrada" | OK |
| Gates de código | não exigidos (nenhum PHP/TS alterado; `docs` está no `.prettierignore`); não rodados | — |

## 6. Riscos e pendências

- **Drift ROADMAP ↔ SPEC**: contido por desenho — a SPEC fica em três linhas apontando e a §6 do ROADMAP
  define o que só muda por ADR.
- **Número de ADR**: 179 estava livre no início e no fim da sessão; se outra branch tomar o número antes do
  merge, renumerar e atualizar as quatro referências (ROADMAP, SPEC, glossário, este relatório).
- **Aceites e ADRs das fases ainda não existem**: citados como padrão de caminho, nunca como link.
- **Pendência de produto (intencional)**: F5–F7 têm só critérios, guardrails e perguntas — nada de modelo,
  retenção, preço ou posição de anúncio antecipado.
- **Frase da SPEC §8 sobre sinais de anúncio** e a Política de Privacidade permanecem como estão; a F7
  revisita as duas quando começar.

## 7. Parada

Entregável concluído e verificado. **Sem commit, push ou deploy** — o usuário revisa e commita pelo gitpr.
Nenhuma fase do Programa Futuro foi iniciada nesta sessão.
