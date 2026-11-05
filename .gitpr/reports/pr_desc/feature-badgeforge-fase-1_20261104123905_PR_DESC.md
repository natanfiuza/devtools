# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona badges de endpoint JSON no BadgeForge
```

---

## 🎯 Resumo

Entrega a Fase 5 do BadgeForge (ADR-083/ADR-084): o dono cadastra uma URL HTTPS que devolve um JSON no contrato shields v1 e recebe uma URL de imagem **opaca** (`/api/badges/endpoint/{ulid}.svg|png`). O objetivo é permitir badges dinâmicas — status de deploy, cobertura, versão — sem que a URL de origem apareça no README, no HTML, no log ou no cache. A imagem pública é anônima, stateless e identificada apenas pelo ULID; a origem permanece no servidor.

A mudança também fecha o risco de SSRF inerente à funcionalidade: só HTTPS na 443, sem userinfo/fragmento/redirect, resolução DNS obrigatoriamente pública (tudo-ou-nada) e conexão pinada no IP validado via `CURLOPT_RESOLVE`, impedindo DNS rebinding entre a checagem e o fetch. Falha de origem nunca vira 500: serve STALE (última resposta boa) ou fallback 200 com a mensagem `unavailable`.

## 🛠️ Mudanças Técnicas

- **Modelo e persistência**: novo `BadgeEndpoint` (ULID, `url` em `#[Hidden]`, unicidade `user_id+name`), migration `badge_endpoints`, relação `User::badgeEndpoints()` e factory.
- **Actions**: `CreateBadgeEndpoint` (valida URL, resolve DNS e faz probe de amostra; insegurança/contrato inválido → 422; rede fora/timeout/DNS sem resposta não bloqueiam), `UpdateBadgeEndpoint` (apenas renomeia — URL é imutável) e `DeleteBadgeEndpoint` (remove linha + entrada de cache).
- **Guard de URL e SSRF**: `EndpointUrlGuard` (canonicalização, esquema, porta, host DNS ASCII/IPv4, caracteres proibidos), `EndpointTargetResolver`, `IpAddressClassifier` (bloqueio de loopback, RFC1918, CGNAT, link-local, metadata, multicast, ULA etc.), `DnsHostResolver` e `EndpointHttpClient` com sink limitado (`LimitedResponseSink`) e teto de corpo mesmo sem `Content-Length`.
- **Cache e fallback**: `EndpointCache`/`EndpointCacheKey` (chave = hash SHA-256 da URL canônica), `CachedEndpointPayload` (só payload projetado, nunca o corpo cru), `EndpointMetrics` com fluxo HIT/MISS/STALE, lock por chave, revalidação `If-None-Match`/304 e TTLs configuráveis.
- **Contrato shields v1**: `EndpointPayloadParser` aceita estritamente `schemaVersion === 1` com `label`/`message` não vazios e `color`/`labelColor` opcionais; demais campos são descartados.
- **API pública**: rota `GET /api/badges/endpoint/{badgeEndpoint}.{ext}` com rate limit `badgeforge-endpoint`, registrada antes da rota de provider (e `endpoint` reservado no catálogo). Headers `X-Badge-Cache` e `Warning: 110` em STALE.
- **Rate limit por dono**: `RateLimiter::for('badgeforge-endpoint')` resolve o dono pelo ULID cru (o `ThrottleRequests` roda antes do `SubstituteBindings`), com limites distintos para anônimo/Free/pago e queda para anônimo por IP em caso de ULID desconhecido ou banco fora.
- **Gestão na dashboard**: `EndpointManagementController` (store/update/destroy com 404 para recurso de outra conta) + FormRequests com limites de nome/URL.
- **UI**: `EndpointBadgesPanel.vue` (lista, copiar SVG/PNG, renomear, excluir com confirmação), `EndpointBadgeDialog.vue` (cadastro), CTA no builder e nova seção em `BadgeForgeDocs.vue` com contrato, TTL, limites e nota de segurança.
- **Privacidade e LGPD**: `AnonymizeUser` passa a apagar os endpoints do usuário; `ExportUserData` inclui os endpoints (nome, URL de origem e URLs públicas); `dontFlash(['credential','url'])` impede que a URL volte em `_old_input` de um 422.
- **Config**: `config/badgeforge.php` ganha bloco `endpoint` (TTLs, timeouts, tetos, CIDRs bloqueados, rate limits); a chave antiga `badgeforge.endpoint` (string) foi renomeada para `badgeforge.static_endpoint`, com todos os pontos de uso e testes ajustados.
- **i18n**: novas chaves em `docs`, `tools` e `validation` para pt_BR, pt_PT, en, es e fr.
- **Testes**: unitários (guard de URL, classificador de IP, resolver, parser, cliente HTTP) e feature (rota pública HIT/MISS/STALE/304/404/422/fallback, gestão, rate limit, exportação de props sem a URL de origem) + testes Vitest do painel, diálogo e docs.

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova migration `create_badge_endpoints_table` (tabela `badge_endpoints`, FK `user_id` com `cascadeOnDelete`, unique `user_id+name`). Requer `php artisan migrate`.
- **Variáveis de ambiente**: novas opcionais `BADGEFORGE_ENDPOINT_LIMIT_ANONYMOUS` (60), `BADGEFORGE_ENDPOINT_LIMIT_FREE` (300) e `BADGEFORGE_ENDPOINT_LIMIT_PAID` (3000).
- **Config renomeada (breaking para customizações)**: `badgeforge.endpoint` (string) virou `badgeforge.static_endpoint`; a chave `badgeforge.endpoint` agora é um array. Qualquer config ou override externo que use a chave antiga precisa ser migrado.
- **Dependência de extensão**: `ext-curl` é exigido em runtime (`require_curl => true`) para o pin de DNS; sem curl, o cadastro e a renderização caem em falha fechada/fallback.
- **Rede**: o servidor passa a fazer requisições HTTP de saída para origens fornecidas por usuários. Ambientes com firewall/egress restrito precisam liberar HTTPS 443 e DNS; proxies de ambiente são ignorados de propósito (`proxy => ''`).
- **Cache**: usa o store padrão do Laravel; sem cache disponível a imagem continua funcionando com fetch direto (degradação graciosa).
- **Rate limit**: a recusa é 429 `rate_limited` no envelope padrão — consumidores de imagem verão o badge quebrado ao exceder o limite, comportamento esperado e documentado.
- **Segurança**: `endpoint` passa a ser slug reservado no catálogo de providers; qualquer provider futuro com esse nome colidiria.

close #128

---

[![GitPR](https://img.shields.io/badge/GitPR-6_errors_%C2%B7_106_warnings-red)](https://gitpr.natanfiuza.dev.br/)