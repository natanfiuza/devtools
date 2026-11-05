# Survey — BadgeForge: Fase 2 (PNG, download, docs e fontes)

- **Branch:** `feature/badgeforge-fase-1`
- **Data:** 2026-11-02
- **Origem:** `/grill-with-docs` — "Etapa 2: implemente o renderer SVG com os
  cinco estilos, cores e logos embutidos. Adicione testes Pest. Pare para
  revisão." Retificado pelo usuário para **Fase 2** do SPEC.

## Contexto da tarefa

A F1 (núcleo SVG estático) já está entregue e revisada. O usuário pediu o
próximo passo: implementar a **F2 do `docs/SPEC-BADGEFORGE.md`** — PNG via
Imagick, download e documentação — e, durante o grill, ampliou o escopo para
incluir as três fontes que colocou em `docs/fonts/`:

- `docs/fonts/Fira_Code/` (variável + estáticas, OFL)
- `docs/fonts/Libre_Franklin/` (variável + estáticas, OFL)
- `docs/fonts/Space_Grotesk/` (variável + estáticas, OFL)

O objetivo é parar ao final para revisão, sem commit nem push.

## Fatos levantados

### Estado do repositório

- Branch `feature/badgeforge-fase-1`; últimos commits:
  `002a303 fix: corrige campos numericos e ordena logos no BadgeForge`,
  `916dd74 feat: adiciona logo GitPR ao catalogo do BadgeForge`,
  `3020670 feat: adiciona BadgeForge com badges SVG estaticas`.
- `git status` só tem os dois relatórios `.gitpr/` e `docs/fonts/` (não
  versionado).
- A única rota de badge é
  `GET api/badges/static/{label}/{message}.{ext}` com `where('ext', 'svg')`;
  `.png` responde 404 e há teste `'png na F1'` afirmando isso.

### Imagick (verificado empiricamente)

- `php -m` mostra `imagick`; `Imagick::getVersion()` → ImageMagick 7.1.1 Q16
  HDRI.
- Fixture `flat_logo_left.svg` (111×20) → PNG válido em ~86 ms no primeiro
  uso e 5–6 ms depois; bytes **determinísticos** entre renders na mesma
  máquina; texto com acento renderiza.
- Badge com 100 caracteres gera SVG de ~689 px — precisa do cap de 600 px do
  SPEC §3.
- O aviso `Invalid UTF-8 string passed to pango_layout_set_text()` vem do
  libpango, é benigno e **não** contém conteúdo do badge.

### Rota, controller e cache HTTP

- `routes/api.php:57-65`: prefixo `badges`, `where('ext', 'svg')`.
- `StaticBadgeController`: `Content-Type: image/svg+xml; charset=utf-8`,
  `Cache-Control: public, max-age={cacheSeconds}`, ETag =
  `sha256(svg)` e `isNotModified`.
- `BadgeRequest` já lê `cacheSeconds`, `logo`, `logoPosition`, `link` etc.;
  valor cosmético inválido cai no default sem lançar.

### Renderer e espelho TS

- `StaticBadgeRenderer` usa `style->fontFamily` e `style->metricsFamily` no
  texto e na medição; o espelho `lib/render.ts` replica byte a byte.
- `FontMetrics::width()` usa `data.families[$family] ?? families.sans`, com
  `bold_percent` global (106) para peso ≥ 600.
- `font-metrics.json`: `units_per_em: 1000`, `fallback_units: 650`,
  `bold_percent: 106`, `zero_width_ranges`, `families.sans` e
  `families.helvetica` (192 code points cada, ASCII + Latin-1).
- `tool-props.json` é comparado por igualdade exata (ordem incluída) em
  `BadgeForgeToolPropsTest` e `BadgeForgePageTest` (5 locales).
- `resources/js/tools/badgeforge/lib/url.ts` fixa `.svg` e monta a query com
  só o que difere do default.

### Fontes

- O site carrega Inter e JetBrains Mono via Bunny (`vite.config.ts`).
- **SVG dentro de `<img>` não herda fontes da página**: o preview atual
  (`<img :src="dataUri">`) não mostraria a fonte escolhida. Para o `?font=`
  refletir no preview, ele precisa virar SVG inline (`v-html`).
- Parsing de TTF já validado em PHP: `head.unitsPerEm`, `cmap` format 4 e
  `hmtx`/`hhea`.
  - Space Grotesk: upm 1000 (A=630, espaço=257, m=860).
  - Libre Franklin: upm 1000 (A=727, espaço=214, m=906).
  - Fira Code: upm **2000** (A=espaço=m=1200) — normalizar ×1000/2000.
- Para o PNG sair com a fonte certa no servidor, o fontconfig precisa enxergar
  as TTF (`~/.local/share/fonts` + `fc-cache -f`); sem isso o pango cai no
  DejaVu.
- Tamanhos: FiraCode-Regular 188 500 B; LibreFranklin-Regular 108 452 B;
  SpaceGrotesk-Regular 86 588 B.

### Scheduler e comandos

- Não há agenda estática: `bootstrap/app.php::withSchedule` lê a tabela
  `task_schedules` (`is_active`).
- Padrão de prune existente: `PruneBase64Images` (`--dry-run`, corte por
  mtime, `File::isDirectory`).

## Decisões da sessão (grill)

| # | Questão | Decisão |
| --- | --- | --- |
| Q1 | Formato na UI | Seletor SVG/PNG mudando snippets e links + botão **Baixar**; preview continua 100% local. |
| Q2 | Tamanho do PNG | 1× natural, downscale proporcional só acima de 600 px; RGBA transparente. |
| Q3 | Cache do PNG | Disco em `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`, escrita atômica, TTL config, comando `badgeforge:prune`. |
| Q4 | Download | `?download=1` para SVG e PNG; `Content-Disposition: attachment` com nome ASCII + `filename*` RFC 5987. |
| Q5 | Docs da fase | Só no repositório (`API.md`, `OPENAPI.md`, aceite da F2); página pública fica para F4/F8. |
| Q6 | Sem Imagick | 503 com envelope `ApiError` (`image_unavailable`), log só da classe. |
| Q7 | Fontes | Parâmetro `?font=` com `fira-code`, `libre-franklin`, `space-grotesk`; default é a pilha do estilo. |

## Resultado

Plano de execução em
`docs/plans/feature_badgeforge-fase-1/20261102_badgeforge_fase2_plansfacts.md`;
plano técnico completo em
`docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase2.md`.
