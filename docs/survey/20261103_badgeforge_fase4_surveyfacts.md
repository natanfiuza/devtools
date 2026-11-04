# Survey — BadgeForge Fase 4

- Data: 2026-11-03
- Branch: `feature/badgeforge-fase-1`
- Solicitação: executar o plano da F4 do BadgeForge.

## Contexto da tarefa

F1 (SVG estático), F2 (PNG/fontes) e F3 (logos personalizados, presets e
contas) estão entregues. A F4 do `docs/SPEC-BADGEFORGE.md` §16 pede providers
GitHub/npm/Packagist/Docker com acesso público anônimo e PAT de usuário pago
apenas para item privado. Hoje não existe tabela de provider, chamada HTTP,
rota `/api/badges/{provider}/...` ou `/b/{username}/{ulid}`, cast `encrypted`
nem página pública de documentação do BadgeForge.

O estado do repositório no início da sessão: branch
`feature/badgeforge-fase-1`, commit mais recente
`c57d9ab feat: adiciona logos personalizados, presets e contas ao BadgeForge`;
apenas um relatório de linter não versionado.

## Fatos levantados

- `docs/SPEC-BADGEFORGE.md` §8/§16 descrevem `badge_providers`,
  `badge_provider_definitions` e `badge_assets` (esta com `slug` e
  `user_id` nullable).
- `routes/api.php` já tem o grupo `badges.` com a rota estática
  `static/{label}/{message}.{ext}`; a rota de provider precisa ser registrada
  depois dela para não sombrear `/static/...`.
- `bootstrap/app.php` suporta o callback `then` do `withRouting` e o 404
  global monta a página Inertia de erro; a rota de imagem pessoal precisa
  responder `response('', 404)` direto.
- `config/locales.php` tem 5 idiomas (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) e
  `namespaces` ainda não inclui `docs`.
- `docs/glossario.md` linhas 125–126 e 137 já definem "Badge dinâmica (de
  provider)", "Badge pessoal" e "Acesso ao provider"; a F4 atualiza esses
  verbetes em vez de duplicar "Provider badge".
- `docs/plans/` e `docs/survey/` já são usados por sessões anteriores; os
  diretórios existem.
- O padrão de seed em migração é `2026_10_14_000004_seed_plans_catalog.php`
  (`insertOrIgnore`), porque o deploy roda `migrate --force` sem `db:seed`.
- `PngCache` já usa escrita atômica (tmp + rename), padrão a repetir em
  `BadgeAssetStorage`.
- Não há cast `encrypted` no app hoje; `User` já usa `#[Hidden]`.

## Rodadas de decisão

### Rodada 1 (fronteira inicial)

1. **Cadência** — contínuo E1→E9, sem checkpoints (escolhido).
2. **Métricas** — 9, exatamente a lista enumerada; o rótulo "8 comuns" da
   opção anterior não batia com a própria lista (escolhido 9).
3. **Schema** — manter o desvio do plano: `badge_assets` sem `slug` e com
   `user_id` obrigatório; SPEC/ADR-081 registram (escolhido).
4. **Smoke** — chamar APIs reais com rede; bloquear registro se o ambiente
   impedir (escolhido).

Fronteira esgotada após a rodada: nenhuma decisão dependente restou aberta;
os demais pontos já estavam fixados no plano detalhado.

## Riscos residuais

- Rate limit anônimo dos providers: mitigado por cache de 300 s, condicional,
  stale-if-error e fallback.
- Vazamento de PAT: cast `encrypted`, `#[Hidden]`, nunca em `select`/props/log/
  cache; fingerprint HMAC na chave.
- 404 de imagem cair na página Inertia: resposta direta nos controllers.
- Free perder a própria lista após downgrade: lista permanece em modo leitura.
- Mudança de username quebrar `/b`: o arquivo guarda o path relativo criado;
  a URL pública usa o username atual e a antiga 404 (documentado).
