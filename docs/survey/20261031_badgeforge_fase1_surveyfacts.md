# Survey — BadgeForge Fase 1 (2026-10-31)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa

- **Pedido original:** implementar apenas a Fase 1 do BadgeForge — núcleo de badges estáticas em SVG, endpoint público, cinco estilos, cores CSS, logos embutidos, preview em tempo real, snippets, cache HTTP, tool no catálogo, i18n em cinco idiomas e testes Pest/Vitest.
- **Módulo:** BadgeForge (`app/Support/BadgeForge/`, `resources/badgeforge/`, `resources/js/tools/badgeforge/`, `routes/api.php`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`.
- **Método:** 15 decisões fechadas em rodadas; todas aprovadas com a opção recomendada.
- **Arquivos-chave lidos:**
  - `CLAUDE.md`, `docs/SPEC-BADGEFORGE.md`, `docs/glossario-badgeforge.md`, `docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md`
  - `routes/api.php`, `bootstrap/app.php`, `config/tools.php`
  - `app/Support/ToolProps.php`, `app/Http/Middleware/{ForceJsonResponse,SecurityHeaders}.php`
  - `resources/js/tools/{types,categories,icons,registry}.ts`, `resources/js/tools/base64-image/lib/formatters.ts`
  - `tests/Feature/Tools/ToolsRegistryTest.php`, `tests/Feature/TranslationsParityTest.php`, `resources/js/__tests__/tools-registry.test.ts`
  - `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`, `tsconfig.json`, `composer.json`, `package.json`

## 2. Decisões das rodadas

### Rodada 1 — Arquitetura e escopo

| # | Decisão | Resolução |
|---|---|---|
| Q1 | Preview em tempo real | Espelho TypeScript do renderer PHP, 100% no cliente; fixtures-ouro compartilhadas com SVG byte-a-byte idêntico. |
| Q2 | Numeração dos ADRs | Renumerar os 10 ADRs untracked de 041–050 para 062–071 e criar 072–076. |
| Q3 | Estilos | Proporções compatíveis com Shields (flat 20 px etc.), código e geometria autorais. |
| Q4 | Texto na URL | `_` → espaço, `__` → `_`, `-` literal; percent-decoding do router. |
| Q5 | Grupo de rotas | `routes/api.php`, fora do `v1` do Sanctum: stateless, sem CSRF, sem throttle na F1. |
| Q6 | Entrada inválida | Cosmético cai no padrão; logo desconhecido ignorado; clamps; texto > 100 trunca com `…`; segmento vazio → 404; nunca 500. |
| Q7 | Logos | ~24 logos embutidos, SVGs autorais, registro em `config/badgeforge.php`. |
| Q8 | `logoPosition` | `left` (padrão) ou `right` do bloco inteiro. |
| Q9 | Cores | 148 nomes CSS + aliases, hex 3/4/6/8, rgb/rgba/hsl/hsla e transparent. |
| Q10 | `cacheSeconds` | Padrão 86400, clamp 300–86400, inválido cai no padrão. |
| Q11 | Categoria | Nova categoria `badges` (“Imagens”) nos 5 idiomas, ícone `image`. |
| Q12 | `link` | `<a target="_blank" rel="noopener noreferrer">`; somente http/https. |
| Q13 | Limite de texto | 100 caracteres por segmento, configurável. |
| Q14 | Catálogo no cliente | Estilos, logos, limites e endpoint chegam por `toolProps` (ADR-043). |
| Q15 | Glossário | Fundir `docs/glossario-badgeforge.md` em `docs/glossario.md` e remover o arquivo separado. |

### Defaults assumidos (não contestados)

- URL canônica `GET /api/badges/static/{label}/{message}.svg`; sem alias sem extensão na F1.
- Defaults visuais: `flat`, `#9f9f9f`, label `#555`, logo `#fff`, `logoSize` 10 (14 em for-the-badge), clamp 8–20.
- `social` usa fundos fixos `#555`/`#007ec6`; `for-the-badge` usa caixa alta e `letter-spacing`.
- ETag = `sha256` do SVG; `Cache-Control: public, max-age=<ttl>`; 304 via `Response::isNotModified()`.
- Fora da F1: PNG/Imagick, providers, endpoint JSON, logos personalizados, badges pessoais, analytics, exportação, `?download=`, OG por tool.

## 3. Relatório de fatos levantados

### Fato 1: os ADRs citados no pedido não existem
Os arquivos `ADR-031/032/033/040-badgeforge-*.md` não estão no repositório. O que existe são ADRs `041..050-badgeforge-*` untracked, que colidem com ADRs `041..050` já commitados (gate lexical, API pública). Daí a renumeração para 062–071; `ADR-061` é hoje o maior número.

### Fato 2: o grupo `api` é stateless e sem throttle
`bootstrap/app.php` usa `$middleware->api(prepend: [ForceJsonResponse::class])`; o limiter padrão da API é nulo e `throttleApi()` não é chamado. O `SecurityHeaders` é global com CSP por nonce, `X-Frame-Options: DENY` e `nosniff`. O `shouldRenderJsonWhen` e o render de exceções cobrem `api/*` com o envelope `ApiError`.

### Fato 3: o catálogo de tools tem duas listas fixas de MVP
`tests/Feature/Tools/ToolsRegistryTest.php` e `resources/js/__tests__/tools-registry.test.ts` enumeram os 11 slugs atuais; ambos precisam receber `badgeforge`. A categoria é uma união fechada (`ToolCategoryId`) e a ordem dos chips vem de `categories.ts`.

### Fato 4: i18n é verificado por paridade exata
`TranslationsParityTest` exige o mesmo conjunto de chaves nos 5 locales e proíbe `@` e `|` crus. Chaves com `{`/`}` precisam seguir o ADR-060.

### Fato 5: `%2F` não sobrevive ao roteamento
O `UrlMatcher` do Symfony aplica `rawurldecode` ao pathinfo antes do match, então `%2F` vira `/` e cria um segmento extra. A F1 trata `/` como não representável, avisa na UI e documenta a limitação; dupla codificação fica para fase futura.

### Fato 6: Vite 8 e tsconfig suportam os ativos compartilhados
`import.meta.glob(..., { query: '?raw', import: 'default' })` é suportado e `resolveJsonModule` já está ligado, permitindo que PHP e TS leiam os mesmos JSON de métricas, cores e geometria.
