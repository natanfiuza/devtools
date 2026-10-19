# ADR-037: Suporte a cores modernas do Tailwind 4 na exportacao de PDF com html2canvas-pro e jsPDF

Status: Aceito
Data: 2026-10-19
Emenda: ADR-002 (processamento local)

## Contexto

A ferramenta Markdown to PDF (`markdown-to-pdf`) gera documentos PDF inteiramente no cliente (ADR-002).
Originalmente, utilizava-se a biblioteca `html2pdf.js` para capturar a representacao visual do documento renderizado e compilar as paginas via `jsPDF`.

Internamente, `html2pdf.js` depende da versao 1.4.1 de `html2canvas`. O parser de CSS dessa versao antiga do `html2canvas` suporta unicamente os modelos de cor legados: `rgb()`, `rgba()`, `hsl()`, `hsla()`, hexadecimal e cores nomeadas.
Com a adocao do Tailwind CSS v4 no projeto, todo o sistema de design passou a operar sob espacos de cores do CSS Color Module Level 4 (`oklch()`, `oklab()` e `color-mix(in oklab, ...)`). Em navegadores baseados em Chromium modernos (Chrome, Edge), estilos computados de classes com opacidade (como `border-neutral-200/80`), sombras (`shadow-md`) ou tokens de texto (`text-slate-800`) sao avaliados pela engine nativa do navegador retornando strings no formato `oklab(...)`.

Ao tentar fazer o download do PDF gerado, o parser do `html2canvas` disparava uma excecao fatal de execucao:
`Error: Attempting to parse an unsupported color function "oklab"`.
Alem disso, a biblioteca `html2pdf.js` e um pacote legado e sem manutencao ativa, trazendo indirecoes de prototype e dependencias obsoletas.

## Decisao

1. **Remocao de `html2pdf.js` e adocao direta de `jspdf` e `html2canvas-pro`**:
   - O wrapper legado `html2pdf.js` foi desinstalado e removido do projeto.
   - `jspdf` e `html2canvas-pro` foram adicionados como dependencias diretas e explicitas no `package.json`.
   - `html2canvas-pro` e um fork moderno com API 100% compativel com `html2canvas`, que adiciona suporte nativo aos novos modelos de cores CSS: `oklab()`, `oklch()`, `lab()`, `lch()` e `color()`.

2. **Reimplementacao direta de `exportToPdf`**:
   - A rotina `exportToPdf` em `resources/js/tools/markdown-to-pdf/lib/pdf.ts` foi reescrita sem intermediarios.
   - O elemento e capturado em alta definicao (`scale: 2`) com fundo `#ffffff`.
   - Um hook `onclone` neutraliza estilos visuais especificos do preview de tela (bordas, sombras de card e cantos arredondados) antes da fotografia do canvas, garantindo que a folha física do PDF nao apresente artefatos de tela.
   - O fatiamento em paginas e o posicionamento proporcional em milimetros sao calculados com base nas dimensoes do papel (`getPaperDimensions`) e salvos diretamente pelo `jsPDF`.

3. **Padronizacao de cores dos temas em hexadecimal**:
   - Os temas visuais em `themes.ts` e `Tool.vue` definem suas cores base diretamente em hexadecimal solido (Moderno: `#0f172a` / `#1e293b`, Editorial: `#1c1917`, Minimalista: `#000000`, Academico: `#171717`), prevenindo variacoes de interpretacao entre navegadores e mantendo consistencia com a impressao vetorial (`getPrintCss`).

## Consequencias

- A exportacao de Markdown para PDF funciona perfeitamente em navegadores modernos com Tailwind CSS v4, eliminando completamente a falha de parsing de `oklab`.
- O tamanho e a qualidade do pacote sao otimizados pela eliminacao de wrappers legados, mantendo processamento 100% local no navegador em estrito cumprimento ao ADR-002.
- A folha exportada em PDF reflete fielmente o padrao visual de papel limpo, sem sombras de janela ou bordas de preview.
