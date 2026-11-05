# SPEC - BadgeForge (v1.1)

## 1. Visão
BadgeForge é uma plataforma de badges SVG/PNG integrada ao devtools.natanfiuza.dev.br. Pública para anônimos, com recursos para cadastrados e ilimitados para pagos.

## 2. Perfis
- Anônimo: badges estáticas, estilos, logos embutidos, preview, snippets, download.
- Cadastrado (F3): até 2 logos personalizados (`custom_logos.free_limit`, em config), presets ilimitados, JSON endpoint (F5), analytics de 30 dias (F7, entregue).
- Pago: logos personalizados ilimitados, badges pessoais (F4, entregue), providers customizados até `custom_providers.limit` (F7, entregue), analytics completo com janelas 7/30/90 e exportação CSV (F7), sem limite de badges pessoais.
- Admin: vê o catálogo, os providers customizados (com template e pointer), desabilita providers/definições e acompanha o volume global em 7/30/90/400 dias.

## 3. Formatos
- SVG: formato canônico (F1, ADR-073).
- A extensão é obrigatória no caminho (`svg` ou `png`); `.png` não é alias de SVG.
- PNG (F2, entregue): 1x natural e fundo transparente, gerado na primeira requisição e depois servido do cache em disco (ADR-077).
- Largura máxima do PNG: 600 px, com redução proporcional acima disso.
- Biblioteca: PHP Imagick (extensão imagick); sem ela, PNG responde 503 `image_unavailable`.
- Conversão: SVG -> PNG no servidor.
- Fontes do badge: pilha do estilo por padrão; `?font=` aceita `fira-code`, `libre-franklin` e `space-grotesk` (ADR-077).

## 4. Estilos
flat, flat-square, plastic, for-the-badge, social.

## 5. Parâmetros
style, label, message, color, labelColor, logo, logoColor, logoSize, logoPosition, link, download, cacheSeconds, refresh, font.

`logo` aceita um slug embutido ou `u_<ULID>` de um logo personalizado (F3,
ADR-078). O namespace `u_` é reservado. Logo inexistente, apagado ou
malformado não é erro: o badge sai sem logo, 200.

`cacheSeconds` em badge com `logo=u_...` é limitado a
`custom_logos.max_cache_seconds` (3600 s), para que exclusão/renomeação do logo
apareçam em no máximo 1 h. Badge sem logo custom mantém o teto de 86400 s.

## 6. Tipos
### Estática
GET /api/badges/static/{label}/{message}.{svg|png}  (F1/F2, entregue)

### Provider
GET /api/badges/{provider}/{definition}/{path}.{svg|png}  (F4, entregue)

Providers (F4 + F6, entregues): github, npm, packagist, docker, codecov, coveralls, laravel, php e python — acesso anônimo para item público; nenhum dos cinco novos aceita item privado. Quinze definições: `github/{stars,forks,issues,license}`, `npm/{v,dm}`, `packagist/{dt,v}`, `docker/pulls`, `codecov/coverage`, `coveralls/coverage`, `laravel/{v,dt}`, `php/v` e `python/v`. O `path` segue o `path_pattern` da definição (`owner/repo`, `package`, `vendor/package`, `namespace/repository`); nas definições de versão de runtime o path é o literal `latest` (ADR-085).

Cache padrão: 300 segundos por URL resolvida (as quatro definições do GitHub compartilham uma entrada; no Laravel `v` e `dt` também, porque o client projeta as duas métricas de uma única chamada), com requisição condicional, `stale-if-error` e fallback 200. O TTL de resposta é limitado pelo teto de cache do BadgeForge (`personal_badges.max_cache_seconds`, 3600 s). `?cacheSeconds=` e `?refresh=1` são ignorados na rota pública; a imagem nunca responde 500 por falha do provider (ADR-080, ADR-085).

### Endpoint JSON
GET /api/badges/endpoint/{ulid}.{svg|png}  (F5, entregue)

Apenas cadastrados: o dono cadastra nome + URL de origem em `/dashboard/badgeforge` e a URL pública leva só o ULID. A imagem é stateless e anônima como a badge pessoal; ULID desconhecido responde 404. HTTPS obrigatório (porta 443), SSRF bloqueado com DNS pinado, sem redirect; contrato shields v1 (`schemaVersion=1`, `label`/`message` obrigatórios, `color`/`labelColor` opcionais) e TTL fixo de 300 s — `?cacheSeconds=` e `?refresh=1` são ignorados. O tipo está no SPEC §6 por causa da referência; a decisão completa está na ADR-083 e a defesa em rede na ADR-084.

