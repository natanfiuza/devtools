# HTML Color Picker

## URL
/{locale}/tools/colors/html-color-picker

## Formatos
HEX, RGB, RGBA, HSL, HSLA, OKLCH, OKLab, nomes CSS.

## Funcionalidades
- EyeDropper (lupa), com fallback de imagem e lupa ampliada. O seletor nativo do navegador (`input[type=color]`) não é oferecido: a cor vem do campo, da área 2D, dos sliders ou do conta-gotas.
- Preview.
- Sliders RGB/RGBA e HSL/HSLA, mais área 2D e matiz. O slider de opacidade é único e cobre o “A” de RGBA/HSLA; os canais RGB e HSL ficam em abas, cada um com slider e campo numérico.
- Contraste.
- Shades/tints.
- Histórico local de 50 cores (ADR-118).
- Favoritos locais de 50 cores (ADR-118).
- Copiar — o ícone do botão clicado vira um check verde por alguns segundos e volta ao ícone de copiar.
- Alpha.

## Contraste
Black text (X:1 👍/👎)
White text (X:1 👍/👎)
AA/AAA indicadores.
Fundo de comparação explícito e escolhido pelo usuário; alpha é composto antes do cálculo.

## Acessibilidade
Labels, foco, ARIA live, teclado.
