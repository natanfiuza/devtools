# BadgeForge — Fase 3: logos personalizados + presets + contas

## Contexto

A F1 (SVG estático) e a F2 (PNG, download e fontes) estão entregues. A F3 do
SPEC é **"logos personalizados + presets + contas"** e hoje não existe nada
disso no código: não há tabelas, models, rotas, props de usuário nem tela de
conta para o BadgeForge. O `BadgeForgeToolProps::for()` nem recebe o usuário, e
o `BadgeRequest` só conhece os 25 logos embutidos.

O objetivo é tornar o BadgeForge útil para quem tem conta: enviar um logo
próprio, reaproveitar a configuração do badge como preset e gerenciar tudo
tanto no builder quanto no painel. A geração e o preview do badge continuam
100% no navegador; o servidor entra apenas nas ações explícitas de conta
(gerenciar logos/presets) e na renderização pública do badge.

Decisões desta sessão:

| # | Decisão |
|---|---|
| 1 | **Badges pessoais (`/b/{username}/{ulid}`) não entram na F3.** Ficam para a F4, junto de providers/PAT (ADR-068). |
| 2 | Logo personalizado é um **SVG multi-path sanitizado**: `<svg>`, `<g>` e `<path>`; guarda-se apenas `viewBox` + paths normalizados no banco; o arquivo cru nunca é servido. |
| 3 | O logo é referenciado na URL pública como **`?logo=u_<ULID>`** (namespace reservado, opaco). O endpoint de imagem não exige autenticação; ULID inexistente/apagado cai para "sem logo", 200. |
| 4 | **Free autenticado: 2 logos; pago: ilimitado.** Presets ilimitados para qualquer conta cadastrada. Tudo em `config/badgeforge.php`. |
| 5 | Gestão na tool (diálogos) **e** em `/dashboard/badgeforge`, com item em `useAccountNav`. |
| 6 | Preset é um **snapshot do formulário**, não um badge publicado; não tem URL pública. Logo órfão no preset cai para "sem logo" com aviso. |
| 7 | Badge com logo personalizado tem **teto de cache** (`max_cache_seconds`, 3600) para que exclusão/renomeação apareça em no máximo 1 h. |

Branch: `feature/badgeforge-fase-1`. Data: 2026-11-02.

## Fora de escopo

Badges pessoais publicadas, providers, endpoint JSON/SSRF, analytics,
exportação CSV de badges, página pública de documentação, admin de logos e
qualquer API pública de gestão (a gestão é web/Inertia autenticada).

## 1. Banco e configuração

### Migrações aditivas

`2026_11_02_000001_create_badge_custom_logos_table.php`

```php
$table->ulid('id')->primary();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 80);
$table->string('view_box', 64);
$table->json('paths');                 // list<{d, fillRule?, clipRule?}>
$table->unsignedInteger('size_bytes'); // tamanho do upload original
$table->timestamps();
$table->unique(['user_id', 'name']);
$table->index(['user_id', 'created_at']);
```

`2026_11_02_000002_create_badge_presets_table.php`

```php
$table->ulid('id')->primary();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('name', 80);
$table->json('payload');               // snapshot do formulário
$table->timestamps();
$table->unique(['user_id', 'name']);
$table->index(['user_id', 'created_at']);
```

O preset guarda `logo` dentro do `payload` (slug embutido ou `u_<ULID>`); o
órfão é calculado na leitura (logo custom sumiu ou embutido saiu da config).
Não há FK nem coluna duplicada de logo.

### Models/factories

- `App\Models\BadgeCustomLogo`: `HasUlids`, `#[Fillable]`, casts
  `paths => array`, `size_bytes => integer`, `belongsTo(User)`, acessor
  `slug(): string` (`u_'.$id`), `scopeForUser`.
- `App\Models\BadgePreset`: `HasUlids`, `payload => array`,
  `belongsTo(User)`, `scopeForUser`.
- `User`: `badgeCustomLogos()` e `badgePresets()` (`HasMany`).
- `BadgeCustomLogoFactory`, `BadgePresetFactory` (ULID explícito, paths
  sintéticos, estados free/paid quando fizer sentido).

### Config (`config/badgeforge.php`)

