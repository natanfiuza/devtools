# Relatório — Layout Harmonizer F0 (auditoria e plano)

Data: 2026-11-15
Branch: `feature/layout-harmonizer-fase-1`
Contrato: `docs/colors/layout-harmonizer/PROMPT-INICIAL-CLAUDE.md`
Fase executada: **F0 somente** — auditoria e plano, sem código.

## 1. Objetivo e o que foi entregue

A F0 do Layout Harmonizer pedia inspecionar o Colors real, apontar as diferenças
entre o código atual e os requisitos aprovados no pacote
`docs/colors/layout-harmonizer/`, listar arquivos, dependências, riscos e testes
por fase, e identificar os parâmetros técnicos ainda a calibrar sem inventar
defaults silenciosos. Também pedia registrar as decisões numa rodada de grill.

Entregues nesta sessão:

| Artefato | Caminho |
|---|---|
| Relatório | `docs/claude-code/reports/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md` |
| Plano | `docs/claude-code/plans/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md` |
| Plano (facts) | `docs/plans/feature_layout-harmonizer-fase-1/20261115_f0_auditoria_plansfacts.md` |
| Survey | `docs/survey/20261115_layout_harmonizer_f0_surveyfacts.md` |
| Mapa de ADRs | `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` |
| Docs globais | `docs/SPEC.md` (§5 e §9), `docs/glossario.md`, `docs/colors/README.md` |

Nenhum arquivo de aplicação foi criado ou modificado; nenhuma migration,
dependência, commit ou push.

## 2. Inspeção realizada (fatos verificados)

### 2.1 Pacote Layout Harmonizer

- O pacote é **exclusivamente documental**. Busca por `layout-harmonizer`,
  `LayoutSystem` e termos equivalentes não encontra nada em `app/`,
  `resources/`, `routes/`, `lang/`, `database/` ou `config/`.
- Conteúdo: `PROMPT-INICIAL-CLAUDE.md`, `SPEC.md`, `TOKENS.md` (33 tokens: 29
  aprovados + 4 do accent triádico), `CONTRATO-TECNICO.md`, `DADOS.md`,
  `EXPORTACOES.md`, `TESTES.md`, `DESIGN.md`, `PLANO-IMPLEMENTACAO.md`,
  `HISTORICO.md`, `glossario.md`, `FONTES.md`, `PROMPTS-POR-FASE.md`,
  `PROMPT-aprovacao.md`, `PROMPT-Fase1_A/B` e 22 ADRs `ADR-LH-001..022`.

### 2.2 Registro de tools

- `resources/js/tools/registry.ts` (glob de `manifest.json`) e
  `App\Support\Tools` leem o mesmo manifesto; `App\Support\ToolProps::for()`
  entrega props por tool; `pages/tools/Show.vue` é genérica.
- Rota `/{locale}/tools/{group}/{slug}` (`tools.show.grouped`, ADR-116);
  `soon` e `hidden` dão 404.
- `resources/js/tools/icons.ts` tem **14 ícones** e **não** tem
  `layout-panel-left` — a F2 precisa acrescentar a entrada (lucide já está
  instalado via `@lucide/vue`).
- `palette-generator/manifest.json` (`category: colors`, `group: colors`,
  `order: 110`) é o molde direto para o manifesto do LH.

### 2.3 Núcleo de cor

- `resources/js/lib/color/` já provê `rgbToOklch`, `mapOklchToSrgb` (busca
  binária, 24 iterações, ε 1e-3), `contrastRatio`/`classifyContrast`
  (AA 4.5/3 e AAA 7/4.5), `compositeOver`, `mixOklab`, `parseColor`,
  `buildPaletteScale`/`PALETTE_TONES` e `buildHarmony`.
- Sem biblioteca externa de cor (ADR-117) — o motor do LH segue o mesmo padrão.
- `resources/js/lib/frozenScale.ts` é o precedente de "reabrir sem recalcular"
  e o modelo para o snapshot congelado do LH.

### 2.4 Picker, protótipos e exportações

- Picker: `components/colors/ColorPickerControls.vue` + `useColorState` cobrem
  HEX/RGB/HSL/OKLCH/OKLab, alpha e eyedropper — reuso direto.
- Protótipos: `tools/palette-generator/prototype/` (12 arquivos, `templates/`)
  renderizam SVG puro por nó, rasterizam o mesmo SVG e conhecem **8
  `PaletteRole`s**; estão acoplados ao palette-generator.
