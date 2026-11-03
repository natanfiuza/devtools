# BadgeForge — Fase 3 (logos personalizados + presets + contas)

- Data: 2026-11-02
- Branch: `feature/badgeforge-fase-1`
- Plano: `docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase3.md`
- Aceite: `docs/acceptance/BADGEFORGE-FASE-3.md`
- ADRs: `docs/adr/ADR-078-badgeforge-logos-personalizados.md`,
  `docs/adr/ADR-079-badgeforge-presets-e-gestao.md`

## Objetivo

Entregar a F3 do SPEC-BADGEFORGE: logos personalizados, presets e recursos de
conta, mantendo a geração, o preview e os snippets 100% no navegador. O
servidor entra apenas na renderização pública do badge e nas ações explícitas
de conta (buscar paths de um logo escolhido, enviar/renomear/excluir logo,
salvar/renomear/excluir preset).

Decisões da sessão: badges pessoais `/b/{username}/{ulid}` ficam para a F4
(com providers/PAT, ADR-068); logo é SVG multi-path sanitizado; referência
pública `u_<ULID>`; Free 2 logos, pago ilimitado; presets ilimitados; gestão
no builder e em `/dashboard/badgeforge`.

## Resumo executivo

- `?logo=u_<ULID>` renderiza logos personalizados no endpoint stateless, sem
  autenticação; slug inválido, logo apagado ou banco indisponível → 200 sem
  logo. Badges publicados nunca quebram.
- O upload é sanitizado por allowlist (`<svg>`, `<g>`, `<path>`) e só o
  `viewBox` + paths normalizados são persistidos; o arquivo cru nunca vai para
  banco, disco, log ou exportação.
- O renderer PHP e o espelho TS desenham os paths na mesma ordem byte a byte;
  as fixtures históricas da F1/F2 não mudaram.
- `cacheSeconds` de badge com logo custom é limitado a 3600 s, para que
  renomear/excluir apareça em no máximo 1 h.
- Presets são snapshots do formulário (sem URL/cache/analytics), com órfão
  calculado na leitura e queda para `logo = none` com aviso.
- Gestão em diálogos no builder e na página `/dashboard/badgeforge`, com item
  na navegação, limites em `config/badgeforge.php`, throttle nas escritas e
  **404** para registro de outra conta.
- Gates finais: Pint/PHPStan ok, Pest **966 testes**, Vitest **904 testes**,
  `types:check` limpo, `lint:check` 0 erros, `build` concluído.

## Decisões (ADR-078 e ADR-079)

### ADR-078 — logos personalizados

1. Parser com allowlist de tags (`svg`, `g`, `path`), atributos
   (`viewBox`, `d`, `fill-rule`, `clip-rule`) e limites de bytes/paths/
   profundidade; recusa `DOCTYPE`, entidade, PI, `script`, `style`, `image`,
   `use`, `foreignObject`, `on*`, `href`, `style`, `transform`, `opacity` e
   `url(`.
2. Persistência apenas do SVG normalizado (`view_box`, `paths` JSON,
   `path_count`, `size_bytes`); `size_bytes` é o tamanho do upload original.
3. Referência pública `u_<ULID>` em namespace reservado; resolução na leitura,
   memoizada por request, tolerante a `QueryException`.
4. Free 2 logos / pago ilimitado (`custom_logos.free_limit` /
   `paid_limit`), checagem transacional com `lockForUpdate`.
5. Teto de cache de 1 h para badges com logo custom; ETag continua sendo o
   sha256 do corpo.
6. Paths não viajam nas props da página: são buscados sob demanda em
   `GET /badgeforge/logos/{id}`, só pelo dono.

### ADR-079 — presets e gestão autenticada

1. Preset é snapshot normalizado do formulário em `badge_presets.payload`, sem
   URL pública, cache ou analytics.
2. Órfão (`logoOrphaned`) é calculado na leitura; a UI aplica com
   `logo = none` e avisa.
3. Limites em config (`presets.limit = null`), `BadgeForgeQuota` centraliza a
   checagem.
4. Rotas web/Inertia sob `auth, verified, active`, escritas em
   `throttle:badgeforge-management`; dono errado responde 404.
5. `?preset=<ulid>` aplica o snapshot e remove o parâmetro com
   `history.replaceState`.
6. Exceção documentada à ADR-002 (emenda registrada): só ações explícitas de
   conta chamam o servidor; digitar no formulário não dispara requisição.

## O que foi feito por etapa

### E1 — Banco, models, factories e config

- Migrações aditivas `2026_11_02_000001_create_badge_custom_logos_table.php` e
  `2026_11_02_000002_create_badge_presets_table.php`: ULID, FK `user_id` em
  cascata, único `(user_id, name)`, índice `(user_id, created_at)`.
