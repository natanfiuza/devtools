# Plano — Fase 5, Etapa 5: exportação de design tokens

- Data: 2026-11-14
- Branch: `feature/colors-fase-5`
- Pré-requisito: Etapas 1–4 concluídas e verdes (histórico, favoritos,
  favoritos de paletas, presets).
- Aprovação: ADR-095 (W3C DTCG, sem API do Figma) e o prompt da Fase 5; o
  usuário autorizou a continuação com "pode continuar". Esta etapa só começa
  depois da aprovação deste plano.

## Objetivo

Exportar a paleta como **design tokens** reutilizáveis, reaproveitando os
exportadores existentes:

- primitivas: os 11 tons da escala, nos formatos já publicados;
- semânticas: os 8 papéis × 2 temas do editor, quando a paleta tem papéis;
- formatos novos: **W3C DTCG JSON** (importável por Tokens Studio e Figma
  Variables), **Style Dictionary JSON** e **TypeScript**;
- versão do formato registrada no próprio arquivo (onde o formato permite);
- alpha preservado em todos os formatos;
- conversões de gamut documentadas (OKLCH → sRGB, croma cortado);
- tudo no navegador: nenhuma requisição ao servidor, nenhuma dependência nova.

## Decisões de escopo

1. **Tokens semânticos só no editor.** O gerador não tem papéis (`PaletteRoles`
   existe a partir da F4/ADR-126, no editor). No gerador a exportação continua
   só com primitivas; no editor, `ExportPanel` recebe `roles` e acrescenta o
   grupo semântico. O gerador não ganha uma segunda fonte de papéis.
2. **Formatos existentes continuam byte a byte iguais sem `roles`.** CSS, SCSS,
   JSON e Tailwind permanecem iguais quando chamados pelo gerador; com `roles`
   só CSS/SCSS/JSON ganham linhas semânticas. Tailwind v3/v4 ficam primitivos:
   papéis claro/escuro não cabem em `theme.colors` sem inventar convenção.
3. **Nomenclatura semântica explícita.** CSS/SCSS usam
   `--color-{theme}-{role}` / `$color-{theme}-{role}` (ex.:
   `--color-light-primary`), sem depender de `prefers-color-scheme` ou de uma
   classe `.dark`. DTCG/SD/TS aninham `semantic.{light,dark}.{role}`. As
   primitivas mantêm o slug no caminho: `color.{slug}.{tone}` (contrato já
   publicado em `docs/colors/export-formats.md`).
4. **JSON nativo é o formato de ida e volta.** Ganha `formatVersion`,
   `clippedTones` e, com papéis, `semantic`. É o formato que a Etapa 6 vai
   reimportar sem perdas; DTCG e Style Dictionary entram na importação também.
5. **Versionamento.** `COLOR_TOKEN_FORMAT_VERSION = 1`, exportado pelo módulo.
   Registrado em: DTCG `$extensions["devtools.colors"]`, JSON nativo
   (`formatVersion`), comentário de cabeçalho de CSS/SCSS/TypeScript. O JSON do
   Style Dictionary fica só com tokens (o schema clássico não tem slot de
   metadados); a versão aparece na UI, no painel e na documentação.
6. **Gamut.** Tons marcados como `clipped` são listados no arquivo
   (`clippedTones`) e num comentário/descrição; a nota é neutra de idioma, como
   os comentários atuais. A explicação completa (OKLCH → sRGB, croma reduzido
   preservando L e H) vai para `docs/colors/export-formats.md`.

## Arquivos afetados

### Novos

- `resources/js/tools/palette-generator/lib/tokenExporters.ts` — modelo puro de
  tokens (primitivas + semânticas), `COLOR_TOKEN_FORMAT_VERSION`,
  `buildDtcg`, `buildStyleDictionary`, `buildTypeScript`, helpers de nome de
  identificador TS e de comentário de gamut. Sem i18n.
- `resources/js/__tests__/palette-token-exporters.test.ts` — contrato dos
  formatos novos.
