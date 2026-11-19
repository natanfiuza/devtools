# ADR-168 — Persistência do estado efetivo do sistema de layout
Data: 2026-11-19
Status: Aceito

## Contexto

A F1–F3 entregaram o motor puro, o quadro semântico e a edição manual, mas o
estado efetivo da tela só existia em memória. O `LayoutSnapshot` da F3 guarda
as cores e os checks finais, não os textos crus digitados, nem o RGB da
secundária manual, nem uma forma de reabrir o quadro sem regenerar. A F4
precisa salvar o sistema, reabrir exatamente o que foi mostrado e preservar o
comportamento da F3 quando o sistema salvo for da versão atual.

## Decisão

### Tabela própria e shape do estado

O sistema persiste em `layout_systems` (migration
`2026_11_19_000001_create_layout_systems_table.php`), com ULID, FK para o dono,
FK opcional para `duplicated_from_id` e `source_palette_id`, `state` em JSON,
crédito congelado em `source`, versões (`schema_version`, `algorithm_version`,
`suggestions_version`, `snapshot_version`), `tokens_count`,
`duplications_count`, `slug`/`archived_slug`/`archived_at` e índices por dono,
visibilidade e data. As tags usam o pivot `color_tag_layout_system` e os slugs
aposentados a tabela `layout_system_retired_slugs` (migrations 000002 e 000003).
Não há discriminador em `color_palettes`: um discriminador vazaria o sistema
para curtidas, comentários, favoritos, perfil e OG, que são só de paleta.

O JSON de `state` tem a forma:

```json
{
  "stateVersion": 1,
  "inputs": { "seedText": "#3b82f6", "backgroundText": "", "secondaryText": "",
              "strategy": "analogous" },
  "secondary": null,
  "snapshot": { "snapshotVersion": 1, "schemaVersion": 1, "algorithmVersion": 1,
                "suggestionsVersion": 1, "target": "aa", "seed": {},
                "strategy": "analogous", "secondarySource": "auto",
                "themes": [], "overrides": [], "locks": [],
                "acceptedAdjustments": [], "diagnostics": [] }
}
```

`snapshot` é exatamente o `LayoutSnapshot` da F3, sem mudar o contrato.
`inputs` preserva os textos crus — inclusive alpha e sintaxes alternativas — e
`secondary` é o RGB efetivo da secundária manual (fonte de verdade da
assinatura de regeneração), `null` quando automática. `target` fica só em
`snapshot.target`; `viewMode` não é persistido.

### Validação no servidor

`App\Support\Colors\LayoutSystemState` valida em
`withValidator`/`after`: `stateVersion`, `snapshotVersion` e `schemaVersion`
iguais aos atuais (1); `algorithmVersion`/`suggestionsVersion` inteiros entre 1
e 999 (o servidor não adivinha versões futuras); exatamente dois temas com os
33 ids do catálogo, sem repetição, cada um com a família correta; os 16 checks
por tema com os mesmos pares de `LAYOUT_CONTRAST_PAIRS`; canais `rgba` em
faixa, alpha 0–1, `hex` `/^#[0-9a-f]{6}$/`, `oklch` numérico, `source` no enum
conhecido, `clipped` booleano e `theme` coerente; `overrides`/`locks`/
`acceptedAdjustments` até 66 e `diagnostics` até 100; `inputs` até o limite
configurado (120); `strategy` no enum `App\Enums\LayoutStrategy` e igual a
`snapshot.strategy`; `secondarySource` coerente com `secondary`; seed com alpha
exigindo `background`; `json_encode` abaixo de
`colors.layout_systems.state_max_bytes` (256 KB), com falha de codificação
rejeitada. `LayoutSystemCatalog` concentra ids, famílias, pares e estratégias
em PHP, com teste Pest de paridade contra `engine/types.ts`/`engine/tokens.ts`.
O servidor **deriva** `schema_version`, `algorithm_version`,
`suggestions_version`, `snapshot_version` e `tokens_count` do snapshot
validado; o cliente nunca envia colunas de versão separadas.

### Hidratação no cliente

`resources/js/tools/layout-harmonizer/lib/state.ts` define
`LAYOUT_STATE_VERSION` e `LayoutSystemStateV1`, `buildLayoutState(inputs,
secondary, snapshot)` (monta o envelope sem reconstruir o snapshot),
`hydrateState(state)` (valida shape/versões e devolve `{inputs, secondary,
snapshot, editState, mode}`), `boardsFromSnapshot(snapshot)` (converte arrays
em `Record` sem chamar o motor), `materializeResult(snapshot)` (só no modo
`live`) e `hydrateEditState(snapshot)`. Se `seedText`/`backgroundText`/
`secondaryText` não parsearem para a cor gravada no snapshot, a hidratação
troca o texto por `formatHex` da cor efetiva, para nunca regenerar um quadro
diferente do salvo. `useLayoutSystem` recebe `initial` e usa um latch
`skipNext` para consumir a primeira execução do `watch` imediato da F3 — sem
isso, a montagem regeneraria o quadro e poderia descartar overrides.

## Consequências

- Reabrir um sistema da versão atual não recalcula nada: o quadro hidratado é
  idêntico ao gravado, coberto por teste de identidade.
- O estado padrão cabe em ~20–25 KB, com teto técnico de 256 KB.
- Estado malformado, versão mais nova ou desconhecida nunca entra pela API:
  `layout_systems.state` só aceita o shape v1 validado.
- O rascunho anônimo (ADR-161) usa o mesmo envelope, o que garante que
  importar e salvar falam o mesmo formato.
