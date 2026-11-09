# Survey — Colors Fase 1 (HTML Color Picker)

- Data: 2026-11-07
- Branch: `feature/colors-fase-1`
- Origem: `docs/colors/PROMPT-INICIAL-CLAUDE-CODE.md`
- Método: grill em 4 rodadas + levantamento de fatos em três explorações (docs/colors, catálogo/rotas/i18n/testes, OG/SEO)

## Contexto da tarefa

A pasta `docs/colors/` é especificação pura: não existe código de cor no repositório. A Fase 1 pede o grupo Colors no catálogo e o HTML Color Picker completo, 100% no navegador, nos cinco idiomas, tema dark-first. O prompt inicial exige, antes de codificar, resolver seis inconsistências de documentação e auditar conversões, contraste, alpha, arredondamento, gamut, EyeDropper, i18n e regressões.

## Decisões da sessão

### Rodada 1 — fundação

| Pergunta | Decisão |
|---|---|
| Escopo da sessão | Auditoria + plano; implementação começa depois do OK, com paradas por etapa |
| Branch | `feature/colors-fase-1` (já existia e estava corrente) |
| Documentação | Corrigir todos os docs agora |
| URL | Aninhada e canônica `/{locale}/tools/colors/html-color-picker` via campo `group`; sem alias plano; `/tools/html-color-picker` → 404 |
| Núcleo de cor | Módulo TS próprio, sem dependência nova |
| Layout | Próprio do picker (não `ToolShell`) |
| i18n | Namespace `tools` (`tools.html-color-picker.*`) |

### Rodada 2 — comportamento de cor

| Pergunta | Decisão |
|---|---|
| Gamut | Reduzir croma por busca binária preservando L/H; avisar; mostrar fonte OKLCH e sRGB mapeado; sem P3 |
| EyeDropper | API nativa; fallback com imagem colada/arrastada + lupa 8×/16× e setas |
| Shades/tints | 10 + 10 em OKLab; separados da escala 50–950 da F2 |
| Persistência | `localStorage`; histórico 50 e favoritos 50; dedupe por cor canônica com alpha |

### Rodada 3 — superfície

| Pergunta | Decisão |
|---|---|
| UI | Área 2D S/V + sliders de matiz e alpha + `<input type="color">` |
| Contraste | Fundo explícito (branco padrão/preto/hex), composição do alpha, AA/AAA normal e grande, sem arredondar a classificação |
| Formatos | Nove linhas com cópia individual; entrada aceita todos, `transparent` e HEX 3/4/6/8 |
| Estado na URL | Nenhum; última cor em `localStorage` |

### Rodada 4 — documentação

| Pergunta | Decisão |
|---|---|
| ADRs de Colors | Renumerar 057–086 → 091–115 (repo termina em 090), com tabela de mapeamento |
| Arquivamento | Arquivada sai da lista padrão, URL pública 404, restaurável, conta na cota Free; excluir é definitivo e libera cota |
| Figma | Exportar W3C DTCG JSON, importável por Tokens Studio e Figma Variables; sem API do Figma |
| Pacote de correções | Aplicar todas as correções mecânicas e criar ADRs novos para as decisões |

## Fatos levantados

### Catálogo e rotas

- Catálogo = `resources/js/tools/<slug>/manifest.json` (`slug`, `status`, `category`, `icon`, `order`, `keywords`); leitores em `app/Support/Tools.php` e `resources/js/tools/registry.ts`; componentes em `resources/js/tools/components.ts`.
- `category` é união fechada em `resources/js/tools/types.ts:13`; catálogo de categorias em `resources/js/tools/categories.ts`; ícones em `resources/js/tools/icons.ts` (teste PHP lê o mapa por regex).
- Rota única `Route::get('tools/{slug}', ToolController::class)->name('tools.show')` em `routes/web.php:88`, dentro do prefixo `{locale}` (`:39-41`). Precedente multi-segmento: `tools/share-text/{id}` (`:83-86`). Fallback 404 em `:247`.
- `ToolController` resolve slug, `Tools::find`, 404 se não `available`, renderiza `tools/Show` com limites e `toolProps`.
- SEO/sitemap/JSON-LD dependem do nome `tools.show`: `app/Support/Seo.php` (`pageMeta` ~:120, `toolSlug` ~:273), `app/Support/Sitemap.php:57`, `app/Support/StructuredData.php:27`.
- Hreflang/canonical trocam só o primeiro segmento (`app/Support/Locales.php:135-157`) — URL aninhada funciona sem mudança.
- OG estática: comando `seo:og-image` grava `public/og/<slug>.png` 1200×1200, versionada; `public/og` tem 12 PNGs.

