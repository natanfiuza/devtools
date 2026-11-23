# Layout Harmonizer — F6 (auditoria final e entrega)

## Context

F1–F5 entregaram o motor, o quadro semântico, a edição manual, a persistência e as exportações
Markdown/CSS/ZIP do Layout Harmonizer. A F6 fecha o contrato de
`docs/colors/layout-harmonizer/PROMPT-Fase6-auditoria_final_entrega.md`: auditar a implementação inteira
contra `SPEC.md`, `TOKENS.md`, ADRs e `TESTES.md`, corrigir tudo o que for encontrado, rodar os gates reais,
entregar matriz requisito → arquivos → testes → situação, resultados reais, pendências por gravidade,
checklist manual, instruções de deploy/rollback, lista de migrations e efeitos, e documentação atualizada.
Sem commit, push ou deploy.

Data: 2026-11-22. Branch: `feature/layout-hamonizer-fase-4` (grafia do repositório). As correções abaixo
foram aprovadas no grill de 2026-11-22 (rodadas 1–3); decisões de produto não aprovadas não são reabertas.
Se a auditoria encontrar um desvio que exija reabrir decisão de produto, **parar e perguntar**.

Restrições em vigor: sem commit/push (gitpr do usuário); sem dependências novas; sem migration; toda string
visível via i18n nos 5 idiomas; tools do cliente 100% no navegador; nunca registrar schema/prompt/estado em
log; migrations retrocompatíveis; nada de defaults silenciosos.

## Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Postura da auditoria | **Corrigir tudo o que achar** (não só gravidade alta); o que exigir reabrir produto para e pergunta |
| Verificação manual | Checklist executável **+ as partes automatizáveis** (testes de componente, Playwright para CSS) |
| Aceite | `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md` (formato de `COLORS-FASE-8.md`) + **ADR-178** |
| Operação | Deploy/rollback no **RUNBOOK (§12)** + variáveis no **`.env.example`** |
| Lacunas de teste | Criar agora: bloqueio retido após aceite; feedback independente da seed; hover/active/variant distintos; fronteira Primary == seed |
| Concorrência | Teste **MySQL opt-in** (sqlite não tem lock de linha), guardado para nunca tocar dev/prod |
| ADRs locais | Remover os 22 originais de `docs/colors/layout-harmonizer/adr/` **no aceite** (todos já copiados para 145–166) |
| Accent | Seguir `TOKENS.md`: catálogo estável de 33 tokens, Accent θ+120° em todas as estratégias; **sem mudança de motor**; fechar a nota no ADR-178 |
| Teto de croma | **Aplicar** o teto do F0: superfícies/textos claro ≤ 0.012, escuro ≤ 0.020 (4 tokens mudam); `algorithm 2` + fixtures |
| Secundária manual com alpha | **Suportar** alpha com fundo de composição próprio e explícito (novo campo/estado, composição antes de derivar, UI, validação, exports, testes) |
| Fundo da seed com alpha | **Rejeitar explicitamente** (hoje é descartado em silêncio) com erro i18n próprio |
| Fora de escopo | Protótipo editável/exportável, exportação pela página pública/API, mudanças de quota, i18n nova de produto |

## Fatos da auditoria (levantados antes do plano)

- **Teto de croma:** `engine/params.ts` tem `SURFACE_TEXT_PARAMS` claro em 0.015/0.015/0.020 e
  `SURFACE_PARAMS.hairline` escuro em 0.022 — exatamente os 4 tokens fora do teto do F0
  (“superfícies/textos: C ≤ 0.012 / ≤ 0.020”). `LAYOUT_PARAMS_VERSION = 1` e `LAYOUT_ALGORITHM_VERSION = 1`;
  `config/colors.php` `versions.algorithm = 1`. Fixtures douradas (80 snapshots) e alguns testes fixam
  razões/valores atuais.
- **Alpha na secundária:** `engine/generate.ts:401` faz `{ ...input.secondary, a: 1 }`, descartando alpha em
  silêncio; `lib/inputs.ts` (`parseSecondaryText`, `parseBackgroundText`) também descarta alpha sem avisar;
  `LayoutSystemState` só aceita secundária manual RGB opaca; `SecondaryField.vue` só tem um campo.
