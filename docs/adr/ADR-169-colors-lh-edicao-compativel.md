# ADR-169 — Edição compatível e quadro congelado
Data: 2026-11-19
Status: Aceito

## Contexto

Um sistema salvo carrega as versões do algoritmo, do schema, do snapshot e das
sugestões com que foi gerado. Com a evolução da ferramenta, abrir um sistema
antigo pode significar regenerar um quadro diferente do que o dono viu. Era
preciso um comportamento explícito para três situações: sistema da versão
atual, sistema de uma versão anterior suportada e sistema de uma versão mais
nova ou desconhecida.

## Decisão

`hydrateMode` classifica o snapshot em `live` (todas as versões atuais),
`compatible` (state/snapshot/schema atuais e algorithm/suggestions menores) ou
`readonly` (shape/versão mais nova ou desconhecida).

**`live`** usa `useLayoutSystem` normalmente; `materializeResult` devolve o
`LayoutSystemResult` com as versões atuais.

**`compatible`** usa `lib/frozen.ts` + `composables/useFrozenLayout.ts`, que
implementam a mesma interface `LayoutController` do workspace:

- editar token atualiza `rgba`/`hex`/`oklch` e **todos** os checks do tema com
  o `evaluateContrast` já exportado pelo motor, sem gerar tokens e sem mudar
  versões; o override entra em `snapshot.overrides`/`editState`;
- lock/unlock atualiza só `snapshot.locks`; o valor exibido não muda;
- restaurar token/tema/sistema fica desabilitado (o snapshot não guarda o valor
  automático anterior) e a UI explica, oferecendo “Regenerar com a versão
  atual”;
- sugestões ficam desabilitadas (o algoritmo de sugestões pode ter mudado) e a
  UI informa;
- alterar seed/estratégia/secundária marca `pendingRegeneration`: o quadro não
  muda e o salvamento fica bloqueado até a decisão explícita;
- “Regenerar com a versão atual” pede confirmação, gera com as entradas atuais
  carregando os tokens bloqueados como overrides e descartando manuais não
  bloqueados; o editor remonta o workspace com o novo estado `live` (via
  `key`) e o salvamento passa a gravar as versões atuais.

**`readonly`** mostra o quadro com `editable=false`, mantém metadados, ciclo de
vida e duplicação e, quando o shape é conhecido, oferece a regeneração; não
aceita edição de token.

O `result` do controller usa uma visão estrutural `LayoutViewResult` com
versões `number`, para o quadro congelado não fingir ser da versão atual.
`LayoutWorkspace` continua a única camada que conhece os dois composables.

## Consequências

- Nenhum sistema antigo é aberto com um quadro silenciosamente diferente do
  gravado.
- O modo compatível nunca promete restauração que o snapshot não permite.
- Salvar no modo compatível preserva as versões antigas; regenerar é a única
  rota para migrar um sistema para as versões atuais.
- Textos `lifecycle.*` nos cinco idiomas explicam cada bloqueio.
