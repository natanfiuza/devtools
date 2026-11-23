# ADR-178 — Auditoria final e entrega do Layout Harmonizer
Data: 2026-11-22
Status: Aceito

## Contexto

A F6 fecha o track do Layout Harmonizer auditando F1–F5 contra `SPEC.md`,
`TOKENS.md`, os ADRs e a matriz de `TESTES.md`, corrigindo o que a auditoria
encontrar e entregando o aceite, a operação e a documentação final. As
decisões deste ADR foram fechadas no grill da F6 (2026-11-22); nenhuma reabre
uma decisão de produto anterior.

## Decisão

### Teto de croma (emenda numérica)

O teto da tabela do F0 — superfícies/textos com C ≤ 0.012 no claro e ≤ 0.020
no escuro — passa a valer sem exceção. Quatro tokens o excediam e foram
corrigidos só no croma, mantendo matiz e claridade:

| Token | Tema | Antes | Depois |
| --- | --- | --- | --- |
| `on-background` | claro | 0.015 | **0.012** |
| `on-surface` | claro | 0.015 | **0.012** |
| `on-surface-variant` | claro | 0.020 | **0.012** |
| `hairline` | escuro | 0.022 | **0.020** |

`LAYOUT_PARAMS_VERSION` e `LAYOUT_ALGORITHM_VERSION` sobem de 1 para **2**
(`config/colors.php` `versions.algorithm = 2`). `schemaVersion`,
`snapshotVersion`, `stateVersion` e `suggestionsVersion` continuam **1**: o
envelope é aditivo e os sistemas salvos em 1 abrem no modo **compatível**
(ADR-169), nunca somente leitura, sem migration e sem backfill. As 80
fixtures douradas foram regeneradas com diff revisado: só os tokens afetados e
as razões/pares que os usam mudaram.

### Passo da variante de marca

A auditoria mostrou que `primary-variant` e `primary-active` podiam cair no
mesmo HEX: o passo da variante (0.10) era igual ao de `active` e, com o croma
fora do gamut, o corte do mapeamento igualava as duas cores. `VARIANT_DELTA`
passa a claro `-0.12` / escuro `+0.12`, mantendo direção e pisos/tetos; a
regra vale também para `secondary-variant`. Faz parte da versão 2 do motor e
da regeneração das fixtures.

### Accent (fechamento documental, sem mudança de motor)

O catálogo permanece com **33 tokens** e o Accent derivado a θ + 120° em todas
as estratégias, como recomenda `TOKENS.md` e já decidido na ADR-163 (na
triádica é uma cor da harmonia; nas demais, o mesmo papel de destaque). A
nota "revisável" do relatório da F1 fica fechada: não há mudança de motor,
versão ou fixture por causa do Accent. O glossário foi corrigido (a entrada
dizia que o Accent não existia fora da triádica).

### Secundária manual com alpha

A secundária manual passa a aceitar alpha, mas sempre com **fundo de
composição explícito**:

- `GenerateLayoutInput` ganha `secondary?: Rgba` e
  `secondaryBackground?: Rgb | null`; com `alpha < 1` e sem fundo, o motor
  falha com `secondary-alpha-without-background`.
- A cor efetiva é a composição (`compositeOver`, já existente em
  `engine/alpha.ts`) sobre o fundo informado; é ela que deriva as demais
  cores e aparece no quadro. Sem defaults silenciosos.
- `inputs.secondaryBackgroundText` e `state.secondaryBackground` são campos
  aditivos e opcionais; estados antigos continuam válidos. `state.secondary`
  só vira RGBA quando `secondarySource === 'manual'`, e o servidor exige o
  fundo opaco válido nesse caso.
- O Markdown lista os três valores (original, efetiva e fundo), no mesmo
  padrão da seed; CSS e ZIP não mudam de forma.
- A UI ganhou o campo de fundo de composição (i18n nos cinco idiomas), com
  erro próprio quando falta ou quando o fundo tem alpha.

### Fundo da seed com alpha (rejeição explícita)

O fundo da seed com alpha era descartado em silêncio. Agora
`parseBackgroundText` devolve um resultado discriminado (`vazio | inválido |
opaco | alpha`), e alpha gera o erro i18n `seed_background_alpha_invalid`,
impedindo o quadro. Não há composição silenciosa e nenhum estado inválido é
gravado.

### Teste de concorrência real (opt-in)

A cobertura HTTP já provava a quota conjunta, mas não o lock de linha. A F6
acrescenta `LayoutSystemConcurrencyTest` com dois PDOs independentes e
`SELECT ... FOR UPDATE` na linha da conta. Ele é opt-in com guarda tripla —
`LAYOUT_CONCURRENCY_TEST=1`, driver `mysql`/`mariadb` e banco terminando em
`_test` — e, fora disso, fica `skipped`; sem MySQL de teste descartável, o
skip é o resultado esperado e está registrado no aceite.

### Lacunas de teste fechadas

Os quatro requisitos de `TESTES.md` sem cobertura direta ganharam casos:
bloqueio retido depois do aceite; feedback independente da seed;
hover/active/variant distintos por tema e estratégia; Primary inicial igual à
seed efetiva por tema. Também entraram: teto de croma nas fixtures, alpha da
secundária, rejeição do alpha no fundo da seed, regeneração do quadro
congelado e o descarte explícito do rascunho anônimo.

### Correções de UI e acessibilidade (auditoria)

- O aviso de "Regenerar com a versão atual" aparece ao abrir o quadro
  compatível/readonly (sem exigir trocar uma entrada) e o clique pede
  confirmação com a contagem de edições sem bloqueio que serão descartadas.
- Restaurar no editor de token pede confirmação dentro do popover, em vez de
  apagar override e bloqueio no primeiro clique.
- Fechar/`Esc` no diálogo de importação do rascunho não apaga mais o
  rascunho; só o botão explícito descarta. O anônimo ganhou a ação de
  descartar o rascunho salvo (com confirmação), que limpa o quadro junto.
- O campo de cor do editor usa `useId()` (dois editores abertos não repetem
  id); o prefixo/escopo de exportação liga erro e dica por
  `aria-describedby`/`role="alert"`; os selos de contraste e de croma
  reduzido têm o texto também fora do `title`, para teclado e leitor.

### Documentação e encerramento

- Aceite em `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md`; operação no
  `RUNBOOK.md` §12; variáveis no `.env.example`; `CHANGELOG.md`, `SPEC.md`
  (F6 concluída), `docs/colors/README.md`, `ADR-MAPEAMENTO.md`,
  `PARAMETROS.md`, `CONTRATO-TECNICO.md`, `DADOS.md`, `TESTES.md` e glossário
  atualizados.
- Os 22 ADRs originais de `docs/colors/layout-harmonizer/adr/` são removidos
  neste aceite: o conteúdo (com as emendas) está integralmente em
  `docs/adr/ADR-145` a `ADR-178`, que é a fonte única a partir daqui.

## Consequências

- Sistemas salvos em `algorithmVersion` 1 continuam legíveis e editáveis em
  modo compatível; regeneram para 2 pela própria tela, retendo bloqueios e
  descartando edições sem bloqueio só com confirmação (ADR-169).
- Nenhuma migration e nenhuma dependência nova.
- O rollback é só de aplicação: voltar o código restaura o comportamento
  anterior; linhas salvas com `algorithmVersion` 2 passarão a abrir no modo
  somente leitura no código antigo, sem perda de dados.
- Limites conhecidos que não foram corrigidos nesta fase (não são decisões de
  produto) ficam na tabela de pendências do aceite com gravidade e ação.
