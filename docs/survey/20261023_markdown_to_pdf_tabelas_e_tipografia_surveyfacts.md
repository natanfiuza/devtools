# Survey — Markdown to PDF: Contencao de Tabelas, Paginacao e Tipografia Padrao Google Docs (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** "Na ferramenta Markdown to PDF quando o usuario cola algum MD que tenha tabelas elas estao quebrando a pagina e escapando da area de pagina do PDF para a direita fora da margem direita. Deixe os textos no formato PDF menores pois os textos estao ficando muito grandes. Utilize o mesmo formato padrao que e utilizado no Google Docs quando abro um arquivo MD"
- **Tela / Modulo:** `/{locale}/tools/markdown-to-pdf` (`resources/js/tools/markdown-to-pdf/Tool.vue`, `resources/js/tools/markdown-to-pdf/lib/{themes,pdf,types,markdown}.ts`).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada, 4 decisoes; inspecao completa de `Tool.vue`, `themes.ts`, `pdf.ts`, `types.ts`, `ADR-037` e `markdown-to-pdf.test.ts`.
- **Arquivos-chave lidos:** `resources/js/tools/markdown-to-pdf/Tool.vue`, `resources/js/tools/markdown-to-pdf/lib/themes.ts`, `resources/js/tools/markdown-to-pdf/lib/pdf.ts`, `resources/js/tools/markdown-to-pdf/lib/types.ts`, `resources/js/__tests__/markdown-to-pdf.test.ts`, `docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md`.

## 2. Decisoes das rodadas

### Rodada 1 — Tipografia Google Docs, contencao de tabelas, paginacao e preview
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Escala tipografica no padrao Google Docs | **Proporcao Google Docs em `pt`/`em`**: base de corpo `sm` = `9pt` (`12px`), `md` (padrao) = `10pt` (`13.3px`), `lg` = `11pt` (`14.67px`), entrelinha `1.35` e espacamento de paragrafo `0.6em`. Titulos em `em` proporcionais ao seletor (`h1` = `1.65em`, `h2` = `1.35em`, `h3` = `1.15em`, `h4` = `1.05em`, `h5/h6` = `0.95em`). Tabelas e blocos de codigo em `0.9em`. Aplicar `config.fontSize` tambem em `getPrintCss`. |
| Q2 | Comportamento das tabelas na largura da pagina | **Layout automatico contido com quebra de palavras estilo Google Docs**: `width: 100%; max-width: 100%; border-collapse: collapse`, celulas `th, td` e `code` dentro de celulas com `overflow-wrap: anywhere; word-break: break-word; white-space: normal`, padding compacto (`4pt 6pt` / `5px 8px`), fonte `0.9em` e bordas `#dadce0` no tema moderno. Blocos `<pre><code>` tambem ganham `white-space: pre-wrap; overflow-wrap: anywhere`. |
| Q3 | Paginacao vertical de tabelas e margens entre paginas | **Fluxo multipagina limpo**: (1) No `@media print`, remover `break-inside: avoid` de `table` e `pre` (mantendo apenas em `tr`, `blockquote` e `img`, com `thead { display: table-header-group }`). (2) No `exportToPdf`, renderizar o clone exatamente na largura fisica util (`widthMm - 2 * marginMm`) com `padding: 0`, aplicar as 4 margens (`marginMm`) em todas as paginas no `jsPDF` e usar corte inteligente por linha em branco no canvas para evitar cortar linhas de texto ou de tabela ao meio. |
| Q4 | Escala do preview na tela | **Preview responsivo + PDF em dimensao fisica exata**: no preview (`getPageSheetStyle`), usar `width: 100%; maxWidth: ${widthMm}mm` sem rolagem horizontal forçada; no `onclone` do `exportToPdf` e no `@page` do `printDocument`, travar as dimensoes fisicas exatas do papel (`widthMm`, `minHeightMm`, `marginMm`). |

### Defaults assumidos (nao contestados)
- Processamento 100% no cliente (ADR-002, ADR-037), sem novas dependencias externas.
- Os 4 temas visuais (`modern`, `editorial`, `minimal`, `academic`), os 3 formatos de papel (`a4`, `letter`, `a5`), as 2 orientacoes (`portrait`, `landscape`), as 3 margens (`compact`, `normal`, `wide`) e as 4 familias de fontes (`sans`, `serif`, `mono`, `fira-code`) permanecem com os mesmos identificadores e chaves de traducao.
- Cores permanecem em hexadecimal solido conforme ADR-037 para compatibilidade total com `html2canvas-pro`.

## 3. Relatorio de fatos levantados

### Causa 1: Compressao flexbox no preview + estiramento no `jsPDF` (textos gigantes e corte lateral)
Em `Tool.vue`, `<article ref="documentSheet">` recebia `width: 210mm` (~794px), mas era filho direto de `<div class="flex flex-1 justify-center">` dentro do painel direito do `ToolShell` (~500px em telas desktop comuns). Como item flex com `flex-shrink: 1`, o navegador comprimia o `<article>` para ~500px; ao exportar no `exportToPdf`, o `html2canvas` fotografava a caixa de ~500px e o `jsPDF` a esticava para preencher `210mm`, ampliando todas as fontes e espacamentos em ~1,58x. Quando havia uma tabela larga, a tabela forçava a largura intrinseca alem do container, invadindo o `padding` direito e escapando da margem direita da folha.

### Causa 2: Tabelas e blocos de codigo sem contencao horizontal
Nas regras CSS de `Tool.vue` e `themes.ts`, `.markdown-pdf-document table` possuia apenas `width: 100%; border-collapse: collapse`, sem `max-width: 100%` e sem `overflow-wrap: anywhere` / `word-break: break-word` nas celulas `th, td` e tags `code` internas. Alem disso, o padding das celulas (`0.6rem 0.8rem`) e a fonte em tamanho integral consumiam espaco horizontal excessivo em tabelas com muitas colunas.

### Causa 3: `break-inside: avoid` na tabela inteira e fatiamento sem margens entre paginas
Em `themes.ts` (linha 250), `table, pre, blockquote, img` recebiam `page-break-inside: avoid; break-inside: avoid`, fazendo qualquer tabela media/grande quebrar a pagina prematuramente. Em `pdf.ts`, o `padding: ${marginMm}mm` estava no `<article>` inteiro: a pagina 1 não tinha margem inferior no corte e a pagina 2 começava no topo absoluto (`y = 0`), podendo cortar linhas de texto e bordas de tabela ao meio.

### Causa 4: Tamanhos em `rem` fixo e `getPrintCss` sem `config.fontSize`
Em `Tool.vue` e `themes.ts`, os titulos (`h1` ate `2rem` = 32px), paragrafos e tabelas usavam unidades `rem` (ancoradas no `<html>` raiz de 16px) em vez de `em` (relativo ao container `.markdown-pdf-document`), e `getPrintCss(config)` nao aplicava o tamanho de fonte configurado em `config.fontSize`.