- `resources/js/__tests__/palette-export-panel.test.ts` — `ExportPanel` com
  `roles`: abas novas, grupo semântico no conteúdo e nota de papéis incluídos.

### Alterados

- `resources/js/tools/palette-generator/lib/exporters.ts` — `PaletteExportFormat`
  ganha `dtcg`, `style-dictionary` e `typescript`; `buildPaletteExport` e
  `buildAllExports` aceitam `roles?: PaletteRoles`; CSS/SCSS/JSON ganham a parte
  semântica apenas quando `roles` existe; JSON ganha `formatVersion` e
  `clippedTones`. Delega DTCG/SD/TS ao `tokenExporters.ts`.
- `resources/js/tools/palette-generator/components/ExportPanel.vue` — prop
  opcional `roles`; repassa aos builders; nota "papéis semânticos incluídos"
  quando presente; abas novas entram pela lista de formatos.
- `resources/js/tools/palette-generator/GeneratorWorkspace.vue` — prop opcional
  `roles`, repassada ao `ExportPanel`.
- `resources/js/pages/dashboard/colors/PaletteEditor.vue` — passa
  `:roles="roles"` ao `GeneratorWorkspace` (a ref reativa já existe).
- `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` — `export.tabs.dtcg`,
  `export.tabs.style_dictionary`, `export.tabs.typescript` e
  `export.semantic_included`. Nenhuma string visível hardcoded.
- `resources/js/__tests__/palette-exporters.test.ts` — lista de formatos,
  `EXPECTED_FILE` e formato nativo JSON (campos aditivos).
- `resources/js/__tests__/palette-generator.test.ts` — dicionário i18n e aba
  DTCG presente no painel.
- `docs/colors/export-formats.md` — contrato exato F5 (DTCG com
  `$extensions`, SD, TS, semânticas, alpha, gamut, versão).
- `docs/colors/import-export.md` — exportação de tokens implementada;
  importação continua marcada para a Etapa 6.

### Sem mudanças

Backend, rotas, migrations, config, PHP, dependências (`package.json` e
`composer.json` intocados). Nenhuma requisição ao servidor.

## Contrato dos formatos novos

### W3C DTCG — `{slug}.tokens.json`

```json
{
  "$description": "primary - generated by devtools palette generator (tokens v1, algorithm v1)",
  "$extensions": {
    "devtools.colors": {
      "formatVersion": 1,
      "algorithmVersion": 1,
      "base": "#6366f1",
      "clippedTones": []
    }
  },
  "color": {
    "primary": {
      "50": { "$type": "color", "$value": "#eef2ff" },
      "500": { "$type": "color", "$value": "#6366f1" },
      "semantic": {
        "light": { "primary": { "$type": "color", "$value": "#4f46e5" } },
        "dark": { "primary": { "$type": "color", "$value": "#818cf8" } }
      }
    }
  }
}
```

- 11 tons na ordem 50–950, `$type: "color"` por token.
- `semantic` só existe quando `roles` é passado (editor); 8 papéis por tema,
  na ordem declarada em `PaletteRole`.
- HEX8 preserva alpha (`#rrggbbaa`), que é um valor CSS válido aceito pelo
  Tokens Studio; consumidores sem suporte a alpha descartam o canal.
- `$extensions` usa namespace próprio; Tokens Studio/Figma Variables ignoram
  chaves desconhecidas.
- Sem API do Figma: o fluxo é baixar o JSON e importar pelo plugin.

### Style Dictionary — `{slug}.style-dictionary.json`

```json
{
  "color": {
    "primary": {
      "50": { "value": "#eef2ff", "type": "color" },
      "semantic": {
        "light": { "primary": { "value": "#4f46e5", "type": "color" } }
      }
    }
  }
}
```

Mesma árvore do DTCG com o vocabulário clássico (`value`/`type`). Sem metadados
para não virar token de cor ou quebrar o v3.

### TypeScript — `{slug}.tokens.ts`

