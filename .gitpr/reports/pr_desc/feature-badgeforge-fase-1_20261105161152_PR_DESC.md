# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona CRUD e analytics à API de gestão do BadgeForge
```

---

## 🎯 Resumo

Completa a API de gestão do BadgeForge (Fase 9, ADR-090) expondo via token Sanctum tudo o que hoje só existe no painel web: renomeação de logos, ciclo de vida de badges pessoais (renomear, rotacionar credencial e regenerar imagem), CRUD de endpoints JSON e de providers customizados, além de analytics da conta. O objetivo é permitir automação e integração programática sem depender da sessão web, mantendo as mesmas regras de plano, os guards de SSRF e a política de nunca devolver dados sensíveis (URLs de origem, credenciais e desenhos de logo).

## 🛠️ Mudanças Técnicas

- Adiciona `AnalyticsController` que espelha o painel em JSON, com janela inválida caindo em 30 (nunca 422), remoção do `exportUrl` (CSV permanece web) e 503 `badge_unavailable` em falha de banco via `QueryException`.
- Adiciona `BadgeEndpointController` e `BadgeCustomProviderController` com listagem paginada, criação, atualização e exclusão, isolamento por dono (404 para recurso alheio) e preservação de imutáveis (`url`/`url_template`, `path_params`, `example_path`, conjunto de slugs).
- Estende `BadgeAssetController` com `update` (renomeação), `credential` (troca write-only do PAT, com invalidação de cache) e `refresh` (regeneração síncrona que empilha o throttle `badgeforge-refresh` com o `api-write`).
- Adiciona `update` ao `BadgeLogoController` e extrai a mutação compartilhada na action `RenameCustomLogo`, reutilizada também pelo `CustomLogoController` web.
- Cria `ApiBadgeEndpointResource` e `ApiBadgeCustomProviderResource` que omitem URL de origem, expondo apenas ULID, URLs públicas e métricas declaradas.
- Ajusta `EnsureBadgeForgeHasPaidPlan` para lançar `AccessDeniedHttpException`, permitindo que o `api/*` converta em envelope `forbidden` em vez de 403 genérico.
- Ajusta o callback de throttle em `BadgeForgeServiceProvider` para responder `rate_limited` no envelope da API (mantendo `{message}` na web) e propagando headers de rate limit.
- Atualiza `openapi.yaml` para 1.9.0 com todos os novos paths, schemas (`BadgeEndpoint`, `BadgeCustomProvider`, `OwnerAnalytics`, `BadgeCredentialInput`, etc.), parâmetros e a resposta `BadgeUnavailable`.
- Registra todas as novas rotas em `routes/api.php` respeitando as abilities `badgeforge:read`/`badgeforge:write` e o middleware `badgeforge.paid` onde aplicável.
- Cobre tudo com testes de feature: isolamento de abilities, analytics (Free/pago, isolamento, 503), refresh (sucesso, validações, 404, 429 por conta), escrita de badges (rename, credencial, imutabilidade), endpoints (SSRF, probe, cache), providers customizados (limites de plano, imutáveis, tolerância a rede) e rename de logos.

## ⚠️ Impacto/Avisos

- **Sem migrations**: nenhuma alteração de schema; apenas novas rotas e controllers sobre modelos existentes.
- **Sem novas variáveis de ambiente** ou dependências externas.
- **Comportamento de API alterado**: `EnsureBadgeForgeHasPaidPlan` agora lança `AccessDeniedHttpException`; clientes que consumiam o 403 genérico passam a receber o envelope `forbidden`. O throttle de refresh em `api/*` também muda para o envelope `rate_limited` (a web permanece inalterada).
- **Contrato OpenAPI bump 1.8.0 → 1.9.0**: consumidores devem regenerar clientes a partir do spec atualizado.
- **Segurança**: credenciais e URLs de origem continuam write-only e nunca são ecoadas em respostas ou mensagens de erro; acessos cruzados respondem 404 (não 403), evitando enumeração de recursos.
- **Rate limit**: o `refresh` consome tanto o limite `api-write` (30/min por token) quanto `badgeforge-refresh` (10/min por conta), prevalecendo o mais restritivo.

close #136

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_41_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)