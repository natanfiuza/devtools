# BadgeForge — Fase 1: núcleo SVG estático

## Contexto

O BadgeForge existe hoje só como especificação (`docs/SPEC-BADGEFORGE.md`, `docs/badgeforge/*`, 10 ADRs 041–050 ainda não versionados). Não há uma linha de código. A Fase 1 entrega o núcleo de badges **estáticas em SVG**: um endpoint público que gera a imagem, uma página de builder com preview em tempo real e snippets de incorporação, cache HTTP com ETag e o registro completo da tool no catálogo (home, busca, sitemap).

O objetivo imediato é ter badges gerados e servidos pelo próprio devtools, sem depender de terceiros e sem copiar código do Shields.io — só o formato visual que o público de READMEs já reconhece.

**Fora desta fase:** PNG/Imagick, providers externos, endpoint JSON, logos personalizados, badges pessoais, analytics, exportação, `?download=`, OG por tool, endpoints `/api/badgeforge/*` e `?refresh=`.

---

## Decisões fechadas no grill (2026-10-31)

| # | Decisão |
|---|---|
| 1 | Preview 100% no navegador, por um espelho TypeScript do renderer PHP. Fixtures-ouro compartilhadas entre Pest e Vitest, com SVG **byte-a-byte idêntico** para as mesmas entradas. |
| 2 | ADRs do BadgeForge renumerados de 041–050 para **062–071** (arquivos ainda untracked, custo zero) e 5 ADRs novos (072–076) para as decisões da F1. |
| 3 | Proporções visualmente compatíveis com Shields (flat 20 px etc.), mas SVG, larguras e código inteiramente autorais. |
| 4 | Texto na URL: `_` → espaço, `__` → `_`, `-` literal, percent-decoding aplicado pelo router. |
| 5 | Rota no grupo `api` (stateless), fora do `v1` do Sanctum: sem sessão, sem CSRF, sem throttle na F1. |
| 6 | Inválidos: cosmético cai no padrão; logo desconhecido é ignorado; `logoSize` sofre clamp; texto acima de 100 caracteres trunca com `…`; segmento vazio → 404. Nunca 500. |
| 7 | ~24 logos embutidos, SVGs autorais só com `<path d>`, registro em `config/badgeforge.php`. |
| 8 | `logoPosition`: `left` (padrão, logo antes do label) ou `right` (logo depois da message). |
| 9 | Cores: 148 nomes CSS + apelidos de badge (brightgreen, critical…), hex 3/4/6/8, rgb/rgba/hsl/hsla e transparent. |
| 10 | `cacheSeconds`: padrão 86400, clamp 300–86400, inválido cai no padrão. |
| 11 | Categoria nova `badges` (“Imagens”/“Images”) nos 5 idiomas; ícone `image` (já existe). |
| 12 | `link` envolve o SVG inteiro em `<a target="_blank" rel="noopener noreferrer">`, só `http`/`https`. |
| 13 | Limite de 100 caracteres por segmento, em config. |
| 14 | Catálogos (estilos, logos, limites, endpoint) chegam ao cliente por `toolProps`. |
| 15 | Glossário do BadgeForge fundido em `docs/glossario.md`; `docs/glossario-badgeforge.md` é removido. |

---

## Contrato da API

```text
GET /api/badges/static/{label}/{message}.svg
```

- Rota nomeada `badges.static`, no grupo `api` (sem sessão), fora do prefixo `v1`.
- Implementação já preparada para a F2: parâmetro `{ext}` restrito a `svg` agora; a F2 acrescenta `png` sem mexer no resto. **URL sem extensão responde 404** (não é alias).
- Resposta `200`: `Content-Type: image/svg+xml; charset=utf-8`, `Cache-Control: public, max-age=<ttl>`, `ETag: "<sha256 do SVG>"`.
- `If-None-Match` igual → `304`, sem corpo e sem `Content-Type` (comportamento do `Response::isNotModified()` do Symfony).
- Entrada inválida que não seja cosmética → 404 pelo envelope `ApiError` (já é o comportamento de `api/*`).

