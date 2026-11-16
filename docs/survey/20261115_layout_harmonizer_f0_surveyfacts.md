# Survey — Layout Harmonizer F0 (auditoria e plano)

Data: 2026-11-15
Branch: `feature/layout-harmonizer-fase-1`
Contrato: `docs/colors/layout-harmonizer/PROMPT-INICIAL-CLAUDE.md`
Base: pacote `docs/colors/layout-harmonizer/` v1.0 (2026-11-09) — 12 documentos e 22 ADRs `ADR-LH-001` a `ADR-LH-022`.

## Contexto da tarefa

Nova feature do grupo Colors: uma cor vira um **sistema semântico de layout**
(seed + tokens por papel, quadro nos temas claro e escuro, contraste AA/AAA,
protótipos de site/mobile e exportações MD/CSS/ZIP). O prompt inicial pede
**somente a F0**: inspecionar o Colors real, apontar diferenças entre o código
atual e os requisitos aprovados, listar arquivos, dependências, riscos e testes,
e identificar os parâmetros técnicos ainda a calibrar — sem código de aplicação,
sem migration, sem dependência nova, sem commit.

Regras que permanecem em vigor: GIT só pelo usuário (gitpr); toda string visível
via i18n nos 5 idiomas; tools do cliente 100% no navegador; migrations
retrocompatíveis; não reabrir decisões de produto aprovadas; não inventar
defaults silenciosos.

## Decisões do grill

### Rodada 1 — escopo e registro

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Escopo da sessão | **Somente F0**, sem código; parar para revisão ao fim. |
| 2 | Entregáveis | Os 4 arquivos de registro (relatório, plano em `docs/claude-code/`, `docs/plans/` e `docs/survey/`) + as atualizações de docs previstas na rodada 4. |
| 3 | Numeração dos ADRs | Renumerar na **sequência global** a partir de `ADR-145`, copiando um ADR por fase conforme a implementação avança. |
| 4 | Profundidade da calibração na F0 | Entregar **eixos + tabela candidata marcada como provisória**; os números só valem depois da aprovação da F1. |

### Rodada 2 — dados, superfícies e dependências

| # | Tema | Decisão |
| --- | --- | --- |
| 5 | Persistência | **Nova tabela `layout_systems`**, e não discriminador/JSON em `color_palettes`. |
| 6 | Viewer público | **Nova rota** `/{locale}/colors/s/{slug}`; o viewer de paletas `/{locale}/colors/p/{slug}` fica intocado. |
| 7 | Protótipos | **Promover** `resources/js/tools/palette-generator/prototype/` a módulo compartilhado em `resources/js/lib/prototype/`. |
| 8 | Dependências de exportação | Núcleo de cor **interno** (`@/lib/color`, sem lib nova); `fflate` como dependência **direta** apenas na F5, com autorização e ADR próprios. |

### Rodada 3 — gestão, escopo social e limites

| # | Tema | Decisão |
| --- | --- | --- |
| 9 | Gestão do dono | Página própria `/dashboard/colors/layout-systems` (não reusa Minhas Paletas). |
| 10 | Escopo social | **Fora**: curtidas, comentários, denúncias, notificações, seguir, favoritos, imagem-de-paleta, OG dinâmica, perfil público e API JSON. |
| 11 | i18n | Novo namespace `layout_harmonizer` + `tools.layout-harmonizer.*` nos 5 idiomas. |
| 12 | Limites de nome/descrição | 80 / 500, configuráveis em `config/colors.php` (mesma família de `palettes.name_max_length` e `description_max_length`). |

### Rodada 4 — nomes, relação e rascunho

| # | Tema | Decisão |
| --- | --- | --- |
| 13 | Nome dos ADRs | `ADR-145-colors-lh-<escopo>.md`, escopo em minúsculo (padrão de arquivo do grupo Colors). |
| 14 | Tags | Novo pivô `color_tag_layout_system` (reusa `color_tags`; não reusa o pivô de paletas). |
| 15 | Rascunho | `localStorage` versionado, chave `devtools.layout-harmonizer.draft.v1` (distinto do rascunho de paleta em `sessionStorage`). |
| 16 | Docs globais | Atualizar `docs/SPEC.md`, `docs/glossario.md` e `docs/colors/README.md`. |

## Fatos levantados

### Pacote Layout Harmonizer

