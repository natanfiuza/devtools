# ADR-041: Gate lexical do SQL gerado (emenda ao ADR-006)

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-005 (saida estruturada), ADR-006 (validacao SQL), ADR-011 (output targets)

## Contexto

O SPEC pedia um "parser" para validar o SQL gerado. Na pratica, o projeto
precisaria de um parser por dialeto (MySQL, PostgreSQL, SQL Server e SQLite) -
quatro dependencias grandes, com gramaticas diferentes, para validar um texto
que **nunca e executado**. O risco real nao e sintaxe: e devolver ao usuario,
como se fosse uma consulta, uma instrucao que apaga ou altera dados, uma
chamada a funcao que le arquivo ou um lote com duas instrucoes.

Tambem nao existe um banco descartavel para rodar `EXPLAIN`: o schema e dado do
usuario, de um banco que nao temos acesso, e executar o SQL por conta propria
seria exatamente o que a promessa da tool proibe.

## Decisao

Para o alvo `sql`, o SQL passa por um **gate lexical** (`App\Support\Ai\SqlGuard`),
que nao tenta entender a gramatica:

1. remove strings, identificadores entre crases/colchetes e comentarios -
   mantendo a estrutura - para que palavra proibida dentro de literal nao conte
   e palavra escondida em comentario nao escape;
2. no MySQL, recusa `/*! ... */` e `/*M! ... */`: o servidor executa esse
   conteudo como SQL, ao contrario de um comentario comum;
3. divide o texto **cru** por `;`: um `;` final e permitido, qualquer `;` com
   conteudo depois (ou antes, no meio de duas instrucoes) e `multiple_statements`;
   a divisao antes da limpeza e deliberadamente conservadora - um `;` dentro de
   um literal recusa a consulta, mas nenhum `;` escondido deixa duas instrucoes
   passarem;
4. exige que a instrucao comece em `SELECT` ou `WITH`;
5. recusa verbos de escrita, DDL e administracao (lista fechada: `INSERT`,
   `UPDATE`, `DELETE`, `MERGE`, `CREATE`, `ALTER`, `DROP`, `TRUNCATE`,
   `GRANT`, `SET`, `CALL`, `EXEC`, `COPY`, `PRAGMA`, `VACUUM`, entre outros),
   `INTO`, `OUTFILE`/`DUMPFILE` e funcoes perigosas (`LOAD_FILE`, `PG_READ_FILE`,
   `PG_SLEEP`, `SLEEP(`, `BENCHMARK(`, `XP_CMDSHELL`, ...);
6. recusa literal ou comentario sem fechamento.

O gate roda **depois** do `ContractValidator` e **so** no alvo `sql`. Os oito
alvos de ORM geram codigo de aplicacao que nao e executado pelo site; para eles
basta o contrato (JSON, dialeto, `outputs[target]` e limites). Nenhuma
ferramenta, em nenhum caminho, executa o SQL gerado.

O gate e **defesa em profundidade, nao sandbox**: ele reduz a chance de uma
resposta perigosa chegar ao usuario, mas nao substitui revisao humana. O texto
da tela e do prompt diz que o SQL deve ser revisado antes de rodar no banco do
usuario.

## Consequencias

### Positivas

- Nenhuma dependencia de parser por dialeto, nenhum servico de banco para
  validar, e a validacao roda em microssegundos dentro do job.
- Recusa o que importa: escrita, DDL, lote e funcao de sistema/arquivo.
- O comportamento e testavel por tabela (`SqlGuardTest`), sem infraestrutura.
- A regra do projeto "o SQL nunca e executado" continua verdadeira em todo o
  caminho - inclusive na validacao.

### Negativas / trade-offs

- **Falsos positivos sao possiveis**: uma coluna chamada `update` sem aspas, um
  `SELECT ... INTO` legitimo de variavel ou um `;` dentro de literal sao
  recusados. A resposta e `unsafe_sql` com estorno, e o usuario pode reformular;
  preferimos recusar a arriscar.
- **Falsos negativos tambem**: o gate nao conhece semantica de funcao definida
  pelo usuario nem de dialeto exotico; `SELECT` que chama uma funcao de escrita
  criada no banco do usuario passa. Nao ha como saber sem o schema do banco.
- A lista de verbos e funcoes precisa de manutencao quando um dialeto ganha um
  comando novo relevante.

## Alternativas descartadas

- **Parser completo por dialeto**: peso de dependencia e manutencao de quatro
  gramaticas para validar um texto que nao executamos; o ganho seria
  principalmente sintatico, e nao de seguranca.
- **Executar `EXPLAIN` num banco nosso**: exigiria um banco por dialeto, com o
  schema do usuario, e executaria SQL de terceiros - proibido pela proposta da
  tool.
- **Nao validar nada**: devolveria `DROP TABLE` como "consulta" ao usuario.
- **Regex apenas no inicio da string**: deixaria passar `WITH ... DELETE` e
  instrucoes encadeadas; o gate verifica o texto inteiro depois de limpar
  literais e comentarios.
