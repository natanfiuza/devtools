# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona locale aos prompts de IA e isolamento de cache
```

---

## 🎯 Resumo

O gerador de consultas com IA respondia sempre a partir de um único prompt de sistema em inglês, sem distinguir o idioma solicitado pelo usuário nem isolar o cache por idioma. Esta mudança introduz o conceito de `locale` (en, pt_BR, pt_PT, fr, es) de ponta a ponta — front-end, API, canonicalização, cache e prompt — garantindo que título, explicação, premissas, avisos e esclarecimentos sejam entregues no idioma escolhido, e que respostas de idiomas diferentes nunca compartilhem o mesmo resultado em cache. O locale também passa a ser persistido para auditoria e histórico (ADR-056).

## 🛠️ Mudanças Técnicas

- Adiciona `App\Support\Locales` como fonte única de códigos suportados, slugs, fallback e resolução a partir da requisição/usuário.
- Cria migration `2026_10_23_000005_add_locale_to_ai_requests_table` adicionando `locale` (string 10, nullable, indexada, após `status`).
- Estende `CanonicalPayload` com `locale` (default `en`), incluindo o campo em `toArray()`/`fromArray()` e, consequentemente, no hash canônico.
- `Canonicalizer` normaliza o locale (slug → código) com fallback seguro quando o container/config não está disponível.
- `PromptBuilder` carrega o template de sistema de `resources/prompts/system/{locale}.md`, com fallback para `en.md` e para template inline, usando placeholders (`{target_instructions}`, `{target_key}`, `{target_slug}`).
- Novos prompts de sistema localizados: `en.md`, `pt_BR.md`, `pt_PT.md`, `es.md`, `fr.md`, com instrução explícita de idioma de saída.
- `StartAiQuery` aceita `?string $locale`, resolve fallback e persiste o locale em `ai_requests`.
- `StoreAiQueryRequest` valida `locale` como opcional contra códigos e slugs suportados.
- Controllers (`AiQueryController`, `Api\V1\SqlQueryController`) resolvem o locale informado, por slug ou pela preferência do usuário.
- Front-end (`SchemaForm.vue`, `types.ts`) envia `locale` no payload da consulta.
- Testes: persistência/fallback de locale na API, variação de hash por locale, reconstrução de payload legado e geração de prompt em cada idioma.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nova coluna `locale` em `ai_requests` — requer `php artisan migrate`. Registros anteriores permanecem com `locale` nulo.
- **Cache:** o hash canônico agora inclui o locale, invalidando cache de payloads gerados antes desta versão e garantindo isolamento por idioma.
- **Comportamento:** respostas deixam de seguir o idioma “implícito” da descrição e passam a obedecer ao locale resolvido; sem locale explícito, aplica-se o fallback (`en` ou preferência do usuário).
- **Novos arquivos de recurso:** os templates em `resources/prompts/system/*.md` passam a ser dependência de runtime (com fallback automático para `en.md`/template inline se ausentes ou ilegíveis).
- **Sem novas variáveis de ambiente ou dependências**; `ai.prompt_path` continua sendo respeitado, agora com subdiretório `system`.

close #82

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_5_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)