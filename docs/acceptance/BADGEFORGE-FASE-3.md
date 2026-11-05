# Critérios de aceite — BadgeForge FASE 3 (logos personalizados + presets + contas)

Gerado em 2026-11-02.
Fonte: `docs/SPEC-BADGEFORGE.md` seção 16 + plano da sessão
(`docs/claude-code/plans/feature_badgeforge-fase-1/20261102_feature_badgeforge-fase-1_badgeforge_fase3.md`)
+ ADR-078 e ADR-079.

## Escopo da fase

- Logos personalizados: upload de SVG multi-path (`<svg>`, `<g>`, `<path>`),
  parser com allowlist e limites, persistência apenas do `viewBox` + paths
  normalizados, referência pública `u_<ULID>` e resolução stateless no endpoint
  de imagem (ADR-078).
- Presets: snapshot normalizado do formulário salvo por conta verificada,
  aplicável no builder (`?preset=<ulid>`) e no painel, com órfão calculado na
  leitura e queda para `logo = none` (ADR-079).
- Contas: página `/dashboard/badgeforge`, diálogos de gestão no builder, item
  na navegação da conta, limites em `config/badgeforge.php` (Free: 2 logos;
  pago: ilimitado; presets: ilimitados), throttle e 404 para registro alheio.
- Privacidade: logos e presets entram na exportação de dados e são apagados na
  anonimização da conta.
- Documentação: ADR-078, ADR-079, notas em ADR-002/ADR-066, SPEC §2/§5/§7/§13/
  §15/§16, `docs/badgeforge/API.md`, `OPENAPI.md`, glossário e `CLAUDE.md`.

Fora da fase: badges pessoais `/b/{username}/{ulid}`, providers, endpoint
JSON/SSRF, analytics, exportação CSV de badges, página pública de documentação,
admin de logos e qualquer API pública de gestão.

## Critérios

| # | Critério | Como verificar |
|---|---|---|
| 1 | Migrações aditivas criam `badge_custom_logos` e `badge_presets` com FK em cascata, único por `(user_id, name)` e índice `(user_id, created_at)` | `php artisan migrate`/`migrate:rollback`; testes de model |
| 2 | O parser aceita `<svg viewBox>` + `<g>`/`<path>` multi-path, herda `fill-rule`/`clip-rule` de `<g>` e preserva a ordem dos paths | `CustomLogoSvgParserTest` |
| 3 | O parser recusa `DOCTYPE`, entidade, PI, `script`, `style`, `image`, `use`, `foreignObject`, `defs`, `text`, `animate`, `set`, `on*`, `href`, `style`, `transform`, `opacity`, `filter`, `clip-path`, `mask`, `marker-*`, `url(` e `viewBox`/`d` inválidos, com código estável | `CustomLogoSvgParserTest` |
| 4 | Limites de bytes, paths, comprimento por path/total, extensão do viewBox e profundidade vêm de `config/badgeforge.php`; nenhum número no parser/controller | teste com config sobrescrita |
| 5 | Só o SVG normalizado é persistido: nenhum arquivo de upload fica em disco e `paths` é JSON `list<{d, fillRule?, clipRule?}>` | `BadgeCustomLogoTest` + inspeção do banco |
| 6 | PHP e TS renderizam os paths byte a byte; fixtures históricas intactas e casos `custom_multipath_basic`/`custom_multipath_fillrule` | `StaticBadgeRendererTest`, `badgeforge-render.test.ts`, `git diff tests/Fixtures/badgeforge/expected` |
| 7 | `?logo=u_<ULID>` resolve o logo do dono; slug malformado, ULID inexistente, logo apagado ou banco indisponível → 200 sem logo | `CustomLogoResolutionTest` |
| 8 | O repositório memoiza por request (inclusive "não encontrado") e não consulta o banco para slugs embutidos | `CustomLogoResolutionTest` |
| 9 | `cacheSeconds` com `logo=u_...` é limitado a `custom_logos.max_cache_seconds` (3600); embutidos mantêm o teto de 86400 | `CustomLogoResolutionTest`/`BadgeRequestTest` |
| 10 | `toolProps` não carrega paths: `customLogos` traz só metadados (`id`, `slug`, `name`, `viewBox`, `pathCount`, `sizeBytes`) e `presets` traz o snapshot com `logoOrphaned` | `BadgeForgeToolPropsTest` + fixture exata |
| 11 | Convidado/não verificado vê CTA de login, `canManage = false`, listas vazias e nenhuma query nova; Free vê limite 2; pago vê ilimitado | `BadgeForgeToolPropsTest`, `BadgeForgeAccountPropsTest` |
| 12 | Free envia 2 logos e o terceiro responde 422 traduzido; pago ultrapassa 2; concorrência não estoura o limite (`lockForUpdate`) | `BadgeCustomLogoTest` |
| 13 | Upload válido, nome duplicado, SVG malicioso (422 com `validation.custom_logo.<code>`), renomear e excluir | `BadgeCustomLogoTest` |
| 14 | Registro de outra conta responde **404** (nunca 403) em `show`/`update`/`destroy` de logo e preset | `BadgeCustomLogoTest`, `BadgePresetTest` |
| 15 | `GET /badgeforge/logos/{id}` entrega `{viewBox, paths}` só ao dono, com `Cache-Control: private, no-store` | `BadgeCustomLogoTest` |
| 16 | Preset salva snapshot normalizado (`none`, embutido ou `u_<ULID>` do dono), renomeia, exclui e é ilimitado; logo alheio é recusado | `BadgePresetTest` |
| 17 | Excluir o logo deixa o preset órfão (`logoOrphaned = true`) e aplicar cai para `logo = none` sem falhar | `BadgePresetTest`, `badgeforge-component.test.ts` |
| 18 | `?preset=<ulid>` aplica o snapshot e remove o parâmetro com `history.replaceState` | `badgeforge-component.test.ts` |
| 19 | Digitar no formulário não dispara `fetch`; selecionar logo custom dispara **uma** requisição e reusa o cache na segunda seleção | `badgeforge-component.test.ts` |
| 20 | Gestão nos diálogos e em `/dashboard/badgeforge` (limites, upgrade, lista, órfão), com item em `useAccountNav` e `<Toaster />` no layout público | `badgeforge-component.test.ts`, `badgeforge-account.test.ts`, `account-panel.test.ts` |
| 21 | i18n completo nos 5 idiomas para `tools.badgeforge.management.*`, `presets.*`, `account.badgeforge.*` e `validation.custom_logo.*` | `TranslationsParityTest` |
| 22 | Exportação de dados inclui `badgeforge.custom_logos` e `badgeforge.presets`; anonimização apaga os dois registros | `AccountPanelTest`, `ProfileUpdateTest` |
| 23 | Gate: `composer test`, `npm run test`, `npm run types:check`, `npm run lint:check` e `npm run build` | os comandos |
| 24 | Nenhum commit/push feito pelo assistente | `git status` revisado |