- `App\Models\BadgeCustomLogo` (cast `paths => array`, `slug()` `u_<id>`,
  `scopeForUser`) e `App\Models\BadgePreset` (cast `payload => array`);
  relations `badgeCustomLogos()`/`badgePresets()` em `User`.
- `BadgeCustomLogoFactory` e `BadgePresetFactory`.
- `config/badgeforge.php`: blocos `custom_logos`, `presets` e `management`
  (nenhum limite numérico no código de aplicação).

### E2 — Parser e DTOs

- `LogoPath` (readonly: `d`, `?fillRule`, `?clipRule`), `Logo` com `paths` e
  `custom`, `LogoCatalog` adaptado para montar um path por logo embutido.
- `CustomLogoSvgParser`: validação de bytes/UTF-8, `DOCTYPE`/entidade/PI,
  `DOMDocument` com `LIBXML_NONET | LIBXML_NOBLANKS`, raiz única, profundidade,
  herança de `fill-rule`/`clip-rule`, charset e limites de `d`, mínimo de um
  path. Códigos estáveis: `invalid_encoding`, `doctype_not_allowed`,
  `processing_instruction_not_allowed`, `malformed`, `invalid_root`,
  `unsupported_attribute`, `unsafe_value`, `invalid_viewbox`, `too_deep`,
  `unsupported_element`, `text_not_allowed`, `invalid_path`, `path_too_long`,
  `total_path_too_long`, `too_many_paths`, `missing_paths`, `too_large`.
- `ParsedCustomLogo`, `InvalidCustomLogoException` e extração de `BadgeInput`
  (normalização de texto/cores/etc. reusada pelo preset).

### E3 — Renderer multi-path e fixtures

- `StaticBadgeRenderer` e `resources/js/tools/badgeforge/lib/render.ts`
  emitem, por path e nesta ordem:
  `<path d="..." [fill-rule] [clip-rule] fill="..."/>`.
- Fixtures novas `custom_multipath_basic.svg` e `custom_multipath_fillrule.svg`;
  `cases.json` ganhou os dois casos.
- `git diff tests/Fixtures/badgeforge/expected` vazio: nenhuma fixture
  histórica mudou.

### E4 — Resolução pública

- `CustomLogoRepository::find()` aceita `u_<ULID>` com qualquer caixa, memoiza
  por request (inclusive “não encontrado”), revalida o JSON na leitura e
  captura `QueryException`.
- `BadgeRequest` resolve `u_*` pelo repositório (nunca pelo catálogo embutido)
  e aplica o teto `custom_logos.max_cache_seconds` quando o valor bruto é
  custom. Qualquer falha → `null` (badge sem logo, 200).
- Nenhuma mudança em controller, ETag, `Cache-Control`, PNG ou cache em disco;
  logos embutidos não fazem consulta nova.

### E5 — Props e contrato

- `BadgeForgeToolProps::for(?User)` (e `ToolProps::for()` repassando o usuário)
  expõe `limits.customLogos`, `limits.presets`, `management`, `customLogos`
  (metadados) e `presets` (`form`, `logoOrphaned`).
- Convidado/não verificado: `canManage = false`, listas vazias, sem query de
  logos/presets. Free: `limit = 2`; pago: `limit = null`.
- `tests/Fixtures/badgeforge/tool-props.json` regravada para o convidado;
  testes por perfil em `BadgeForgeAccountPropsTest`.
- TS: tipos de conta/tool, `customLogos.ts`, `builder.ts`, `lib/url.ts`
  (`logoSlug` explícito) e `lib/render.ts`.

### E6 — Rotas, controllers, requests e quota

- `routes/badgeforge.php` (incluído por `routes/web.php`): 8 rotas —
  `dashboard.badgeforge`, `badgeforge.logos.store|show|update|destroy`,
  `badgeforge.presets.store|update|destroy`.
- `BadgeForgeAccountController`, `CustomLogoController`,
  `BadgePresetController`.
- FormRequests: `StoreCustomLogoRequest`, `UpdateCustomLogoRequest`,
  `StoreBadgePresetRequest`, `UpdateBadgePresetRequest`.
- Actions `StoreCustomLogo` (transação + `lockForUpdate` + quota) e
  `StoreBadgePreset`; `BadgeForgeQuota`; `PresetSpecNormalizer`.
- `BadgeForgeServiceProvider` registra o rate limiter
  `badgeforge-management` lendo `badgeforge.management.*`; `bootstrap/providers.php`
  atualizado.
- `abort_unless($model->user_id === $user->id, 404)` em show/update/destroy.

### E7 — UI, painel, navegação e i18n

