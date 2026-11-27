# ADR-181 — F2 do Programa Futuro: CLI e ambiente
Data: 2026-11-25
Status: Aceito

## Contexto

A F2 do Programa Futuro (ADR-179) entrega três tools 100% de cliente — `cron-explainer`,
`curl-to-code` e `env-example` — na Onda 2 "CLI e ambiente". Nenhuma delas tem equivalente no
catálogo: o `timestamp-uuid` formata datas, mas não interpreta agendamento; nada converte comando de
terminal em código; e o `share-text` compartilha texto, mas não prepara um `.env.example`.

O roadmap deixou em aberto o dialeto do cron, a semântica quando dia-do-mês e dia-da-semana discordam,
o conjunto de opções do cURL que a tool entende, como reconhecer um valor sensível no `.env`, o que
fazer com o que não é suportado e se alguma das três precisa de worker. As decisões abaixo foram
fechadas no grill de 2026-11-25 (três rodadas, registradas em
`docs/survey/20261125_futuro_fase_2_surveyfacts.md`) e não reabrem decisão de produto.

Fatos do levantamento que pesaram:

- a infra de cliente já existe e cobre as três telas: `ToolShell` (com `paneLabels` desde a F1),
  `ToolActionBar`/`ToolButton`, `CodeEditor`, `FileDropzone`, `lib/file`, `lib/download`,
  `lib/clipboard` e os composables de cópia e de erro de arquivo;
- não há biblioteca de datas no projeto (`date-fns`/`dayjs`/`luxon` ausentes); o `timestamp-uuid` já
  usa `Date` + `Intl` com o locale da interface, e o `Intl` resolve fuso e horário de verão;
- o `dragonmantank/cron-expression` do Composer é dependência transitiva do Laravel, roda no
  servidor e usa a semântica da máquina — não serve a uma tool de navegador;
- o `CodeMirror` só tem as gramáticas `json`, `markdown` e `sql`; `lang-javascript` existe apenas como
  transitiva do markdown e não será importada;
- o registro exige manifesto, ícone, categoria, i18n nos cinco idiomas, OG 1200×1200 e os pins de
  `ToolsRegistryTest`/`tools-registry.test.ts` na mesma etapa.

## Decisão

### `cron-explainer` — dialeto e semântica

- **Cinco campos**: minuto, hora, dia do mês, mês, dia da semana. Sem segundos e sem as extensões
  Quartz (`L`, `W`, `#`, `?`); um campo a mais ou a menos é erro nomeado.
- **Sintaxe**: `*`, listas (`1,15`), intervalos (`1-5`), passos (`*/n`, `a-b/n`) e nomes de mês e de
  dia da semana (sem diferenciar maiúsculas). `0` e `7` são domingo; o motor normaliza `7` para `0`.
- **Macros**: `@yearly`/`@annually`, `@monthly`, `@weekly`, `@daily`/`@midnight`, `@hourly`. A macro
  vale sozinha; campos depois dela são erro, nunca uma soma silenciosa.
- **Semântica Vixie**: quando dia-do-mês **e** dia-da-semana estão restritos (o campo não começa com
  `*`), a data casa se **qualquer um** dos dois casar — a regra do cron de longa data. `*` com passo
  (`*/2`) continua sendo `*` para essa pergunta; só conta como restrição o campo que começa com outro
  token.
- **Fuso**: o da máquina de quem está vendo, lido de `Intl` na montagem e exibido na tela; não há
  seletor. São listadas as **10** próximas execuções, no locale da interface.
- **Parser próprio**, sem dependência. A explicação da tela sai de descritores **estruturados**
  (`any`, `step_star`, `step_range`, `range`, `at`): o motor descreve, o i18n fraseia.

