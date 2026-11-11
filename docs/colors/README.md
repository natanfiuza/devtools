# Colors — índice da documentação

Track do grupo Colors do devtools: HTML Color Picker e Color Palette Generator.
Estado atual: **Fases 1, 2, 3 e 4 implementadas**. A Fase 1 entregou o HTML Color Picker completo (área 2D, matiz, alpha e sliders RGB/RGBA e HSL/HSLA — a lacuna de canais foi fechada antes da F2). A **Fase 2** entregou o Color Palette Generator em `/{locale}/tools/colors/palette-generator`: escala determinística de 11 tons em OKLCH (ADR-120), contraste por tom, base intacta, cópia e exportações CSS/SCSS/JSON/Tailwind v3/v4. A **Fase 3** acrescentou as abas de harmonias — cinco tipos em OKLCH e HSL (ADR-121) — e de extração de 5/7/9/11 cores dominantes de imagem local ou URL HTTPS, em worker e com limites configuráveis (ADR-122). A **Fase 4** transformou o gerador em produto: paletas salvas com cota sob lock em `/dashboard/colors/palettes` (ADR-123), página pública com canonical/sitemap/OG dinâmica e duplicação (ADR-124, ADR-125), e protótipos vetoriais de site e mobile com papéis claro/escuro exportáveis em SVG e PNG 1x/2x (ADR-126). O processamento das ferramentas continua 100% no navegador; só as ações de conta falam com o servidor.

## Documentos

| Documento | Conteúdo |
|---|---|
| `SPEC-COLORS.md` | escopo geral, URLs, limites, fases F1–F8 e critérios de aceite (fonte de verdade) |
| `glossario.md` | termos do grupo Colors |
| `html-color-picker.md` | escopo da tool HTML Color Picker |
| `palette-generator.md` | escopo do Color Palette Generator |
| `import-export.md` | importação de imagem/JSON e exportação |
| `export-formats.md` | CSS, Tailwind, W3C DTCG e Style Dictionary |
| `minhas-paletas.md` | dashboard de paletas salvas |
| `paleta-publica.md` | página pública da paleta, SEO e OG |
| `og-image.md` | OG estática de tool versus OG dinâmica de paleta |
| `moderacao.md` | moderação de comentários |
| `social.md` | curtidas, comentários, follows e notificações |
| `PROMPT-INICIAL-CLAUDE-CODE.md` | prompt da Fase 1 |
| `PROMPT-Fase2-gerador_paletas.md` … `PROMPT-Fase8-auditoria-final.md` | prompts das fases seguintes |
| `docs/adr/ADR-0NN-colors-*.md` | decisões do grupo |

## ADRs de Colors

Numeração vigente: **ADR-091 a ADR-126**, em `docs/adr/` com o prefixo `colors` no nome do arquivo (ex.: `ADR-091-colors-espacos-de-cor.md`), como o BadgeForge.
Os números 057–086 que a especificação original citava já estavam ocupados no repositório (`docs/adr/`), por isso os 25 ADRs de Colors foram renumerados em 2026-11-07, sem mudar o conteúdo além das emendas da Fase 1.

| antigo | novo | | antigo | novo | | antigo | novo |
|---|---|---|---|---|---|---|---|
| 057 | 091 | | 063 | 097 | | 077 | 106 |
| 058 | 092 | | 064 | 098 | | 078 | 107 |
| 059 | 093 | | 065 | 099 | | 079 | 108 |
| 060 | 094 | | 066 | 100 | | 080 | 109 |
| 061 | 095 | | 067 | 101 | | 081 | 110 |
| 062 | 096 | | 068 | 102 | | 082 | 111 |
|  |  | | 069 | 103 | | 083 | 112 |
|  |  | | 070 | 104 | | 084 | 113 |
|  |  | | 076 | 105 | | 085 | 114 |
|  |  | |  |  | | 086 | 115 |

ADRs criados nas Fases 1–4:

| ADR | Tema |
|---|---|
| 116 | grupo de tools na URL |
| 117 | núcleo de cor próprio e gamut |
| 118 | persistência local do picker |
| 119 | arquivamento de paleta |
| 120 | algoritmo da escala de 11 tons (escada OKLCH literal, croma relativo e base intacta) |
| 121 | harmonias (ângulos, monocromática relativa, OKLCH padrão, auxiliares fora dos exports) |
| 122 | extração de cores de imagem local/URL (color-thief/MMCQ, worker, limites, CORS sem proxy, memória) |
| 123 | persistência de paletas (schema, cores efetivas, cota sob lock, arquivamento × exclusão, tags, rascunho, slug) |
| 124 | página pública, canonical/SEO/sitemap, duplicação e “uso” |
| 125 | OG dinâmica (cliente, upload validado, storage, `og_version`, serving) |
| 126 | protótipos vetoriais (papéis/temas, renderer SVG, PNG do mesmo SVG, fidelidade) |

## Emendas de 2026-11-07

As decisões antigas foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-07":

- escala é sempre de 11 tons; 5/7/9/11 é só extração de imagem (ADR-092, ADR-099, ADR-101);
- OG estática em `public/og` via `seo:og-image`; OG dinâmica de paleta em `storage/app/data/og_paleta_image`, nunca versionada (ADR-105);
- Free 25 paletas; pago sem limite funcional (ADR-096);
- exportação Figma em W3C DTCG JSON, sem API do Figma (ADR-095);
- cache de moderação em `ai_cache` (ADR-110);
- curtida anônima por identificador assinado de primeira parte (ADR-112);
- threads de comentários em no máximo 2 níveis (ADR-111).

## Emendas de 2026-11-11 (Fase 4)

As decisões anteriores foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-11":

- rascunho anônimo em `sessionStorage` (distinto de "paleta anônima") e arquivadas contando na cota (ADR-096);
- slug global, 404 uniforme, canonical sem query e sitemap só com públicas ativas (ADR-098);
- protótipo vetorial: SVG é o formato canônico e o PNG rasteriza o mesmo SVG (ADR-100);
- tags como entidade por usuário, com `key` de comparação e reordenação da conta (ADR-103);
- Minhas Paletas sem prefixo de locale, com busca/filtros/ordenação no servidor (ADR-104, ADR-109);
- OG dinâmica: upload validado, `og_version`, invalidação visual e serving só para pública ativa (ADR-105);
- slug liberado no arquivamento e reivindicado na restauração quando livre (ADR-119);
- shape persistido da extração: cores efetivas, papéis e origem reduzida, sem re-quantizar (ADR-122).

## Regras de manutenção

- Arquivo novo só entra aqui depois de ser listado neste índice.
- Decisão nova relevante vira ADR em `docs/adr/ADR-0NN-colors-<slug>.md` sem reutilizar número ocupado.
- Toda correção de decisão anterior é emenda datada, não reescrita silenciosa.
