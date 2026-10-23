# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: ajusta tipografia e paginação do markdown para PDF
```

---

## 🎯 Resumo

O PDF gerado pelo Markdown to PDF apresentava três problemas recorrentes: tabelas e blocos de código estouravam a margem direita, a virada de página cortava linhas de texto ao meio e a tipografia não correspondia ao esperado em documentos formais. Esta mudança padroniza a tipografia no padrão Google Docs (9pt/10pt/11pt com entrelinha 1.35), contém tabelas e código dentro da área útil da folha e faz o fatiamento de páginas respeitar linhas seguras, além de respeitar as margens configuradas no posicionamento de cada página do jsPDF.

## 🛠️ Mudanças Técnicas

- Adiciona `getFontSizeCss` em `themes.ts` com valores em `pt` (sm: 9pt, md: 10pt, lg: 11pt) e `lineHeight: 1.35`, aplicados também no CSS de impressão via `!important`.
- Atualiza `getFontSizeClasses` para px equivalentes (12px / 13.3px / 14.67px) e inclui `Arial` como fallback na família padrão.
- Cria `BASE_DOCUMENT_STRUCTURE_CSS` compartilhado por todos os temas, com `overflow-wrap: break-word`, `word-break: break-word` e `max-width: 100%` em tabelas, `pre` e `code`, garantindo contenção na margem direita em todos os temas (editorial, minimal, academic, modern).
- Converte espaçamentos e tipografia dos temas de `rem` para `em`, tornando a escala proporcional ao tamanho base da fonte, e adiciona estilos explícitos para `h4`/`h5`/`h6`.
- Implementa `findSafeSliceHeight` em `pdf.ts`: inspeciona pixels via `getImageData` e busca até 3 linhas consecutivas claras para cortar páginas entre linhas de texto, com tolerância de 1.5% de pixels escuros (bordas de tabela) e fallback para o corte padrão em caso de erro.
- `exportToPdf` passa a calcular a largura/altura útil (`widthMm - 2*marginMm`) e trava essas medidas no `onclone`, além de remover padding, sombra e classes de card do preview antes da captura.
- Posiciona cada página no jsPDF em `(marginMm, marginMm)` com largura `contentWidthMm`, respeitando as 4 margens configuradas (antes desenhava em `0,0` com largura total).
- Preview na tela passa a usar `width: 100%` com `maxWidth` na medida física do papel (`getPageSheetStyle`), evitando rolagem horizontal em painéis estreitos.
- CSS de impressão adiciona `thead { display: table-header-group }` para repetir cabeçalho de tabelas longas e altera `page-break-inside: avoid` para `blockquote, img, tr`, permitindo que tabelas longas fluam entre páginas sem quebrar linhas.
- `Tool.vue` recebe `min-w-0 overflow-x-hidden` na folha do documento e ajustes de cores/espaçamentos alinhados ao padrão adotado.
- Testes atualizados e ampliados: novas asserções para `getFontSizeCss`, contenção de tabelas, `font-size` no CSS de impressão, `table-header-group`, chamada de `addImage` com margens e travas de estilo no clone.
- Documentação incluída: ADR-037 (suporte a cores modernas no html2canvas-pro), plano, relatório e survey da iniciativa.

## ⚠️ Impacto/Avisos

- **Comportamento visual alterado:** documentos existentes exportados com temas anteriores terão aparência diferente (fontes menores em pt, espaçamentos relativos em `em`, cores do tema moderno alinhadas ao Google Docs).
- **Sem mudanças em banco de dados, variáveis de ambiente ou dependências** — nenhuma migração ou ajuste de configuração é necessário.
- O fatiamento agora depende de leitura de pixels do canvas (`getImageData`); navegadores que bloqueiem canvas por CORS com imagens externas cairão no fallback de corte padrão (sem regressão funcional, apenas sem o corte inteligente).
- A opção de tamanho de fonte passa a ter efeito real no PDF (antes era apenas visual no preview), o que pode reduzir a quantidade de conteúdo por página em `sm` e aumentá-la em `lg`.
- Nenhuma alteração de API pública das funções exportadas além da adição de `getFontSizeCss` e `findSafeSliceHeight`.


close #72

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)