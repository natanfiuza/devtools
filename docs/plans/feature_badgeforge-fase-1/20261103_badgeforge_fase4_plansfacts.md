# BadgeForge — Fase 4 (providers + badges pessoais + PAT + docs públicas)

- Data: 2026-11-03
- Branch: `feature/badgeforge-fase-1`
- SPEC: `docs/SPEC-BADGEFORGE.md` §16 (F4)
- Plano detalhado: `docs/claude-code/plans/feature_badgeforge-fase-1/20261103_feature_badgeforge-fase-1_badgeforge_fase4.md`
- Levantamento: `docs/survey/20261103_badgeforge_fase4_surveyfacts.md`

## Objetivo

Transformar o BadgeForge em produto com badges dinâmicas e badges pessoais:
4 providers (GitHub, npm, Packagist, Docker Hub) lidos anonimamente por URL,
badges pessoais `/b/{username}/{ulid}.{svg|png}` para contas pagas, PAT por
badge cifrado para item privado e página pública de documentação em 5 idiomas.

## Decisões confirmadas na sessão de grill

1. **Escopo**: F4 completa (providers + pessoais + PAT + docs públicas).
2. **Metadados**: tabelas `badge_providers` e `badge_provider_definitions`
   com carga via migração (`insertOrIgnore`), porque o deploy nunca roda
   `db:seed`.
3. **Métricas**: 9 — `github/stars`, `github/forks`, `github/issues`,
   `github/license`, `npm/v`, `npm/dm`, `packagist/dt`, `packagist/v`,
   `docker/pulls`.
4. **Cadência**: execução contínua E1→E9, parada única no fim (ou gate
   vermelho).
5. **Schema**: `badge_assets` sem `slug` e com `user_id` obrigatório; o
   `source_path` do SPEC corresponde a `svg_path`/`png_path`; desvio
   registrado no SPEC e na ADR-081.
6. **Acesso**: provider público é anônimo; PAT é por badge pessoal, cifrado,
   e nunca aparece em URL, log, props, erro ou exportação.
7. **Cache**: 300 s por URL, requisição condicional, `stale-if-error` e
   fallback 200; imagem pessoal é stateless e o refresh é `POST` autenticado.
8. **Plano**: badge pessoal só é criada por conta paga; após downgrade as
   existentes continuam servindo, mas criação/edição ficam bloqueadas.
9. **Docs**: página `/{locale}/docs/badgeforge` nesta fase, 5 idiomas, SEO e
   sitemap.
10. **Smoke**: rede real, registrando bloqueios do ambiente.

## Etapas

| Etapa | Conteúdo |
|---|---|
| E1 | Migrações, models, factories, seeder e config |
| E2 | `ProviderCatalog`, parser de path, URL builder e registry |
| E3 | Clientes dos 4 providers, payload e formatter |
| E4 | Cache, endpoint público de provider, responder e spec builder |
| E5 | Badges pessoais: actions, storage, controller, refresh e prune |
| E6 | Props, fixture exata, tipos TS, exportação e anonimização |
| E7 | UI do builder, painel e i18n nos 5 idiomas |
| E8 | Página de docs, SEO/sitemap, footer e tutoriais |
| E9 | ADRs 080–082, SPEC, glossário, CLAUDE, aceite, relatório e gates |

## Gates

`composer test` (antes do Vitest), `npm run test`, `npm run types:check`,
`npm run lint:check`, `npm run build`, `route:list` das rotas novas e smoke
local. Nunca commit/push; o usuário revisa e commita.
