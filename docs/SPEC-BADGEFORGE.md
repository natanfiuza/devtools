# SPEC - BadgeForge (v1.1)

## 1. Visão
BadgeForge é uma plataforma de badges SVG/PNG integrada ao devtools.natanfiuza.dev.br. Pública para anônimos, com recursos para cadastrados e ilimitados para pagos.

## 2. Perfis
- Anônimo: badges estáticas, estilos, logos embutidos, preview, snippets, download.
- Cadastrado (F3): até 2 logos personalizados (`custom_logos.free_limit`, em config), presets ilimitados, JSON endpoint (F5), analytics básico (F7).
- Pago: logos personalizados ilimitados, badges pessoais (F4, entregue), providers customizados (F7), analytics completo, exportação CSV, sem limite de badges pessoais.

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

Providers iniciais: github, npm, packagist, docker — acesso anônimo para item público. Nove definições: `github/{stars,forks,issues,license}`, `npm/{v,dm}`, `packagist/{dt,v}`, `docker/pulls`. O `path` segue o `path_pattern` da definição (`owner/repo`, `package`, `vendor/package`, `namespace/repository`).

Cache padrão: 300 segundos por URL resolvida (as quatro definições do GitHub compartilham uma entrada), com requisição condicional, `stale-if-error` e fallback 200. O TTL de resposta é limitado pelo teto de cache do BadgeForge (`personal_badges.max_cache_seconds`, 3600 s). `?cacheSeconds=` e `?refresh=1` são ignorados na rota pública; a imagem nunca responde 500 por falha do provider (ADR-080).

### Endpoint JSON
GET /api/badges/endpoint.{svg|png}?url=...
Apenas cadastrados. HTTPS obrigatório, SSRF bloqueado, TTL mínimo 300s.

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
- badge_asset_views: id, badge_asset_id, user_id nullable, ip_hash, user_agent_hash, user_agent, referer_domain, country, format, style, cache_hit, viewed_at.

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

O **comportamento é código** (ADR-080): `client_key` resolve o cliente HTTP no `ProviderClientRegistry` (`github`, `npm`, `packagist`, `docker`) e `extractor_key` escolhe a métrica no payload projetado (`stars`, `forks`, `issues`, `license`, `v`, `dm`, `dt`, `pulls`). `parser_class` existe por compatibilidade e nasceu NULL; **nunca é lida nem instanciada**. Os metadados (catálogo, labels, paths, exemplos, `supports_private`, documentação, ordem) entram por migração com `insertOrIgnore`, porque o deploy roda `migrate --force` e nunca `db:seed`.

Providers iniciais (F4, entregue): github, npm, packagist, docker — sempre chamando a API pública do provider de forma anônima para item público. Cache do provider por URL resolvida (payload **projetado**, sem descrição/README), requisição condicional quando suportada e `stale-if-error` absorvem o rate limit anônimo; um 429 do provider nunca vira 500 na imagem. `supports_private` é true para GitHub (todas), npm `v` e Docker `pulls`; false para npm `dm` e Packagist (ambas). Docker privado usa Basic com `username` non-secret além do PAT.

Providers customizados: apenas pagos. Configuráveis via painel (F7).

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
- Provider (F4): 300s padrão (`providers.cache_ttl`), cache por URL resolvida com payload projetado; `stale_ttl` (86400 s) guarda o último valor bom; falha sem entrada vira fallback 200 com `fallback_ttl` (60 s, ou `Retry-After` dentro do limite). Headers `X-Badge-Cache: HIT|MISS|STALE` e `Warning: 110` quando stale. Store de cache fora do ar degrada para fetch direto/fallback (ADR-080).
- Badge pessoal (F4): o arquivo é servido de disco com `expires_at` no futuro; vencido, regenera sob lock e regrava. Exclusão/renomeação aparecem em no máximo `personal_badges.max_cache_seconds` (3600 s).
- Endpoint: mínimo 300s.
- PNG: mesmo TTL do SVG.
- Invalidação: `?refresh=1` apenas em badge própria com sessão; a rota pública de provider e a imagem pessoal **ignoram** o parâmetro. Atualizar badge pessoal é `POST /badgeforge/badges/{id}/refresh` autenticado do dono (ADR-069 e ADR-081).
- Admin pode limpar cache manualmente.

## 11. Rate limit
- Anônimo: 60/min.
- Cadastrado: 300/min.
- Pago: 3000/min configurável.
Aplica-se a provider e endpoint. Estáticas não são limitadas além de proteção global.

F4 (entregue): a rota pública de provider não tem throttle próprio — o cache por URL resolvida é a proteção contra rajada, e a imagem nunca responde 429 do nosso lado por padrão. O refresh autenticado usa `throttle:badgeforge-refresh`, configurado por `providers.refresh_rate_limit` (10) e `providers.refresh_rate_decay_seconds` (60). A gestão de logos/presets/badges usa `throttle:badgeforge-management` (`management.rate_limit`). Os limites por plano (60/300/3000 por minuto) valem para o endpoint JSON da F5.

