# ADR-124 - Página pública, SEO, sitemap e duplicação
Status: Aceito
Data: 2026-11-11
Relacionados: ADR-098 (paleta pública com SEO, emenda), ADR-105 (OG, emenda), ADR-106 (social, emenda), ADR-109 (busca server-side, emenda), ADR-119 (arquivamento, emenda), ADR-123 (persistência), ADR-125 (OG dinâmica)

## Contexto
A ADR-098 fixou que a paleta pública teria página com SEO, OG e duplicação, mas sem decidir canonical/hreflang, o que entra no sitemap, o que a página mostra (e o que não mostra) e o que "duplicar" copia. A F4 precisou fechar isso junto com a regra de "uso" (`duplications_count`) e a fronteira entre o que é da F4 e o que é social (F6/F7).

## Decisão

### 1. Uma URL por slug, em todos os idiomas

`GET /{locale}/colors/p/{slug}` (nome `colors.palettes.show`), slug global (`[a-z0-9-]{1,60}`) compartilhado entre locales. A página só resolve paleta `publiclyVisible()`: visibilidade pública, `archived_at` nulo e slug não nulo. Privada, arquivada e slug inexistente respondem o **mesmo 404** — a visibilidade não vaza. Slug inválido nem casa com a rota.

Canonical e hreflang vêm de `Locales::alternates()`, agora **sem a query string**: `?duplicate=1` (e qualquer filtro) muda a tela, não a página, e o `og:url` usa o mesmo valor. O `x-default` continua sendo o idioma de fallback.

### 2. Metas só com dados públicos

`Seo::paletteMeta()` usa o nome como título e a descrição como `description`; sem descrição, monta o texto traduzido com a contagem de cores e as tags (`colors.meta.description*`). Nenhum dado de paleta privada ou arquivada entra em meta — o resolvedor usa a mesma consulta da rota.

A OG própria só é anunciada quando `og_version` existe **e** o arquivo está no disco (`PaletteOgStorage::exists`), com `?v={og_version}`, `1200×630` e `alt` traduzido (`colors.meta.image_alt`); caso contrário, a página cai no OG padrão do site. A miniatura nunca aponta para um arquivo que já foi invalidado.

### 3. Sitemap com teto configurável

Depois das tools, o sitemap inclui paletas `publiclyVisible()`, ordenadas por `updated_at desc`, limitadas por `colors.palettes.sitemap_limit` (500, `COLORS_PALETTES_SITEMAP_LIMIT`), cada uma com as cinco alternates. O índice `(visibility, archived_at, updated_at)` sustenta a consulta e só `slug` é selecionado. Privadas e arquivadas nunca entram, e `limit <= 0` desliga o bloco.

### 4. A página é a paleta, não o perfil

A página mostra nome, descrição, tags, tipo, contagem, "Uso", data, as cores persistidas, cópia e download (escala reusa os exportadores; extraída copia HEX/JSON). **Não** mostra autor, curtidas, comentários, seguidores nem protótipo — essas decisões são F6/F7 (ADR-106/ADR-111/ADR-112) e o protótipo é `dashboard`.

"Testar nova base" recalcula a escala **só em memória**; URL, canonical, meta e OG continuam os da paleta publicada. O botão "Duplicar" envia o que está na tela: com base experimentada, o corpo leva o retrato da tela (`kind=scale`), então a duplicação vira uma paleta nova com aquela base.

### 5. Duplicação autenticada, respeitando cota, nascendo privada

`POST /{locale}/colors/p/{slug}/duplicate` (auth; intent `GET .../duplicate` para quem não está logado, voltando com `?duplicate=1`) e a duplicação da própria paleta no dashboard chamam `DuplicatePalette`:

- mesmas regras de cota da criação (transação + lock da linha do usuário);
- copia `colors`, `kind`, `algorithm_version`, `roles`, `source` e `colors_count`; com override usa o conjunto enviado;
- nome `"{nome} (cópia)"` truncado em 80 (`PaletteName`);
- nasce `private`, sem slug, sem OG, com `duplicated_from_id`;
- tags: upsert nas tags **do duplicador**, respeitando 10.

**"Uso" (`duplications_count`)** incrementa **somente** quando quem duplica não é o dono, dentro da mesma transação; é cumulativo e não decresce quando a cópia é excluída. Duplicar a própria paleta não infla a métrica.

### 6. Escritas com throttle por conta

Mutações do dashboard usam `throttle:colors-management` (60/min por conta) e o upload de OG tem `throttle:colors-og` (10/min). Os limites vivem em `colors.rates`, com override por `.env`.

## Consequências
- A página pública é indexável e estável: canonical/hreflang sem query, meta só com o que é público e sitemap com teto.
- A 404 uniforme impede descobrir paletas privadas ou arquivadas por tentativa.
- Duplicar é seguro: cota, lock, cópia fiel e métrica de "uso" que mede adoção real, não autocópia.
- Sem autor nem social na F4, a página é mais simples e não antecipa decisões da F6/F7.
- O custo é canonical sem query em todas as páginas (nenhuma dependia disso), intent de duplicação para anônimos e um override de payload para o experimento local.

## Alternativas rejeitadas
- **Autor na página pública**: exporia conta sem pedido e anteciparia perfil/seguir (F6).
- **Slug por idioma**: multiplicaria URLs da mesma paleta e complicaria canonical e OG.
- **Incluir query no canonical**: `?duplicate=1` criaria duplicatas de indexação.
- **Sitemap sem limite**: cresce com o acervo e sem índice próprio; o teto configurável resolve enquanto o volume é pequeno.
- **Incrementar "uso" também na autocópia**: mediria organização pessoal, não adoção por outros.
- **Gerar a OG no servidor**: contraria o processamento no cliente e a ADR-125; o servidor só valida e guarda.