- O pacote é **somente documentação**: nada em `app/`, `resources/`, `routes/`,
  `lang/` ou `database/` referencia `layout-harmonizer`, `LayoutSystem` ou
  equivalente. Toda a infraestrutura que ele supõe precisa ser construída.
- Documentos lidos: `PROMPT-INICIAL-CLAUDE.md`, `SPEC.md`, `TOKENS.md`
  (29 tokens + 4 de accent triádico = 33), `CONTRATO-TECNICO.md`, `DADOS.md`,
  `EXPORTACOES.md`, `TESTES.md`, `DESIGN.md`, `PLANO-IMPLEMENTACAO.md`,
  `HISTORICO.md`, `glossario.md`, `FONTES.md`, `PROMPTS-POR-FASE.md`,
  `PROMPT-aprovacao.md`, `PROMPT-Fase1_A/B`.
- Os 22 ADRs locais (`ADR-LH-001` a `ADR-LH-022`) estão fora de `docs/adr/` e
  precisam ser transferidos renumerando (ver `ADR-MAPEAMENTO.md`).

### Registro de tools

- Cada tool é `resources/js/tools/<slug>/{manifest.json,Tool.vue}`; o manifesto
  guarda só identidade e ciclo de vida (sem texto visível) e tem dois leitores:
  `resources/js/tools/registry.ts` (home, busca, chips) e `App\Support\Tools`
  (sitemap, imagens por tool).
- Rota genérica `/{locale}/tools/{group}/{slug}` (`tools.show.grouped`) para
  tools com `group` no manifesto; `ToolController` + `ToolProps::for()`
  entregam as props; `hidden` e `soon` respondem o mesmo 404.
- `resources/js/tools/icons.ts` tem 14 ícones lucide (`binary`, `braces`,
  `clock`, `database`, `file-code`, `file-text`, `image`, `key-round`,
  `palette`, `regex`, `shield-check`, `sparkles`, `swatch-book`, `type`) —
  **não existe** o ícone pretendido para a F2; o mapa precisa ganhar a entrada.
- `palette-generator/manifest.json` usa `category: colors`, `group: colors`,
  `order: 110` — é o precedente direto para o manifesto do LH.

### Núcleo de cor

- `resources/js/lib/color/`: `rgbToOklch`, `mapOklchToSrgb` (busca binária de
  croma, 24 iterações, ε 1e-3), `contrastRatio`/`classifyContrast`
  (AA 4.5/3, AAA 7/4.5), `compositeOver`, `mixOklab`, `parseColor`,
  `PALETTE_TONES`/`buildPaletteScale`, `buildHarmony`.
- Nenhuma biblioteca externa de cor no runtime (ADR-117) — o motor do LH deve
  seguir o mesmo caminho.
- `resources/js/lib/frozenScale.ts` é o precedente de "reabrir sem recalcular":
  reconstrói a escala a partir dos HEX salvos e falha alto em dado corrompido.

### Picker e rascunho

- `components/colors/ColorPickerControls.vue` + `useColorState` cobrem HEX,
  RGB, HSL, OKLCH, OKLab, alpha e eyedropper — reuso direto, nada a criar.
- `resources/js/lib/saveDraft.ts` grava o rascunho de paleta em
  `sessionStorage` (`colors.palette.draft.v1`, TTL 24 h, validação tolerante).
  O LH usa `localStorage` com chave própria e versionamento; o módulo atual não
  serve como está.

### Protótipos

- `resources/js/tools/palette-generator/prototype/` contém `buildPrototype`,
  `renderSvg` (com escape), `rasterizeSvg`, `defaults.ts`, `fonts.ts`,
  `types.ts`, `templates/` e os componentes de tela; tamanhos site/mobile/OG e
  **8 `PaletteRole`s**.
- Está acoplado ao palette-generator por imports internos; a promoção para
  `resources/js/lib/prototype/` altera esses imports e os testes/snapshots
  correspondentes.

### Exportações

- `resources/js/tools/palette-generator/lib/exporters.ts` entrega CSS, SCSS,
  JSON, DTCG, Style Dictionary, TypeScript, Tailwind v3 e v4; helpers
  `downloadText`, `downloadBlob`, `writeClipboard`.
- **Não existe** exportador Markdown nem ZIP. `fflate` 0.8.3 (MIT) só está em
  `node_modules` como transitiva do `jspdf` — não é dependência declarada.
- Não há hoje CSS de tokens com `data-theme` nem fallback HEX → OKLCH para
  tokens semânticos de layout; isso é trabalho da F5.

### Persistência, cota e slug