- Exportações: `tools/palette-generator/lib/exporters.ts` entrega CSS, SCSS,
  JSON, DTCG, Style Dictionary, TypeScript e Tailwind v3/v4; **não há
  Markdown nem ZIP**; `fflate` 0.8.3 só existe como transitiva de `jspdf`.
- Helpers `downloadText`/`downloadBlob`/`writeClipboard` existem e são
  reutilizáveis.

### 2.5 Persistência, cota, slug e público (Colors)

- `color_palettes`: `kind` `scale|extracted`, `colors`/`roles` JSON, `source`,
  `slug`/`archived_slug`, `archived_at`, `duplications_count`; insuficiente
  para o estado do LH.
- `app/Support/Colors/PaletteQuota.php`: `used()` conta
  `$user->colorPalettes()->count()` (arquivadas incluídas); Free 25
  (`colors.palettes.free_limit`), pago `null` = ilimitado.
- `PaletteSlug` (reservados em `colors.slug.reserved`, máximo 60) é usado por
  Create/Restore/Update; `PalettePresenter` tem `row`/`editor`/`public`.
- `PublicPaletteController` + `pages/colors/PublicPalette.vue` servem
  `/{locale}/colors/p/{slug}` com 404 uniforme; `Sitemap::palettes()` usa
  `colors.palettes.sitemap_limit` (500) e `Schema::hasTable`.
- Autorização: `ensureOwner` → 404, sem Policy dedicada.

### 2.6 i18n, testes e deploy

- `config/locales.php` expõe 20 namespaces ao frontend; cada um é um arquivo em
  `lang/{en,pt_BR,pt_PT,fr,es}/`; `TranslationsParityTest` exige paridade.
- Gates do repo: `composer ci:check` (Pest), `npm run test` (Vitest),
  `npm run types:check`, `npm run build` e `build:ssr`.
- Deploy por cron a cada minuto; migrations precisam ser aditivas.

## 3. Decisões do grill (2026-11-15)

16 decisões em 4 rodadas; a tabela completa está no survey. Em resumo:

1. Escopo **somente F0**, sem código.
2. Entregáveis: relatório, plano em `docs/claude-code/`, plansfacts e survey.
3. ADRs renumerados na sequência **global após ADR-144**, cópia por fase.
4. Calibração entregue como eixos + tabela candidata provisória.
5. Persistência em **nova tabela `layout_systems`**.
6. Viewer público em **nova rota `/{locale}/colors/s/{slug}`**.
7. Protótipos **promovidos** a `resources/js/lib/prototype/`.
8. Núcleo interno; `fflate` direto só na F5, com autorização.
9. Gestão do dono em `/dashboard/colors/layout-systems`.
10. Recursos sociais **fora de escopo** (curtidas, comentários, favoritos,
    seguir, imagem-de-paleta, OG dinâmica, perfil público).
11. Namespace i18n **`layout_harmonizer`**.
12. Limites 80/500 configuráveis.
13. Arquivos `ADR-145-colors-lh-<escopo>.md` em minúsculo.
14. Pivô novo **`color_tag_layout_system`**.
15. Rascunho em `localStorage` versionado
    (`devtools.layout-harmonizer.draft.v1`).
16. Atualizar `docs/SPEC.md`, `docs/glossario.md` e `docs/colors/README.md`.

## 4. Diferenças entre o código atual e os requisitos aprovados

