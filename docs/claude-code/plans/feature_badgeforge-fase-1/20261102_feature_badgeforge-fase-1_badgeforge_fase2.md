# BadgeForge — Fase 2: PNG, download, docs e fontes do badge

- **Branch:** `feature/badgeforge-fase-1`
- **Data:** 2026-11-02
- **Survey:** `docs/survey/20261102_badgeforge_fase2_surveyfacts.md`
- **Plansfacts:** `docs/plans/feature_badgeforge-fase-1/20261102_badgeforge_fase2_plansfacts.md`

## Contexto

A F1 (núcleo SVG) está entregue e revisada. A F2 do SPEC é "PNG + Imagick +
download + docs"; no grill desta sessão o usuário decidiu ampliar com um
**parâmetro `?font=`** usando as fontes que ele colocou em `docs/fonts/`
(Fira Code, Libre Franklin, Space Grotesk, ainda não versionadas).

Fatos verificados nesta sessão:

- Imagick 7.1.1 presente: fixture `flat_logo_left` → PNG 111×20 em ~86 ms
  (frio) / 5–6 ms (quente); bytes **determinísticos** entre renders; texto com
  acento renderiza. Badge de 100 chars → SVG de 689 px (precisa do cap de 600).
- O aviso `Invalid UTF-8 string passed to pango_layout_set_text()` vem do
  libpango e **não** contém conteúdo do badge.
- A rota já é `{ext}` com `where('ext','svg')`; `.png` hoje responde 404 e há
  teste afirmando isso.
- O preview usa `<img src="data:...">`; **fontes da página não entram em SVG
  dentro de `<img>`** — para o preview refletir a fonte escolhida ele precisa
  virar SVG inline.
- `tool-props.json` é comparado por igualdade exata (ordem incluída) em Pest e
  nos testes de página dos 5 idiomas.
- Parsing de TTF validado: `head.unitsPerEm` + `cmap` 4 + `hmtx`. Fira Code tem
  `unitsPerEm=2000`; a tabela normaliza para 1000.
- O scheduler é dirigido pela tabela `task_schedules` (não há agenda estática
  em `bootstrap/app.php`).
- Site usa Inter + JetBrains Mono via Bunny (`vite.config.ts`); as fontes do
  badge serão **self-hosted** a partir de `resources/badgeforge/fonts/`.

## Decisões da sessão (grill)

| # | Decisão |
| --- | --- |
| 1 | UI ganha seletor de formato (SVG/PNG) que muda snippets e links, mais botão **Baixar**; o preview continua 100% local (sem request). |
| 2 | PNG em **1×** natural, com downscale proporcional só acima de **600 px**; fundo transparente (RGBA). |
| 3 | PNG **em cache no disco**: `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`, escrita atômica, comando `badgeforge:prune`. |
| 4 | `?download=1` vale para SVG e PNG; `Content-Disposition: attachment; filename="<label>-<message>.<ext>"` sanitizado + `filename*` UTF-8. |
| 5 | "Docs" = documentação no repo (`API.md`, `OPENAPI.md`, aceite da F2); página pública `/{locale}/docs/badgeforge` fica para quando houver providers (F4/F8). |
| 6 | Sem Imagick → **503** com envelope `ApiError` (`image_unavailable`), log só da classe. |
| 7 | Fontes: parâmetro **`?font=`** com `fira-code`, `libre-franklin`, `space-grotesk`; default continua a pilha do estilo. TTF empacotados no repo, métricas geradas dos próprios arquivos, preview inline para o navegador usar a mesma fonte. |

## Escopo

1. Fontes + métricas por família.
2. Parâmetro `?font=` no servidor e no espelho TS.
3. Endpoint `.png` com Imagick, cache em disco e `?download=1`.
4. UI: fonte, formato, download e preview inline.
5. Docs, ADR, SPEC, glossário, aceite e CLAUDE.md.

## Passos de execução

### E1 — Fontes e métricas

1. Copiar de `docs/fonts/` (deixar `docs/fonts/` como está, fora do git) para
   `resources/badgeforge/fonts/<slug>/` os **Regular + Bold** TTF, `OFL.txt` e
   `README.txt` de cada família:
   - `fira-code/FiraCode-{Regular,Bold}.ttf`
   - `libre-franklin/LibreFranklin-{Regular,Bold}.ttf`
   - `space-grotesk/SpaceGrotesk-{Regular,Bold}.ttf`
