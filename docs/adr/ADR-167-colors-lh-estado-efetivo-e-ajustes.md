# ADR-167 — Estado efetivo, edição manual e ajustes sugeridos
Data: 2026-11-18
Status: Aceito

## Contexto

A F1 entregou o motor puro do Layout Harmonizer e a F2 a interface do quadro
semântico, ambas somente leitura: a seed e a estratégia geram 33 tokens nos dois
temas, e os 16 checks de contraste são reportados, mas nada pode ser corrigido
sem trocar a entrada. A F3 (`PROMPT-Fase3-edicao_bloqueios_contraste.md`) fecha
o controle manual sem persistir nada: editar tokens, bloquear, restaurar,
secundária manual, meta AA/AAA para sugestões, aceite explícito por tema ou nos
dois temas e um snapshot consistente do que está na tela.

O grill de 2026-11-16/18 fechou as decisões abaixo; o ADR-LH-007 (bloqueios)
foi transferido como ADR-151 e o ADR-158 (contraste) recebeu emenda datada.
Persistência, exportação e protótipos continuam fora da fase.

## Decisão

### Overrides por tema e token

O estado de edição vive fora de `LayoutSystemResult`, em
`lib/editState.ts`: overrides por tema e por token, cada um com a origem
`edit` (editor), `accept` (ajuste aceito) ou `lock` (bloqueio). O motor recebe
`overrides?: LayoutOverrides` e os aplica dentro de `buildBoard`, logo antes de
`evaluateContrast`: o sRGB exato entra como `source: 'manual'`, `clipped: false`
e o contraste é remedido. Não há cascata — editar o `primary` não reconstrói
`on-primary`, `primary-hover` nem `primary-variant` —, ids ou temas
desconhecidos são ignorados e a ausência de overrides produz o mesmo resultado
de antes (fixtures e `LAYOUT_ALGORITHM_VERSION` intactos).

Precedência documentada: override por token > secundária manual (entrada
`secondary` do motor) > derivação automática.

### Bloqueio

O bloqueio é por token e por tema (ADR-151). Ligar congela o valor efetivo como
override de origem `lock`; desligar remove apenas esse congelamento e preserva
um valor editado ou aceito no mesmo token. O aceite de uma sugestão em token
bloqueado grava o valor novo e mantém o bloqueio. Restaurar o token remove
override, bloqueio e os aceites registrados para ele.

### Regeneração e descarte

A assinatura das entradas que regeneram o quadro é
`seed efetiva | estratégia | secundária manual (ou auto)`, via `canonicalKey`
(o fundo de composição já está dentro da seed efetiva). Quando a assinatura
muda, overrides sem bloqueio — inclusive os de origem `accept` — são
descartados e a tela avisa; os bloqueados permanecem. O aviso usa um canal
próprio (`editNotice: 'discarded'`), separado do aviso de entrada inválida, e
não marca o quadro como obsoleto (`isStale` continua falso): a última geração
foi bem-sucedida. Entrada inválida, seed vazia e estratégia inválida mantêm o
comportamento da F2 e nunca derrubam edições.

### Restaurar

Três escopos explícitos: token, tema e sistema. `restoreAllEdits` limpa
overrides, bloqueios e aceites dos dois temas e **não** toca a secundária
manual, que tem o próprio botão de voltar ao automático. A interface só pede
confirmação quando há edições a perder no escopo.

### Secundária manual

O campo de secundária usa a entrada `secondary` do motor (as variantes por tema
são derivadas como antes). A cor manual sobrevive à troca de seed e de
estratégia até o reset explícito, e `secondarySource` mostra `manual` ou
`auto`. Texto inválido preserva o último quadro válido e pede correção.

### Meta AA/AAA e sugestões

A meta existe só para sugestões: alternar entre AA (4,5) e AAA (7) não altera
nenhuma cor e mantém a identidade do `result` (a meta fica fora do watch de
geração). `lib/suggestions.ts` produz **uma sugestão por token de frente e
tema** — nunca por par —, agrupando todos os checks do token (por exemplo,
`on-primary` contra `primary`, `primary-hover` e `primary-active`).

Parâmetros (`LAYOUT_SUGGESTIONS_VERSION = 1`):

- move só a luminosidade OKLCH, preservando matiz e croma (`mapOklchToSrgb`);
- passo de L `0,005`, no máximo `200` passos em cada direção, sem repetir o
  candidato preso no clamp de 0/1;
- a razão projetada é medida no sRGB mapeado e precisa atingir a meta **mais**
  `0,001` (épsilon de arredondamento) em **todos** os fundos do token;
- desempate no mesmo passo: maior razão mínima e, persistindo, a direção mais
  clara; a primeira cor viável no menor passo vence;
- sem candidato, a sugestão sai com `proposal: null` e `deltaLightness: 0` — é
  a combinação inviável, exibida como aviso, sem ação de aceite.

**AA não é universalmente alcançável** para tokens medidos contra base, hover e
active: quando os três fundos cruzam a faixa de luminância média, nenhum
foreground único atende 4,5 em todos (ex.: `#3b82f6`, `on-*` no tema claro:
texto claro reprova na base, texto escuro reprova em hover/active). Isso é
consequência da regra aprovada de exigir todos os fundos numa só proposta, não
um defeito; a meta continua podendo encontrar propostas onde há solução. O caso
está coberto por testes com seeds fixas nos dois sentidos.

Nada é aplicado automaticamente. O aceite é uma ação explícita **por tema** e
grave só ali; "aceitar nos dois temas" grava a proposta de cada tema que tem
uma, ignorando o tema que já passa ou cuja proposta é inviável. O aceite
enquanto a meta é AA ou AAA fica registrado em `acceptedAdjustments`.

### Snapshot do estado efetivo

`lib/snapshot.ts` (`LAYOUT_SNAPSHOT_VERSION = 1`) congela o que a tela mostra
em cópia profunda, sem regenerar nada: `schemaVersion`, `algorithmVersion`,
`suggestionsVersion`, meta atual, seed original/efetiva/fundo/acromática,
estratégia, origem da secundária, tokens e checks dos dois temas, overrides
(origem, RGBA e HEX), bloqueios, ajustes aceitos e diagnósticos. Diagnósticos
listam apenas os checks que reprovam AA, com a razão também para AAA. Ainda não
há UI nem exportação: a função é pura e já pronta para a persistência da F4.

### Interface

O editor é um popover (wrapper shadcn sobre reka-ui `Popover`) montado só
enquanto aberto, com swatch, `ColorInputField` com `id`/`testId` próprios,
bloqueio, restaurar e aplicar/cancelar. Tokens do quadro são opacos: o alpha
digitado é ignorado e o editor declara isso antes de aplicar. As sugestões
ficam em um painel abaixo dos quadros; as células mantêm apenas os selos AA.

## Consequências

- Nenhuma persistência, rota, migration ou dependência nova; tudo roda no
  navegador e nada é salvo.
- Mudar o algoritmo de sugestão exige subir `LAYOUT_SUGGESTIONS_VERSION`;
  mudar a forma do snapshot exige subir `LAYOUT_SNAPSHOT_VERSION`.
- Os testes cobrem motor (overrides, precedência, sem cascata), `editState`
  (bloqueio, descarte, restore), sugestões (menor passo, todos os fundos,
  inviável em AA e AAA), snapshot (versões, cópia profunda) e interface
  (editor, painel, quadro, i18n nos cinco idiomas).
- A emenda datada no ADR-158 registra que AA/AAA passam a valer também para as
  sugestões, que a meta nunca altera cores e que a inviabilidade em AA é
  possível com tokens multi-fundo.
