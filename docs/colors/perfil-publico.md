# Perfil público

Implementado na Fase 6 (2026-11-14, ADR-134).

## URL

`/{locale}/u/{username}` (rota `colors.profiles.show`), com `username` casando
`[a-z0-9_]{3,30}`. O username é global; a URL antiga de um username trocado
responde 404 e não redireciona.

## Conteúdo

- Cabeçalho: avatar (quando a conta tem foto), nome, `@username`, data de entrada
  e os contadores `followers_count`/`following_count`.
- Botão de seguir quando quem olha está logado e não é o dono; o visitante não vê
  o botão. A contagem acompanha a escrita otimista.
- Grade de paletas `publiclyVisible()` do autor, ordenada por `updated_at desc`,
  com prévia, nome e curtidas ativas; paginação `colors.social.profile_per_page`
  (12, `COLORS_PROFILE_PER_PAGE`) preservando a query.
- Sem lista de seguidores/seguindo, sem bio, sem e-mail, sem dados de conta.

## Regras

- Conta inexistente, suspensa, anonimizada ou sem username responde o **mesmo
  404**.
- A página é indexável, com canonical/hreflang, mas **não** entra no sitemap.
- As paletas listadas respeitam a visibilidade atual: despublicar ou arquivar
  remove da grade; voltar a pública reaparece.
- O autor de cada paleta é o mesmo `ProfilePresenter::author()` da página pública
  da paleta; conta invisível não vira byline.
- Curtidas e follows são contados por linhas ativas, não por contador
  denormalizado.

## Fora desta fase

- Editar perfil, username ou bio.
- Listas de seguidores e seguindo.
- Bloqueio.
- Notificações visíveis (o banco grava, mas não há sino na F6).
