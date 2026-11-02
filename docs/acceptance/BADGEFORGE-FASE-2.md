# Critérios de aceite — BadgeForge FASE 2 (PNG, download, docs e fontes)

Gerado em 2026-11-02.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16 + plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase2.md`)
+ ADR-077.

## Escopo da fase

- Fontes empacotadas Fira Code, Libre Franklin e Space Grotesk (Regular + Bold),
  com métricas derivadas dos próprios TTF e parâmetro `?font=` no servidor e no
  espelho TypeScript.
- PNG rasterizado pelo Imagick em 1× natural, fundo transparente, largura máxima
  de 600 px com redução proporcional; cache em disco
  `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png` e comando
  `badgeforge:prune`.
- `?download=1` para SVG e PNG com `Content-Disposition` sanitizado e
  `filename*` RFC 5987.
- UI com seletor de fonte, seletor de formato (SVG/PNG), botão **Baixar** e
  preview em SVG inline (a fonte web vale no preview); i18n nos cinco idiomas.
- Documentação: ADR-077, `docs/badgeforge/API.md`, `OPENAPI.md`, SPEC §3/§5/§6/§7/§16,
  glossário e `docs/guides/badgeforge-render.md`.

Fora da fase: providers, endpoint JSON, badges pessoais, analytics, presets,
página pública de documentação, `?refresh=` e logos personalizados.

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | `/static/build/passing.svg` continua idêntico ao da F1 | fixtures existentes inalteradas em `tests/Fixtures/badgeforge/expected` |
| 2 | `?font=space-grotesk`, `?font=fira-code` e `?font=libre-franklin` trocam `font-family` e a família de métricas; largura muda de acordo com o TTF | 3 fixtures novas + `StaticBadgeRendererTest` + `badgeforge-render.test.ts` |
| 3 | `?font=` é case-insensitive; símbolo desconhecido, vazio, nulo ou array cai na pilha do estilo | `BadgeRequestTest` |
| 4 | `font-metrics.json` tem `families.<slug>` e `family_bold_percent` gerados dos TTF e sem drift | `TrueTypeFontTest`, `BadgeFontCatalogTest`, `php artisan badgeforge:font-metrics --check` |
| 5 | `toolProps.fonts` segue a ordem da config e os TTF `regular`/`bold` existem no disco | `BadgeForgeToolPropsTest` |
| 6 | `GET /static/build/passing.png` responde 200 `image/png` com PNG válido, 1× natural e altura 20 | `StaticBadgePngTest` |
| 7 | PNG é determinístico entre renders e grava **um** arquivo de cache | `StaticBadgePngTest` + `PngCache::path` |
| 8 | A segunda requisição do mesmo PNG vem do disco e não rasteriza de novo | `StaticBadgePngTest` com `PngRenderer` mockado |
| 9 | Badge largo em PNG sai com largura 600 e altura proporcional | `StaticBadgePngTest` (cap) |
| 10 | PNG tem ETag sha256 próprio; `If-None-Match` responde 304 | `StaticBadgePngTest` |
| 11 | `?download=1` vale para SVG e PNG, com nome ASCII sanitizado e `filename*` para acentos | `StaticBadgePngTest` |
| 12 | Sem `download=1` não há `Content-Disposition` | `StaticBadgePngTest` |
| 13 | Sem extensão `imagick`, PNG responde 503 `image_unavailable` sem logar conteúdo | `StaticBadgePngTest` |
| 14 | Extensão `.gif`/sem extensão responde 404 | `StaticBadgeEndpointTest` |
| 15 | `badgeforge:prune` remove PNG com mtime acima da janela, preserva os recentes e `.tmp`, respeita `--dry-run` e configuração | `PruneBadgePngsTest` |
| 16 | `limits.png.maxWidth` aparece no `toolProps` e é idêntico à fixture exata nos 5 idiomas | `BadgeForgeToolPropsTest` + `BadgeForgePageTest` |
| 17 | A UI troca a fonte do preview e da URL; troca formato do snippet e do botão; Baixar leva `download=1` | `badgeforge-component.test.ts` |
| 18 | O preview é SVG inline (não data URI/`<img>`), com `aria-label` | `badgeforge-component.test.ts` |
| 19 | i18n completo (`fields.font`, `fields.format`, `fonts.default`, `formats.*`, `actions.download`) nos 5 idiomas | `TranslationsParityTest` |
| 20 | Gate: `composer test` (Pint + PHPStan + Pest) e `npm run test` verdes; `types:check` e `lint:check` sem erros | os comandos |
| 21 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-02

- `composer test`: Pint ok, PHPStan 0 erros, Pest **850 testes / 17.630
  asserções**.
- `npm run test`: Vitest **888 testes / 68 arquivos**.
- `npm run types:check`: limpo.
- `npm run lint:check`: 0 erros (somente avisos de indentação pré-existentes em
  `.vue`).
- `php artisan badgeforge:font-metrics --check`: `font-metrics.json esta em dia
  com os TTF.`
- `php artisan route:list --path=badges`: somente
  `GET|HEAD api/badges/static/{label}/{message}.{ext}` (nome `badges.static`,
  middleware `api`).
- Comandos registrados: `badgeforge:font-metrics` e `badgeforge:prune`.
- Smoke local (`php artisan serve`, porta 8123):
  - `?font=space-grotesk` → SVG 93 px, `font-family="Space Grotesk,DejaVu Sans,sans-serif"`;
  - `.png` → `image/png`, 98×20 RGBA;
  - badge de 100+100 caracteres → SVG 1404 px, PNG 600×9 (cap proporcional);
  - `?download=1` → `attachment; filename="build-passing.svg"` e
    `filename*=UTF-8''vers%C3%A3o-caf%C3%A9.svg` no caminho acentuado;
  - segunda requisição do mesmo PNG manteve mtime e tamanho do arquivo de
    cache (3 arquivos antes e depois, mesmo hash);
  - `/{locale}/tools/badgeforge` respondeu 200 e a página contém a ação
    “Baixar”.
  - Os PNGs gerados no smoke foram removidos do cache ao fim.

## Notas e desvios do plano

- O `BadgeSpec` carrega um objeto de fonte (`slug`, `name`, `stack`) em vez de
  dois campos soltos; o `toolProps.fonts` expõe o `stack` para o espelho TS
  resolver a pilha exatamente como o PHP.
- A URL do PNG é `/static/{label}/{message}.png`; a linha `.svg.png` do plano
  era engano — com `{message}.{ext}`, `.svg` entraria no texto da mensagem.
- O badge de duas mensagens de 100 caracteres tem largura natural acima de
  600 px (1.404 px no smoke com `x`/`y`), então o teste do cap calcula a altura
  esperada a partir da largura natural devolvida pelo próprio SVG.
- O preview inline usa `v-html`; o conteúdo é gerado e escapado pelo renderer
  (mesmo documento das fixtures), e o `eslint-disable` do `vue/no-v-html` é
  local, com justificativa.
- O PNG depende de fontconfig com as TTF instaladas no servidor; o guia
  `docs/guides/badgeforge-render.md` cobre instalação, verificação e poda.
