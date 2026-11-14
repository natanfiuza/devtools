# ADR-127 - Biblioteca pessoal de cores: histórico e favoritos da conta
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-096 (limites de paletas), ADR-118 (persistência local do picker, emenda), ADR-131 (biblioteca compartilhada), ADR-132 (histórico anônimo e importação explícita)

## Contexto
A F1 guardava histórico e favoritos só no `localStorage` do navegador (ADR-118). Isso atendia o visitante, mas não quem tem conta: a lista não acompanhava o usuário entre navegadores, não era a mesma no HTML Color Picker e no Color Palette Generator e não sobrevivia à limpeza do navegador. O prompt da F5 pediu histórico no backend para cadastrados, sem limite funcional, e favoritos de cor compartilhados pelas duas ferramentas.

A gravação acontece a cada cor finalizada no picker e pode ser frequente; a leitura alimenta dois painéis e precisa continuar barata. O anônimo não pode passar a depender do servidor.

## Decisão

### 1. Duas tabelas aditivas, uma linha por cor
`color_history_entries` e `color_favorites` têm `id` ULID, `user_id` com `cascadeOnDelete`, `color` de até 16 caracteres, timestamps, índice `unique(user_id, color)` e índice de listagem. Nenhuma tabela existente é alterada. A exclusão da conta leva junto as duas listas.

### 2. Chave canônica é a representação persistida
O servidor aceita apenas `#rrggbbaa` em minúsculas ou `transparent` (`ColorKey::PATTERN`), depois de normalizar caixa e espaços. Formatos como `#rrggbb`, nomes CSS ou `rgba()` seriam ambíguos para a unicidade e perderiam alpha; por isso a normalização acontece no cliente antes do envio e a validação no `FormRequest` recusa o resto com 422.

### 3. Histórico é upsert; favorito é vínculo idempotente
- `RecordColorHistory` usa `firstOrCreate` por `(user_id, color)` e toca `updated_at`; repetir a mesma cor não cria linha nova e a cor sobe para o topo. A corrida entre duas abas cai no índice único e a ação tenta de novo até 3 vezes.
- `FavoriteColor` também é `firstOrCreate`, e `UnfavoriteColor` apaga sem erro quando a cor não estava favoritada. Favoritar/desfavoritar duas vezes continua sendo uma operação válida.
- O histórico é ordenado por `updated_at` e o favorito por `created_at`, ambos com `id` como desempate.

### 4. Sem limite funcional, leitura por cursor
O histórico da conta não tem cota: é organização pessoal, não um recurso publicado. A listagem usa `cursorPaginate` com `history_per_page`/`favorites_per_page` (100 por padrão) e `next_cursor`; o cliente carrega mais sem duplicar cores. Os números são de transporte e vêm de `config/colors.php`, nunca fixados no código.

### 5. HTTP da conta, ações de escrita com limiter próprio
As rotas ficam em `routes/colors.php`, sob `auth`, `verified` e `active`:

- `GET /api/tools/colors/history` e `GET /api/tools/colors/favorites`;
- `POST`/`DELETE` nos dois recursos;
- `POST /api/tools/colors/history/import` (ADR-132).

As leituras não têm throttle; as escritas usam `throttle:colors-library` porque o picker pode finalizar com frequência. O envelope de erro é o do projeto e a conta vem sempre da sessão — nenhum `user_id` é aceito no corpo.

### 6. Conta é a fonte da verdade; a UI é otimista
Para quem está logado o composable `useColorLibrary` carrega as duas listas da API, aplica a mudança na hora e reverte se a requisição falhar, marcando `failed`. No próximo `load` o servidor volta a mandar. O anônimo continua 100% em `localStorage`, sem nenhuma requisição. O login não mescla as listas automaticamente; o histórico local só entra pelo fluxo explícito do ADR-132.

### 7. Limpeza explícita
`DELETE /history` sem corpo limpa o histórico; com `color`, remove uma cor. O mesmo vale para favoritos. Limpar não afeta a outra lista nem as paletas salvas.

## Consequências
- Histórico e favoritos acompanham a conta e são idênticos nas duas ferramentas; a UI responde sem esperar o servidor.
- A persistência cresce com o uso, mas sem cota: o custo é uma linha pequena por cor distinta e uma consulta paginada por cursor, sem `count` caro.
- O anônimo mantém a experiência local e a privacidade: nenhuma cor sai do navegador sem conta.
- A unicidade por cor exige uma representação canônica; qualquer novo formato de entrada precisa passar por `ColorKey`.
- Duas listas com semânticas diferentes (histórico = recência; favorito = intenção) não podem ser fundidas numa tabela só sem perder a ordenação.

## Alternativas rejeitadas
- **Manter tudo em `localStorage` também para logado**: não sincroniza entre navegadores, não compartilha entre Picker e Generator e se perde ao limpar o navegador.
- **Uma tabela única com um campo `kind`**: histórico e favoritos têm ordem e intenção diferentes; separar mantém consultas e índices simples.
- **Cota no histórico**: o requisito é organização pessoal sem limite funcional; a paginação já controla o custo de leitura.
- **Persistir o texto digitado**: `#fff` e `#ffffff` criariam duplicatas e nomes CSS não teriam alpha; a chave canônica elimina a ambiguidade.
- **Mesclar automaticamente o histórico local no login**: decisão explícita do usuário, registrada no ADR-132.
