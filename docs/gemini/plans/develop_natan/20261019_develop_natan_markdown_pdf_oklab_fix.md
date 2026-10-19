# Plano - Correcao de Cores Modernas (OKLab/OKLCh) no Markdown to PDF

Branch: `develop_natan`.
Data: 2026-10-19.
Escopo: Correcao do erro de parsing de cores modernas (`oklab`) durante o download do arquivo PDF na ferramenta "Markdown to PDF" (`markdown-to-pdf`), removendo a biblioteca legada `html2pdf.js`, reescrevendo `exportToPdf` diretamente com `jsPDF` + `html2canvas-pro`, isolando estilos de preview dos estilos de impressao e formalizando a arquitetura no ADR-037.

---

## 1. Analise da Causa Raiz

Ao clicar no botao "Baixar PDF" (`downloadPdf`) no conversor de Markdown para PDF, a rotina `exportToPdf` em `resources/js/tools/markdown-to-pdf/lib/pdf.ts` invocava o wrapper legado `html2pdf.js`.
Por sua vez, `html2pdf.js` utiliza internamente a versao 1.4.1 do `html2canvas`.

O erro:
```text
Error: Attempting to parse an unsupported color function "oklab"
    at Object.parse (Tool-...js)
```
ocorre porque:
1. O Tailwind CSS v4 utiliza como padrao espacos de cores do CSS Color Module 4 (`oklch()`, `oklab()` e `color-mix(in oklab, ...)`). Em navegadores Chromium modernos (Chrome, Edge), estilos computados de classes com opacidade (ex.: `border-neutral-200/80`), sombras (`shadow-md`) ou tokens de texto (`text-slate-800`) sao avaliados pela engine CSS para strings de cor no formato `oklab(...)`.
2. O parser CSS do `html2canvas` 1.4.1 possui uma lista rigida de funcoes de cores suportadas (`SUPPORTED_COLOR_FUNCTIONS`: apenas `rgb`, `rgba`, `hsl`, `hsla`). Qualquer valor com `oklab` ou `oklch` dispara excecao fatal imediata.
3. O wrapper `html2pdf.js` e um pacote legado sem manutencao ativa, trazendo indirecoes desnecessarias e acoplamento a versoes antigas de dependencias.

---

## 2. Decisoes Arquiteturais

1. **Remocao do `html2pdf.js` e adocao direta de `jspdf` + `html2canvas-pro`**:
   - `html2pdf.js` e desinstalado e removido do `package.json`.
   - `jspdf` e `html2canvas-pro` passam a ser dependencias diretas e explicitas do projeto.
   - O arquivo de definicao de tipos `resources/js/types/html2pdf.d.ts` e removido.
   - Mantem conformidade estrita com o ADR-002 (processamento 100% no cliente).

2. **Reimplementacao direta e limpa de `exportToPdf` em `lib/pdf.ts`**:
   - O motor de exportacao executa `html2canvas-pro` com opcoes otimizadas:
     - `scale: 2` (para alta resolucao/DPI).
     - `useCORS: true`.
     - `backgroundColor: '#ffffff'`.
     - `onclone`: remove bordas e sombras do preview de tela antes de fotografar a folha.
   - Paginacao nativa e corte proporcional em fatias calculadas com base nas dimensoes do papel (`widthMm`, `minHeightMm`) e margens configuradas.
   - Criacao do documento PDF via `new jsPDF({ unit: 'mm', format: [widthMm, minHeightMm], orientation })`, inserindo as paginas fatiadas e salvando o arquivo diretamente via `doc.save(cleanFilename)`.

3. **Higienizacao dos estilos de tema do documento**:
   - Ajustar `resources/js/tools/markdown-to-pdf/lib/themes.ts` e `Tool.vue` para aplicar cores de texto solidas em padrao hexadecimal nos temas do documento, evitando utilitarios de opacidade do Tailwind dentro do noh exportado.

4. **Novo ADR (ADR-037)**:
   - Criacao do `docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md` documentando a transicao do `html2pdf.js` para `jsPDF` + `html2canvas-pro`.

---

## 3. Fases de Execucao

### Fase 1: Limpeza e Ajuste de Dependencias
- Desinstalar `html2pdf.js`: `npm uninstall html2pdf.js`.
- Instalar `jspdf` e `html2canvas-pro` como dependencias diretas: `npm install jspdf html2canvas-pro`.
- Remover `resources/js/types/html2pdf.d.ts`.
- Remover o alias temporario de `vite.config.ts` (desnecessario agora que usamos `html2canvas-pro` diretamente).
- **Verificacao**: `npm run types:check` e `npm run build`.

### Fase 2: Reescrita de `exportToPdf` e Temas
- Reescrever `resources/js/tools/markdown-to-pdf/lib/pdf.ts` utilizando `jsPDF` e `html2canvas-pro`.
- Ajustar `resources/js/tools/markdown-to-pdf/lib/themes.ts` para cores hexadecimais solidas nos temas.
- Ajustar `Tool.vue` para nao aplicar classes de borda/sombra na tag capturada pelo PDF.
- **Verificacao**: `npm run test` (Vitest).

### Fase 3: Documentacao e ADR
- Criar `docs/adr/ADR-037-suporte-a-cores-modernas-html2canvas-pro.md`.
- Atualizar `docs/glossario.md` se pertinente.

### Fase 4: Validacao Completa e Relatorio
- Executar `composer test` (Pint, PHPStan, Pest).
- Executar `npm run test` e `npm run types:check`.
- Executar `npm run lint` e `npm run format`.
- Gerar relatorio detalhado em `docs/gemini/reports/develop_natan/20261019_develop_natan_markdown_pdf_oklab_fix.md`.
