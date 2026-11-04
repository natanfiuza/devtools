# Levantamento — BadgeForge Fase 5 (endpoint JSON + SSRF)

- Data: 2026-11-04
- Branch: `feature/badgeforge-fase-1`
- Plano da sessão:
  `docs/plans/feature_badgeforge-fase-1/20261104_badgeforge_fase5_plansfacts.md`
- Plano de implementação (CLAUDE.md):
  `docs/claude-code/plans/feature_badgeforge-fase-1/20261104_feature_badgeforge-fase-1_badgeforge_fase5.md`

## Contexto da tarefa

A F5 do `docs/SPEC-BADGEFORGE.md` é "endpoint JSON + SSRF". O SPEC (§6) previa
`GET /api/badges/endpoint.{svg|png}?url=...` "apenas cadastrados", HTTPS
obrigatório, SSRF bloqueado e TTL mínimo de 300 s; §11 fixa os limites por
minuto (anônimo 60, cadastrado 300, pago 3000). O código não tinha nenhum
artefato da fase.

## Decisões (rodadas do grill)

### Rodada 1

1. **Contrato JSON**: shields v1 núcleo (`schemaVersion === 1`,
   `label`/`message` obrigatórios, `color`/`labelColor` opcionais).
2. **Identidade**: persistir o endpoint em tabela própria (ULID) e expor
   `/api/badges/endpoint/{ulid}.{svg|png}`; a imagem é anônima.
3. **SSRF**: resolver DNS e exigir que **todos** os IPs sejam públicos, com
   conexão pinada no IP validado (`CURLOPT_RESOLVE`), sem redirect e sem
   proxy; curl obrigatório.
4. **Rate limit**: por dono (Free 300/min, pago 3000/min); ULID
   desconhecido/inválido cai no anônimo 60/min por IP.

### Rodada 2

5. **UI**: painel e diálogo na dashboard; o builder ganha apenas um CTA.
6. **Criação**: valida SSRF e schema com uma busca de amostra; rede fora do ar
   não bloqueia a criação.
7. **JSON**: só o núcleo shields; `logoSvg`, `namedLogo`, `style`,
   `cacheSeconds` e `link` do JSON são ignorados.
8. **TTL**: servidor fixa 300 s; `?cacheSeconds=` e `?refresh=1` ignorados.

## Fatos levantados

### SPEC e docs

- `docs/SPEC-BADGEFORGE.md:8` — "Cadastrado (F3): JSON endpoint (F5)".
- `docs/SPEC-BADGEFORGE.md:45-47` — rota prevista com `?url=`, HTTPS, SSRF e
  TTL mínimo 300 s.
- `docs/SPEC-BADGEFORGE.md:102` — "Endpoint: mínimo 300s".
- `docs/SPEC-BADGEFORGE.md:107-113` — 60/300/3000 por minuto; os limites por
  plano valem para o endpoint JSON.
- `docs/SPEC-BADGEFORGE.md:142,154,164-165` — SSRF/F5 no checklist.
- `docs/badgeforge/API.md:135-141` — endpoint marcado como planejado.
- `docs/badgeforge/OPENAPI.md` — `info.version: 1.4.0`; stanza do endpoint
  marcada "(F5 — planejado)".

### Config e rotas atuais

- `config/badgeforge.php:31` — `'endpoint' => '/api/badges/static'` (string),
  o que colide com a nova seção `endpoint`; renomear para `static_endpoint`.
- `app/Support/BadgeForge/BadgeForgeToolProps.php:42` e
  `app/Http/Controllers/BadgeForgeDocsController.php:22` leem
  `badgeforge.endpoint`.
- `routes/api.php:58-77` — grupo `badges.`: estática primeiro, provider depois;
  sem throttle global no grupo `api`.
- `routes/badgeforge.php:15-67` — gestão autenticada com
  `throttle:badgeforge-management`; refresh com limiter próprio.
- `bootstrap/app.php:100` — `$exceptions->dontFlash(['credential'])`; é onde
  entra `url`.
- `bootstrap/app.php:102-115` — `ApiError` para tudo em `api/*`.

### Padrões reutilizáveis

- `ProviderBadgeController` — controller stateless, `ApiError` para 404,
  `BadgeImageResponder` + `StaticBadgeRenderer`.
- `BadgeSpecBuilder::build($label, $message, $query, ?$cacheSecondsOverride)`.
- `BadgeImageResponder::respond(...)` — Content-Type, Cache-Control,
  ETag/304, `download=1`, 503 sem Imagick.
- `ProviderMetrics`/`ProviderCache` — HIT/MISS/STALE, lock, stale-if-error e
  fallback; **não** generalizar nesta fase.
- `BadgeForgeServiceProvider` — `RateLimiter::for`; o refresh responde JSON
  customizado, a gestão responde toast. O novo limiter não terá `->response()`
  para o `ApiError` manter os headers.
- `BadgeAsset` — `HasUlids`, `#[Fillable]`, `#[Hidden]`,
  `publicSvgUrl()/publicPngUrl()`.
- `CreateBadgeAsset` + `PersonalBadgeGuard::fail($field, $key)` —
  `ValidationException` com chave traduzida nos fluxos de gestão.
- `BadgeForgeToolProps` — props exatas e fixture
  `tests/Fixtures/badgeforge/tool-props.json` comparada em Pest e Vitest.
- `ExportUserData:147-167` e `AnonymizeUser:96-102` — onde badges pessoais
  entram/saem.
- `User::effectivePlan()` → assinatura atual ou `Plan::free()`; `Plan::isFree()`.
- `PersonalBadgesPanel.vue`/`PersonalBadgeDialog.vue` — padrão de painel,
  diálogo, cópia, renomear e excluir.
- `resources/js/tools/badgeforge/types.ts` — contratos do `toolProps`.

### Fatos técnicos relevantes

- O `ThrottleRequests` tem prioridade **antes** do `SubstituteBindings`
  (`vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:110-113`),
  então o limiter recebe o ULID cru, não o model.
- Locales: `en`, `es`, `fr`, `pt_BR`, `pt_PT`.
- `Http::fake` não inspeciona opções curl; a pinagem precisa de teste unitário
  de `options()` e smoke real.
- Exemplo público de JSON shields v1 para smoke:
  `https://raw.githubusercontent.com/ibrahimcesar/react-lite-youtube-embed/main/.github/badges/size-es.json`.

## Contradições resolvidas

- "Apenas cadastrados" vs. imagem consumida por `<img>` anônimo: o **cadastro**
  é do dono; a **leitura** é anônima via ULID opaco, com limite derivado do
  dono.
- SPEC com `?url=` na rota pública vs. risco de SSRF e de URL com segredo:
  a URL fica no servidor (`#[Hidden]`), nunca em props/log/flash/cache
  legível; só o ULID viaja.

## Fora de escopo

Credenciais/headers na origem, `cacheSeconds`/`refresh` públicos,
estilo/logo vindos do JSON, analytics, admin, edição da URL de origem,
providers da F6 e OpenAPI completo da F8.
