# Plano de execução — Layout Harmonizer F5 (exportações Markdown, CSS e ZIP)

Data: 2026-11-20
Branch: `feature/layout-hamonizer-fase-4` (grafia da branch como está no repositório)
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase5-markdown_css_zip.md` + `EXPORTACOES.md`
Plano completo: `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261120_feature_layout-hamonizer-fase-4_f5_exportacoes.md`
Survey: `docs/survey/20261120_layout_harmonizer_f5_surveyfacts.md`

## Objetivo

Gerar, 100% no navegador e a partir do snapshot exibido, três artefatos de exportação:
Markdown de instruções para IA, CSS com fallback HEX/OKLCH e ZIP com os dois. Autenticados
exportam sem salvar; anônimos veem o card bloqueado com CTA; a página pública não exporta.
Sem servidor de exportação, sem chamada de IA e sem regeneração de cores. Ao fim: gates
reais, relatório e parada para revisão, sem F6 e sem commit/push.

## Decisões que dirigem o plano (grill de 2026-11-20)

| Tema | Decisão |
| --- | --- |
| Biblioteca ZIP | `fflate` 0.8.3 (MIT) como dependência direta |
| Anônimo | Card bloqueado com CTA de login via intent `export=1` |
| Página pública | Sem exportação para ninguém |
| Fallback CSS | HEX/rgb + `@supports (color: oklch(0 0 0))` |
| Estados | Exporta live/compatível/readonly/arquivado; bloqueia só `pendingRegeneration` |
| Temas | Claro no escopo base + `data-theme="light"`/`"dark"` |
| Origem no MD | Só crédito congelado de paleta pública |
| Nomes | Fixos dentro do ZIP; avulsos com locale; ZIP `{slug}-layout.zip` |
| UI | Card abaixo do quadro, só download |
| Idioma | Um seletor (default atual); técnicos em inglês |
| Nome não salvo | Nome sugerido do salvamento; editor usa o salvo |
| Validação | Prefixo ≤32, escopo `:root` ou classe simples ≤64 |

## Arquitetura

```text
export/
├─ types.ts       LayoutExportInput / LayoutExportFile
├─ options.ts     defaults (lh, :root), validação de prefixo/escopo
├─ filename.ts    sanitização de nome, nomes de MD e ZIP
├─ messages.ts    lookup no subtree, interpolação, escapes MD/CSS
├─ css.ts         buildLayoutCss (fallback + @supports + avisos)
├─ markdown.ts    buildLayoutMarkdown (10 seções, avisos por snapshot.target)
└─ zip.ts         buildLayoutZip (fflate, mtime/level fixos)

LayoutExportPanel.vue  (card; anônimo bloqueado)
├─ Tool.vue → LayoutHarmonizerPanel.vue (via exportMessages/exportLimits)
└─ LayoutSystemEditor.vue (inclui ramo arquivado/readonly)

Backend: LayoutSystemExportMessages::all() ─ config/colors.php export limits
         ─ ToolProps/editor props ─ intent export=1
```

## Etapas

| Etapa | Conteúdo | Gate ao fim |
| --- | --- | --- |
| 1 | `npm install fflate@^0.8.3`; `export/{types,options,filename,messages}.ts` | Vitest de options/mensagens |
| 2 | `export/css.ts` | Vitest de CSS |
| 3 | `export/markdown.ts` | Vitest de Markdown |
| 4 | `export/zip.ts` | Vitest de ZIP |
| 5 | Backend: `LayoutSystemExportMessages`, ToolProps, editor, intent `export=1` | Pest e `composer test` |
| 6 | `LayoutExportPanel.vue` + integração tool/editor (incl. arquivado) | Vitest de painel/editor/pública |
| 7 | i18n nos 5 idiomas e paridade | `TranslationsParityTest`, `layout-harmonizer-i18n` |
| 8 | ADRs 148/155 + 175/176/177, docs, gates completos e relatório | todos os gates |

## Gates

```bash
composer test
npm run test
npm run types:check
npm run format:check
npm run lint:check
npm run build
```

## Parada

Relatório em `docs/claude-code/reports/feature-layout-hamonizer-fase-4/20261120_feature-layout-hamonizer-fase-4_f5_exportacoes.md`.
Ao fim: **parar para revisão**, sem commit/push e sem iniciar a F6.
