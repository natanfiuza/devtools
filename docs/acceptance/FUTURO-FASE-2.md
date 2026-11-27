# Critérios de aceite — Programa Futuro FASE 2 (CLI e ambiente)

Gerado em 2026-11-27 (implementação de 2026-11-25; evidência de navegador em 2026-11-27).
Fonte: `docs/ROADMAP.md` §3.3 (ADR-179), `docs/adr/ADR-181-futuro-fase-2-cli-ambiente.md`,
levantamento em `docs/survey/20261125_futuro_fase_2_surveyfacts.md` e plano em
`docs/claude-code/plans/feature_futuro-fase-2/20261125_feature_futuro-fase-2_futuro_fase_2.md`.

## Escopo da fase

- **`cron-explainer`**: expressão cron de cinco campos lida campo a campo, com macros
  (`@yearly`/`@annually`, `@monthly`, `@weekly`, `@daily`/`@midnight`, `@hourly`), listas,
  intervalos, passos, nomes de mês/dia e a semântica Vixie entre dia-do-mês e dia-da-semana. Mostra o
  fuso do navegador e as **10** próximas execuções no locale da interface; expressão impossível para no
  horizonte de oito anos com aviso `exhausted`; erro nomeado com o campo.
- **`curl-to-code`**: tokenizador de shell próprio e conversão de um comando `curl` do núcleo
  (`-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`, `--url`, `-u/--user`,
  `--json`, valores anexados e flags agrupadas) em três abas determinísticas — Laravel
  (`Http::...`), `fetch` e `axios` — em texto simples. O que fica de fora é listado como não
  suportado; `-u` gera aviso de credencial no código; `-d @arquivo` é recusado.
- **`env-example`**: `.env` colado vira `.env.example` preservando ordem, comentários, linhas vazias,
  agrupamentos, aspas e `export `; máscara por segmento do nome (`KEY`, `SECRET`, `PASSWORD`, `PASS`,
  `TOKEN`, `AUTH`, `CREDENTIAL`, `DSN`, `PRIVATE`, `PASSWD`, `PWD`) e por valor (URL com `user:senha@`);
  aviso com nome e linha das chaves mascaradas e com o número das linhas inválidas; copiar e baixar.
- **Registro, i18n e SEO**: três manifestos (`time` 150 com `calendar-clock`; `text` 160 e 170 com
  `terminal` e `file-key`), ícones, blocos completos nos cinco idiomas, pins do registro e as três
  imagens OG 1200×1200 (mais o `og-default.png` regenerado).
- **Testes**: motores e telas das três tools (11 arquivos, 131 testes), mais os pins de registro.
- **Documentação**: ADR-181, este aceite, ROADMAP (status e revisão 1.2), SPEC §5/§9, CHANGELOG,
  glossário, levantamento, plano e relatório.
- **Gates reais** e verificação no navegador; **nenhum** commit, push ou deploy.

Fora da fase (decisões já registradas na ADR-181): segundos e extensões Quartz no cron; seletor de
fuso; `cron-expression`/`cron-parser` no cliente; parser de shell completo; opções de cURL além do
núcleo (`-F`, cookies, redirects, proxy, certificado — listadas como não suportadas); execução da
requisição; leitura de arquivo local (`-d @arquivo`); detecção de segredo por entropia no `.env`;
validação contra esquema do projeto; worker nas três tools; download no cron e no cURL; dependência
nova; deploy.

## Matriz requisito → implementação → teste → situação

