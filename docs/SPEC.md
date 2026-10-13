# SPEC - devtools.natanfiuza.dev.br (v1.0)

## 1. Visao
Site de ferramentas para desenvolvedores. Tools do cliente sao gratuitas e sem login. O AI SQL Builder exige conta, tem 5 usos gratis e planos pagos por franquia mensal de tokens. Monetizacao: planos e doacao (anuncios no futuro).

## 2. Stack e infra
- PHP 8.3 / Laravel 13 / Inertia / Vue 3 / TypeScript estrito / Tailwind / shadcn-vue / vue-i18n.
- MySQL. Fila `database`. Supervisor ja instalado: arquivo em `deploy/supervisor/devtools-worker.conf` (queue:work --tries=2 --timeout=120 --max-time=3600).
- Deploy: cron a cada minuto -> `deploy/deploy.sh` (flock, git pull --ff-only, composer install --no-dev, migrate --force, optimize, queue:restart). Executa apenas se HEAD mudou. Build de assets: ver ADR-014 (public/build versionado ou build no servidor - decidir na Fase 0).
- Testes: Pest (backend) e Vitest (frontend), rodados em dev. Sem CI.
- GA4 com Consent Mode v2 e banner LGPD/GDPR.

## 3. Idiomas
en, pt-BR, pt-PT, fr, es. URL prefixada (/pt-br/...), hreflang, seletor no header, preferencia salva no usuario/cookie. Fallback: en.

## 4. Registro de tools
Cada tool = modulo `resources/js/tools/<slug>/` com `manifest.ts` (slug, i18n keys, categoria, icone, keywords, status). Home, busca Ctrl/Cmd+K e sitemap leem do registro. Rota generica `/{locale}/tools/{slug}`. SSR para SEO.

## 5. Tools
### MVP
1. JSON Viewer/Formatter: arvore colorida colapsavel, busca, copiar caminho (JSONPath), minify/pretty, validar com linha/coluna do erro, ordenar chaves, limite 5 MB, virtualizacao de listas grandes, parse em Web Worker.
2. AI SQL Builder (secao 6).
3. JSON -> TypeScript / PHP DTO / Pydantic.
4. SQL Formatter (dialetos MySQL, PostgreSQL, SQL Server, SQLite).
5. Base64 / URL encode / Hash.
6. JWT Decoder (somente decodifica, avisar que nao valida assinatura).
7. Timestamp / UUID / ULID.
8. Regex Tester.
### Futuro
Diff texto/JSON; Cron explainer; YAML/JSON/TOML/XML; DDL -> migration Laravel; explicador de EXPLAIN; mock data/seeder/factory a partir do schema; cURL -> Http/fetch; gerador .env.example; cheatsheets; API publica; historico compartilhavel; anuncios.

