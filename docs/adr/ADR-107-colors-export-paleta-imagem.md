# ADR-107 - Exportar paleta como imagem
Status: Aceito

## Decisão
Exportar paleta como imagem formatada como tipografia (PNG e SVG), gerada no cliente.

## Consequências
Útil para compartilhar em redes e documentação.

## Emenda 2026-11-14

Implementada na F6 (ADR-138) nas três composições fixas: horizontal 1600×500,
vertical 1080×1920 e card 1080×1080. A imagem reusa o serializador sanitizado e
o rasterizador do protótipo (ADR-126), embute a fonte só na exportação, sai em
SVG e PNG 1×/2×, é determinística e roda inteiramente no navegador, no editor e
na página pública. O conteúdo é o nome, os HEX e a marca fixa
`devtools.natanfiuza.dev.br`.
