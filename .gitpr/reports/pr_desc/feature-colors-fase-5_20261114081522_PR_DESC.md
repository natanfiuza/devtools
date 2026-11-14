# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona exportação de tokens DTCG, Style Dictionary e TS
```

---

## 🎯 Resumo

O gerador de paletas exportava apenas CSS, SCSS, JSON e Tailwind, o que obrigava o designer a converter manualmente as cores para ferramentas de design tokens. Esta entrega permite baixar a paleta já no formato esperado por Tokens Studio/Figma Variables (DTCG), Style Dictionary e TypeScript, além de propagar os papéis semânticos claro/escuro definidos no editor de paletas para todas as saídas — sem alterar em nada as exportações do gerador, que continua puramente primitivo.

## 🛠️ Mudanças Técnicas

- Adiciona `tokenExporters.ts` com os geradores DTCG (`$type`/`$value` + `$extensions`), Style Dictionary (`value`/`type`) e TypeScript (`as const`), todos puros e determinísticos.
- Introduz `COLOR_TOKEN_FORMAT_VERSION` como contrato versionado do arquivo de tokens, com `algorithmVersion`, `clippedTones` e nota de gamut (OKLCH → sRGB) registrados nos metadados.
- Estende `PALETTE_EXPORT_FORMATS` e `buildPaletteExport`/`buildAllExports` para incluir os três novos formatos e o parâmetro opcional `roles`.
- CSS, SCSS e JSON ganham a camada semântica (`--color-light-primary`, `$color-dark-border`, `semantic` no JSON) somente quando `roles` é fornecido; Tailwind permanece só com primitivas.
- Propaga `roles` de `PaletteEditor` → `GeneratorWorkspace` → `ExportPanel`, que passa a exibir a nota `export-semantic-note`.
- Preserva alpha em HEX8 e valida identificadores TypeScript via `tokenIdentifier` (camelCase com prefixo `palette` quando inicia com dígito).
- Adiciona chaves de i18n `semantic_included` e rótulos das novas abas em en, es, fr, pt_BR e pt_PT.
- Cobre o comportamento com `palette-token-exporters.test.ts`, `palette-export-panel.test.ts` e atualizações em `palette-exporters.test.ts` e `palette-generator.test.ts`.

## ⚠️ Impacto/Avisos

- **Formato do JSON alterado**: o export JSON agora inclui `formatVersion`, `clippedTones` e, opcionalmente, `semantic` — consumidores que comparam chaves estritamente precisam ser ajustados.
- **Novo contrato público**: o arquivo de tokens passa a ser versionado por `COLOR_TOKEN_FORMAT_VERSION`; a importação (F6) deve validar esse número.
- **Sem mudanças de banco, env ou dependências**: nenhum ajuste de infraestrutura é necessário.
- Exportações do gerador permanecem idênticas às da F2; a camada semântica só aparece quando o editor de paletas fornece `roles`.

close #153

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)