```php
'custom_logos' => [
    'free_limit' => 2,
    'paid_limit' => null,          // null = ilimitado
    'name_max_length' => 80,
    'max_bytes' => 262144,
    'max_paths' => 200,
    'max_path_length' => 4000,
    'max_total_path_length' => 100000,
    'max_viewbox_extent' => 10000,
    'max_document_depth' => 8,
    'max_cache_seconds' => 3600,
],
'presets' => [
    'name_max_length' => 80,
    'limit' => null,               // null = ilimitado
],
'management' => [
    'rate_limit' => 20,
    'rate_decay_seconds' => 60,
],
```

Nenhum desses números aparece em controller, FormRequest, parser ou Vue.

## 2. Sanitização e renderização multi-path

### DTOs

- `App\Support\BadgeForge\LogoPath` (`readonly`): `d`, `?fillRule`,
  `?clipRule`.
- `Logo` passa a ter `array $paths` (lista de `LogoPath`) e `bool $custom`.
  `LogoCatalog` continua aceitando só `<svg viewBox>` + um `<path>` e monta um
  path; o comportamento dos embutidos não muda.
- `ParsedCustomLogo` (`viewBox`, `paths`, `sizeBytes`) e
  `InvalidCustomLogoException` com código estável.

### Parser (`CustomLogoSvgParser`)

1. Validar bytes/UTF-8.
2. Rejeitar `<!DOCTYPE`, `<!ENTITY`, `<?php` e processing instructions.
3. `DOMDocument::loadXML(..., LIBXML_NONET | LIBXML_NOBLANKS)` — nunca
   `LIBXML_NOENT`/DTD.
4. Raiz única `svg` (namespace vazio ou SVG), profundidade limitada.
5. Só `g` e `path` como descendentes; texto só whitespace; nada de
   `script`, `style`, `image`, `use`, `foreignObject`, `defs`, `text`,
   `animate`, `set`.
6. Atributos: `viewBox` obrigatório e numérico (`w > 0`, `h > 0`, extensão
   limitada); `path@d` obrigatório; `fill-rule`/`clip-rule` só
   `nonzero`/`evenodd`, com herança de `<g>`; rejeitar `on*`, `href`,
   `xlink:href`, `style`, `transform`, `opacity`, `filter`, `clip-path`,
   `mask`, `marker-*`, `url(`. Ignorar `fill`/`width`/`height`/`class`/`id`
   (o logo é recolorido por `logoColor`).
7. `d` com charset `^[MmLlHhVvCcSsQqTtAaZz0-9+\-.,eE\s]+$`, começando por
   `M`/`m`, respeitando limites por path e total.
8. Pelo menos um path. Erros viram 422 com `validation.custom_logo.<code>`.

`Svg::assertSafe` continua como segunda barreira no documento final. O
renderer emite, por path e nesta ordem:
`<path d="..." [fill-rule="..."] [clip-rule="..."] fill="..."/>`. Sem regras,
a saída é exatamente a atual.

### Paridade PHP/TS e fixtures

- `app/Support/BadgeForge/StaticBadgeRenderer.php` e
  `resources/js/tools/badgeforge/lib/render.ts` fazem o mesmo loop.
- `BadgeLogo` no TS vira `{ slug, viewBox, paths: BadgeLogoPath[] }`;
  `logos.ts` adapta os embutidos; `builder.ts` resolve `u_...` contra os dados
  carregados.
- As fixtures históricas de `tests/Fixtures/badgeforge/expected/*.svg` **não
  podem mudar**. Gate: `git diff --exit-code tests/Fixtures/badgeforge` antes
  de adicionar casos novos.
- Casos novos em `cases.json`: `custom_multipath_basic` e
  `custom_multipath_fillrule`; teste de integridade sha256 para as fixtures
  antigas.
- PNG/cache não mudam; teste de fumaça condicionado ao Imagick.

## 3. Resolução pública do logo

- `CustomLogoRepository::find(string $slug): ?Logo` aceita
  `u_<ULID>`, memoiza por request (inclusive "não encontrado"), revalida o
  JSON na leitura e captura `QueryException` (migração ausente/banco fora não
  derrubam o endpoint).
- `BadgeRequest` ganha a dependência e resolve:
  `u_*` → repositório (não cai no catálogo embutido); demais → `LogoCatalog`.
  Qualquer falha → `null` (badge sem logo, 200), preservando a regra atual.
- `cacheSeconds` é limitado a `custom_logos.max_cache_seconds` quando o valor
  bruto começa com `u_`; embutidos seguem o teto atual. A tool recebe o teto
  em `limits.customLogos.maxCacheSeconds`.
