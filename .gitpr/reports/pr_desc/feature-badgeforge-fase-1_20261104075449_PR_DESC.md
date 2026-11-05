# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona logos, presets e badges pessoais ao BadgeForge
```

---

## 🎯 Resumo

Conclui as fases 3 e 4 do BadgeForge, entregando recursos de personalização e publicação de badges: upload de logos SVG customizados, presets de formulário, badges pessoais publicados com URL estática e integração com providers externos (GitHub, npm, Packagist, Docker Hub). O objetivo é ampliar o valor do produto para usuários pagos, permitindo reutilizar configurações e exibir métricas reais de serviços externos com cache, isolamento de credenciais e privacidade por padrão.

## 🛠️ Mudanças Técnicas

- **Domínio e persistência**: novos models `BadgeCustomLogo`, `BadgePreset`, `BadgeAsset` (ULID, PAT cifrado via cast `encrypted`, fingerprint HMAC oculto), `BadgeProvider` e `BadgeProviderDefinition`, com relações em `User` e migrations (incluindo seed idempotente do catálogo de providers).
- **Actions isoladas**: criação/edição de logos e presets (lock pessimista + quota), CRUD de badges pessoais, troca de credencial com invalidação de cache, refresh e exclusão com remoção de arquivos do disco.
- **Privacidade e ciclo de vida**: `AnonymizeUser` apaga logos, presets e badges pessoais; `ExportUserData` inclui a seção `badgeforge` sem PAT nem fingerprint; PAT marcado em `dontFlash` no bootstrap.
- **HTTP e rotas**: controllers para gestão de logos, presets, badges pessoais e página `/dashboard/badgeforge`; rotas públicas stateless em `routes/public.php` (imagem estática, provider `/{path}.svg|png`, badge pessoal `/b/{username}/{ulid}`) com 404 tolerante a falhas e formato `ApiError`. `StaticBadgeController` refatorado para delegar ao `BadgeImageResponder` (Content-Type, Cache-Control, ETag/304, `download=1`, 503 sem Imagick).
- **Camada de suporte**: `CustomLogoSvgParser` (allowlist `<svg>/<g>/<path>`, descarta arquivo cru), `CustomLogoRepository` memoizado, `BadgeSpecBuilder` extraído e reutilizado por presets, `BadgeAssetStorage` com escrita atômica, `PersonalBadgeRenderer` (lock por badge, stale-if-error, fallback 200) e utilitários de providers (`ProviderCatalog`, `ProviderClientRegistry`, `ProviderMetrics`, `ProviderCache`, `ProviderUrlBuilder`, `ProviderPathParser`, `ProviderCredential`, `ProviderMetricFormatter`) com fetch sem redirect, corpo limitado, ETag condicional e cache por URL + fingerprint.
- **Quotas e props**: `BadgeForgeQuota` e `BadgeForgeToolProps` estendidos com limites de logos/presets/badges, metadados sem paths e tolerância a `QueryException`.
- **Infra/config**: `BadgeForgeServiceProvider` com rate limiters distintos (toast para gestão, 429 JSON para refresh); novas seções em `config/badgeforge.php` (`custom_logos`, `presets`, `management`, `providers`, `personal_badges`); `config/locales.php` inclui `docs`; `PruneBadgePngs` remove arquivos órfãos preservando os referenciados.
- **Frontend (Vue/TS)**: `Tool.vue` com abas de modo (estático/provider via `ModeTabs`), gestão de conta e resolução sob demanda de logos; novos componentes `ProviderForm`, `ProviderPreview`, `LogoLibraryDialog`, `LogoUploadForm`, `LogoPreview`, `PresetDialog`, `PersonalBadgeDialog`, `PersonalBadgesPanel`, além das páginas `BadgeForgeDocs.vue` e `dashboard/BadgeForge.vue`; composable `customLogos.ts` com cache e deduplicação; `builder.ts` resolve `u_<ULID>` e limita `cacheSeconds`; `lib/providerUrl.ts` valida params/patterns/traversal; ajustes em `render.ts`/`url.ts` para multipath e `cosmeticParams`.
- **Documentação e i18n**: textos de docs públicas (en/es/fr/pt_BR/pt_PT), traduções de conta/tools/validação, entradas de SEO e sitemap para `badgeforge-docs`.
- **Testes**: cobertura de página de conta, diálogos de logos/presets, limites Free/pago, prévia sob demanda, exclusão confirmada, provider endpoint (métricas, stale/cache/lock), catálogo e seeder, sanitização SVG, isolamento de logos/presets/badges, não vazamento de PAT (props, logs, export, 422, exclusão), serving público stateless, prune de órfãos, docs/SEO e paridade PHP↔TS multipath; Pest para `ProviderClient` (projeção de métricas, headers/Auth, 304, mapeamento de erros, JSON inválido, limite de corpo, timeout vs DNS), `ProviderCredential` (fingerprint HMAC, redação), `ProviderMetricFormatter` (k/M/B, SPDX, sanitização), `ProviderPathParser` (traversal/limites/multipath) e `ProviderUrlBuilder` (encoding e bloqueio de templates inseguros); `StaticBadgeRendererTest` refatorado para fixtures inline.

## ⚠️ Impacto/Avisos

- **Banco de dados**: novas tabelas e migrations (logos, presets, assets, providers e definições) com seed idempotente — requer execução de `migrate`/`db:seed`.
- **Configuração**: novas chaves em `config/badgeforge.php`, incluindo `providers.max_body_bytes` (dependência implícita nos testes) e seções de limites por plano.
- **Segurança**: rotas públicas novas operam sem sessão/CSRF; exige dependência de storage local para imagens pessoais e reforça proteção contra SSRF e vazamento de credenciais (PAT cifrado, nunca exportado/logado).
- **Plano**: criação e gestão de badges pessoais exigem plano pago; usuários Free mantêm leitura e exclusão.
- **Compatibilidade**: integração com APIs externas depende de rede; falhas são toleradas com stale-if-error e respostas 200/404 controladas.

close #126

---

[![GitPR](https://img.shields.io/badge/GitPR-1_error_%C2%B7_305_warnings-red)](https://gitpr.natanfiuza.dev.br/)