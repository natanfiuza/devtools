# Survey facts — Layout Harmonizer F6 (auditoria final e entrega)

Data: 2026-11-22
Branch: `feature/layout-hamonizer-fase-4` (grafia do repositório)
Origem: `/grill-with-docs` com argumento "Pode iniciar a fase 6".
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase6-auditoria_final_entrega.md`.

## Contexto

A F6 fecha o track do Layout Harmonizer (F1–F6): auditar a implementação contra
`SPEC.md`, `TOKENS.md`, ADRs e `TESTES.md`; corrigir o que for encontrado; rodar gates reais;
entregar matriz requisito → arquivos → testes → situação, resultados, pendências por gravidade,
checklist manual, deploy/rollback, migrations e efeitos; atualizar a documentação. Sem commit,
push ou deploy.

## Decisões das rodadas do grill

### Rodada 1

| Pergunta | Resposta |
| --- | --- |
| Postura diante de desvios | Corrigir tudo o que achar (não só gravidade alta) |
| Verificação manual | Checklist + as partes automatizáveis |
| Artefato de aceite | `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md` + ADR-178 novo |
| Operação | RUNBOOK + `.env.example` |

### Rodada 2

| Pergunta | Resposta |
| --- | --- |
| Lacunas de teste do `TESTES.md` | Criar os testes agora (lock após aceite; feedback independente da seed; hover/active/variant distintos; fronteira Primary == seed) |
| Concorrência | Criar teste MySQL opt-in |
| ADRs locais | Remover os 22 originais no aceite |
| Calibração da F1 | Reabrir e corrigir |

### Rodada 3

| Pergunta | Resposta |
| --- | --- |
| Accent | Seguir o `TOKENS.md`: catálogo estável de 33 tokens, Accent θ+120° em todas as estratégias, sem mudança de motor |
| Teto de croma | Aplicar: superfícies/textos claro ≤ 0.012 / escuro ≤ 0.020 (4 tokens), subir versões e regenerar fixtures |
| Secundária manual | Suportar alpha com fundo de composição explícito (campo/estado/validação/exports/testes) |

## Fatos levantados

### Motor e versões

- `resources/js/tools/layout-harmonizer/engine/params.ts`: `LAYOUT_PARAMS_VERSION = 1`;
  `SURFACE_TEXT_PARAMS` claro `on-background` 0.015, `on-surface` 0.015,
  `on-surface-variant` 0.020; `SURFACE_PARAMS.hairline` escuro 0.022; `STRATEGY_HUES.accent = 120`
  em todas as estratégias; `FEEDBACK_PARAMS` fixos.
- `engine/types.ts`: `LAYOUT_SCHEMA_VERSION = 1`, `LAYOUT_ALGORITHM_VERSION = 1`;
  `GenerateLayoutInput.secondary?: Rgb | null`; erro `invalid-strategy | seed-alpha-without-background`.
- `engine/generate.ts`: linha ~401 `const manualSecondary = { ...input.secondary, a: 1 }` descarta alpha;
  retorna `schemaVersion`, `algorithmVersion`, `seed`, `strategy`, `secondarySource`, `themes`.
- `engine/alpha.ts`: `resolveSeed(seed, background)` compõe com `compositeOver` e lança
  `seed-alpha-without-background`; `compositeOver` reutilizável.
- `engine/*` tem fixtures douradas (80 snapshots: 10 seeds × 4 estratégias × 2 temas).

### Entradas e estado

- `lib/inputs.ts`: `parseBackgroundText` e `parseSecondaryText` retornam RGB e descartam alpha em silêncio.
- `lib/snapshot.ts`: `LAYOUT_SNAPSHOT_VERSION = 1`; snapshot tem `seed {original, effective, background,
  achromatic}`, `strategy`, `secondarySource`, `themes`, `overrides`, `locks`, `acceptedAdjustments`,
  `diagnostics`; não há informação original/efetiva da secundária.
- `lib/state.ts`: `hydrationMode` (`live`/`compatible`/`readonly`); `buildLayoutState(inputs, secondary,
  snapshot)` monta `{stateVersion, inputs:{seedText, backgroundText, secondaryText, strategy}, secondary,
  snapshot}`.
- `lib/regenerate.ts`: usa `parseBackgroundText`/`parseSecondaryText`; `null` quando não há quadro.
- `composables/useLayoutSystem.ts`: aviso `seed-alpha-without-background`; sem fundo de secundária.
- `LayoutHarmonizerPanel.vue`: `inputsOf()` com seed/background/secondary/strategy; `currentState()` e
  `saveSource` usam `controller.secondaryColor`.

### UI

- `components/SecondaryField.vue`: um campo de texto, hint/erro, botão de reset; sem fundo de composição.
- `composables/useLayoutDraft`, `useLayoutSystem`: precisam acompanhar os novos campos.

### Servidor

- `app/Support/Colors/LayoutSystemState.php`: valida `stateVersion`; inputs strings
  (`seedText`, `backgroundText`, `secondaryText`, `strategy`) com `input_max_length` 120; snapshot com
  `snapshotVersion`/`schemaVersion` iguais ao config, `algorithm`/`suggestions` 1..999, enums; quando
  `secondarySource === 'manual'` exige `secondary` RGB opaco (`isRgb`); `state_max_bytes` 256 KiB.
- `config/colors.php` `layout_systems`: name 80, description 500, per_page 20, preview 6, sitemap 500,
  state_max_bytes 262144, input 120, export 32/64; `versions.state/schema/snapshot/algorithm/suggestions`
  = 1; tags `per_layout_system` 10; quota conjunta `COLORS_FREE_LIMIT=25`.
- `.env.example`: não tem nenhuma variável `COLORS_LAYOUT_SYSTEM_*` nem
  `COLORS_TAGS_PER_LAYOUT_SYSTEM`; cuidado documentado com `(int) ''`.
- Migrations existentes: `2026_11_19_000001_create_layout_systems_table.php`,
  `2026_11_19_000002_create_layout_system_retired_slugs_table.php`,
  `2026_11_19_000003_create_color_tag_layout_system_table.php`; nenhuma nova planejada.
- `phpunit.xml` força `DB_CONNECTION=sqlite`; MySQL real não tem lock de linha no sqlite.

### Documentação

- `TOKENS.md` recomenda catálogo estável de 33 tokens com Accent derivado (linha 4).
- `PARAMETROS.md` ainda tem os valores antigos de croma e versão 1.
- `ADR-MAPEAMENTO.md` e `docs/colors/README.md`: originais locais ainda em `adr/`; 22 arquivos, todos
  copiados para ADR-145..166.
- `docs/SPEC.md` §9 termina na F5 (2026-11-20); `CHANGELOG.md` sem entrada do track; `RUNBOOK.md` sem
  seção do Layout Harmonizer; `docs/acceptance/` tem o modelo de `COLORS-FASE-8.md`.
- ADR mais alto: 177; novo ADR da auditoria: 178.

### Lacunas de teste identificadas

- Bloqueio (lock) retido depois de aceitar sugestões.
- Tokens de feedback iguais entre seeds diferentes (mesma estratégia/tema).
- `hover`/`active`/`variant` distintos por tema/estratégia (primária e feedback).
- Fronteira Primary == seed até o primeiro aceite.
- Lock real de linha em concorrência (cobertura HTTP existe; lock de banco não).
- Alpha na secundária e alpha no fundo da seed (comportamento atual silencioso).