- `Tool.vue`: botões “Meus logos”/“Presets” quando `management.canManage`,
  CTA de login para convidado, Select com embutidos + custom, resolução lazy
  dos paths, aplicação de `?preset=`.
- `LogoLibraryDialog.vue`, `LogoUploadForm.vue`, `PresetDialog.vue`,
  `LogoPreview.vue` (SVG inline multi-path com escape do renderer).
- `resources/js/pages/dashboard/BadgeForge.vue`: limites, upgrade, listas,
  preview sob demanda, renomear/excluir, abrir preset na tool.
- `useAccountNav`: item BadgeForge no grupo “Menu”; `PublicLayout.vue` passou a
  montar `<Toaster />` para os flashes.
- i18n nos 5 idiomas: `tools.badgeforge.management.*`, `presets.*`,
  `account.badgeforge.*`, `account.nav.badgeforge` e
  `validation.custom_logo.*`.
- Testes de componente cobrem convidado, limites, upload/renomear/excluir,
  órfão, `?preset=` e a guarda “digitar não dispara fetch; selecionar custom
  dispara exatamente uma requisição”.

### E8 — Privacidade, documentação e gates

- `AnonymizeUser`: apaga `badgeCustomLogos` e `badgePresets` da conta.
- `ExportUserData`: inclui `badgeforge.custom_logos` (nome, slug, viewBox,
  paths, sizeBytes, created_at) e `badgeforge.presets` (nome, form,
  created_at).
- ADR-078 e ADR-079 novas; nota datada na ADR-066 e emenda na ADR-002.
- `docs/SPEC-BADGEFORGE.md` §2/§5/§7/§13/§15/§16; `docs/badgeforge/API.md`
  (logo custom, teto, rotas de gestão e F4/F5/F8 remapeadas);
  `docs/badgeforge/OPENAPI.md` (v1.3.0, `logo` sem enum); `docs/glossario.md`
  (Preset atualizado, Logo personalizado, Slug opaco, Preset órfão);
  `CLAUDE.md` (F3 entregue e ADRs 062–079).
- `docs/acceptance/BADGEFORGE-FASE-3.md` e este relatório.
- Ajustes finais de PHPStan: `array_values` redundante removido,
  `payload`/`paths` lidos como `mixed` e revalidados, laços de atributos DOM
  sem checagens que o stub já garante. Nenhum `@phpstan-ignore`/baseline.

## Gates e testes

| Comando | Resultado |
|---|---|
| `composer test` | Pint ok; PHPStan 0 erros; Pest **966 testes / 18.465 asserções** |
| `npm run test` | Vitest **904 testes / 69 arquivos** |
| `npm run types:check` | limpo (Wayfinder regenerado antes do `vue-tsc`) |
| `npm run lint:check` | 0 erros; 1.175 avisos de indentação pré-existentes |
| `npm run build` | concluído (aviso de tamanho de chunk já conhecido) |
| `php artisan route:list --path=badgeforge` | 8 rotas, nomes e middleware conforme o plano |
| `git diff tests/Fixtures/badgeforge/expected` | vazio (só fixtures novas) |

Suítes novas: `CustomLogoSvgParserTest`, `BadgeCustomLogoTest`,
`BadgePresetTest`, `CustomLogoResolutionTest`, `BadgeForgeAccountPageTest`,
`BadgeForgeAccountPropsTest`, `badgeforge-account.test.ts`.
Suítes afetadas: `LogoCatalogTest`, `StaticBadgeRendererTest`,
`BadgeForgeToolPropsTest`, `BadgeForgePageTest`, `AccountPanelTest`,
`ProfileUpdateTest`, `TranslationsParityTest`, `badgeforge.test.ts`,
`badgeforge-render.test.ts`, `badgeforge-component.test.ts`,
`account-panel.test.ts`.

O único teste que falhou em uma execução paralela foi
`markdown-to-pdf.test.ts > componente Tool.vue` por timeout de 5 s sob carga
(composer + Vitest ao mesmo tempo); isolado e na execução sequencial passou
18/18. Não é relacionado à F3.

## Smoke local

Não foi executado smoke manual nesta sessão. Os comportamentos do roteiro
(limite Free, SVG malicioso, `?logo=u_...` existente/apagado, teto de cache,
preset órfão, página nos 5 idiomas, item de navegação e CTA de convidado) estão
cobertos pelos testes de feature/componente listados no aceite. O smoke manual
com conta real segue recomendado antes do PR.

## Desvios do plano

- O `logo` do preset vive dentro do `payload` (sem FK nem coluna duplicada),
  como decidido na ADR-079; o órfão é sempre derivado na leitura.
