# Relatório — Fase 5, Etapa 5: exportação de design tokens

- Data: 2026-11-14
- Branch: `feature/colors-fase-5`
- Tarefa: exportação de tokens primitivos e semânticos em W3C DTCG, Style
  Dictionary, TypeScript, CSS/SCSS/JSON (ADR-095), 100% no navegador
- Plano: `docs/claude-code/plans/feature_colors-fase-5/20261114_feature_colors-fase-5_stage5_token_export.md`

## Decisões

- **Semânticas só no editor.** O gerador não tem papéis (ADR-126), então o
  `ExportPanel` recebe `roles` apenas pelo `GeneratorWorkspace` do editor; no
  gerador a saída é só primitiva.
- **F2 preservada.** Sem `roles`, CSS/SCSS/Tailwind v3/v4 continuam byte a byte
  iguais. O JSON nativo ganhou apenas campos aditivos (`formatVersion`,
  `clippedTones`) e `semantic` quando há papéis — por isso ele é o formato de
  ida e volta da Etapa 6.
- **Nomes semânticos explícitos.** CSS/SCSS: `--color-{tema}-{papel}` /
  `$color-{tema}-{papel}` (sem `prefers-color-scheme` ou `.dark`). DTCG/SD/TS:
  `color.{slug}.semantic.{tema}.{papel}`. Papéis são literais, não aliases.
- **Tailwind fica primitivo**: papéis claro/escuro não cabem em
  `theme.colors` sem inventar convenção.
- **Versão do formato.** `COLOR_TOKEN_FORMAT_VERSION = 1` no DTCG
  (`$extensions["devtools.colors"]`), no JSON nativo e no cabeçalho de
  CSS/SCSS/TypeScript. O JSON do Style Dictionary fica limpo (schema clássico
  sem metadados); a versão aparece no painel e na doc.
- **Alpha e gamut.** Formatos de token preservam alpha em HEX8; CSS/SCSS/
  Tailwind v3 usam `rgba()`. Tons com croma cortado aparecem em
  `clippedTones` (JSON/DTCG) e na nota de gamut (DTCG/TS); a explicação
  completa ficou em `docs/colors/export-formats.md`.
- **Sem API do Figma e sem requisição**: o JSON é baixado e importado no
  Tokens Studio/Figma Variables.

## Entregue

### Novo módulo puro

`resources/js/tools/palette-generator/lib/tokenExporters.ts`:

- `COLOR_TOKEN_FORMAT_VERSION`, `gamutNote`, `tokenIdentifier`;
- `buildDtcgTokens`: `$description`, `$extensions` (versão, algoritmo, nome,
  base, `clippedTones`, gamut) e `color.{slug}.{tom}` com `$type`/`$value`;
  `semantic.{tema}.{papel}` quando há papéis;
- `buildStyleDictionaryTokens`: mesma árvore com `value`/`type`, sem
  metadados;
- `buildTypeScriptTokens`: objeto `as const`, cabeçalho com versões/base/gamut,
  identificador camelCase (slug numérico recebe prefixo `palette`);
- `semanticHexMap` para o JSON nativo.

### Exportadores e UI

- `lib/exporters.ts`: formatos `dtcg`, `style-dictionary`, `typescript`;
  `roles?: PaletteRoles` em `buildPaletteExport`/`buildAllExports`; CSS/SCSS
  com 16 linhas semânticas quando há papéis; JSON aditivo; arquivos
  `{slug}.tokens.json`, `{slug}.style-dictionary.json` e `{slug}.tokens.ts`.
- `components/ExportPanel.vue`: prop `roles`, repasse aos builders e nota
  `export-semantic-note` quando presente; 8 abas.
- `GeneratorWorkspace.vue`: prop `roles` repassada ao painel.
- `pages/dashboard/colors/PaletteEditor.vue`: `:roles="roles"` no workspace
  (a ref reativa já existia).
- i18n em en, pt-BR, pt-PT, fr e es: `export.semantic_included` e abas `dtcg`,
  `style_dictionary`, `typescript`. Nenhuma string visível hardcoded.

### Docs

- `docs/colors/export-formats.md`: seção F5 com tabela, nomes, exemplos DTCG/
  SD/TS, alpha, gamut e fluxo de importação.
- `docs/colors/import-export.md`: exportação marcada como implementada;
  importação segue na Etapa 6.

## Testes

- `resources/js/__tests__/palette-token-exporters.test.ts` (novo): DTCG
  (caminho/ordem dos tons, `$type`/`$value`, extensões, semânticas por tema,
  ausência sem papéis, HEX8, determinismo), Style Dictionary (value/type, sem
  metadados), TypeScript (objeto `as const`, semânticas, gamut, identificador
  com slug numérico).
- `resources/js/__tests__/palette-export-panel.test.ts` (novo): 8 abas, nota
  de semânticas com/sem papéis, CSS e DTCG refletindo os papéis recebidos.
- `palette-exporters.test.ts`: novos formatos/MIME, JSON aditivo e camada
  semântica em CSS/SCSS/JSON; Tailwind inalterado; regressão sem papéis.
- `palette-generator.test.ts`: dicionário i18n e exportação DTCG no gerador
  sempre sem semanticas.

## Comandos executados e resultados

| Comando | Resultado |
| --- | --- |
| `npx vitest run` (4 arquivos da etapa) | 67 testes — verde |
| `npm run test` | 115 arquivos, 1409 testes — verde |
| `npm run types:check` | `wayfinder:generate` + `vue-tsc --noEmit` — verde |
| `npm run lint:check` | 0 erros; avisos de indentação de template já existentes |
| `npm run build` | build concluído (avisos de tamanho de chunk/plugins) |
| `composer test` | Pint ok; PHPStan 0 erros; Pest 1572 testes / 27957 asserções — verde |

## Pendências

- Nenhuma para esta etapa.
- Seguem: importação validada de tokens (6) e ADRs/docs finais (7).
- Sem commit/push, conforme a regra do projeto.