O cálculo (`lib/next.ts`) anda em **parede**, não em instantes: o calendário do cron é local, e um
`Date` é um instante. O motor converte parede ↔ instante por `Intl` com o fuso explícito, por
parâmetro — o teste passa `UTC` ou um fuso com horário de verão e pina instantes absolutos. As duas
armadilhas do DST saem resolvidas pela conversão: hora que não existe (salto da primavera) não casa;
hora que se repete (retorno do outono) usa o primeiro instante. O horizonte de busca é de oito anos
(366 × 8 + 31 dias, um ciclo de bissexto inteiro), suficiente para o 29 de fevereiro; passado isso, a
busca para e devolve `exhausted`, que a tela avisa — nenhuma expressão impossível gira para sempre.

### `curl-to-code` — o que é suportado

- **Tokenizador próprio de shell**: aspas simples literais, aspas duplas com `\"`, `\\`, `\$` e
  `` \` ``, escape fora de aspas e `\` no fim da linha como continuação (LF e CRLF). Aspas sem par e
  barra pendurada são erro com posição, não uma leitura adivinhada.
- **Núcleo**: `-X/--request`, `-H/--header`, `-d/--data/--data-raw/--data-binary`, `-G/--get`,
  `--url`, `-u/--user` e `--json`, inclusive valores anexados (`-XPOST`, `-H'...'`) e flags
  agrupadas (`-sS`). O resto é **listado** como não suportado, nunca ignorado em silêncio.
- **Regras de leitura**: o método é `-X` > `-G` > corpo/`--json` > `GET`; `-G` move os `-d` para a
  query; `--json` só acrescenta o `Content-Type` quando ele não foi declarado; `-d @arquivo` não é
  suportado (ler arquivo local está fora do escopo); URL extra vira aviso; cabeçalho sem dois-pontos
  vira aviso.
- **Credencial**: `-u` é convertido em autenticação básica e **avisa** que a credencial vai parar no
  código, com o nome do usuário — a senha não aparece no aviso.
- **Saídas determinísticas** em três abas: Laravel (`Http::...`), `fetch` e `axios`, em texto simples
  sem destaque de sintaxe (não há gramática de JavaScript no CodeMirror aprovado). Copiar copia a aba
  ativa.

### `env-example` — a heurística da máscara

- **Leitura que preserva**: o parser guarda a linha crua e a leitura dela — `export ` opcional,
  espaços, aspas simples ou duplas, valor multi-linha entre aspas, comentário em linha depois do
  valor, CRLF. A saída é o documento na ordem original; só o valor sensível muda.
- **Sensível por segmento**: o nome é dividido em segmentos não alfanuméricos e comparado em caixa
  alta com `KEY`, `SECRET`, `PASSWORD`, `PASS`, `TOKEN`, `AUTH`, `CREDENTIAL`, `DSN`, `PRIVATE`,
  `PASSWD` e `PWD`. `DB_PASSWORD` e `api_key` são sensíveis; `AUTHOR`, `APIKEY`, `MONKEY`, `PATH` e
  `MAILER` não são. O falso positivo e o falso negativo conhecidos ficam registrados aqui; a escolha
  é ser previsível pelo nome, não adivinhar por entropia.
- **Sensível por valor**: URL com `user:senha@` (o userinfo precisa ter `:`) também é mascarada,
  porque o nome da chave não denuncia o valor.
- **O que a máscara faz**: o valor sensível vira vazio; `export `, chave, `=`, aspas e comentário em
  linha ficam. Valor já vazio não entra no aviso. Valor multi-linha vira uma linha só (`KEY=""`), e
  CRLF normaliza para LF na saída. Linha sem `=` é mantida e listada com o número.
- **Aviso**: as chaves mascaradas aparecem com nome e linha; as linhas inválidas, com o número.
  Copiar devolve o documento; baixar grava `.env.example` (`text/plain`) — o único download da fase.

### Telas, registro e limites

- **Cron**: campo de texto simples (a expressão é uma linha) + tabela campo a campo no painel de
  entrada; fuso e lista no painel de saída; Exemplo, Limpar e Copiar relatório. **Sem download.**
- **cURL**: `CodeEditor` na entrada e `<pre>` na saída, em abas; Exemplo, Limpar e Copiar.
- **`.env`**: `FileDropzone` + `CodeEditor` na entrada e `<pre>` na saída; Exemplo, Abrir, Limpar,
  Copiar e Baixar. A aceitação de arquivo cobre os nomes comuns do `.env` (`.env`, `.env.local`,
  `.env.production`, `.env.example` etc.) e `text/plain`, porque `.env` não tem extensão.
- **Registro**: `cron-explainer` em `time` (ordem 150, ícone `calendar-clock`); `curl-to-code` e
  `env-example` em `text` (160 e 170; ícones `terminal` e `file-key`).
- **Sem worker nas três**: as transformações são lineares e baratas (uma expressão, um comando, um
  arquivo de configuração). O teto de entrada continua sendo o do shell (`maxInputBytes`), e o
  trabalho é síncrono.
- **Sem dependência nova**: nenhum pacote entra nesta fase.

## Consequências

- Três tools sem servidor: nada do que se cola sai do navegador; não há cota, retenção ou log de
  conteúdo — o comando cURL e o `.env` podem conter segredo, e é justamente por isso que a tool roda
  local.
- A semântica Vixie é a do cron real; quem conhece `crontab` não é surpreendido, e a tabela da tela
  mostra a leitura de cada campo.
- O aviso de `exhausted` e o de opção não suportada transformam os dois casos "estranhos" em algo
  visível: nenhuma expressão impossível trava a tela e nenhuma opção some do comando convertido.
- O código gerado pelo cURL é determinístico e fixado por teste de string exata; mudar a forma da
  saída de uma aba exige emenda a esta ADR.
- A heurística do `.env` erra pelos casos documentados acima; o aviso com a linha permite conferir
  e corrigir à mão, e a preservação da ordem e dos comentários mantém o arquivo utilizável.
- Limitações registradas: sem segundos/Quartz no cron; sem `-F`/multipart, cookies, redirects,
  proxy ou certificado no cURL (listados como não suportados); sem detecção de segredo por entropia
  no `.env`.
- Alterar o dialeto do cron, a semântica de dia, o núcleo de opções do cURL, a lista de segmentos
  sensíveis ou a regra do valor multi-linha exige emenda a esta ADR.

## Alternativas descartadas

- **`cron-expression` ou `cron-parser` no cliente**: traria semântica e dependência que o projeto
  não escolheu (segundos, Quartz, fuso da máquina) para uma tool que é, na essência, cinco listas de
  inteiros; o parser próprio é pequeno e testável.
- **Cálculo com `Date` local do processo**: parecia mais simples, mas amarra a tool ao fuso da
  máquina e erra no DST. A conversão explícita por `Intl` custa poucas linhas e pina instantes nos
  testes.
- **Semântica "E" entre dia-do-mês e dia-da-semana**: não é o que o `crontab` faz; prometeria uma
  data que o cron não roda.
- **Tokenizer de shell completo (ex.: `shell-quote`)**: a gramática inteira do shell é bem maior que
  um comando `curl` e erraria em silêncio em construções que a tool não converte; o tokenizador
  mínimo cobre o núcleo e recusa o resto com aviso ou erro.
- **Ignorar opções desconhecidas**: geraria código que parece fiel e não é (`-k` some, `-b` some);
  listar o que ficou de fora é o comportamento honesto.
- **Mascarar tudo no `.env`**: um exemplo sem `APP_NAME`/`MAIL_HOST` não ensina o que configurar; a
  máscara seletiva por nome e por valor é o que mantém o arquivo útil.
- **Detecção de segredo por entropia**: daria resultado imprevisível e difícil de explicar; a regra
  por segmento é auditável e está coberta dos dois lados (positivo e negativo).
- **Worker nas três**: custo de bundle e de manutenção para cargas de KiB; o limite já é o do shell.
- **Baixar no cron e no cURL**: o cron copia um relatório de 10 linhas e o cURL um trecho de código
  para colar no editor; download não ajuda e ficaria como ação a mais para manter.
- **Destaque de sintaxe no cURL**: exigiria uma gramática de JavaScript não aprovada; a saída é
  texto simples e legível como está.
