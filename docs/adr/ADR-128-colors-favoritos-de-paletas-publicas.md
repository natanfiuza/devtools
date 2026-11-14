# ADR-128 - Favoritos de paletas públicas e aba Favoritas
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-096 (cota de paletas), ADR-098 (visibilidade pública), ADR-119 (arquivamento), ADR-124 (página pública e “Uso”)

## Contexto
O prompt da F5 pediu para favoritar paletas públicas sem duplicatas. A F4 já tinha página pública, arquivamento, slug global e o contador “Uso”, mas nenhuma forma de guardar uma paleta de outra conta para reencontrá-la. “Favoritar” não podia virar uma cópia: duplicar consome cota, cria slug e OG e infla “Uso” — coisas que o usuário não pediu ao salvar um link.

## Decisão

### 1. O favorito é só o vínculo
`color_palette_favorites` guarda `user_id` + `color_palette_id` (ULID), com `unique(user_id, color_palette_id)`, `cascadeOnDelete` nos dois lados e índice de listagem por `created_at`. A paleta não é copiada: nome, cores, papéis, tags e versões continuam sendo os da origem. O vínculo pertence a quem favoritou e não aparece na paleta nem para o dono.

### 2. Operações idempotentes
`FavoritePalette` usa `firstOrCreate`; a corrida entre duas abas cai no índice único e a ação relê o vínculo. `UnfavoritePalette` apaga sem erro quando não havia vínculo. POST e DELETE respondem `{favorited: true|false}`.

### 3. Só paleta pública e ativa de outra conta
O controller exige, antes de qualquer escrita:

- usuário autenticado (senão 401);
- paleta pública, não arquivada e com slug (senão 404 uniforme, igual à página pública);
- dono diferente da conta (senão 403 `forbidden`).

Favoritar a própria paleta é recusado porque ela já está em Minhas Paletas. O 403 usa `AccessDeniedHttpException` para o envelope da API preservar `error.code`.

### 4. A aba Favoritas mostra o que ainda pode ser acessado
Em Minhas Paletas, `?tab=favorites` lista os vínculos da conta com `whereHas('palette', publiclyVisible())`, ordenados por `favorited_at`/`created_at` decrescente e paginados como a aba “Minhas”. Se a paleta virar privada ou for arquivada, ela some da lista **sem apagar o vínculo**; se voltar a ser pública, reaparece. Não há notificação nem snapshot do conteúdo enquanto está oculta.

### 5. A estrela da página pública depende de quem olha
`PublicPaletteController@show` entrega `canFavorite` (logado e não dono) e `favorited` (consulta do vínculo). Sem `canFavorite`, a estrela não aparece. O clique é otimista com rollback e `aria-pressed`, reusando o padrão da biblioteca de cores (ADR-131).

### 6. Favoritar não consome cota, não duplica e não conta “Uso”
O vínculo não cria paleta, então não passa pela cota (ADR-096), não gera slug/OG e não incrementa o contador de duplicações (ADR-124). Duplicar continua sendo uma ação separada e explícita.

## Consequências
- O usuário organiza referências de outras contas sem poluir Minhas Paletas nem gastar cota.
- A lista respeita a visibilidade atual da origem: favoritos não viram um caminho lateral para conteúdo privado ou arquivado.
- O vínculo é pequeno e estável; o custo é uma consulta com `whereHas` na aba Favoritas e uma consulta de existência na página pública.
- Favoritos órfãos não existem: excluir a paleta remove o vínculo por cascata.

## Alternativas rejeitadas
- **Copiar a paleta ao favoritar**: consome cota, cria slug e OG e infla “Uso” — efeitos que o usuário não pediu.
- **Guardar um snapshot das cores no vínculo**: a página pública já é a fonte da verdade e o favorito deve refletir o estado atual; snapshot criaria duas versões para manter.
- **Permitir favoritar a própria paleta**: duplicaria a lista de Minhas Paletas e confundiria “favorito” com “minha”.
- **Manter o favorito visível quando a paleta fica privada/arquivada**: exporia ou quebraria links; ocultar sem apagar preserva a intenção e o retorno.
- **Notificar o dono a cada favorito**: isso é interação social e pertence à F6/F7, não à organização pessoal.
