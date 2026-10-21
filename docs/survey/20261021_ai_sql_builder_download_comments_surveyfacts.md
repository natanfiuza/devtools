# Survey — Download com comentarios no AI SQL Builder (2026-10-21)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Em ai sql builder quando fizer o download do resultado deve adicionar os textos de Premissas, Avisos e Tabelas usadas como comentarios junto com o codigo do resultado.
- **Tela / Modulo:** `resources/js/tools/ai-sql-builder/ResultPanel.vue`, `resources/js/tools/ai-sql-builder/`
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de 3 perguntas explorando a fronteira de estilizacao por linguagem, posicionamento no arquivo e escopo (copiar vs baixar).
- **Arquivos-chave lidos:**
  - `resources/js/tools/ai-sql-builder/ResultPanel.vue`
  - `resources/js/tools/ai-sql-builder/types.ts`
  - `resources/js/__tests__/ai-sql-builder.test.ts`
  - `lang/pt_BR/ai.php`

## 2. Decisoes das rodadas

### Rodada 1 — Formatacao, Posicionamento e Escopo
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Estilo de comentario por alvo/linguagem | **Adaptar delimitadores conforme a extensao/alvo: SQL/PHP/TS usam bloco `/* ... */`, Python usa `#` ou `"""`, Prisma usa `//` ou `/*`** |
| Q2 | Posicao dos comentarios e secoes vazias | **No topo do arquivo (cabecalho). Secoes vazias sao omitidas. Se nao houver nenhuma informacao adicional, exporta apenas o codigo puro.** |
| Q3 | Localizacao dos titulos e comportamento de Copiar | **Titulos traduzidos com `t(...)` no idioma ativo (`ai.result.assumptions`, etc.). O botao Copiar preserva o codigo limpo sem comentarios.** |

### Defaults assumidos (nao contestados)
- Criacao de funcao utilitaria auxiliar em `resources/js/tools/ai-sql-builder/download.ts` ou `formatComments.ts` para testabilidade unitaria pura sem depender do DOM ou do blob de download.
- As chaves de traducao reutilizam as ja existentes em `ai.result.assumptions`, `ai.result.warnings` e `ai.result.tables` em todos os 5 idiomas suportados.

## 3. Relatorio de fatos levantados
### Suporte a multiplos alvos de saida
O AI SQL Builder suporta SQL e dialetos ORM em linguagens distintas:
- `.sql`: SQL puro
- `.php`: `laravel_query_builder`, `eloquent`
- `.py`: `sqlmodel`, `sqlalchemy`, `django_orm`
- `.prisma`: `prisma`
- `.ts`: `drizzle`, `typeorm`

### Regra de ouro da suite de testes e CI
Os testes em `resources/js/__tests__/ai-sql-builder.test.ts` e novos testes unitarios especificos devem validar que a funcao geradora do conteudo do download monta o cabeçalho correto com delimitadores apropriados, respeitando ausencia de campos vazios e caracteres especiais.
