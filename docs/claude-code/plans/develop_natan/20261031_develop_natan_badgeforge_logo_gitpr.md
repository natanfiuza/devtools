# Plano — BadgeForge: adicionar o logo GitPR ao catálogo embutido

- **Branch:** `develop_natan`
- **Data:** 2026-10-31
- **Origem:** pedido do usuário (logo do projeto GitPR em `?logo=gitpr`)

## Contexto

O BadgeForge (F1 entregue em 2026-10-31) tem 24 logos embutidos registrados em
`config/badgeforge.php` e desenhados a partir de `resources/badgeforge/logos/*.svg`.
O usuário quer o logo do seu próprio projeto, GitPR, disponível em
`?logo=gitpr` e no `Select` de logo da página `/{locale}/tools/badgeforge`.

Fonte: `C:/Users/nataniel/projetos/pessoal/gitpr_projeto/gitpr/docs/logo_48x48.svg`
(fallback PNG 47×47 se o SVG não servir).

O SVG de origem **não é aceito como está** pelo `LogoCatalog`: ele tem
`<?xml?>` + `<!DOCTYPE>`, um `<g transform="translate(0,47) scale(0.1,-0.1)">`
e **três `<path>`**. O catálogo exige um `<svg>` com exatamente um `<path>`
(sem `<g>`), porque o renderer embute `<svg aria-hidden><path d fill/></svg>` no
documento do badge e o `logoColor` é aplicado no path. O PNG não é opção: a F1 é
path-based e não há rasterização de logo.

Solução: **achatar a transformação nas coordenadas do path** (a transformação é
escala uniforme + espelhamento em Y, então todos os comandos — `M`, `m`, `l`,
`c`, `z` — continuam exatos) e concatenar os três paths num único `d`
(subpaths `M`), gerando um `gitpr.svg` no formato da casa. O PNG fica sem uso.

## Decisão de forma

- Slug `gitpr`, nome **GitPR** (nome técnico, como os demais — não passa por
  i18n; vem do registro em config e chega ao cliente em `toolProps`).
- **`viewBox="0 0 24 24"`**, como todos os 24 logos atuais: `LogoCatalogTest`
  exige exatamente esse viewBox para todo logo. A escala final é
  `x'' = (0,1x) · 24/47` e `y'' = (47 − 0,1y) · 24/47`.
- Sem `fill` no arquivo (o `logoColor` pinta), sem `<?xml?>`, sem `<!DOCTYPE>`,
  sem `<g>`/`transform`.
- Registro **depois de `html`** em `config/badgeforge.php`, preservando a ordem
  do catálogo que vira a lista do `Select`.
- Sem código novo de renderer/TS: `logos.ts` e `Tool.vue` já derivam a lista de
  `toolProps` e importam todo `resources/badgeforge/logos/*.svg` por
  `import.meta.glob`.

## Passos

1. Gerar `resources/badgeforge/logos/gitpr.svg`:
   - script temporário **fora do repositório** (ex.: `/tmp`), lendo o SVG do
     gitpr, aplicando a transformação do `<g>` (`x' = 0,1x`, `y' = 47 − 0,1y`) e
     depois a normalização para 24×24 (fator `24/47`) a cada coordenada dos três
     paths, concatenando os `d` num único path e normalizando números (até 3
     decimais, sem zeros à direita);
   - comandos presentes nos paths são `M`, `m`, `l`, `c` e `z` — todos afins:
     absolutos recebem a transformação completa, relativos só a escala
     (`24·0,1/47` em x e `−24·0,1/47` em y), `z` não tem parâmetros;
   - arquivo final numa linha:
     `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="…"/></svg>`;
   - apagar o script temporário depois (não entra no repo).
2. Registrar em `config/badgeforge.php`:
   `'gitpr' => ['name' => 'GitPR', 'file' => 'gitpr.svg'],` após `html`.
3. Atualizar a ponte PHP↔TS: `tests/Fixtures/badgeforge/tool-props.json` ganha
   `{ "slug": "gitpr", "name": "GitPR" }` no fim da lista `logos`.
4. Atualizar o teste que fixa a contagem:
   `tests/Unit/BadgeForge/LogoCatalogTest.php` — `toHaveCount(24)` → `25` e a
   descrição do teste ("carrega os 25 logos…"). `BadgeForgeToolPropsTest` e o
   `badgeforge-render.test.ts` iteram o config/fixture, então acompanham sem
   mudança.
5. Atualizar contagens em docs vivas (não reescrever plan/survey/relatório
   históricos): `docs/acceptance/BADGEFORGE-FASE-1.md` ("24 logos" → 25);
   `docs/SPEC-BADGEFORGE.md`/ADRs só se citarem número (não citam hoje).
6. Salvar este plano em
   `docs/claude-code/plans/develop_natan/20261031_develop_natan_badgeforge_logo_gitpr.md`
   (regra do `CLAUDE.md`).
7. Novo relatório da tarefa em
   `docs/claude-code/reports/develop_natan/20261031_develop_natan_badgeforge_logo_gitpr.md`.

## Testes e verificação

- `vendor/bin/pest tests/Unit/BadgeForge/BadgeForgeToolPropsTest.php` — prova que
  o payload bate com o fixture, que todo logo tem arquivo e que `gitpr.svg` tem
  exatamente `svg` + um `<path>`.
- `vendor/bin/pest tests/Unit/BadgeForge/StaticBadgeRendererTest.php` — fixtures
  continuam byte a byte.
- `npx vitest run resources/js/__tests__/badgeforge-render.test.ts resources/js/__tests__/badgeforge-component.test.ts`
  — todo slug de `tool-props.json` tem SVG cru e o `Select` monta.
- Smoke local (`php artisan serve` + curl):
  `GET /api/badges/static/build/passing.svg?logo=gitpr` → 200, SVG com o path do
  GitPR e `fill` do `logoColor`; `?logo=gitpr&logoColor=%23ff0000` muda o fill.
- Gates finais: `composer test`, `npm run test`, `npm run types:check`,
  `npm run lint:check`.
- Nenhum commit/push (regra do `CLAUDE.md`).