- A página `/dashboard/badgeforge` duplica parte da marcação dos diálogos
  (lista/renomear/excluir); a duplicação é aceita para manter cada superfície
  simples.
- O `GET /badgeforge/logos/{id}` fica fora do throttle de escrita; só as
  mutações são limitadas.
- A fixture `tool-props.json` representa o convidado; Free/pago/não verificado
  são cobertos por testes dedicados, como no plano.
- PHPStan 7 exigiu três ajustes de tipagem no fechamento (sem mudança de
  comportamento): retorno de listas sem `array_values`, leitura de
  `payload`/`paths` via valor cru revalidado e remoção de checagens de nulidade
  impossíveis nos mapas de atributos DOM.

## Riscos e pendências operacionais

1. **Migração**: as duas tabelas são novas e aditivas; o deploy não toca
   tabelas existentes. Ainda assim, rodar `php artisan migrate` antes de
   liberar as rotas de gestão.
2. **Página de conta com muitos logos**: no plano pago, a lista não tem teto.
   As props levam só metadados (sem paths), mas a lista em si cresce com o uso;
   se virar problema, paginar a página é uma mudança localizada.
3. **Cache de 1 h**: renomear/excluir logo pode levar até 3600 s para sumir de
   caches/CDNs; é o trade-off aceito na ADR-078.
4. **Preset com logo excluído**: aplica sem logo, com aviso; o usuário precisa
   escolher outro conscientemente.
5. **Paridade PHP/TS**: qualquer mudança no renderer precisa vir com PHP + TS +
   fixtures juntos; o teste de integridade das fixtures históricas continua
   valendo.
6. Nada foi commitado ou publicado; o tree segue para revisão do usuário
   (`git status` revisado, nenhum commit/push executado).

## Arquivos

Novos (principais):

- `database/migrations/2026_11_02_000001_create_badge_custom_logos_table.php`,
  `database/migrations/2026_11_02_000002_create_badge_presets_table.php`
- `app/Models/{BadgeCustomLogo,BadgePreset}.php`,
  `database/factories/{BadgeCustomLogoFactory,BadgePresetFactory}.php`
- `app/Support/BadgeForge/{LogoPath,CustomLogoSvgParser,ParsedCustomLogo,InvalidCustomLogoException,CustomLogoRepository,BadgeForgeQuota,BadgeInput,PresetSpecNormalizer}.php`
- `app/Actions/BadgeForge/{StoreCustomLogo,StoreBadgePreset}.php`
- `app/Http/Controllers/BadgeForge/{BadgeForgeAccountController,CustomLogoController,BadgePresetController}.php`
- `app/Http/Requests/BadgeForge/{StoreCustomLogoRequest,UpdateCustomLogoRequest,StoreBadgePresetRequest,UpdateBadgePresetRequest}.php`
- `app/Providers/BadgeForgeServiceProvider.php`, `routes/badgeforge.php`
- `resources/js/tools/badgeforge/{LogoLibraryDialog,LogoUploadForm,PresetDialog,LogoPreview}.vue`,
  `resources/js/tools/badgeforge/customLogos.ts`,
  `resources/js/pages/dashboard/BadgeForge.vue`
- `tests/Unit/BadgeForge/CustomLogoSvgParserTest.php`,
  `tests/Feature/BadgeForge/{BadgeCustomLogoTest,BadgePresetTest,CustomLogoResolutionTest,BadgeForgeAccountPageTest,BadgeForgeAccountPropsTest}.php`,
  `resources/js/__tests__/badgeforge-account.test.ts`
- fixtures `tests/Fixtures/badgeforge/expected/custom_multipath_*.svg`
- `docs/adr/ADR-078-badgeforge-logos-personalizados.md`,
  `docs/adr/ADR-079-badgeforge-presets-e-gestao.md`,
  `docs/acceptance/BADGEFORGE-FASE-3.md`

Modificados (principais): `config/badgeforge.php`, `routes/web.php`,
`bootstrap/providers.php`, `User`, `BadgeForgeToolProps`, `BadgeRequest`,
`Logo`, `LogoCatalog`, `StaticBadgeRenderer`, `ColorValue`, `ToolProps`,
`AnonymizeUser`, `ExportUserData`, `Tool.vue` e libs TS do BadgeForge,
`useAccountNav`, `PublicLayout.vue`, `resources/js/types/account.ts`,
5 arquivos `lang/*/{account,tools,validation}.php`, fixtures
(`cases.json`, `tool-props.json`), testes citados, `CLAUDE.md`,
`docs/SPEC-BADGEFORGE.md`, `docs/glossario.md`,
`docs/badgeforge/{API,OPENAPI}.md`, `docs/adr/ADR-002-...md`,
`docs/adr/ADR-066-...md`.
