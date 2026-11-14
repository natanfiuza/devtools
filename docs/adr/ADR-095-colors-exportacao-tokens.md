# ADR-095 - Exportação de tokens
Status: Aceito

## Decisão
Exportar Design Tokens do W3C (DTCG) JSON, Style Dictionary JSON, CSS, SCSS, Tailwind v3/v4, JSON e código.

## Consequências
Compatível com workflows de design system.

## Emenda 2026-11-07
"Figma Tokens JSON" fica definido como o formato DTCG (`$type`/`$value`), importável pelo plugin Tokens Studio e pelo plugin Figma Variables, com passo a passo documentado na F5. Não há integração com a API do Figma.

## Emenda 2026-11-14
A F5 detalha o contrato de exportação (ADR-130): 11 primitivas e, quando o editor fornece papéis, 8 papéis × claro/escuro como valores literais; DTCG, Style Dictionary e TypeScript além de CSS/SCSS/JSON nativo; `COLOR_TOKEN_FORMAT_VERSION = 1` registrado no JSON nativo, no DTCG (`$extensions["devtools.colors"]`) e no cabeçalho de CSS/SCSS/TypeScript, com o JSON do Style Dictionary limpo; metadados de versão, algoritmo, base, `clippedTones` e gamut no namespace `devtools.colors`; alpha em HEX8 onde o formato suporta e `rgba()` em CSS/SCSS/Tailwind v3. O gerador puro exporta só primitivas (não tem papéis, ADR-126); Tailwind v3/v4 continuam sem semântica. A importação validada do JSON nativo, DTCG e Style Dictionary fica na ADR-133; TypeScript, CSS, SCSS e Tailwind continuam apenas exportação. Não há API do Figma.