### Provider customizado
GET /api/badges/custom/{ulid}/{definition}/{path}.{svg|png}  (F7, entregue)

Apenas plano pago cria e edita (limite em `custom_providers.limit`, default 10; `null` = ilimitado); o Free continua servindo, copiando e excluindo após downgrade, mas recebe 403 para criar/editar. O dono declara um template HTTPS, os placeholders do path e, por métrica, um JSON Pointer (RFC 6901) e um formatador `text|number|percent`. A URL pública é opaca e stateless; o template nunca sai do servidor. Template e conjunto de métricas são imutáveis (trocar = excluir e recriar); `label`, `pointer` e `formatter` são editáveis. O cadastro valida a origem com um `example_path` concreto: shape inválido responde 422, falha de rede é tolerada. Qualquer falha no serving vira stale/fallback 200, nunca 500. A imagem participa do analytics com `definition` preenchida (ADR-086, ADR-087).

### Pessoal
GET /b/{username}/{ulid}.{svg|png}  (F4, entregue)

Apenas usuários pagos podem criar. Todas públicas. A URL leva o ULID opaco e o username exato do dono; a rota é stateless (sem sessão, CSRF ou auth) e um registro alheio/excluído responde 404 direto, nunca a página Inertia. Depois de um downgrade as badges existentes continuam servindo e podem ser copiadas/excluídas; criação, renomeação, troca de PAT e refresh ficam bloqueados com 422 `paid_only` (ADR-081).

## 7. Persistência
Caminho (F4, revisado na ADR-081):
storage/app/data/tools/badgeforge/personal/{username}/{ulid}.svg
storage/app/data/tools/badgeforge/personal/{username}/{ulid}.png

O subdiretório `personal/` evita colisão com o cache `static/` e permite conferir o prefixo antes de qualquer leitura. O banco guarda o caminho relativo (`personal/...`), nunca absoluto. Badge pessoal sempre tem dono; não existe username `anonymous` nem badge anônima persistida.

Cache estático do PNG (F2): `storage/app/data/tools/badgeforge/static/{sha256(svg)}.png`, sem teto de tamanho; `badgeforge:prune` remove arquivos com mais de `prune_after_hours` (48 h) sem uso, com `--dry-run` (ADR-077).

Tabelas:
- badge_assets (F4, ADR-081): id ULID, **user_id obrigatório** (FK em cascata), name, type (`static|provider`), private, provider_slug, definition_slug, params (JSON), form (JSON — snapshot cosmético), credential (cifrado), credential_username, credential_fingerprint, credential_set_at, svg_path, png_path, etag, bytes, last_generated_at, expires_at, timestamps; único por `(user_id, name)` e índice `(user_id, created_at)`. **Desvio consciente do SPEC anterior**: não há coluna `slug` (o identificador público é o ULID) nem `source_path` (equivale a `svg_path`/`png_path`); `user_id` deixou de ser nullable porque não existe badge anônima.
- badge_endpoints (F5, ADR-083): id ULID, **user_id obrigatório** (FK em cascata), name (80), url (2048 — URL canônica de origem), timestamps; único por `(user_id, name)` e índice `(user_id, created_at)`. Não há coluna de segredo nem de payload: o corpo da origem nunca é persistido. A URL é `#[Hidden]` no model e fica fora de props, flash e log; exportação é a exceção explícita (dado do dono, download dele).

Analytics (F7, ADR-086; substitui o desenho do ADR-063):

- badge_views: id bigint, subject_type (`personal|endpoint|custom`), subject_id (ULID), user_id (FK em cascata), definition (nula em pessoal/endpoint), ip_hash (HMAC-SHA256), user_agent_hash (HMAC-SHA256), user_agent truncado, referer_domain, country (2 letras ou nula), format, style, cache_hit, viewed_at; índices `(subject_type, subject_id, viewed_at)`, `(user_id, viewed_at)` e `(viewed_at)`.
- badge_view_dailies: mesmas dimensões de sujeito + `day`, `country`/`referer_domain`/`format`/`definition` com default `''`, `views`, `unique_visitors`, `cache_hits`; único por `(subject_type, subject_id, day, country, referer_domain, format, definition)`, índices `(user_id, day)` e `(day)`. O default vazio evita NULL duplicado no único do MySQL.

