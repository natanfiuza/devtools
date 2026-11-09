# ADR-117 - Núcleo de cor próprio e gamut
Status: Aceito
Data: 2026-11-07

## Contexto
O picker e o gerador precisam das mesmas conversões CSS Color 4, de contraste WCAG e de tratamento de gamut, com arredondamento auditável.

## Decisão
Manter um núcleo puro em `resources/js/lib/color/`, sem dependência nova e sem Vue/DOM. O estado autoritativo é sRGB + alpha; OKLCH é derivado. OKLCH/OKLab fora do gamut sRGB é mapeado reduzindo o croma por busca binária, preservando L e H, com aviso na tela e exibição do valor de origem.

## Consequências
Mais código próprio e testes de precisão; em troca, cada arredondamento e limiar fica visível e a Fase 2 reusa o núcleo. Sem P3 nesta fase.
