# ADR-132 - Histórico anônimo reduzido e importação explícita
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-096 (limites de paletas), ADR-118 (persistência local do picker, emenda), ADR-127 (histórico e favoritos da conta), ADR-131 (biblioteca compartilhada)

## Contexto
Com a biblioteca da conta (ADR-127), o histórico local do visitante deixou de ser o destino final: ele é uma lista temporária que pode virar histórico da conta. A F1 guardava 50 cores; 50 posições no `localStorage` competiam com o espaço de rascunho e de outras ferramentas e davam a impressão de um arquivo permanente. O prompt da F5 também exigiu que a importação do histórico local fosse **explícita**, nunca automática.

## Decisão

### 1. Tetos anônimos vêm do servidor
`config/colors.php` (`colors.library`) expõe `anonymous_history_limit` (20) e `anonymous_favorites_limit` (50). `ColorsToolProps::library()` entrega esses números nas props das duas tools, e `useColorLibrary` passa os valores para `createColorLibrary`. O cliente tem defaults (`HISTORY_LIMIT = 20`, `FAVORITES_LIMIT = 50`) apenas para teste/SSR; nenhuma tela fixa o número.

### 2. Histórico anônimo é reduzido; favoritos ficam em 50
- `colors.picker.history.v1` guarda no máximo 20 chaves canônicas; a mais recente entra na frente e a mais antiga sai.
- `colors.picker.favorites.v1` continua com 50, porque favorito é intenção e o mesmo teto já era do ADR-118.
- A última cor (`colors.picker.last.v1`) não conta nesses tetos.
- Leitura valida o formato, deduplica e corta o excesso; `localStorage` bloqueado, cheio ou ausente nunca quebra a tool.

### 3. A importação é sempre um pedido explícito
O histórico local só entra na conta pelo botão do painel, que abre `ImportLocalHistoryDialog` com a contagem e a confirmação. Não existe importação no login, no carregamento da página, em segundo plano ou por presença de dados locais. O visitante que nunca clicar mantém tudo no navegador.

### 4. Lotes configuráveis, sem duplicar
`ImportColorHistoryRequest` aceita de 1 a `import_batch` (200 por padrão) cores canônicas por requisição; o cliente divide a lista local nesse tamanho e chama `POST /api/tools/colors/history/import` em sequência. `ImportColorHistory` deduplica o lote, usa `upsert` por `(user_id, color)` e só toca `updated_at` nas que já existem: importar duas vezes não duplica nada. A resposta traz `{imported, total}`.

### 5. O `localStorage` só é limpo depois de todos os lotes
Se qualquer lote falhar, a importação para, nada é perdido e o botão continua disponível. Só depois de todos os lotes passarem o cliente chama `local.clearHistory()`, zera `importable` e recarrega as listas da conta. O usuário vê o resultado real (`imported`) e não um sucesso presumido.

### 6. Favoritos locais não são importados automaticamente
O fluxo cobre o histórico, que era o requisito. Favoritos locais continuam locais até o usuário favoritar novamente na conta; não há migração silenciosa nem botão de importação de favoritos.

## Consequências
- O visitante tem uma lista curta e honesta, que cabe no navegador e deixa claro que a conta é o lugar da lista completa.
- A conta recebe apenas o que o usuário mandar, em lotes pequenos, e a operação é idempotente.
- Falha de rede não apaga o histórico local; o usuário pode tentar de novo.
- O teto anônimo é ajustável por ambiente sem publicar cliente novo.
- A redução de 50 para 20 muda o comportamento da F1 para o visitante; quem tiver uma lista antiga é lido e cortado para 20 na próxima gravação.

## Alternativas rejeitadas
- **Importar automaticamente no primeiro login**: muda dados da conta sem consentimento e pode surpreender quem usa navegadores compartilhados.
- **Um único POST com toda a lista**: estouraria o tamanho do corpo e dificultaria retomar após falha; os lotes dão controle e progresso.
- **Limpar o `localStorage` antes de confirmar**: perderia dados se a rede falhasse; limpar só no fim é seguro.
- **Manter 50 no histórico anônimo**: contraria a decisão de lista curta e o requisito de reduzir o armazenamento local da F5 (ADR-118, emenda).
- **Importar favoritos junto**: não foi pedido, e favorito local pode não representar a intenção atual da conta.
- **Fixar 20/50 no cliente**: impediria ajuste por ambiente e duplicaria a fonte de verdade.
