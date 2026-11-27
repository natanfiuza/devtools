# Relatório — Programa Futuro F2: CLI e ambiente

Data: 2026-11-27 (implementação de 2026-11-25; evidência de navegador em 2026-11-27)
Branch: `feature/futuro-fase-2`
Task: `futuro_fase_2`
Origem: `/grill-with-docs` com o pedido "Pode seguir para a proxima fase do Programa Futuro, a nova
branch já foi criada" (plano aprovado em
`docs/claude-code/plans/feature_futuro-fase-2/20261125_feature_futuro-fase-2_futuro_fase_2.md`).

## 1. Contexto

A F2 do Programa Futuro (ADR-179) é a Onda 2 — "CLI e ambiente", porte M: três tools pequenas de
texto → texto, 100% no navegador, que reusam a casca existente. Nada do escopo existia no
repositório: não havia parser de cron, tokenizador de shell nem geração de `.env.example`. O
`dragonmantank/cron-expression` do Composer é dependência transitiva do Laravel, roda no servidor e
usa a semântica da máquina — não servia a uma tool de cliente. Esta sessão entrega a fase inteira: as
três tools, registro, i18n nos cinco idiomas, SEO, testes, ADR-181, aceite, atualizações de
ROADMAP/SPEC/CHANGELOG/glossário, evidência no navegador e este relatório. Sem commit, push ou deploy.

## 2. Decisões fechadas no grill (2026-11-25, três rodadas)

