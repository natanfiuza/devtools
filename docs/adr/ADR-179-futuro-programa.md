# ADR-179 — Programa Futuro: roadmap e protocolo de fase
Data: 2026-11-24
Status: Aceito

## Contexto

A SPEC §5 "Futuro" era uma linha com doze candidatas — nove tools e três frentes de produto —, sem fase,
escopo, porte ou prioridade. O repositório não tinha documento de roadmap; a SPEC §9 fazia esse papel de
fato. O registro de tools já suporta o status `soon` (home com selo, Cmd+K sem navegação, rota 404), mas
todos os 15 manifestos são `available` e nada apontava para as candidatas.

O repositório tem precedentes de track faseado — `docs/SPEC-BADGEFORGE.md` (fases §16, encerramento §17,
ADRs 062–090) e `docs/colors/` (`SPEC-COLORS.md`, aceites por fase, ADRs 091–178) —, mas o Programa Futuro é
**cross-tool**: não tem um contrato central nem um produto único, então um track com spec própria
distorceria o modelo. As decisões deste ADR foram fechadas no grill de 2026-11-24 (quatro rodadas,
registradas em `docs/survey/20261124_futuro_roadmap_surveyfacts.md`); nenhuma reabre decisão de produto.

## Decisão

### Fonte única

`docs/ROADMAP.md` é a fonte de verdade do Programa Futuro, com versão e data no cabeçalho. A SPEC §5
"Futuro" fica reduzida a três linhas apontando para ele; §9 ganha um bullet do programa e §10 inclui o
documento na lista. O roadmap não duplica detalhe de implementação: cada fase tem prompt, plano e aceite
próprios.

### Critérios

A ordem segue, nesta prioridade: reuso de infra de cliente, demanda real/busca, custo só-cliente,
sinergia e risco. O porte (S/M/L) só desempata. Não há score numérico nem pesos; a evidência de demanda
entra no survey da fase.

### Ondas e fases

Cinco ondas temáticas e sete fases:

| Fase | Onda | Escopo | Porte |
| --- | --- | --- | --- |
| F1 | Conversão e comparação | conversor YAML/JSON/TOML/XML + diff texto/JSON | L |
| F2 | CLI e ambiente | cron explainer, cURL → código, gerador de `.env.example` | M |
| F3 | Banco e schema | DDL → migration, factory/seeder, explicador de EXPLAIN | L |
| F4 | Referência | cheatsheets | S |
| F5 | Produto | histórico compartilhável | M |
| F6 | Produto | API pública v2 | L |
| F7 | Produto | anúncios | M |

Não há datas. A ordem é recomendação, não bloqueio: qualquer fase pode começar antes se fizer sentido; só
F5–F7 têm gate de entrada. O aceite de cada fase fica em `docs/acceptance/FUTURO-FASE-N.md`.

### Fases de produto

F5–F7 só começam com (1) grill de produto registrado, (2) ADR de entrada cobrindo modelo de produto,
retenção/privacidade, planos e preço, métrica de sucesso, rollout/rollback e impacto legal, e (3) as
perguntas da SPEC §11.2 respondidas e movidas para §11.1. O roadmap traz apenas critérios, guardrails e
perguntas — sem detalhe técnico. Se o grill disser não, a fase fica cancelada e o ID não é reutilizado.

### Dependências

O roadmap nunca escolhe biblioteca. Cada fase que precisar de dependência decide no próprio ADR, registrando
peso no bundle, alternativa nativa e manutenção. As notas por ferramenta do roadmap são apenas candidatas a
avaliar (por exemplo: XML usa `DOMParser` nativo; o diff pode ser escrito à mão; YAML/TOML provavelmente
exigem biblioteca; o parser de cron pode ser próprio).

### Registro e URL

Toda tool nova usa URL plana `/{locale}/tools/{slug}`, sem `group`. Nenhum manifesto `soon` é criado agora:
a tool entra no registro, com ícone, categoria, i18n e OG, quando a fase começa.

### Protocolo

O roadmap §4 define o protocolo de fase: entrada (prompt, surveyfacts, plansfacts, cópia do plano),
implementação com reuso e sem dependência sem ADR, registro/i18n/SEO nos cinco idiomas, gates reais
(`composer test`, `npm run test`, `types:check`, `format:check`, `lint:check`, `build`), aceite
`FUTURO-FASE-N.md`, ADR quando houver decisão, relatório detalhado e parada sem commit/push/deploy.

### Artefatos

Levantamento e plano desta sessão em `docs/survey/20261124_futuro_roadmap_surveyfacts.md` e
`docs/plans/feature_futuro-roadmap/20261124_futuro_roadmap_plansfacts.md`, com cópia em
`docs/claude-code/plans/`. As fases usam a partir de 180 os números de ADR livres no dia.

## Consequências

- Nenhum código, manifesto, i18n, dependência, migration ou teste muda com este ADR; o catálogo continua com
  15 tools `available` e os testes de registro não são tocados até uma fase começar.
- O `CHANGELOG.md` não recebe entrada: isto é planejamento, não release; SPEC, ROADMAP e ADR são o registro.
- O risco de drift entre SPEC e roadmap fica contido: a SPEC aponta e não repete escopo, porte ou
  não-objetivos.
- Reordenar ou mudar escopo interno é barato (emenda datada no roadmap); mudar critérios, URL plana, a
  regra de dependência ou os gates de produto exige ADR que emende ou substitua esta decisão.
- A ADR-179 não agenda datas e não antecipa decisões de produto.

## Alternativas descartadas

- **Manter a lista só na SPEC**: era o estado atual, sem fase, escopo ou prioridade.
- **Fase única "Futuro"**: misturaria nove tools e três produtos com riscos e decisões incompatíveis.
- **Criar os doze manifestos `soon` agora**: poluiria home, Cmd+K e sitemap com promessas sem spec e mudaria
  os testes que hoje afirmam zero `soon`.
- **`docs/SPEC-FUTURO.md` com doze specs**: especificação sem demanda comprovada, e o programa não é um
  track com contrato central.
- **Escolher as bibliotecas no roadmap**: prenderia decisões de implementação antes de conhecer as
  restrições de cada fase.
- **Cronograma com datas**: envelhece no primeiro atraso e o porte já informa o tamanho relativo.
- **Tracker externo como fonte**: o repositório já versiona planos, surveys e ADRs; uma fonte fora do git
  se desincroniza.
