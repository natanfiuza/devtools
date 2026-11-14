# Fase 5 — Etapa 6: importação validada de design tokens

Data: 2026-11-14
Branch: `feature/colors-fase-5`
Plano: `docs/claude-code/plans/feature_colors-fase-5/20261114_feature_colors-fase-5_stage6_token_import.md`

## Objetivo

Fechar a interoperabilidade dos tokens: ler JSON nativo da ferramenta, W3C DTCG
(Tokens Studio / Figma Variables) e Style Dictionary de volta para o gerador e o
editor, validando estrutura, tamanho, profundidade, referências e ciclos — sem
executar nada, sem requisição ao servidor e sem perder o estado atual quando o
arquivo é incompatível. A importação fica disponível no **gerador** e no
**editor** (escolha aprovada pelo usuário).

## O que foi entregue

### 1. Limites e props (servidor)

- `config/colors.php`: bloco `tokens.import` (512 KiB, 5000 tokens/nós,
  profundidade 40, referências 10, diagnósticos exibidos 20) e
  `palettes.source_label_max_length` (80).
- `ColorsToolProps::tokenImportLimits()`; props `tokens` no gerador
  (`ToolProps::for`) e no editor (`PaletteController@edit`).
- `PaletteRules` valida o rótulo de origem pelo limite configurado.
- `resources/js/types/colors.ts`: `TokenImportLimits`, `tokens` nas props de
  gerador/editor, `sourceLabelMaxLength` em `PaletteToolProps`.

### 2. Exportação compatível

- JSON nativo ganhou o campo aditivo `base` (versão do formato continua 1).
- `buildFrozenScale` aceita `clippedTones` opcional e preserva a marcação.
- Testes de exportação e documentação de ida e volta atualizados.

### 3. Parser puro + fixtures

- `resources/js/tools/palette-generator/lib/tokenImport.ts`:
  detecção estrutural dos três formatos, travessia iterativa com profundidade e
  contagem, chaves proibidas, herança de `$type`, aliases com limite e ciclos,
  HEX6/HEX8 estritos, seleção determinística de grupo, papéis semânticos e
  diagnóstico com `code`/`severity`/`path`/`params`. Nada é executado além de
  `JSON.parse` via `analyzeJson`.
- `tests/Fixtures/colors/tokens/**` compartilhados por Vitest e Pest, com
  `expected/*.payload.json`.
- `tests/Unit/Colors/TokenImportFixturesTest.php` valida os payloads esperados
  contra `PaletteRules` (sem reimplementar o parser em PHP).

### 4. Rascunho e salvamento com papéis

- `PaletteDraft` ganhou `scale?` e `roles?` opcionais, validados na leitura de
  forma tolerante (campo inválido é descartado sem invalidar o rascunho).
- `PaletteSaveSource.roles?`; o `SavePaletteDialog` usa os papéis importados
  quando presentes.
- `paletteSignature()` identifica o snapshot importado; mudar base, preset ou
  extração descarta os papéis.

### 5. Workspace

- `applyImported(palette)` congela a escala **antes** de trocar a base, seta a
  aba/estado da extraída e registra a assinatura dos papéis.
- O watcher da base compara `canonicalKey`, eliminando a corrida que existia
  com `await nextTick()`.
- `restoreDraft` reconstrói a escala congelada e os papéis, revalidando a
  assinatura.
- `draft()`/`roles` expostos para a página.

### 6. Diálogo e i18n

- `resources/js/components/colors/TokenImportDialog.vue`: leitura com
  `readTextFile` (`.json`/`application/json`, tamanho antes do tipo),
  diagnóstico com erros primeiro, teto de exibição com contagem dos ocultos,
  prévia (formato, tipo, base, cores, papéis, nome) e **Aplicar** só quando não
  há erro.
- Chaves `tools.shared.token_import.*` (incluindo `applied` e os 21 erros +
  4 avisos) em en, pt-BR, pt-PT, fr e es.
- Teste de cobertura de todos os códigos do parser nos cinco idiomas.

### 7. Wiring

- `Tool.vue`: prop `tokens`, botão **Importar tokens** (sempre disponível),
  diálogo, `applyImported` + aviso, e `sourceOf` incluindo os papéis válidos do
  workspace.
- `PaletteEditor.vue`: botão **Importar tokens** só quando a paleta não está
  arquivada; papéis importados substituem a cópia editável e entram no PATCH;
  sem papéis no arquivo, os atuais são mantidos.

### 8. Docs

- `docs/colors/import-export.md`: seção “Importação validada” (formatos,
  segurança, seleção, papéis, base/metadados, limites, assinatura,
  persistência e privacidade).
- `docs/colors/export-formats.md`: seção “Ida e volta” (o que é comparado e as
  diferenças entre formatos).

## Verificação (comandos e resultados reais)

| Comando | Resultado |
|---|---|
| `npx vitest run` (9 suítes da etapa) | **9 arquivos, 137 testes passando** |
| `php artisan test --filter=TokenImportFixtures` | **11 testes, 54 asserções passando** |
| `npm run test` | **120 arquivos, 1471 testes passando** |
| `npm run types:check` | **sem erros** (só a geração do Wayfinder) |
| `npm run lint:check` | **0 erros**; avisos são preexistentes do repositório (indentação em arquivos antigos e `one-component-per-file` em stubs de teste) |
| `npm run build` | **sucesso** (avisos de tamanho de chunk e de plugins, como já ocorria) |
| `composer test` | **Pint passou, PHPStan 0 erros, Pest 1585 testes / 28338 asserções** (1 deprecação em `vendor/pestphp`) |

## Decisões registradas nesta etapa

- Importação vale para JSON nativo, DTCG e Style Dictionary; TypeScript, CSS,
  SCSS e Tailwind continuam só exportação.
- O estado só muda ao clicar em **Aplicar**; erro nunca descarta a paleta atual.
- Papéis importados valem enquanto a assinatura do snapshot bater; sem papéis
  completos, os atuais são mantidos.
- Style Dictionary não tem slot de base: usa o tom 500. JSON nativo e DTCG
  preservam base e `clippedTones`.
- Importar não salva: persistir continua sendo o botão **Salvar**.

## Pendências / próximo passo

- ADR-133 (importação validada, emenda ao ADR-096), atualização de SPEC e
  glossário e relatório final da fase pertencem à **Etapa 7**, que só começa com
  nova aprovação.
- Nenhum commit ou push foi feito; o usuário commita e abre o PR pelo fluxo
  habitual.
