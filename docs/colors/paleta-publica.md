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

## Regras

- Apenas paletas públicas e ativas; privada, arquivada ou inexistente respondem **404 uniforme** (sem revelar a existência).
- Slug único e compartilhado entre locales.
- Canonical sem `?duplicate=1` e sem qualquer query.
- OG image enviada como imagem pela paleta e validada no servidor; a URL leva `?v={og_version}`.
- A página **não** mostra autor nem controles sociais (curtir/comentar/seguir/denunciar) — esses pertencem às fases F6/F7 (ADR-098, ADR-124). Também não mostra protótipo.

## Fora desta fase

- Curtir (inclusive anônimo, ADR-112): F6.
- Seguir autor: F6.
- Comentários em threads de 2 níveis (ADR-111) e denúncia: F7.
- Autor na página pública: fora de escopo.
