# Color Palette Generator

## URL
/{locale}/tools/colors/palette-generator

## Estado
**Fase 2 implementada** (2026-11-09): escala determinística de 11 tons a partir de uma cor base, contraste por tom, identificação da base, valores convertidos, cópia e exportações, tudo no navegador e sem persistência. Harmonia, quantidade variável, importação, tokens W3C DTCG/Style Dictionary e protótipos continuam nas fases seguintes.

## Entrada
Na Fase 2, apenas a **cor base** (HEX/HEX8 e demais formatos do picker compacto, com alpha). Escala, harmonia e quantidade entram nas fases seguintes; a escala é sempre a de 11 tons fixos (ADR-101).

## Escala da Fase 2
- Algoritmo determinístico em OKLCH, tabulado literalmente na ADR-120, com `PALETTE_ALGORITHM_VERSION = 1`.
- Croma relativo à base, normalizado pelo tom mais próximo; cinzas permanecem acromáticos.
- Alpha preservado nos 11 tons; contraste compõe por polaridade do texto.
- A base nunca é alterada. Quando ela não coincide com o tom 500, a tela marca o tom mais próximo e avisa que a base foi mantida intacta.
- Tons cujo croma não cabe no sRGB são marcados como cortados, sem forçar a escala.

## Critérios de aceite da Fase 2
1. `/{locale}/tools/colors/palette-generator` abre nos 5 idiomas; a URL plana `/tools/palette-generator` responde 404.
2. Com `#6366f1`, a tela mostra 11 cartões, a linha da base com os formatos convertidos, o selo no tom 600 e a nota de que a base não é o 500.
3. Cada cartão exibe HEX (ou HEX8), duas linhas de contraste com razão e veredito AA/AAA, e copia o próprio tom.
4. “Copiar escala” entrega as 11 linhas `tom: hex`; o feedback aparece em `role="status"`.
5. Limpar o campo mostra o estado vazio com “restaurar exemplo”; texto inválido mantém a escala anterior e exibe o aviso.
6. As abas CSS, SCSS, JSON, Tailwind v3 e Tailwind v4 mostram conteúdo gerado na hora e permitem copiar e baixar; o nome inválido é normalizado e avisado.
7. O seletor nativo preserva o alpha da base nos 11 tons.
8. Nenhuma requisição ao servidor e nenhuma persistência.

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
