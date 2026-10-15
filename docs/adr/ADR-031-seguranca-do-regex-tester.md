# ADR-031-seguranca-do-regex-tester
Status: Aceito
Data: 2026-10-14

## Decisao
**A busca do Regex Tester roda num Web Worker descartavel, com prazo: passou de
`TOOLS_REGEX_TIMEOUT_MS`, o worker e encerrado e a tela nao mostra resultado
nenhum - nem a lista parcial, nem a leitura que ja estava la.**

- **Onde a conta roda**: `lib/run.worker.ts` (worker de modulo, criado por
  `lib/run.workerHost.ts`) chama a mesma funcao pura que a queda usa na thread
  principal (`lib/run.ts`). O caminho da thread principal existe para o SSR, o
  jsdom e o CSP que barra o modulo - e nele **nao ha protecao nenhuma**.
- **Quem mata e o relogio**: `lib/runner.ts`, o unico lugar da F3 que chama
  `terminate()`. O prazo vem de `config/tools.php` e chega na tela por prop,
  como o teto de entrada (ADR-029) - o cliente nao guarda o numero.
- **Estourou, nao ha resultado**: a promessa do estouro e a de "nada". A frase
  traduzida (`states.timeout`) diz o que aconteceu e quantos segundos o prazo
  deu; a lista, a previa realcada e o campo de substituicao ficam vazios, e o
  Copiar desabilita.
- **Depois do estouro nao ha queda para a thread principal**: o prazo existe
  por causa do retrocesso catastrofico, e mandar a mesma conta para a thread
  principal seria congelar a aba - exatamente o que a protecao evita. O worker
  morto nao volta: nasce outro para o proximo pedido.
- **Padrao invalido nao mata o worker**: o erro de sintaxe volta como
  *resultado* (`{ ok: false, detail }`), e nao como excecao. Todo `(` no meio de
  uma expressao e um padrao invalido por alguns instantes: derrubar o worker a
  cada um deles faria a queda silenciosa do cliente levar as buscas seguintes
  para a thread principal, sem ninguem notar a perda.
- **O teto de casamentos e a outra metade da protecao**:
  `TOOLS_REGEX_MAX_MATCHES` (padrao 1000). Um padrao barato (`a?`, `\b`) sobre
  um texto grande casa em toda posicao - o tempo fica baixo e mesmo assim a
  lista e a previa viram um documento proprio. A lista para no teto e **avisa**
  (`states.truncated`); o texto trocado continua saindo do texto inteiro.
- **`g` esta sempre ligado e `y` fica de fora**: a tela promete todos os
  casamentos, entao o `g` nao e um alternador; o `y` (sticky) ancora cada
  tentativa no `lastIndex` e mudaria o sentido do padrao em silencio. `i`, `m`,
  `s` e `u` mudam como o padrao e lido, e por isso sao alternadores.
- **O relogio e de parede porque nao ha outro**: o `RegExp` do JS nao expoe
  interrupcao, contador de passos nem prazo. Encerrar quem esta preso e a unica
  alavanca que o motor oferece.

## Por que
A ameaca aqui nao vem de terceiro: o padrao e o texto sao de quem esta usando a
ferramenta, e nada sai do navegador (ADR-002). O risco e **auto-infligido** - um
`(a+)+$` sobre um log colado e o retrocesso catastrofico acontece, e a aba trava
sem saida que nao seja mata-la. Um testador de expressoes que pune a tentativa
nao serve para nada: tentar e a funcao dele.

O worker transforma "aba travada" em mensagem. Ele nao evita o retrocesso - nada
no navegador evita -, mas devolve o controle a quem esta olhando, e o custo e um
worker por busca (que nasce no clique, e nao no meio do travamento).

O estouro nao mostrar resultado e a mesma regra do "saida defasada" ao
contrario: a lista de casamentos vale para o texto **inteiro**, e uma conta que
morreu no meio nao leu o texto inteiro. Devolver o pedaco que deu tempo seria
uma resposta errada sem dizer que e parcial - a contagem mudaria com o humor do
relogio da pessoa.

## Consequencias
- `config/tools.php` e `.env.example` ganharam os dois numeros da tool
  (`TOOLS_REGEX_TIMEOUT_MS`, `TOOLS_REGEX_MAX_MATCHES`). Mudar o prazo no `.env`
  muda a frase da tela sem deploy de front, porque o numero viaja por prop.
- **O Regex Tester e a unica tool da F3 que mata worker**. No JSON Viewer e no
  SQL Formatter o worker e fluidez - o cliente degrada em silencio e a tool
  continua certa. Aqui ele e a protecao, e a degradacao silenciosa seria perda
  de funcao; por isso o runner **recusa** correr o relogio na thread principal,
  e `regex-worker.test.ts` prende as tres regras: estourou nao ha resultado; nao
  ha queda depois do estouro; sem worker nao ha relogio (e a busca sai aqui
  mesmo, como no SSR).
- O worker morto nao recebe pedido nenhum e o proximo pedido vai para um worker
  novo - o teste prende as duas coisas (o antigo encerrado, o novo com o pedido).
- O que atravessa do worker de volta e pequeno: a resposta leva os casamentos
  (no maximo o teto) e o texto trocado, e nunca o texto de teste de volta.
- **ReDoS nao e problema do servidor**: a tool nao tem endpoint, nao ha cota,
  nao ha log e nada do que se cola aqui chega ao Laravel.
- O padrao morar na barra de acoes (excecao ao shell, documentada no DESIGN) faz
  parte da mesma promessa: o campo que se ajusta olhando o resultado fica ao
  lado do resultado.

## Alternativas descartadas
- **Prazo no servidor** - exigiria mandar o padrao e o texto para o servidor,
  contra o ADR-002; e o texto colado aqui pode ser um log de producao.
- **Orcamento de passos no `RegExp` da thread principal** - o motor nao expoe
  interrupcao nem contador; alcancar isso seria escrever (e manter) um motor de
  expressoes regulares proprio.
- **Devolver a lista parcial no estouro** - resposta errada apresentada como
  resposta, com a contagem variando conforme a maquina de quem usa.
- **Manter a ultima leitura valida na tela** (o `stale_output` do JSON Viewer) -
  a pessoa veria a leitura de **outro** texto enquanto a tela diz que a busca
  passou do tempo. Nesta tool a saida e uma leitura do texto, e nao um documento
  derivado dele.
- **Deixar o worker vivo depois do estouro** - um worker preso no retrocesso
  nao responde: reusa-lo penduraria toda busca seguinte.
- **`y` e `g` como alternadores** - o `y` redefiniria a busca em silencio e o
  `g` desligado contradiz a tela, que lista os casamentos e os conta.
- **Teto de matches e prazo como constantes do cliente** - sao decisao de
  produto, como o teto de entrada: moram na configuracao (CLAUDE.md).
