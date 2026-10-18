# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: adiciona o JSON Viewer e a pagina generica de tool
```

---

## 🎯 Resumo

A F2 entrega a **primeira ferramenta que funciona** (JSON Viewer) e a infraestrutura que permite acrescentar as proximas sem tocar em pagina: uma rota/controller genericos (`/{locale}/tools/{slug}`) que encaixam o `Tool.vue` do diretorio da tool, uma casca reutilizavel com dois paineis redimensionaveis (`ToolShell`), e a imagem de compartilhamento propria por tool.

O JSON Viewer roda inteiramente no navegador: o texto colado nunca sai da maquina. A analise (validar, achatar, formatar/minificar, ordenar chaves, buscar, copiar caminho JSONPath) roda num Web Worker, com queda transparente para a thread principal onde nao ha worker.

## 🛠️ Mudanças Técnicas

- **Backend**: `ToolController` responde `tools.show`; so tool `available` tem pagina (slug desconhecido, `soon` e `hidden` dao o mesmo 404). `Tools::find()` e `keywords` no registro; `Seo::openGraph(Request)` escolhe o card da tool quando existe PNG, caindo na imagem padrao caso contrario.
- **OG image**: `GenerateOgImage` passa a desenhar a imagem padrao **e** uma por tool `available` em `public/og/<slug>.png`, a partir do nome e das `keywords` do manifest. Os PNGs vao comitados.
- **Config**: `tools.limits.input_bytes` (default 5 MB) e `seo.image.tools_dir`.
- **Frontend**: `ToolShell` com divisor arrastavel/teclado e abas no mobile (`useSplitPane`, persistencia por `tool-split:<slug>`); `ClientOnly` para o que depende de DOM; `Show.vue` generica com glob lazy de `Tool.vue`; `ToolCard` linka so a tool que funciona.
- **JSON Viewer**: editor CodeMirror com tema por tokens `--json-*`, arvore virtualizada, busca sem acento/caixa, caminho JSONPath, download, drop de arquivo e limite de entrada.
- **i18n**: textos da casca (`tools.ui.*`) e do viewer nos cinco idiomas; `app.search.key_escape`.
- **Testes**: suites PHP (pagina da tool, sitemap, OG por tool, registro) e vitest (parse, arvore, caminho, transform, janela, worker, casca, pagina).

## ⚠️ Impacto/Avisos

- **Env nova**: `TOOLS_MAX_INPUT_BYTES` (opcional; default `5242880`).
- **Dependencias novas**: pacotes `@codemirror/*` e `@lezer/highlight` no `package.json`; requer `npm install`.
- **Artefatos versionados**: `public/og/json-viewer.png` (e futuros `public/og/<slug>.png`) entram no repositorio; mexer no registro ou nos nomes das tools exige rodar `GenerateOgImage` de novo.
- **Banco de dados**: sem alteracoes de schema ou migrations.
- **Status das tools**: `json-viewer` passa de `soon` para `available`; as outras sete seguem com selo "em breve" e sem link, e ficam fora do sitemap.

close #8

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_13_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)
