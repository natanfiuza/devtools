# ADR-155 — CSS exportado do sistema de layout
Data: 2026-11-09
Status: Aceito

## Contexto

Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão

Prefixo lh editável; :root/classe simples validada; data-theme light/dark
controlado; fallback.

## Consequências

Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES.
Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar
na F1.

## Implementação (F5)

- **Seletores emitidos pelo código** (`export/options.ts` +
  `export/css.ts`): escopo `:root` (default) ou `.classe` simples; o claro é o
  default e também `{escopo}[data-theme="light"]`; o escuro só responde a
  `{escopo}[data-theme="dark"]`. Sem `@media` automática e sem seletor livre.
- **Fallback progressivo**: o bloco base declara HEX (ou `rgba()` quando há
  alpha real, sem recompor) e, dentro de `@supports (color: oklch(0 0 0))`, o
  mesmo escopo é redeclarado em `oklch(...)`. Nenhuma variável fica com valor
  de formato não suportado fora do fallback.
- **Validação**: prefixo de identificador ASCII
  (`^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`) limitado a 32 caracteres e escopo
  limitado a 64; entrada inválida mostra erro inline e desabilita os downloads.
  Os tetos vêm de `config/colors.php` (`layout_systems.export`), não do código.
- **Comentários**: cabeçalho, metadados, tema padrão e avisos são comentários
  CSS sanitizados (sem `*/`, controle ou quebra de linha); o texto livre do
  usuário (nome do sistema) só entra no cabeçalho e escapado. Os trechos
  técnicos nunca vêm de `lang/`.
- **Paridade com o quadro**: os valores saem do snapshot exibido, na ordem do
  catálogo, com as versões no comentário de metadados (ADRs 168 e 175).
