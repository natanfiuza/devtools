# Paleta pública

## URL
`/{locale}/colors/p/{slug}` — o mesmo slug em todos os locales; o `{slug}` é global, único, `[a-z0-9-]`, até 60 caracteres e imutável ao renomear (ADR-124).

## Recursos (Fase 4, implementados em 2026-11-11)

- Visualização das cores efetivas persistidas (escala de 11 tons ou paleta extraída), com tags, tipo e “Uso”.
- Cópia e download (escala reusa os exportadores; extraída copia HEX/JSON).
- “Testar nova base”: recalcula só em memória, sem tocar na URL, no canonical nem na OG.
- Duplicar: autenticado, via intent para o login; duplica o que está exibido (a base experimentada vira `kind=scale`), nasce privada, sem OG, e respeita a cota.
- Compartilhar (URL).
- SEO: title, description, canonical e hreflang **sem** query string.
- OG image dinâmica 1200×630, gerada no cliente e servida só para paleta pública ativa; fallback para a OG padrão do site (ADR-125).
- Tags.
- Estrela de favorito (Fase 5, ADR-128): o servidor entrega `canFavorite` e `favorited` e o botão alterna `POST`/`DELETE /api/tools/colors/palettes/{palette}/favorite`. Favoritar é só um vínculo da conta com a paleta: não copia, não consome cota, não conta “Uso” e aparece na aba **Favoritas** de Minhas Paletas.
- Byline do autor (Fase 6, ADR-134): nome, avatar e link para
  `/{locale}/u/{username}` quando a conta tem username e continua visível.
- Curtir (Fase 6, ADR-135): `liked`/`canLike` e `likes_count`; a conta curte pelo
  login e o visitante por identidade anônima first-party, sem identificador na
  resposta. O dono não curte a própria paleta.
- Seguir o autor (Fase 6, ADR-136): `following`/`canFollow`; exige login e o
  botão aparece junto à byline.
- Gerar imagem (Fase 6, ADR-138): seção recolhível com os três formatos
  tipográficos, disponível também a visitante; nada é enviado ao servidor.

## Regras

- Apenas paletas públicas e ativas; privada, arquivada ou inexistente respondem **404 uniforme** (sem revelar a existência).
- Slug único e compartilhado entre locales.
- Canonical sem `?duplicate=1` e sem qualquer query.
- OG image enviada como imagem pela paleta e validada no servidor; a URL leva `?v={og_version}`.
- Favoritar exige conta: a rota é autenticada, a própria paleta responde **403** e paleta que não é pública/ativa responde **404** (o mesmo 404 da página) — favoritar nunca revela o que não está no ar.
- A página mostra autor visível, curtir, seguir e a seção de imagem desde a F6
  (ADR-134 a ADR-138). Comentários, respostas, denúncia e moderação continuam
  fora (F7); protótipo não é exibido nesta página.
- A estrela de favorito da F5 é um vínculo da conta, independente da curtida
  anônima/conta: as duas relações coexistem sem se influenciar.

## Fora desta fase

- Comentários em threads de 2 níveis (ADR-111), denúncia e moderação: F7.
- E-mail, sino e preferências de notificação: F7.
- Lista de seguidores/seguindo e bloqueio: não implementados.
