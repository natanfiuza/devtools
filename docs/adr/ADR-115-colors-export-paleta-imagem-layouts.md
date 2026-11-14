# ADR-115 - Layouts de exportação
Status: Aceito

## Decisão
Exportar paleta como imagem tipográfica em layouts horizontal, vertical e card. Marca d'água fixa devtools. PNG e SVG.

## Consequências
Componente de renderização parametrizado.

## Emenda 2026-11-14

As medidas e a composição foram fechadas na F6 (ADR-138): horizontal 1600×500,
vertical 1080×1920 e card 1080×1080; nome truncado com `…` (26/22/22), HEX em
minúsculas na ordem da paleta e marca fixa `devtools.natanfiuza.dev.br` em toda
imagem. O SVG é sanitizado por construção (só retângulo e texto), a fonte
embutida só na exportação e o PNG é o mesmo SVG rasterizado em 1×/2×.