- Nenhuma mudança no controller, ETag, `Cache-Control`, PNG ou cache em disco.
  Nenhuma consulta nova para logos embutidos.

## 4. Props e contrato da tool

`BadgeForgeToolProps::for(?User $user = null)`; `ToolProps::for()` repassa o
usuário. Chaves antigas mantêm a ordem; as novas entram depois de `defaults`:

```text
limits.customLogos: { limit, used, maxBytes, maxPaths, maxPathLength,
                      maxTotalPathLength, maxCacheSeconds, nameMaxLength }
limits.presets:     { limit, used, nameMaxLength }
management:         { canManage, canSavePresets, isPaid }
customLogos:        [{ id, slug, name, viewBox, pathCount, sizeBytes }]
presets:            [{ id, name, form, logoOrphaned }]
```

- Convidado/não verificado: `canManage=false`, listas vazias,
  `customLogos.limit=0`, sem query nova.
- Free: `limit=2`, `isPaid=false`; pago: `limit=null`, `isPaid=true`.
- `form` do preset tem a forma de `BadgeFormState`
  (`label`, `message`, `style`, `font`, `color`, `labelColor`, `logoColor`,
  `logoSize`, `logoPosition`, `link`, `cacheSeconds`, `logo`).
- **Paths não viajam nas props.** Elas levam só metadados para não crescerem
  sem teto no plano pago. Ao selecionar um logo custom (ou aplicar preset que
  o use), o cliente busca `GET /badgeforge/logos/{id}` (dono apenas) e
  memoiza; a URL/snippet já é montada com `logo=u_...` mesmo antes do preview.
  Nenhuma requisição por digitação — só na ação explícita.
- A fixture `tests/Fixtures/badgeforge/tool-props.json` (comparação exata em
  Pest e Vitest) é regravada para o convidado; testes separados cobrem Free,
  pago e não verificado.

## 5. Conta, rotas e backend

### Rotas (web/Inertia, CSRF, sem prefixo de locale)

No grupo `auth, verified, active` de `routes/web.php`:

| Método | Caminho | Nome | Ação |
|---|---|---|---|
| GET | `/dashboard/badgeforge` | `dashboard.badgeforge` | página da conta |
| POST | `/badgeforge/logos` | `badgeforge.logos.store` | upload + parse |
| GET | `/badgeforge/logos/{badgeCustomLogo}` | `badgeforge.logos.show` | JSON `{viewBox, paths}` do dono |
| PATCH | `/badgeforge/logos/{badgeCustomLogo}` | `badgeforge.logos.update` | renomear |
| DELETE | `/badgeforge/logos/{badgeCustomLogo}` | `badgeforge.logos.destroy` | excluir |
| POST | `/badgeforge/presets` | `badgeforge.presets.store` | criar |
| PATCH | `/badgeforge/presets/{badgePreset}` | `badgeforge.presets.update` | renomear |
| DELETE | `/badgeforge/presets/{badgePreset}` | `badgeforge.presets.destroy` | excluir |

Grupo em `routes/badgeforge.php`, incluído por `routes/web.php` (padrão de
`settings.php`). Throttle nomeado `badgeforge-management` lendo
`badgeforge.management.*` (registrar no provider existente). Ownership:
`abort_unless($model->user_id === $user->id, 404)` — 404, nunca 403.

### Controllers/requests/actions

- `BadgeForgeAccountController` (página), `CustomLogoController`,
  `BadgePresetController`.
- `StoreCustomLogoRequest` (`name` único por usuário, `logo` arquivo SVG até
  `max_bytes`; parser é a autoridade), `UpdateCustomLogoRequest`,
  `StoreBadgePresetRequest` (`form` com chaves conhecidas),
  `UpdateBadgePresetRequest` (nome).
- `StoreCustomLogo` (transação + `lockForUpdate` na linha do usuário para o
  limite Free; `BadgeForgeQuota` centraliza `free_limit`/`paid_limit`).
- `PresetSpecNormalizer`: reusa as regras de `BadgeRequest`
  (`truncate`, cores, `logoSize`, `cacheSeconds`, link) e valida que
  `form.logo` é `none`, embutido existente ou `u_<ULID>` do próprio dono.
  Extrair `BadgeLink`/`BadgeInput` de `BadgeRequest` sem mudar comportamento.
