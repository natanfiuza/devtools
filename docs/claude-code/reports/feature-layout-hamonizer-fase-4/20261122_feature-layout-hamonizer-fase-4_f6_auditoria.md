# Relatório — Layout Harmonizer F6: auditoria final e entrega

- Data: 2026-11-22
- Branch: `feature/layout-hamonizer-fase-4`
- Fonte da fase: `docs/colors/layout-harmonizer/PROMPT-Fase6-auditoria_final_entrega.md`
- Plano: `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261122_feature_layout-hamonizer-fase-4_f6_auditoria.md`
- Aceite: `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md`
- Decisão registrada: `docs/adr/ADR-178-colors-lh-auditoria-final.md`
- **Sem commit, push ou deploy** (o usuário revisa e commita).

## 1. Objetivo e postura

Fechar o track do Layout Harmonizer auditando F1–F5 contra a SPEC, os 33
tokens, os ADRs e a matriz de `TESTES.md`, corrigindo **tudo** o que fosse
encontrado (não só a gravidade alta) e entregando a documentação de operação.
A auditoria encontrou desvios de implementação e de documentação; nenhum
exigiu reabrir decisão de produto.

Decisões fechadas no grill de 2026-11-22 (todas implementadas):

1. teto de croma do F0 aplicado nos quatro tokens que o excediam, com versão de
   algoritmo 2 e fixtures regeneradas;
2. Accent fica como o `TOKENS.md`/ADR-163 definem (33 tokens, θ + 120° em todas
   as estratégias), sem mudança de motor — nota fechada na ADR-178;
3. secundária manual passa a aceitar alpha com fundo de composição próprio e
   explícito;
4. alpha no fundo da seed é **recusado explicitamente**, nunca descartado em
   silêncio;
5. as quatro lacunas de `TESTES.md` foram cobertas e entrou um teste de
   concorrência MySQL opt-in (sqlite não tem lock de linha);
6. deploy/rollback documentados no `RUNBOOK.md` §12 e variáveis no
   `.env.example`;
7. os 22 ADRs locais de `docs/colors/layout-harmonizer/adr/` foram removidos —
   o conteúdo integral está em `docs/adr/ADR-145` a `ADR-178`.

## 2. Correções de motor e contrato

### 2.1 Teto de croma e passo da variante (ADR-178)

- `resources/js/tools/layout-harmonizer/engine/params.ts`: croma de
  `on-background` (claro), `on-surface` (claro) e `on-surface-variant` (claro)
  para 0.012 e de `hairline` (escuro) para 0.020, sem mudar matiz ou clareza;
  `VARIANT_DELTA` em ±0.12, eliminando a colisão de `primary-variant` com
  `primary-active` em várias seeds; a regra da variante passou a valer também
  para a secundária.
- `engine/types.ts`, `engine/generate.ts`, `config/colors.php`:
  `LAYOUT_PARAMS_VERSION` e `LAYOUT_ALGORITHM_VERSION` = 2. `schema`,
  `snapshot`, `state` e `suggestions` continuam em 1 (envelope aditivo,
  ADR-169), então linhas salvas em 1 abrem em modo **compatível** — nunca
  readonly — e regeneram para 2 pela tela.
- As 80 fixtures douradas (10 seeds × 4 estratégias × 2 temas) foram
  regeneradas; o diff foi revisado e só os tokens afetados (e os pares de
  contraste que os usam) mudaram.

### 2.2 Alpha na secundária manual

- `engine/types.ts` e `engine/generate.ts`: `secondary` passa a ser `Rgba` e há
  `secondaryBackground?: Rgb | null`. Com alpha, o motor exige o fundo opaco,
  compõe com `compositeOver` e deriva a partir da cor efetiva; sem fundo lança
  `secondary-alpha-without-background`. O resultado/snapshot ganhou
  `secondary { original, effective, background }` (aditivo).
- `lib/inputs.ts`: `parseSecondaryText` preserva alpha;
  `parseSecondaryBackgroundText` valida o fundo opaco;
  `parseBackgroundText` passou a devolver resultado discriminado para detectar
  alpha em vez de descartar.
- `lib/regenerate.ts`, `lib/state.ts`, `lib/save.ts`, `lib/editState.ts`,
  `lib/snapshot.ts`, `lib/frozen.ts`, `lib/controller.ts`: campos novos
  (`inputs.secondaryBackgroundText`, `state.secondaryBackground`) com leitura
  tolerante para estados antigos.
