# Plano - Markdown to PDF: Contencao de Tabelas, Paginacao e Tipografia Padrao Google Docs

Branch: `develop_natan`.
Data: 2026-10-23.
Escopo: Correcao do escape horizontal e quebra de pagina de tabelas no PDF/preview e reducao/padronizacao da escala tipografica para o formato padrao do Google Docs na ferramenta Markdown to PDF (`markdown-to-pdf`).

---

## 1. Diagnostico e Decisoes Alinhadas (`/grill-with-docs`)

1. **Escala Tipografica no Padrao Google Docs (Q1)**:
   - **Tamanhos base de corpo (`getFontSizeClasses` e `getFontSizeCss`)**:
     - `sm` (Pequeno): `9pt` (`12px`), entrelinha `1.35`.
     - `md` (Medio — padrao): `10pt` (`13.33px`), entrelinha `1.35`.
     - `lg` (Grande): `11pt` (`14.67px`), entrelinha `1.35`.
   - **Hierarquia proporcional em `em`** (escala automaticamente com o seletor `sm | md | lg` tanto na tela quanto no PDF e na impressao):
     - `h1`: `1.65em` (~16.5pt no padrao), `line-height: 1.25`, `margin-top: 1.1em`, `margin-bottom: 0.45em`.
     - `h2`: `1.35em` (~13.5pt no padrao), `line-height: 1.3`, `margin-top: 1em`, `margin-bottom: 0.4em`.
     - `h3`: `1.15em` (~11.5pt no padrao), `line-height: 1.3`, `margin-top: 0.85em`, `margin-bottom: 0.35em`.
     - `h4`: `1.05em`, `h5` e `h6`: `0.95em`.
     - `p`, `ul`, `ol`: `margin-bottom: 0.6em`, listas com `padding-left: 1.4em` e `li` com `margin-bottom: 0.2em`.
     - `table`, `pre`, `code`: `font-size: 0.9em` (~9pt no padrao).
   - **Impressao (`getPrintCss`)**: passa a injetar explicitamente `font-size` e `line-height` de acordo com `config.fontSize`.

2. **Contencao Estrita de Tabelas e Blocos de Codigo dentro da Margem Direita (Q2)**:
   - `.markdown-pdf-document table`: `width: 100%; max-width: 100%; border-collapse: collapse; margin: 0.75em 0; font-size: 0.9em;`.
   - `.markdown-pdf-document th, .markdown-pdf-document td`: `padding: 4pt 6pt` (`5px 8px`), `overflow-wrap: anywhere; word-break: break-word; white-space: normal; vertical-align: top;`.
   - `.markdown-pdf-document td code, .markdown-pdf-document th code`: `white-space: normal; overflow-wrap: anywhere; word-break: break-word;`.
   - `.markdown-pdf-document pre`: `max-width: 100%; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; font-size: 0.9em; padding: 0.65em 0.8em;`.
   - Bordas de tabela no tema `modern` ajustadas para `#dadce0` (padrao Google Docs) com cabecalho `#f8f9fa`.

3. **Paginacao Multipagina e Margens Reais nas 4 Bordas (Q3)**:
   - **No `@media print` (`themes.ts`)**:
     - Remover `table` e `pre` da regra `page-break-inside: avoid; break-inside: avoid` (mantendo apenas `tr`, `blockquote` e `img`), permitindo que tabelas longas fluam naturalmente entre paginas sem empurrar a tabela inteira para a pagina seguinte.
     - Adicionar `thead { display: table-header-group; }` para repetir o cabecalho da tabela em cada pagina impressa.
   - **No `exportToPdf` (`pdf.ts`)**:
     - Calcular a largura e a altura uteis da folha: `contentWidthMm = widthMm - 2 * marginMm` e `contentHeightMm = minHeightMm - 2 * marginMm`.
     - No `onclone` do `html2canvas`, fixar o elemento clonado exatamente na largura fisica util (`width: ${contentWidthMm}mm`, `maxWidth: ${contentWidthMm}mm`, `minHeight: auto`, `padding: 0`, `margin: 0`, `boxSizing: 'border-box'`), eliminando qualquer compressao ou distorcao do flexbox do painel de preview.
     - Fatiar o canvas usando a altura util (`pxPageContentHeight = Math.floor(canvas.width * (contentHeightMm / contentWidthMm))`) com busca de linha horizontal em branco (`findSafeSliceHeight`) proxima ao fim da pagina para nao cortar linhas de texto ou de tabela ao meio.
     - Inserir cada fatia no `jsPDF` posicionada em `(x = marginMm, y = marginMm, width = contentWidthMm, height = sliceHeightMm)`, garantindo margens superior, inferior, esquerda e direita em todas as paginas do PDF.

