# Survey — Tutorial do BadgeForge (2026-11-19)

> Levantamento automático da sessão de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuário.

## 1. Contexto da tarefa
- **Pedido original:** `/plan /grill-with-docs Crie um tutorial em docs/tutorial, para explicar como usar o badgeforge`
- **Tela / Módulo:**
  - Ferramenta pública: `/{locale}/tools/badgeforge` (`resources/js/tools/badgeforge/Tool.vue`)
  - Painel da conta: `/dashboard/badgeforge` (`resources/js/pages/dashboard/BadgeForge.vue`)
  - Documentação pública in-app: `/{locale}/docs/badgeforge` (`app/Http/Controllers/BadgeForgeDocsController.php`, `resources/js/pages/BadgeForgeDocs.vue`)
  - Rotas de imagem e API v1: `/api/badges/static/...`, `/api/badges/{provider}/{definition}/{path}.{svg|png}`, `/api/badges/endpoint/{ulid}.{svg|png}`, `/api/badges/custom/{ulid}/{definition}/{path}.{svg|png}`, `/b/{username}/{ulid}.{svg|png}` e `/api/v1/badgeforge/*`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `feature/layout-harmonizer-fase-3`
- **Método:** 2 rodadas de entrevista, 6 decisões fechadas (todas alinhadas às recomendações), mais inspeção direta de código, specs, configurações e traduções.
- **Arquivos-chave lidos:**
  - `docs/SPEC-BADGEFORGE.md`
  - `docs/badgeforge/API.md` e `docs/badgeforge/OPENAPI.md`
  - `docs/glossario.md`
  - `docs/tutorial/github-token.md`, `docs/tutorial/docker-token.md`, `docs/tutorial/npm-token.md`, `docs/tutorial/instalar-imagick-ubuntu.md`
  - `config/badgeforge.php`
  - `lang/pt_BR/tools.php`
  - `resources/js/tools/badgeforge/Tool.vue` e `ModeTabs.vue`
  - `resources/js/pages/dashboard/BadgeForge.vue`
  - `app/Http/Controllers/BadgeForgeDocsController.php`

## 2. Decisões das rodadas

### Rodada 1 — Estrutura, escopo e abordagem didática
| # | Decisão | Resolução |
|---|---|---|
| Q1 | Estrutura de arquivos em `docs/tutorial/` | **Arquivo único em `docs/tutorial/badgeforge.md`**, com sumário navegável por âncoras, mantendo consistência com os demais tutoriais planos da pasta. |
| Q2 | Escopo de perfis e funcionalidades | **Camadas 1 a 3 em passo a passo** (anônimo/rápido, conta gratuita e plano pago) + **seção curta de introdução à API v1**, referenciando `docs/badgeforge/API.md`, `docs/badgeforge/OPENAPI.md` e os guias de token em `docs/tutorial/{github,docker,npm}-token.md`. |
| Q3 | Foco didático (UI vs. URLs) | **Interface-first + anatomia da URL**: guiar o usuário pela interface (`/{locale}/tools/badgeforge` e `/dashboard/badgeforge`) e detalhar a estrutura das URLs e query params para quem edita direto no README. |
| Q4 | Exemplos visuais | **Exemplos práticos em Markdown/HTML/RST/URL/curl + diagrama Mermaid** de escolha do tipo de badge, sem capturas de tela binárias. |

### Rodada 2 — Domínio nos exemplos e escopo de edição
| # | Decisão | Resolução |
|---|---|---|
| Q5 | Domínio nos exemplos | **Usar `https://devtools.natanfiuza.dev.br`**, com nota informando que instâncias locais/self-hosted substituem pela própria `APP_URL`. |
| Q6 | Edição em arquivos existentes | **Criar apenas `docs/tutorial/badgeforge.md`** (além deste survey e do plano), mantendo intactos os guias de token existentes. |

### Defaults assumidos (não contestados)
- Idioma do documento: **português com acentuação correta**, conforme regra de `GEMINI.md` para `docs/`.
- Vocabulário canônico alinhado a `docs/glossario.md` (*Badge*, *Badge pessoal*, *Item público / item privado*, *Badge de endpoint JSON*, *Provider customizado*, *Logo embutido*, *Fonte do badge*, *TTL da badge*).
- Sem criação de ADR ou alteração de glossário: a tarefa documenta comportamento já entregue e especificado (Fases 1–9 do BadgeForge).

## 3. Relatório de fatos levantados

