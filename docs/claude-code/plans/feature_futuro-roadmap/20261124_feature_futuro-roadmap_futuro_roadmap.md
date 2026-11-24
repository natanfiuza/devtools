# Programa Futuro — planejamento do roadmap (docs-only)

## Context

`docs/SPEC.md` §5 (linha 101) lista doze candidatos a tools/frentes sem fase, escopo ou prioridade, e o
resto do repositório não tem nenhum documento de roadmap — a SPEC §9 faz esse papel de fato. O registro de
tools (`resources/js/tools/registry.ts`, `app/Support/Tools.php`) já suporta `soon`, mas os 15 manifestos são
todos `available` e não há plano para as candidatas. Esta sessão fecha o grill de 2026-11-24 (rodadas 1–4)
e entrega **apenas documentos**: um roadmap versionado, emendas mínimas na SPEC, o ADR de adoção e os
artefatos da sessão.

Sem commit, push ou deploy (o usuário commita pelo gitpr). Nenhum código, manifesto, i18n, dependência,
migration ou teste muda nesta entrega.

Branch atual: `feature/futuro-roadmap` (criada pelo usuário). Data: 2026-11-24.

## Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Entregável | Roadmap + atualização mínima da SPEC; **sem código** |
| Critérios | Reuso de infra client-side, demanda real/busca, custo só-cliente, sinergia, risco; porte só desempata. Sem score numérico |
| Empacotamento | 5 ondas temáticas; **7 fases** `FUTURO-FASE-N`; aceite em `docs/acceptance/FUTURO-FASE-N.md` |
| Onda 1 | Um conversor (YAML/JSON/TOML/XML, entrada/saída selecionáveis) + um Diff (modo texto/JSON) |
| Produto | F5 histórico, F6 API v2, F7 anúncios: **condicionadas** a grill de produto + ADR de entrada |
| Fonte de verdade | `docs/ROADMAP.md`; SPEC §5/§9/§10 só apontam |
| Dependências | O roadmap não escolhe biblioteca; cada fase decide no próprio ADR (peso, alternativa nativa, manutenção) |
| URLs | Sempre plano `/{locale}/tools/{slug}`; sem `group`; sem manifesto `soon` até a fase começar |
| Calendário | Sem datas; ordem + porte S/M/L |
| Ordem | Recomendação, não bloqueio; só F5–F7 têm gate real |
| ADR | `ADR-179` registra o programa; fases usam 180+ quando trouxerem decisão própria |
| Protocolo | Escrito no ROADMAP (prompt, aceite, gates, i18n 5 idiomas, registro/SEO, ADR, relatório, parada) |

## Fatos que sustentam o roadmap

- Infra reutilizável: `components/tool/ToolShell.vue`, `CodeEditor.vue`, `FileDropzone.vue`;
  `lib/worker/client.ts` + `serve.ts`; `lib/file.ts` (limites de `config/tools.php`), `download.ts`,
  `clipboard.ts`; `composables/useCopyFeedback`, `useFileError`, `useSplitPane`.
- `share-text` já é server-side e é o precedente de histórico compartilhável (rota
  `tools/share-text/{id}` reusa `ToolController`).
- Registrar tool exige: manifesto, `Tool.vue`, ícone em `resources/js/tools/icons.ts`, categoria em
  `types.ts`/`categories.ts`, chaves `tools.<slug>.name/description/keywords/intro` nos 5 idiomas, slug
  pinado em `tests/Feature/Tools/ToolsRegistryTest.php`, OG via `php artisan seo:og-image` (ADR-030),
  `ToolProps.php` só se houver servidor (ADR-043).
- XML tem `DOMParser` nativo; diff pode ser escrito à mão; YAML/TOML provavelmente exigem lib; cron pode
  ser parser próprio. Tudo isso fica como "candidata a avaliar" — nunca decidido no roadmap.
- A SPEC §8 já afirma "sinais de anúncio desligados no config (ADR-032)" — F7 terá de revisitar essa frase
  e a Política de Privacidade; registrar como pergunta da fase, nunca alterar agora.
- ADR mais alto: **178**. `docs` está no `.prettierignore` (sem impacto no `format:check`).

## Entregáveis

### 1. `docs/ROADMAP.md` (novo)

Cabeçalho com versão 1.0, data 2026-11-24 e nota "fonte de verdade da SPEC §5 Futuro (ADR-179); sem datas,
ordem + porte". Seções:

1. **Propósito e escopo** — o que é (9 tools + 3 frentes de produto), o que não é (spec de implementação,
   cronograma, escolha de dependência); relação com SPEC, aceites e ADRs; regras herdadas em uma linha:
   URL plana, sem `group`, sem `soon`, tool registra na fase, ordem recomendada, F5–F7 condicionadas;
   fora do programa (execução de código no servidor, nova capacidade de IA, app mobile, conta/plano novo).
