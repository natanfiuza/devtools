# DESIGN - devtools
Referencias: Linear, Vercel, Clerk, SST, opencode. Principios: tipografico, silencioso, denso, sem decoracao.
## Tokens (dark-first)
--bg:#0b0c0f; --surface:#111318; --surface-2:#171a20; --border:rgba(167,165,198,.14); --border-strong:rgba(167,165,198,.28)
--fg:#e8e9ee; --fg-muted:#8797b2; --fg-subtle:#6d8a96; --accent:#66ced6; --accent-2:#a7a5c6; --elevated:#5d707f (so bordas/superficies, NAO texto pequeno)
Light: bg #fafafb, fg #14161a, mesma familia de acentos escurecida para contraste AA (validar).
## Tipografia
Inter (UI), JetBrains Mono (codigo). Escala 12/13/14/16/20/32/56. Titulos com tracking -0.02em, peso 500-600.
## Layout
Container 1120px; header 56px sticky com blur: wordmark `devtools_` (cursor em --accent), links Tools/Pricing/Docs, seletor de idioma, Cmd+K, Login.
Home: hero (titulo 56px + subtitulo + campo de busca Cmd+K), grid 3 colunas de cards (icone de linha 20px, nome, 1 linha), categorias como chips, secao 'AI SQL Builder' em destaque com preview, footer minimo (idioma, privacidade, termos, doar).
Pagina de tool: 2 paineis (entrada | saida) redimensionaveis, barra de acoes (copiar, baixar, limpar, exemplo), atalhos visiveis, mobile empilhado em abas.
## Componentes
Button (primary --accent texto escuro; ghost; outline), Input, Select, Tabs, Dialog, Command palette, Toast, Badge, Card sem sombra (borda 1px, hover border-strong), Tooltip, Skeleton, ConsentBanner (canto inferior, nao bloqueia).
## Cores sintaxe JSON
chaves #a7a5c6; strings #66ced6; numeros #8797b2; boolean/null #6d8a96; pontuacao #5d707f.
## Motion
150ms ease-out; respeitar prefers-reduced-motion. Sem animacao decorativa.
## Acessibilidade
Contraste AA, foco visivel 2px --accent, navegacao por teclado, aria-labels, i18n com textos expansivos (fr/es ~30% maiores).
## Entregaveis de design
1) tokens.css + tailwind preset 2) pagina /styleguide com todos os componentes 3) wordmark SVG 4) favicon 5) OG image por tool (gerada) 6) mockups textuais das telas Home, Tool, AI Builder, Pricing, Admin.
