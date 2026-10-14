# F3 - tools simples (3 a 8 do MVP), com o bloco 0 de deploy

Sessao de grill (skill `grill-with-docs`), pedido: "execute a proxima fase 3".
Branch `develop_natan`. Tres rodadas de perguntas (**Q1 a Q23**), todas
respondidas pelo usuario, e o plano aprovado antes da execucao.

O **bloco 0** entrou no meio da sessao: o usuario relatou que a producao esta
mostrando chave de traducao crua na home. Ele virou a primeira coisa a fazer,
antes de qualquer linha da F3.

## Context

A F2 fechou em 2026-10-14 (`edb62b2`) com a pagina generica da tool e o JSON
Viewer funcionando; a revisao independente dela entrou em `1d556ee`. A F3 e a
proxima fase da secao 9 do SPEC: "Tools simples (3 a 8 do MVP)" - seis
ferramentas que hoje existem so como `manifest.json` com `status: "soon"`
(`json-to-code`, `sql-formatter`, `base64-url-hash`, `jwt-decoder`,
`timestamp-uuid`, `regex-tester`). A F2 deixou o molde pronto de proposito
(ADR-029): a fase seguinte acrescenta um `Tool.vue` por diretorio e vira o
status.

**O desvio.** A producao esta servindo **JavaScript anterior a F1**: a home
mostra `app.actions.search_placeholder`, `home.search_hint`, `home.status_title`,
`home.status_body`, `home.ai_title` e `home.ai_body` - strings que nasceram em
`172aea0` e foram removidas em `1f00431`. Os arquivos de idioma no servidor sao
os de hoje; o bundle nao. O `deploy.sh` publica assets **depois** do `git pull` e
do `composer install`, e o `set -euo pipefail` mata o script nesse bloco: o PHP
atualiza, o JS nao. Como o pull ja moveu o HEAD, o cron nao retenta - os dois
ultimos deploys (F1 e F2) falharam no mesmo ponto e nenhum se recuperou sozinho.
`migrate`, `optimize`, `queue:restart` e o reinicio do SSR tambem nao rodaram;
nenhum dos dois commits traz migration, entao nao ha risco de banco.

**Resultado esperado**: producao publicando de novo, e as seis tools
`available` - 7 paginas de tool no sitemap, 7 cards linkados na home, 7 imagens
OG, tudo rodando no navegador.

## Bloco 0 - destravar o deploy (branch propria, curta)

| # | Passo | Estado |
|---|---|---|
| 1 | Diagnostico pelo `deploy.log` do servidor | **bloqueado no usuario** |
| 2 | Parar de versionar o `package-lock.json` (deploy.sh + ADR-014) | **feito** - falta o commit, que e do usuario |
| 3 | Corrigir a causa que o log apontar | a fazer, esperando o passo 1 |
| 4 | Publicar (commit do usuario -> PR -> merge em `main` -> cron) e conferir | a fazer |
| 5 | Trazer o `main` de volta para a `develop_natan` | a fazer |

O passo 1 depende de um log que so o usuario alcanca:

```bash
tail -150 /var/www/devtools/storage/logs/deploy.log
ls -l /var/www/devtools/public/build /var/www/devtools/bootstrap/ssr
ls -1dt /var/www/devtools/storage/app/builds/* | head -5
cat /var/www/devtools/storage/framework/npm-lock.sha256
node -v && npm -v
```

Hipoteses, com o que ja foi verificado **daqui** (o `deploy.log` do servidor
segue sendo o que decide):

- **(a) descartada**: o comando exato de build do script, com o `--outDir` da
  release, roda localmente e termina com exit 0 - o `laravel-vite-plugin` nao
  recusa o caminho, e `storage/app/builds` esta dentro do root do projeto.
- **(b) descartada**: `npm ci --dry-run` com o `package.json` e o
  `package-lock.json` do HEAD instala 422 pacotes sem erro; os dois mudaram
  juntos em `edb62b2` e o lock esta completo, com o binding
  `@rolldown/binding-linux-x64-gnu@1.2.13` (o Vite 8 usa **Rolldown**; os
  `@rollup/rollup-*` fixados em 4.9.5 no `package.json` sao pinos orfaos).
- **(c) a candidata que sobrou**: o plugin `fonts` do `laravel-vite-plugin`
  baixa as duas familias do Bunny por `fetch` quando nao tem cache, e o cache
  mora em `node_modules/.cache/laravel-vite-plugin/fonts` - que o `npm ci` apaga
  junto com o `node_modules` inteiro.