Providers customizados (F7, ADR-087):

- badge_custom_providers: id ULID, user_id (FK em cascata), name (80), url_template (2048, `#[Hidden]`), path_params (JSON ordenado), example_path (512), metrics_version (uint, namespace de cache), enabled, timestamps; único por `(user_id, name)` e índice `(user_id, created_at)`.
- badge_custom_provider_definitions: id ULID, provider_id (FK em cascata), slug (32), label (80), pointer (256), formatter (`text|number|percent`), sort, timestamps; único por `(provider_id, slug)` e índice `(provider_id, sort)`.

F3 (ADR-078, ADR-079):
- badge_custom_logos: id ULID, user_id, name, view_box, paths (JSON), path_count, size_bytes, timestamps; único por (user_id, name). Guarda só o SVG normalizado — o arquivo enviado nunca vai para banco ou disco.
- badge_presets: id ULID, user_id, name, payload (JSON — snapshot do formulário), timestamps; único por (user_id, name). Não tem URL pública nem cache.

## 8. Providers
Tabelas:
- badge_providers
- badge_provider_definitions

Campos do provider:
slug, name, category, enabled, requires_auth, auth_type, base_url, cache_ttl, rate_limit_per_minute, config, documentation_url.

Campos da definition:
provider_id, slug, name, label (metade esquerda da imagem), client_key, extractor_key, path_pattern, required_params, optional_params, url_template, response_type, parser_class, example_path, supports_private, sort, enabled.

O **comportamento é código** (ADR-080, ADR-085): `client_key` resolve o cliente HTTP no `ProviderClientRegistry` (`github`, `npm`, `packagist`, `docker`, `codecov`, `coveralls`, `laravel`, `php`, `python`) e `extractor_key` escolhe a métrica no payload projetado (`stars`, `forks`, `issues`, `license`, `v`, `dm`, `dt`, `pulls`, `coverage`, `version`, `downloads`). `parser_class` existe por compatibilidade e nasceu NULL; **nunca é lida nem instanciada**. Os metadados (catálogo, labels, paths, exemplos, `supports_private`, documentação, ordem) entram por migração com `insertOrIgnore`, porque o deploy roda `migrate --force` e nunca `db:seed`.

Providers iniciais (F4, entregue): github, npm, packagist, docker — sempre chamando a API pública do provider de forma anônima para item público. Cache do provider por URL resolvida (payload **projetado**, sem descrição/README), requisição condicional quando suportada e `stale-if-error` absorvem o rate limit anônimo; um 429 do provider nunca vira 500 na imagem. `supports_private` é true para GitHub (todas), npm `v` e Docker `pulls`; false para npm `dm` e Packagist (ambas). Docker privado usa Basic com `username` non-secret além do PAT.

Providers da F6 (entregue, ADR-085): codecov (`coverage`, `totals.coverage`), coveralls (`coverage`, `covered_percent`), laravel (`v` e `dt` de `stats.json`, uma chamada para as duas), php (`version` do primeiro release de `php.net`) e python (`latest` do `endoflife.date`). Categorias novas: `coverage` e `runtime`. Os cinco são públicos e sem PAT (`supports_private=false`). Cobertura é percentual inteiro da branch padrão. As definições de runtime usam `required_params=[]` e `path_pattern=latest`; o parser e o builder só aceitam esse literal.

Providers customizados (F7, entregue, ADR-087): apenas plano pago cria e edita, com limite `custom_providers.limit` (default 10; `null` = ilimitado). A tabela é própria (`badge_custom_providers` + `badge_custom_provider_definitions`) e não se mistura ao catálogo global: `custom` é slug reservado e a rota `/api/badges/custom/...` é registrada antes da rota genérica. O mapeamento é declarativo — template HTTPS + `path_params` + JSON Pointer RFC 6901 por métrica, com formatadores `text`, `number` e `percent`. `text` normaliza e trunca pelo limite do badge; `number` e `percent` reusam o `ProviderMetricFormatter`. Nenhum código, credencial ou header do usuário é executado. Template e conjunto de métricas são imutáveis; `label`, `pointer` e `formatter` são editáveis e incrementam `metrics_version`. O dono nunca recebe `url_template`; recebe `pointer` (é ele quem edita). O admin vê template e pointer para investigar, pode desabilitar (URL vira 404) e nunca exclui; desabilitado, o provider continua na lista do dono para que ele possa excluir.

