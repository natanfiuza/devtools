# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona ferramenta de conversão de Markdown para PDF
```

---

## 🎯 Resumo

Entrega a ferramenta **Markdown to PDF**, que converte Markdown (GFM) em documentos PDF estilizados com pré-visualização em tempo real. O objetivo é cobrir o fluxo "escrever Markdown → exportar documento formatado" sem depender de serviços externos: toda a renderização, sanitização e geração do PDF acontecem no navegador do usuário, mantendo a promessa de privacidade das ferramentas (ADR-002).

Além da feature em si, o PR ajusta a suíte de testes de registro/home, que assumia um número fixo de 8 ferramentas, para derivar a contagem do próprio registry — evitando quebra a cada nova tool adicionada.

## 🛠️ Mudanças Técnicas

- Adiciona o componente `resources/js/tools/markdown-to-pdf/Tool.vue` com editor CodeMirror (sintaxe Markdown), preview em folha simulada, contadores de palavras/caracteres, validação de tamanho de entrada e ações de exemplo, abrir, limpar, copiar, imprimir e baixar PDF.
- Cria camada de lógica isolada em `lib/`: `markdown.ts` (parser `marked` + sanitização com `DOMPurify`), `themes.ts` (dimensões de papel, margens, fontes, temas e CSS de impressão `@page`/`@media print`) e `pdf.ts` (exportação via `html2pdf.js` e impressão vetorial em iframe isolado).
- Define tipos e configurações de documento em `lib/types.ts` (formatos A4/Letter/A5, orientação, margens, famílias de fonte, tamanhos e 4 temas: modern, editorial, minimal, academic).
- Registra o manifesto `markdown-to-pdf` (status `available`, categoria `text`, ordem 90) e adiciona o ícone `file-text` ao mapa de ícones.
- Traduz toda a interface da ferramenta nos locales `en`, `es`, `fr`, `pt_BR` e `pt_PT`, incluindo rótulos de controles, temas, ações e estatísticas.
- Adiciona assets de Open Graph (`public/og/markdown-to-pdf.png`) e atualiza o `og-default.png`.
- Inclui declaração de tipos própria para `html2pdf.js` (`resources/js/types/html2pdf.d.ts`), já que a lib não publica tipagens.
- Adiciona testes unitários (`markdown-to-pdf.test.ts`) cobrindo parser, proteção contra XSS, cálculos de página, temas, CSS de impressão e comportamento do componente.
- Refatora testes de home, registry e o teste PHP de registro para usar `allTools.length`/`availableTools.length` em vez de valores fixos.

## ⚠️ Impacto/Avisos

- **Dependências novas:** `marked`, `dompurify`, `html2pdf.js`, `@codemirror/lang-markdown` e `@types/dompurify`. `html2pdf.js` carrega `html2canvas` e `jsPDF`, o que aumenta o bundle do frontend — recomenda-se considerar carregamento lazy do módulo de exportação caso o tamanho do chunk vire problema.
- **Segurança:** o HTML gerado pelo Markdown é sanitizado com DOMPurify antes de ser injetado via `v-html`; há teste cobrindo tentativas de XSS (`<script>`, `onerror`). Reforçar que qualquer novo atributo permitido em `ADD_ATTR` seja revisado.
- **Sem mudanças de banco de dados, migrations ou variáveis de ambiente.**
- **Privacidade:** nenhum conteúdo é enviado ao servidor; o processamento é 100% client-side.
- **Limite de entrada:** respeita `maxInputBytes` vindo de `config/tools.php`; arquivos acima do limite exibem erro e bloqueiam renderização/exportação.
- **Testes:** a contagem de ferramentas agora é dinâmica, então novos cards passam a ser validados automaticamente — mudança de comportamento nas asserções existentes.


close #41

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)