# Relatório — Layout Harmonizer F1 (motor puro)

Data: 2026-11-16
Branch: `feature/layout-harmonizer-fase-1`
Fase anterior: F0 (auditoria + plano, 2026-11-15)
Autorização: tabela candidata da F0 aprovada integralmente no grill da F1
("Aprovar e rodar a F1 inteira"), com a tabela virando `PARAMETROS.md` e o
motor entrando em código.

## 1. Escopo entregue

A F1 entregou o **motor puro** do Layout Harmonizer:

- catálogo de 33 tokens em cinco famílias;
- quatro estratégias (análoga, complementar, triádica, monocromática);
- dois temas gerados juntos;
- seed original e seed efetiva, com composição explícita de alpha;
- variantes separadas de hover/active;
- feedback independente da seed;
- mapeamento para sRGB e diagnóstico de gamut;
- pares de contraste classificados sem arredondar;
- versões de schema, algoritmo e parâmetros;
- fixtures nas quatro estratégias e nos dois temas.

Sem Vue, DOM, Inertia, i18n, storage, persistência ou exportadores. O motor
reusa `@/lib/color` (ADR-117) e não adiciona dependência.

## 2. Arquivos criados

### Motor — `resources/js/tools/layout-harmonizer/engine/`

| Arquivo | Conteúdo |
|---|---|
| `types.ts` | contrato do motor: temas, estratégias, catálogo, token, checks, seed, entrada/saída, erro tipado e versões |
| `params.ts` | tabela numérica aprovada, matizes de feedback, deslocamentos de estratégia e constantes |
| `tokens.ts` | catálogo de 33 tokens, famílias e os 16 pares de contraste |
| `strategies.ts` | deslocamentos de matiz e guarda de estratégia |
| `alpha.ts` | resolução da seed (original × efetiva), com fundo obrigatório para alpha |
| `contrast.ts` | medição dos pares e classificação AA/AAA |
| `generate.ts` | geração dos dois quadros e montagem de todos os tokens |
| `index.ts` | superfície pública do motor |

### Testes — `resources/js/__tests__/`

| Arquivo | Foco |
|---|---|
| `layout-harmonizer-engine.test.ts` | catálogo, determinismo, Primary exata, acromática, secundária manual, erros |
| `layout-harmonizer-strategies.test.ts` | +30, +180, +240/+120, monocromática e Accent em +120 |
| `layout-harmonizer-contrast.test.ts` | pares, estados, melhor conteúdo e classificação crua |
| `layout-harmonizer-gamut.test.ts` | seed fora do gamut, derivadas em sRGB, extremos preto/branco/cinza |
| `layout-harmonizer-alpha.test.ts` | composição sobre fundo escolhido, original preservado, fundo ignorado em seed opaca |
| `layout-harmonizer-fixtures.test.ts` | 80 snapshots (10 seeds × 4 estratégias × 2 temas) |
| `__snapshots__/layout-harmonizer-fixtures.test.ts.snap` | fixtures congeladas |

### Documentação

- `docs/colors/layout-harmonizer/PARAMETROS.md` (novo): tabela aprovada,
  constantes, regras derivadas, notas de calibração e versionamento.
- `docs/adr/ADR-145-colors-lh-identidade.md`
- `docs/adr/ADR-146-colors-lh-quadro-semantico.md`
- `docs/adr/ADR-147-colors-lh-geracao.md`
- `docs/adr/ADR-149-colors-lh-seed.md`
- `docs/adr/ADR-150-colors-lh-processo.md`
- `docs/adr/ADR-156-colors-lh-harmonias.md`
- `docs/adr/ADR-157-colors-lh-variantes.md`
- `docs/adr/ADR-158-colors-lh-contraste.md`
- `docs/adr/ADR-163-colors-lh-accent.md`
- `docs/adr/ADR-166-colors-lh-temas.md`
- `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md`: seção de status da
  transferência.
- `docs/colors/README.md`: seção do LH em F1, linha do pacote e nota dos ADRs.
- `docs/SPEC.md`: §5 (22 ADRs, 10 já em `docs/adr/`) e §9 (F1 concluída).