| Tema | Decisão |
| --- | --- |
| `cron-explainer` — dialeto | Cinco campos; `*`, listas, intervalos, passos; nomes de mês/dia; macros `@yearly/@annually/@monthly/@weekly/@daily/@midnight/@hourly`. Sem segundos e sem Quartz (`L`, `W`, `#`, `?`) |
| `cron-explainer` — dia | Regra Vixie: quando dia-do-mês **e** dia-da-semana estão restritos, vale OR; `0` e `7` são domingo; `*` com passo não restringe |
| `cron-explainer` — fuso/lista | Fuso do navegador exibido na tela, sem seletor; próximas **10** execuções no locale da interface |
| `cron-explainer` — parser | Próprio, sem dependência; horizonte de oito anos com `exhausted` |
| `curl-to-code` — saídas | Laravel (`Http::...`), `fetch` e `axios`, em texto simples (sem destaque) |
| `curl-to-code` — opções | Núcleo `-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`, `--url`, `-u/--user` (aviso de credencial), `--json`; aspas e continuação `\`; o resto **listado** como não suportado |
| `env-example` — máscara | Segmento sensível do nome (`KEY`, `SECRET`, `PASSWORD`, `PASS`, `TOKEN`, `AUTH`, `CREDENTIAL`, `DSN`, `PRIVATE`, `PASSWD`, `PWD`) e URL com `user:senha@` → valor vazio + aviso com a linha |
| `env-example` — preservação | Ordem, comentários, linhas vazias, agrupamentos, aspas e `export ` intactos; linha sem `=` mantida e listada |
| Registro | `cron-explainer` em `time` 150/`calendar-clock`; `curl-to-code` e `env-example` em `text` 160/170 com `terminal` e `file-key` |
| Telas | Cron: `Input` simples + tabela de campos; cURL e `.env`: `CodeEditor` na entrada e `<pre>` na saída (cURL em abas) |
| Worker | Nenhum nas três |
| Ações | Exemplo e Copiar nas três; Baixar só no `env-example` |
| ADR | Um `ADR-181` cobrindo dialeto/semântica do cron, tokenizador e opções do cURL, heurística da máscara, telas/registro e "sem worker" |

## 3. Entregáveis

| # | Entregável | Arquivo | Situação |
| --- | --- | --- | --- |
| 1 | Levantamento do grill | `docs/survey/20261125_futuro_fase_2_surveyfacts.md` | criado |
| 2 | Plano da sessão | `docs/plans/feature_futuro-fase-2/20261125_futuro_fase_2_plansfacts.md` | criado |
| 3 | Cópia do plano (CLAUDE.md) | `docs/claude-code/plans/feature_futuro-fase-2/20261125_feature_futuro-fase-2_futuro_fase_2.md` | criado |
| 4 | Tool `cron-explainer` | `resources/js/tools/cron-explainer/` (8 arquivos) | criada |
| 5 | Tool `curl-to-code` | `resources/js/tools/curl-to-code/` (11 arquivos) | criada |
| 6 | Tool `env-example` | `resources/js/tools/env-example/` (7 arquivos) | criada |
| 7 | Registro e i18n | `icons.ts`, `lang/*/tools.php` (5), pins em `tools-registry.test.ts`/`ToolsRegistryTest.php` | atualizado |
| 8 | SEO | `public/og/{cron-explainer,curl-to-code,env-example}.png` (1200×1200) + `og-default.png` | gerado |
| 9 | Dependências | nenhuma; `package.json` e `composer.json` intactos | — |
| 10 | Testes | 11 arquivos novos em `resources/js/__tests__/` | criados |
| 11 | ADR | `docs/adr/ADR-181-futuro-fase-2-cli-ambiente.md` | criado |
| 12 | Aceite | `docs/acceptance/FUTURO-FASE-2.md` | criado |
| 13 | ROADMAP/SPEC/CHANGELOG/glossário | status F2 + revisão 1.2, §5/§9, entrada da fase, 4 termos | atualizados |
| 14 | Evidência | `evidence/` (6 capturas + o `.env.example` baixado) | criada |

## 4. Implementação

### 4.1 `cron-explainer`

- **Parser** (`lib/parse.ts`, 305 linhas): normaliza espaços, expande as sete macros para os cinco
  campos, lê `*`, listas, intervalos (`a-b`), passos (`*/n`, `a-b/n`) e nomes de mês/dia sem
  diferenciar maiúsculas; `7` vira `0` no domingo. Os erros são códigos (`CronErrorCode`) com o campo
  e o texto ofensor; macro com campos depois é erro, nunca soma silenciosa.
- **Descrição** (`lib/describe.ts`): descritores estruturados `any`, `step_star`, `step_range`,
  `range`, `at` — o motor descreve, o i18n fraseia (nada de frase pronta no código).
- **Próximas execuções** (`lib/next.ts`, 232 linhas): anda em parede lendo `Date` + `Intl` com o fuso
  explícito por parâmetro; hora que não existe no salto da primavera não casa, hora que se repete no
  retorno do outono usa o primeiro instante. Regra Vixie implementada por token inicial do campo
  (`*` e `*/n` não restringem). Horizonte de `366 * 8 + 31` dias; passado isso devolve `exhausted`.
- **Relatório** (`lib/report.ts`): fuso + as 10 execuções formatadas no locale, para copiar.
- **Tela** (`Tool.vue`, 405 linhas): `ToolShell` com `paneLabels`, `Input` mono para a expressão, tabela
  Campo/Valor/Significado, fuso exibido, lista das execuções e estados vazio/inválido/esgotado.

### 4.2 `curl-to-code`

- **Tokenizador** (`lib/tokenize.ts`, 163 linhas): aspas simples literais; aspas duplas com `\"`,
  `\\`, `\$`, `` \` ``; escape fora de aspas; `\` no fim da linha como continuação (LF e CRLF). Aspas
  sem par e barra pendurada são erro com posição (`shell_details` no i18n).
- **Parser** (`lib/parse.ts`, 331 linhas): primeiro token `curl` (ou caminho terminado em `curl`),
  valores anexados e flags agrupadas, método por `-X` > `-G` > corpo/`--json` > `GET`, `-G` move os
  `-d` para a query, `--json` só acrescenta o `Content-Type` ausente, `-d @arquivo` recusado, URL
  extra e cabeçalho sem `:` avisados, `-u` gera aviso de credencial com o usuário (a senha nunca
  aparece no aviso). Toda opção desconhecida entra em `unsupported`.
- **Emissores** (`lib/emit/{laravel,fetch,axios}.ts` + `emit.ts` + `strings.ts`): saídas
  determinísticas, pinadas por teste de string exata. Laravel monta `Http::withHeaders`,
  `->withBasicAuth` e `->withBody(<string PHP escapada>, <content-type>)` com verbo final; `fetch`
  com `method/headers/body` (sem `body` em GET); `axios.request({ url, method, headers, data })`.
- **Tela** (`Tool.vue`, 278 linhas): `CodeEditor` na entrada, banner de avisos, abas sobre `<pre>`,
  cópia da aba ativa, Exemplo e Limpar.

### 4.3 `env-example`

- **Parser** (`lib/parse.ts`, 258 linhas): linha a linha, guardando o texto cru: `export ` opcional,
  espaços, aspas simples/duplas, valor multi-linha entre aspas, comentário em linha depois do valor,
  CRLF; BOM tolerado com `\uFEFF`; linha sem `=` guardada como `invalid` com o número.
- **Máscara** (`lib/mask.ts`, 108 linhas): `isSensitiveKey` divide o nome em segmentos não
  alfanuméricos e compara em caixa alta com a lista aprovada; valor que é URL com `user:senha@`
  também mascara. O valor sensível vira vazio preservando `export `, chave, `=`, aspas e comentário;
  multi-linha vira uma linha; valor já vazio não entra no aviso.
- **Arquivos** (`lib/files.ts`): regras de nome (`.env`, `.env.*`, `*.env`) e `text/plain`, porque
  `.env` não tem extensão.
- **Tela** (`Tool.vue`, 310 linhas): `FileDropzone` + `CodeEditor` na entrada, `<pre>` na saída, banner
  com chaves (nome + linha) e linhas inválidas, Copiar e Baixar (`.env.example`, `text/plain`).

### 4.4 Registro, i18n e SEO

- `resources/js/tools/icons.ts`: `CalendarClock`, `Terminal` e `FileKey` importados e mapeados.
- `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`: blocos completos com `name/description/keywords/intro` e
  todas as strings de tela (campos, descritores, erros, abas, avisos, máscara, tipos de arquivo). O
  `@` não aparece em mensagem (é o sigilo de mensagem ligada do vue-i18n).
- Pins: `mvpToolSlugs()` do `ToolsRegistryTest` e a lista do sitemap no mesmo teste, mais o `MVP` do
  `tools-registry.test.ts`, agora com 20 slugs.
- OG: `php artisan seo:og-image` gerou os três PNG 1200×1200 e atualizou `og-default.png`; o sitemap
  lista as três URLs automaticamente pelo `available`.

## 5. Testes

| Arquivo | Cobre |
| --- | --- |
| `cron-parse.test.ts` | campos, listas, intervalos, passos, nomes, macros, 0/7, erros por código |
| `cron-next.test.ts` | próximas execuções, OR Vixie, passo, 29/02, DST, horizonte esgotado |
| `cron-describe.test.ts` | descritores por tipo de campo |
| `cron-tool.test.ts` | campo, tabela, fuso, lista, erro, esgotado, copiar, exemplo, i18n |
| `curl-tokenize.test.ts` | aspas, escapes, continuação LF/CRLF, valores anexados, erros |
| `curl-parse.test.ts` | método, headers, corpo, `-G`, `--json`, `-u`, não suportadas, `-d @arquivo`, erros |
| `curl-emit.test.ts` | saídas exatas das três abas |
| `curl-tool.test.ts` | abas, banner, cópia da aba ativa, i18n |
| `env-parse.test.ts` | `export`, aspas, multi-linha, CRLF, comentário em linha, inválida |
| `env-mask.test.ts` | segmentos sensíveis (positivos e negativos), preservação, contagens, URL com credencial |
| `env-tool.test.ts` | entrada, banner, cópia, download `.env.example`, regras de arquivo, i18n |

Medido em 2026-11-27: **11 arquivos, 131 testes, 0 falhas**. Os testes de motor usam dados fixos; os
de tela usam o `CodeEditor` stub, `langMessages` e `data-test` em cada nó relevante. As datas do cron
são construídas a partir dos componentes locais e o caso de DST é verificado com `Intl` no fuso do
processo, para não depender da máquina.

## 6. Evidência no navegador

Chromium real do Playwright contra `php artisan serve` em `127.0.0.1:8123`, build de produção, URLs
com o slug de locale (`/pt-br/...`). Capturas copiadas para
`docs/claude-code/reports/feature_futuro-fase-2/evidence/`:

| Arquivo | Conteúdo |
| --- | --- |
| `f2-cron.png` | cron válido `0 9 * * 1-5` com tabela, fuso e 10 execuções |
| `f2-cron-mobile.png` | tela a 390×844 com as abas “Expressão”/“Próximas execuções” |
| `f2-cron-mobile-output.png` | a segunda aba no mobile com as execuções |
| `f2-curl.png` | avisos reais: `-b` não suportado, credencial de `ana` no código, URL extra |
| `f2-env.png` | exemplo com três chaves mascaradas e aviso com linha |
| `f2-env-dark.png` | o mesmo no tema escuro |
| `env.example` | arquivo baixado pela tela, com o conteúdo preservado |

Resultados observados:

- **Cron**: válido mostra a leitura campo a campo e `sex., 27 de nov. de 2026, 09:00` como primeira
  execução; `0 99 * * *` mostra `Valor fora do intervalo em Hora: 99.`; `0 0 30 2 *` mostra o aviso
  de horizonte esgotado com 0 execuções, sem travar.
- **cURL**: Laravel com `Content-Type` e `Http::withBody(...)->post(...)`; `fetch` e `axios` com método,
  cabeçalhos e corpo certos (abas trocadas com clique real). Avisos: “Opções não suportadas (ficaram
  de fora): -b.”, “A credencial de ana foi parar no código…”, “A URL sess=abc foi ignorada…”.
- **`.env`**: saída preserva comentários, ordem e linhas em branco; `APP_KEY=`, `DB_PASSWORD= # segredo`
  e `DATABASE_URL=` mascarados; aviso lista `APP_KEY, linha 3`, `DB_PASSWORD, linha 7` e
  `DATABASE_URL, linha 8`; status “Removidas: 3 · Inválidas: 0”.