| # | Requisito | Hoje | Diferença / fase |
|---|---|---|---|
| 1 | Tool em `/{locale}/tools/colors/layout-harmonizer` | Registro por manifesto + rota genérica | Diretório e manifesto ausentes (F2) |
| 2 | Picker HEX/RGB/HSL/OKLCH/alpha | `ColorPickerControls` + `useColorState` prontos | Nenhuma — reuso direto (F2) |
| 3 | Conversões, gamut e contraste | `lib/color` completo | Falta a camada de tokens/motor (F1) |
| 4 | Quadro claro/escuro com 33 tokens | Nada | Motor puro (F1) e UI (F2) |
| 5 | Overrides, bloqueios e sugestões AA/AAA | Nada | F3 |
| 6 | Persistência com estado versionado e snapshot | `color_palettes` sem lugar para esse estado | Nova tabela `layout_systems` (F4) |
| 7 | Cota conjunta Free 25 com paletas | `PaletteQuota` conta só paletas | Somar as duas tabelas sob o lock do usuário (F4) |
| 8 | Slug público próprio | `PaletteSlug` está atado a paletas | Extrair base comum e criar o slug do LH (F4) |
| 9 | Página pública `/{locale}/colors/s/{slug}` | Viewer de paletas em `/p/{slug}` | Nova rota, controller, página e 404 uniforme (F4) |
| 10 | Sitemap com sistemas públicos | `Sitemap::palettes()` | Novo leitor com teto próprio (F4) |
| 11 | Gestão em `/dashboard/colors/layout-systems` | Minhas Paletas em `/dashboard/colors/palettes` | Página própria, tags e actions (F4) |
| 12 | Tags do sistema | Pivô `color_palette_color_tag` | Novo pivô `color_tag_layout_system` (F4) |
| 13 | Exportações MD/CSS(with fallback)/ZIP | Exportadores de paleta; sem MD/ZIP | Novos exportadores; `fflate` na F5 |
| 14 | Rascunho anônimo versionado | `saveDraft.ts` em `sessionStorage`, acoplado à paleta | Módulo próprio em `localStorage` (F2) |
| 15 | i18n nos 5 idiomas | 20 namespaces; paridade testada | Namespace `layout_harmonizer` completo (F2) |
| 16 | Exportar só autenticado; copiar cor livre | Tool pública; dashboard autenticado | Gate de produto no cliente + rotas (F2/F5) |
| 17 | ADRs em `docs/adr/` | 22 ADRs locais; último global ADR-144 | Cópia renumerada por fase (ADR-MAPEAMENTO.md) |

## 5. Parâmetros técnicos a calibrar (aprovação na F1)

Nada abaixo foi implementado; a tabela numérica é **provisória** e só entra em
código depois da aprovação explícita da F1.

Eixos a calibrar: L por papel e tema; croma por família e teto de gamut;
matiz de feedback; mapeamento para sRGB; variantes (variant ≠ hover ≠ active);
feedback estável entre seeds; composição de alpha (original × efetiva);
sugestões AA/AAA sem reescrita automática; fixtures.

Regras de produto já aprovadas (não reabertas): seed efetiva preservada e
Primary idêntica à seed nos dois temas até aceite explícito; claro e escuro
gerados juntos; análoga +30°, complementar +180°, triádica +120/+240°,
monocromática com H constante; feedback independente da seed; seed acromática
não inventa matiz.

Tabela candidata (L / C; feedback com hue fixo):

| Papel | Claro | Escuro |
|---|---|---|
| background | 0.985 / 0.006 (H seed) | 0.160 / 0.015 (H seed) |
| surface | 1.000 / 0.003 | 0.200 / 0.018 |
| surface-variant | 0.955 / 0.010 | 0.245 / 0.020 |
| hairline | 0.900 / 0.012 | 0.330 / 0.022 |
| on-background | 0.220 / 0.015 | 0.955 / 0.006 |
| on-surface | 0.260 / 0.015 | 0.930 / 0.008 |
| on-surface-variant | 0.480 / 0.020 | 0.740 / 0.018 |
| primary | seed exata | seed exata |
| primary-variant | L − 0.10 (piso 0.30), C × 0.90 | L + 0.10 (teto 0.85), C × 0.90 |
| primary-hover / active | − 0.05 / − 0.10 | + 0.05 / + 0.10 |
| secondary | L seed, C × 0.85 | idem |
| accent (triádica) | L/C seed, H + 120° | idem |
| link / hover / active | L seed / − 0.05 / − 0.10 | L seed / + 0.05 / + 0.10 |
| focus-ring | 0.55 / 0.15 | 0.75 / 0.15 |
| error (H 25°) | 0.55 / 0.19 | 0.68 / 0.18 |
| success (H 145°) | 0.55 / 0.15 | 0.72 / 0.15 |
| warning (H 75°) | 0.75 / 0.16 | 0.80 / 0.15 |
| info (H 245°) | 0.55 / 0.14 | 0.72 / 0.14 |
| on-* | branco ou escuro, o de maior razão | idem |
| superfícies/textos | C ≤ 0.012 / ≤ 0.020 | idem |

Fixtures propostas para a F1 (4 estratégias × 2 temas): `#3B82F6`, `#10B981`,
`#EF4444`, `#F59E0B`, `#7C3AED`, `#000000`, `#FFFFFF`, `#808080`,
`oklch(0.70 0.35 20)` (fora do gamut) e `rgba(59,130,246,0.6)` sobre
`#FFFFFF`. Cada snapshot registra HEX, OKLCH, `clipped` e os pares de contraste.

## 6. Plano de arquivos por fase

