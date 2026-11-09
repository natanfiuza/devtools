# ADR-091 - Espaços de cor
Status: Aceito

## Decisão
Suportar HEX, RGB/RGBA, HSL/HSLA, OKLCH, OKLab e nomes CSS. Conversões bidirecionais.

## Consequências
OKLCH é perceitualmente mais uniforme; útil para escalas e harmonias.

## Emenda 2026-11-07
RGBA/HSLA (alpha) e nomes CSS entram na cobertura. OKLCH/OKLab fora do gamut sRGB reduz o croma preservando L e H (ADR-117); arredondamento só na exibição, nunca na classificação AA/AAA.
