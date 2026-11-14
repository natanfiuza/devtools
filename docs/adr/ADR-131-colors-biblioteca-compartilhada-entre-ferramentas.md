# ADR-131 - Biblioteca de cores compartilhada entre as ferramentas
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-118 (persistência local do picker, emenda), ADR-127 (histórico e favoritos da conta), ADR-132 (histórico anônimo e importação explícita)

## Contexto
O HTML Color Picker e o Color Palette Generator têm a mesma necessidade: guardar as últimas cores usadas e as cores favoritas. Na F1 cada ferramenta tinha sua própria cópia no `localStorage`; na F5 a lista precisa ser uma só, disponível nas duas telas, com o mesmo comportamento para visitante e para conta. Implementar dois composables ou dois pares de painéis duplicaria regras de deduplicação, paginação, escrita otimista e importação.

## Decisão

### 1. Um composable e um conjunto de painéis
`resources/js/lib/colors/library.ts` expõe `useColorLibrary(props.library)`; o picker (`html-color-picker/Tool.vue`) e o gerador (`palette-generator/GeneratorWorkspace.vue`) usam a mesma instância de regras. Os painéis `HistoryPanel`, `FavoritesPanel` e `ImportLocalHistoryDialog`, em `components/colors/`, são os mesmos nas duas telas. A biblioteca é de cores — não se confunde com paletas favoritas (ADR-128).

### 2. Conta é a fonte da verdade; anônimo é local
- **Logado**: o composable lê `/api/tools/colors/history` e `/favorites` (ADR-127), pagina por cursor e mantém `history`/`favorites` em memória. O servidor é a autoridade no próximo `load`.
- **Anônimo**: tudo em `localStorage`, via `createColorLibrary`, com histórico de 20 e favoritos de 50 (ADR-132); nenhuma requisição é feita. A ferramenta continua funcionando sem conta.

O composable recebe `library` nas props da página (`ColorsToolProps::library()`), então os limites vêm de `config/colors.php`, não de constantes no cliente. Os defaults do composable existem só para teste e SSR.

### 3. Escrita otimista com reversão
`addHistory`, `toggleFavorite`, `clearHistory` e `clearFavorites` atualizam a lista imediatamente e disparam a requisição; se ela falhar, a lista volta ao estado anterior e `failed` é marcado. O usuário vê a resposta na hora sem perder consistência. `load(force)` recarrega as duas listas; `loadMoreHistory`/`loadMoreFavorites` mesclam páginas por `mergeUnique`, sem duplicar a chave canônica.

### 4. A chave canônica é a identidade
Histórico e favoritos comparam `canonicalKey(color)` (sRGB com alpha, alpha 0 = transparente), a mesma representação persistida pelo servidor (`ColorKey`). Isso garante dedupe no cliente, no `localStorage` e no banco, e faz `#rrggbb` e `#rrggbbaa` equivalentes quando o alpha é 1.

### 5. Login não mescla listas automaticamente
Ao autenticar, o composable passa a ler a conta e **não** copia o `localStorage` para o servidor. O histórico local continua visível como oferta de importação (`importable`, `canImportLocalHistory`) e só entra pelo fluxo explícito do ADR-132. Favoritos locais não são importados automaticamente: não há requisito de migração e uma mescla silenciosa surpreenderia o usuário.

### 6. Falha de armazenamento nunca quebra a ferramenta
Leitura/escrita local em modo privado, cota cheia ou SSR é tolerada: `createColorLibrary` retorna lista vazia e segue. O mesmo vale para a API: falha de rede vira `failed` e a UI mantém o último estado bom.

## Consequências
- Uma única implementação de dedupe, paginação, otimismo, importação e estados de erro; corrigir um comportamento vale para as duas ferramentas.
- A lista é a mesma ao trocar de tool e entre navegadores quando há conta.
- O anônimo mantém a privacidade e a ferramenta não depende do servidor para funcionar.
- O composable precisa lidar com três modos (local, conta, conta com histórico local importável) e com a diferença de ordenação (histórico por recência, favoritos por intenção), o que concentra complexidade num módulo testável.
- A conta não recebe dados locais sem consentimento; o usuário decide quando importar.

## Alternativas rejeitadas
- **Um composable por ferramenta**: duplicaria regras e permitiria que Picker e Generator divergissem na deduplicação ou na escrita otimista.
- **Só backend, inclusive para anônimo**: exigiria conta para usar o picker e contrariaria o processamento local.
- **Só `localStorage`, inclusive para logado**: não sincroniza entre navegadores nem compartilha entre as tools (ADR-127).
- **Mesclar automaticamente o local no login**: muda dados da conta sem ação do usuário; o fluxo explícito do ADR-132 é mais previsível.
- **Guardar o estado no servidor a cada mudança sem otimismo**: a UI ficaria refém da latência e o picker é usado em sequência rápida.
