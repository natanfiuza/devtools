# ADR-092 - Escala configurável
Status: Aceito

## Decisão
A escala gerada é sempre de 11 tons: 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950.

## Consequências
Mesma escala para anônimo e cadastrado; compatível com Tailwind e consistente entre paletas.

## Emenda 2026-11-07
A decisão anterior ("cadastrado pode escolher 5, 7, 9 ou 11 tons") foi substituída: 5, 7, 9 e 11 são tamanhos de **extração de cores de imagem** (ADR-099), não tamanhos de escala.
