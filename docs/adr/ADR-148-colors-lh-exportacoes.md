# ADR-148 — Exportações do sistema de layout
Data: 2026-11-09
Status: Aceito

## Contexto

Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão

MD, CSS HEX/OKLCH fallback, ZIP para autenticados sem salvar; anônimos não
exportam.

## Consequências

Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES.
Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar
na F1.

## Implementação (F5)

- **Superfície**: card de exportação no corpo do painel (`LayoutExportPanel.vue`),
  abaixo do quadro, no tool e no editor; o arquivado monta o card direto com o
  snapshot congelado. A página pública (`colors/s/{slug}`) não exporta para
  ninguém (ADR-174).
- **Permissão de produto**: autenticado exporta com ou sem salvar; anônimo vê
  o card bloqueado com CTA que passa pelo intent
  `dashboard/colors/layout-systems/create?export=1` e volta ao tool já logado,
  sem abrir o modal de salvar. `save=1` continua intocado.
- **Fonte única**: os três arquivos saem de `LayoutSystemStateV1.snapshot` (o
  retrato exibido). Nenhum exportador regenera cores, chama IA ou faz
  requisição ao servidor; um valor trocado no snapshot aparece no arquivo
  (teste de consistência).
- **Formatos**: `buildLayoutMarkdown` (ADR-177), `buildLayoutCss` (ADR-155) e
  `buildLayoutZip` com `fflate` (ADR-175). ZIP com `design-instructions.md` e
  `colors.css` fixos; avulsos `design-instructions.{locale}.md` e `colors.css`;
  pacote `{slug}-layout.zip`.
- **Idioma do documento**: um seletor (default = locale ativo) alimentado pelo
  subtree que o servidor envia para os cinco idiomas
  (`LayoutSystemExportMessages::all()`), porque o cliente só recebe o locale
  ativo.
- **Avisos**: falha de contraste, bloqueio, valor manual, ajuste aceito, token
  clipado e modo compatível/readonly aparecem sempre nos arquivos e nunca
  impedem a exportação.
- **Sem endpoint de exportação**: nada mudou em rotas, migrations ou jobs; o
  card é 100% navegador.