## 9. Acesso e credenciais
- Item **público**: lido anonimamente na API pública do provider. Não há token global por provider em `.env`, `config/` ou banco.
- Item **privado**: apenas usuário pagador, e apenas em badge pessoal. O PAT é criado pelo usuário no provider com escopo mínimo, informado na UI da conta, cifrado em repouso (cast `encrypted`, `APP_KEY`) e vinculado à definição do badge pessoal.
- A URL pública do badge leva somente o ULID opaco (`/b/{username}/{ulid}.svg`), nunca o token.
- O token nunca aparece em URL, log de aplicação, payload de debug, mensagem de erro ou exportação de dados. Revogável/rotacionável pelo usuário, apagado com o badge e com a conta.
- Packagist não hospeda pacote privado: o acesso é sempre público e anônimo; não há tutorial de PAT do Packagist.
- A F1 não chama provider e não lê token; a regra vale da F4 em diante (ADR-068).

F4 (entregue): `credential` usa cast `encrypted` e a coluna é `hidden` com `credential_fingerprint` (HMAC-SHA256 com `APP_KEY`, prefixado pelo provider). A chave de cache usa o fingerprint, nunca o token. Os FormRequests de criação/credencial declaram `$dontFlash = ['credential']`. Não há rota de "remover credencial" mantendo a badge: revogar é excluir a badge. Trocar `APP_KEY` invalida os PATs; o fluxo é re-credencializar (ADR-081).

## 10. Cache
- Estático: 86400s padrão (`cacheSeconds` clampado 300–86400, editável na URL) + ETag sha256 e 304 via `If-None-Match`.
- Provider (F4/F6): 300s padrão (`providers.cache_ttl`), cache por URL resolvida com payload projetado; `stale_ttl` (86400 s) guarda o último valor bom; falha sem entrada vira fallback 200 com `fallback_ttl` (60 s, ou `Retry-After` dentro do limite). Headers `X-Badge-Cache: HIT|MISS|STALE` e `Warning: 110` quando stale. Store de cache fora do ar degrada para fetch direto/fallback (ADR-080). A chave inclui o slug do provider: `laravel/v/latest` e `laravel/dt/latest` compartilham a entrada porque o client projeta versão e downloads juntos; `packagist/dt` continua numa entrada separada, mesmo usando o mesmo host (ADR-085).
- Badge pessoal (F4): o arquivo é servido de disco com `expires_at` no futuro; vencido, regenera sob lock e regrava. Exclusão/renomeação aparecem em no máximo `personal_badges.max_cache_seconds` (3600 s).
- Endpoint JSON (F5, ADR-083): chave `badgeforge:endpoint:v1:sha256(url canônica)` — dois cadastros da mesma origem compartilham a entrada e a chave não revela host/path. TTL fixo em `endpoint.cache_ttl` (300 s), limitado por `max_cache_ttl` (3600 s); `stale_ttl` (86400 s) guarda o último valor bom; `fallback_ttl` (60 s) é o TTL do fallback. HIT/MISS/STALE sob `Cache::lock`, `If-None-Match` quando a origem manda `ETag` (304 renova `fetched_at`), `X-Badge-Cache` e `Warning: 110` no stale; sem entrada, fallback 200 com `label` = nome do cadastro e `message` = `endpoint.unavailable_message`. Store fora do ar degrada para fetch direto e, se este falhar, fallback — nunca 500.
- Provider customizado (F7, ADR-087): chave `badgeforge:custom:v1:{ulid}:{metricsVersion}:sha256(url renderizada)`; TTL 300 s, `stale_ttl` 86400 s e `fallback_ttl` 60 s (todos em `custom_providers`). Só os escalares extraídos são cacheados. `metrics_version` é incrementado a cada update para que um pointer alterado nunca sirva o valor antigo. `?cacheSeconds=` e `?refresh=1` são ignorados. HIT/MISS/STALE sob lock, `X-Badge-Cache` e `Warning: 110` no stale; qualquer falha (guard, resolve, fetch, JSON, pointer) vira stale/fallback 200.
- PNG: mesmo TTL do SVG.
- Invalidação: `?refresh=1` apenas em badge própria com sessão; a rota pública de provider e a imagem pessoal **ignoram** o parâmetro. Atualizar badge pessoal é `POST /badgeforge/badges/{id}/refresh` autenticado do dono (ADR-069 e ADR-081).
- Admin pode limpar cache manualmente.