- **Download**: evento com o conteúdo exato da saída e tipo `text/plain`; a página declara
  `.env.example` e o Chromium sugere `env.example` (remove o ponto inicial — comportamento do
  navegador, registrado como P4 no aceite).
- **Mobile**: as abas do `ToolShell` mostram entrada e saída certas.
- **Cinco idiomas**: `en`, `pt-br`, `pt-pt`, `fr` e `es` conferidos nas três telas, com datas do cron
  no locale da interface.
- **Tema escuro**: `html.dark`, fundo `rgb(11, 12, 15)`, texto `rgb(232, 233, 238)`.
- **Privacidade**: depois de digitar nas três tools, nenhuma entrada `fetch`/`xmlhttprequest`/
  `beacon`/`ping` em `performance.getEntriesByType('resource')`.
- **Sitemap**: `/sitemap.xml` lista as três URLs; os três OG medem 1200×1200.

## 7. Gates reais

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formatação PHP | `vendor/bin/pint --parallel --test` | verde, exit 0 |
| Análise estática PHP | `vendor/bin/phpstan analyse` | 0 erros, exit 0 |
| Suíte PHP | `php artisan test` | 1857 testes, 1855 passaram, 2 skipped, 34638 asserções, exit 0, 181 s |
| Suíte JS | `npx vitest run` em três shards com `--maxWorkers=2` | 184 arquivos, 2218 testes, 0 falhas |
| Tipos | `npm run types:check` | exit 0 |
| Formatação | `npm run format:check` | exit 1 em 251 arquivos pré-existentes; **nenhum da F2** |
| Lint | `npm run lint:check` | exit 0, 0 erros, 1147 avisos |
| Build | `npm run build` | exit 0, `✓ built in 16.72s`; chunks 11,47 / 9,86 / 7,35 kB (gzip 4,46 / 3,81 / 3,03) |