| # | Requisito | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Cinco campos, listas, intervalos, passos, nomes e macros | `tools/cron-explainer/lib/parse.ts` | `cron-parse.test.ts` | auto | OK |
| 2 | Erros nomeados com campo (`empty`, contagem, macro, nome, valor, intervalo, passo, sintaxe) | `lib/parse.ts`, `types.ts` (`CronErrorCode`) | `cron-parse.test.ts` | auto | OK |
| 3 | `0` e `7` como domingo (normalização para `0`) | `lib/parse.ts` | `cron-parse.test.ts` | auto | OK |
| 4 | Semântica Vixie (OR quando dia-do-mês e dia-da-semana estão restritos; `*` com passo não restringe) | `lib/next.ts` | `cron-next.test.ts` | auto | OK |
| 5 | Próximas 10 execuções no fuso do navegador, no locale da interface | `lib/next.ts`, `lib/report.ts`, `Tool.vue` | `cron-next.test.ts`, `cron-tool.test.ts`; navegador | auto + manual | OK |
| 6 | DST: hora que não existe é pulada, hora repetida usa o primeiro instante | `lib/next.ts` (conversão parede ↔ instante por `Intl`) | `cron-next.test.ts` | auto | OK |
| 7 | Horizonte de oito anos com `exhausted`, sem laço infinito | `lib/next.ts` (`MAX_SEARCH_DAYS`), `Tool.vue` | `cron-next.test.ts`, `cron-tool.test.ts`; navegador (`0 0 30 2 *`) | auto + manual | OK |
| 8 | Explicação campo a campo com descritores estruturados (i18n fraseia) | `lib/describe.ts`, `Tool.vue` | `cron-describe.test.ts`, `cron-tool.test.ts` | auto | OK |
| 9 | Tokenizador de shell: aspas, escapes, continuação `\`+nova linha (LF/CRLF), erros com posição | `tools/curl-to-code/lib/tokenize.ts` | `curl-tokenize.test.ts` | auto | OK |
| 10 | Núcleo de opções, valores anexados e flags agrupadas | `lib/parse.ts` | `curl-parse.test.ts` | auto | OK |
| 11 | Precedência de método `-X` > `-G` > corpo/`--json` > `GET`; `-G` move `-d` para query; `--json` só acrescenta `Content-Type` ausente | `lib/parse.ts` | `curl-parse.test.ts` | auto | OK |
| 12 | Não suportado listado (nunca silencioso), `-d @arquivo` recusado, URL extra e cabeçalho sem `:` avisados, credencial avisada só pelo usuário | `lib/parse.ts`, `Tool.vue` | `curl-parse.test.ts`, `curl-tool.test.ts`; navegador | auto + manual | OK |
| 13 | Três saídas determinísticas em texto simples (Laravel, `fetch`, `axios`), cópia da aba ativa | `lib/emit/{laravel,fetch,axios}.ts`, `Tool.vue` | `curl-emit.test.ts`, `curl-tool.test.ts` | auto | OK |
| 14 | `.env` lido preservando `export `, aspas, multi-linha, comentário em linha, CRLF e linha sem `=` | `tools/env-example/lib/parse.ts` | `env-parse.test.ts` | auto | OK |
| 15 | Máscara por segmento e por URL com `user:senha@`, mantendo chave, `=` e comentário; multi-linha vira uma linha | `lib/mask.ts` | `env-mask.test.ts` | auto | OK |
| 16 | Aviso com chaves (nome + linha) e linhas inválidas (número); copiar e baixar `.env.example` | `Tool.vue`, `lib/mask.ts` | `env-tool.test.ts`; navegador | auto + manual | OK |
| 17 | Aceitação de arquivo cobre `.env`, `.env.*`, `*.env` e `text/plain` | `lib/files.ts`, `Tool.vue` | `env-tool.test.ts` | auto | OK |
| 18 | Registro: manifestos, ícones, categoria e ordem, pins de slug (20 manifestos) | `manifest.json` das três tools, `icons.ts`, `tools-registry.test.ts`, `ToolsRegistryTest.php` | Pest `ToolsRegistryTest`; `tools-registry.test.ts` | auto | OK |
| 19 | i18n nos cinco idiomas, sem texto fixo | `lang/{en,pt_BR,pt_PT,fr,es}/tools.php` | `*-tool.test.ts`; Pest `TranslationsParityTest` | auto | OK |
| 20 | SEO: OG 1200×1200 das três tools, sitemap e cards | `public/og/{cron-explainer,curl-to-code,env-example}.png` | Pest `SeoMetaTest`, `ToolsRegistryTest`; navegador/servidor | auto + manual | OK |
| 21 | Privacidade: nada do conteúdo sai do navegador | `Tool.vue` das três tools (nenhum `fetch`/`axios`) | navegador (performance entries `fetch`/`xhr`/`beacon` vazias); grep | auto + manual | OK |
| 22 | Sem worker e sem dependência nova | nenhum arquivo de worker; `package.json` intacto | `git status`; gates | manual | OK |
| 23 | Nenhuma regressão nas 17 tools anteriores e no resto do site | suíte inteira, `types:check`, `build` | gates abaixo | auto | OK (com observações) |

## Resultados reais dos gates (2026-11-25)

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formatação PHP | `vendor/bin/pint --parallel --test` | verde, exit 0 |
| Análise estática PHP | `vendor/bin/phpstan analyse` | **0 erros**, exit 0 |
| Suíte PHP | `php artisan test` | **1857 testes, 1855 passaram, 2 skipped, 34638 asserções**, exit 0, 181 s |
| Suíte JS | `npx vitest run`, em três shards com `--maxWorkers=2` | **184 arquivos, 2218 testes passaram, 0 falhas** (35 arquivos de Layout/399 testes; 74/895; 75/924) |
| Testes da fase | `npx vitest run` nos 11 arquivos `cron-*`/`curl-*`/`env-*` | **11 arquivos, 131 testes passaram** |
| Tipos | `npm run types:check` | exit 0 (Wayfinder + `vue-tsc --noEmit`) |
| Formatação | `npm run format:check` | exit 1 em **251 arquivos pré-existentes**, **nenhum da F2**; os arquivos da fase passam no `prettier --check` direto |
| Lint | `npm run lint:check` | exit 0 — **0 erros**, 1147 avisos (`vue/html-indent` em templates Vue) |
| Build | `npm run build` | exit 0, `✓ built in 16.72s` |

Pesos de build medidos depois do build (decimal / gzip): chunk da tela do cron 11,47 kB / 4,46 kB;
chunk da tela do cURL 9,86 kB / 3,81 kB; chunk da tela do `.env` 7,35 kB / 3,03 kB.

Observações reais dos gates:

- Os 2 skips da suíte PHP são os dois casos de `LayoutSystemConcurrencyTest` opt-in, herdados.
- `composer test` roda Pint e PHPStan (os dois verdes) e depois a suíte com o timeout de processo de
  300 s do Composer; como na F1, a suíte foi rodada pelo comando equivalente `php artisan test` (o
  mesmo do script), com o resultado acima. Nenhum teste foi pulado ou filtrado.
- O Vitest cheio estoura por RAM nesta máquina (registrado na memória do projeto); a suíte foi rodada
  em três shards com `--maxWorkers=2`, cobrindo os 184 arquivos e 2218 testes sem falha.
- `format:check` já falhava antes da fase no repositório inteiro (251 arquivos com o template em 2
  espaços que o Prettier configurado não aceita); os arquivos da F2 foram formatados e passam no
  `prettier --check` direto. Nenhum arquivo da F2 aparece na lista do gate.
- Os 1147 avisos de lint são `vue/html-indent` nos templates (classe pré-existente); os três `.vue` da
  fase respondem por parte deles e **0 erros**. Os arquivos da F2 seguem o Prettier.
- Os `sample.txt` das três tools não entram no `format:check` (o Prettier não infere parser para
  `.txt` na expansão de diretório), o mesmo comportamento dos `sample.txt` anteriores.

## Verificação no navegador (Playwright)

Feita com o Chromium real do Playwright contra o build servido local (`php artisan serve` em
`127.0.0.1:8123`), com capturas em `.playwright-mcp/` (scratch local, fora do versionamento; cópias em
`docs/claude-code/reports/feature_futuro-fase-2/evidence/`). URLs com o slug de locale (`/pt-br/...`).

| Verificação | Resultado observado |
| --- | --- |
| Cron válido | `0 9 * * 1-5` → tabela Minuto `0`/“Em 0”, Hora `9`/“Em 9”, Dia do mês `*`/“Qualquer dia do mês”, Mês `*`/“Qualquer mês”, Dia da semana `1-5`/“De segunda-feira a sexta-feira”; status “10 próximas execuções no fuso America/Sao_Paulo.”; primeira execução `sex., 27 de nov. de 2026, 09:00` |
| Cron inválido | `0 99 * * *` → `Valor fora do intervalo em Hora: 99.` no campo e no status |
| Cron impossível | `0 0 30 2 *` → aviso de horizonte esgotado, 0 execuções, sem travar a tela |
| cURL (Laravel) | `Http::withHeaders([...])` com `Content-Type` e `Http::withBody('<json>', 'application/json')->post('https://api.example.com/users')` |
| cURL (`fetch`/`axios`) | `fetch` com `method: 'POST'`, `headers` e `body`; `axios` com `auth`/`data` — abas trocadas com clique real |
| cURL — avisos | `-b` → “Opções não suportadas (ficaram de fora): -b.”; `-u ana:segredo` → “A credencial de ana foi parar no código…”, **sem** a senha; `-b 'sess=abc'` também gera “A URL sess=abc foi ignorada: vale a primeira.” (ver P3) |
| `.env` exemplo | saída preserva `# Aplicacao`, `APP_NAME=Demo`, linhas em branco, `# Banco`, `DB_HOST`, `# E-mail`; `APP_KEY=`, `DB_PASSWORD= # segredo`, `DATABASE_URL=` mascarados; aviso “Chaves com o valor removido: APP_KEY, linha 3 · DB_PASSWORD, linha 7 · DATABASE_URL, linha 8”; status “Removidas: 3 · Inválidas: 0” |
| Baixar `.env.example` | evento de download com o conteúdo exato da saída e `text/plain`; o nome declarado na página é `.env.example` (o Chromium/Playwright sugere `env.example` — ver P4) |
| Mobile (390×844) | `ToolShell` mostra as abas “Expressão”/“Próximas execuções”; a segunda aba exibe as 10 execuções |
| Cinco idiomas | títulos, status e botões conferidos em `en`, `pt-br`, `pt-pt`, `fr` e `es` nas três telas (ex.: “Cron explainer”, “cURL vers code”, “.env a ejemplo”; datas do cron no locale da interface) |
| Tema escuro | `html.dark`, fundo `rgb(11, 12, 15)`, texto `rgb(232, 233, 238)`; máscara e avisos legíveis |
| Rede | depois de digitar nas três tools: **zero** entradas `fetch`/`xmlhttprequest`/`beacon`/`ping` em `performance.getEntriesByType('resource')` |
| Sitemap | `/sitemap.xml` lista `tools/cron-explainer`, `tools/curl-to-code` e `tools/env-example` |
| OG | os três PNG medidos em 1200×1200 |