## 11. Rate limit
- Anônimo: 60/min.
- Cadastrado: 300/min.
- Pago: 3000/min configurável.
Aplica-se a provider e endpoint. Estáticas não são limitadas além de proteção global.

F4 (entregue): a rota pública de provider não tem throttle próprio — o cache por URL resolvida é a proteção contra rajada, e a imagem nunca responde 429 do nosso lado por padrão. O refresh autenticado usa `throttle:badgeforge-refresh`, configurado por `providers.refresh_rate_limit` (10) e `providers.refresh_rate_decay_seconds` (60). A gestão de logos/presets/badges usa `throttle:badgeforge-management` (`management.rate_limit`).

F5 (entregue, ADR-084): `throttle:badgeforge-endpoint` na rota pública, com o limite **do dono** do endpoint — Free `endpoint.rate_limit.free` (300/min), pago `endpoint.rate_limit.paid` (3000/min); ULID inválido/desconhecido ou banco fora cai no anônimo `endpoint.rate_limit.anonymous` (60/min) por IP, sem 500. “Anônimo” é o leitor sem conta; o destino sempre tem dono, e um README popular não pode derrubar a imagem de todos. O `ThrottleRequests` roda antes do binding, então o limiter valida o ULID cru com `Str::isUlid` e consulta o plano na hora (sem cache, para upgrade/downgrade valerem imediatamente). O 429 sai no envelope `rate_limited` com `X-RateLimit-*` e `Retry-After`.

F7 (entregue, ADR-087): `throttle:badgeforge-custom` na rota de provider customizado, com o mesmo desenho — limite do dono (Free `custom_providers.rate_limit.free` 300/min, pago `custom_providers.rate_limit.paid` 3000/min), ULID inválido/desconhecido ou banco fora cai no anônimo `custom_providers.rate_limit.anonymous` (60/min) por IP, sem 500. As escritas de gestão (`POST/PATCH/DELETE /badgeforge/custom-providers`) reusam `throttle:badgeforge-management` e o gate `badgeforge.paid`.

## 12. Analytics
- GA apenas na interface web, com consentimento.
- Banco próprio registra acessos a imagens **com dono**: badge pessoal, endpoint JSON e provider customizado (F7, entregue, ADR-086). Estática e provider global não gravam.
- Registro síncrono e best-effort: conta 200 e 304, uma linha por acesso, sem dedupe; falha de analytics nunca impede a imagem.
- Campos: `subject_type`, `subject_id`, `user_id`, `definition`, `ip_hash`, `user_agent_hash`, `user_agent` truncado, `referer_domain`, `country`, `format`, `style`, `cache_hit`, `viewed_at`.
- Privacidade: IP e UA viram HMAC-SHA256 com `APP_KEY`; referer guarda só o host; país vem de GeoLite2 local opcional (`analytics.geoip_path`) e fica `null` sem o arquivo.
- Cadastrado/Free: janela fixa de 30 dias, totais e únicos por imagem + totais da conta.
- Pago: janela 7/30/90, série diária zero-filled, top referrers, países, formatos, taxa de cache, tabela por sujeito e exportação CSV **sem** `ip_hash`/UA.
- Admin: janelas 7/30/90/400 a partir do agregado diário, com o aviso de que únicos não são somáveis entre dias.
- Retenção: bruto 90 dias, agregado diário 400 dias. `badgeforge:analytics:aggregate` reconstrói os últimos 7 dias a cada hora; `badgeforge:analytics:prune` roda diariamente. Agendados em `task_schedules`.
- Exportação em lote disponível para pagos (`GET /badgeforge/analytics/export`, 403 no Free).
- API JSON (F9, entregue, ADR-090): `GET /api/v1/badgeforge/analytics?window=` devolve o mesmo recorte do painel sob `data`, sem `exportUrl`; Free fica nos 30 dias, janela inválida cai em 30 e falha do bruto responde 503 `badge_unavailable`. O CSV continua só na web.

## 13. Presets
Usuários cadastrados podem salvar presets: label, message, estilo, fonte, cores, logo (`none`, embutido ou `u_<ULID>`), logoSize, logoPosition, link e cacheSeconds. Preset é um **snapshot normalizado do formulário**, não uma badge publicada: não tem URL, cache, analytics nem registro de acesso (F3, ADR-079).

