# Color Palette Generator

## URL
/{locale}/tools/colors/palette-generator

## Entrada
Cor base, escala, harmonia, quantidade.

## Escalas
- Sempre os 11 tons fixos: `50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950` (ADR-101).
- A escolha de 5, 7, 9 ou 11 cores pertence **somente** à extração de imagem (`import-export.md`, ADR-099), nunca à escala do gerador.

## Harmonias
HSL e OKLCH.
Complementary, analogous, triadic, tetradic, monochromatic.

## Exportações
HEX, RGB, HSL, OKLCH, OKLab, CSS, SCSS, JSON, Tailwind v3/v4, tokens W3C DTCG (importáveis por Tokens Studio e Figma Variables), Style Dictionary, código.

## Protótipos
Site e mobile, light/dark, papéis editáveis, exportar PNG 1x/2x e SVG.