- So o log separa: `composer install`, o build do SSR, a versao do Node no VPS
  (o `package.json` nao tem `engines` e o script nao confere) e a troca de
  symlink.

**Correcao de uma leitura anterior**: o mecanismo de release por symlink **nao**
entrou em `b9a7f78` - existe desde `172aea0`, e o `b9a7f78` so moveu o arquivo do
`flock` (verificado em `git show 172aea0:deploy/deploy.sh` e no diff do
`b9a7f78`). Isso levanta a hipotese de que **o cron nunca publicou nada** e o
`public/build` servido e um build manual da subida do VPS, na epoca do `172aea0`
- o que explicaria por que a quebra so aparece agora, quando o PHP (com os
arquivos de idioma atuais) se descolou do JS antigo. Um `public/build` que seja
**diretorio de verdade** em vez de symlink confirma isso de imediato.

O passo 2 e a decisao Q23 e nao depende do log:

- `deploy/deploy.sh`: o gatilho de dependencias passa de `sha256sum
  package-lock.json` para `sha256sum package.json`, com o arquivo de estado
  renomeado para `storage/framework/npm-deps.sha256` (o `.gitignore` de
  `storage/framework` ja cobre), e `npm ci` vira `npm install --no-audit
  --no-fund`.
- `docs/adr/ADR-014-deploy.md`: reescrever a parte de dependencias de front e
  registrar a consequencia assumida - **o build no servidor deixa de ser
  reproduzivel**, porque o `package.json` usa faixas (`^`) e dois deploys do
  mesmo commit podem instalar versoes diferentes.
- O `.gitignore` (que ja ganhou **duas** linhas no working tree, sem commit:
  `package-lock.json` e `composer.lock`) e a remocao do `package-lock.json` do
  rastreamento entram no commit desta branch.
- **`composer.lock` tambem esta saindo do git** (remocao preparada no indice,
  arquivo ainda no disco). Isso tem peso proprio no deploy: `composer install`
  **sem lockfile nao instala do lock** - ele resolve a arvore na hora e escreve
  um lock novo, o que torna o lado PHP tao pouco reproduzivel quanto o do npm e
  deixa uma atualizacao de dependencia entrar sem revisao.
- **Decidido (2026-10-14): "Deixar `composer install`"** - sem lockfile ele ja
  resolve sozinho e escreve o lock novo; o script ganhou so um comentario
  explicando o comportamento, e a consequencia esta no ADR-014.
- **Decidido (2026-10-14): a falha no meio do deploy fica "So documentar"** -
  o comportamento atual nao muda. Uma falha antes da troca do symlink deixa o
  **PHP novo com o JS velho**, sem `migrate`, `optimize`, `queue:restart` nem
  reinicio do SSR, e o cron **nao retenta** (o `git pull` ja moveu o HEAD). O
  sintoma, o diagnostico e o conserto manual ficam no ADR-014 e numa secao nova
  do `docs/RUNBOOK.md`. E o mesmo mecanismo do bug de producao desta sessao.

## F3 - blocos de execucao

| # | Bloco | Estado |
|---|---|---|
| 1 | Base compartilhada: aceite, ajudantes extraidos, i18n do shell, limites do regex, dependencias | a fazer |
| 2 | `json-to-code` (JSON -> TypeScript / PHP DTO / Pydantic) | a fazer |
| 3 | `sql-formatter` (MySQL, PostgreSQL, SQL Server, SQLite) | a fazer |
| 4 | `base64-url-hash` | a fazer |
| 5 | `jwt-decoder` | a fazer |
| 6 | `timestamp-uuid` | a fazer |
| 7 | `regex-tester` (worker com tempo limite) | a fazer |
| 8 | Virada de status das seis, imagens OG, `hidden` alinhado | a fazer |
| 9 | Gates, smoke, docs, aceite e relatorio | a fazer |

### Bloco 1 - base compartilhada

- `docs/acceptance/FASE-3.md` no formato da FASE-2 (escopo, tabela
  `| # | Criterio | Como verificar |`, fora de escopo, resultado a preencher).