| Query | Padrão | Regra |
|---|---|---|
| `style` | `flat` | desconhecido → `flat` |
| `color` | `#9f9f9f` (lightgrey) | inválido → padrão |
| `labelColor` | `#555` | inválido → padrão |
| `logo` | — | slug fora do registro → ignorado |
| `logoColor` | `#fff` | inválido → padrão |
| `logoSize` | 10 (14 em for-the-badge) | clamp 8–20 |
| `logoPosition` | `left` | `left`/`right`; outro → `left` |
| `link` | — | só `http`/`https`; outro → sem âncora |
| `cacheSeconds` | 86400 | inteiro clampado 300–86400; inválido → padrão |

**Decodificação do texto (por segmento, nesta ordem):** percent-decode (feito pelo router) → remover caracteres de controle Unicode → `__` → `_`, depois `_` → espaço → truncar em 100 caracteres com `…` → escapar XML na saída. `-` e `.` são literais; `/` só existe como `%2F` (o router decodifica depois do match), e o builder gera essa forma.

**Acessibilidade:** o `<svg>` leva `role="img"`, `aria-label="label: message"` e um `<title>` com o mesmo texto; nada de conteúdo visível fora dele.

---

## Arquitetura

### Backend — `app/Support/BadgeForge/` + controller

| Arquivo | Papel |
|---|---|
| `config/badgeforge.php` | Fonte dos limites e catálogos: cache (default/min/max), `limits.max_text_length = 100`, `limits.logo_size` (min/max), cores nomeadas, registro de logos (slug, name, file, viewBox), família de fonte, caminhos de métricas, estilos e logos. Geometria fica no `styles.json` (fonte única com o cliente). Nenhum limite no código (regra do CLAUDE.md). |
| `app/Support/BadgeForge/BadgeSpec.php` | Value object imutável com o pedido já normalizado: label, message, estilo, cores resolvidas, logo, logoColor/Size/Position, link, ttl. |
| `app/Support/BadgeForge/BadgeRequest.php` | Traduz path + query em `BadgeSpec` (decodificação, truncagem, fallbacks, clamps). Não conhece SVG. |
| `app/Support/BadgeForge/Color.php` | Parser de cor → `{r,g,b,a}` + `fillAttributes()` em SVG 1.1 (`fill="#rrggbb"` + `fill-opacity`). Cobre hex 3/4/6/8, RGB/RGBA, HSL/HSLA, nomes CSS + aliases e `transparent`. |
| `app/Support/BadgeForge/Style.php` | Enum dos 5 estilos + geometria lida de `resources/badgeforge/styles.json`. |
| `app/Support/BadgeForge/FontMetrics.php` | Lê `resources/badgeforge/font-metrics.json` uma vez (cache estático) e calcula largura de texto; fallback generoso para glifos desconhecidos. |
| `app/Support/BadgeForge/LogoCatalog.php` | Resolve slug → `<path d>` do arquivo; ignora slug inexistente; valida que o SVG contém apenas `path`. |
| `app/Support/BadgeForge/Svg.php` | Escapes (`htmlspecialchars` com `ENT_XML1|ENT_QUOTES`), montagem de atributos e `assertSafe()`: allowlist de tags (`svg`, `title`, `a`, `defs`, `linearGradient`, `stop`, `clipPath`, `g`, `rect`, `path`, `text`) e de atributos, rejeitando `script`, `on*`, `javascript:` e qualquer tag fora da lista. Sanitização é por construção — nada de markup do usuário entra no documento. |
| `app/Support/BadgeForge/StaticBadgeRenderer.php` | Contrato público: `render(BadgeSpec $spec): string`. Única peça que conhece o documento SVG. |
| `app/Http/Controllers/BadgeForge/StaticBadgeController.php` | `__invoke`: monta o spec, chama o renderer, calcula ETag e devolve 200/304. Sem log de conteúdo. |