2. **Critérios de priorização** — tabela `# | Critério | Pergunta que responde | Efeito na ordem`;
   critérios qualitativos, sem pesos; porte só desempata.
3. **Ondas e fases**
   - §3.1 Ondas: 1 Conversão e comparação (F1); 2 CLI e ambiente (F2); 3 Banco e schema (F3);
     4 Referência (F4); 5 Produto (F5–F7). Uma linha de por que estão juntas.
   - §3.2 Tabela de fases `Fase | Onda | Escopo | Ferramentas/frente | Porte | Condição | Status`:
     F1 conversor+diff (L); F2 cron/cURL/`.env` (M); F3 DDL/seeder/EXPLAIN (L); F4 cheatsheets (S);
     F5 histórico (M, grill+ADR); F6 API v2 (L, grill+ADR); F7 anúncios (M, grill+ADR). Definir S/M/L.
   - §3.3 Escopo por ferramenta: propósito em 2–4 linhas, `Dentro`, `Fora`, `Porte`, `Reuso`,
     `Candidatas de dependência a avaliar no ADR da fase`. Slugs provisórios (`data-format-converter`,
     `diff`, `cron-explainer`, `curl-to-code`, `env-example`, `ddl-to-migration`, `schema-mock-data`,
     `sql-explain`, `cheatsheets`); o manifesto congela o slug no início da fase.
     Não-objetivos mínimos: conversor não busca URL remota nem faz upload; diff não persiste, não aplica
     patch, sem 3-way; nada executa comando/requisição; cron não cobre systemd timers; DDL não conecta em
     banco nem cobre views/triggers/procedures/grants; EXPLAIN só plano colado; cheatsheets sem conteúdo
     do usuário; F5–F7 sem detalhe técnico.
4. **Protocolo de fase** — §4.1 entrada (prompt, surveyfacts, plansfacts, cópia em `docs/claude-code/plans`);
   §4.2 implementação (reuso, dependência só com ADR da fase); §4.3 registro/i18n/SEO (manifesto,
   `Tool.vue`, ícone/categoria, 5 idiomas, slug pinado, OG, sitemap, `ToolProps` só com servidor, URL plana);
   §4.4 gates (`composer test`, `npm run test`, `types:check`, `format:check`, `lint:check`, `build`);
   §4.5 aceite (`FUTURO-FASE-N.md`: escopo, fora de escopo, decisões, arquivos, matriz, gates, checklist
   i18n/SEO/registro, pendências, evidências, status); §4.6 ADR + relatório; §4.7 parada sem commit/push.
5. **Fases de produto F5–F7** — critérios de entrada duros: (1) grill de produto registrado; (2) ADR de
   entrada cobrindo modelo de produto, retenção/privacidade, planos/preço, métrica de sucesso,
   rollout/rollback e impacto legal; (3) perguntas da SPEC §11.2 respondidas e movidas para §11.1;
   (4) só então plano técnico. Se o grill disser não, a fase é marcada `cancelada`; nunca renumera.
6. **Manutenção do roadmap** — reordenar = emenda datada em §7, sem ADR; mudança de escopo = emenda datada
   na subseção da fase; fase concluída = status + link do aceite, sem apagar escopo; novo candidato entra
   numa onda ou no backlog com porte; decisões fixas (critérios, URL plana, sem lib no roadmap, gates de
   produto) só mudam por ADR que emende/supersede a 179.
7. **Revisões** — tabela `Versão | Data | Mudança | ADR`: `1.0 | 2026-11-24 | criação | ADR-179`.

### 2. `docs/adr/ADR-179-futuro-programa.md` (novo)

Formato da casa (como ADR-178): `# ADR-179 — Programa Futuro: roadmap e protocolo de fase`, `Data`,
`Status: Aceito`, `## Contexto`, `## Decisão` (subseções: Fonte única; Critérios; Ondas e fases; Fases de
produto; Dependências; Registro e URL; Protocolo; Artefatos), `## Consequências`,
`## Alternativas descartadas` (manter a lista só na SPEC; fase única "Futuro"; 12 manifestos `soon` agora;
`SPEC-FUTURO.md` com 12 specs; escolher libs agora; cronograma/Gantt; tracker externo como fonte).
Registrar que não há entrada no CHANGELOG (planejamento, não release) e que o catálogo continua com 15
tools `available`.

### 3. `docs/SPEC.md` — quatro trechos

- **§5 Futuro (linha ~101-102)**: substituir a lista de 12 itens por 3 linhas apontando para
  `docs/ROADMAP.md` (ADR-179), critérios/ondas/porte/protocolo, ordem como recomendação, F5–F7 com grill
  de produto + ADR de entrada e nenhum manifesto antes da fase.
- **§9 Fases (depois da linha ~237)**: um bullet `Programa Futuro (F1 a F7, sem datas)` com link para o
  ROADMAP, ADR-179, gate F5–F7 e padrão de aceite `docs/acceptance/FUTURO-FASE-N.md`.