- **Fixado pelo ADR-169:** `snapshotVersion`/`schemaVersion`/`stateVersion` iguais a 1 mantêm linhas v1
  editáveis; subir só `algorithm` para 2 deixa os sistemas salvos em modo **compatível** (1 < 2), nunca
  readonly, e não exige migration. Campos novos do envelope são aditivos e opcionais.
- **Três requisitos de `TESTES.md` sem cobertura direta:** lock retido após aceite; feedback independente da
  seed; hover/active/variant distintos por tema; fronteira Primary == seed só cai junto do primeiro.
  Concorrência, downgrade e crédito revogado têm cobertura HTTP, mas não lock real de linha.
- **Docs:** `PARAMETROS.md` ainda publica os valores antigos; `ADR-MAPEAMENTO.md` e `docs/colors/README.md`
  dizem que os originais locais permanecem; `docs/SPEC.md` §9 termina na F5; `.env.example` não tem nenhuma
  variável `COLORS_LAYOUT_SYSTEM_*`; `CHANGELOG.md` não tem entrada do track; RUNBOOK não tem seção do
  Layout Harmonizer.
- **Versões/migrations:** 3 migrations (`create_layout_systems_table`, `create_layout_system_retired_slugs`,
  `create_color_tag_layout_system`); nenhuma nova planejada. `LayoutSystemState` valida
  `algorithm`/`suggestions` em 1..999, então linhas antigas continuam válidas.

## Escopo

1. **Correções aprovadas** (motor, estado, UI, servidor, exports): teto de croma; alpha na secundária;
   rejeição de alpha no fundo da seed; teste de concorrência opt-in; os 4 testes faltantes.
2. **Auditoria completa do track** (F1–F6) contra SPEC/TOKENS/ADRs/TESTES, com correção do que aparecer.
3. **Entrega documental:** aceite, ADR-178, RUNBOOK §12, `.env.example`, CHANGELOG, SPEC, README colors,
   ADR-MAPEAMENTO, PARAMETROS, CONTRATO-TECNICO/DADOS/TESTES, glossário se houver termo novo; remoção dos
   22 ADRs locais.
4. **Gates reais** + verificação Playwright do CSS + relatório detalhado; parada sem commit/push.

Fora de escopo: novas fases, migrações, dependências, endpoints, mudanças de cota/produto, deploy.

## Correção 1 — Teto de croma (motor + versões + fixtures)

Arquivo central: `resources/js/tools/layout-harmonizer/engine/params.ts`.

| Token | Tema | Hoje | Novo |
| --- | --- | --- | --- |
| `on-background` | claro | C 0.015 | **0.012** |
| `on-surface` | claro | C 0.015 | **0.012** |
| `on-surface-variant` | claro | C 0.020 | **0.012** |
| `hairline` | escuro | C 0.022 | **0.020** |

Sem mudar matiz/clareza nem os demais parâmetros. Em seguida:

- `LAYOUT_PARAMS_VERSION` 1 → **2**; `LAYOUT_ALGORITHM_VERSION` 1 → **2**
  (`engine/types.ts`, referência em `engine/generate.ts`); `config/colors.php` `versions.algorithm` → **2**.
  `LAYOUT_SCHEMA_VERSION`, `LAYOUT_SNAPSHOT_VERSION`, `LAYOUT_STATE_VERSION` e `LAYOUT_SUGGESTIONS_VERSION`
  ficam em **1** (envelope aditivo, ADR-169).
- Regenerar as **80 fixtures** douradas (10 seeds × 4 estratégias × 2 temas) e revisar o diff: só os tokens
  afetados (e pares/razões que os usam) podem mudar; qualquer outro token diferente invalida a regeneração.
- Atualizar valores fixos em testes que codificam a tabela aprovada (razões/hex de `on-*`/hairline), sem
  enfraquecer asserções; novo teste de propriedade: para toda fixture, croma de superfícies/textos
  claro ≤ 0.012 e escuro ≤ 0.020.
