# Importação e exportação

## Imagem (Fase 3, implementada)
- Arquivo local (Canvas + `createImageBitmap`) ou URL HTTPS; **nada** é enviado ao servidor e não há proxy para CORS.
- Usuário escolhe 5, 7, 9 ou 11 cores (padrão 11); só a extração tem essa escolha, a escala do gerador continua sempre com 11 tons.
- Quantização MMCQ com `colorSpace: 'oklch'` via `color-thief@3.5.0`, num worker; o worker é encerrado ao desmontar.
- Pesos e percentuais são recalculados sobre os pixels visíveis (alpha ≥ 0,5); cores ordenadas por peso decrescente.
- Imagem com menos cores que o pedido devolve as existentes com aviso; imagem toda transparente é erro.
- Limites de bytes, lado, pixels, tamanho de análise e timeout vêm de `config/tools.php` (`limits.colors_image`).
- Permitir salvar direto: **Fase 4** (a paleta extraída fica em memória, pronta para o botão Salvar).
- Importar por URL de imagem: só HTTPS, CORS direto na origem, `content-type` raster e corte por stream em `max_bytes`; bloqueio de CORS oferece o arquivo local (ADR-122).

## JSON
- Importar tokens W3C DTCG (Tokens Studio / Figma Variables).
- Importar Style Dictionary.
- Importar paleta JSON.

## Exportação
- Individual por cor.
- Paleta completa.
- Protótipo PNG 1x/2x.
- Protótipo SVG.
- Paleta como imagem tipográfica (horizontal, vertical, card).