- **§10 Documentação a manter (linha ~242)**: inserir `docs/ROADMAP.md (Programa Futuro)` na lista.
- **§11.2 Abertas (linha ~300)**: três bullets — histórico compartilhável (o que o link mostra, público ou
  só conta, retenção, opt-in, exportação/exclusão), API v2 (escopo/abilities, v1 coexiste ou é depreciada,
  rates, página pública de docs) e anúncios (formato/posição, assinante sem anúncio, consentimento/LGPD e
  revisão da frase da §8 + Política de Privacidade), todas "fechar no grill + ADR de entrada da fase".

### 4. `docs/glossario.md` — 3 entradas curtas

`Programa Futuro`, `Onda`, `Porte (S/M/L)` — só porque o ROADMAP introduz os termos.

### 5. `README.md` — uma linha

Incluir `docs/ROADMAP.md` na lista de documentos, se a lista existir (verificar antes; edição cosmética de
uma linha, sem reescrever o README).

### 6. Artefatos da sessão

- `docs/survey/20261124_futuro_roadmap_surveyfacts.md` — contexto, fatos das 3 explorações, 4 rodadas e
  decisões.
- `docs/plans/feature_futuro-roadmap/20261124_futuro_roadmap_plansfacts.md` — plano de execução salvo pela
  skill.
- Cópia em `docs/claude-code/plans/feature_futuro-roadmap/20261124_feature_futuro-roadmap_futuro_roadmap.md`
  (regra do CLAUDE.md; `/` da branch vira `_`).
- Relatório final em
  `docs/claude-code/reports/feature_futuro-roadmap/20261124_feature_futuro-roadmap_futuro_roadmap.md`.
- **Sem entrada no CHANGELOG** — não há mudança de produto.

## Etapas

| Etapa | Conteúdo | Verificação |
| --- | --- | --- |
| 0 | Salvar surveyfacts, plansfacts e a cópia do plano | arquivos existem nos caminhos exatos |
| 1 | Escrever `docs/ROADMAP.md` completo | seções 1–7, 7 fases, 12 candidatos cobertos, sem datas/libs |
| 2 | Escrever ADR-179 | número livre, formato da casa, alternativas descartadas |
| 3 | Emendas na SPEC (§5, §9, §10, §11.2), glossário e README | 4 hunks na SPEC; nada em §11.1 |
| 4 | Relatório detalhado | matriz entregável → arquivo → situação |
| 5 | Parada | sem commit/push/deploy |

## Verificação (docs-only)

1. `git diff --name-only` só contém `docs/SPEC.md`, `docs/ROADMAP.md`, `docs/adr/ADR-179-*`,
   `docs/glossario.md`, `README.md` (se editado) e os artefatos de survey/plan/report — **nada** em
   `resources/`, `lang/`, `app/`, `routes/`, `config/`, `tests/`, `package.json`, `composer.json`.
2. `git status --short` não inclui `.gitpr/` nem o relatório da F6; nada disso entra na entrega.
3. `ls docs/adr | grep '^ADR-179'` devolve exatamente um arquivo; repetir antes de terminar (outra branch
   pode ter tomado o número — se tomado, usar o próximo livre e atualizar as 4 referências).
4. Links: ROADMAP → `./adr/ADR-179-futuro-programa.md` e `./SPEC.md`; SPEC → `./ROADMAP.md`. Aceites e
   ADRs futuros citados como padrão/inline code, nunca como link quebrado.
5. Consistência: F1–F7, 9 tools + 3 frentes = 12, portes S/M/L, `FUTURO-FASE-N.md`, slug plano, sem
   `group`, sem `soon`, nenhuma dependência escolhida (só "candidata a avaliar").
6. Terminologia: nunca "F5" solto onde a fase 5 do site (AI SQL Builder) poderia ser confundida — sempre
   "fase do Programa Futuro" ou "F5 do programa".
7. Gates de código não são exigidos (nenhum PHP/TS alterado; `docs` está no `.prettierignore`). Se rodados,
   sem mudança esperada.

## Riscos

- **Drift ROADMAP ↔ SPEC**: SPEC fica em 3 linhas; detalhe só no ROADMAP, com regra explícita na §6.
- **Número de ADR**: rechecar 179 no início e no fim.
- **Links quebrados**: acceptance/ADRs futuros não existem — citar como padrão.
- **Vazamento de produto**: F5–F7 só com critérios, perguntas e guardrails.
- **Frase de privacidade**: §8 menciona sinais de anúncio desligados; F7 a revisita — não tocar agora.

## Parada

Ao fim: relatório escrito, **sem commit, push ou deploy**; aguardar revisão do usuário. Nenhuma fase do
Programa Futuro é iniciada nesta sessão.