- `PARAMETROS.md`: corrigir a tabela e registrar “Emenda da auditoria (F6, 2026-11-22)” com versão 2.
- Sistemas salvos v1 passam a hidratar em **compatível** (banner com versões) e regeneram para 2 ao editar
  entradas; nada de rewrite de linhas.

## Correção 2 — Accent (fechamento documental, sem código)

`TOKENS.md` recomenda conservar o catálogo de 33 tokens com Accent derivado (+120°) em todas as
estratégias, e a implementação já faz isso. Nenhuma mudança de motor, versão ou fixture. O ADR-178 fecha a
nota “revisável” do relatório da F1 apontando para a recomendação do TOKENS e para o ADR-163.

## Correção 3 — Alpha na secundária manual

### Motor (`engine/`)

- `GenerateLayoutInput` ganha `secondaryBackground?: Rgb | null` e `secondary?: Rgba | null` (deixa de ser
  só `Rgb`); documentar o novo comportamento.
- Se `secondary.a < 1`: exigir `secondaryBackground` opaco; compor com `compositeOver` (já existe em
  `engine/alpha.ts`) e derivar a partir da cor efetiva. Sem fundo → novo erro
  `LayoutEngineError('secondary-alpha-without-background')` (entra na união `LayoutEngineErrorCode`).
- `secondary.a === 1`: comportamento atual (fundo ignorado/opcional), sem erro.
- `LayoutSystemResult`/`LayoutSnapshot` ganham informação opcional da secundária
  (`{ original: Rgba; effective: Rgb; background: Rgb | null }`), no mesmo espírito de `seed`; campo
  **aditivo** com `snapshotVersion` 1. `secondarySource` continua `'auto' | 'manual'`.
- `lib/inputs.ts`: `parseSecondaryText` passa a preservar alpha (`Rgba | undefined`); novo
  `parseSecondaryBackgroundText` (opaco). `parseBackgroundText` (seed) passa a devolver resultado
  discriminado para **detectar alpha** em vez de descartar em silêncio.
- `lib/regenerate.ts` passa `secondaryBackground` e retorna `null` para secundária/fundo inválidos; o
  comentário do contrato é atualizado.

### Estado, controlador e UI

- `LayoutStateInputsV1` ganha `secondaryBackgroundText: string` (opcional na leitura para estados antigos);
  `LayoutSystemStateV1` ganha `secondaryBackground?: Rgb | null` (só presente quando a secundária tem alpha).
- `buildLayoutState(inputs, secondary: Rgba | null, secondaryBackground: Rgb | null, snapshot)` e todos os
  chamadores (`LayoutHarmonizerPanel.vue` → `inputsOf()`/`currentState()`/`saveSource`, `regenerate.ts`,
  `useLayoutDraft`/draft) atualizados.
- `composables/useLayoutSystem.ts`: novo ref `secondaryBackgroundText`, validação (fundo obrigatório/opaco
  quando a secundária tem alpha; alpha no fundo de composição inválido), repasse ao motor e aviso
  `secondary-alpha-without-background`.
- `components/SecondaryField.vue`: campo “Fundo de composição”, exibido quando a secundária tem alpha (ou
  sempre opcional, com dica), com hint/erro, reset junto do secundário; nada de texto hardcoded.
- i18n (5 locales, `lang/*/tools.php`): `secondary_background_label`, `_hint`, `_invalid`, `_alpha_invalid`,
  `seed_background_alpha_invalid` e o aviso da secundária; paridade no `TranslationsParityTest` e no
  `layout-harmonizer-i18n.test.ts`.

### Servidor (`app/Support/Colors/LayoutSystemState.php`)

- Aceitar `state.secondary` RGBA **somente** quando `secondarySource === 'manual'` e
  `state.secondaryBackground` for RGB opaco válido; secundária opaca continua sem exigir fundo (compatível
  com estados antigos). `secondaryBackground` presente com secundária opaca é aceito se válido.