## Resultado em 2026-11-02

- `composer test`: Pint ok, PHPStan 0 erros, Pest **966 testes / 18.465
  asserções**.
- `npm run test`: Vitest **904 testes / 69 arquivos**.
- `npm run types:check`: limpo (Wayfinder regenerado antes do `vue-tsc`).
- `npm run lint:check`: 0 erros; 1.175 avisos de indentação pré-existentes no
  repositório. Nos arquivos da F3: 0 avisos, exceto dois
  `vue/one-component-per-file` intencionais em `badgeforge-account.test.ts`
  (stubs inline do teste).
- `npm run build`: concluído (o aviso de tamanho de chunk é o já conhecido).
- `php artisan route:list --path=badgeforge`: 8 rotas
  (`dashboard.badgeforge`, `badgeforge.logos.store|show|update|destroy`,
  `badgeforge.presets.store|update|destroy`).
- `git diff tests/Fixtures/badgeforge/expected`: vazio — as fixtures históricas
  não mudaram; só entraram as duas novas (`custom_*`).
- `git status` revisado; nenhum commit/push executado.

## Notas e desvios do plano

- `BadgeForgeToolProps::for()` recebe `?User` e o `ToolProps::for()` repassa o
  usuário; a fixture `tool-props.json` foi regravada para o convidado e há
  testes separados para Free, pago e não verificado.
- O `logo` do preset vive dentro do `payload`, sem FK nem coluna duplicada; o
  órfão é sempre calculado na leitura.
- A página `/dashboard/badgeforge` reusa `BadgeForgeToolProps` (mesmo contrato
  dos diálogos), o que duplica parte da marcação de lista/renomear/excluir —
  aceito na ADR-079 para manter cada superfície simples.
- O endpoint de leitura dos paths fica fora do throttle de escrita; só as
  mutações usam `throttle:badgeforge-management`.
- Badges já publicados com `logo=u_...` continuam válidos após a exclusão do
  logo: a URL não é reescrita, o badge sai sem logo e o cache expira em no
  máximo 1 h.
