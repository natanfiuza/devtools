# ADR-099 - Extração de paleta de imagem
Status: Aceito

## Decisão
Extração de 5, 7, 9 ou 11 cores dominantes usando median cut, com opção OKLab. Processamento local via Canvas.

## Consequências
Privacidade; sem upload; performance depende do tamanho da imagem.

## Emenda 2026-11-07
Este é o único lugar onde 5, 7, 9 e 11 são tamanhos válidos. A escala do gerador é sempre de 11 tons (ADR-092) e as cores dominantes extraídas ficam separadas da escala 50–950.

## Emenda 2026-11-09
A Fase 3 implementa a extração com **MMCQ em OKLCH/RGB** via `color-thief@3.5.0` (MIT, sem dependências de runtime), encapsulada em `resources/js/tools/palette-generator/lib/extract.ts` e executada num worker; o median cut/OKLab próprio descrito acima não foi implementado. Pesos e percentuais são recalculados sobre os pixels visíveis (alpha ≥ 0,5), a paleta extraída continua separada da escala 50–950 e vive só em memória até a F4. Limites, transparência, URL e cancelamento em ADR-122.