- `inputs.secondaryBackgroundText` validado como string (≤ `layout_systems.input_max_length`, 120);
  ausente = vazio, mantendo linhas v1 válidas.
- Nenhuma migration: o JSON do `state` é opaco para o banco; só a validação estrutural muda.

### Exports e tipos

- Valores do quadro continuam sendo os efetivos; MD passa a listar, quando manual com alpha:
  secundária original/efetiva/fundo de composição (mesmo padrão da seed). CSS/ZIP inalterados na forma.
- `resources/js/types/layoutSystem.ts` e `export/types.ts` acompanham os campos opcionais.

## Correção 4 — Alpha no fundo da seed (rejeição explícita)

`parseBackgroundText` hoje aceita e descarta alpha. Novo resultado discriminado
(`vazio | inválido | opaco | alpha`) alimenta o controlador: alpha no fundo gera erro i18n
`seed_background_alpha_invalid` e impede o quadro (nada de composição silenciosa). Testes no parser, no
controlador e no estado salvo (payload com alpha no `backgroundText` é rejeitado ou normalizado — decidir
pelo lado seguro: rejeitar no cliente e não gravar estado inválido).

## Testes (Vitest e Pest)

Novos/ajustados em `resources/js/__tests__/`:

| Arquivo | Casos |
| --- | --- |
| `layout-harmonizer-overrides.test.ts` (ou `editState`) | lock de token **permanece** depois de aceitar sugestões; Primary recebe ajuste; demais locks intactos |
| `layout-harmonizer-engine.test.ts` | feedback idêntico entre seeds diferentes (mesma estratégia/tema); hover/active/variant distintos (primária e feedback) por tema/estratégia; Primary inicial === seed efetiva por tema; alpha na secundária (com/sem fundo, fundo com alpha, composição correta); alpha no fundo da seed rejeitado |
| `layout-harmonizer-state.test.ts` | `secondaryBackground` só com alpha; round-trip de estado; estados antigos sem os campos continuam válidos |
| `layout-harmonizer-params.test.ts` (novo) | teto de croma em todas as fixtures; versões 2/1 conforme aprovado |
| `layout-harmonizer-fixtures.test.ts` | regeneração das 80 fixtures e diff revisado (só tokens afetados) |
| `layout-harmonizer-export-markdown.test.ts` | metadados da secundária com alpha; nenhuma regeneração |
| `layout-system-editor`/painel/controlador | campo de fundo de composição, erros i18n, salvar/regenerar com alpha |
| `layout-harmonizer-i18n.test.ts` | chaves novas nos 5 idiomas, sem `@`/`|` crus |

Pest:

- `tests/Feature/Colors/LayoutSystemHttpTest.php`: estado manual RGBA com/sem fundo; fundo inválido; estado
  antigo sem os campos; comprimento de `secondaryBackgroundText`.
- **`tests/Feature/Colors/LayoutSystemConcurrencyTest.php` (novo, opt-in):** roda só com
  `LAYOUT_CONCURRENCY_TEST=1`, driver `mysql`/`mariadb` e nome do banco terminando em `_test`; caso contrário
  `markTestSkipped`. Dois PDOs independentes: A inicia transação e `SELECT ... FOR UPDATE` na linha do
  usuário; B tenta o mesmo (com `innodb_lock_wait_timeout` curto) e espera; commit de A libera B — prova o
  lock que sustenta a cota conjunta. Documentar o comando no aceite/relatório; sem MySQL de teste
  descartável, registrar como **skipped** com o motivo (nunca apontar para dev/prod).

## Auditoria do track (matriz do aceite)

Percorrer o prompt da F6 e preencher a matriz `# | Requisito | Implementação | Teste | Tipo | Status`
cobrindo, no mínimo:

1. Motor determinístico, parâmetros e versões; 2. catálogo de 33 tokens e semântica; 3. contraste AA/AAA
(16 pares, alvo e avisos); 4. 4 estratégias × 2 temas; 5. seed (opaca/alpha + fundo); 6. secundária e
derivadas; 7. sugestões e aceite (Primary == seed até aceite); 8. overrides/bloqueios; 9. entradas e picker
compartilhado; 10. quadro/painel e acessibilidade (teclado, foco, ARIA); 11. rascunho anônimo; 12.
persistência, cota conjunta (25 incl. arquivadas), ownership/404; 13. público/SEO/sitemap/crédito; 14.
downgrade, arquivamento e exclusão; 15. concorrência e integridade; 16. exportações MD/CSS/ZIP; 17.
protótipos somente leitura; 18. privacidade/logs/i18n/rede; 19. migrations e efeitos; 20. regressões nas
outras tools.

Correções que surgirem entram nesta fase (sem reabrir produto); se alguma exigir decisão de produto, parar
e perguntar. Itens não corrigíveis ficam na tabela de pendências com gravidade/impacto/evidência/ação.

## Documentação e entrega

| Documento | Conteúdo |
| --- | --- |
| `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md` | Escopo; matriz completa com situação real; resultados dos gates; checklist manual numerado (inclui os 7 passos da F5 e fluxos F1–F4); desvios; pendências `ID | Pendência | Gravidade | Impacto | Evidência | Ação`; deploy/rollback; migrations e efeitos; decisões não aprovadas |
| `docs/adr/ADR-178-colors-lh-auditoria-final.md` | Resultado da auditoria; emenda do teto de croma (valores, versão 2, fixtures); Accent fechado pelo TOKENS/ADR-163; secundária com alpha (fundo explícito, envelope aditivo, versões); fundo de seed com alpha rejeitado; teste de concorrência opt-in; remoção dos 22 ADRs locais; itens deferidos |
| `RUNBOOK.md` §12 | Layout Harmonizer: deploy pelo cron; rollback só de aplicação (dados intactos); efeitos das 3 migrations; `algorithm 2` (linhas v1 → compatível, sem backfill); quotas/limites; diagnóstico (logs sem estado/schema, sintomas comuns); como desligar/limitar sem migration |
| `.env.example` | `COLORS_LAYOUT_SYSTEM_NAME_MAX_LENGTH=80`, `..._DESCRIPTION_MAX_LENGTH=500`, `..._SYSTEMS_PER_PAGE=20`, `..._PREVIEW_COLORS=6`, `..._SYSTEMS_SITEMAP_LIMIT=500`, `..._STATE_MAX_BYTES=262144`, `..._INPUT_MAX_LENGTH=120`, `..._EXPORT_PREFIX_MAX_LENGTH=32`, `..._EXPORT_SCOPE_MAX_LENGTH=64`, `COLORS_TAGS_PER_LAYOUT_SYSTEM=10` (comentado o cuidado com `(int) ''`) |
| `docs/SPEC.md` §5/§9 | “F6 concluída em 2026-11-22”; track encerrado; ADR-178 |
| `docs/colors/README.md` | F6 concluída/track concluído; ADRs 145–178; originais locais removidos |
| `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` | Status F6; remoção dos 22 originais; contagem final |
| `docs/colors/layout-harmonizer/PARAMETROS.md` | Teto de croma corrigido; versões 2; emenda datada |
| `docs/colors/layout-harmonizer/CONTRATO-TECNICO.md` / `DADOS.md` / `TESTES.md` | Campos novos do estado/entrada; versão 2; testes novos e comando opt-in do MySQL |
| `CHANGELOG.md` | “## Track Layout Harmonizer: painel semântico, edição e exportações - 2026-11-22” com Adicionado/Corrigido/Operação e ADR 145–178 |
| `docs/glossario.md` | Só se houver termo novo (ex.: “teto de croma”, “secundária com fundo de composição”) |
| `docs/colors/layout-harmonizer/adr/` | **Remover os 22 arquivos** no aceite (conteúdo preservado em ADR-145–166) |

## Etapas e gates

