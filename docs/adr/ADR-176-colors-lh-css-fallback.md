# ADR-176 — Fallback HEX/OKLCH e escopo do CSS exportado
Data: 2026-11-20
Status: Aceito

## Contexto

A ADR-155 pediu “fallback HEX sRGB primeiro e declaração OKLCH compatível
depois, sob mecanismo progressivo validado por testes”, com prefixo editável e
escopo restrito. A implementação precisava escolher o mecanismo, o tratamento
do alpha e como o texto do usuário entra no arquivo sem virar CSS livre.

## Decisão

- **Mecanismo progressivo**: `@supports (color: oklch(0 0 0))`. O bloco base
  declara HEX (`#rrggbb`) e, quando o token tem alpha real, `rgba(r, g, b, a)`
  com os componentes originais — nada é recombinado. Dentro do `@supports`,
  os mesmos seletores são redeclarados em `oklch(l% c h)` ou
  `oklch(l% c h / a)`. Navegador sem OKLCH ignora o bloco inteiro e fica com o
  fallback válido.
- **Temas**: o claro ocupa o escopo puro e `{escopo}[data-theme="light"]`; o
  escuro só `{escopo}[data-theme="dark"]`. Não há `prefers-color-scheme`
  automático nem duplicação de declaração no mesmo bloco.
- **Escopo e prefixo validados**: `:root` ou classe simples
  (`^\.[A-Za-z_][A-Za-z0-9_-]*$`, até 64) e prefixo identificador ASCII em
  kebab-case (`^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`, até 32). A validação roda no
  cliente antes de gerar; entrada inválida desabilita os downloads e mostra
  erro inline. Os tetos vêm de `config/colors.php` (`layout_systems.export`),
  documentados em `PARAMETROS.md`.
- **Comentários sanitizados**: nome do sistema e textos de aviso passam por
  `escapeCssComment` (remove `*/`, controles e quebras, colapsa espaços). Os
  seletores, `@supports` e nomes de variáveis são sempre emitidos pelo código.
- **Sem `@media`, sem seletor arbitrário, sem CSS do usuário**: o único campo
  livre é o nome da classe, que a validação reduz a um identificador.

## Consequências

- O arquivo é válido em navegadores antigos e modernos com o mesmo conteúdo
  semântico; o teste de CSS cobre ordem dos blocos, alpha e seletores.
- O usuário pode adotar `.app` (ou o próprio `:root`) sem editar o arquivo,
  mas nunca injetar regra CSS arbitrária pelo exportador.
- Mudar os tetos é configuração, não código; o card lê o limite das props do
  servidor.
