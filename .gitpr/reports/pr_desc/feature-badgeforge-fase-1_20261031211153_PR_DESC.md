# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona BadgeForge com badges SVG estáticas e preview offline
```

---

## 🎯 Resumo

Entrega a primeira fase do **BadgeForge**, uma nova ferramenta que permite montar badges SVG (estilo shields.io) para READMEs, com prévia em tempo real e trechos prontos para Markdown, HTML e reStructuredText. O objetivo é cobrir uma necessidade recorrente de desenvolvedores — gerar selos de build/cobertura/versão — sem depender de serviços externos e sem que o conteúdo digitado saia do navegador. A renderização acontece de duas formas equivalentes: no cliente (espelho TypeScript, prévia instantânea, zero requisições) e no servidor (endpoint público `GET /api/badges/static/{label}/{message}.svg`), garantindo que o snippet copiado continue funcionando em qualquer README.

A mudança também amplia o catálogo do produto com a nova categoria **Imagens**, eleva o MVP de nove para dez ferramentas e inclui a página no sitemap.

## 🛠️ Mudanças Técnicas

- **Endpoint público stateless** `StaticBadgeController` em `/api/badges/static/{label}/{message}.svg`, com `Content-Type: image/svg+xml`, `Cache-Control` configurável (300s–86400s) e `ETag` sha256 do próprio SVG, suportando `304 Not Modified` e `HEAD`.
- **Camada de normalização** (`BadgeRequest`, `BadgeSpec`) que traduz path/query em um pedido imutável: `_`→espaço, `__`→`_`, `-` literal, barra via `%252F`, remoção de caracteres de controle, truncamento por code point e fallback silencioso para qualquer valor cosmético inválido (nunca lança exceção por entrada do usuário).
- **Renderer SVG próprio** (`StaticBadgeRenderer` + `Svg::assertSafe`) com allowlist de tags/atributos, escaping de texto e link, e defesa em profundidade contra XSS mesmo sendo um template fechado.
- **Catálogos compartilhados PHP/TypeScript** em `resources/badgeforge/`: `colors.json` (nomes CSS + aliases de badge), `styles.json` (5 estilos: flat, flat-square, plastic, for-the-badge, social) e `font-metrics.json` (tabela de avanços para cálculo de largura).
- **Registro de 24 logos embutidos** (`LogoCatalog` + SVGs em `resources/badgeforge/logos`), validados quanto a `viewBox` único e um único `<path>`.
- **Espelho TypeScript** (`lib/color.ts`, `lib/metrics.ts`, `lib/render.ts`, `lib/styles.ts`, `lib/url.ts`, `builder.ts`, `logos.ts`) com paridade byte a byte ao renderer PHP, garantida por fixtures douradas comuns.
- **Página Vue `Tool.vue`** com formulário, paleta de atalhos, seleção de logo/posição/cor, controle de cache e link, prévia em fundo claro/escuro e snippets em quatro abas com cópia para a área de transferência.
- **Configuração centralizada** em `config/badgeforge.php` (limites, defaults, registro de logos e caminhos dos assets).
- **Internacionalização** completa de textos e nova categoria `badges` em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **Fixtures compartilhadas** `tests/Fixtures/badgeforge/{cases.json,tool-props.json}` consumidas simultaneamente por Pest e Vitest.
- **Cobertura de testes**: unitários PHP (Color, FontMetrics, LogoCatalog, BadgeRequest, Renderer, ToolProps), feature do endpoint e da página, e testes Vitest de render/paridade, builder, snippets e componente.

## ⚠️ Impacto/Avisos

- **Novas rotas públicas**: `/api/badges/static/{label}/{message}.svg` e `/{locale}/tools/badgeforge`. A rota do endpoint é intencionalmente sem throttle e sem sessão (badges estáticas não consomem cota), conforme spec — se houver política de rate limit global no proxy, ela passa a ser a única barreira.
- **Nova configuração**: `config/badgeforge.php` precisa existir em todos os ambientes; os caminhos de assets apontam para `resources/badgeforge/`, que passa a ser dependência de runtime (ausência de um JSON degrada silenciosamente para defaults vazios).
- **Ativos novos no repositório**: SVGs de logos, três JSONs de catálogo, `public/og/badgeforge.png` e atualização de `public/og-default.png`.
- **Sem migrações de banco, sem novas variáveis de ambiente** e sem alteração de dependências de `composer.json`/`package.json`.
- **Contrato de paridade**: alterações futuras no renderer exigem mudança conjunta em PHP e TS e regravação das fixtures — a divergência quebra Pest e Vitest simultaneamente.
- **Compatibilidade de URLs**: `message` aceita pontos (`1.0.0`) e barras exigem dupla codificação (`%252F`); colisão com `%2F` digitado literalmente está documentada na API e é um comportamento conhecido da F1.
- **Logs**: o conteúdo do badge (rótulo/mensagem) não é registrado em log pela aplicação; o log de acesso permanece sob responsabilidade do proxy.

close #117

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_51_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)