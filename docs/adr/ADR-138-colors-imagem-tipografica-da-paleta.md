# ADR-138 - Imagem tipográfica da paleta
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-107 (exportar paleta como imagem, emenda), ADR-115 (layouts, emenda), ADR-126 (protótipos vetoriais), ADR-133 (tokens)

## Contexto
A ADR-107 prometeu exportar a paleta como imagem tipográfica em PNG e SVG gerada no cliente; a ADR-115 fixou três layouts com marca d'água. A F6 precisava fechar as medidas, o conteúdo da composição, a sanitização do SVG e a fronteira com a infraestrutura de exportação da F5 — sem geração no servidor e sem que a imagem carregasse dado da conta. O protótipo vetorial (ADR-126) já tinha um serializador sanitizado, rasterizador e fonte embutida; a pergunta era reutilizar sem herdar as medidas e a composição do protótipo.

## Decisão

### 1. Três formatos com medidas fixas
`horizontal` 1600×500, `vertical` 1080×1920 e `card` 1080×1080, declarados em `PALETTE_IMAGE_SIZES`. As medidas não dependem da quantidade de cores: a composição se adapta dentro da área fixa, e o arquivo sai sempre no tamanho anunciado.

### 2. A composição é tipográfica
Toda imagem leva:

- o **nome** da paleta, truncado com `…` (26 caracteres no horizontal, 22 no vertical e no card) e escapado no serializador;
- cada **HEX** na ordem da paleta, normalizado em minúsculas e escrito no próprio SVG; HEX8 com alpha é aceito;
- a **marca fixa** `devtools.natanfiuza.dev.br` como texto, no rodapé ou na base, em toda exportação.

O tom da escala não entra na imagem: a imagem é a paleta, e a escala é outra composição. Nome/hora/autor da conta não entram — a fonte é só `name` e `colors` vindos da tela.

### 3. Cores inválidas são descartadas, não escapadas
`imageColors()` faz `trim`, minúsculas e filtra por `/^#[0-9a-f]{6}([0-9a-f]{2})?$/`. Um valor corrompido (`vermelho`, `#12345`, `javascript:...`) nunca vira atributo de retângulo; a imagem segue com as cores válidas. A ordem é a da paleta.

### 4. O serializador é o da ADR-126, com limite de tipos
A imagem reutiliza `renderSvg`/`rasterizeSvg` e a árvore `SvgNode` (retângulo e texto), que escapa texto e só emite `fill`, `x`, `y`, `width`, `height`, `radius`, `font` e `text-anchor`. Por construção não há `<script>`, `<image>`, `<foreignObject>`, `href`, `src` nem `url(`; o SVG é sanitizado sem etapa de pós-processamento. Texto sobre cor escura/clara usa `readableOn`, o mesmo contraste do protótipo.

### 5. A fonte entra só na exportação
A prévia é o SVG puro como `data:image/svg+xml`, sem `<style>`. A exportação importa `fonts` dinamicamente e embute a Libre Franklin como `data:font/ttf;base64` (cerca de 290 KB), de modo que o preview e o SSR não carregam a fonte. O mesmo SVG com fonte é rasterizado para PNG em 1× e 2×; a falha do canvas é traduzida e mostrada, sem quebrar a página.

### 6. 100% no navegador, nas duas telas
Não há rota, job ou requisição ao servidor. O painel vive no editor (aba **Imagem**, com o nome e as cores persistidos) e na página pública (seção recolhível, disponível também a visitante). Os arquivos saem como `palette-{layout}.svg` e `palette-{layout}.png`; SVG usa `image/svg+xml;charset=utf-8` e o download reusa `downloadBlob`.

### 7. O tipo de documento é `image`, fora do catálogo de protótipos
`PrototypeDocumentKind` ganhou `'image'`, mas `PROTOTYPE_SIZES` e `PrototypeBuildInput` usam `PrototypeBuildKind = Exclude<PrototypeDocumentKind, 'image'>`: a imagem compartilha o serializador e o rasterizador, não o catálogo de medidas dos protótipos.

## Consequências
- A paleta vira imagem compartilhável sem servidor, sem fila e sem armazenamento.
- O SVG é determinístico para a mesma paleta e formato, o que torna prévia, download e PNG o mesmo documento.
- Nome hostil e cor inválida não escapam do documento: um é escapado, o outro é descartado.
- O preview é leve; o custo da fonte fica no clique de exportar.
- A imagem não expõe conta, e-mail, slug de origem nem identidade anônima.
- O custo é um módulo de composição novo e três funções de exportação; o serializador continua único.
- Layouts e medidas são constantes de código, não configuração: mudá-los exige novo desenho e teste, como no protótipo.

## Alternativas rejeitadas
- **Gerar a imagem no servidor**: contraria “tools rodam 100% no navegador” e a ADR-107/ADR-115.
- **Capturar screenshot do DOM**: depende de CSS, fonte carregada e layout da página; o SVG determinístico é menor e previsível.
- **Usar as medidas do protótipo**: o protótipo é a escala em peças de interface; a imagem social tem proporções próprias.
- **Embutir a fonte na prévia**: carregaria ~290 KB em toda visita e no SSR sem necessidade.
- **Incluir tom, autor ou data na imagem**: a imagem deve ser só a paleta; tom é a escala e dado de conta não pertence à vitrine.
- **Sanitizar o SVG depois de montado**: seria uma segunda fonte de verdade; a árvore de tipos seguros já impede o problema.
- **Padronizar um único formato**: horizontal, vertical e card atendem usos diferentes (cabeçalho, story, feed) com a mesma composição.
