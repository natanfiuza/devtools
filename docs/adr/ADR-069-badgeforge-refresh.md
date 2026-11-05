# ADR-069: Refresh manual
Status: Aceito

## Decisão
?refresh=1 permite invalidar cache apenas em badges próprias.

## Consequências
Evita abuso; exige verificação de propriedade.

## Emenda da F4 (2026-11-03)

A regra ganhou duas formas, porque uma imagem stateless nao tem sessao:
`?refresh=1` continua valendo para badge propria servida com sessao, mas a
badge pessoal publica (`/b/...`) o **ignora**; atualizar e
`POST /badgeforge/badges/{id}/refresh`, autenticado, do dono, com
`throttle:badgeforge-refresh`, seguido de `?v=<timestamp>` local na UI
(ADR-081). Na rota de provider publica o `?refresh=1` tambem e ignorado: o TTL
e do servidor e o fetch externo nao pode ser disparado por qualquer leitor
(ADR-080).
