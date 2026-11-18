# ADR-158 — Contraste
Data: 2026-11-09
Status: Aceito

## Contexto
Sistema de cores integrado ao Colors, entrevista consolidada.

## Decisão
AA padrão, AAA opcional; meta não altera automaticamente; avisos não bloqueiam exportar.

## Consequências
Refletir no motor, nas permissões e nos testes conforme SPEC e TESTES. Parâmetros não aprovados não devem ser escolhidos silenciosamente; apresentar na F1.

## Emenda da F3 (2026-11-18, ADR-167)

- AA (4,5) e AAA (7) passam a ser metas **selecionáveis para as sugestões** de
  correção. A meta escolhida nunca altera nenhuma cor do quadro: ela só filtra
  a sugestão e o aceite continua sendo ação explícita (ADR-167).
- A sugestão move apenas a luminosidade do foreground e precisa atingir a meta
  em **todos** os fundos daquele token no tema. Como consequência, **AA pode
  ser inviável** para tokens medidos contra base, hover e active quando os três
  fundos cruzam a faixa média: o caso sai como aviso explícito, sem aceite, e
  não bloqueia o trabalho.
- Os avisos de contraste continuam informativos e não impedem exportar; nada
  é aplicado automaticamente.