- Extrair para `resources/js/lib` e `composables` o que **duas ou mais** tools
  usam (Q8): copiar para a area de transferencia, abrir arquivo + arrastar-e-
  soltar (hoje inline no `json-viewer/Tool.vue`), `downloadText` e `formatBytes`
  (hoje em `json-viewer/lib/`), e um wrapper de CodeMirror parametrizado por
  linguagem (`CodeEditor.vue` + `useCodeEditor`) no lugar do `JsonEditor.vue`.
  O `json-viewer` passa a consumir os mesmos ajudantes; arvore, caminho,
  transformacao e parse **ficam** onde estao.
- Generalizar `tools.ui.*` nos 5 idiomas (Q7): o texto do shell deixa de falar de
  JSON (`states.empty`, `states.file_type`, `states.file_unreadable`,
  `states.too_large`, `states.drop_hint`, `actions.*`). O erro com linha e coluna
  continua sendo do JSON Viewer.
- `config/tools.php` + `.env.example`: `TOOLS_REGEX_TIMEOUT_MS` (padrao 2000) e
  `TOOLS_REGEX_MAX_MATCHES` (padrao 1000), descendo por prop como o teto de
  entrada.
- Dependencias: `sql-formatter` e `@codemirror/lang-sql`.

### Bloco 2 - `json-to-code`

`lib/infer.ts` (JSON -> modelo de tipos), `lib/emit/{typescript,php,pydantic}.ts`
e `lib/names.ts` (identificador valido, chave que precisa de aspas). Alvo por
`Select` no cabecalho da saida; campo do **nome da raiz** (padrao `Root`) ao
lado, usado literal nas tres saidas (Q11). Inferencia completa (Q12): campo
ausente em parte do array vira opcional, array misto vira uniao, `null` vira
anulavel, inteiro e decimal se distinguem, array vazio e objeto vazio tem tipo
proprio. PHP: `final class`, `declare(strict_types=1)` e promovidas
`public readonly`. Entrada invalida mantem a ultima saida valida (Q10).

### Bloco 3 - `sql-formatter`

`lib/dialects.ts` com os slugs do SPEC (`mysql`, `pgsql`, `sqlsrv`, `sqlite` - o
vocabulario do glossario) mapeados para os nomes da biblioteca; `lib/format.ts`
envolvendo o `sql-formatter` e traduzindo o erro dele para linha e coluna. So a
caixa das palavras-chave e configuravel (padrao MAIUSCULAS), indentacao fixa em
2, dialeto padrao `mysql`, sem memoria do ultimo (Q13).

### Bloco 4 - `base64-url-hash`

Abas de modo no painel de saida (`Base64` | `URL` | `Hash`); a **direcao**
(codificar / decodificar) fica no cabecalho da **entrada** (Q14). `lib/base64.ts`
(UTF-8 de verdade, sem `btoa` cru; variante URL-safe sem padding), `lib/url.ts`
(componente e formulario `+`), `lib/md5.ts` (proprio, com os vetores do RFC 1321)
e `lib/hash.ts` (Web Crypto para SHA-1/256/512). A aba Hash mostra **os quatro de
uma vez**, uma linha cada, hex minusculo, com copiar; sem HMAC (Q15).

### Bloco 5 - `jwt-decoder`

`lib/decode.ts`: split, base64url, JSON e claims de tempo. Tolerar o prefixo
`Bearer` colado (Q16). A saida sao dois blocos (cabecalho e payload) com as cores
`--json-*`, `exp`/`iat`/`nbf` como data legivel no idioma e "expirado" em
`--destructive`, e a assinatura so como comprimento em bytes. **Faixa fixa no
topo da saida** dizendo que a ferramenta decodifica e nao valida a assinatura
(borda `--accent/40`: aviso, nao erro).

### Bloco 6 - `timestamp-uuid`

`lib/time.ts` (unidade detectada pela quantidade de digitos, aceita texto de
data; saida em linhas com Unix s, Unix ms, ISO UTC, ISO local, data por extenso
no idioma e tempo relativo) e `lib/ids.ts` (UUID v4, v7 e ULID proprios, com a
decodificacao de um v7/ULID colado de volta - Q18). Gerar 1, 5 ou 10 com "copiar
todos"; botao "Agora" na entrada (Q17).

### Bloco 7 - `regex-tester`