## 12. Analytics
- GA apenas na interface web, com consentimento.
- Banco próprio registra acessos a imagens.
- Campos: badge_asset_id, user_id, ip_hash, user_agent_hash, user_agent, referer_domain, country, format, style, cache_hit, viewed_at.
- Cadastrado: visualização por badge.
- Pago: série temporal, top referrers, países, formatos, exportação CSV.
- Exportação em lote disponível para pagos.

## 13. Presets
Usuários cadastrados podem salvar presets: label, message, estilo, fonte, cores, logo (`none`, embutido ou `u_<ULID>`), logoSize, logoPosition, link e cacheSeconds. Preset é um **snapshot normalizado do formulário**, não uma badge publicada: não tem URL, cache, analytics nem registro de acesso (F3, ADR-079).

- Limite em `config/badgeforge.php` (`presets.limit`); `null` = ilimitado, e o valor vale para qualquer conta verificada.
- Um preset que aponta para logo excluído é lido como **órfão** (`logoOrphaned`) e aplicado com `logo = none`, com aviso na UI.
- `?preset=<ulid>` abre o builder com o snapshot aplicado; o parâmetro é removido da URL depois.
- Gestão no builder e em `/dashboard/badgeforge`, com item na navegação da conta.

## 14. Documentação
- Pública em `/{locale}/docs/badgeforge` (F4, entregue): cinco idiomas, tabela por provider gerada do catálogo, snippets copiáveis, badge pessoal/PAT, cache/stale, erros e segurança. Indexável, com meta de servidor, sitemap e link no rodapé (ADR-082).
- Página por provider (a partir da F4, entregue dentro da página única).
- A OpenAPI documenta provider e badge pessoal como entregues em v1.4.0; o 3.1 completo é F8.
- Tutoriais: guias do **usuário pagador** para item privado (GitHub, npm, Docker) em `docs/tutorial/`; Packagist é sempre público e não tem tutorial de PAT. Os tutoriais apontam para a página pública e para o fluxo real de revogação (excluir a badge).
- Exceção de idioma: o label/message da **imagem** são neutros (nomes próprios e números), porque a URL é pública e cacheada; a interface e a documentação são traduzidas (ADR-082).

## 15. Segurança
- Sanitização de SVG por allowlist de tags/atributos e escape XML; nada de markup do usuário entra no documento.
- Logo personalizado (F3, ADR-078): o parser aceita só `<svg>`, `<g>` e `<path>`, com allowlist de atributos (`viewBox`, `d`, `fill-rule`, `clip-rule`) e limites de bytes/paths/profundidade; recusa `DOCTYPE`, entidade, PI, `script`, `style`, `image`, `use`, `foreignObject`, `on*`, `href`, `style`, `transform`, `opacity` e `url(`. Só `viewBox` + paths normalizados são persistidos; o upload cru nunca é servido, logado ou exportado.
- `link` só aceita `http`/`https` e sai com `rel="noopener noreferrer"`.
- SSRF protection (F5).
- Rate limit global; o endpoint estático não tem throttle próprio na F1.
- Gestão autenticada (F3): rotas sob `auth, verified, active`, escritas em `throttle:badgeforge-management`, e registro de outra conta responde **404** (nunca 403).
- Hash de IP (analytics, a partir da F7).
- Auditoria.
- Credenciais: nenhum token global em `.env`; PAT do usuário só para item privado, cifrado, nunca em URL ou log (ADR-068).

## 16. Fases
F1: núcleo SVG estático + builder + snippets + cache. **Entregue em 2026-10-31** — ver `docs/acceptance/BADGEFORGE-FASE-1.md`.
F2: PNG + Imagick + download + docs + fontes `?font=`. **Entregue em 2026-11-02** — ver `docs/acceptance/BADGEFORGE-FASE-2.md` e ADR-077.
F3: logos personalizados + presets + contas. **Entregue em 2026-11-02** — ver `docs/acceptance/BADGEFORGE-FASE-3.md`, ADR-078 e ADR-079.
F4: providers GitHub/npm/Packagist/Docker + badges pessoais + PAT + docs públicas. **Entregue em 2026-11-03** — ver `docs/acceptance/BADGEFORGE-FASE-4.md`, ADR-080, ADR-081 e ADR-082.
F5: endpoint JSON + SSRF.
F6: providers Codecov/Coveralls/Laravel/PHP/Python.
F7: providers customizados + analytics completo + admin.
F8: OpenAPI + documentação + hardening.

## 17. Critérios de aceite
- Pest e Vitest verdes em cada fase.
- SVG válido e acessível (`role="img"`, `<title>`, `aria-label`).
- PNG até 600 px (F2).
- Cache respeitado (ETag/304 e `Cache-Control`).
- Rate limit funcionando.
- SSRF bloqueado (F5).
- i18n completo nos 5 idiomas.
- Docs por provider (F4, entregue em `/{locale}/docs/badgeforge`).
- Provider nunca vira 500 na imagem: falha é stale/fallback 200; PATH/URL inválidos são 404; badge pessoal desconhecida é 404 de corpo vazio, nunca HTML.
- Nenhum PAT em props, log, erro, flash, cache ou exportação; `badge_assets.credential` é cifrado e a chave de cache leva só o fingerprint.
- Analytics registrado (F7).
- Nenhuma credencial exposta: item público é anônimo; PAT de usuário só em item privado, cifrado e fora da URL e do log.
