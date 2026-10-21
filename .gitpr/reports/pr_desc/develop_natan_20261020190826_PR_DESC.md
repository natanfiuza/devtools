# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: implementa AI SQL Builder com cache, cota e guarda SQL
```

---

## 🎯 Resumo

Entrega a Fase 5 do produto: a ferramenta **AI SQL Builder**, que gera consultas SQL a partir de linguagem natural com controle de cota por plano, cache de respostas e gate de segurança read-only. A mudança não se limita à tela: cria o ciclo de vida completo da solicitação de IA (reserva, liquidação e estorno), o contrato HTTP de sessão/CSRF/polling, o cliente fino do provedor DeepSeek e a infraestrutura de operação (comandos de limpeza de cache e reconciliação de solicitações presas). O objetivo é permitir que o envio de dados a um provedor externo seja controlado por feature-gate, transparente no texto legal, auditável no ledger de uso e resiliente a falhas do provedor. A tool também é promovida no catálogo público e recebe suíte de testes que trava contrato, segurança de SQL, cache, cota e resiliência.

## 🛠️ Mudanças Técnicas

- **Configuração**: novo `config/ai.php` (provider, timeouts, limites técnicos, cache TTL, job e rate limits); novas variáveis em `.env.example`; `DB_QUEUE_RETRY_AFTER` elevado para 150 para alinhar com o timeout do worker; namespace `ai` em `config/locales.php`; data legal atualizada para 2026-10-20.
- **Banco de dados**: migrations para `output_targets` (catálogo de 9 alvos com carga idempotente), `ai_requests` (ULID, reserva, payload canônico comprimido e índice de dedupe por `prompt_hash`), `ai_cache` (resposta por hash com TTL) e coluna `ai_request_ulid` em `usage_ledger`.
- **Domínio**: enums (`AiRequestStatus`, `AiFailureCode`, `QuotaUnit`, `SchemaSource`, `SqlDialect`), exceções tipadas de IA, `CanonicalPayload`/`Canonicalizer`/`PayloadCodec` (canonicalização com MD5 de identidade e zip+base64), `TokenEstimator`, `PromptLoader`/`PromptBuilder` (prompt versionado com delimitadores e regras anti prompt-injection), `ContractValidator` (validação estrita do JSON), `SqlGuard` (gate lexical read-only) e `AiCacheRepository` (hit apenas após revalidar contrato e payload).
- **Orquestração**: `StartAiQuery` (tranca de conta, dedupe de solicitação em fila, cache antes de cota, reserva na unidade do plano e dispatch pós-commit), `SettleAiQuery` (ajuste de tokens pagos, gravação de cache pós-commit e idempotência), `FailAiQuery` (estorno idempotente) e job `ProcessAiQuery` (rechecagem de cache, chamada ao provider, gate SQL, retry com `failOnTimeout` e hook `failed()`).
- **Providers e serviços**: `DeepSeekProvider` (cliente HTTP fino com fallback de `usage`), contrato `LlmProvider`, `AiServiceProvider` (bindings e rate limiters `ai-post`/`ai-poll`) e `AiConfiguration` (feature-gate por envs).
- **API**: `AiQueryController` (POST 200/202 e GET restrito ao dono, 404 para terceiros), `StoreAiQueryRequest`, `AiQueryResource` com `poll_after_ms`, envelope único de erro (`ApiError`), render de exceções em `bootstrap/app.php` e middleware de conta suspensa respondendo JSON em `api/*`.
- **Operação**: comandos `ai:prune-cache` e `ai:reconcile` (com `--dry-run`) para limpar cache expirado e fechar solicitações presas.
- **Suporte de UI**: `ToolProps`/`AiToolProps` entregam estado, catálogo e limites à página genérica, respeitando o contrato do ADR-043.
- **Frontend**: novo módulo `ai-sql-builder` (Tool, SchemaForm, ResultPanel, StateNotice, `api.ts` com CSRF/sessão, `polling.ts` com backoff e timeout, `useAiQuery` como máquina de estados, types), `Tool.vue` repassando `toolProps`, `Home.vue` com CTA real, `manifest.json` promovido a `available` e utilitário `byteLength` extraído para `lib/bytes`.
- **i18n e conteúdo**: novo `lang/*/ai.php` (5 locales) e ajustes em `home`, `legal` e `tools` para transparência do envio ao provedor; prompts versionados em `resources/prompts`.
- **Testes**: novos testes de feature (`AiToolPropsTest`, `DeepSeekProviderTest`, `ProcessAiQueryTest`), unitários (`CanonicalizerTest`, `ContractValidatorTest`, `PayloadCodecTest`, `PromptBuilderTest`, `PromptLoaderTest`, `SqlGuardTest`, `TokenEstimatorTest`) e de frontend (api, polling, tela, formulário e painel); ajuste de `SitemapTest`, `ToolsRegistryTest`, `SeoMetaTest` e `ToolPageTest` para o catálogo de nove tools.

## ⚠️ Impacto/Avisos

- **Banco de dados**: 3 novas tabelas (`output_targets`, `ai_requests`, `ai_cache`) e 1 nova coluna em `usage_ledger`. Requer `php artisan migrate`; a carga de `output_targets` é idempotente.
- **Variáveis de ambiente**: novas chaves de IA (provider, chave do provedor, timeouts, limites, TTL de cache, rate limits e feature-gate) em `.env.example`; `DB_QUEUE_RETRY_AFTER` deve ser 150 para casar com o timeout do worker sob pena de retry prematuro.
- **Dependências**: nenhuma biblioteca externa nova; o provedor é um cliente HTTP fino sobre a camada HTTP do framework.
- **Privacidade/compliance**: o texto legal passa a vigorar em 2026-10-20 e a UI informa explicitamente o envio do prompt ao provedor externo; o feature-gate permite desligar a funcionalidade sem deploy de código.
- **Segurança**: `SqlGuard` bloqueia DDL/DML, múltiplas instruções, `INTO` e funções perigosas; o prompt isola dados não confiáveis por delimitadores; respostas de cache só são servidas após revalidação de contrato e payload.
- **Contrato de UI**: páginas genéricas passam a receber `toolProps` (ADR-043) — tools de navegador devem entregar objeto vazio, sob risco de quebra de teste/contrato.
- **Operação**: agendar `ai:prune-cache` e `ai:reconcile` (com `--dry-run` em validação) para evitar acúmulo de cache e solicitações presas; o polling no cliente tem backoff e timeout próprios.
- **Catálogo público**: `ai-sql-builder` e `markdown-to-pdf` entram no índice, totalizando nove tools e eliminando manifests `soon`.


close #48

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_160_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)