- Mutations respondem `back()` + `Inertia::flash('toast', ...)`; a tool usa
  `preserveState: true` para não perder o formulário.

## 6. UI

### Tool (`resources/js/tools/badgeforge/`)

- `Tool.vue`: botões "Meus logos"/"Presets" quando `management.canManage`;
  convidado vê CTA de login. O Select de logo lista embutidos + custom
  (metadados); selecionar `u_...` busca os paths sob demanda.
- `LogoLibraryDialog.vue`: upload (useForm + `forceFormData`), lista,
  renomear, excluir com confirmação, aviso de limite Free e CTA de upgrade.
- `PresetDialog.vue`: salvar o formulário atual, aplicar, renomear, excluir;
  aviso de preset órfão (cai para `none`).
- `LogoPreview.vue`: SVG inline multi-path com o mesmo escape do renderer.
- `?preset=<ulid>` lido no `onMounted` (a tool está em `ClientOnly`),
  aplicado se pertencer ao usuário; `history.replaceState` remove o parâmetro.
- `buildBadgeUrl` aceita `logoSlug` explícito para incluir `logo=u_...` antes
  dos paths chegarem; `normalizeBadge` clampa `cacheSeconds` pelo teto custom.
- i18n nos 5 idiomas em `tools.badgeforge.management.*`/`presets.*`.
- Toda geração/preview/snippet continua local; só upload, salvar/aplicar
  preset e buscar paths de logo fazem requisição explícita.

### Painel

- `resources/js/pages/dashboard/BadgeForge.vue` (herda `AccountLayout`):
  "Meus logos" (upload, preview sob demanda, renomear, excluir), "Presets"
  (listar, abrir na tool via `?preset=`, renomear, excluir), limites e CTA de
  upgrade no Free.
- `useAccountNav`: novo item no grupo "Menu", ícone `BadgeCheck`, título
  `account.nav.badgeforge`, href Wayfinder `dashboard.badgeforge`.
- `PublicLayout.vue`: montar `<Toaster />` (hoje só os layouts de conta/admin
  têm), para os flashes das ações da tool aparecerem.
- i18n em `account.badgeforge.*` e `account.nav.badgeforge`.

## 7. Testes

### Pest