- Limite em `config/badgeforge.php` (`presets.limit`); `null` = ilimitado, e o valor vale para qualquer conta verificada.
- Um preset que aponta para logo excluído é lido como **órfão** (`logoOrphaned`) e aplicado com `logo = none`, com aviso na UI.
- `?preset=<ulid>` abre o builder com o snapshot aplicado; o parâmetro é removido da URL depois.
- Gestão no builder e em `/dashboard/badgeforge`, com item na navegação da conta.

## 14. Documentação
- Pública em `/{locale}/docs/badgeforge` (F4, entregue): cinco idiomas, tabela por provider gerada do catálogo, snippets copiáveis, badge pessoal/PAT, cache/stale, erros e segurança. Indexável, com meta de servidor, sitemap e link no rodapé (ADR-082).
- Endpoint JSON (F5, entregue): nova seção na mesma página, com URL pública, contrato shields v1, TTL fixo, fallback e a regra de que a URL de origem só é cadastrada na dashboard — nunca exposta.
- Provider customizado (F7, entregue): seção curta na mesma página (ADR-087), explicando que é recurso pago, o formato da URL opaca, o JSON Pointer, a imutabilidade do template e que a origem nunca sai do servidor. Nenhum template de usuário aparece nos docs.
- Página por provider (a partir da F4, entregue dentro da página única).
- API JSON de gestão (F8, entregue, ADR-088): oito endpoints em `/api/v1/badgeforge` — catálogo anônimo de providers/estilos (ETag/304) e logos/badges do dono com as abilities `badgeforge:read`/`badgeforge:write`. A página pública ganha uma seção curta em cinco idiomas com URL base, escopos do token, link para `/settings/api-tokens` e link para a especificação.
- OpenAPI 3.1 (F8, entregue, ADR-071 e ADR-088): `docs/badgeforge/openapi.yaml` é a fonte da verdade do contrato, servida em `/openapi/badges.yaml`; o `OPENAPI.md` explica a semântica e um teste de contrato garante paridade rota ↔ spec. O contrato está em **1.9.0** desde a F9 (ADR-090), com as operações de PATCH, credencial, refresh, endpoints, custom providers e analytics.
- API de gestão completa (F9, entregue, ADR-090): o espelho do painel em `/api/v1/badgeforge` — PATCH de badge e logo, rotação de credencial, refresh, CRUD de endpoints e de providers customizados (pago para criar/editar) e analytics JSON sem `exportUrl`. Reusa as actions/FormRequests da tela, as abilities `badgeforge:read`/`badgeforge:write` e os limites `api-read`/`api-write`; presets e GET por ID seguem fora.
- Tutoriais: guias do **usuário pagador** para item privado (GitHub, npm, Docker) em `docs/tutorial/`; Packagist é sempre público e não tem tutorial de PAT. Os tutoriais apontam para a página pública e para o fluxo real de revogação (excluir a badge).
- Exceção de idioma: o label/message da **imagem** são neutros (nomes próprios e números), porque a URL é pública e cacheada; a interface e a documentação são traduzidas (ADR-082).

