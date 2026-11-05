# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona suporte a DML seguro no gerador de SQL com IA
```

---

## 🎯 Resumo

Amplia o gerador de SQL com IA para além de consultas (DQL): agora ele também produz operações de manipulação de dados (INSERT, UPDATE, DELETE), conforme a ADR-061. A mudança responde a um caso de uso real — usuários precisavam de scripts de alteração de dados, não apenas SELECTs — sem abrir mão da segurança: o gate lexical passa a aceitar DML, mas exige cláusula `WHERE` explícita em `UPDATE`/`DELETE`, bloqueia `SET` fora de `UPDATE`, `FOR UPDATE`, DDL e comandos administrativos. Todo o vocabulário de interface (formulários, placeholders, avisos) e os prompts dos dialetos foram atualizados para refletir "consulta ou operação" e continuam deixando claro que nada é executado na aplicação.

## 🛠️ Mudanças Técnicas

- `SqlGuard`: remove `INSERT`, `UPDATE`, `DELETE`, `SET` e `UPSERT` da lista de palavras proibidas e passa a permitir statements iniciados por `SELECT`, `WITH`, `INSERT`, `UPDATE` e `DELETE`.
- `SqlGuard`: novas validações — `missing_where_clause` (UPDATE/DELETE sem WHERE), `missing_set_clause` (UPDATE sem SET), `forbidden_keyword` (SET em statement que não é UPDATE) e `for_update_clause` (bloqueio de `FOR UPDATE`).
- `SqlGuard`: motivo de rejeição `not_select` substituído por `unsupported_statement`; a checagem de `INTO` agora se aplica apenas a `SELECT`/`WITH`, liberando `INSERT INTO`.
- `PromptBuilder`: template de fallback atualizado para descrever geração de DQL e DML, com regras explícitas de WHERE obrigatório e proibição de DDL.
- Prompts versionados (`resources/prompts/*.v1.md` e `system/*.md`): alinhados ao novo escopo DQL/DML em todos os dialetos e idiomas.
- Traduções (`en`, `es`, `fr`, `pt_BR`, `pt_PT`): textos de formulário e avisos passam a mencionar "consulta ou operação", com exemplos de UPDATE.
- Testes (`SqlGuardTest`): cobrem aceitação de DML seguro, recusa de UPDATE/DELETE sem WHERE e manutenção do bloqueio de DDL/administrativo.
- Novo `.gitpr/conf/gitpr.config.yml` definindo `main` como branch base de PR.
- Documentação: ADR-061, survey, plano e relatório do Gemini.

## ⚠️ Impacto/Avisos

- **Comportamento do gate alterado**: o identificador de erro `not_select` deixa de existir; qualquer consumidor que trate esse código precisa migrar para `unsupported_statement`.
- **Segurança**: `UPDATE`/`DELETE` sem `WHERE` são rejeitados na camada lexical, não no banco. A proteção é heurística (busca textual por `WHERE`), não um parser por dialeto — vale revisar o resultado antes de executar.
- **Sem mudanças de banco, envs ou dependências.**
- A interface e os prompts passam a sugerir operações de escrita; recomenda-se reforçar na UI o aviso de revisão manual antes da execução.

close #115

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)