# Survey — Layout Harmonizer F5 (exportações Markdown, CSS e ZIP)

Data: 2026-11-20
Branch: `feature/layout-hamonizer-fase-4` (grafia do repositório)
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase5-markdown_css_zip.md` + `EXPORTACOES.md`
ADRs locais: `ADR-LH-004` (exportações) e `ADR-LH-011` (CSS)

## Contexto da tarefa

F1–F4 entregues: motor puro, quadro semântico, edição manual/sugestões e persistência
(`LayoutSystem` + editor + página pública). A F5 fecha o contrato de exportação: a partir do
snapshot **exibido**, gerar localmente (sem servidor, sem IA, sem regenerar cores) um Markdown
de instruções para IA, um CSS com fallback HEX/OKLCH e um ZIP com os dois.

Usuários autenticados (Free e pagos) exportam sem precisar salvar; anônimos veem o card bloqueado
com CTA de login e não exportam nada; a página pública não exporta para ninguém.

## Decisões das rodadas de grill

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Biblioteca ZIP | `fflate` 0.8.3 (MIT), dependência direta declarada; instalação autorizada pelo usuário |
| 2 | Anônimo | Card visível e bloqueado com CTA de login (não escondido); intent leva `export=1` |
| 3 | Página pública | Nenhuma exportação; continua leitura + duplicação |
| 4 | Fallback CSS | Base HEX/rgb e bloco `@supports (color: oklch(0 0 0))` redeclarando em OKLCH |
| 5 | Estados exportáveis | live, compatível, readonly e arquivado exportam o snapshot exibido com versões e avisos; bloqueio só em `pendingRegeneration` |
| 6 | Temas | Claro no escopo base (default sem atributo) + `[data-theme="light"]` e `[data-theme="dark"]` |
| 7 | Origem no MD | Só crédito congelado de paleta pública, em texto, sem link nem dados privados |
| 8 | Nomes de arquivo | Dentro do ZIP sempre `design-instructions.md` e `colors.css`; avulsos `design-instructions.{locale}.md` e `colors.css`; ZIP `{slug}-layout.zip` |
| 9 | UI | Card abaixo do quadro (padrão do `ExportPanel` das paletas), apenas download |
| 10 | Idioma do documento | Um seletor (default = idioma atual) para prosa/comentários; nomes técnicos em inglês |
| 11 | Nome não salvo | Nome sugerido de `suggestLayoutSystemName`; no editor usa o nome salvo |
| 12 | Validação | Prefixo `^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$` ≤32; escopo `:root` ou `^\.[A-Za-z_][A-Za-z0-9_-]*$` ≤64 (CONTRATO-TECNICO) |

## Fatos levantados (código)

- `resources/js/lib/i18n.ts`: o cliente recebe **somente o locale ativo** (`applyTranslations`);
  gerar MD/CSS em qualquer dos 5 idiomas exige o servidor enviar um subtree de traduções por
  locale → `LayoutSystemExportMessages::all()`.
- `resources/js/lib/download.ts`: `downloadText(filename, text, type)` e `downloadBlob(filename, blob)`
  já existem e são testados (`download.test.ts`); reuso direto, sem novo helper.
- `resources/js/tools/palette-generator/lib/exporters.ts` e `components/ExportPanel.vue`: padrão de
  arquivos de exportação (`{format, filename, mime, contents}`), abas, sanitização de nome
  (NFD + regex, máx. 32) e testes linha a linha — mesmo desenho para o card.
- Snapshot (`LayoutSystemStateV1.snapshot`): `target:'aa'|'aaa'`, `seed{original,effective,background}`,
  `strategy`, `secondarySource`, `themes[{theme,tokens,checks}]`, `overrides`, `locks`,
  `acceptedAdjustments`, `diagnostics`. `diagnostics` é **só AA** — avisos do alvo AAA precisam
  filtrar `themes[].checks` por `snapshot.target` (`aaNormal`/`aaaNormal`).
- `formatRatio(ratio, locale)` em `lib/boardView.ts` e `formatHex`/`formatColor` em `lib/color/format.ts`.
- `suggestLayoutSystemName` (nome sugerido) e `strategyMessageKey` reutilizáveis.
- `LayoutHarmonizerPanel.vue`: `currentState()`, `suggestedName`, `controller.mode`,
  `controller.pendingRegeneration`; o `Tool.vue` remonta o painel com `:key` na regeneração
  (estado de UI do card volta ao default — aceito e documentado).
- `LayoutSystemEditor.vue`: ramo arquivado não monta o painel; o card de exportação monta direto
  com `system.state`, `system.name` e `system.credit` (exportar é leitura).
- `LayoutSystemIntentController::create()` fixa `save=1` e preserva `palette`; passa a aceitar
  `export=1` (sem `save=1`) para o CTA do anônimo.
- `LayoutSystemToolProps::for()` entrega `authenticated`, `limits`, `tags`, `origin`,
  `currentVersions`; editor passa `system`, `tags`, `limits`, `form`, `currentVersions`.
- `fflate` 0.8.3: `zipSync`/`unzipSync`/`strToU8`/`strFromU8`; sem `mtime` explícito usa
  `Date.now()` — timestamp fixo obrigatório para determinismo (DOS date depende de fuso; testes
  comparam duas gerações no mesmo processo, não golden bytes).
- ADRs livres: 175, 176, 177 (F4 terminou em 174); locais a promover: `ADR-LH-004` → 148,
  `ADR-LH-011` → 155.

## Fora de escopo

- Endpoint/rota de exportação no servidor; chamada de IA; regeneração; alteração de decisões F1–F4;
  exportação na página pública; dependências além de `fflate`.