Os 10 ADRs foram copiados com o mesmo conteúdo; só título e numeração
mudaram. Os **originais locais foram mantidos** até o aceite da F1, como
previsto no mapa de transferência.

## 3. Decisões de implementação

### Catálogo

33 tokens na ordem do TOKENS.md: 11 de marca, 4 de superfície, 4 de feedback,
10 de conteúdo e 4 de interação. `accent`, `accent-hover`, `accent-active` e
`on-accent` entram no catálogo em todas as estratégias; o que muda é a
derivação. O catálogo é o mesmo nos dois temas.

### Seed e Primary

- `seed.original` guarda a cor informada, inclusive alpha.
- `seed.effective` é sempre opaca; é a única usada na geração.
- `primary` é construída a partir do RGBA efetivo, sem gamut mapping, nos dois
  temas — igual à seed até o aceite explícito (ADR-149).
- Seed com alpha **exige** fundo; sem fundo o motor lança
  `LayoutEngineError('seed-alpha-without-background')`. Não há fundo padrão.
- Seed opaca ignora e não registra fundo informado.

### Acromática

Croma efetivo < 0.02: superfícies, textos de superfície, foco, secundária e
Accent caem para C 0 e H 0; feedback continua colorido. Nenhum matiz é
inventado (ADR-149).

### Marca, variante e estados

- `primary-variant`: L −0.10 (piso 0.30) no claro, L +0.10 (teto 0.85) no
  escuro, C × 0.90.
- `primary-hover` / `primary-active`: L −0.05 / −0.10 no claro e +0.05 / +0.10
  no escuro, C preservado.
- Variante e estados usam passos diferentes e a variante reduz croma; a
  distinção não é só de nome (ADR-157).

### Estratégias e Accent

- Análoga: secundária +30.
- Complementar: secundária +180.
- Triádica: secundária +240 e Accent +120.
- Monocromática: secundária com a matiz da seed.
- O Accent mantém +120 em todas as estratégias, para o catálogo não mudar de
  composição (ADR-163 + recomendação do TOKENS.md). Na triádica é uma cor da
  harmonia; nas demais, o mesmo papel de destaque.

### Feedback

Erro 25°, sucesso 145°, aviso 75° e informação 245°, com L/C da tabela por
tema. Não seguem a seed nem o tema por matiz.

### Conteúdo e contraste

- `on-background`, `on-surface` e `on-surface-variant` usam os tons explícitos
  da tabela.
- `on-primary`, `on-secondary`, `on-accent`, `on-error`, `on-success`,
  `on-warning` e `on-info` escolhem entre branco (L 1.0) e escuro (L 0.18) o
  candidato de maior razão sobre a cor base; empate fica com o branco.
- 16 pares medidos: cada `on-*` contra o fundo de mesmo nome e, nas famílias de
  marca e no Accent, também contra `-hover` e `-active`.
- A razão é crua; AA 4.5/3 e AAA 7/4.5 sem arredondamento prévio (ADR-158).

### Gamut

Toda derivada passa por `mapOklchToSrgb` (busca binária de croma, L e H
preservados). `clipped` marca o token cujo croma pedido não coube; a seed
efetiva nunca passa por mapeamento.

## 4. Regras derivadas da tabela

A tabela da F0 não listava individualmente todos os valores exigidos pelo
catálogo. Em vez de escolher números soltos, a F1 aplicou regras explícitas,
registradas em `PARAMETROS.md`:

1. `secondary-variant` usa a regra da variante de marca sobre a secundária.
2. `secondary-hover` / `secondary-active` usam os passos de estado sobre a
   secundária.
3. `accent-hover` / `accent-active` usam os passos de estado sobre o Accent.
4. `link` é a seed exata; estados seguem os passos de link da tabela.
5. Accent +120 em todas as estratégias (estabilidade do catálogo).
6. Matiz do `focus-ring` vem da seed; C 0 quando acromática.
7. Conteúdo `on-*` escolhido por maior razão; textos de superfície com tons
   próprios.
8. Pares de contraste incluem os estados de marca.
9. Seed acromática sem matiz inventado.

## 5. Testes executados e resultados reais