- `composables/useLayoutSystem.ts`, `composables/useFrozenLayout.ts`,
  `components/SecondaryField.vue`, `LayoutWorkspace.vue`,
  `LayoutHarmonizerPanel.vue`: campo “Fundo de composição” (só exigido com
  alpha), erros traduzidos e reset junto da secundária.
- `export/markdown.ts`: com secundária manual e alpha, lista original, efetiva e
  fundo de composição — mesmo padrão da seed; CSS/ZIP inalterados.

### 2.3 Alpha no fundo da seed

O controlador marca o erro `seed_background_alpha_invalid` e não gera o quadro;
nada de composição silenciosa. O estado salvo com alpha no `backgroundText`
também é recusado pela validação do servidor.

### 2.4 Servidor

- `app/Support/Colors/LayoutSystemState.php`: valida `state.secondary` RGBA
  somente com `secondarySource === 'manual'` e fundo opaco válido;
  `inputs.secondaryBackgroundText` como string limitada por
  `layout_systems.input_max_length`; estados antigos continuam válidos.
- `app/Actions/Colors/*` e `app/Http/Controllers/Colors/*`: repasse dos campos,
  slug/locale da rota pública e mensagens conferidos na auditoria.
- `lang/*/{tools,colors,validation}.php`: chaves novas nos cinco idiomas
  (fundo de composição, alpha no fundo da seed, regeneração, descarte do
  rascunho), com paridade coberta por teste.

## 3. Correções de UI e acessibilidade

| ID | Achado | Correção |
| --- | --- | --- |
| U2 | Modo compatível não oferecia regenerar sem trocar entrada | banner com as versões + diálogo de confirmação |
| U3 | Modo readonly não oferecia regenerar | oferta presente, com confirmação |
| U4 | “Restaurar” apagava override/bloqueio no primeiro clique | confirmação dentro do popover |
| U6 | Rascunho anônimo salvo não tinha como ser descartado | ação “Descartar rascunho” com confirmação, limpa quadro e storage |
| U7 | Fechar o diálogo de importação apagava o rascunho | só o botão explícito descarta |
| U8 | Dois editores abertos repetiam o `id` do campo | `useId()` por editor |
| U9 | Erro de exportação não ligado ao campo para leitor de tela | `aria-describedby`, `aria-invalid` e `role="alert"` |
| U10 | Selos (“Croma reduzido”, alvo do contraste) só no `title` | texto em `sr-only` junto do selo |

Arquivos: `components/TokenCell.vue`, `TokenEditorPopover.vue`,
`LayoutExportPanel.vue`, `ImportDraftDialog.vue`, `SeedPicker.vue`,
`LayoutWorkspace.vue`, `LayoutHarmonizerPanel.vue`, `composables/useLayoutDraft.ts`.

## 4. Testes

Casos novos/ajustados (Vitest): `layout-harmonizer-params.test.ts` (teto de
croma e versões), `layout-harmonizer-regeneration.test.ts` (oferta no quadro
congelado), `layout-harmonizer-draft-ui.test.ts` (diálogo e descarte
explícito), casos de alpha na secundária/fundo da seed em
`-engine`/`-state`/`-alpha`/`-system`, lock retido após o aceite, feedback
independente da seed, hover/active/variant distintos e a fronteira
“Primary == seed” em `-engine`/`-overrides`/`-edits`, além do Markdown com
secundária composta.

Pest: `LayoutSystemHttpTest` (RGBA com/sem fundo, fundo inválido, estado
antigo), `LayoutSystemStateTest` e
**`tests/Feature/Colors/LayoutSystemConcurrencyTest.php`** (novo, opt-in:
`LAYOUT_CONCURRENCY_TEST=1` + driver `mysql`/`mariadb` + banco terminando em
`_test`; dois PDOs provam o `SELECT ... FOR UPDATE` da cota conjunta).

Infra de teste: `__tests__/support/langMessages.ts` passou a cachear a leitura
de `lang/` (um spawn de PHP por arquivo de teste) e `vitest.config.ts` define
`testTimeout: 20000` — sob a carga dos 163 ambientes jsdom casos triviais
estouravam o default de 5 s; isolados rodam em menos de 1 s. O limite maior não
mascara asserção.

## 5. Gates reais (2026-11-22)