### Funcionalidades e limites verificados (`config/badgeforge.php` e `docs/SPEC-BADGEFORGE.md`)
- **Estilos:** `flat` (padrão), `flat-square`, `plastic`, `for-the-badge` e `social` (que fixa suas próprias cores `#555555` e `#007ec6`, ignorando `color` e `labelColor`).
- **Parâmetros de URL:** `style`, `color` (padrão `#9f9f9f`), `labelColor` (padrão `#555555`), `logo`, `logoColor` (padrão `#ffffff`), `logoSize` (8–20, padrão 10 ou 14 em `for-the-badge`), `logoPosition` (`left` padrão ou `right`), `link` (`http`/`https`), `cacheSeconds` (300–86400, teto de 3600 quando `logo=u_<ULID>`), `font` (`fira-code`, `libre-franklin`, `space-grotesk` ou padrão do estilo) e `download=1`.
- **Regras de path em badges estáticas:** `_` vira espaço, `__` vira `_`, barra `/` deve ser duplo-codificada como `%252F`, `-` e `.` são literais; texto acima de 100 caracteres é truncado com `…`; extensão obrigatória `.svg` ou `.png` (nunca `.svg.png`).
- **Formatos:** SVG canônico; PNG 1× transparente rasterizado no servidor via Imagick (largura máxima 600 px, redução proporcional acima disso; retorna 503 `image_unavailable` se Imagick não estiver instalado).
- **Providers públicos (9 providers, 15 definições):**
  - `github/{stars,forks,issues,license}` (`owner/repo`)
  - `npm/{v,dm}` (`package`)
  - `packagist/{dt,v}` (`vendor/package`)
  - `docker/pulls` (`namespace/repository`)
  - `codecov/coverage` (`owner/repo`)
  - `coveralls/coverage` (`owner/repo`)
  - `laravel/{v,dt}` (`latest`)
  - `php/v` (`latest`)
  - `python/v` (`latest`)
  - Cache padrão de 300 s (`stale_ttl` 86400 s, `fallback_ttl` 60 s); `?cacheSeconds=` e `?refresh=1` são ignorados na rota pública.
- **Conta Gratuita (Free):**
  - Logos personalizados (`?logo=u_<ULID>`): limite de 2 (`custom_logos.free_limit`), SVG sanitizado apenas com `<svg>`, `<g>` e `<path>`, máximo 256 KiB (`max_bytes: 262144`), 200 paths.
  - Presets ilimitados (`?preset=<id>` na URL do construtor).
  - Endpoint JSON (`/api/badges/endpoint/{ulid}.{svg|png}`): contrato shields v1 (`schemaVersion: 1`, `label`, `message`, opcionais `color`/`labelColor`), HTTPS porta 443 obrigatório, sem redirects, proteção SSRF com DNS pinado, TTL 300 s, rate limit por dono (Free 300/min, Pago 3000/min, Anônimo fallback 60/min).
  - Analytics básico: janela fixa de 30 dias para imagens com dono (pessoal, endpoint JSON, provider customizado).
- **Plano Pago:**
  - Logos personalizados ilimitados.
  - Badges pessoais (`/b/{username}/{ulid}.{svg|png}`): única forma de consultar **itens privados** (GitHub, npm `v`, Docker `pulls`) com PAT cifrado por badge (`encrypted`); refresh manual autenticado (`POST /badgeforge/badges/{id}/refresh`, 10 req/min); após downgrade continuam servindo e podem ser copiadas/excluídas, mas criação/edição/refresh ficam bloqueados.
  - Providers customizados (`/api/badges/custom/{ulid}/{definition}/{path}.{svg|png}`): até 10 por conta (`custom_providers.limit`), template HTTPS com placeholders no path + métricas via JSON Pointer (RFC 6901) e formatadores `text|number|percent`; template e conjunto de métricas imutáveis após criação (`label`, `pointer` e `formatter` editáveis).
  - Analytics completo: janelas de 7, 30 e 90 dias, série diária, top referrers, países, formatos, taxa de cache e exportação CSV sem dados pessoais (`ip_hash`/UA).
- **API REST v1:** base `/api/v1/badgeforge/*` com API tokens e especificação OpenAPI 3.1.0 em `/openapi/badges.yaml` (`docs/badgeforge/openapi.yaml`).

### Rótulos reais da interface em PT-BR (`lang/pt_BR/tools.php`)
- **Seções do construtor:** `Conteúdo`, `Cores`, `Logo`, `Avançado`.
- **Campos:** `Rótulo`, `Mensagem`, `Estilo`, `Cor`, `Cor do rótulo`, `Logo` (`Sem logo`), `Cor do logo`, `Tamanho do logo`, `Posição do logo` (`Esquerda`, `Direita`), `Link`, `Cache (segundos)`, `Fonte` (`Padrão do estilo`), `Formato` (`SVG`, `PNG`).
- **Prévia:** `Prévia`, `Fundo escuro`, `Fundo claro`.
- **Snippets:** `Incorporar` com abas `URL`, `Markdown`, `HTML`, `reStructuredText`.
- **Ações:** `Exemplo`, `Limpar`, `Meus logos`, `Presets`.
