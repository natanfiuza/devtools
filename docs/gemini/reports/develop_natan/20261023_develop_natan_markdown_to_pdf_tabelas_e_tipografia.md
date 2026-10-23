# Relatorio — Markdown to PDF: Contencao de Tabelas, Paginacao e Tipografia Padrao Google Docs

- **Data:** 2026-10-23
- **Branch:** `develop_natan`
- **Tarefa:** Correcao de tabelas escapando da margem direita e quebrando paginas no PDF, e reducao/padronizacao dos textos para o formato padrao do Google Docs ao abrir arquivos Markdown (`markdown-to-pdf`).
- **Survey:** [`docs/survey/20261023_markdown_to_pdf_tabelas_e_tipografia_surveyfacts.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/survey/20261023_markdown_to_pdf_tabelas_e_tipografia_surveyfacts.md)
- **Plano:** [`docs/gemini/plans/develop_natan/20261023_develop_natan_markdown_to_pdf_tabelas_e_tipografia.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/gemini/plans/develop_natan/20261023_develop_natan_markdown_to_pdf_tabelas_e_tipografia.md)

---

## 1. Causa Raiz Identificada

1. **Compressao flexbox no preview + estiramento no `jsPDF` (textos gigantes e corte lateral)**:
   - O elemento `<article ref="documentSheet">` tinha `width: 210mm` (~794px), mas estava dentro de um container flex no painel direito do `ToolShell` (~500px em telas desktop comuns).
   - O flexbox encolhia o `<article>` na tela para ~500px; ao exportar o PDF (`exportToPdf`), o `html2canvas` fotografava a caixa de ~500px e o `jsPDF` a esticava para preencher `210mm`, ampliando todas as fontes e espacamentos em ~1,58x.
   - Quando o Markdown possuia uma tabela larga, a `<table>` expandia alem do container por nao ter quebra de palavra forçada (`overflow-wrap: anywhere`), invadindo o `padding` direito e escapando para fora da margem direita da pagina.
2. **Tipografia em `rem` fixo e `getPrintCss` sem `config.fontSize`**:
   - Os titulos (`h1` ate `2rem` = 32px), paragrafos e celulas de tabela usavam `rem` (ancorado no `<html>` de 16px) em vez de `em` proporcional ao seletor de tamanho, e `getPrintCss(config)` ignorava `config.fontSize`.
3. **Quebra de pagina abrupta em tabelas e falta de margens entre paginas**:
   - No `@media print`, `table` tinha `break-inside: avoid` (empurrando tabelas medias/grandes inteiras para outra pagina).
   - No `exportToPdf`, o `padding: ${marginMm}mm` ficava apenas no container externo `<article>` e o fatiamento desenhava em `(0, 0, widthMm)`, deixando o fim da pagina 1 e o topo da pagina 2 sem margem e cortando linhas de texto/tabela ao meio.

---

## 2. Solucao Implementada

1. **Escala Tipografica no Padrao Google Docs (`themes.ts` e `Tool.vue`)**:
   - Nova funcao `getFontSizeCss(size)` e atualizacao de `getFontSizeClasses(size)` calibradas no padrao Google Docs:
     - `sm` (Pequeno): `9pt` (`12px`), entrelinha `1.35`.
     - `md` (Medio — padrao): `10pt` (`13.3px`), entrelinha `1.35`.
     - `lg` (Grande): `11pt` (`14.67px`), entrelinha `1.35`.
   - Hierarquia inteira convertida para `em` proporcional ao tamanho escolhido: `h1` (`1.65em`), `h2` (`1.35em`), `h3` (`1.15em`), `h4` (`1.05em`), `h5/h6` (`0.95em`), paragrafos/listas com `margin-bottom: 0.6em`, e tabelas/blocos de codigo em `0.9em`.
   - `getPrintCss(config)` agora injeta `font-size` e `line-height` de `config.fontSize`.

2. **Contencao Estrita de Tabelas e Blocos de Codigo na Margem Direita (`themes.ts` e `Tool.vue`)**:
   - `table`: `width: 100%; max-width: 100%; border-collapse: collapse; margin: 0.75em 0; font-size: 0.9em;`.
   - `th, td` e `th code, td code`: `padding: 5px 8px; vertical-align: top; overflow-wrap: anywhere; word-break: break-word; white-space: normal;`.
   - `pre, pre code`: `max-width: 100%; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; font-size: 0.9em;`.
   - Tema `modern` atualizado com bordas de tabela `#dadce0` e cabecalho `#f8f9fa` fieis ao Google Docs.

3. **Paginacao Multipagina, Margens nas 4 Bordas e Largura Fisica Real (`pdf.ts` e `themes.ts`)**:
   - No `@media print`: removido `break-inside: avoid` de `table` e `pre` (mantido em `tr`, `blockquote`, `img`) e adicionado `thead { display: table-header-group; }`.
   - No `exportToPdf`:
     - Calculo da area util fisica: `contentWidthMm = widthMm - 2 * marginMm` e `contentHeightMm = minHeightMm - 2 * marginMm`.
     - No `onclone`, o elemento clonado e travado em `width: ${contentWidthMm}mm`, `maxWidth: ${contentWidthMm}mm`, `minWidth: ${contentWidthMm}mm` e `padding: 0`, com `windowWidth` amplo, eliminando qualquer distorcao de escala causada pelo painel de preview.
     - Implementada `findSafeSliceHeight` para localizar linhas horizontais limpas de texto perto do limite inferior de cada pagina, evitando cortar caracteres ou linhas de tabela ao meio.
     - Cada fatia de pagina e posicionada no `jsPDF` em `(x = marginMm, y = marginMm, width = contentWidthMm, height = sliceHeightMm)`, garantindo as 4 margens em todas as paginas.
   - No preview em tela (`getPageSheetStyle` e `Tool.vue`): aplicado `width: 100%; maxWidth: ${widthMm}mm` com `min-w-0 overflow-x-hidden`.

---

## 3. Arquivos Modificados

- [`resources/js/tools/markdown-to-pdf/lib/themes.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/lib/themes.ts)
- [`resources/js/tools/markdown-to-pdf/lib/pdf.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/lib/pdf.ts)
- [`resources/js/tools/markdown-to-pdf/Tool.vue`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/markdown-to-pdf/Tool.vue)
- [`resources/js/__tests__/markdown-to-pdf.test.ts`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/__tests__/markdown-to-pdf.test.ts)
- [`docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md`](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md)

---

## 4. Validacao e Testes

- `npm run test` (Vitest): todos os testes passando (incluindo os novos testes em `markdown-to-pdf.test.ts`).
- `npm run types:check` (Wayfinder + `vue-tsc --noEmit`): 0 erros.
- `npm run lint` (ESLint): 0 erros.
- `composer test` (Pint, PHPStan e Pest): todos os testes passando.