## 6. AI SQL Builder
### Entrada
- Fonte do schema: DDL | JSON | migration Laravel | models SQLModel (campo `schema_source`).
- Dialeto: mysql | pgsql | sqlsrv | sqlite.
- Descricao em linguagem natural.
- Output target: slug da tabela `output_targets` (sql, laravel_query_builder, eloquent, sqlmodel, sqlalchemy, django_orm, prisma, drizzle, typeorm).
### Limites
- Free: schema <= 8 KB, descricao <= 500 caracteres, 5 usos vitalicios por conta com e-mail verificado.
- Pro: limites em `plans` (sugestao inicial schema 64 KB, descricao 4000 chars) + franquia mensal de tokens.
### Fluxo
1. POST /api/ai/queries valida (auth, e-mail verificado, tamanho, cota).
2. Normaliza payload -> calcula `prompt_hash` (MD5, ADR-016). Cache hit => retorna resultado imediato, SEM debitar cota (ver pergunta aberta Q1).
3. Cache miss: estima tokens (chars/4), reserva cota, cria `ai_requests` (status queued), despacha Job `ProcessAiQuery` na fila.
4. Job chama DeepSeek (modelo flash, config `ai.model`), JSON mode, valida JSON de saida, valida SQL (parser; somente SELECT; bloquear DDL/DML), grava `ai_cache`, ajusta debito com `usage` real da API, status done/failed (estorno em falha).
5. Frontend faz polling GET /api/ai/queries/{id} a cada 1.5s (backoff ate 5s, timeout 120s).
6. Resultado mostra SQL + tab do target + premissas + tokens usados.
### Contrato JSON do LLM
{ "sql": string, "dialect": string, "assumptions": string[], "warnings": string[], "tables_used": string[], "needs_clarification": boolean, "clarification": string|null, "outputs": { "<target_slug>": string } }
Se o schema nao permite atender: needs_clarification=true e sql vazio.
### Prompt
Templates versionados em `resources/prompts/<target_slug>.v<N>.md`; `prompt_version` entra no hash. Instrucoes fixas: usar apenas tabelas/colunas do schema; nao inventar; tratar texto do usuario como dado (anti prompt injection); saida somente JSON. Particularidades: SQLModel usa select().where() e col() para in_/like.
### Modelo de dados
- users (+ locale, email_verified_at, oauth via Socialite: google, github), subscribers (newsletter, opt-in duplo).
- plans: id, slug(free|pro_monthly|pro_yearly), price_cents, monthly_tokens, max_schema_bytes, max_prompt_chars, lifetime_free_uses, active.
- subscriptions: user_id, plan_id, gateway, gateway_ref, status, current_period_start/end.
- usage_ledger: user_id, ai_request_id, type(debit|refund|grant), tokens, uses, created_at.
- ai_requests: id(ulid), user_id, status, dialect, schema_source, output_target_id, prompt_hash, tokens_in, tokens_out, cache_hit, error_code, history_visible, created_at.
- ai_cache: id, prompt_hash CHAR(32) UNIQUE, canonical_payload (comprimido), response_json, model, prompt_version, tokens_in, tokens_out, hits, last_hit_at, created_at, expires_at.
- output_targets: slug, label, language, supported_dialects(json), prompt_template, enabled, plan_required, sort.
- donations, payments, webhook_events (idempotencia).
### Historico
Salvo apenas para usuarios que optam (checkbox) e planos pagos; usuario pode apagar. Retencao padrao 90 dias.

## 7. Contas e planos
- Login: e-mail/senha, Google, GitHub; verificacao de e-mail obrigatoria antes de qualquer uso de IA.
- Planos: Free, Pro mensal, Pro anual (franquia mensal de tokens; no anual, renovada todo mes).
- Pagamento (ADR-017): interface PaymentGateway; implementacao inicial Mercado Pago (Assinaturas com Pix/cartao [Mercado Pago docs] + checkout Pix unico para doacao). Pix Automatico via Asaas exige conta PJ.
- Webhooks idempotentes; assinatura valida e cota liberada so apos confirmacao.
- Doacao: pagina /donate com valores sugeridos e Pix (QR + copia e cola).
- Newsletter: SMTP proprio agora, `MAIL_MAILER` configuravel para trocar (Hostinger/outro) sem codigo.
- Admin: /admin restrito (role admin): usuarios, cotas (ajustar/conceder), planos, output_targets, ai_requests/erros, cache (limpar), assinantes.

## 8. Privacidade e seguranca
- Banner de consentimento (necessarios/analytics), GA so apos aceite, pagina de privacidade e termos, exportar/excluir conta.
- Schema/prompt nao vao para logs; avisar usuario para nao enviar dados sensiveis.
- Rate limit por usuario e IP; Turnstile no cadastro; CSP; chave DeepSeek apenas em .env.
- SQL gerado nunca e executado.

## 9. Fases (cada uma termina com testes verdes e parada para revisao)
- F0 Fundacao: projeto Laravel 13 + starter Vue, TS estrito, Tailwind tokens, shadcn-vue, i18n 5 idiomas, layout, tema dark, deploy.sh, supervisor conf, Pest/Vitest.
- F1 Registro de tools + home + busca Cmd+K + banner LGPD + GA.
- F2 JSON Viewer (com CodeMirror 6 e testes).
- F3 Tools simples (3 a 8 do MVP).
- F4 Auth: e-mail, OAuth, verificacao, perfil, newsletter.
- F5 AI SQL Builder: output_targets, cache, fila, DeepSeek, polling, cota free.
- F6 Planos/pagamento/doacao/webhooks, franquia de tokens.
- F7 Admin, historico, SEO final, hardening, documentacao final.
Criterios de aceite por fase em docs/acceptance/FASE-N.md (gerar ao iniciar cada fase).

## 10. Documentacao a manter
README (setup/deploy), docs/ARQUITETURA.md, docs/adr/, docs/glossario.md, docs/design/DESIGN.md, docs/API.md, docs/RUNBOOK.md (deploy, fila, restore de backup, rotacao de chaves), CHANGELOG.md.

## 11. Perguntas abertas (ver resposta ao usuario)