- `color_palettes` tem `kind` `scale|extracted`, `colors`/`roles` JSON (8 papéis
  × 2 temas), `source`, `slug`/`archived_slug`, `archived_at`,
  `duplications_count` — insuficiente para o estado do LH (seed efetiva,
  overrides/locks, sugestões, tokens por tema, snapshot).
- `PaletteQuota` lê `colors.palettes.free_limit` (25) e `paid_limit` (`null` =
  ilimitado); `used()` conta `$user->colorPalettes()->count()`, incluindo
  arquivadas. Lock por usuário nas actions `CreatePalette`/`DuplicatePalette`.
- `PaletteSlug` (reservados em `colors.slug.reserved`, `max_length` 60,
  `claimOrGenerate`) é injetado em Create/Restore/Update; a F4 precisa extrair
  a base comum sem mudar o comportamento das paletas.
- `PalettePresenter` tem `row`, `editor`, `public` (o público exclui
  `roles`/`source` de propósito) — precedente de shape para o presenter do LH.
- O padrão de autorização é `ensureOwner` → 404 uniforme, sem Policy dedicada.

### Viewer público, sitemap e rotas

- `PublicPaletteController` + `pages/colors/PublicPalette.vue` servem
  `/{locale}/colors/p/{slug}` com 404 uniforme para privada/arquivada/inexistente.
- `App\Support\Sitemap::palettes()` guarda `Schema::hasTable`, usa
  `colors.palettes.sitemap_limit` (500) e só lista públicas ativas.
- As rotas do grupo ficam em `routes/colors.php`; o dashboard autenticado usa
  `auth,verified,active` e `throttle:colors-management`.

### i18n e testes

- `config/locales.php` lista os namespaces expostos (20 hoje, incluindo
  `colors`); cada namespace é um arquivo em `lang/{en,pt_BR,pt_PT,fr,es}/`.
- `TranslationsParityTest` exige chaves idênticas nos 5 idiomas — o namespace
  novo precisa nascer completo.
- Testes: Pest (backend) e Vitest (frontend); `composer ci:check` e
  `npm run build`/`build:ssr` são os gates estáticos do repositório.

### Documentação e deploy

- Último ADR global: `ADR-144-colors-auditoria-final.md` (verificado).
- `docs/SPEC.md` §9 registra o track Colors como concluído em 2026-11-15 e §10
  aponta `docs/colors/` como fonte; `docs/glossario.md` ainda não tem os termos
  do LH; `docs/colors/README.md` não indexa `layout-harmonizer/`.
- Deploy por cron a cada minuto; migrations precisam ser aditivas.

## Diferenças entre código e requisitos (resumo)

A tabela completa está no relatório da F0
(`docs/claude-code/reports/feature_layout-harmonizer-fase-1/`). Os pontos
estruturais são: tabela/estado próprios, cota conjunta Free 25, slug próprio,
nova rota pública e nova entrada de sitemap, pivot de tags próprio, página de
gestão própria, exportadores MD/CSS/ZIP novos, promoção dos protótipos e
namespace i18n novo.

## Parâmetros a calibrar (aprovação na F1)

Eixos: L (OKLCH) por papel/tema; croma por família e teto de gamut; matiz de
feedback; mapeamento para sRGB; variantes versus estados; composição de alpha;
sugestões AA/AAA; fixtures. A tabela numérica **provisória** (L/C por papel nos
dois temas, hues de feedback 25/145/75/245 e as 10 fixtures) está no plano e no
relatório, explicitamente marcada como candidata — nenhum número entra em código
antes da aprovação da F1.

## Riscos registrados

Cota conjunta mexendo em código existente; refactor de slug; promoção dos
protótipos; paridade i18n em 5 idiomas; snapshot congelado nas exportações;
fallback CSS OKLCH; volume de tokens por frame; vazamento de overrides/locks no
viewer público; migrations aditivas; nenhum default silencioso; dependência
`fflate` exige autorização; concorrência entre criar paleta e criar sistema.

## Artefatos da F0

- `docs/claude-code/reports/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md`
- `docs/claude-code/plans/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md`
- `docs/plans/feature_layout-harmonizer-fase-1/20261115_f0_auditoria_plansfacts.md`
- `docs/survey/20261115_layout_harmonizer_f0_surveyfacts.md` (este arquivo)
- `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md`
- Atualizações: `docs/SPEC.md`, `docs/glossario.md`, `docs/colors/README.md`