## 15. Segurança
- Sanitização de SVG por allowlist de tags/atributos e escape XML; nada de markup do usuário entra no documento.
- Logo personalizado (F3, ADR-078): o parser aceita só `<svg>`, `<g>` e `<path>`, com allowlist de atributos (`viewBox`, `d`, `fill-rule`, `clip-rule`) e limites de bytes/paths/profundidade; recusa `DOCTYPE`, entidade, PI, `script`, `style`, `image`, `use`, `foreignObject`, `on*`, `href`, `style`, `transform`, `opacity` e `url(`. Só `viewBox` + paths normalizados são persistidos; o upload cru nunca é servido, logado ou exportado.
- `link` só aceita `http`/`https` e sai com `rel="noopener noreferrer"`.
- SSRF protection (F5, entregue, ADR-084): `EndpointUrlGuard` exige HTTPS/443, sem userinfo, host ASCII válido e URL canônica; `EndpointTargetResolver` resolve A+AAAA e exige **todos** os IPs públicos (`IpAddressClassifier` com CIDRs da config, normalizando `::ffff:`); o fetch pina a conexão no IP validado com `CURLOPT_RESOLVE`, `CURLOPT_NOPROXY=*`, `proxy: ''`, `CURLOPT_PROTOCOLS=CURLPROTO_HTTPS` e `FOLLOWLOCATION=false` (3xx vira `redirected`). Sem `ext-curl` falha fechado (`require_curl`), nunca cai num handler que não pina. URL insegura na criação vira 422 `validation.badge_endpoint.*`; no serving a falha de rede vira stale/fallback 200.
- Rate limit global (F8, ADR-089): static, provider e `/b/` dividem um cap por IP `badgeforge-images` (`badgeforge.serving.rate_limit.per_minute`, default 6000/min, configurável por ambiente); endpoint JSON e provider customizado mantêm o limite por dono da F5/F7. O 429 sai no envelope `rate_limited` com `X-RateLimit-*` e `Retry-After`.
- Hardening da imagem (F8, ADR-089): todo SVG sai com CSP própria (`default-src 'none'; style-src 'unsafe-inline'`), independente do `CSP_MODE`; recurso inexistente/ULID inválido/dono errado é **404** `not_found`; `QueryException` em `api/badges/*` e `b/*` é **503** `badge_unavailable` com `Retry-After: 60` e `Cache-Control: no-store`; `/b/` usa o envelope `ApiError` (inclusive pelo guard do fallback do site); falha da **origem** continua stale/fallback 200.
- Gestão e operação (F8, ADR-089): o throttle do `badgeforge-management` responde **429 JSON** com headers do throttle no GET de export CSV e mantém o toast Inertia em POST/PATCH/DELETE; `/up` checa banco, cache e disco do PNG e responde 503 `down` com o mapa das sondas, sem vazar exceção.
- Gestão autenticada (F3): rotas sob `auth, verified, active`, escritas em `throttle:badgeforge-management`, e registro de outra conta responde **404** (nunca 403).
- Analytics (F7, ADR-086): IP e UA só como HMAC-SHA256 com `APP_KEY`; UA cru truncado em 512; referer só host; GeoIP local opcional; CSV sem PII.
- Provider customizado (F7, ADR-087): mesma defesa SSRF da F5 (HTTPS/443, sem userinfo/redirect, todos os IPs públicos, conexão pinada, fail-closed sem curl), com 422 no cadastro e fallback 200 no serving. `url_template` é `#[Hidden]`, `dontFlash` e nunca entra em props do dono, builder, docs, CSV, log ou auditoria. Exceções explícitas: o dono recebe o `pointer` (é ele quem o edita) e o admin recebe template/pointer na tela de custom providers.
- Admin (F7): `/admin/badgeforge/*` sob `can:access-admin`; desabilitar provider, definição ou provider customizado registra auditoria **sem** a URL de origem. O admin nunca exclui provider customizado.
- Auditoria.
- Credenciais: nenhum token global em `.env`; PAT do usuário só para item privado, cifrado, nunca em URL ou log (ADR-068).

## 16. Fases
F1: núcleo SVG estático + builder + snippets + cache. **Entregue em 2026-10-31** — ver `docs/acceptance/BADGEFORGE-FASE-1.md`.
F2: PNG + Imagick + download + docs + fontes `?font=`. **Entregue em 2026-11-02** — ver `docs/acceptance/BADGEFORGE-FASE-2.md` e ADR-077.
F3: logos personalizados + presets + contas. **Entregue em 2026-11-02** — ver `docs/acceptance/BADGEFORGE-FASE-3.md`, ADR-078 e ADR-079.
F4: providers GitHub/npm/Packagist/Docker + badges pessoais + PAT + docs públicas. **Entregue em 2026-11-03** — ver `docs/acceptance/BADGEFORGE-FASE-4.md`, ADR-080, ADR-081 e ADR-082.
F5: endpoint JSON + SSRF. **Entregue em 2026-11-04** — ver `docs/acceptance/BADGEFORGE-FASE-5.md`, ADR-083 e ADR-084.
F6: providers Codecov/Coveralls/Laravel/PHP/Python. **Entregue em 2026-11-04** — ver `docs/acceptance/BADGEFORGE-FASE-6.md` e ADR-085.
F7: providers customizados + analytics completo + admin. **Entregue em 2026-11-04** — ver `docs/acceptance/BADGEFORGE-FASE-7.md`, ADR-086 e ADR-087.
F8: OpenAPI + documentação + hardening. **Entregue em 2026-11-05** — ver `docs/acceptance/BADGEFORGE-FASE-8.md`, ADR-088 e ADR-089.
F9: API de gestão completa. **Entregue em 2026-11-05** — ver `docs/acceptance/BADGEFORGE-FASE-9.md` e ADR-090.

