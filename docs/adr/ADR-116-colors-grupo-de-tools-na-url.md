# ADR-116 - Grupo de tools na URL
Status: Aceito
Data: 2026-11-07

## Contexto
A especificação do Colors pede `/tools/colors/html-color-picker`, mas o site serve toda tool em `/{locale}/tools/{slug}` e o registro de tools só enxerga um nível de diretório.

## Decisão
O manifest ganha o campo opcional `group`. Tool com `group` é servida em `/{locale}/tools/<group>/<slug>` pela rota `tools.show.grouped`; tool sem `group` continua em `/{locale}/tools/<slug>` pela rota `tools.show`. A URL agrupada é a única canônica: a URL plana de uma tool agrupada responde 404. Grupo é namespace de URL, independente de `category`; não há landing de grupo na Fase 1.

## Consequências
Cards, busca e sitemap passam a resolver o caminho por `toolPath` (front) e `Tools::routeFor` (back). Seo, Sitemap e StructuredData aceitam os dois nomes de rota. Nenhuma tool existente muda de URL.