- `CustomLogoSvgParserTest`: multi-path, herança de `fill-rule`, ordem;
  rejeições (DOCTYPE/entidade/script/style/image/use/foreignObject/on*/style/
  transform/opacity/url(/viewBox inválido/path sem M/charset/depth/limites).
- `LogoCatalogTest`/`StaticBadgeRendererTest`: embutidos com um path;
  fixtures históricas intactas; casos `custom_*`; `BadgeFixtureIntegrityTest`.
- `BadgeRequestTest`/`StaticBadgeEndpointTest`: `u_` resolve; inexistente,
  malformado, apagado e banco indisponível → 200 sem logo; ETag; teto de
  cache; embutido não consulta o repositório.
- `BadgeForgeToolPropsTest`/`BadgeForgePageTest`: fixture exata no convidado;
  5 locales; Free (2), pago (null), não verificado (sem gestão).
- `BadgeCustomLogoTest`: guest/verificação/suspensão, upload válido sem
  arquivo em disco, nome duplicado, limite Free 2 e terceiro 422, pago acima
  de 2, 404 para outro dono, `show` só do dono, SVG malicioso 422.
- `BadgePresetTest`: criar com logo none/embutido/custom, validação,
  renomear/excluir, 404 alheio, ilimitado, órfão após excluir logo, aplicar.
- `BadgeForgeAccountPageTest`: página, props, estado vazio, `toolUrl`.
- `AnonymizeUser`/`ExportUserData`: apagam/incluem logos e presets.
- `TranslationsParityTest` cobre as chaves novas.

### Vitest

- `badgeforge-render.test.ts`: paths e casos custom byte a byte.
- `badgeforge.test.ts`: URL com `logo=u_...` mesmo sem paths; clamps.
- `badgeforge-component.test.ts`: diálogos, convidado sem gestão, limite Free,
  aplicar preset, órfão; guarda "nenhum fetch por digitação" e exatamente uma
  requisição ao selecionar logo custom.
- `account-panel.test.ts`: novo item de navegação.

## 8. Etapas de execução

| Etapa | Conteúdo | Gate |
|---|---|---|
| E1 | Migrações, models, relations, factories, config | migrate/rollback; Pint/PHPStan |
| E2 | `LogoPath`/`Logo`/parser/exceção, `LogoCatalog` adaptado, extrações de input | testes do parser; fixtures intactas |
| E3 | Loop multi-path no PHP/TS, casos `custom_*`, integridade de fixtures | paridade byte a byte; Pest/Vitest |
| E4 | `CustomLogoRepository`, `BadgeRequest` (`u_`, fallback, teto de cache) | endpoint custom/ausente; embutidos idênticos |
| E5 | `BadgeForgeToolProps(?User)`, fixture, tipos TS, resolução lazy | 5 locales; testes Free/pago |
| E6 | Rotas, controllers, requests, actions, quota, throttle | feature tests de dono/limite |
| E7 | UI da tool + painel + nav + Toaster + i18n | Vitest, types:check, lint |
| E8 | Anonimização/exportação, ADRs, SPEC/API/glossário, aceite, relatório | `composer test`, `npm run test`, `types:check`, `lint:check`, `build` |

Ao iniciar a implementação, salvar este plano em
`docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase3.md`.
Ao final, criar `docs/acceptance/BADGEFORGE-FASE-3.md` e o relatório em
`docs/claude-code/reports/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase3.md`.

## 9. Documentação

- **ADR-078 — Logos personalizados multi-path**: allowlist/parser, persistência
  só de dados normalizados, `u_<ULID>` público, fallback, limite 2/ilimitado,
  teto de cache, lazy asset.
- **ADR-079 — Presets e gestão autenticada**: snapshot do formulário, órfão,
  limites em config, página de conta e exceção documentada à regra
  "tool 100% no navegador" (geração/preview locais; requisições só em ação
  explícita).
- Nota datada em `ADR-066` (presets) e emenda em `ADR-002` (processamento
  local) apontando para a exceção.
- `docs/SPEC-BADGEFORGE.md` §2/§5/§7/§13/§15/§16; `docs/badgeforge/API.md`
  (`logo=u_<ULID>`, sem auth, teto, pós-exclusão); `OPENAPI.md` (descrição do
  parâmetro, sem enum); `docs/glossario.md` (Logo personalizado, Preset,
  Preset órfão, Slug opaco); `CLAUDE.md` (fase F3 e ADRs 062–079).

## 10. Verificação final

```bash
composer test
npm run test
npm run types:check
npm run lint:check
npm run build
```

Smoke local (`php artisan serve`):

- Free envia 2 logos e o terceiro é recusado com mensagem i18n; pago envia 3+.
- SVG com `script`/`onload`/`<style>` → 422 traduzido.
- `?logo=u_<ULID>` renderiza o multi-path; inexistente/apagado → 200 sem logo;
  ETag muda quando o logo muda; `cacheSeconds` efetivo ≤ 3600.
- Preset salva, aparece no painel, aplica no builder; excluir o logo deixa o
  preset órfão e ele carrega sem logo com aviso.
- `/dashboard/badgeforge` nos 5 idiomas; item na navegação; tool sem gestão
  para convidado.
- `git diff tests/Fixtures/badgeforge/expected` vazio para as fixtures
  históricas.
- `git status` revisado; **nunca commit/push** (regra do CLAUDE.md).

## 11. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Fixtures históricas mudarem | Ordem fixa de atributos + gate `git diff` + sha256. |
| `?logo` inválido virar 4xx | Resolver sempre para `null`; testes de ausência/corrupção. |
| PHPStan 7 com JSON `mixed` | DTOs tipados, casts e revalidação na leitura. |
| Concorrência no limite Free | Transação + `lockForUpdate` na linha do usuário. |
| Preset órfão | `logoOrphaned` calculado na leitura; UI cai para `none`. |
| Logo excluído em badge existente | Badge stateless; teto de cache 1 h + aviso na UI; nunca reescrever URL. |
| Payload grande no plano pago | Props só com metadados; paths sob demanda e memoizados. |
| Banco/migração fora derrubar badge | Repositório captura `QueryException` e devolve `null`. |
| Regra "tool local" | ADR-079: só ações explícitas de conta chamam o servidor; teste de guarda. |
| Toaster ausente no layout público | Adicionar `<Toaster />` ao `PublicLayout`. |
| Anonimização/exportação esquecerem dados | Incluir os dois blocos e testar. |