**Estrutura do documento** (uma só, parametrizada por estilo):

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="W" height="H" viewBox="0 0 W H"
     role="img" aria-label="…">
  <title>…</title>
  [<a href="…" target="_blank" rel="noopener noreferrer">]
    <defs><linearGradient id="s" x2="0" y2="100%">…</linearGradient></defs>
    <clipPath id="r"><rect width="W" height="H" rx="R"/></clipPath>
    <g clip-path="url(#r)">
      <rect width="labelWidth" height="H" fill="…"/>        <!-- labelColor -->
      <rect x="labelWidth" width="messageWidth" height="H" fill="…"/> <!-- color -->
      <rect width="W" height="H" fill="url(#s)"/>            <!-- só flat/plastic -->
      <g fill="#fff" [fill-opacity] font-family="…" font-size="11" font-weight="600"
         text-anchor="middle">
        [logo path]
        <text x="…" y="…">label</text>
        <text x="…" y="…">message</text>
      </g>
    </g>
  [</a>]
</svg>
```

Geometria por estilo (números no `styles.json`, não no código):

| Estilo | Altura | Raio | Gradiente | Texto | Padding |
|---|---|---|---|---|---|
| `flat` | 20 | 3 | branco→preto translúcido | caixa normal, 11 px, sombra de 1 px | 6 |
| `flat-square` | 20 | 0 | nenhum | igual ao flat | 6 |
| `plastic` | 20 | 3 | mais forte + borda inferior de sombra | igual ao flat | 6 |
| `for-the-badge` | 28 | 0 | nenhum | 10 px, negrito, caixa alta, letter-spacing 0,1 em | 10 |
| `social` | 20 | 3 | nenhum | 11 px, fundos cinza (`#555`) e azul (`#007ec6`), texto branco | 5 |

Largura = avanço do texto (tabela de métricas) + 2×padding + largura do logo + vão de 2 px quando houver logo. `logoPosition=right` soma no segmento da message e desloca o texto.

### Ativos compartilhados

| Arquivo | Uso |
|---|---|
| `resources/badgeforge/font-metrics.json` | **Fonte única** das larguras de glifo. Lido pelo PHP e importado pelo TS (`resolveJsonModule` já ligado). |
| `resources/badgeforge/styles.json` | **Fonte única** da geometria dos 5 estilos (altura, raio, padding, baseline, gradientes, tamanho padrão de logo, caixa alta/tracking). Lido pelo `Style` em PHP e importado pelo `lib/render.ts`. |
| `resources/badgeforge/logos/*.svg` | 24 logos só com `<path d>` (sem `fill`), para o `logoColor` sobrescrever. |
| `resources/badgeforge/logos` (registro em `config/badgeforge.php`) | slug → nome, arquivo e `viewBox`. |
| `tests/Fixtures/badgeforge/cases.json` + `expected/*.svg` | Fixtures-ouro do **spec já normalizado** (entrada igual para os dois lados) e o SVG esperado ao lado. Pest compara o renderer PHP; Vitest compara o espelho TS, **byte-a-byte**. |
| `tests/Fixtures/badgeforge/tool-props.json` | Payload de `BadgeForgeToolProps::for()`; um teste Pest garante que o servidor produz exatamente isso e o Vitest usa o mesmo arquivo. É a ponte que impede defaults/limites divergirem entre PHP e TS. |

**Snippets** (funções puras em `lib/snippets.ts`):

| Aba | Forma |
|---|---|
| URL | URL absoluta do endpoint com os segmentos codificados (espaço → `_`, `_` → `__`, `/` → `%2F`, resto `encodeURIComponent`) e só os parâmetros que diferem do padrão. |
| Markdown | `![label: message](url)` com `[`/`]` escapados no alt. |
| HTML | `<img src="…" alt="…" width height />` com `&`, `<`, `>`, `"` escapados nos atributos. |
| reST | `.. image:: url` + `   :alt: label: message`. |

