# SPEC - BadgeForge (v1.1)

## 1. Visão
BadgeForge é uma plataforma de badges SVG/PNG integrada ao devtools.natanfiuza.dev.br. Pública para anônimos, com recursos para cadastrados e ilimitados para pagos.

## 2. Perfis
- Anônimo: badges estáticas, estilos, logos embutidos, preview, snippets, download.
- Cadastrado: até 2 logos personalizados, JSON endpoint, presets, analytics básico.
- Pago: logos ilimitados, badges pessoais, providers customizados, analytics completo, exportação CSV, sem limite de badges pessoais.

## 3. Formatos
- SVG: formato canônico e único da F1 (ADR-073).
- A extensão é obrigatória no caminho; `.png` só existe a partir da F2 (não é alias de SVG).
- PNG (F2): gerado na primeira requisição, depois servido do disco.
- Largura máxima do PNG: 600 px.
- Biblioteca: PHP Imagick (extensão imagick).
- Conversão: SVG -> PNG no servidor.

## 4. Estilos
flat, flat-square, plastic, for-the-badge, social.

## 5. Parâmetros
style, label, message, color, labelColor, logo, logoColor, logoSize, logoPosition, link, download, cacheSeconds, refresh.

## 6. Tipos
### Estática
GET /api/badges/static/{label}/{message}.svg  (F1; `.png` entra na F2)

### Provider
GET /api/badges/{provider}/{path}.{svg|png}
Providers iniciais: github, npm, packagist, docker — acesso anônimo para item público.
Cache padrão: 300 segundos.
Refresh: ?refresh=1 apenas para badges próprias.

### Endpoint JSON
GET /api/badges/endpoint.{svg|png}?url=...
Apenas cadastrados. HTTPS obrigatório, SSRF bloqueado, TTL mínimo 300s.

### Pessoal
GET /b/{username}/{ulid}.{svg|png}
Apenas usuários pagos podem criar. Todas públicas.

## 7. Persistência
Caminho:
storage/app/data/tools/badgeforge/{username}/{ulid}.svg
storage/app/data/tools/badgeforge/{username}/{ulid}.png

Anônimo usa username=anonymous.

Tabelas:
- badge_assets: id ULID, user_id nullable, slug, type, provider_slug, source_path, svg_path, png_path, etag, bytes, last_generated_at, expires_at, timestamps.
- badge_asset_views: id, badge_asset_id, user_id nullable, ip_hash, user_agent_hash, user_agent, referer_domain, country, format, style, cache_hit, viewed_at.

## 8. Providers
Tabelas:
- badge_providers
- badge_provider_definitions

Campos do provider:
slug, name, category, enabled, requires_auth, auth_type, base_url, cache_ttl, rate_limit_per_minute, config, documentation_url.

Campos da definition:
provider_id, slug, name, path_pattern, required_params, optional_params, url_template, response_type, parser_class, badge_template, example_url, enabled.

Providers iniciais (F4): github, npm, packagist, docker — sempre chamando a API pública do provider de forma anônima para item público. Cache do provider, requisição condicional quando suportada e `stale-if-error` absorvem o rate limit anônimo; um 429 do provider nunca vira 500 na imagem.

Providers customizados: apenas pagos. Configuráveis via painel.

## 9. Acesso e credenciais
- Item **público**: lido anonimamente na API pública do provider. Não há token global por provider em `.env`, `config/` ou banco.
- Item **privado**: apenas usuário pagador, e apenas em badge pessoal. O PAT é criado pelo usuário no provider com escopo mínimo, informado na UI da conta, cifrado em repouso (cast `encrypted`, `APP_KEY`) e vinculado à definição do badge pessoal.
- A URL pública do badge leva somente o ULID opaco (`/b/{username}/{ulid}.svg`), nunca o token.
- O token nunca aparece em URL, log de aplicação, payload de debug, mensagem de erro ou exportação de dados. Revogável/rotacionável pelo usuário, apagado com o badge e com a conta.
- Packagist não hospeda pacote privado: o acesso é sempre público e anônimo; não há tutorial de PAT do Packagist.
- A F1 não chama provider e não lê token; a regra vale da F4 em diante (ADR-068).

## 10. Cache
- Estático: 86400s padrão (`cacheSeconds` clampado 300–86400, editável na URL) + ETag sha256 e 304 via `If-None-Match`.
- Provider: 300s padrão; `stale-if-error` quando o provider falhar ou limitar.
- Endpoint: mínimo 300s.
- PNG: mesmo TTL do SVG.
- Invalidação: ?refresh=1 apenas para badges próprias.
- Admin pode limpar cache manualmente.

## 11. Rate limit
- Anônimo: 60/min.
- Cadastrado: 300/min.
- Pago: 3000/min configurável.
Aplica-se a provider e endpoint. Estáticas não são limitadas além de proteção global.

## 12. Analytics
- GA apenas na interface web, com consentimento.
- Banco próprio registra acessos a imagens.
- Campos: badge_asset_id, user_id, ip_hash, user_agent_hash, user_agent, referer_domain, country, format, style, cache_hit, viewed_at.
- Cadastrado: visualização por badge.
- Pago: série temporal, top referrers, países, formatos, exportação CSV.
- Exportação em lote disponível para pagos.

## 13. Presets
Usuários cadastrados podem salvar presets: label, message, estilo, cores, logo, provider e parâmetros. Preset não é badge publicada.

## 14. Documentação
- Pública em /docs/badgeforge.
- Página por provider (a partir da F4).
- OpenAPI 3.1 completo (F8).
- Tutoriais: guias do **usuário pagador** para item privado (GitHub, npm, Docker) em `docs/tutorial/`; Packagist é sempre público e não tem tutorial de PAT.

## 15. Segurança
- Sanitização de SVG por allowlist de tags/atributos e escape XML; nada de markup do usuário entra no documento.
- `link` só aceita `http`/`https` e sai com `rel="noopener noreferrer"`.
- SSRF protection (F5).
- Rate limit global; o endpoint estático não tem throttle próprio na F1.
- Hash de IP (analytics, a partir da F7).
- Auditoria.
- Credenciais: nenhum token global em `.env`; PAT do usuário só para item privado, cifrado, nunca em URL ou log (ADR-068).

## 16. Fases
F1: núcleo SVG estático + builder + snippets + cache. **Entregue em 2026-10-31** — ver `docs/acceptance/BADGEFORGE-FASE-1.md`.
F2: PNG + Imagick + download + docs.
F3: logos personalizados + presets + contas.
F4: providers GitHub/npm/Packagist/Docker (acesso público anônimo; PAT de usuário pago só para item privado).
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
- Docs por provider (F4+).
- Analytics registrado (F7).
- Nenhuma credencial exposta: item público é anônimo; PAT de usuário só em item privado, cifrado e fora da URL e do log.
