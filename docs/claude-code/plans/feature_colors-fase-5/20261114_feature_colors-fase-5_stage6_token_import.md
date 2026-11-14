# Fase 5 — Etapa 6: importação validada de design tokens

## Contexto

A Etapa 5 entregou exportação de tokens em W3C DTCG, Style Dictionary,
TypeScript e JSON nativo, com primitivas e papéis semânticos. A Etapa 6 fecha a
interoperabilidade: ler JSON nativo, DTCG (Tokens Studio / Figma Variables) e
Style Dictionary de volta para o gerador e o editor, validando estrutura,
tamanho, profundidade, referências e ciclos — sem executar nada, sem
requisição ao servidor e sem perder o estado atual quando o arquivo é
incompatível.

O usuário escolheu disponibilizar a importação **no gerador e no editor**.
Isso exige preservar as semânticas importadas também no fluxo anônimo → login
(rascunho), não só na paleta já salva.

## Decisões

1. **Formatos de entrada**: JSON nativo da ferramenta, DTCG e Style
   Dictionary. TypeScript continua só exportação; CSS/SCSS/Tailwind não são
   importados. Formato não reconhecido é erro com diagnóstico.
2. **Nada é executado**: o único parser é `JSON.parse` via `analyzeJson`
   (`resources/js/lib/json/parse.ts`); nunca `eval`, `Function`, import dinâmico
   ou interpretação de código. Aliases `{caminho}` são resolvidos como dados.
3. **Diagnóstico antes de mutação**: o diálogo só lê e valida. O workspace só é
   alterado ao clicar em “Aplicar”, e somente depois de o parser montar a escala
   ou a extração completa. Erro nunca descarta a paleta atual.
4. **Semânticas**: reconhecidas apenas na estrutura exportada
   (`semantic.{light,dark}.{papel}`, 8 papéis × 2 temas, HEX6/HEX8). Bloco
   incompleto vira aviso e os papéis não são importados; os papéis atuais do
   editor são mantidos.
5. **Grupo de paleta**: seleção determinística — grupo com os 11 tons = escala;
   senão grupo com 5/7/9/11 cores (fora de `semantic`) = extraída. Mais de um
   candidato válido ou nenhum vira diagnóstico (`ambiguous_palette`,
   `unsupported_color_count`); nunca “pega o primeiro”.
6. **Metadados**: `formatVersion` presente e diferente de
   `COLOR_TOKEN_FORMAT_VERSION` (1) é erro. `algorithmVersion` diferente do
   atual vira aviso e a escala importada é regravada com a versão atual
   (`PALETTE_ALGORITHM_VERSION` / `EXTRACTION_ALGORITHM_VERSION`), porque as
   cores são literais e o servidor exige a versão de `config/colors.php`.
   `clippedTones` (JSON/DTCG) é preservado na escala congelada.
7. **Ida e volta**: o JSON nativo ganha o campo aditivo `base` (versão do
   formato continua 1); DTCG já registra `base` em
   `$extensions["devtools.colors"]`; Style Dictionary não tem slot e usa o tom
   500. O teste de ida e volta compara base, 11 tons e papéis — não bytes.
8. **Rascunho anônimo**: `PaletteDraft` ganha `scale?` (11 `{tone,hex}`) e
   `roles?` opcionais, validados na leitura; campos inválidos são descartados
   sem invalidar o rascunho. Isso preserva uma escala importada e suas
   semânticas através do login. Emenda a decisão do ADR-096 (o rascunho continua
   não sendo uma paleta salva); será registrada na ADR-133 na Etapa 7.
9. **Assinatura do estado importado**: os papéis do gerador só entram no
   `PaletteSaveSource`/rascunho enquanto o snapshot atual tiver a mesma
   assinatura (tipo + base + tons/cores) do que foi importado. Mudar a base,
   aplicar preset ou editar a extração descarta os papéis importados.
