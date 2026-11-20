# ADR-175 — Exportação local do sistema de layout
Data: 2026-11-20
Status: Aceito

## Contexto

A ADR-148 fixou que MD, CSS e ZIP existem para autenticados, mas a F5 precisava
decidir onde a geração acontece, qual é a fonte dos valores, como o visitante
anônimo é tratado e o que o ZIP carrega. O requisito da ferramenta é que nada
saia do navegador: sem endpoint de exportação, sem chamada de IA e sem
regenerar o quadro no caminho.

## Decisão

- **Geração 100% no cliente** em `resources/js/tools/layout-harmonizer/export/`:
  `types.ts` (contrato), `options.ts` (prefixo/escopo), `filename.ts` (nomes),
  `messages.ts` (lookup e escapes), `css.ts`, `markdown.ts` e `zip.ts`. Nenhuma
  rota, action, job ou migration nova.
- **Fonte única**: `LayoutSystemStateV1.snapshot` já exibido, com `inputs`,
  versões e avisos. O exportador não executa o motor; um valor sentinela
  trocado direto no snapshot chega aos arquivos (teste de consistência).
- **ZIP com `fflate`** (MIT, `^0.8.3`, declarada como dependência direta): dois
  arquivos de nomes fixos, `design-instructions.md` e `colors.css`, UTF-8,
  `mtime` fixo e nível de compressão fixo. Determinismo é testado comparando
  duas gerações no mesmo processo (não com bytes dourados, que dependem de
  fuso na data DOS). O nome do pacote é `{slug}-layout.zip`, sanitizado como
  nos exportadores de paleta (sem diacríticos, hífens, máximo 32, fallback
  `layout-harmonizer`).
- **Permissões de produto**: autenticado exporta sem salvar; anônimo vê o card
  bloqueado com CTA de login (`create?export=1`), que volta ao tool liberado
  sem abrir o modal de salvar. A página pública não exporta para ninguém. O
  gate é de produto, não DRM: o card não promete que as cores não possam ser
  inspecionadas no DevTools.
- **Idioma do documento**: um seletor com default no locale ativo; o servidor
  envia o subtree necessário de `tools.layout-harmonizer` nos cinco idiomas
  (`LayoutSystemExportMessages::all()`), porque o cliente só recebe o locale
  ativo. O documento usa o subtree escolhido, não o vue-i18n da tela.
- **Estados**: exporta live, compatível, readonly e arquivado com as versões
  gravadas; só `pendingRegeneration` bloqueia, com o mesmo aviso do
  salvamento. Falha de contraste nunca bloqueia.
- **Sem texto livre de origem privada**: o Markdown só cita a paleta de origem
  quando o crédito congelado era público (ADR-172), nunca a paleta ao vivo.

## Consequências

- O exportador é puro, testável sem navegador e reutilizado pelo tool, pelo
  editor e pelo card do arquivado.
- O payload de tradução é enxuto (só o subtree usado) e o teste de paridade
  garante as mesmas folhas nos cinco idiomas.
- Regenerar o quadro remonta o painel e devolve idioma/prefixo ao default; é
  uma ação explícita e documentada, não um reset silencioso.
