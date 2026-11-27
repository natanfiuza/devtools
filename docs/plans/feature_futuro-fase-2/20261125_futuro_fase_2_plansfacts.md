# Plano — Programa Futuro F2: CLI e ambiente

Data: 2026-11-25
Branch: `feature/futuro-fase-2`
Origem: `/grill-with-docs` ("Pode seguir para a proxima fase do Programa Futuro, a nova branch já foi
criada"), três rodadas de grill em 2026-11-25; levantamento em
`docs/survey/20261125_futuro_fase_2_surveyfacts.md`.
Cópia do plano para o CLAUDE.md:
`docs/claude-code/plans/feature_futuro-fase-2/20261125_feature_futuro-fase-2_futuro_fase_2.md`.

## Decisões (fechadas no grill)

- `cron-explainer`: 5 campos com `*`, listas, intervalos, passos, nomes e macros; sem segundos/Quartz;
  OR Vixie quando DOM e DOW restritos; fisga `*` (inclusive `*/n`) como não restrita; `0` e `7` =
  domingo; fuso do navegador exibido, 10 próximas execuções; parser próprio.
- `curl-to-code`: abas Laravel `Http::...`, `fetch` e `axios`; sem destaque de sintaxe; núcleo de
  opções `-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`, `--url`,
  `-u/--user` (aviso de credencial) e `--json`; continuação `\` e aspas; todo o resto em aviso de não
  suportado, nunca ignorado em silêncio.
- `env-example`: máscara por segmento sensível (`KEY`, `SECRET`, `PASSWORD`, `PASS`, `TOKEN`, `AUTH`,
  `CREDENTIAL`, `DSN`, `PRIVATE`, `PASSWD`, `PWD`) e por URL com `user:pass@`, valor vazio e lista
  com a linha; preserva ordem, comentários, vazias, agrupamentos, aspas e `export `; linha sem `=`
  mantida e listada.
- Registro: `cron-explainer` (`time`, 150, `calendar-clock`), `curl-to-code` (`text`, 160,
  `terminal`), `env-example` (`text`, 170, `file-key`).
- Telas: cron com `Input` (sem editor) e dois painéis do `ToolShell`; curl e env com `CodeEditor` e
  `<pre>`; curl com `Tabs`.
- Sem worker nas três. Exemplo nas três, copiar nas três, baixar só no `env-example`.
- Um ADR-181. Sem dependência nova e sem mudança em componente compartilhado.

## Entregáveis

- `resources/js/tools/cron-explainer/`: `manifest.json`, `types.ts`, `lib/{parse,describe,next,report}.ts`,
  `sample.txt`, `Tool.vue`; testes `cron-parse`, `cron-next`, `cron-describe` e `cron-tool`.
- `resources/js/tools/curl-to-code/`: `manifest.json`, `types.ts`, `lib/{tokenize,parse,emit}.ts` (+
  emissores), `sample.txt`, `Tool.vue`; testes `curl-tokenize`, `curl-parse`, `curl-emit`, `curl-tool`.
- `resources/js/tools/env-example/`: `manifest.json`, `types.ts`, `lib/{parse,mask}.ts`, `sample.txt`,
  `Tool.vue`; testes `env-parse`, `env-mask`, `env-tool`.
- Registro/i18n/SEO: `icons.ts` (3 ícones), blocos novos nos cinco `lang/*/tools.php`, pins dos dois
  testes de registro, OG 1200×1200 das três.
- Docs: ADR-181, `docs/acceptance/FUTURO-FASE-2.md`, ROADMAP 1.2, SPEC §5/§9, CHANGELOG, glossário,
  survey, plano, cópia e relatório.
- Gates reais e evidência no navegador; nenhum commit, push ou deploy.

## Etapas

| # | Conteúdo | Verificação |
| --- | --- | --- |
| 0 | Salvar surveyfacts, plansfacts e cópia do plano; confirmar branch | arquivos no lugar; `git branch --show-current` |
| 1 | Motor do cron + testes | `npx vitest run cron-*` |
| 2 | Tela do `cron-explainer` + manifest + exemplo + testes | `npx vitest run cron-*` |
| 3 | Motor do cURL + testes | `npx vitest run curl-*` |
| 4 | Tela do `curl-to-code` + manifest + exemplo + testes | `npx vitest run curl-*` |
| 5 | Motor e tela do `env-example` + manifest + exemplo + testes | `npx vitest run env-*` |
| 6 | Ícones, i18n, pins, OG | `php artisan test --filter=ToolsRegistry`; `npx vitest run tools-registry` |
| 7 | ADR-181, aceite, ROADMAP/SPEC/CHANGELOG/glossário, relatório | links e datas conferidos |
| 8 | Gates finais, Playwright, relatório e parada | resultados reais |

## Verificação

`composer test` (Pint/PHPStan; Pest por `php artisan test` por causa do timeout de 300 s), `npm run
test` (shards se a RAM estourar), `types:check`, `format:check` (baseline de 251 arquivos
pré-existentes; nenhum da F2), `lint:check` (0 erros), `build`; Playwright com as três tools (cron
campo a campo/execuções, cURL nas abas com aviso de não suportadas, `.env` mascarado com download),
mobile, tema escuro, cinco idiomas e rede vazia; sitemap e OG; `git status` restrito a tools, i18n,
testes, `public/og` e docs.

## Riscos

DST e fuso (cálculo por `Intl` com zona explícita testável), horizonte para expressões impossíveis,
aspas/escapes do shell, máscara por segmento (falsos positivo/negativo documentados), `.env` sem
extensão no seletor, casca de dois painéis no cron, dívida pré-existente de `format:check`/lint e
gates sob pouca RAM. Nenhuma dependência nova: qualquer tentação para e volta ao usuário.
