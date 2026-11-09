# ADR-099 - Extração de paleta de imagem
Status: Aceito

## Decisão
Extração de 5, 7, 9 ou 11 cores dominantes usando median cut, com opção OKLab. Processamento local via Canvas.

## Consequências
Privacidade; sem upload; performance depende do tamanho da imagem.

## Emenda 2026-11-07
Este é o único lugar onde 5, 7, 9 e 11 são tamanhos válidos. A escala do gerador é sempre de 11 tons (ADR-092) e as cores dominantes extraídas ficam separadas da escala 50–950.