O formulário usa um `Select` para estilo/logo/posição, `Input` para textos/cores/`link`/`cacheSeconds`, e uma paleta fixa de ~10 swatches com o hex visível (sem nomes traduzidos) ao lado do campo livre. Ícone e nomes de estilo/logo não são traduzidos (termos técnicos, como os nomes de produto).

### Endpoint → middleware

- `routes/api.php`: novo bloco `Route::prefix('badges')->name('badges.')` **fora** do `prefix('v1')`, sem `auth:sanctum` e sem throttle (SPEC §11: estáticas não são limitadas além da proteção global).
- `ForceJsonResponse` só mexe no `Accept` da request e não afeta a resposta; erros continuam no envelope `ApiError` porque o `shouldRenderJsonWhen`/render de `api/*` já existe.
- `SecurityHeaders` continua aplicado (CSP + nosniff + `X-Frame-Options`). Não se mexe em código de segurança global na F1; um teste garante que a resposta de imagem é servida corretamente com eles.

### Frontend — `resources/js/tools/badgeforge/`

| Arquivo | Papel |
|---|---|
| `manifest.json` | `slug: badgeforge`, `status: available`, `category: badges`, `icon: image`, `order: 95`, keywords. |
| `types.ts` | `BadgeForgeToolProps` (endpoint, estilos, logos, limites, cache) e tipos do builder. |
| `lib/color.ts` | Espelho do parser de cor. |
| `lib/metrics.ts` | Leitura do `font-metrics.json` + cálculo de largura. |
| `lib/render.ts` | Espelho do renderer: `renderBadge(spec, logos, metrics): string`, **mesma string** que o PHP. |
| `lib/url.ts` | Montagem da URL canônica (encode de segmentos, query só com o que difere do padrão). |
| `lib/snippets.ts` | URL, Markdown, HTML e reST puras, testáveis. |
| `builder.ts` | Estado do formulário (defaults, clamps, truncagem, validações) como funções puras. |
| `logs.ts` | Importa os SVGs crus por `import.meta.glob('/resources/badgeforge/logos/*.svg', { query: '?raw', import: 'default' })`, indexados pelo basename. |
| `Tool.vue` | Duas colunas no padrão do AI SQL Builder (`form | preview`), empilhando abaixo de `lg`. Formulário à esquerda; preview à direita como `<img>` com data URI (sem âncora de link, sem cliques que navegam) e abaixo as abas de snippet com `useCopyFeedback()`. |

- `app/Support/BadgeForge/BadgeForgeToolProps.php` + arm em `App\Support\ToolProps::for()` — catálogo e limites por prop (ADR-043), sem dados de path dos logos.
- A tool é 100% cliente: nenhuma requisição é feita pela página. O endpoint só é usado pelo consumidor final do badge.

### Registro e i18n

- `resources/js/tools/types.ts`: `ToolCategoryId` ganha `'badges'`; `categories.ts` ganha `{ id: 'badges', icon: 'image' }` na ordem dos chips.
- `lang/{en,es,fr,pt_BR,pt_PT}/tools.php`: bloco novo `tools.badgeforge.*` (nome, descrição, keywords, intro + campos, estilos, posição, abas de snippet, estados) e `tools.categories.badges` (“Images”/“Imagens”/“Images”/“Imagens”/“Images”). Chaves idênticas nos 5; chaves literais `{`/`}` escapadas conforme ADR-060.
- Atualizar as listas fixas do MVP em `tests/Feature/Tools/ToolsRegistryTest.php` e `resources/js/__tests__/tools-registry.test.ts` (inclui o comentário “nove tools”).

### Documentação

