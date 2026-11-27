# Levantamento — Programa Futuro F2: CLI e ambiente

Data: 2026-11-25
Branch: `feature/futuro-fase-2` (base `d98decf`, F1 commitada; árvore limpa além do
`.gitpr/reports/pr_desc/feature-futuro-fase-1_20261125154609_PR_DESC.md` pré-existente)
Origem: `/grill-with-docs` com o pedido "Pode seguir para a proxima fase do Programa Futuro, a nova branch
já foi criada"
Fonte: `docs/ROADMAP.md` §3.2/§3.3 (ADR-179), `docs/SPEC.md` §5/§9, código do repositório.

## 1. Contexto da tarefa

A F2 do Programa Futuro é a Onda 2 — "CLI e ambiente", porte M: `cron-explainer`, `curl-to-code` e
`env-example`, três tools de texto → texto, 100% no navegador, sem servidor, cota ou retenção. Nada do
escopo existe hoje: não há parser de cron, tokenizador de shell nem geração de `.env.example` no
repositório. O `dragonmantank/cron-expression` do Composer é dependência transitiva do Laravel,
server-side, e não serve ao cliente.

Fora da fase (já no roadmap): systemd timers, editar/instalar crontab, executar comando; executar a
requisição do cURL, ser parser completo de shell, ler arquivo local, guardar cookies/segredos; validar
`.env` contra esquema, guardar valores, buscar segredo remoto. Implementação normal, sem decisão de
produto nova (F5–F7 são as únicas com gate de entrada).

## 2. Rodadas do grill (2026-11-25)

### Rodada 1 — dialeto, fuso, parser, saídas do cURL

| Questão | Decisão |
| --- | --- |
| Dialeto do cron | 5 campos; `*`, listas, intervalos, passos; nomes de mês/dia; macros `@yearly/@annually/@monthly/@weekly/@daily/@midnight/@hourly`. Sem segundos e sem Quartz (`L`, `W`, `#`, `?`) |
| Fuso e lista | Fuso do navegador exibido na tela, sem seletor; próximas 10 execuções |
| Parser de cron | Próprio, sem dependência |
| cURL — saídas | Laravel `Http::...`, `fetch` e `axios` em abas; texto simples, sem pacote de linguagem |

### Rodada 2 — opções do cURL, máscara do `.env`, semântica do dia

| Questão | Decisão |
| --- | --- |
| Opções do cURL | Núcleo: `-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`, `--url`, `-u/--user` (aviso de credencial no código), `--json`; continuação `\` e aspas. O resto vira aviso de não suportado |
| Máscara do `.env` | Nome com segmento sensível (`KEY`, `SECRET`, `PASSWORD`, `PASS`, `TOKEN`, `AUTH`, `CREDENTIAL`, `DSN`, `PRIVATE`, `PASSWD`, `PWD`) → valor vazio e chave no aviso com a linha; URL com `user:pass@` também mascara; demais valores preservados |
| DOM/DOW | OR estilo Vixie quando ambos restritos; `0` e `7` são domingo |
| Preservação do `.env` | Ordem, comentários, linhas vazias, agrupamentos, aspas e `export ` intactos; linha sem `=` mantida e listada com o número |

### Rodada 3 — registro, telas, worker, ações

| Questão | Decisão |
| --- | --- |
| Registro | `cron-explainer` em `time` (150, `calendar-clock`); `curl-to-code` e `env-example` em `text` (160 e 170, `terminal` e `file-key`) |
| Telas | Cron com campo de texto simples; curl e env com `CodeEditor` + `<pre>`; curl em abas |
| Worker | Nenhum nas três; registrado no ADR-181 |
| Ações | Exemplo nas três; copiar nas três; baixar só no `env-example` (`.env.example`) |
| ADR | Um `ADR-181` cobrindo as três frente |

Fronteira esgotada: nenhuma decisão de produto nova; as demais escolhas são de implementação e vão
para o ADR-181.

## 3. Relatório de fatos levantados

### Infra reusada sem mudança

- `ToolShell` (`splitKey` obrigatório, `paneLabels`, dois painéis no desktop, abas no mobile),
  `ToolActionBar`/`ToolButton`, `CodeEditor` (sem prop `language`/`readOnly`; `extensions` só na
  montagem), `FileDropzone`, `lib/file` (`readTextFile`, `acceptAttribute`, `acceptsFile`),
  `lib/download` (`downloadText(filename, text, type)`), `lib/clipboard`, `useCopyFeedback`,
  `useFileError`, `lib/bytes`, `components/ui/{input,tabs,select}`.
- Precedentes: `timestamp-uuid` e `base64-url-hash` (casca + entrada + lista); `base64-url-hash` e
  `badgeforge` (abas no corpo); amostras por `./sample.txt?raw`; `json-to-code` (geradores em
  módulos); banner de perdas da F1 (`data-format-converter/Tool.vue` 594–613).
- O `ToolShell` **é de dois painéis por contrato** (duas `section` no desktop e duas abas no mobile;
  `tool-shell.test.ts` pina). O cron usa os dois painéis: entrada = expressão + explicação; saída =
  próximas execuções. Nenhum componente compartilhado muda.

### Dependências e ambiente

- Sem lib de datas (`date-fns`, `dayjs`, `luxon`, `moment` ausentes); `timestamp-uuid/lib/time.ts` já
  usa `Date` + `Intl`.
- CodeMirror instalado: `lang-json`, `lang-markdown`, `lang-sql`; `lang-javascript` só como
  transitiva do markdown e não será importada.
- Ícones `CalendarClock`, `Terminal` e `FileKey` exportados pelo `@lucide/vue` (declarado
  `^1.17.0`); precisam entrar explicitamente no mapa de `icons.ts`.

### Registro, i18n, SEO e pins

- 17 manifestos hoje (maior `order` 140); categorias `text` e `time` existem.
- Pins: `tests/Feature/Tools/ToolsRegistryTest.php` (`mvpToolSlugs()` e a expectativa do sitemap) e
  `resources/js/__tests__/tools-registry.test.ts` (`MVP`).
- i18n: `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`, paridade por `TranslationsParityTest`; compilação
  estrita por `i18n-messages.test.ts`.
- SEO: `php artisan seo:og-image` gera `public/og/<slug>.png` 1200×1200, exigido por `SeoMetaTest`;
  GD e fontes locais funcionaram na F1.
- Prettier: `printWidth 80`, `tabWidth 4`, aspas simples; os arquivos da F1 passam, os antigos não
  (251 pré-existentes). Os arquivos da F2 precisam ser Prettier-clean.

### Documentação

- ADR livre: **181**; template em `ADR-180`. Aceite: template `docs/acceptance/FUTURO-FASE-1.md`.
- ROADMAP: §3.2 (status da F2), §7 (revisão 1.2), `Versão: 1.2`. SPEC §5 (`### Futuro`) e §9
  (bullet do Programa Futuro). CHANGELOG no topo. Glossário no fim, sem acentos.
- Artefatos: `docs/survey/`, `docs/plans/feature_futuro-fase-2/`, cópia em
  `docs/claude-code/plans/feature_futuro-fase-2/`, relatório em `docs/claude-code/reports/`.

### Restrições em vigor

- Nenhum commit, push ou deploy; o usuário commita pelo gitpr.
- Nenhuma dependência nova (qualquer necessidade para e volta ao usuário).
- Nada do conteúdo digitado sai do navegador nem vai para log; strings visíveis só por i18n nos cinco
  idiomas.