10. **Limites no servidor**: `config/colors.php` `tokens.import` +
    `ColorsToolProps::tokenImportLimits()`; nenhum número no cliente. Defaults:
    512 KiB, 5000 tokens/nós de token, profundidade 40, referências 10,
    diagnósticos exibidos 20. Também entra `source_label_max_length` (80) em
    `palettes`, usado pelo `PaletteRules` e pelo label de origem da importação.
11. **Histórico**: aplicar a base importada usa o mesmo `useBase` do gerador e,
    para quem está logado, registra no histórico da conta como qualquer troca
    de base (comportamento já existente da F5). Nada do arquivo sai do
    navegador além da cor base já normalizada no histórico.
12. **i18n**: textos em `tools.shared.token_import.*` (en, pt-BR, pt-PT, fr,
    es). Mensagens de diagnóstico são escolhidas pelo código; a mensagem crua do
    `JSON.parse` nunca vai para a tela.

## Contrato do parser

Novo módulo puro `resources/js/tools/palette-generator/lib/tokenImport.ts`:

```ts
importTokens(text, {
  limits: TokenImportLimits,
  sourceLabel: string, // nome do arquivo, já truncado pelo limite do servidor
}): TokenImportResult
```

- `TokenImportResult = { ok: true, palette: ImportedPalette, diagnostics } |
  { ok: false, palette: null, diagnostics }`; `ok` depende de erros, não do
  limite de exibição.
- `ImportedPalette`: `format` (`native|dtcg|style-dictionary`), `kind`
  (`scale|extracted`), `base`, `scale: PaletteScale | null`,
  `extracted: ExtractedPalette | null`, `roles: PaletteRoles | null`,
  `tokenCount`, `name: string | null`.
- Escala: `buildFrozenScale({ base, items, algorithmVersion:
  PALETTE_ALGORITHM_VERSION, clippedTones })` (campo opcional novo).
- Extraída: `ExtractedPalette` com `weight/percentage = 0`, `sufficient: true`,
  `visiblePixels: 0`, `requested = count`, `source = { kind:'file', label }`.
- Base: `base` do JSON nativo/DTCG quando válida; senão tom 500; extraída usa
  tom 500 se existir, senão a primeira cor — sempre uma das cores.
- Detecção: `colors` (nativo), `color` com `$value` (DTCG) ou com `value`
  (Style Dictionary); mistura de estilos = `ambiguous_format`.
- Travessia iterativa (pilha), com profundidade, contagem de tokens e chaves
  proibidas (`__proto__`, `constructor`, `prototype`). Ignora metadados
  `$description`, `$extensions`, `$type`, `$value` como segmentos de caminho;
  herda `$type` do grupo e só importa tokens de cor.
- Aliases: mapa global caminho → folha; aceita `{color.x.500}` e as formas
  `.value`/`.$value` (caminho exato primeiro); resolução iterativa com
  `max_reference_depth`, `unknown_reference`, `reference_not_color`,
  `reference_cycle`. Aliases fora do grupo escolhido não bloqueiam a paleta.
- Cores: HEX6/HEX8 estritos após resolver a referência; qualquer outra coisa é
  `invalid_color` com caminho.
- Diagnósticos: `code` interno + `severity` + `path?` + `params?`. Erros:
  `empty_file`, `too_large`, `invalid_json` (linha/coluna), `root_not_object`,
  `unsupported_format`, `ambiguous_format`, `unsupported_format_version`,
  `too_deep`, `too_many_tokens`, `forbidden_key`, `mixed_token_styles`,
  `invalid_structure`, `no_colors`, `ambiguous_palette`,
  `unsupported_color_count`, `missing_tones`, `invalid_color`,
  `unknown_reference`, `reference_not_color`, `reference_cycle`,
  `reference_depth`. Avisos: `algorithm_version_mismatch`,
  `non_color_ignored`, `semantic_incomplete`, `extra_colors_ignored`.
- O parser devolve todos os diagnósticos; o diálogo exibe no máximo
  `maxDiagnostics`, com contagem de restantes. Erros ocultos nunca viram “ok”.

## Implementação em etapas