| Fase | Entrega | Arquivos principais |
|---|---|---|
| F1 | Motor puro | `resources/js/tools/layout-harmonizer/engine/*`; testes `layout-harmonizer-*.test.ts`; `PARAMETROS.md` |
| F2 | UI e registro | `tools/layout-harmonizer/{manifest.json,Tool.vue,LayoutWorkspace.vue}`, componentes/composables, `lib/prototype/*`, `LayoutHarmonizerToolProps.php`, i18n `layout_harmonizer` |
| F3 | Edição e contraste | `engine/overrides.ts`, `engine/suggestions.ts`, `TokenEditor.vue`, `SuggestionDialog.vue`, `ContrastWarnings.vue` |
| F4 | Dados e público | migrations `layout_systems` + pivô, model, actions, controllers/requests, rotas, `LayoutSystem*` supports, `Sitemap`, páginas |
| F5 | Exportações | `tools/layout-harmonizer/export/{markdown,css,zip}.ts`, `ExportPanel.vue`, `fflate` |
| F6 | Aceite | matriz de testes, gates completos, runbook e docs |

Detalhamento completo, incluindo nomes de classes e testes, está no plano
aprovado.

## 7. Dependências

- **Nenhuma dependência nova na F0.**
- Reuso integral: `@/lib/color`, picker, `downloadText`/`downloadBlob`,
  `writeClipboard`, `SaveDraft` como referência, registro de tools.
- `fflate` (MIT 0.8.3) só na F5, como dependência direta — exige autorização e
  ADR próprios antes do `npm install`.

## 8. Riscos

Os 12 riscos registrados no plano (cota conjunta, refactor de slug, promoção
dos protótipos, paridade i18n, snapshot congelado, fallback OKLCH, volume de
tokens por frame, privacidade do viewer público, migrations aditivas, nenhum
default silencioso, `fflate`, concorrência) permanecem com as mitigações
propostas. Nenhum foi materializado nesta fase.

## 9. Testes propostos

Por fase, no plano: Vitest puro na F1 (determinismo, estratégias, gamut, alpha,
contraste, fixtures); Vitest/Pest na F2 (registro, picker, quadro, i18n);
Vitest na F3 (overrides/bloqueios/AA-AAA); Pest na F4 (auth, dono, cota
conjunta, ciclo de vida, duplicação, slug, público, sitemap); Vitest na F5
(MD/CSS/ZIP do mesmo snapshot, fallback OKLCH); F6 com a matriz de aceite e os
gates completos.

## 10. Verificação e estado do repositório

- Nenhum comando de build/teste foi necessário: a F0 não alterou código.
- `git status` desta sessão mostra apenas documentação (novos arquivos em
  `docs/` e a atualização de `docs/SPEC.md`, `docs/glossario.md`,
  `docs/colors/README.md`), além do que já estava não versionado antes da
  sessão.
- Nenhum commit, push ou deploy.

## 11. Pendências e pontos de atenção

1. **Aprovação da tabela de parâmetros**: a F1 depende da aprovação explícita
   dos números da seção 5; nada será implementado antes disso.
2. **Cópia física dos ADRs**: o mapa atribui `ADR-LH-001` (Identidade) e
   `ADR-LH-006` (Processo) à F0, mas o entregável desta fase é documental e o
   prompt pede para não sobrescrever/converter arquivos sem autorização. A
   cópia para `docs/adr/ADR-145` e `ADR-150` (e a remoção dos originais locais)
   fica para o **início da F1**, quando houver implementação para acompanhar.
   O mapeamento completo já está em `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md`.
3. **Ícone da tool**: `layout-panel-left` ainda não existe em
   `resources/js/tools/icons.ts`; acrescentar na F2.
4. **`fflate`**: declarar como dependência direta só na F5, com autorização
   explícita do usuário.
5. **Decisões novas do grill** (tabela própria, rota dedicada, promoção dos
   protótipos, cota conjunta e `fflate`) viram ADR-167+ na fase em que forem
   implementadas, não em F0.

## 12. Conformidade com o prompt inicial

- F0 somente, sem código de aplicação, migrations ou dependências.
- Decisões de produto aprovadas não reabertas.
- Nenhum default silencioso: números apenas como candidatos a calibrar.
- Nenhum log de schema/prompt (não aplicável nesta fase).
- Nenhum commit/push/deploy; nenhuma sobrescrita de CLAUDE.md ou ADRs
  existentes.
- Strings do produto seguem i18n nos 5 idiomas quando a implementação começar.
