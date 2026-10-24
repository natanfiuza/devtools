# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona formatos de saída JSON e XML ao Base64 Image
```

---

## 🎯 Resumo

Amplia o conversor Base64 Image com saídas estruturadas em JSON e XML, permitindo que o usuário obtenha metadados da imagem (nome, MIME type, tamanhos e Base64 puro) de forma pronta para integração com APIs, automações e pipelines. Antes, a ferramenta oferecia apenas Data URI, `<img>` e CSS, o que limitava o reuso dos dados fora do contexto visual.

## 🛠️ Mudanças Técnicas

- Cria `lib/formatters.ts` com funções puras: `escapeXml`, `extractRawBase64`, `formatJsonOutput`, `formatXmlOutput` e `formatSnippet`.
- Estende o tipo `OutputFormat` para incluir `'json'` e `'xml'`.
- Substitui a lógica inline de formatação no `EncoderPanel.vue` pelo `formatSnippet`, centralizando a geração de snippets.
- Adiciona abas JSON e XML com ícones `Braces` e `CodeXml`, além de `TabsList` com quebra de linha responsiva.
- Torna o botão de download dinâmico: rótulo e extensão variam conforme o formato ativo (`.json`, `.xml` ou `.base64.txt`).
- Para JSON e XML, gera `Blob` com MIME types `application/json` e `application/xml` e dispara download via `triggerDownload`.
- Adiciona testes unitários cobrindo escape XML, extração de Base64 puro, validação do JSON gerado, estrutura do XML e resolução de todos os modos em `formatSnippet`.
- Atualiza traduções em inglês, espanhol, francês, português (BR e PT) com rótulos de download e nomes dos formatos.
- Registra as capacidades `json` e `xml` no `manifest.json` da ferramenta.

## ⚠️ Impacto/Avisos

- **Sem alterações em banco de dados, variáveis de ambiente ou dependências.**
- O download no formato texto passa a usar `triggerDownload` com o `download_url` existente, mantendo o mesmo comportamento funcional.
- Usuários que consomem o snippet apenas como Data URI, `<img>` ou CSS não são afetados; as novas opções são aditivas.
- A saída XML inclui declaração `<?xml version="1.0" encoding="UTF-8"?>` e escapa caracteres reservados, garantindo documento válido para integrações externas.

close #80

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)