## Checklist manual

Executar depois do deploy candidato (ou build local servido); marcar cada item.

1. Abrir `/{locale}/tools/cron-explainer` nos cinco idiomas: nome, descrição, exemplo e rótulos
   traduzidos; o card da home e o sitemap listam a tool.
2. Testar `*/15 * * * *`, `0 9 * * 1-5`, `0 0 1 */3 *`, `30 4 1,15 * 5`, `@weekly` e nomes
   (`0 9 * JAN-MAR MON-FRI`): explicação campo a campo e 10 execuções no fuso exibido.
3. Testar `0 0 30 2 *` (aviso de horizonte) e expressões inválidas (`0 99 * * *`, `61 * * * *`,
   `@sometimes`, `* * * *`): erro por campo, sem quebrar a tela.
4. Copiar o relatório do cron e conferir fuso, linhas e datas no locale.
5. Abrir `/{locale}/tools/curl-to-code` e colar o exemplo; conferir as três abas e copiar cada uma.
6. Colar um comando com `-G`, `-d`, `--json`, `-u` e `-b`: query montada, `Content-Type` só quando
   ausente, aviso de credencial sem a senha e `-b` listado como não suportado.
7. Colar `curl -d @arquivo.json ...` e conferir a recusa; colar `-H 'Sem dois pontos'` e conferir o
   aviso; colar aspas sem par e conferir o erro com detalhe.
