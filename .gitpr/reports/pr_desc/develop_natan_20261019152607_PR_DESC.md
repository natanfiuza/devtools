# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige exportação de PDF com cores modernas
```

---

## 🎯 Resumo
Substitui o `html2pdf.js` por `html2canvas-pro` + `jsPDF` para gerar PDFs do Markdown com suporte às cores modernas (`oklab`/`oklch`) usadas pelo Tailwind 4 (ADR-037). A mudança elimina falhas de renderização de tema e garante fundo branco consistente no arquivo exportado, sem a estilização de card do preview.

## 🛠️ Mudanças Técnicas
- Remove `html2pdf.js` e adiciona `html2canvas-pro` e `jspdf` ao projeto.
- Reescreve `exportToPdf` para renderizar o documento em canvas, fatiar por página e montar o PDF manualmente com `jsPDF`.
- Remove sombra, borda, arredondamento e classes de card do elemento clonado antes da captura.
- Move as cores de texto dos temas para o CSS do documento (`.markdown-pdf-document`), reduzindo dependência de classes utilitárias do Tailwind na exportação.
- Ajusta os temas para garantir fundo branco e contraste fixo no PDF.
- Atualiza testes com mocks de `html2canvas-pro` e `jsPDF`, cobrindo paginação, limpeza visual e nome do arquivo.
- Remove a declaração de tipos customizada de `html2pdf.js`.

## ⚠️ Impacto/Avisos
- Dependências alteradas: `html2pdf.js` removido; novas dependências `html2canvas-pro` e `jspdf`.
- A paginação agora é manual: conteúdos muito altos podem ser cortados entre páginas, exigindo validação visual em documentos longos.
- O PDF é composto por imagens JPEG, portanto o texto não é selecionável.
- A geração continua 100% no cliente, sem envio de dados ao servidor.
- Não há mudanças em banco de dados ou variáveis de ambiente.


close #45

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)