- Renomear os 10 ADRs `041..050-badgeforge` → `062..071-badgeforge`; corrigir `CLAUDE.md` (“ADR-062-badgeforge a ADR-076-badgeforge”).
- Novos ADRs: **072** nome/escopo, **073** formatos, **074** estilos, **075** render em PHP + espelho TS, **076** cache HTTP e endpoint stateless.
- `docs/SPEC-BADGEFORGE.md`: marcar o que a F1 entregou e o que continua adiado.
- `docs/badgeforge/API.md` e `docs/badgeforge/OPENAPI.md`: parâmetros completos da estática (`logoSize`, `logoPosition`, `link`, `cacheSeconds`), `svg` como único formato da F1, 304 documentado.
- `docs/glossario.md`: receber os termos do BadgeForge; apagar `docs/glossario-badgeforge.md`.
- `docs/design/DESIGN.md`: parágrafo da página BadgeForge (form | preview | snippets) no bloco de páginas de tool.
- `docs/acceptance/BADGEFORGE-FASE-1.md`: critérios de aceite da fase.
- Artefatos de sessão: `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_fase1.md`, `docs/plans/develop_natan/20261031_badgeforge_fase1_plansfacts.md`, `docs/survey/20261031_badgeforge_fase1_surveyfacts.md`; relatório final em `docs/claude-code/reports/develop_natan/20261031_develop_natan_badgeforge_fase1.md`.

---

## Plano de testes

### Pest

| Arquivo | Casos |
|---|---|
| `tests/Unit/BadgeForge/ColorTest.php` | hex 3/4/6/8; rgb/rgba; hsl/hsla; 148 nomes CSS + aliases; `transparent`; case/trim; inválidos → null (chamador decide padrão). |
| `tests/Unit/BadgeForge/FontMetricsTest.php` | avanço por glifo, acentos, fallback de glifo desconhecido, texto vazio. |
| `tests/Unit/BadgeForge/BadgeRequestTest.php` | decodificação `_`/`__`, `-` literal, controle removido, truncagem em 100 com `…`, cada fallback/clamp, `logoPosition`, `link` só http(s), `cacheSeconds`. |
| `tests/Unit/BadgeForge/StaticBadgeRendererTest.php` | fixtures-ouro (todas as 5 formas, cores, logo left/right, link, unicode, truncagem, tentativa de injeção); `role="img"`, `<title>`, `aria-label`; ausência de `<script`/`on*=`/`javascript:`; logo inexistente ignorado; SVG só com path. |
| `tests/Unit/BadgeForge/BadgeForgeToolPropsTest.php` | payload do `toolProps` idêntico a `tests/Fixtures/badgeforge/tool-props.json`; todo logo do registro tem arquivo e `viewBox`; todo SVG de logo contém só `<path>`. |
| `tests/Feature/BadgeForge/StaticBadgeEndpointTest.php` | 200 + content-type; `Cache-Control` padrão; `cacheSeconds` e clamps; ETag estável e `304` com `If-None-Match`; 404 em segmento vazio e em `.png`; estilo desconhecido cai em flat; header de segurança não quebra a resposta; nenhum log escrito com o texto do badge (`Log::spy()`). |

### Vitest (`resources/js/__tests__/`)

| Arquivo | Casos |
|---|---|
| `badgeforge.test.ts` | builder: defaults, clamps, truncagem, URL e query canônica; snippets URL/Markdown/HTML/rST com escapes (`(`, `)`, `"`, `[`, `]`). |
| `badgeforge-render.test.ts` | espelho TS contra **as mesmas fixtures-ouro** do Pest (byte-a-byte); mesmas asserções de acessibilidade e ausência de script. |
| `badgeforge-component.test.ts` | `Tool.vue` monta com i18n de teste; digitar label/message atualiza o preview; trocar estilo/cor/logo reflete no SVG; abas de snippet mostram o texto certo; copiar usa `navigator.clipboard` e o feedback de `useCopyFeedback`. |
| `tools-registry.test.ts` (editar) | lista do MVP com `badgeforge`; categoria `badges` em uso; `Tool.vue` presente. |

Comandos: `composer test` e `npm run test` ao fim de cada etapa (o `npm run test` já exige `php` no PATH pelo teste de i18n).

---

## Etapas (cada uma termina verde e para para revisão)

