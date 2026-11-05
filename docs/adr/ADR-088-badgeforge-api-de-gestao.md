# ADR-088: BadgeForge F8 — API pública de gestão

Status: Aceito
Data: 2026-11-05
Relacionados: ADR-050 (API pública e tokens), ADR-068 (acesso a providers), ADR-078 (logos personalizados), ADR-079 (presets e gestão), ADR-081 (badges pessoais), ADR-087 (providers customizados)

## Contexto

O `docs/badgeforge/API.md` prometia desde a F3 uma API de gestão em
`/api/badgeforge/*` e ela nunca existiu: logos, presets, badges pessoais,
endpoints e providers customizados só têm superfície web/Inertia. Quem quer
automatizar (CI que publica logo, script que cria a badge do time) precisa hoje
abrir o painel.

A F8 do SPEC §16 pede "OpenAPI + documentação + hardening". A API de gestão
entra nela porque é a lacuna que a documentação prometia e porque o contrato
OpenAPI só fica honesto quando a superfície existe. A restrição é não duplicar
regra: plano pago, username, quota de logo, PAT cifrado e sanitização de SVG
já vivem em actions/requests, e uma segunda implementação divergiria.

## Decisão

### 1. `/api/v1/badgeforge/*`, fora do grupo `v1/` do AI SQL Builder

A API de gestão fica em `api/v1/badgeforge` e **não** herda o grupo `v1/` da
API de IA: um token com `ai:query` não pode ganhar escrita em badges por
estar no mesmo `Route::prefix`. Os grupos de route têm middlewares próprios.

| Método | Path | Auth | Throttle |
|---|---|---|---|
| GET | `/api/v1/badgeforge/providers` | anônimo | `api-read` (IP) |
| GET | `/api/v1/badgeforge/styles` | anônimo | `api-read` (IP) |
| GET | `/api/v1/badgeforge/logos` | `badgeforge:read` | `api-read` (token) |
| POST | `/api/v1/badgeforge/logos` | `badgeforge:write` | `api-write` |
| DELETE | `/api/v1/badgeforge/logos/{ulid}` | `badgeforge:write` | `api-write` |
| GET | `/api/v1/badgeforge/badges` | `badgeforge:read` | `api-read` |
| POST | `/api/v1/badgeforge/badges` | `badgeforge:write` | `api-write` |
| DELETE | `/api/v1/badgeforge/badges/{ulid}` | `badgeforge:write` | `api-write` |

O catálogo é anônimo porque o builder do navegador precisa dele antes de
qualquer token; a resposta leva `Cache-Control: public, max-age=<catalog_cache_seconds>`
e `ETag` do corpo, revalidando com `304`.

### 2. Duas abilities novas, ortogonais ao `ai:query`

`badgeforge:read` cobre as duas listagens; `badgeforge:write` cobre
POST/DELETE. Nenhuma das duas é concedida por herança do `ai:query`, e o
`ai:query` continua sendo o **default** de um token novo em
`/settings/api-tokens` (nenhum token existente muda de escopo). A tela de
tokens passa a listar as três com apenas `ai:query` marcado.

### 3. A API delega às actions; não reimplementa regra

- `POST /badges` chama `CreateBadgeAsset` com o `StoreBadgeAssetRequest`: o
  plano pago, o username obrigatório, o provider/definição e a credencial
  cifrada seguem exatamente as regras da tela.
- `DELETE /badges` e `DELETE /logos` chamam as actions de exclusão usadas pelo
  painel (arquivos da badge pessoal inclusive).
- `POST /logos` (multipart) reusa `StoreCustomLogoRequest`,
  `ParseCustomLogoUpload` e `StoreCustomLogo`: o SVG cru nunca é persistido,
  servido nem devolvido.

Por consequência, um erro de plano ou de quota sai do mesmo ponto do código e
chega como `validation_error` 422 (ou 404 de dono), nunca como uma regra
paralela.

### 4. Envelope e escopo de dono

Sucesso: `{"data": ...}`; liste com `meta`/`links` e paginação
(`per_page` 20 por padrão, máximo 100). Erro: envelope `ApiError` com `code`
estável (`validation_error`, `forbidden`, `not_found`, `rate_limited`).
Recurso de outra conta responde **404** `not_found`, igual à tela — não 403,
que confirmaria a existência do id.

### 5. O que a resposta **não** leva

- Logo: só `id`, `slug`, `name`, `viewBox`, `pathCount`, `sizeBytes` e
  `createdAt`. Os paths normalizados continuam disponíveis apenas para o dono
  autenticado na tela, sob demanda.
- Badge pessoal: estado da credencial (`credentialSet`,
  `credentialUsername`), nunca a credencial em si.
- Endpoint e provider customizado: a URL de origem continua fora de qualquer
  API. Criar/editar endpoint ou provider customizado fica fora da F8.

### 6. Limites

`api-read` 120/min e `api-write` 30/min, com chave por token autenticado (ou
IP no catálogo anônimo), em `config/api.php`. A escrita é mais curta porque
cada POST/DELETE altera estado do dono. O 429 sai no envelope `rate_limited`
com `X-RateLimit-*` e `Retry-After`.

## Consequências

### Positivas

- A promessa do `API.md` deixa de ser falsa e o contrato OpenAPI cobre gestão.
- As regras continuam em um só lugar (actions/requests); a API só troca
  `back()` por `201`/`data`.
- Tokens do AI SQL Builder não ganham escrita em badge por acidente.

### Negativas / Trade-offs

- Duas abilities novas na tela de tokens; quem já tinha token continua com
  `ai:query` e precisa criar um segundo token para a API de badges.
- Sem PATCH, sem rotação de credencial e sem criação de endpoint/provider
  customizado pela API: automação de edição continua exigindo o painel.
- A API e a tela compartilham a action de criação; uma mudança de contrato da
  tela arrasta a API (é o preço de não duplicar regra).

## Alternativas descartadas

- **Reusar `ai:query`**: daria a qualquer token de SQL o poder de excluir
  badges; escopo demais para um token de outro produto.
- **API de gestão completa (PATCH, credencial, endpoint, custom provider)**:
  multiplicaria superfícies sensíveis (URL de origem, PAT) sem demanda; a F8
  entrega o que o `API.md` já prometia.
- **Devolver os paths do logo**: um GET com o desenho inteiro aumenta a
  exposição sem necessidade — o builder busca sob demanda e o documento
  continua só no cofre do dono.
- **Rotas em `/api/badgeforge`** (como o `API.md` antigo): divergiria do
  prefixo versionado do resto da API pública e do contrato OpenAPI 1.8.0.
- **403 para recurso de outra conta**: revela que o id existe; 404 é a regra
  do resto do BadgeForge.