Observações: a suíte Pest foi rodada por `php artisan test` (mesmo comando do script) por causa do
timeout de 300 s do Composer; o Vitest foi dividido em shards por RAM, como na F1 — cada arquivo
falhou somente quando executado junto com os montes pesados do Layout Harmonizer, nunca isolado.

## 8. Desvios e correções

| ID | Achado | Correção |
| --- | --- | --- |
| D1 | `env-example/lib/parse.ts` usava o caractere BOM literal dentro do regex e o ESLint acusava `no-irregular-whitespace`; a edição pela ferramenta normalizava o caractere e a troca não pegava | o regex passou a usar a escape sequence `\uFEFF`, gravada por código de caractere; ESLint e Prettier voltaram a passar |
| D2 | O primeiro teste de clique nas abas do cURL usou `dispatchEvent(new MouseEvent('click'))` e não trocou a aba | a evidência foi refeita com o clique real do Playwright; o componente já usava reka-ui corretamente |
| D3 | Nenhum desvio de escopo: `ToolShell`, `ToolActionBar`, `ToolButton`, `CodeEditor`, `FileDropzone`, `lib/file`, `lib/download`, clipboard e composables usados sem mudança | — |

## 9. Riscos e pendências

| ID | Pendência | Gravidade | Impacto | Ação |
| --- | --- | --- | --- | --- |
| P1 | `format:check` falha em 251 arquivos pré-existentes | Baixa | Gate não fica verde; os arquivos da F2 passam | Reformatação em massa num trabalho separado |
| P2 | 1147 avisos de lint (`vue/html-indent`) | Baixa | Gate sem erros; classe anterior à fase | Ajuste de template num trabalho separado |
| P3 | O valor de opção curta desconhecida (`-b 'sess=abc'`) também vira "URL extra", gerando dois avisos | Baixa | Mensagem a mais, honesta; código gerado correto | Sem ação: consumir o valor exigiria adivinhar a aridade de cada opção; registrado na ADR-181 |
| P4 | O Chromium sugere `env.example` para o download `.env.example` | Baixa | Conteúdo e tipo corretos; nome sai sem o ponto inicial | Comportamento do navegador; o teste pina `.env.example` |
| P5 | `.playwright-mcp/` não está no `.gitignore` | Baixa | Risco de commit acidental de evidência | Decidir num trabalho separado; cópias úteis estão no relatório |

