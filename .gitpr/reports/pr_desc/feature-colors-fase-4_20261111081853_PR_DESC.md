# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona persistência de paletas na Fase 4 do Colors
```

---

## 🎯 Resumo

A Fase 4 do Colors entrega a persistência de paletas ("Minhas Paletas"), fechando o fluxo de valor **gerar → salvar → compartilhar → duplicar**. Antes, a ferramenta só produzia paletas em memória; agora o usuário autenticado pode salvá-las com cota por plano (free vs. pro), organizá-las com tags, publicá-las em páginas indexáveis nos 5 locales e duplicá-las com rastreio por `duplications_count`. As regras de negócio ficam no servidor (validação por tipo de paleta, slug, cota e ownership) e o client apenas espelha os limites recebidos por props. Nada é recalculado ao reabrir uma paleta: a escala é reconstruída dos HEX salvos, garantindo fidelidade visual. O resultado desbloqueia SEO orgânico via páginas públicas com OG dinâmica e sitemap.

## 🛠️ Mudanças Técnicas

**Backend**
- Actions transacionais `ArchivePalette`, `CreatePalette`, `DeletePalette`, `DuplicatePalette`, `RestorePalette` e `UpdatePalette`, com lock da linha do usuário para respeitar a cota (`PaletteQuota`), retry em `UniqueConstraintViolationException` de slug e invalidação da imagem OG quando o visual muda.
- Enums `PaletteKind` (scale/extracted) e `PaletteVisibility` (private/public); models `ColorPalette` e `ColorTag` (ULID, scopes `forUser`/`active`/`archived`/`publiclyVisible`) e relações `colorPalettes`/`colorTags` em `User`.
- Controllers: CRUD autenticado com busca, filtro e ordenação server-side (`PaletteController`), duplicação (`PaletteDuplicationController`), intenções pós-login (`PaletteIntentController`), tags da conta (`PaletteTagController`), serving/upload/remoção da OG (`PaletteOgController`) e página pública (`PublicPaletteController`). Recurso de outra conta responde **404 em vez de 403**.
- FormRequests validam payload por tipo (escala exige os 11 tons; extraída aceita 5/7/9/11 com base entre as cores), versão do algoritmo, confirmação do nome na exclusão e PNG 1200x630 no upload da OG.
- Camada Support: `PaletteData`, `PaletteRules`, `PaletteSlug` (reservados/colisões), `PaletteTags` (key normalizada, position por conta), `PaletteName`, `PalettePresenter`, `PaletteOgStorage` (escrita atômica em `storage/app/data/og_paleta_image`) e `PaletteQuota`; `ColorsToolProps` passa a expor limites de formulário e tags do usuário.
- `ColorsServiceProvider` com rate limiters `colors-management` e `colors-og`, `config/colors.php` centralizando limites e defaults, três migrations (`color_palettes`, `color_tags`, pivô `color_palette_color_tag`) e factories.
- SEO: `Seo` gera meta e OG da paleta pública, `Sitemap` inclui paletas públicas com limite por config e guard de `Schema::hasTable`, e `Locales` remove a query string do canonical/hreflang.

**Frontend**
- Componentes Vue 3/TS: `PaletteActions`, `PaletteCard`, `PaletteMetaForm`, `PalettePagination`, `SavePaletteDialog`, `TagInput` e `TagManagerDialog`; ajustes em `ExtractedSwatch` (percentual desconhecido = 0) e `Tool.vue` refatorado para `GeneratorWorkspace` + fluxo de salvar/rascunho.
- Páginas Inertia: `PublicPalette` (vitrine sem autor/roles/source, com copiar HEX, export JSON/TXT e duplicação exigindo login via `?duplicate=1`), `PaletteEditor` (reusa `GeneratorWorkspace`, escala "congelada" via `buildFrozenScale`, aba Protótipo, OG automática e modo somente leitura para arquivadas) e `PalettesIndex` (busca debounced 300ms, filtros/ordenação/status/tag server-side com `history.replace` e cota no topo).
- Libs/composables: `frozenScale`, `palettePayload` (roles derivados por tema), `paletteOg` (documento 1200x630 com truncamento), `usePaletteOg` (lock por `paletteId` evitando upload duplo), `paletteSave`/`saveDraft` (rascunho anônimo em sessionStorage com TTL 24h) e `downloadBlob`.
- Renderizador de protótipos: tipos de nós SVG, templates site (1200x900), mobile (390x844) e og (1200x630), `renderSvg` determinístico com escape/num/readableOn, rasterização via canvas (1x/2x) e injeção da fonte Libre Franklin como data URI lazy-loaded.
- Roteamento em `routes/colors.php`: grupo autenticado `dashboard/colors/palettes` com throttles, área pública sob `{locale}` (show, `og.png` e duplicação GET/POST); item "Paletas" em `useAccountNav` e `PublicLayout` aplicado a `colors/*`.
- Tipos compartilhados em `types/colors.ts` (PaletteRow, PaletteEditor, PalettePublic, payload, paginator, filtros) e reexport em `types/index.ts`; traduções `colors.php` em en/es/fr/pt_BR/pt_PT.
- Testes: Feature (`PaletteActions`, `PaletteHttp`, `PalettePublicHttp`, `PaletteSeo`, ajuste em `ToolPage`) e Unit (`AlgorithmVersionConfig`, `ColorsToolProps`, `PalettePresenter`, `PaletteSlug`) cobrindo ownership 404, cota sob lock, ciclo publicar/arquivar/restaurar, slug/reivindicação, validação por tipo, OG por HTTP, SEO/canonical/hreflang/sitemap e tags por conta; suíte Vitest para actions, payloads, drafts, rasterização e snapshots.

## ⚠️ Impacto/Avisos

- **Banco de dados:** três novas migrations (`color_palettes`, `color_tags`, pivô `color_palette_color_tag`) — requer `migrate`.
- **Configuração:** novas chaves obrigatórias — `colors.og.path/width/height`, `colors.palettes.free_limit/per_page/sitemap_limit`, `colors.slug.max_length`, `colors.tags.*` e `colors.algorithm_versions.*`.
- **Rotas públicas novas** (`colors/p/{slug}` e `og.png`) exigem revisão de cache/CDN e estratégia de invalidação da OG.
- **Dependência de ~290KB de base64** (fonte) já existente em badgeforge, usada na exportação de protótipos/OG — atenção ao bundle das páginas que rasterizam.
- **Listagens** passam a aceitar ordenação/filtro via query string (whitelist aplicada server-side).
- Rate limits `colors-management` e `colors-og` aplicados a uploads e requisições autenticadas.

close #145

---

[![GitPR](https://img.shields.io/badge/GitPR-2_errors_%C2%B7_147_warnings-red)](https://gitpr.natanfiuza.dev.br/)