2. `config/badgeforge.php`: `fonts_path`, e `fonts` com
   `{slug => [name, family, stack, regular, bold]}`:
   - stack `Fira Code,DejaVu Sans,monospace`
   - stack `Libre Franklin,DejaVu Sans,sans-serif`
   - stack `Space Grotesk,DejaVu Sans,sans-serif`
3. Novo `App\Support\BadgeForge\TrueTypeFont` (parser `head`/`cmap` 4/`hmtx`,
   normalizando para 1000 unidades/em) — protótipo já validado.
4. Novo comando `badgeforge:font-metrics` (`App\Console\Commands`): lê as
   famílias configuradas, gera avanços dos mesmos 192 code points de
   `families.sans` (ASCII + Latin-1) e `family_bold_percent` (média Bold /
   média Regular), **faz merge** preservando `sans`/`helvetica`.
5. `resources/badgeforge/font-metrics.json`: adicionar
   `families.fira-code|libre-franklin|space-grotesk` e
   `family_bold_percent`; estilo inalterado.
6. `FontMetrics.php` e `lib/metrics.ts`: usar
   `family_bold_percent[family] ?? bold_percent`.
7. Tests: parser (avanços conhecidos, Fira Code normalizado por 2000), JSON
   gerado igual aos TTF (guard de drift) e largura maior/menor conforme a
   família.

### E2 — `?font=` no servidor e no espelho TS

1. `App\Support\BadgeForge\BadgeFontCatalog` (slug → name/family/stack/metrics
   a partir da config; `find()` devolve null para desconhecido — cai no default).
2. `BadgeSpec`: novos campos `fontFamily` e `metricsFamily`; `BadgeRequest`
   resolve a fonte do `?font=` e preenche (default = `style->fontFamily` /
   `style->metricsFamily`).
3. `StaticBadgeRenderer` passa a usar `spec->fontFamily` / `spec->metricsFamily`
   mantendo o resto intacto — **a saída padrão não pode mudar**.
4. `BadgeForgeToolProps::for()` ganha `'fonts' => [['slug','name'], …]` na ordem
   da config; atualizar `tests/Fixtures/badgeforge/tool-props.json`.
5. Espelho TS: `types.ts` (`BadgeFont`, `props.fonts`, campos do spec,
   `BadgeFormState.font`), `builder.ts` (default/sample/normalize), `render.ts`
   (usar os campos do spec), `url.ts` (`font=<slug>` só quando não-default).
6. Fixtures novas em `cases.json` (uma por fonte, ex. `flat_font_space_grotesk`,
   `flat_font_fira_code`, `flat_font_libre_franklin`) e `expected/*.svg`
   gerados pelo renderer PHP; Vitest compara byte a byte.
7. `BadgeRequestTest`, `BadgeForgeToolPropsTest`, `StaticBadgeRendererTest`,
   `badgeforge.test.ts`, `badgeforge-render.test.ts`.

### E3 — PNG, cache em disco e download

1. `config/badgeforge.php`: `png` com `max_width=600`, `cache_path` relativo
   (`app/data/tools/badgeforge/static`) e `prune_after_hours=48`.
2. `App\Support\BadgeForge\PngRenderer` (não `final`, para mock em teste):
   - `isSupported()`: `extension_loaded('imagick')`;
   - `render(string $svg): string`: `readImageBlob` + `setImageFormat('png32')`;
     se `width > max_width`, `resizeImage(max_width, …, LANCZOS)` proporcional.
3. `App\Support\BadgeForge\PngCache`: chave `sha256($svg)`, leitura do arquivo,
   escrita atômica (tmp + `rename`), hash do arquivo para o ETag.
4. `StaticBadgeController`: dispatch por `ext` (`svg`|`png`), 503 via
   `ApiError::response('image_unavailable', …, 503)` quando sem Imagick,
   `Content-Disposition` quando `download=1` (sanitizar para ASCII e usar
   `filename*`), ETag/304 iguais ao SVG.
5. `routes/api.php`: `where('ext', 'svg|png')`.
6. Comando `badgeforge:prune` (apaga `*.png` com mtime acima de
   `prune_after_hours`; `--dry-run`), no padrão de `PruneBase64Images`.
7. Tests: PNG 200/`image/png`/dimensões, determinismo, cap 600 (badge longo),
   ETag/304, `download=1` nos dois formatos (nome sanitizado e acento via
   `filename*`), cache em disco (2ª request não rasteriza), prune, 503 com
   `PngRenderer` mockado. Pular os testes de rasterização se Imagick faltar.

### E4 — UI

