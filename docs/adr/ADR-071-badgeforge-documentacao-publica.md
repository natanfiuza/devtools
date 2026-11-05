# ADR-071: Documentação pública
Status: Aceito

## Decisão
Documentação e OpenAPI públicos. Gerados pelo Claude Code a partir das definições.

## Consequências
Facilita adoção e integração.

## Emenda da F4 (2026-11-03)

A pagina `/{locale}/docs/badgeforge` foi entregue (ADR-082), ao lado de
`/{locale}/api-docs`: catalogo de providers gerado do banco, snippets
copiáveis, badge pessoal/PAT, cache/stale e seguranca, nos cinco idiomas, com
meta de servidor, sitemap e link no rodape. A OpenAPI subiu para v1.4.0 e
documenta provider e badge pessoal como entregues.

## Emenda da F8 (2026-11-05)

A OpenAPI do BadgeForge deixa de ser "gerada a partir das definições": passa a
ser `docs/badgeforge/openapi.yaml`, YAML 3.1.0 **autoral e versionado**, com
`components`, `tags`, `servers` e operações nomeadas. O arquivo é a fonte
única; `OPENAPI.md` vira ponteiro semântico (cache, SSRF, privacidade,
política de compatibilidade) e não replica o YAML.

A spec é servida em `GET /openapi/badges.yaml` (`application/yaml`,
`Cache-Control: public, max-age=86400`), ao lado do `/openapi.yaml` existente.
O `servers[0].url` usa o marcador `%APP_URL%`, resolvido pelo
`OpenApiController` a partir de `config('app.url')` — o repositório não fixa
domínio. A versão do contrato é `1.8.0` e a política é aditiva: remoção ou
mudança de forma só em major.

`tests/Feature/BadgeForge/OpenApiContractTest.php` impede o drift: YAML
parseável, `$ref` sem dangling, versão esperada e paridade bidirecional entre
paths documentados e rotas registradas, com os nomes reais dos parâmetros de
path.