| Etapa | Conteúdo | Gate ao fim |
| --- | --- | --- |
| 0 | Salvar surveyfacts/plansfacts e o plano em `docs/claude-code/plans/...`; baseline dos gates (antes das mudanças) | gates verdes no baseline |
| 1 | Teto de croma + versões + fixtures + testes de parâmetros | Vitest de motor/params/fixtures |
| 2 | Alpha na secundária: motor, parsers, estado, erros | Vitest de motor/estado |
| 3 | Servidor: `LayoutSystemState`, config, testes HTTP | `composer test` |
| 4 | UI/controlador/i18n (5 locales) + exports | Vitest de UI/i18n/export |
| 5 | Testes faltantes (lock, feedback, variantes, Primary == seed) + concorrência opt-in | Vitest/Pest; opt-in registrado |
| 6 | Auditoria completa do track e correções remanescentes | matriz preenchida |
| 7 | Aceite, ADR-178, RUNBOOK §12, `.env.example`, CHANGELOG e docs; remover ADRs locais | revisão documental |
| 8 | Gates completos, Playwright do CSS e relatório | todos verdes; parada |

Gates reais (sem resultados inventados):

```bash
composer test
npm run test
npm run types:check
npm run format:check
npm run lint:check
npm run build
# opt-in, apenas com banco MySQL de teste descartável:
LAYOUT_CONCURRENCY_TEST=1 php artisan test --filter=LayoutSystemConcurrencyTest
```

## Verificação end-to-end

1. **Automatizada:** os gates acima; teste de croma nas fixtures; alpha da secundária (composição, erros,
   estado salvo, MD); exports sem regeneração e sem rede (spy de `fetch` no caminho de exportação).
2. **CSS real (Playwright):** gerar o CSS de uma fixture (arquivo temporário não versionado que imprime o
   CSS via Vitest, removido ao fim), aplicar em página `data:text/html` no Chromium e conferir
   `getComputedStyle` no claro (sem atributo e `data-theme="light"`) e no escuro (`data-theme="dark"`),
   incluindo fallback HEX/rgba e bloco OKLCH; ZIP abre com os dois arquivos.
3. **Checklist manual** do aceite: anônimo (card bloqueado/CTA, cópia individual), painel (teclado/foco,
   troca de estratégia/tema, sugestões e aceite com lock), secundária com alpha e fundo, rascunho, salvar
   (quota 25 conjunta), editor, arquivar, downgrade, página pública/sitemap, exportação nos 5 idiomas,
   comportamento compatível de um sistema salvo v1 (banner + versões).
4. **Concorrência:** rodar o opt-in se houver MySQL de teste; senão registrar skipped com motivo.

## Riscos e mitigação

- **Fixture cega:** regenerar sem revisar mascara regressão → diff revisado + teste de croma e de tokens
  não afetados.
- **Mudança de versão quebra linhas:** subir só `algorithm` mantém linhas v1 compatíveis (ADR-169);
  `LayoutSystemState` já aceita 1..999; nenhuma migration.
- **Alpha additivo no snapshot v1:** campos opcionais, leitura tolerante, testes de estado antigo.
- **UI complexa na secundária:** campo só aparece/é exigido com alpha; estados antigos não mudam.
- **Teste MySQL perigoso:** guarda tripla (env + driver + nome `_test`); sem banco elegível, skipped.
- **Remoção dos ADRs locais:** só depois do aceite; conteúdo idêntico já em `docs/adr/`; sem `git rm` manual
  (o usuário commita).
- **Sem defaults silenciosos:** fundo da secundária sempre explícito; alpha no fundo da seed rejeitado.

## Parada

Salvar `docs/survey/20261122_layout_harmonizer_f6_auditoria_surveyfacts.md` e
`docs/plans/feature_layout-hamonizer-fase-4/20261122_f6_auditoria_plansfacts.md` ao iniciar a implementação;
plano em `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261122_feature_layout-hamonizer-fase-4_f6_auditoria.md`;
relatório em
`docs/claude-code/reports/feature-layout-hamonizer-fase-4/20261122_feature-layout-hamonizer-fase-4_f6_auditoria.md`.
Ao fim: **parar para revisão**, sem commit/push/deploy e sem iniciar nova fase.