**E0 — Registro da sessão (antes do código).** Gravar, sem tocar em código:
- `docs/survey/20261031_badgeforge_fase1_surveyfacts.md` (levantamento completo da sessão de grill);
- `docs/plans/develop_natan/20261031_badgeforge_fase1_plansfacts.md` (decisões fechadas e plano de execução);
- `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_fase1.md` (cópia do plano no formato exigido pelo CLAUDE.md).

→ Nada a rodar; seguir direto para E1.

**E1 — Núcleo PHP e ativos.** `config/badgeforge.php`, `font-metrics.json`, os 24 logos, `Color`, `FontMetrics`, `Style`, `BadgeSpec`, `BadgeRequest`, `LogoCatalog`, `Svg`, `StaticBadgeRenderer`, fixtures-ouro e os testes Unit. Sem rota ainda. → `composer test`, parar.

**E2 — HTTP e cache.** Rota no `routes/api.php`, `StaticBadgeController`, ETag/304, testes Feature. → `composer test`, parar.

**E3 — Tool no catálogo, i18n e UI.** `manifest.json`, categoria `badges`, `toolProps` PHP, chaves nos 5 idiomas, `Tool.vue` + `lib/` TS (builder, render espelho, snippets, logos), testes Vitest e ajuste das listas do MVP. → `npm run test` + `composer test`, parar.

**E4 — Docs e fechamento.** Renumeração dos ADRs, 5 ADRs novos, SPEC/API/OPENAPI/glossário/DESIGN/aceite, artefatos de sessão e relatório final; rodar a suíte completa e checar `pint`, `phpstan`, `eslint`, `vue-tsc`. → `composer test` + `npm run test`, parar.

Sem commit e sem push em nenhuma etapa (gitpr do usuário).

---

## Riscos e pontos de atenção

1. **Largura de texto sem medir a fonte real.** A tabela própria é aproximada; em leitor com fonte substituta o texto pode encostar. Mitigação: padding generoso + fallback de glifo largo + fixtures; aceitar pequena folga visual.
2. **Glifos fora do ASCII (acentos, CJK, emoji).** Fallback largo evita corte, mas alarga o badge. Documentar como limitação da F1.
3. **Byte-a-byte entre PHP e TS** exige disciplina de formatação (ordem de atributos, separadores, casas decimais). Mitigação: um único template documentado e fixtures que quebram na primeira divergência.
4. **`SecurityHeaders` injeta CSP/nonce em resposta de imagem.** Inofensivo para `<img>`, mas é peso por requisição; qualquer mudança fica para uma decisão posterior (não mexer na F1).
5. **Conteúdo do badge em log de acesso do nginx.** O texto vai na URL (como em qualquer badge). A regra de não logar é do app — não escrever `Log::` com label/message — e o tema merece nota no RUNBOOK/docs da F2.
6. **Marcas dos logos.** 24 desenhos autorais inspirados em marcas de terceiros; revisar atribuição/uso antes de publicar docs públicas.
7. **`{message}.{ext}` e pontos no texto.** Um texto terminado em `.svg` não é expressável literalmente; documentar. `.png` na F1 dá 404 (esperado).
8. **OG por tool e sitemap.** O sitemap pega a tool automaticamente; a imagem OG cai na padrão (ADR-030), aceito para a F1.

---

## Verificação final

1. `composer test` (config:clear + pint + phpstan + Pest).
2. `npm run test` (Vitest, inclui parity de i18n e design tokens).
3. `npm run lint:check`, `npm run types:check`, `npm run format:check`.
4. `php artisan route:list --path=badges` mostra só `GET api/badges/static/{label}/{message}.{ext}`.
5. Smoke local: abrir `/{locale}/tools/badgeforge`, montar um badge com logo e link, copiar os 4 snippets; abrir a URL gerada e conferir 200, `image/svg+xml`, `ETag`; repetir com `If-None-Match` e conferir 304; abrir o SVG e ver `role="img"`, `<title>`, `aria-label`.