| Comando | Resultado |
|---|---|
| `npx vitest run resources/js/__tests__/layout-harmonizer` | 6 arquivos, **107 testes**, todos passando |
| `npm run test` (suíte inteira) | 134 arquivos, **1653 testes**, todos passando |
| `npm run types:check` | Wayfinder gerou actions/routes; `vue-tsc --noEmit` sem erros |
| `composer test` | Pint passou; PHPStan 0 erros; Pest **1737 testes**, 30326 asserções, todos passando |

Observações reais das fixtures:

- `on-primary` sobre `#3b82f6`: razão 5.11 com o escuro (`#121212`), escolhido
  em vez do branco (3.68).
- Estados podem reprovar AA: `on-primary` sobre `primary-hover` = 4.16 e sobre
  `primary-active` = 3.35 no claro. O motor reporta (`aaNormal=false`); não
  corrige. A F3 exibe o aviso.
- `surface` claro pede L 1.0 com C 0.003; o gamut reduz o croma ao branco
  (`#ffffff`, `clipped=true`). Comportamento esperado do mapeamento.
- `warning` no claro (`0.75 / 0.16`) também é cortado para `#e79d00`.

## 6. Pendências e pontos para revisão

1. **Comparação com o prompt empacotado da F1**: o
   `PROMPT-Fase1_B-implementar_motor_aprovado.md` cita "estrutura para
   overrides e bloqueios" e "sugestões separadas dos valores aplicados". O plano
   aprovado nesta sessão colocou `engine/overrides.ts` e
   `engine/suggestions.ts` na **F3**. A F1 seguiu o plano aprovado; essas
   estruturas não foram criadas agora. Se o prompt empacotado prevalecer, isso
   precisa de decisão antes da F2.
2. **Teto de croma da tabela da F0**: a última linha ("C ≤ 0.012 / ≤ 0.020")
   não bate com três tons de texto explícitos no claro (0.015/0.020) nem com
   `hairline` no escuro (0.022). O motor usa os valores por token; o teto não é
   aplicado separadamente. Registrado em `PARAMETROS.md`.
3. **Estados abaixo de AA**: valores reais nas fixtures; a F3 decide como
   avisar e a F5 exporta os avisos. Nenhum ajuste automático.
4. **Accent fora da triádica**: a derivação +120 é uma decisão de implementação
   para manter o catálogo estável; revisável na auditoria.
5. **Secundária manual**: a entrada é `Rgb` (opaca). Alpha na secundária manual
   não é aceito na F1; se a F3 precisar, o contrato muda com versão.
6. **Originais dos ADRs**: mantidos em `docs/colors/layout-harmonizer/adr/`
   até o aceite da F1, conforme o mapa.

## 7. O que a F1 não fez

- Sem UI, registro da tool, manifest, rota, i18n ou acessibilidade (F2).
- Sem overrides, locks, sugestões, meta AA/AAA interativa (F3).
- Sem migration, tabela, cota, slug, viewer público ou sitemap (F4).
- Sem Markdown, CSS, ZIP ou snapshot de exportação (F5).
- Sem dependência nova, sem `fflate`, sem `npm install`.
- Sem commit, push, deploy, migration ou teste contra produção.

## 8. Verificação do estado do repositório

`git status --short` mostra:

- modificados: `docs/SPEC.md`, `docs/colors/README.md`, `docs/glossario.md`
  (este desde a F0);
- novos: os 10 ADRs em `docs/adr/`, `docs/colors/layout-harmonizer/`
  (incluindo `PARAMETROS.md`), os 8 arquivos do motor, os 6 testes, o snapshot,
  os artefatos da F0 em `docs/claude-code/`, `docs/plans/` e `docs/survey/`;
- pré-existente, fora do trabalho: `.gitpr/reports/pr_desc/feature-colors-fase-8_20261115154225_PR_DESC.md`.

Nenhum arquivo rastreado de aplicação foi alterado; os gerados pelo Wayfinder
não mudaram.

## 9. Confirmação de parada

A F1 está concluída com os gates verdes e **não avança para a F2**. Aguardo a
revisão do código, de `PARAMETROS.md` e dos ADRs copiados. Nenhum commit ou push
foi feito — o usuário commita e abre o PR pelo gitpr.