O **padrao vai na barra de acoes**, em linha inteira, com as flags (`i`, `m`,
`s`, `u`) como alternadores ao lado; `g` fica sempre ligado e `y` fica fora. E a
unica vez que a F3 estica a convencao do shell, e o porque vai para o DESIGN
(Q19). O texto de teste fica no painel de entrada; a saida traz a lista de
matches (indice, trecho, posicao, grupos numerados e nomeados), o match realcado
no texto e o campo de substituicao com preview (`$1`, `$<nome>`) (Q20).
`lib/regex.worker.ts` + host com `terminate` ao estourar `TOOLS_REGEX_TIMEOUT_MS`;
ao estourar, mensagem e **nenhum** resultado. Teto de matches com aviso de corte.
O realce e o primeiro corte se o orcamento apertar.

### Bloco 8 - status e SEO

`status: "available"` nas seis; `php artisan seo:og-image` gera os 6 PNGs novos
em `public/og/` (comitados, ADR-030); `Tools::all()` passa a **excluir
`hidden`**, alinhado ao leitor TypeScript (SPEC secao 4), com teste.

### Bloco 9 - gates, docs e aceite

Os cinco gates, smoke com SSR nos 5 idiomas das 7 paginas, sitemap com 45 URLs,
`docs/acceptance/FASE-3.md` fechado e o relatorio da tarefa.

## Decisoes confirmadas pelo usuario

| # | Decisao |
|---|---|
| Q1 | As seis tools numa fase, com **uma parada** para revisao |
| Q2 | Todas usam o shell; listas de produto por `Select`/`Tabs` vendorizados no cabecalho do painel; abas de modo so no `base64-url-hash` (dentro da saida); `timestamp-uuid` fica no shell, com a acao "Agora" e os geradores escrevendo na saida |
| Q3 | `sql-formatter` como unica dependencia nova de runtime; **MD5 proprio** (Web Crypto nao tem MD5) |
| Q4 | Worker so no Regex Tester, com encerramento por tempo limite |
| Q5 | Listas de produto (dialetos, alvos de codigo, algoritmos) como **constantes tipadas do modulo**; limites so em `config/tools.php` |
| Q6 | `hidden` alinhado agora em `Tools::all()`, com teste |
| Q7 | Strings do shell generalizadas nos 5 idiomas; o erro com linha/coluna continua do JSON Viewer; ajustar `json-viewer.test.ts` |
| Q8 | Ajudantes usados por 2+ tools extraidos para `lib`/`composables`; `@codemirror/lang-sql` entra |
| Q9 | Correcao do deploy como **bloco 0 da F3**, com diagnostico antes |
| Q10 | "Ultima saida valida" repetida em `json-to-code` e `sql-formatter`; o glossario generaliza a entrada que hoje e so da arvore |
| Q11 | `json-to-code`: nome de raiz/tipo unico e literal (padrao `Root`), sem sufixos; PHP DTO = `final class` + `declare(strict_types=1)` + promovidas `public readonly` |
| Q12 | `json-to-code`: inferencia completa (opcionais, unioes, anulaveis, int x float, vazios) |
| Q13 | `sql-formatter`: so a caixa das palavras-chave (padrao MAIUSCULAS), indentacao fixa em 2, dialeto padrao `mysql`, sem memoria |
| Q14 | `base64-url-hash`: direcao no cabecalho da **entrada**; variantes URL-safe sem padding e component/form entram |
| Q15 | `base64-url-hash`: os quatro hashes de uma vez (MD5, SHA-1, SHA-256, SHA-512), hex minusculo, com copiar; sem HMAC |
| Q16 | `jwt-decoder`: tolera o prefixo `Bearer` colado; `exp`/`iat`/`nbf` com data no idioma e "expirado" em `--destructive`; faixa fixa de aviso no topo da saida; assinatura so como comprimento em bytes |
| Q17 | `timestamp-uuid`: aceita digitos (unidade por quantidade de digitos) e texto de data; Unix s/ms, ISO UTC, ISO local, data por extenso e tempo relativo na saida; geradores v4 + v7 + ULID, quantidade 1/5/10 com "copiar todos" |
| Q18 | `timestamp-uuid`: reconhece ULID / UUID v7 colado e mostra o instante embutido |
| Q19 | `regex-tester`: o **padrao vai na barra de acoes**, em linha inteira, com as flags (`i`, `m`, `s`, `u`) como alternadores; `g` sempre ligado, `y` fora; excecao documentada no DESIGN |
| Q20 | `regex-tester`: lista de matches, realce no texto e campo de substituicao com preview; dois limites novos em `config/tools.php` (tempo do regex e teto de matches, com aviso de corte); no estouro de tempo, **nenhum** resultado |
| Q21 | A correcao do deploy vive em **branch propria curta** -> PR -> `main` -> o cron publica; `develop_natan` recebe o `main` de volta |
| Q22 | **Emendar o ADR-029** + **ADR-031** novo (modelo de seguranca do Regex Tester) |
| Q23 | **Parar de versionar o `package-lock.json`**: sai do git e o `deploy.sh` troca `npm ci` por `npm install` com gatilho no hash do `package.json`; ADR-014 reescrito com a consequencia (o build deixa de ser reproduzivel) |

