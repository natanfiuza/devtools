# Colors — índice da documentação

Track do grupo Colors do devtools: HTML Color Picker e Color Palette Generator.
Estado atual: **Fase 1 implementada** (HTML Color Picker completo, incluindo área 2D, matiz, alpha e sliders RGB/RGBA e HSL/HSLA). A **Fase 2** (Color Palette Generator) está planejada e será executada na branch `feature/colors-fase-2`, depois do commit da Fase 1.

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

Numeração vigente: **ADR-091 a ADR-119**, em `docs/adr/` com o prefixo `colors` no nome do arquivo (ex.: `ADR-091-colors-espacos-de-cor.md`), como o BadgeForge.
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

ADRs criados na Fase 1:

| ADR | Tema |
|---|---|
| 116 | grupo de tools na URL |
| 117 | núcleo de cor próprio e gamut |
| 118 | persistência local do picker |
| 119 | arquivamento de paleta |

## Emendas de 2026-11-07

As decisões antigas foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-07":

- escala é sempre de 11 tons; 5/7/9/11 é só extração de imagem (ADR-092, ADR-099, ADR-101);
- OG estática em `public/og` via `seo:og-image`; OG dinâmica de paleta em `storage/app/data/og_paleta_image`, nunca versionada (ADR-105);
- Free 25 paletas; pago sem limite funcional (ADR-096);
- exportação Figma em W3C DTCG JSON, sem API do Figma (ADR-095);
- cache de moderação em `ai_cache` (ADR-110);
- curtida anônima por identificador assinado de primeira parte (ADR-112);
- threads de comentários em no máximo 2 níveis (ADR-111).

## Regras de manutenção

- Arquivo novo só entra aqui depois de ser listado neste índice.
- Decisão nova relevante vira ADR em `docs/adr/ADR-0NN-colors-<slug>.md` sem reutilizar número ocupado.
- Toda correção de decisão anterior é emenda datada, não reescrita silenciosa.