## 17. Critérios de aceite
- Pest e Vitest verdes em cada fase.
- SVG válido e acessível (`role="img"`, `<title>`, `aria-label`).
- PNG até 600 px (F2).
- Cache respeitado (ETag/304 e `Cache-Control`).
- Rate limit funcionando.
- SSRF bloqueado (F5): HTTPS/443, sem userinfo/redirect, todos os IPs públicos e conexão pinada; URL de origem fora de props, flash, log e cache legível.
- Endpoint JSON (F5): contrato shields v1, TTL fixo, convergência de origem nunca 500 (stale/fallback 200), 404 para ULID desconhecido e rate limit por dono.
- Providers F6 (entregue): cobertura como percentual inteiro (0–100), versão de runtime no path literal `latest`, Laravel `v`/`dt` compartilhando uma chamada de cache e nenhum provider novo aceitando item privado; falha de shape vira fallback 200 e path literal inválido é 404.
- i18n completo nos 5 idiomas.
- Docs por provider (F4, entregue em `/{locale}/docs/badgeforge`).
- Provider nunca vira 500 na imagem: falha da origem é stale/fallback 200; PATH/URL inválidos são 404; badge pessoal desconhecida é 404 no envelope `ApiError`, nunca HTML nem corpo vazio (F8).
- API de gestão (F8, ADR-088): catálogo anônimo com ETag/304; `ai:query` não acessa a API de badges e `badgeforge:*` não acessa a API de IA; recurso de outra conta é 404; a resposta nunca leva paths do logo nem a credencial; `per_page` tem teto de 100; o token novo continua com apenas `ai:query` marcado.
- API de gestão completa (F9, ADR-090): PATCH de badge/logo/endpoint renomeia com o mesmo FormRequest da tela (mesmo nome aceito, nome de outra conta 404); credencial é write-only e nunca ecoada; refresh devolve `url`/`pngUrl`/`generatedAt` e responde 429 `rate_limited` com `Retry-After`/`X-RateLimit-Limit: 10`; endpoint é criado no Free com SSRF/probe e a URL de origem nunca volta; custom provider só é criado/editado no pago (**403**), excluído após downgrade, com `url_template` proibido no PATCH (422); analytics devolve o shape do painel sem `exportUrl`, janela inválida em 30 e `badge_views` ausente em **503** `badge_unavailable`; o contrato serve a versão **1.9.0**.
- Hardening (F8, ADR-089): cap por IP em static/provider/`/b/` e limiter por dono preservado em endpoint/custom; SVG com CSP fechada; matriz 404/503 uniforme com `Retry-After`/`no-store`; export CSV do management responde 429 JSON e as ações de diálogo mantêm o toast; `/up` responde 503 quando banco, cache ou disco do PNG falha, sem exceção no corpo; `/openapi/badges.yaml` serve a versão vigente do contrato (1.9.0 desde a F9) e o teste de contrato não deixa rota pública fora da spec.
- Nenhum PAT em props, log, erro, flash, cache ou exportação; `badge_assets.credential` é cifrado e a chave de cache leva só o fingerprint.
- Provider customizado (F7, entregue): só pago cria/edita (Free 403), limite por plano, URL opaca, template imutável, métricas fixas na criação, probe com `example_path` (shape 422; rede tolerada), serving nunca 500 (stale/fallback 200), 404 para ULID/definição/path inválidos ou provider desabilitado, e `custom` nunca resolve pela rota genérica.
- Analytics (F7, entregue): pessoal, endpoint e custom gravam 200 e 304; estática e provider global não gravam; IP/UA só como HMAC; UA truncado; referer só host; GeoIP ausente vira `null`; tabela ausente não derruba a imagem; CSV do dono sem hashes/UA; Free limitado a 30 dias e CSV 403; admin lê o agregado em 7/30/90/400.
- Retenção e agregação (F7): agregação horária idempotente dos últimos 7 dias e poda diária (bruto 90, dailies 400); comandos em `task_schedules`.
- Admin BadgeForge (F7): catálogo e definições alternáveis, custom providers com dono e métricas, template/pointer visíveis só no admin, desabilitar derruba a rota (404) e o toggle é auditado sem a origem; sem exclusão.
- Nenhuma credencial exposta: item público é anônimo; PAT de usuário só em item privado, cifrado e fora da URL e do log.
