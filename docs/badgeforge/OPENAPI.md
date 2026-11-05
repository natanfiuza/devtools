# OpenAPI do BadgeForge

A especificação de máquina é [`openapi.yaml`](openapi.yaml): OpenAPI 3.1.0,
versão de contrato **1.8.0**, escrita à mão e versionada no repositório. Este
arquivo não reproduz o YAML — explica a semântica que o YAML não expressa bem
e diz onde consultar o resto.

## Onde está

- Arquivo: [`docs/badgeforge/openapi.yaml`](openapi.yaml).
- Servida em: `GET /openapi/badges.yaml`, com
  `Content-Type: application/yaml` e `Cache-Control: public, max-age=86400`.
- O campo `servers[0].url` é o marcador `%APP_URL%`; o
  `OpenApiController` o substitui por `config('app.url')` ao servir. O arquivo
  no repositório não fixa domínio.
- Cobertura: as rotas de imagem `/api/badges/**`, `/b/{username}/{badgeAsset}`
  e a API de gestão `/api/v1/badgeforge/*`. Rotas Inertia/HTML ficam fora.
- Documentação humana: `/{locale}/docs/badgeforge`; contrato em prosa:
  [`API.md`](API.md).

## Política de compatibilidade

`info.version` descreve o contrato, não o arquivo. Mudanças são aditivas
dentro da mesma minor (novo path, novo parâmetro opcional, novo header, novo
código de erro); remoção ou alteração de forma só em major. O cliente programa
pelo `code` do envelope `ApiError`, nunca pela `message`.

## Semântica que o YAML não carrega

- **Decodificação do path**: em `label` e `message`, `_` vira espaço, `__` vira
  `_` e `-` é literal. Barra viaja duplo-codificada (`%252F`); um `%2F`
  digitado pelo usuário colide e vira `/` — comportamento documentado, não
  corrigível sem mudar a URL.
- **Cache em camadas**: o `ETag` é o sha256 do corpo; `If-None-Match` responde
  304 sem corpo. `Cache-Control` varia por rota e por `cacheSeconds`; PNG é
  cacheado em disco pelo sha256 do SVG.
- **Falha de origem nunca vira 500**: provider, endpoint e provider custom
  servem STALE (`X-Badge-Cache: STALE` + `Warning: 110`) ou fallback 200. O
  endpoint da F5 responde 422 `unsafe_url` só quando a URL guardada deixa de
  passar no guard de SSRF.
- **SSRF**: toda origem externa é HTTPS/443, sem userinfo, sem redirect e com
  todos os IPs públicos; a conexão é pinada por `CURLOPT_RESOLVE` e falha
  fechada sem curl.
- **Privacidade**: a URL de origem de endpoint/provider custom nunca aparece na
  URL pública, em props, CSV, log ou auditoria; o ULID é a única identidade.
- **Analytics**: imagens com dono (pessoal, endpoint, custom) gravam uma linha
  por acesso 200/304, best-effort; `ip_hash`/`user_agent_hash` são HMAC-SHA256
  com `APP_KEY`.

## Garantias automatizadas

`tests/Feature/BadgeForge/OpenApiContractTest.php` valida que o YAML parseia,
que todo `$ref` resolve, que a versão é a esperada e que existe paridade
bidirecional com as rotas registradas — inclusive os nomes reais dos
parâmetros de path (`badgeAsset`, `badgeEndpoint`, `customProvider`, `path`).
