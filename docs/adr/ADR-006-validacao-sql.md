# ADR-006-validacao-sql
Status: Aceito
Data: 2026-10-05

## Decisao
Parser valida sintaxe; somente leitura; SQL nunca executado.

## Consequencias
Ver SPEC.md.

## Emenda da F5 (2026-10-20, ADR-041)

O "parser" foi substituido por um **gate lexical** (`App\Support\Ai\SqlGuard`,
ADR-041). O motivo: quatro gramaticas (MySQL, PostgreSQL, SQL Server e SQLite)
seriam dependencias grandes para validar um texto que nunca executamos, e nao
existe banco nosso para rodar `EXPLAIN` sobre o schema do usuario. O gate exige
uma unica instrucao `SELECT`/`WITH`, recusa DDL/DML, `;` interno, `INTO`,
`OUTFILE` e funcoes de arquivo/sistema, depois de remover strings e
comentarios (e recusar `/*!` executavel no MySQL).

O resto da decisao continua: **somente leitura** e **SQL nunca executado**. O
gate roda so no alvo `sql`; os oito alvos de ORM param no validador de
contrato. Falsos positivos (uma coluna chamada `update` sem aspas, um `;`
dentro de literal) sao aceitos em troca de nao deixar passar escrita - a
resposta vira `unsafe_sql` e a reserva e estornada.