### i18n e testes

- Locales: `en`, `pt_BR`, `pt_PT`, `fr`, `es`; slugs `en`, `pt-br`, `pt-pt`, `fr`, `es`.
- Strings de tool em `lang/*/tools.php` sob `tools.<slug>.*`; categorias em `tools.categories.<id>`; paridade garantida por `TranslationsParityTest`.
- Testes que fixam o catálogo: `tests/Feature/Tools/ToolsRegistryTest.php`, `ToolPageTest.php`, `SitemapTest.php`, `SeoMetaTest.php`, `StructuredDataTest.php`, `ErrorPageTest.php`; no front, `tools-registry.test.ts`, `home.test.ts` (8 chips), `command-palette.test.ts`.
- Vitest: `resources/js/**/*.test.ts`, arquivos em `resources/js/__tests__/`; `npm test` = `vitest run`.
- Scripts: `types:check` (wayfinder + vue-tsc), `lint:check`, `format:check`; saída do Wayfinder é gitignored.

### Documentação (contradições confirmadas)

1. 25 ADRs de Colors reutilizam números já ocupados (`docs/adr/ADR-057..090`); os prompts proíbem.
2. Escala: SPEC §5, ADR-058 e `palette-generator.md` dizem 5/7/9/11 para cadastrado; o prompt e a Fase 2 dizem 11 tons fixos e 5/7/9/11 só na extração.
3. OG: SPEC §7, ADR-076, `og-image.md` e `paleta-publica.md` mandam `public/og` versionado; o prompt manda `storage/app/data/og_paleta_image` sem versão.
4. `og-image.md` cita `colors:generate-og-image`, que não existe (o comando é `seo:og-image`).
5. Arquivamento chamado de “soft delete” na SPEC, mas os prompts exigem distinguir de excluir e definir cota.
6. `ai_caches` não existe; a tabela real é `ai_cache` (singular) e `expires_at` é obrigatório.
7. Curtida anônima: SPEC/ADR-083 dizem “IP hash”; o prompt da F6 exige identificador assinado e IP só como antiabuso.
8. Threads: ADR-082 já fixa 2 níveis, mas o prompt da F7 pede “verificar”.
9. Plano de fases: SPEC §16 tem F1–F7 e os prompts têm 8; escopos de F3/F6 divergem; referência a “seção 9” está errada.
10. Docs dizem `pt-BR`/`pt-PT`; o código usa `pt_BR`/`pt_PT` e slugs `pt-br`/`pt-pt`; URLs da SPEC omitem `{locale}`.
11. `PROMPT-INICIAL` manda ler “ADR-051 a ADR-086”, mas a pasta começa em 057 e faltam 071–075.
12. Nenhum código de cor existe; `EyeDropper` e `oklch` aparecem só em docs e no teste do tema do CodeMirror.

### Repositório

- `docs/plans/` e `docs/survey/` já existem com o padrão `docs/plans/<branch_com_underscore>/` e `docs/survey/`.
- `storage/app/data/og_paleta_image` não existe.
- `docs/colors/PROMPT-Fase2..8` estão fora do índice do Git; `PROMPT-INICIAL` está modificado no working tree.

## Pendências registradas

- Histórico de cor no backend para cadastrados (sem limite funcional) fica para a F5.
- OG dinâmico de paleta, persistência, protótipos, sociale moderação ficam nas fases seguintes.
- Decisão de `expires_at` sentinela para o cache permanente de moderação fica para a F7.
