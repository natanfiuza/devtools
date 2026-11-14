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
- Histórico de cores: para quem está logado vem da conta, sem limite funcional e paginado por cursor (Fase 5, ADR-127); o anônimo mantém 20 cores no `localStorage` (ADR-132).
- Favoritos de cor: para quem está logado vêm da conta e são a mesma lista do Color Palette Generator (Fase 5, ADR-127, ADR-131); o anônimo mantém 50 no `localStorage` (ADR-132).
- Importar o histórico local para a conta é uma ação explícita do painel, em lotes; nada é importado no login e o `localStorage` só é limpo depois de todos os lotes passarem (ADR-132).
- Para o anônimo a biblioteca não faz nenhuma requisição; a conta é a fonte da verdade para quem está logado, com escrita otimista e reversão em falha (ADR-131).
- Copiar — o ícone do botão clicado vira um check verde por alguns segundos e volta ao ícone de copiar.
- Alpha.

## Contraste
Black text (X:1 👍/👎)
White text (X:1 👍/👎)
AA/AAA indicadores.
Fundo de comparação explícito e escolhido pelo usuário; alpha é composto antes do cálculo.

## Acessibilidade
Labels, foco, ARIA live, teclado.
