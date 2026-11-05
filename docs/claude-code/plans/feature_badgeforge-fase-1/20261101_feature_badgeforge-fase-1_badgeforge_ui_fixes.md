# BadgeForge — corrigir 4 defeitos da UI e auditar as fases do SPEC

## Contexto

A F1 do BadgeForge está entregue (branch `feature/badgeforge-fase-1`), mas o
usuário encontrou quatro defeitos na página `/{locale}/tools/badgeforge` e pediu
a verificação do andamento das fases de `docs/SPEC-BADGEFORGE.md`:

1. O texto de introdução aparece **duas vezes**.
2. Alterar o campo `badge-logo-size` faz **todos os inputs sumirem**.
3. O `Select` de logo não está em **ordem alfabética**.
4. O texto fica **colado ao logo**; o usuário quer **3px** de espaço à direita.

Fatos confirmados no código:

- **Introdução duplicada**: `resources/js/pages/tools/Show.vue:80,117-119` já
  renderiza `tools.<slug>.intro` para toda tool; `resources/js/tools/badgeforge/Tool.vue:116-118`
  repete a mesma chave — é a única tool que faz isso.
- **Inputs somem**: `<Input type="number">` (Tool.vue:365-374) faz o `v-model`
  nativo do Vue emitir `number` em runtime; `integer()` em
  `resources/js/tools/badgeforge/builder.ts:125-135` faz `raw.trim()` e lança
  `TypeError`, quebrando o computed `spec` durante o render. `badge-cache`
  (Tool.vue:408-414 → `builder.ts:96-101`) tem o mesmo defeito.
- **Ordem do Select**: `props.logos` → `BadgeForgeToolProps::for()` →
  `LogoCatalog::metadata()` (ordem do config), sem sort em PHP ou TS.
  `tests/Fixtures/badgeforge/tool-props.json` é comparado por igualdade exata
  (ordem incluída) em `BadgeForgeToolPropsTest` e `BadgeForgePageTest`.
- **Gap**: `gap: 2` nos cinco estilos de `resources/badgeforge/styles.json`;
  `StaticBadgeRenderer.php:21-56` (espelho `lib/render.ts:15-56`) reserva
  `logoSize + gap` e centraliza com `intdiv`, que come 0,5px quando a largura
  do texto é ímpar (ex.: `flat_logo_left.svg` mostra 1,5px).
- **Fases do SPEC**: só a F1 está entregue. F2–F7 não iniciaram; F8 tem só
  documentação parcial. Não existe rota, tabela, model, controller ou teste de
  F2+ no repositório.

## Decisões

- **Gap = 3** nos cinco estilos de `styles.json` (escolha do usuário). Sem mexer
  na centralização: o vão visível fica 2,5–3,0px com logo à esquerda e
  3,0–3,5px com logo à direita (paridade da largura do texto). Regenera só as
  **7 fixtures com logo**; as 14 sem logo não mudam.
- **Remover o parágrafo de intro do `Tool.vue`**; a chave
  `tools.badgeforge.intro` continua sendo usada pelo cabeçalho genérico.
- **`integer()` e o estado do formulário aceitam `string | number`**:
  número finito vira texto; `NaN`/`Infinity`/vazio/inválido caem no fallback.
  Corrige logoSize e cacheSeconds de uma vez, sem cast no template.
- **Ordenar no servidor** em `BadgeForgeToolProps::for()` com `strcasecmp` por
  `name` e desempate por `slug`; `LogoCatalog::metadata()` mantém a ordem do
  config. O fixture passa a refletir a ordem exibida.
- **Nenhuma fase nova implementada**: a auditoria do SPEC vira tabela no
  relatório; F2+ segue fora de escopo.

## Passos

1. `resources/js/tools/badgeforge/Tool.vue`
   - remover o `<p>{{ t('tools.badgeforge.intro') }}</p>` (linhas 116-118).
2. `resources/js/tools/badgeforge/builder.ts`
   - `BadgeFormState.logoSize` e `cacheSeconds`: `string` → `string | number`;
   - `integer(raw: string | number, ...)`: `typeof raw === 'number' ? String(raw) : raw.trim()`,
     mantendo o regex `^\d{1,9}$` e o fallback (cobre `NaN`, `Infinity`,
     negativos e decimais).
3. `app/Support/BadgeForge/BadgeForgeToolProps.php`
   - ordenar `logos` em `for()` com `usort` + `strcasecmp(name) ?: strcmp(slug)`;
     método privado `logosByName()` documentado.
4. `tests/Fixtures/badgeforge/tool-props.json`
   - reordenar `logos` (ordem `strcasecmp` do nome exibido): Composer, Docker,
     GitHub, GitLab, GitPR, HTML, JavaScript, JSON, Laravel, Markdown, MySQL,
     Node.js, npm, Pest, PHP, PHPUnit, PostgreSQL, Python, React, Redis,
     Symfony, Tailwind CSS, TypeScript, Vite, Vue.