4. **Preview Responsivo na Tela sem Distorcer o PDF (Q4)**:
   - Em `getPageSheetStyle(config)` (`themes.ts`): definir `width: '100%'`, `maxWidth: '${widthMm}mm'`, `minHeight: '${minHeightMm}mm'`, `padding: '${marginMm}mm'` e `fontFamily: getFontFamilyCss(config.font)`.
   - Em `Tool.vue`: adicionar `min-w-0 overflow-x-hidden` ao `<article ref="documentSheet">` para que o preview se adapte suavemente a qualquer largura de painel, enquanto o `onclone` do `exportToPdf` aplica a largura fisica real em milimetros.

---

## 2. Arquivos a Modificar

1. [`resources/js/tools/markdown-to-pdf/lib/themes.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/lib/themes.ts):
   - Adicionar `getFontSizeCss(size: FontSize): { fontSize: string; lineHeight: string }` e atualizar `getFontSizeClasses(size: FontSize)` para os tamanhos no padrao Google Docs (`12px` / `9pt`, `13.3px` / `10pt`, `14.67px` / `11pt`, `leading-[1.35]`).
   - Atualizar `getPageSheetStyle(config)` para usar `width: '100%'` e `maxWidth: '${widthMm}mm'`.
   - Atualizar `getDocumentTypographyCss(theme)` com a escala em `em`, contencao de tabelas (`max-width: 100%`, `overflow-wrap: anywhere`, `word-break: break-word`, padding `5px 8px`, `font-size: 0.9em`), contencao de `pre`/`code` (`pre-wrap`) e cores de tabela do padrao Google Docs.
   - Atualizar `getPrintCss(config)` para incluir `font-size` e `line-height` de `config.fontSize`, `thead { display: table-header-group; }` e liberar quebra de pagina interna em `table` e `pre` (mantendo `break-inside: avoid` em `tr`, `blockquote`, `img`).
2. [`resources/js/tools/markdown-to-pdf/Tool.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/Tool.vue):
   - Sincronizar o bloco `<style>` de `.markdown-pdf-document` e dos 4 temas com as mesmas regras de tipografia Google Docs, contencao de tabelas e `pre-wrap` de `themes.ts`.
   - Adicionar `min-w-0 overflow-x-hidden` no `<article ref="documentSheet">`.
3. [`resources/js/tools/markdown-to-pdf/lib/pdf.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/lib/pdf.ts):
   - Atualizar `exportToPdf` para calcular `contentWidthMm` e `contentHeightMm` descontando `2 * marginMm`.
   - No `onclone`, travar `width: ${contentWidthMm}mm`, `maxWidth: ${contentWidthMm}mm`, `minHeight: auto` e `padding: 0`.
   - Implementar `findSafeSliceHeight` (inspecao de linhas de pixels claros proximas ao limite da pagina no canvas 2D, com fallback seguro quando `getContext('2d')` ou `getImageData` nao estiver disponivel em ambiente de teste/jsdom) e desenhar cada pagina no `jsPDF` em `(marginMm, marginMm, contentWidthMm, sliceHeightMm)`.
4. [`resources/js/__tests__/markdown-to-pdf.test.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/__tests__/markdown-to-pdf.test.ts):
   - Atualizar assercoes existentes (`getPageSheetStyle`, `getFontSizeClasses`, `getPrintCss`, `exportToPdf`).
   - Adicionar testes cobrindo a contencao de tabelas (`overflow-wrap: anywhere`, `word-break: break-word`), a escala de fonte no `getPrintCss`, a largura fisica util no `onclone` do `exportToPdf` e a liberacao de quebra de pagina de `table` mantendo `tr` protegido.
5. [`docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md):
   - Atualizar a documentacao da rotina `exportToPdf` e da tipografia dos temas para refletir a captura por area util (`contentWidthMm`), margens por pagina e contencao de tabelas no padrao Google Docs.

---

## 3. Verificacao

- `npm run test` (Vitest — incluindo `markdown-to-pdf.test.ts`).
- `npm run types:check` (`vue-tsc --noEmit`).
- `npm run lint` (ESLint).
- `composer test` (Pint, PHPStan e Pest).
- Gerar relatorio final em `docs/gemini/reports/develop_natan/20261023_develop_natan_markdown_to_pdf_tabelas_e_tipografia.md`.