| Gate | Resultado |
| --- | --- |
| Pint + PHPStan (`composer test`) | verde |
| `php artisan test` | 1856 testes, 1854 passaram, 2 skipped, 33306 asserções, exit 0 |
| `npx vitest run` | 163 arquivos, 1947 testes passaram, 311,77 s, exit 0 |
| `npm run types:check` | verde (3 erros de tipo corrigidos: `mode` reativo no workspace, `clear` do storage no teste, `formatHex`/`compositeOver`) |
| `npm run format:check` | verde (14 arquivos formatados) |
| `npm run lint:check` | exit 0, 0 erros, 27259 avisos de indentação pré-existentes |
| `npm run build` | exit 0, `✓ built in 20.52s` |
| Concorrência opt-in | 2 skipped, sem MySQL `_test` neste ambiente (pendência P2) |

Nota sobre o `composer test`: o timeout de processo de 300 s do Composer
abortou o `artisan test` depois de Pint/PHPStan nesta máquina (a suíte leva
~385 s). A suíte foi então rodada pelo comando equivalente `php artisan test`,
sem filtro, com o resultado acima.

## 6. Verificação do CSS no navegador

CSS exportado de uma fixture (`#3b82f6`, `analogous`, alvo AA, prefixo `lh`,
escopo `:root`) aplicado no Chromium do Playwright:

- 33 custom properties nos dois temas;
- claro sem atributo e `data-theme="light"` idênticos; `data-theme="dark"`
  diferente;
- bloco base em HEX/rgba e `@supports` em OKLCH;
- comparação pixel a pixel entre OKLCH e fallback: delta máximo 0 no claro e 1
  no escuro (arredondamento para 8 bits);
- ZIP exportado abre com `colors.css` (6.794 B) e `design-instructions.md`
  (12.319 B).

O teste temporário que gerou os arquivos foi removido; nada versionado.

## 7. Documentação e entrega

- `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md`: escopo, matriz de 20
  requisitos com implementação/teste/situação, gates reais, checklist manual
  de 15 passos, desvios, pendências e deploy/rollback.
- `docs/adr/ADR-178-colors-lh-auditoria-final.md`.
- `docs/RUNBOOK.md` §12: deploy pelo cron, as três migrations, rollback só de
  aplicação, efeito do `algorithmVersion` 2, limites por env e diagnóstico.
- `.env.example`: os dez limites `COLORS_LAYOUT_SYSTEM_*` /
  `COLORS_TAGS_PER_LAYOUT_SYSTEM`, com o cuidado do `(int) ''`.
- `CHANGELOG.md`, `docs/SPEC.md` (§5/§9), `docs/colors/README.md`,
  `ADR-MAPEAMENTO.md`, `PARAMETROS.md` (emenda datada e versão 2),
  `CONTRATO-TECNICO.md`, `DADOS.md`, `TESTES.md`, `docs/glossario.md`.
- Remoção dos 22 ADRs locais (`docs/colors/layout-harmonizer/adr/`), com o
  conteúdo preservado em `docs/adr/ADR-145`–`ADR-166` (mais 167–178).

## 8. Pendências

| ID | Pendência | Gravidade | Ação |
| --- | --- | --- | --- |
| P1 | `ADR-171` cita `isRetired`, auxiliar que não existe (o comportamento vem de `archived_slug`/ledger) | Baixa | emenda datada em ADR futuro; não reescrever o ADR agora |
| P2 | Concorrência real não rodou aqui (sem MySQL descartável) | Baixa | `DB_CONNECTION=mysql DB_DATABASE=devtools_test ... LAYOUT_CONCURRENCY_TEST=1 php artisan test --filter=LayoutSystemConcurrencyTest` no CI |
| P3 | `(int) env('...')` com variável vazia vira 0, não o default | Baixa | documentado no `.env.example` e no RUNBOOK §12 |
| P4 | Mesma limitação de P2 para o lock da cota | Baixa | mesma ação de P2 |

Nenhuma pendência média/alta. Nenhuma decisão de produto foi reaberta.

## 9. Como revisar

1. `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md` (matriz, gates, checklist).
2. `docs/adr/ADR-178-colors-lh-auditoria-final.md` e
   `docs/colors/layout-harmonizer/PARAMETROS.md` (emenda da versão 2).
3. `docs/RUNBOOK.md` §12 e `.env.example`.
4. Diff de `engine/params.ts`, `engine/generate.ts`, `lib/state.ts`,
   `app/Support/Colors/LayoutSystemState.php` e dos testes novos.

## 10. Parada

Fase 6 concluída. **Nada foi commitado, enviado ou implantado** e nenhuma nova
fase foi iniciada — aguardando revisão do usuário.