Do domain-modeling: adotar **"Linguagem de destino"** para as escolhas do
`json-to-code`, reservando o termo "Output target" do glossario para a tabela
`output_targets` do AI SQL Builder (que e outra coisa), e acrescentar ao
glossario o limite de tempo do regex, o teto de resultados e "Ultima saida
valida" generalizando "Arvore defasada".

## Arquivos criticos

**Novos** - `resources/js/tools/<slug>/Tool.vue` e seus `lib/` para as seis
tools; `resources/js/components/tool/CodeEditor.vue` e
`resources/js/composables/useCodeEditor.ts`; os ajudantes extraidos em
`resources/js/lib/`; `docs/acceptance/FASE-3.md`; `docs/adr/ADR-031-*.md`;
`public/og/*.png` (6).

**Alterados** - `config/tools.php`, `.env.example`, `app/Support/Tools.php`
(`hidden`), os seis `manifest.json`, `lang/*/tools.php` (5 idiomas), o
`json-viewer` (passa a consumir os ajudantes extraidos),
`docs/{SPEC.md,glossario.md,design/DESIGN.md,CHANGELOG.md}`,
`docs/adr/ADR-029-*.md` (emenda), e, no bloco 0, `deploy/deploy.sh`,
`docs/adr/ADR-014-deploy.md` e `.gitignore`.

**Testes que afirmam "so o json-viewer e available"** -
`tests/Feature/Tools/ToolsRegistryTest.php`, `tests/Feature/SitemapTest.php`,
`tests/Feature/SeoMetaTest.php`,
`resources/js/__tests__/tools-registry.test.ts`,
`resources/js/__tests__/home.test.ts` e `resources/js/__tests__/json-viewer.test.ts`.

## Riscos / pontos de atencao

- **O bloco 0 esta bloqueado** no log do servidor; sem ele, o passo 3 e hipotese.
- **Extrair os ajudantes mexe em codigo ja commitado** - o `json-viewer.test.ts`
  e a rede de seguranca; rodar a suite dele isolada antes de seguir.
- **`TranslationsParityTest` rejeita chave sobrando** - generalizar `tools.ui.*`
  exige tirar as chaves orfas nos 5 idiomas, nao so renomear.
- **`ToolsRegistryTest` le o `icons.ts` por expressao regular** - uma tool com
  icone fora do mapa quebra ali.
- **CodeMirror e `sql-formatter` no bundle**: entram por glob lazy, como na F2;
  conferir o `build:ssr` logo depois de instalar os pacotes.
- **O jsdom nao cobre CodeMirror, arrasto de ponteiro nem Worker**: as seis
  tools precisam de conferencia no navegador de verdade antes do aceite.
- **Nenhum commit**: o usuario commita via gitpr, cada bloco para para revisao.

## Verificacao

1. `composer test` (Pint + PHPStan nivel 7 + Pest), `npm run test`,
   `npm run types:check`, `npm run build` e `npm run build:ssr`.
2. Smoke com `INERTIA_SSR_ENABLED=true` nas 7 paginas de tool x 5 idiomas:
   title, description, canonical, hreflang e `og:image` no HTML do servidor;
   404 para `soon`, `hidden` e slug desconhecido.
3. Sitemap com 45 URLs (9 paginas x 5 idiomas).
4. No navegador: cada uma das seis tools com um caso feliz e um caso de erro, e
   o Regex Tester com um padrao catastrofico para ver o tempo limite agir.
5. Nenhuma requisicao ao servidor: `grep` por `fetch`/`axios` nos modulos das
   tools (regra do CLAUDE.md).
6. Bloco 0: `ls -l public/build` (symlink novo), `deploy.log` com o fecho do
   deploy e a home traduzida em producao.