1. `@font-face` das três famílias em `resources/css/app.css` apontando para
   `resources/badgeforge/fonts/...` (Vite empacota e versiona), pesos 400 e 700,
   `font-display: swap`.
2. `Tool.vue`:
   - `Select` de **Fonte** na seção Avançado ("Padrão do estilo" + 3);
   - `Select` de **Formato** (SVG/PNG) junto de "Incorporar", afetando
     `buildBadgeUrl` e os snippets;
   - botão **Baixar** (SVG/PNG) apontando para a URL com `download=1`;
   - preview passa de `<img>` para **SVG inline** (`v-html="svg"`, conteúdo
     gerado e escapado pelo próprio renderer) para a fonte web valer.
3. `lib/url.ts`: formato (`svg|png`) e `download` como argumentos; testes de URL.
4. i18n nos 5 idiomas (`lang/{en,es,fr,pt_BR,pt_PT}/tools.php`): `fields.font`,
   `fonts.default`, `fields.format`, `formats.svg|png`, `actions.download`.
5. Tests: componente (seletor de fonte muda `font-family` no preview, formato
   muda a extensão do snippet, botão de download tem `download=1`, preview
   inline presente/ausente), builder e url.

### E5 — Documentação

1. `docs/adr/ADR-077-badgeforge-fonte-do-badge.md`: parâmetro `font`, fontes
   empacotadas, métricas derivadas do TTF, preview inline, dependência de
   fontconfig no PNG.
2. `docs/badgeforge/API.md`: `.png`, `font`, `download`, cap 600, 503 e o
   caminho do cache estático; `docs/badgeforge/OPENAPI.md` idem.
3. `docs/SPEC-BADGEFORGE.md`: `font` no §5, nota do cache estático no §7, F2
   entregue no §16 ao final.
4. `docs/glossario.md`: termo "Fonte do badge".
5. `docs/acceptance/BADGEFORGE-FASE-2.md` com os critérios da fase.
6. Novo `docs/guides/badgeforge-render.md`: requisitos do servidor (extensão
   imagick + instalar as TTF em `~/.local/share/fonts` e `fc-cache -f`),
   comando de verificação e `badgeforge:prune`.
7. `CLAUDE.md`: fase atual = F2 (entregue) e ADRs 062–077.

## Verificação

- `composer test` (pint + phpstan + Pest) e `npm run test` ao fim de **cada**
  etapa E1–E4; `npm run types:check` e `npm run lint:check`.
- `git diff tests/Fixtures/badgeforge/expected` após E2: os SVG existentes não
  podem mudar — só os três novos fixtures aparecem.
- Smoke local (`php artisan serve`):
  - `/api/badges/static/build/passing.svg?font=space-grotesk` → largura
    coerente com as métricas novas;
  - `/api/badges/static/build/passing.svg.png` → `image/png`, PNG válido;
  - `?download=1` → cabeçalho de anexo;
  - badge de 100 chars em `.png` → largura 600;
  - segunda requisição do mesmo PNG não reescreve o arquivo de cache;
  - página `/pt-br/tools/badgeforge` monta com as fontes e o preview inline.
- `php artisan badgeforge:font-metrics --check` (ou equivalente) confirma que o
  JSON não divergiu dos TTF.
- `git status` revisado; **sem commit/push** (regra do `CLAUDE.md`).

## Riscos e pontos de atenção

1. **Fonte ausente no servidor**: sem fontconfig com as TTF, o PNG cai no
   DejaVu e o texto pode estourar a largura calculada. Mitigação: guia de
   deploy + checagem por família antes de rasterizar (log só da classe).
2. **Preview inline** troca `<img>` por SVG no DOM: atualizar os testes que
   leem `src`/`alt`; o conteúdo é gerado e escapado por nós (`v-html` seguro).
3. **Cache em disco sem limite**: cada texto único gera um PNG; o
   `badgeforge:prune` precisa entrar na operação (task_schedules) — documentar.
4. **PNG determinístico por máquina**: o ETag muda entre ambientes com versões
   diferentes de Imagick; aceitável (o cache é por máquina).
5. **Paridade byte a byte**: qualquer mudança em `render.ts`/`renderer.php`
   exige regravar fixtures; no E2 a saída default deve ficar idêntica.
6. **Escopo**: a F2 não cria tabelas, contas, providers, presets nem a página
   pública de docs; nada disso entra sem nova fase.
7. `docs/fonts/` continua não versionado; só as 6 TTF selecionadas entram em
   `resources/badgeforge/fonts/`.
