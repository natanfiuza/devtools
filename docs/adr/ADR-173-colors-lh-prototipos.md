# ADR-173 — Protótipos do gerador no workspace do Layout Harmonizer
Data: 2026-11-19
Status: Aceito

## Contexto

O gerador de paleta já tinha um painel de protótipo (site e mobile, com SVG
renderizado no navegador) e um editor de papéis. O Layout Harmonizer tem um
quadro semântico com papéis próprios — `primary`, `secondary`, `accent`,
`background`, `surface`, `on-background`, `on-surface-variant`, `hairline` — e
queria mostrar o quadro em contexto sem duplicar o renderizador.

## Decisão

- O diretório `resources/js/tools/palette-generator/prototype/` foi promovido
  para `resources/js/lib/prototype/` como biblioteca compartilhada, com
  movimentação de arquivos no sistema de arquivos (sem `git add`/`git mv`) e
  atualização de todos os imports (`prototypeOg.ts`, `PaletteEditor.vue`,
  `image/buildImage.ts`, `image/exportImage.ts` e testes). Nenhuma mudança de
  comportamento no gerador de paleta.
- O `sample` do `PrototypePanel.vue` foi extraído para
  `lib/prototype/copy.ts` (`prototypeSampleCopy(t)`), reutilizado pelo painel
  da paleta e pelo Layout Harmonizer, com as strings em
  `colors.prototype.sample.*`.
- `lib/layoutRoleMap.ts` fixa o mapa de papéis do quadro para o protótipo:
  `primary→primary`, `secondary→secondary`, `accent→accent`,
  `background→background`, `surface→surface`, `on-background→text`,
  `on-surface-variant→text_muted`, `hairline→border`.
- `LayoutSystemPrototypePanel.vue` oferece seletor site/mobile (“site”/“mobile”
  existentes no renderizador) e tema claro/escuro, chama `buildPrototype` com
  as cores do snapshot **exibido** (live ou compatível) e mostra
  `PrototypePreview`. O `aria-label`/`<title>` do SVG vêm da label traduzida
  (`prototype.preview_label`).
- O painel não tem `RoleEditor`, não exporta SVG/PNG e aparece apenas no
  workspace autenticado; é preview-só, então continua visível com o sistema
  arquivado.

## Consequências

- Um renderizador de protótipo só, testado uma vez, para as duas ferramentas.
- O mapa de papéis é fixo e coberto por teste: mudar o catálogo do quadro exige
  revisar o mapa.
- Exportação de protótipo e editor de papéis continuam fora do Layout
  Harmonizer nesta fase.