8. Abrir `/{locale}/tools/env-example`, carregar o exemplo e conferir máscara, ordem, comentários e
   avisos; baixar e conferir o conteúdo do arquivo.
9. Editar um `.env` com `export`, aspas, valor multi-linha, comentário em linha, CRLF e uma linha sem
   `=`: conferir a preservação e o aviso da linha inválida.
10. Mobile nas três telas: abas do `ToolShell` mostram entrada e saída certas.
11. Tema escuro e claro; as 17 tools anteriores sem mudança de comportamento.

## Desvios registrados e correções

| ID | Achado | Correção |
| --- | --- | --- |
| D1 | `env-example/lib/parse.ts` usava o caractere BOM literal dentro do regex, e o ESLint acusava `no-irregular-whitespace` | o regex passou a usar a escape sequence `\uFEFF`; ESLint e Prettier voltaram a passar no arquivo |
| D2 | Nenhum desvio de escopo: os três `.vue` reusam `ToolShell`, `ToolActionBar`, `ToolButton`, `CodeEditor`, `FileDropzone`, `lib/file`, `lib/download`, clipboard e os composables sem mudar componente compartilhado | — |

## Pendências

| ID | Pendência | Gravidade | Impacto | Evidência | Ação |
| --- | --- | --- | --- | --- | --- |
| P1 | `format:check` falha em 251 arquivos pré-existentes (o repositório inteiro) | Baixa | O gate não fica verde; os arquivos da F2 passam no Prettier | `npm run format:check` → 251 arquivos; nenhum caminho da F2 aparece; `prettier --check` direto nos da F2 → “All matched files use Prettier code style!” | Reformatação em massa num trabalho separado; a fase não pinta 251 arquivos alheios |
| P2 | 1147 avisos de lint (`vue/html-indent`) nos templates | Baixa | Gate sem erros, com avisos; a classe é anterior à fase | `npm run lint:check` → 0 erros, 1147 avisos | Ajuste de template num trabalho separado |
| P3 | `curl-to-code`: o valor de uma opção curta desconhecida (ex.: `-b 'sess=abc'`) também é interpretado como URL extra, gerando dois avisos | Baixa | Mensagem a mais, honesta (nada é ignorado); não altera o código gerado | navegador: “Opções não suportadas (ficaram de fora): -b.” + “A URL sess=abc foi ignorada: vale a primeira.” | Sem ação nesta fase: consumir o valor de opção desconhecida exigiria adivinhar a aridade de cada opção; registrado na ADR-181 (listar o que ficou de fora) |
| P4 | O Chromium sugere o nome `env.example` para o download `.env.example` (remove o ponto inicial do nome sugerido) | Baixa | O conteúdo e o tipo estão certos; o nome do arquivo no disco sai sem o ponto no Windows/Chromium | `download.suggestedFilename()` → `env.example`; o atributo `download` da página é `.env.example` (teste de tela pina `.env.example`) | Comportamento do navegador; nenhuma ação no código |
| P5 | `.playwright-mcp/` com as capturas é scratch local e não está no `.gitignore` | Baixa | Risco de commit acidental de evidência | `git status` mostra o diretório como não rastreado | Decidir se o diretório entra no `.gitignore`; o conteúdo útil foi copiado para o relatório |

## Deploy e rollback

- **Deploy**: caminho normal do cron (RUNBOOK §2). Não há migration, job, comando nem `.env`: as três
  tools são só código de cliente, i18n, manifestos e imagens OG. O deploy publica os assets novos e o
  sitemap passa a listar as três URLs.
- **Nenhum dado novo**: nada é persistido; desinstalar a superfície não deixa resíduo em banco ou
  storage.
- **Rollback**: voltar a release anterior; as tools somem do catálogo e do sitemap junto com o código.
  Não há estado a migrar de volta.
- **Desligar sem rollback**: não há flag; como não há dependência de servidor, o caminho é a release.

## Decisões não aprovadas (para o pedido)

Nenhuma decisão de produto precisou ser reaberta. As escolhas que o roadmap deixou em aberto (dialeto e
semântica do cron, fuso, núcleo de opções do cURL, heurística da máscara, preservação do `.env`,
ausência de worker e de dependência) foram fechadas no grill de 2026-11-25 e estão na ADR-181; o aceite
não acrescentou decisão nova. O `format:check` pré-existente (P1), os avisos de lint (P2) e o
comportamento do Chromium no nome do download (P4) são herdados do ambiente e estão registrados aqui.
