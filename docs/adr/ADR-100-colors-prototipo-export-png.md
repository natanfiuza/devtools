# ADR-100 - Exportação de protótipo
Status: Aceito

## Decisão
Exportar protótipo como PNG em 1x e 2x, e SVG.

## Consequências
Usar Canvas; sem dependência de servidor.

## Emenda 2026-11-11
A F4 não captura a tela: o protótipo é uma **árvore de primitivas SVG** própria (`rect`/`circle`/`text`) serializada de forma determinística, e o PNG 1x/2x é a rasterização **do mesmo SVG** (com a fonte embutida), não de um DOM pintado. Sem `html2canvas` ou equivalente; SVG é o formato canônico e o PNG nunca diverge dele. Papéis semânticos por tema, templates fixos e fidelidade por snapshots em ADR-126.
