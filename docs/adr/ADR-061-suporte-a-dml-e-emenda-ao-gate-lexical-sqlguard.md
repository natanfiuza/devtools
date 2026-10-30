# ADR-061: Suporte a DML no AI SQL Builder e emenda ao gate lexical (SqlGuard)

Status: Aceito
Data: 2026-10-30
Relacionados: ADR-005 (saída estruturada), ADR-006 (validação SQL), ADR-041 (gate lexical de SQL), ADR-056 (i18n de prompts)

## Contexto

O ADR-041 estabeleceu o gate lexical (`App\Support\Ai\SqlGuard`) para o alvo `sql`, restringindo as instruções estritamente a consultas de leitura (`SELECT` e `WITH ... SELECT`). DML (`INSERT`, `UPDATE`, `DELETE`) e DDL eram terminantemente proibidos para assegurar que nenhuma instrução de escrita fosse entregue ao usuário sob a premissa de ser uma consulta.

Entretanto, fluxos reais de desenvolvimento necessitam frequentemente de auxílio de IA não apenas para leitura (DQL), mas também para manipulação de dados (DML) — como construir operações de inserção em lote, atualizações condicionais ou deleções com filtros complexos em SQL puro, Eloquent (`create`, `update`, `upsert`), Laravel Query Builder (`insert`, `update`, `delete`) e demais ORMs (Prisma, TypeORM, Drizzle, SQLAlchemy, SQLModel, Django ORM).

Como o código gerado **nunca é executado pela plataforma**, a restrição absoluta a DQL limitava desnecessariamente a utilidade da ferramenta. Ao mesmo tempo, permitir DML exige salvaguardas adicionais contra destruição inadvertida de dados (como `UPDATE` ou `DELETE` sem `WHERE`) e a manutenção inegociável da proibição de DDL (`DROP`, `ALTER`, `TRUNCATE`, `CREATE`), administração de banco e execução de funções perigosas.

## Decisão

1. **Permissão de DML**:
   O AI SQL Builder passa a aceitar solicitações de manipulação de dados (DML), abrangendo:
   - `INSERT`
   - `UPDATE` (com cláusula `WHERE` obrigatória)
   - `DELETE` (com cláusula `WHERE` obrigatória)
   - `UPSERT` / `updateOrInsert` (conforme suporte de cada dialeto e ORM)
   DDL (`CREATE`, `ALTER`, `DROP`, `TRUNCATE`), comandos administrativos (`GRANT`, `REVOKE`, `KILL`, `SHUTDOWN`) e chamadas de sistema continuam terminantemente proibidos.

2. **Emenda ao Gate Lexical (`SqlGuard`)**:
   Para o alvo `sql` e o campo `sql` equivalente gerado:
   - A verificação de início de instrução passa de `^(SELECT|WITH)\b` para `^(SELECT|WITH|INSERT|UPDATE|DELETE)\b`.
   - `INSERT`, `UPDATE` e `DELETE` são removidos da lista `FORBIDDEN_KEYWORDS`.
   - `INTO` passa a ser permitido se a instrução for `INSERT INTO`. Continua proibido em `SELECT` (evitando `SELECT ... INTO`) e com cláusulas de arquivo (`INTO OUTFILE` / `INTO DUMPFILE`).
   - Instruções `UPDATE` e `DELETE` exigem obrigatoriamente a presença da palavra-chave `WHERE`. Instruções sem `WHERE` são rejeitadas com `UnsafeSqlException::because('missing_where_clause')`.
   - Regras de instrução única (`single_statement`), proibição de comentários executáveis MySQL (`/*! ... */`) e bloqueio de funções perigosas permanecem inalteradas.

3. **Prompts do Sistema e dos Alvos**:
   - As instruções de sistema (`resources/prompts/system/*.md` e fallback em `PromptBuilder.php`) são atualizadas para orientar o modelo na geração de DQL e DML seguro, impondo `WHERE` em qualquer atualização ou deleção.
   - Todos os prompts de alvos (`sql.v1.md`, `eloquent.v1.md`, `laravel_query_builder.v1.md`, `prisma.v1.md`, `typeorm.v1.md`, `drizzle.v1.md`, `sqlalchemy.v1.md`, `sqlmodel.v1.md`, `django_orm.v1.md`) recebem diretrizes claras para gerar código idiomático de manipulação de dados quando solicitado.

4. **Experiência do Usuário (UI/UX) e i18n**:
   - A inferência entre DQL e DML é 100% automática pelo modelo a partir da descrição fornecida pelo usuário, sem necessidade de seletores adicionais no formulário.
   - Textos e placeholders em `lang/*/ai.php` (em todos os 5 idiomas: `en`, `pt-BR`, `pt-PT`, `fr`, `es`) são ajustados para expressar "consulta ou operação".

## Consequências

### Positivas
- O AI SQL Builder torna-se uma ferramenta completa de banco de dados, atendendo casos de uso comuns de `INSERT`, `UPDATE` e `DELETE` em todos os frameworks suportados.
- Proteção ativa contra `UPDATE` e `DELETE` desgovernados (sem `WHERE`), tanto no prompt quanto no gate lexical.
- Preservação da postura de segurança estabelecida no ADR-041: sem execução de SQL no servidor e bloqueio firme contra DDL e comandos de sistema.

### Negativas / Trade-offs
- Casos raros de `UPDATE` intencional em toda a tabela (ex: reset de flag em tabela de configuração com 1 linha) exigirão uma cláusula redundante como `WHERE 1 = 1` ou `WHERE id > 0` para passar no gate. Consideramos essa restrição benéfica frente ao risco de acidentes.
