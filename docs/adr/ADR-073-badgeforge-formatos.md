# ADR-073: Formatos — SVG canônico na F1, PNG na F2

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-067 (Imagick), ADR-072 (nome e escopo), ADR-076 (cache e endpoint)

## Contexto

Badges circulam em README, documentação e páginas estáticas. O formato natural é
SVG: texto, escala sem perda e arquivo pequeno. Parte do público, porém, precisa
de PNG por limitação de editor ou de plataforma que não renderiza SVG — daí a
especificação prever PNG com Imagick (ADR-067).

Converter SVG em PNG no servidor na mesma requisição encarece o caminho da
imagem e depende de extensão nativa (ImageMagick) instalada no VPS. Na F1 não há
essa infraestrutura; prometer `.png` cedo criaria um 404 disfarçado de recurso.

## Decisão

1. **SVG é o formato canônico da F1.** O documento é gerado pelo renderer PHP,
   sem dependência de biblioteca de imagem, e servido como
   `image/svg+xml; charset=utf-8`.
2. **A extensão é obrigatória no caminho.** `GET /api/badges/static/{label}/{message}.svg`
   responde 200; `.../passing` (sem extensão) e `.../passing.png` respondem 404
   na F1. `.png` não é alias de SVG.
3. **Onde termina o texto e começa a extensão**: o parâmetro `message` aceita
   pontos internos (`1.0.0`); a extensão é reconhecida no último segmento depois
   do último ponto. Um texto terminado em `.svg` é lido como extensão — limitação
   documentada na API.
4. **PNG entra na F2**, com Imagick, largura máxima de 600 px e o mesmo TTL de
   cache do SVG; a rota já está parametrizada por `{ext}`, então a F2 acrescenta
   `png` ao `where` sem mudar o resto.
5. **Nenhum outro formato** (PDF, JPG, WebP) sem ADR novo.

## Consequências

### Positivas
- A F1 não depende de extensão PHP nativa nem de fila de conversão.
- O caminho da imagem é texto puro: determinístico, pequeno e cacheável.
- A evolução para PNG não quebra contrato: a mesma URL troca a extensão.

### Negativas / Trade-offs
- Consumidores que exigem PNG precisam esperar a F2.
- A regra da extensão no texto obriga um canto de URL documentado para textos
  que terminam em `.svg`, `.png` ou ponto.

## Nota (F2, 2026-11-02)

O item 2 acima descrevia a F1: `.png` respondia 404. Com a F2 entregue, a rota
aceita `svg` e `png`, o PNG é rasterizado pelo Imagick e cacheado em disco, e a
política de tamanho, transparência e 503 está em `ADR-077`.
