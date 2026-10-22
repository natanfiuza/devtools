# Survey — AI SQL Builder: Descricao Didatica, Dicas de Saida e Scroll Vertical (2026-10-22)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Em Ai Sql Builder adicione a Premissas, Avisos e Tabelas usadas uma breve descricao do que foi feito para que o desenvolvedor possa entender a consulta gerada, o texto deve ser didatico mas resumido. Adicione tambem aos textos retornados dicas uteis sobre o tipo de saida escolhido, exemplo se o usuario escolher Laravel Query Builder mostrar uma dica de como e porque e melhor usar o model do que usar um "DB::" ao montar uma query builder por exemplo. Na secao onde exibe Premissas e Avisos e os outros, deixe os textos um abaixo do outro aplicando uma barra de rolagem vertical. Lembre de adicionar os novos textos ao fazer o download do Resultado como comentarios.
- **Tela / Modulo:** `resources/js/tools/ai-sql-builder/` (`ResultPanel.vue`, `formatDownload.ts`, `types.ts`), `app/Support/Ai/` (`AiResult.php`, `ContractValidator.php`, `PromptBuilder.php`), traducoes `lang/{locale}/ai.php`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** Rodada unica conclusiva (Q1 a Q4), inspecao autonoma do backend, frontend e regras i18n do repositorio.
- **Arquivos-chave lidos:**
  - `resources/js/tools/ai-sql-builder/ResultPanel.vue`
  - `resources/js/tools/ai-sql-builder/formatDownload.ts`
  - `resources/js/tools/ai-sql-builder/types.ts`
  - `app/Support/Ai/AiResult.php`
  - `app/Support/Ai/ContractValidator.php`
  - `app/Support/Ai/PromptBuilder.php`
  - `lang/pt_BR/ai.php` (e variantes `en`, `es`, `fr`, `pt_PT`)

## 2. Decisoes das rodadas

### Rodada 1 — Definicao de Origem de Dados, Layout e Download
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Origem da breve descricao didatica da consulta | **Opcao A**: Campo `explanation` gerado pela IA no contrato JSON, validado opcionalmente/retrocompativelmente pelo `ContractValidator`, instruido no `PromptBuilder` para ser didatico, conciso e no mesmo idioma da requisicao. |
| Q2 | Origem das dicas uteis sobre o tipo de saida | **Opcao A**: Dicas estaticas curadas de boas praticas no `i18n` (`lang/{locale}/ai.php`) sob `ai.tips.<target>`, exibidas de forma reativa conforme o alvo ativo da consulta/aba (ex: `laravel_query_builder`, `eloquent`, `prisma`, etc.). Sem custo adicional de tokens e com suporte aos 5 idiomas. |
| Q3 | Layout da secao de metadados | **Opcao A**: Um container unico vertical (`flex flex-col gap-3 max-h-64 overflow-y-auto pr-2 rounded-md border p-3 bg-muted/20`) contendo verticalmente: Descricao da Consulta, Dica do Alvo, Premissas, Avisos e Tabelas Usadas. |
| Q4 | Formatacao do arquivo de download | **Opcao A**: No formatador de download (`formatDownload.ts`), incluir Descricao Didatica e Dica da Saida no topo dos comentarios antes de Premissas, Avisos e Tabelas, formatados no estilo de comentario da linguagem correspondente (`#` para Python, `/* ... */` para SQL, PHP, TS, Prisma). |

### Defaults assumidos (nao contestados)
- Retrocompatibilidade total com consultas antigas em cache e historico: caso uma consulta antiga nao tenha `explanation`, o componente nao falha e simplesmente omite a secao.
- As strings de dicas (`tips`) serao adicionadas nos 5 idiomas suportados do sistema (`en`, `pt-BR`, `pt-PT`, `fr`, `es`).
- A documentacao do repositorio segue a regra sem acentos; strings visiveis de interface utilizam acentuacao correta.

## 3. Relatorio de fatos levantados
### Contrato da IA (`ContractValidator` e `AiResult`)
- O backend usa `ContractValidator` estrito. Adicionar campos extras requer atualizar o `PromptBuilder` para instruir o modelo a retornar `"explanation": "string"`, atualizar o `ContractValidator` para validar `explanation` (opcional, string nao vazia com limite razoavel ex: 1000 caracteres, normalizado como `?string`), e persistir em `AiResult` (`toArray()`, construtor e `@phpstan-type`).
- O TypeScript em `types.ts` deve refletir `explanation?: string | null` em `AiQueryResult`.

### Dicas de Saida (`ai.tips.<slug>`)
- Cada um dos alvos em `OutputTarget` (`sql`, `laravel_query_builder`, `eloquent`, `sqlmodel`, `sqlalchemy`, `django_orm`, `prisma`, `drizzle`, `typeorm`) recebera uma dica especializada orientando o desenvolvedor sobre melhores praticas (ex: em Laravel Query Builder, explicar a diferenca entre usar `Model::query()` vs `DB::table()`, mantendo tipagem, casts e scopes; em Eloquent, atencao a eager loading vs lazy loading para evitar N+1; etc.).

### Experiencia Visual e Acessibilidade no ResultPanel
- O layout atual usava `sm:grid-cols-2`. Mudar para lista vertical com rolagem unificada (`overflow-y-auto max-h-64`) evita quebra de layout quando muitas premissas ou avisos sao gerados e melhora a leitura em dispositivos moveis e desktop.