```ts
/**
 * primary — devtools palette generator.
 * Tokens format v1 · algorithm v1 · base #6366f1.
 * Gamut: OKLCH mapped to sRGB; tones 600 had chroma reduced to fit.
 */
export const primaryTokens = {
    color: {
        50: '#eef2ff',
        // ...
        semantic: {
            light: { primary: '#4f46e5' /* ... */ },
            dark: { primary: '#818cf8' /* ... */ },
        },
    },
} as const;
```

- Identificador `{camelCase(slug)}Tokens`; se começar com dígito recebe prefixo
  `palette` (identificador TS válido), testado.
- `as const`, sem dependências.
- Comentário de cabeçalho com versões, base e gamut (mesmo quando vazio, a
  linha de gamut só aparece se houver tons cortados).

### CSS/SCSS/JSON existentes com `roles`

- CSS: após as 11 primitivas, `--color-light-{role}` e `--color-dark-{role}`.
- SCSS: `$color-light-{role}: … !default;`.
- JSON: `{ name, formatVersion, algorithmVersion, clippedTones, colors,
  semantic? }`, `semantic = { light: {…}, dark: {…} }`.
- MIME/filename dos novos: `application/json;charset=utf-8`,
  `text/typescript;charset=utf-8`; nomes conforme tabela do doc.

## Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Contrato DTCG/SD publicado e depois alterado quebra quem já importou | Formato travado por testes e por `formatVersion`; mudanças futuras exigem versão nova e emenda à ADR-095 |
| Alpha perdido por consumidores | HEX8 nos formatos de token, `rgba()` no CSS, comportamento documentado no arquivo e no doc |
| Slug iniciando com dígito gerar TS inválido | helper com prefixo + teste dedicado |
| Regressão nos 5 formatos atuais | testes existentes rodam sem `roles`; asserção explícita de igualdade byte a byte |
| Abas demais no painel | `TabsList` já quebra linha (`flex-wrap`); 8 abas continuam legíveis |
| Papéis com cores fora da escala | tokens semânticos são literais; não tentam ser aliases das primitivas |
| Semânticas não aparecerem no gerador | decisão explícita (papéis são do editor/ADR-126); documentada no doc e na nota do painel |

## Testes

Unitários (`palette-token-exporters.test.ts`):

- DTCG: `$type`/`$value` nos 11 tons, ordem, `color.{slug}`, `$extensions`
  (versão/algoritmo/base/clipped), `$description`, semânticas com `roles` e
  ausência sem `roles`;
- Style Dictionary: `value`/`type`, mesma árvore, sem metadados;
- TypeScript: identificador válido (inclusive slug numérico), `as const`, tons,
  semânticas, versões no cabeçalho;
- alpha HEX8 preservado em DTCG/SD/TS/JSON e `rgba()` em CSS/SCSS;
- gamut: `clippedTones` e comentário apenas quando há cromas cortados;
- filename/MIME de cada formato novo e `buildAllExports` com 8 arquivos.

Regressão:

- `palette-exporters.test.ts`: os 5 formatos sem `roles` continuam iguais;
  JSON mantém `name`/`algorithmVersion`/`colors` e ganha os campos aditivos;
- `palette-generator.test.ts`: abas novas traduzidas e presentes;
- `palette-export-panel.test.ts`: `roles` chegam ao conteúdo (DTCG/CSS) e a
  nota de semânticas aparece; sem `roles` a nota some.

Comandos: `npx vitest run` dos arquivos citados; depois `npm run test`,
`npm run types:check`, `npm run lint:check`, `npm run build` e `composer test`
(sem mudança de backend, mas fecha a verificação da etapa). Relatório em
`docs/claude-code/reports/feature_colors-fase-5/20261114_feature_colors-fase-5_stage5_token_export.md`.
Sem commit/push.

## Fora desta etapa

- Importação validada de tokens (Etapa 6): JSON nativo, DTCG e Style
  Dictionary, com estrutura/tamanho/profundidade/refs/ciclos, sem executar,
  diagnósticos sem perder estado, fixtures de referência.
- Emendas à ADR-095 (versão de formato, semânticas) e atualização de
  SPEC/README/glossário (Etapa 7).
