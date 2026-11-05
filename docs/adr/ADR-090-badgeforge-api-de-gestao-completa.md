# ADR-090: BadgeForge F9 — API de gestão completa

Status: Aceito
Data: 2026-11-05
Relacionados: ADR-081 (badges pessoais e PAT), ADR-083 (endpoint JSON), ADR-086 (analytics), ADR-087 (providers customizados), ADR-088 (API de gestão F8), ADR-089 (hardening)

## Contexto

A F8 entregou o contrato OpenAPI 3.1 e a primeira metade da API de gestão em
`/api/v1/badgeforge/*`: catálogo anônimo, listas de logos e badges, criação de
logo (multipart) e de badge pessoal, e exclusões. A própria ADR-088 registrou
como fora de escopo o PATCH, a rotação de credencial, a criação/edição de
endpoint e provider customizado e a API JSON de analytics.

O resultado é que o painel `/dashboard/badgeforge` faz coisas que a API não
faz: renomear, trocar o PAT de uma badge, forçar a regeneração, cadastrar um
endpoint ou provider customizado e ler o analytics. Quem automatiza (CI que
publica a badge do time, script que renomeia, painel externo de métricas)
continua obrigado a abrir o navegador.

O risco de fechar essa lacuna é duplicar regra sensível: plano pago, SSRF,
quota, sanitização e cifra de PAT vivem em actions e FormRequests. A F9 existe
para espelhar o painel **sem** uma segunda implementação.

## Decisão

### 1. Espelho completo do painel, sem presets

A API cobre os recursos que têm URL pública ou uso fora do navegador:

| Método | Path | Ability | Corpo |
| --- | --- | --- | --- |
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}` | `badgeforge:write` | `name` |
| PATCH | `/api/v1/badgeforge/badges/{badgeAsset}/credential` | `badgeforge:write` | `credential`, `credential_username?` |
| POST | `/api/v1/badgeforge/badges/{badgeAsset}/refresh` | `badgeforge:write` | — |
| PATCH | `/api/v1/badgeforge/logos/{badgeCustomLogo}` | `badgeforge:write` | `name` |
| GET | `/api/v1/badgeforge/endpoints` | `badgeforge:read` | — |
| POST | `/api/v1/badgeforge/endpoints` | `badgeforge:write` | `name`, `url` |
| PATCH | `/api/v1/badgeforge/endpoints/{badgeEndpoint}` | `badgeforge:write` | `name` |
| DELETE | `/api/v1/badgeforge/endpoints/{badgeEndpoint}` | `badgeforge:write` | — |
| GET | `/api/v1/badgeforge/custom-providers` | `badgeforge:read` | — |
| POST | `/api/v1/badgeforge/custom-providers` | `badgeforge:write` + pago | template completo |
| PATCH | `/api/v1/badgeforge/custom-providers/{badgeCustomProvider}` | `badgeforge:write` + pago | `name`, `metrics` |
| DELETE | `/api/v1/badgeforge/custom-providers/{badgeCustomProvider}` | `badgeforge:write` | — |
| GET | `/api/v1/badgeforge/analytics?window=` | `badgeforge:read` | — |

Presets ficam fora: são snapshot do builder, sem URL pública, cache ou
analytics, e a única razão para uma API seria replicar o formulário.

Sem `GET` por ID em nenhum recurso: as listas paginadas e o recurso devolvido
no POST/PATCH bastam, e a superfície fica enxuta.

### 2. PATCH espelha a web, não é JSON Merge Patch

Cada PATCH recebe a representação completa dos campos mutáveis e reusa o
FormRequest da tela (`UpdateBadgeAssetRequest`, `UpdateCustomLogoRequest`,
`UpdateBadgeEndpointRequest`, `UpdateBadgeCustomProviderRequest`). Badge, logo
e endpoint aceitam só `name` (obrigatório; o mesmo nome é aceito). Custom
provider aceita `name` + `metrics` completo, com `url_template` `prohibited` e o
conjunto de slugs fixo; `metrics_version` sobe e o probe roda de novo.

O controller declara o model com o **nome exato** do parâmetro da rota
(`$badgeAsset`, `$badgeCustomLogo`, `$badgeEndpoint`, `$badgeCustomProvider`)
para o `ignore()` da regra `unique` receber o id e um PATCH com o nome
inalterado não virar 422.

### 3. Credencial e refresh reusam as actions

`PATCH .../credential` chama `SetBadgeAssetCredential` (write-only, cifrada,
apaga o SVG/PNG em cache, só provider que aceita privado e plano pago; não há
"remover" — revogar é excluir). A resposta leva `credentialSet` e
`credentialUsername`, nunca a credencial.

`POST .../refresh` chama `RefreshBadgeAsset`, que persiste no próprio
`$badgeAsset`; o controller lê o model vinculado depois da ação e devolve
`url`, `pngUrl` e `generatedAt`. O refresh empilha `api-write` (30/min por
token) com `badgeforge-refresh` (10/min por conta), e o limiter passou a
responder o envelope `rate_limited` quando o request é `api/*` — a web mantém
o toast/JSON `{message}` atual.

### 4. Endpoints: Free cadastra, origem nunca volta

O comportamento é o da tela: Free pode criar, sem limite de contagem por
conta; a URL de origem é imutável e o probe/SSRF continuam nas actions
(`CreateBadgeEndpoint`, `UpdateBadgeEndpoint`, `DeleteBadgeEndpoint`). A
resposta é `id`, `name`, `svgUrl`, `pngUrl` e `createdAt` — nunca a URL de
origem.

### 5. Custom providers: pago para criar/editar, excluir sobrevive ao downgrade

POST e PATCH ficam num grupo aninhado `Route::middleware('badgeforge.paid')`
dentro do grupo de escrita; o DELETE fica fora, como na web. A leitura fica no
Free. `url_template`, `path_params` e `example_path` são imutáveis; o conjunto
de slugs é fixo no PATCH; o re-probe e o bump de `metrics_version` são da
action. A resposta leva `definitions` com `slug`, `label`, `pointer` e
`formatter`; `url_template` nunca sai.

O middleware de plano passou a lançar `AccessDeniedHttpException` em vez de
`abort(403)`: no Laravel 13 o `abort` genérico não vira o envelope `forbidden`.

### 6. Analytics: JSON only, sem exportUrl

`GET /analytics?window=` chama `OwnerAnalytics::for()` e devolve o mesmo shape
do painel sob `data`, **sem `exportUrl`**: o CSV é uma rota web
session-based e um link ali seria uma porta que o token não abre. Free mantém
o recorte de `free_window_days` (30); pago aceita `7|30|90`; janela inválida
cai em 30, nunca 422. `QueryException` é tratada no controller (o renderer
global só cobre `api/badges/*` e `b/*`) e vira **503 `badge_unavailable`** com
`Retry-After: 60` e `Cache-Control: no-store`.

### 7. Abilities e limites reusados

Nenhuma ability nova: `badgeforge:read` + `api-read` nas leituras,
`badgeforge:write` + `api-write` nas escritas. Um token novo continua só com
`ai:query`, e a API de IA não ganha escrita em badge por herança de grupo.

### 8. Erros

Envelope `ApiError` (`data`/`meta`/`links` no sucesso): dono errado → **404**
`not_found`; validação/plano de badge → **422** `validation_error`; plano pago
de custom provider → **403** `forbidden`; throttle → **429** `rate_limited`
com headers.

### 9. Contrato 1.9.0, aditivo

`docs/badgeforge/openapi.yaml` sobe para **1.9.0** com as operações novas,
`NameInput`, `AnalyticsWindow`, `OwnerAnalytics`, `BadgeUnavailable` e os
recursos de endpoint/provider customizado. Nenhum path, campo ou código da
1.8.0 muda, então um cliente da F8 continua válido. A página pública e as
traduções não mudam: a API é documentada em `API.md`/OpenAPI, não na página
Inertia.

## Consequências

### Positivas

- O painel inteiro passa a ser automatizável sem uma segunda implementação de
  plano, SSRF, quota, sanitização ou cifra.
- As regras continuam em um só lugar (actions/FormRequests); a API só troca
  `back()` por `data`/`201`.
- Abilities e limites já existentes bastam; nenhum token existente muda de
  escopo.

### Negativas / Trade-offs

- Mais superfície sensível exposta por token (PAT, URL de origem, custom
  provider). A mitigação é a mesma da tela: nunca ecoar segredo, 404 de dono e
  throttle curto na escrita.
- Cada mudança de contrato da tela arrasta a API (preço de não duplicar
  regra).
- Sem GET por ID: um cliente que só tem o id de um recurso precisa varrer a
  lista (teto de 100 por página) ou guardar o corpo devolvido pelo POST/PATCH.
- CSV continua fora da API: automação de export precisa da sessão da web.

## Alternativas descartadas

- **Criar endpoints dedicados por recurso com regras próprias**: duplicaria
  SSRF, plano e cifra; foi o motivo de a F9 reusar as actions.
- **JSON Merge Patch (RFC 7396)**: divergiria da tela (que envia a
  representação completa) e complicaria o `unique` do nome; o espelho é mais
  simples de testar e de manter.
- **GET por ID em cada recurso**: aumenta a superfície sem necessidade; a lista
  paginada e o corpo do POST/PATCH já entregam o recurso.
- **Expor `exportUrl` no analytics**: o link não autentica por token e daria
  um endereço quebrado ao cliente.
- **Devolver `url_template` do provider customizado**: a origem é o dado mais
  sensível do recurso; permanece no servidor, como na web.
- **Bloquear DELETE de custom provider no Free**: contraria a tela e prendê-o
  no plano após downgrade.
- **Contrato 2.0.0**: nada foi removido nem alterado; major sem quebra seria
  ruído.