5. `resources/badgeforge/styles.json`
   - `"gap": 3` nos estilos `flat`, `flat-square`, `plastic`, `for-the-badge`,
     `social`.
6. Regenerar as **7 fixtures com logo** em `tests/Fixtures/badgeforge/expected/`:
   `flat_logo_left`, `flat_logo_right`, `flat_square_logo_left`,
   `plastic_logo_right`, `for_the_badge_logo_left`, `social_logo_right`,
   `logo_color_transparent`.
   - script temporário em `storage/framework/testing/badgeforge-gen.php`
     (diretório ignorado pelo Git), espelhando `badgeSpecFromArray`/
     `badgeCases` de `tests/Unit/BadgeForge/StaticBadgeRendererTest.php:17-41`,
     rodado com `php artisan tinker --execute="require base_path('storage/framework/testing/badgeforge-gen.php');"`,
     apagado no fim;
   - conferir no `git diff` que só mudaram larguras/`x` (mesmos paths, cores,
     estrutura) e que só os 7 arquivos mudaram.
7. Testes
   - `resources/js/__tests__/badgeforge-component.test.ts`:
     - definir `badge-logo-size` e `badge-cache`, provar que os dois inputs
       continuam montados e que o preview/snippet refletem os valores
       (`logoSize=12`, `cacheSeconds=600`);
     - afirmar que o `Tool.vue` **não** renderiza a string de `intro`.
   - `resources/js/__tests__/badgeforge.test.ts`: `normalizeBadge` com
     `logoSize: 12`, `4` (clamp 8), `100` (clamp 20), `NaN`, `Infinity`, e
     `cacheSeconds: 600`/`NaN`.
   - `tests/Unit/BadgeForge/BadgeForgeToolPropsTest.php`: nomes ordenados por
     `strcasecmp`, primeiro `Composer`, último `Vue`.
8. Documentação e fecho
   - salvar plano em
     `docs/claude-code/plans/feature_badgeforge-fase-1/20261101_feature_badgeforge-fase-1_badgeforge_ui_fixes.md`;
   - salvar survey/plansfacts da sessão de grill em
     `docs/survey/20261101_badgeforge_ui_fixes_surveyfacts.md` e
     `docs/plans/feature_badgeforge-fase-1/20261101_badgeforge_ui_fixes_plansfacts.md`;
   - relatório em
     `docs/claude-code/reports/feature_badgeforge-fase-1/20261101_feature_badgeforge-fase-1_badgeforge_ui_fixes.md`,
     com o resultado da auditoria do SPEC (tabela F1–F8), a faixa do gap e o
     aviso de cache HTTP.

## Verificação

- `vendor/bin/pest tests/Unit/BadgeForge tests/Feature/BadgeForge` — fixtures
  byte a byte, payload ordenado, catálogo.
- `npx vitest run resources/js/__tests__/badgeforge.test.ts resources/js/__tests__/badgeforge-component.test.ts resources/js/__tests__/badgeforge-render.test.ts`
  — render espelho, números sem crash, intro ausente.
- Smoke local (`php artisan serve` + curl): `?logo=gitpr` e
  `?logo=gitpr&logoPosition=right` continuam 200 e com o espaçamento novo.
- Gates finais: `composer test`, `npm run test`, `npm run types:check`,
  `npm run lint:check`.
- `git status --short` revisado; nenhum commit/push (regra do `CLAUDE.md`).

## Auditoria do SPEC (para o relatório)

| Fase | Escopo | Status |
|---|---|---|
| F1 | Núcleo SVG + builder + snippets + cache | **Entregue (2026-10-31)** |
| F2 | PNG/Imagick/download/docs | Não iniciada |
| F3 | Logos personalizados + presets + contas | Não iniciada |
| F4 | Providers GitHub/npm/Packagist/Docker | Não iniciada |
| F5 | Endpoint JSON + SSRF | Não iniciada |
| F6 | Providers Codecov/Coveralls/Laravel/PHP/Python | Não iniciada |
| F7 | Providers customizados + analytics + admin | Não iniciada |
| F8 | OpenAPI + docs + hardening | Parcial: só docs da F1 |

## Riscos

1. **Gap não é literalmente 3,0 em todo caso**: faixa 2,5–3,5px pela paridade da
   largura do texto; a constante configurada é 3. Registrar no relatório.
2. **Cache HTTP**: badges já servidas com `Cache-Control: max-age=86400` podem
   manter a largura antiga (1px menor) por até um dia em CDN/proxy.
3. **Fixture byte drift**: regravar só os 7 SVGs com logo; não converter EOL.
4. **PHPStan no `usort`**: se reclamar de `mixed`, usar casts `(string)`.
5. **`strcasecmp` é ASCII**: nomes atuais são ASCII; um logo futuro com acento
   pediria `Collator` (ext-intl), fora de escopo.
