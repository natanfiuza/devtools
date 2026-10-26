# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: torna histórico padrão e amplia escopo da API v1
```

---

## 🎯 Resumo

O histórico de consultas do AI SQL Builder passa a ser **opt-out**: novas contas e contas existentes já nascem com `history_enabled = true`, e todas as consultas geradas ficam visíveis por padrão (com backfill das já existentes, recuperando `title` do `response_json`). Em paralelo, a API pública v1 deixa de escopar consultas por token e passa a escopar por **conta** (`user_id`): qualquer token da conta enxerga as consultas criadas pela web ou por outros tokens da mesma conta, mantendo 404 para consultas de terceiros. A resposta da API também ganha os campos necessários para reconstruir a consulta fora do painel (`schema`, `description`, `schema_source`, `dialect`, `title`).

Motivação: o modelo anterior (opt-in + escopo por token) escondia consultas legítimas do próprio usuário e obrigava o cliente da API a recriar o contexto da consulta a partir de campos parciais.

## 🛠️ Mudanças Técnicas

- **Migration** `2026_10_25_000001`: altera `users.history_enabled` para `default(true)`, faz backfill de `users.history_enabled = true`, marca `ai_requests.history_visible = true` e preenche `ai_requests.title` a partir de `response_json` em chunks de 100.
- **Model `User`**: `history_enabled` incluído nos atributos padrão (`true`).
- **API v1 (`SqlQueryController`)**: removido o filtro `api_token_id` em `index` e `show`; leitura passa a ser exclusivamente por `user_id`.
- **`ApiSqlQueryResource`**: expõe `schema_source`, `dialect`, `schema` e `description` (mapeado de `prompt`).
- **`SettleAiQuery`**: em cache hit, propaga `title` para a requisição persistida somente quando `history_visible` está ativo.
- **`ai-sql-builder/Tool.vue`**: recarga do histórico via `watch(status === 'done')` em vez de encadear no `submit`, garantindo atualização mesmo com submissões concorrentes ou cache hit.
- **`PaymentsServiceProvider`**: closure do `AboutCommand` convertido para `static` com resolução via `app()`, evitando captura de `$this`.
- **Docs**: `docs/openapi.yaml` atualizado (escopo por conta + novos campos e enums) e documentação interna revisada.
- **Testes**: cenários de histórico, API v1 (escopo por conta, novos campos), painel de conta, checkout (`Artisan::output()` em vez de `expectsOutputToContain`), sitemap (`contact`) e tipagem do teste de contato; timeout do `tools-registry` elevado para 60s.

## ⚠️ Impacto/Avisos

- **Banco de dados**: migration altera default de coluna e executa **backfill irreversível de dados** (`history_visible = true` e `title`). O `down()` reverte apenas o default da coluna — os dados backfilled permanecem. Recomenda-se backup antes do deploy.
- **Breaking change de contrato da API v1**: tokens da mesma conta passam a ver consultas uns dos outros e consultas criadas pela web. Clientes que dependiam do isolamento por token terão resultados diferentes (`meta.total` maior). Consultas de outras contas continuam em 404.
- **Privacidade**: consultas antes ocultas tornam-se listáveis para todos os tokens da conta — comunicar usuários com múltiplos tokens.
- **Novos campos na resposta**: `schema`, `description`, `schema_source`, `dialect`, `title` agora são obrigatórios no schema (OpenAPI) — consumidores com validação estrita precisam atualizar.
- **Sem novas variáveis de ambiente ou dependências**.


close #97

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_2_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)