1. **Limites e props**: `config/colors.php`, `ColorsToolProps` (novo
   `tokenImportLimits()`, `sourceLabelMaxLength` em `formLimits`),
   `PaletteController@edit`, `PaletteRules` (max do label via config),
   `types/colors.ts` (`TokenImportLimits`, props do editor e do gerador),
   `ColorsToolPropsTest` e testes de props das páginas.
2. **Exportação compatível**: `base` aditivo no JSON nativo (`exporters.ts`),
   `clippedTones` opcional em `frozenScale.ts`, testes de exportação e docs.
3. **Parser puro + fixtures**: `tokenImport.ts`,
   `tests/Fixtures/colors/tokens/**` e `expected/*.payload.json`;
   `palette-token-import.test.ts`; `TokenImportFixturesTest` (Pest).
4. **Rascunho e salvamento com papéis**: `PaletteDraft.scale?/roles?`,
   validação e testes de compatibilidade; `PaletteSaveSource.roles?`;
   `SavePaletteDialog` sobrepõe `payload.roles`; helper de assinatura em
   `paletteSave.ts`.
5. **Workspace**: `applyImported(result)`, `restoreDraft` reconstrói a escala
   congelada, watcher só limpa `frozen` quando a base muda (`canonicalKey`).
6. **Diálogo + i18n**: `components/colors/TokenImportDialog.vue`,
   `tools.shared.token_import.*` nos 5 idiomas + teste de cobertura.
7. **Wiring**: `Tool.vue` e `PaletteEditor.vue` com botão, aplicação, papéis +
   assinatura, rascunho com escala/papéis.
8. **Docs e verificação**: `docs/colors/import-export.md`,
   `docs/colors/export-formats.md`, relatório da etapa.

## Arquivos principais

**Novos**: `resources/js/tools/palette-generator/lib/tokenImport.ts`,
`resources/js/components/colors/TokenImportDialog.vue`,
`tests/Fixtures/colors/tokens/**`,
`resources/js/__tests__/palette-token-import.test.ts`,
`resources/js/__tests__/palette-token-import-dialog.test.ts`,
`resources/js/__tests__/palette-workspace-import.test.ts`,
`resources/js/__tests__/token-import-i18n.test.ts`,
`tests/Unit/Colors/TokenImportFixturesTest.php`.

**Alterados**: `config/colors.php`, `app/Support/Colors/ColorsToolProps.php`,
`app/Support/Colors/PaletteRules.php`,
`app/Http/Controllers/Colors/PaletteController.php`,
`resources/js/types/colors.ts`, `resources/js/lib/paletteSave.ts`,
`resources/js/lib/saveDraft.ts`, `resources/js/lib/frozenScale.ts`,
`resources/js/tools/palette-generator/GeneratorWorkspace.vue`,
`resources/js/tools/palette-generator/Tool.vue`,
`resources/js/tools/palette-generator/lib/exporters.ts`,
`resources/js/components/colors/SavePaletteDialog.vue`,
`resources/js/pages/dashboard/colors/PaletteEditor.vue`,
`lang/{en,pt_BR,pt_PT,fr,es}/tools.php` e testes listados no plano.

## Fixtures

`tests/Fixtures/colors/tokens/`: `native-scale.json`, `native-legacy.json`,
`dtcg-scale.tokens.json`, `dtcg-aliases.tokens.json`,
`dtcg-cycle.tokens.json`, `dtcg-ambiguous.tokens.json`,
`style-dictionary-extracted.json`, `invalid-json.txt`, `bom-native.txt` e
`expected/*.payload.json`.

## Verificação

1. Vitest direcionado dos arquivos da etapa.
2. `php artisan test --filter=TokenImportFixtures`.
3. `npm run test`, `npm run types:check`, `npm run lint:check`,
   `npm run build`.
4. `composer test`.
5. Relatório com comandos e resultados reais.

Sem commit/push. Parar ao concluir a Etapa 6.

## Fora de escopo

- Importação de TypeScript, CSS, SCSS ou Tailwind.
- Endpoint de importação no servidor.
- Integração direta com a API do Figma.
- Salvar automaticamente após importar.
- Criar paleta a partir de tokens direto na lista de Minhas Paletas.
