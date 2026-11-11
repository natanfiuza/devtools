# Minhas Paletas

## URL
`/dashboard/colors/palettes` — o dashboard **não** tem prefixo de locale (ADR-022); a interface segue o idioma da conta/sessão.

## Recursos (Fase 4, implementados em 2026-11-11)

- Busca server-side por nome, descrição e tags (debounce de 300 ms, `preserveState`/`preserveScroll`).
- Filtro por status (ativas/arquivadas) e por tag.
- Ordenação por data de atualização (padrão), data de criação, nome, número de cores ou “Uso”; direção asc/desc.
- Paginação server-side (`colors.palettes.per_page`, padrão 20) com a query string preservada.
- Cota no topo: Free 25, pago sem limite funcional; **arquivadas contam** e só a exclusão definitiva libera vaga (ADR-096).
- Editar no gerador com as cores efetivas persistidas e a aba Protótipo (`?tab=prototype`).
- Duplicar (própria ou pública), respeitando a cota.
- “Uso”: quantas vezes **outros** usuários duplicaram a paleta; cumulativo e não decresce ao excluir a cópia (ADR-124).
- Arquivar (ADR-119): some da lista padrão, aparece em filtro próprio, URL pública responde 404, libera o slug e a paleta continua restaurável. Conta na cota Free.
- Restaurar: reivindica o slug anterior se ainda estiver livre, senão gera um novo do nome.
- Excluir (definitivo, exige digitar o nome exato; libera cota e slug, não pode ser desfeito).
- Compartilhar (cópia da URL pública quando a paleta é pública).
- Ver protótipo.
- Gerenciar tags: renomear reflete em todas as paletas; reordenar vale para a conta (ADR-103).

Toda escrita passa por `throttle:colors-management`; paleta de outra conta responde 404.
