# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona histórico pesquisável de consultas de IA
```

---

## 🎯 Resumo

Usuários que já geraram consultas com IA não tinham como reencontrá-las ou reaproveitá-las rapidamente, precisando reescrever schema e descrição do zero. Esta mudança introduz um histórico consultável no AI SQL Builder: cada consulta salva recebe um **título gerado pelo modelo** (sintetizando a intenção e o formato de saída) e passa a armazenar o **prompt** e o **schema** usados, permitindo busca textual por qualquer um desses campos e restauração do formulário e do resultado com um clique.

O objetivo é reduzir retrabalho, acelerar consultas recorrentes e dar visibilidade ao que já foi produzido, sem expor dados de contas que optaram por não manter histórico.

## 🛠️ Mudanças Técnicas

- **Banco de dados**: nova migration adiciona `title` (string), `prompt` (text) e `schema` (longText) em `ai_requests`; colunas só são preenchidas quando `history_visible = true`.
- **Contrato de IA**: `AiResult` ganha o campo opcional `title`; `ContractValidator` valida tipo, faz `trim`, aceita vazio como `null` e rejeita títulos acima de 255 caracteres (`title_not_string` / `title_too_long`).
- **Prompt do modelo**: `PromptBuilder` passa a exigir `title` no JSON de resposta, com instrução de idioma e exemplos de título.
- **Persistência**: `StartAiQuery` e `SettleAiQuery` gravam `title`, `prompt` e `schema` apenas quando o histórico está habilitado (inclusive em cache hit).
- **Nova API**: `GET /api/ai/history` (`AiQueryController::history`) lista até 30 registros do próprio usuário com `history_visible = true`, ordenados por `created_at` desc, filtrando por `q` via `LIKE` em `title`, `prompt` e `schema`.
- **Serialização**: `AiQueryResource` expõe `title`; `HistoryController` usa o título como sumário quando disponível.
- **Frontend**: novo componente `QueryHistory.vue` (busca com debounce de 300ms, estados de loading/erro/vazio, colapsável); `SchemaForm` expõe `populate()`; `useAiQuery` ganha `restore()` para reidratar o resultado sem polling; `Tool.vue` integra histórico, recarrega após submit e restaura formulário + resultado ao selecionar um item.
- **Rotas e props**: `AiToolProps` inclui `endpoints.history`; rota nomeada `history` registrada.
- **i18n**: chaves `ai.history.*` adicionadas em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **Testes**: cobertura de listagem/busca por schema, prompt e título, isolamento por usuário e respeito a `history_visible`; testes de contrato para `title`; ajuste em `AiToolPropsTest`.

## ⚠️ Impacto/Avisos

- **Migração de banco obrigatória**: é necessário rodar `php artisan migrate`. O rollback remove as três colunas e, consequentemente, os dados de título/prompt/schema já gravados.
- **Dados retroativos**: consultas anteriores à migration ficam com `title`, `prompt` e `schema` nulos — não serão encontradas por busca textual (mas continuam listáveis por data).
- **Nova rota pública autenticada**: `GET /api/ai/history` requer usuário autenticado e retorna apenas registros do próprio usuário com `history_visible = true`.
- **Busca com `LIKE`**: a consulta usa `%termo%` sem índice dedicado; o limite de 30 itens mitiga o custo, mas volumes altos podem exigir índice ou busca full-text no futuro.
- **Contrato do modelo mais rígido**: respostas sem `title` continuam aceitas (`null`), mas títulos acima de 255 caracteres passam a falhar com `title_too_long`.
- **Sem novas dependências ou variáveis de ambiente**.

close #60

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_4_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)