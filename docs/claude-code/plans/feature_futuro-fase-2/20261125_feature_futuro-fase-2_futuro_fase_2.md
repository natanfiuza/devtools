# Plano — Programa Futuro F2: CLI e ambiente (cópia do CLAUDE.md)

Data: 2026-11-25
Branch: `feature/futuro-fase-2`
Task: `futuro_fase_2`
Origem: `/grill-with-docs` ("Pode seguir para a proxima fase do Programa Futuro, a nova branch já foi
criada"), três rodadas de grill em 2026-11-25. Levantamento em
`docs/survey/20261125_futuro_fase_2_surveyfacts.md`; plano da sessão em
`docs/plans/feature_futuro-fase-2/20261125_futuro_fase_2_plansfacts.md`.

## Contexto

A F2 (Onda 2, porte M) entrega três tools de cliente — `cron-explainer`, `curl-to-code` e
`env-example` —, 100% no navegador, sem servidor, dependência nova ou worker. A F1 está commitada na
base desta branch. Nada do escopo existe no repositório.

## Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Cron — dialeto | 5 campos com `*`, listas, intervalos, passos, nomes de mês/dia e macros; sem segundos nem Quartz |
| Cron — dia | OR Vixie quando DOM e DOW restritos; `*` (inclusive `*/n`) não restringe; `0`/`7` domingo |
| Cron — fuso | Fuso do navegador exibido; 10 próximas execuções; parser próprio |
| cURL — saídas | Laravel `Http::...`, `fetch` e `axios` em abas, texto simples |
| cURL — opções | `-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`, `--url`, `-u/--user` (aviso de credencial), `--json`, `\` e aspas; o resto em aviso |
| `.env` — máscara | Segmento sensível → valor vazio e chave com linha no aviso; URL com `user:pass@` também |
| `.env` — preservação | Ordem, comentários, vazias, agrupamentos, aspas e `export ` intactos; linha sem `=` mantida e listada |
| Registro | `time` 150 `calendar-clock`; `text` 160 `terminal`; `text` 170 `file-key` |
| Telas | Cron com `Input`; curl e env com `CodeEditor` + `<pre>`; curl com abas |
| Worker / ações | Sem worker; Exemplo e Copiar nas três, Baixar só no `env-example` |
| ADR | Um `ADR-181` |

## Entregáveis

- `resources/js/tools/cron-explainer/` — `lib/{parse,describe,next,report}.ts`, `types.ts`,
  `sample.txt`, `Tool.vue`, `manifest.json`.
- `resources/js/tools/curl-to-code/` — `lib/{tokenize,parse,emit}.ts` (+ emissores), `types.ts`,
  `sample.txt`, `Tool.vue`, `manifest.json`.
- `resources/js/tools/env-example/` — `lib/{parse,mask}.ts`, `types.ts`, `sample.txt`, `Tool.vue`,
  `manifest.json`.
- 11 arquivos de teste em `resources/js/__tests__/`.
- `icons.ts`, cinco `lang/*/tools.php`, pins de `ToolsRegistryTest` e `tools-registry.test.ts`, OG.
- ADR-181, aceite F2, ROADMAP 1.2, SPEC §5/§9, CHANGELOG, glossário, survey/plano/cópia/relatório.

## Etapas

0. Artefatos e branch. 1. Motor do cron + testes. 2. Tela do cron + testes. 3. Motor do cURL +
testes. 4. Tela do cURL + testes. 5. `.env` + testes. 6. Registro/i18n/SEO. 7. Docs. 8. Gates,
Playwright, relatório e parada.

## Verificação

`composer test`/`php artisan test`, `npm run test` (shards sob pouca RAM), `types:check`,
`format:check` (baseline pré-existente; nenhum arquivo da F2), `lint:check` (0 erros), `build`;
Playwright nas três telas (campo a campo, abas, máscara, mobile, tema, cinco idiomas, rede vazia);
sitemap/OG; `git status` sem migration, rota, config ou `.env`.

## Parada

Nenhum commit, push ou deploy; o usuário revisa e commita pelo gitpr.