Riscos residuais: o cálculo do cron depende do `Intl` do navegador (cobre IANA e DST, como o
`timestamp-uuid` já faz); expressões impossíveis param no horizonte; a heurística do `.env` erra pelos
casos documentados (falso positivo `AUTHOR`, falso negativo `APIKEY`) e o aviso com a linha permite
conferir. Nenhum segredo entra na página: o `.env` e o comando cURL ficam só no estado do componente.

## 10. Verificação de git e parada

- Branch atual: `feature/futuro-fase-2` (criada pelo usuário; o agente não cria, não commita, não
  faz push nem deploy).
- A árvore contém só o escopo da fase: as três pastas de tool, 11 testes, `icons.ts`, os cinco
  `lang/*/tools.php`, os dois pins de registro, os três OG (+ `og-default.png`), ADR-181, aceite,
  ROADMAP/SPEC/CHANGELOG/glossário e os artefatos de survey/plano/relatório. Fora do escopo há um
  `.gitpr/reports/pr_desc/feature-futuro-fase-1_*.md` não rastreado de sessão anterior e o scratch
  do `.playwright-mcp/`.
- **Nenhum commit, push ou deploy foi feito.** O usuário revisa e commita pelo gitpr.

Próximo passo sugerido: revisar o diff, rodar `git add` dos caminhos acima (deixando de fora o
`.playwright-mcp/` e o arquivo do `.gitpr/`) e abrir o PR pelo gitpr. A F3 do programa (banco e
schema) começa em branch própria, com prompt e grill próprios.
