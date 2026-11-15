# SPEC - Grupo Colors (v1.5)

## 1. Visão
Grupo Colors do devtools com HTML Color Picker e Color Palette Generator.
Processamento local no navegador. Backend para contas, paletas, presets, favoritos, OG images, social, moderação e analytics.

## 2. URLs
As rotas públicas do site são prefixadas por `{locale}`. Os códigos de idioma aparecem como `pt-BR`/`pt-PT` na documentação, `pt_BR`/`pt_PT` no código e `pt-br`/`pt-pt` no slug da URL.

- `/{locale}/tools/colors` — landing do grupo (fora da Fase 1)
- `/{locale}/tools/colors/html-color-picker`
- `/{locale}/tools/colors/palette-generator`
- `/{locale}/colors/p/{slug}` (paleta pública)
- `/{locale}/u/{username}` (perfil público, Fase 6 — ADR-134)
- `/dashboard/colors/palettes` (Minhas Paletas) — o dashboard **não** tem prefixo de locale (ADR-022)
- `/dashboard/notifications` (notificações e preferências da conta — compartilhada por todas as tools; Fase 7, ADR-142)

## 3. Formatos
HEX, RGB/RGBA, HSL/HSLA, OKLCH, OKLab, nomes CSS. Conversões bidirecionais.

## 4. HTML Color Picker
- Input color.
- EyeDropper (lupa) com fallback.
- Preview.
- Sliders.
- Contraste WCAG.
- Shades/tints.
- Histórico de cadastrado no backend, sem limite funcional e paginado por cursor (Fase 5, ADR-127); o anônimo mantém histórico local reduzido a 20 (ADR-132).
- Favoritos de cor da conta, sincronizados entre Picker e Generator (Fase 5, ADR-127, ADR-131); o anônimo mantém 50 favoritos locais (ADR-132).
- Copiar.
- Alpha.

## 5. Palette Generator
- Cor base.
- Escala: sempre os 11 tons fixos `50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950` (ADR-101). A escolha de 5, 7, 9 ou 11 pertence somente à extração de imagem (§9, ADR-099).
- Harmonias HSL e OKLCH.
- Exportações: HEX, RGB, HSL, OKLCH, OKLab, CSS, SCSS, JSON, Tailwind v3/v4, tokens W3C DTCG (importáveis por Tokens Studio e Figma Variables), Style Dictionary, TypeScript e código. Primitivas (11 tons) e, quando o editor fornece papéis, semânticas (8 papéis × claro/escuro); versão do formato 1; sem API do Figma (Fase 5, ADR-130).
- Importação de imagem e extração de paleta (5, 7, 9, 11 cores).
- Importação validada de tokens: JSON nativo, W3C DTCG e Style Dictionary, com limites, diagnóstico e sem executar o arquivo (Fase 5, ADR-133).
- Importação por URL de imagem.
- Presets do gerador (nome + cor base) por conta, sem consumir cota de paleta (Fase 5, ADR-129).
- Favoritos de cor da conta, compartilhados pelas duas tools, e de paletas públicas de outras contas (Fase 5, ADR-127, ADR-128).

## 6. Paletas salvas
- Apenas cadastrados.
- Nome, descrição opcional, tags, visibilidade.
- Limite: Free 25 paletas; pago sem limite funcional (ADR-096).
- Versão do algoritmo registrada.
- Cores efetivas persistidas — escala (base + 11 tons) ou extraída (base + 5/7/9/11 cores + origem sem imagem) — e nunca recalculadas ao abrir. Papéis semânticos fixos (8 × claro/escuro) salvos na paleta e editáveis (ADR-123, ADR-126).
- Tags como entidade do usuário: renomear reflete em todas as paletas e reordenar vale para a conta (ADR-103).
- Duplicar paleta pública ou própria, respeitando a cota; “Uso” é o número de duplicações feitas por **outros** usuários (ADR-124).
- Rascunho anônimo em `sessionStorage` (24 h, base/tipo/cores extraídas/aba e, desde a Fase 5, escala e papéis opcionais) que leva ao login (ADR-096, ADR-123, emenda de 2026-11-14).
- Renomear e reordenar tags.
- Arquivar paleta (ADR-119): some da lista padrão (filtro próprio a mostra), URL pública responde 404 e a paleta continua restaurável. Paleta arquivada **conta** na cota Free. Excluir é definitivo, libera cota e não pode ser desfeito.
- Favoritar paleta pública de outro usuário, sem copiar nem contar “Uso”; a aba **Favoritas** mostra o que ainda é público e ativo (Fase 5, ADR-128).
- Seguir conta (login obrigatório, perfil público em `/{locale}/u/{username}`; Fase 6, ADR-136).
- Curtir paleta pública, inclusive anônimo, por identificador anônimo assinado de primeira parte; IP só como sinal antiabuso e chave do limitador (Fase 6, ADR-135).
- Comentar paleta pública (threads de no máximo 2 níveis, login + conta verificada e ativa, máximo de 255 caracteres; Fase 7, ADR-111 e ADR-139) com edição e exclusão pelo autor.
- Denunciar comentário de outra conta, com motivo em lista fixa e uma denúncia por par comentário/denunciante (Fase 7, ADR-141).

## 7. Compartilhamento
- Paleta pública: `/{locale}/colors/p/{slug}`
- SEO: title, description, OG image.
- Duplicar para editar.
- Compartilhar por URL.
- OG estática das páginas de tools: gerada pelo comando existente `php artisan seo:og-image` e versionada em `public/og/<slug>.png`.
- OG dinâmica por paleta: gerada no cliente pelo template `og` (1200×630), enviada autenticada, validada (PNG, dimensões exatas, ≤ 2 MiB) e salva em `storage/app/data/og_paleta_image`; nunca versionada no Git. Só é servida para paleta pública ativa e é invalidada por mudança visual (ADR-125).
- Regenerar OG image manualmente.

## 8. Protótipos
- Site e mobile genéricos.
- Light/Dark.
- Papéis editáveis: 8 papéis fixos (`primary`, `secondary`, `accent`, `background`, `surface`, `text`, `text_muted`, `border`) × claro/escuro, persistidos na paleta e compartilhados entre site e mobile (ADR-126).
- Exportar PNG 1x e 2x.
- Exportar SVG vetorial (o PNG é a rasterização do mesmo SVG).
- Apenas cadastrados.
- Mobile usa apenas tabs.
- Site inclui apenas: header, hero, botões, links, cards, formulário, footer.

## 9. Importação de imagem
- Upload local apenas (Canvas).
- Usuário escolhe 5, 7, 9 ou 11 cores.
- Median cut com opção OKLab.
- Sem envio ao servidor.
- Permitir salvar paleta extraída diretamente (Fase 4 — implementado; salva mesmo com `sufficient: false`, com aviso, e a paleta reabre com as cores efetivas, sem re-quantizar).
- Importar paleta a partir de URL de imagem.

## 10. Exportação de paleta como imagem

- Formato tipográfico com o nome, cada HEX (HEX8 com alpha aceito) e a marca fixa
  `devtools.natanfiuza.dev.br` (Fase 6, ADR-138).
- PNG (1× e 2×) e SVG, do mesmo documento; a fonte é embutida só na exportação.
- Layouts com medidas fixas: horizontal 1600×500, vertical 1080×1920 e card
  1080×1080.
- Cliente gera e baixa, no editor e na página pública; nada é enviado ao
  servidor e o SVG é sanitizado por construção (sem referência externa).
- Nome truncado com `…` (26/22/22 caracteres) e cores inválidas descartadas.

## 11. Social

- Curtir paleta pública, inclusive anônimo, por identificador anônimo assinado de
  primeira parte (Fase 6, ADR-112 e ADR-135); IP apenas como chave HMAC do
  limitador, nunca gravado nem devolvido.
- Reivindicar as curtidas anônimas no login, sem duplicar e com uma notificação
  por relação (Fase 6, ADR-135).
- Seguir conta com login obrigatório e perfil público (Fase 6, ADR-134 e
  ADR-136).
- Favoritar paleta (Fase 5, ADR-128), independente da curtida.
- Notificações gravadas depois do commit e com no máximo um aviso por relação
  (Fase 6, ADR-137; Fase 7, ADR-142). O canal `database` é sempre ligado e o
  `mail` entra por preferência da conta, por tipo: curtida e novo seguidor
  desligados por padrão, comentário aprovado e resposta aprovada ligados.
- Comentário aprovado avisa o dono da paleta; resposta aprovada avisa o autor do
  comentário pai e o dono, deduplicados e nunca o autor da ação.
  `pending`/`flagged` não notificam; a rejeição não gera aviso social.
- Sino com contador de não lidas por prop Inertia (sem polling) e página única
  `/dashboard/notifications` com a lista e os toggles de e-mail.
- Silenciar paleta pelo dono: corta curtida, comentário e resposta daquela paleta
  nos dois canais, avaliado no envio; follows não são afetados (ADR-142).
- Bloqueio entre contas continua não implementado.
- Comentário `pending` ou `flagged` é visível com o selo "em análise" — nunca
  rotulado como aprovado —, e `rejected` sem versão aprovada anterior não
  aparece. Editar volta a `pending`; se a revisão for rejeitada, volta ao texto
  de `approved_body`. Excluir o pai com respostas deixa tombstone ("comentário
  removido"); sem respostas, some (ADR-139).

## 12. Moderação

- Filtro de palavras local e síncrono, por idioma em
  `resources/colors/moderation/{locale}.txt` com fallback para inglês; compara
  n-gramas de palavras após normalização, nunca substring, e responde 422
  genérico sem gravar nem ecoar o termo (Fase 7, ADR-140).
- IA moderadora DeepSeek em job na fila, com prompt versionado e o comentário
  tratado como dado não confiável; contrato JSON estrito
  (`decision` + `category`), sem chamar a cota da conta.
- Cache em `ai_cache` (singular; a tabela real do projeto), separado por
  `purpose = colors.comment_moderation`. "Permanente" é `expires_at = NULL`;
  `COLORS_COMMENT_CACHE_TTL_DAYS` define validade e o expurgo é
  `ai:prune-cache --purpose=colors.comment_moderation --all`.
- Fila assíncrona com resultado aplicado por UPDATE condicional
  (`revision` + `status`): resultado antigo não aprova revisão nova.
- Status: `pending`, `approved`, `rejected`, `flagged`. Falha do provedor,
  contrato inválido ou retries esgotados viram `flagged`, nunca aprovação.
- Admin revisa `pending`/`flagged`, aprova, rejeita e exclui, com auditoria sem
  o texto.
- Limite: 255 caracteres por comentário.
- Flag `COLORS_COMMENTS_ENABLED` desligada por padrão: sem thread na página e
  404 uniforme nos endpoints de comentário, denúncia e mute (ADR-143).

## 13. Analytics

- GA na interface.
- As Fases 6 e 7 não criaram eventos de analytics: curtidas, seguidores e
  seguindo são lidos como linhas ativas, não como histórico de eventos. Eventos
  de criação, salvamento, exportação, visualização pública, curtida, comentário,
  follow e denúncia permanecem para a fase que abrir analytics.

## 14. Frontend
Componentes implementados: ColorSwatch, ContrastBadge, ColorInput, ShadeScale,
PaletteCard, PrototypeSite, PrototypeMobile, ExportPanel, TagInput,
FollowButton, LikeButton, ProfilePaletteCard, PaletteImagePanel, CommentThread,
CommentForm, CommentItem, CommentReportDialog, MutePaletteButton e
NotificationBell. As abas de admin de comentários e denúncias usam `AdminTable`.

## 15. Testes

- Vitest: conversões, contraste, escalas, harmonias, exportadores, importação de
  imagem e de tokens (parser, diálogo, workspace, rascunho), biblioteca pessoal,
  favoritos de paletas, presets, extração, OG image, export da paleta como
  imagem (dimensões, marca, HEX/HEX8, escaping, sanitização, determinismo,
  fonte só na exportação, 1×/2×), curtir/seguir otimistas, perfil público,
  thread de comentários (estados, selo, tombstone, denúncia), sino e página de
  notificações, e integração nas duas telas.
- Pest: auth, CRUD, limites, visibilidade, slug, archive, biblioteca
  (histórico/favoritos/importação explícita), favoritos de paletas, presets,
  fixtures de tokens validadas contra `PaletteRules`, curtidas (idempotência,
  concorrência, anônimo e privacidade), follows, reivindicação no login, perfil
  público, SEO, comentários (autorização, thread, revisão concorrente,
  tombstone, flag), filtro de palavras nos cinco idiomas, cache de moderação,
  denúncias (dedupe, resolução automática), preferências e silenciar, admin
  de moderação e denúncias, e exportação/anonimização da conta com o bloco
  `colors` (F8, ADR-144).
- Vitest da F8: `admin-comment-reports.test.ts` cobre as linhas da fila de
  denúncias (corpo, motivo, denunciante, status, data, selo de excluído),
  as URLs de resolver/dispensar e os botões só em denúncia pendente.

## 16. Fases
F1: grupo + HTML Color Picker completo (histórico e favoritos locais).
F2: Palette Generator — escala fixa de 11 tons, exportações CSS/SCSS/JSON/Tailwind v3/v4.
F3: harmonias + importação de imagem e de URL com extração de 5/7/9/11 cores.
F4: paletas salvas + protótipos + compartilhamento + OG dinâmica — **implementada em 2026-11-11** (ADR-123 a ADR-126).
F5: organização pessoal (histórico e favoritos no backend, biblioteca compartilhada, presets) + tokens W3C DTCG/Style Dictionary + exportação e importação validadas — **implementada em 2026-11-14** (ADR-127 a ADR-133).
F6: social (curtir anônimo, seguir, perfil público) + exportação de paleta como imagem — **implementada em 2026-11-14** (ADR-134 a ADR-138).
F7: comentários + moderação IA + denúncias + notificações (sino, preferências e e-mail) — **implementada em 2026-11-15** (ADR-139 a ADR-143), com `COLORS_COMMENTS_ENABLED` desligada por padrão.
F8: auditoria final e relatório de aceite — **implementada em 2026-11-15** (ADR-144), com o aceite em `docs/acceptance/COLORS-FASE-8.md` e `COLORS_COMMENTS_ENABLED` ainda desligada por padrão.

## 17. Critérios de aceite
- EyeDropper com fallback.
- Contraste correto.
- Escala correta.
- Exportações válidas.
- Biblioteca pessoal: histórico sem limite funcional e favoritos da conta, iguais nas duas tools; anônimo com 20/50 e importação explícita do histórico.
- Favoritos de paletas públicas sem duplicata, sem cópia e sem contar “Uso”; aba Favoritas coerente com a visibilidade atual.
- Presets do gerador entendidos como entrada (nome + base), sem consumir cota de paleta.
- Exportação de tokens primitivos e semânticos com versão de formato, alpha e gamut documentados; importação validada dos três formatos com diagnóstico sem descartar o estado e sem executar o arquivo.
- i18n completo.
- Testes verdes.
- Limites aplicados.
- SEO da paleta pública.
- OG estática das tools versionada em `public/og`; OG dinâmica de paleta em `storage/app/data/og_paleta_image`, nunca versionada.
- Protótipo exportável em PNG e SVG.
- Paleta exportável como imagem tipográfica (PNG/SVG) nos três formatos, com
  marca fixa, fonte embutida e nenhuma referência externa.
- Curtir (inclusive anônimo), seguir e perfil público funcionando; curtidas
  idempotentes, contagem só de linhas ativas e nenhum identificador na API.
- Curtidas anônimas reivindicadas no login, sem duplicar e sem segundo aviso.
- Notificações de curtida e follow gravadas no canal `database`, uma por relação;
  e-mail por preferência, sino com contador e página única de notificações.
- Comentários em thread de dois níveis, apenas para conta logada, verificada e
  ativa; `pending`/`flagged` visíveis com selo e nunca como aprovados; edição
  rejeitada volta ao texto aprovado anterior; exclusão de pai com respostas deixa
  tombstone e o admin mantém o texto.
- Filtro local bloqueia antes da IA com 422 genérico; falha da IA nunca aprova;
  cache permanente é `expires_at = NULL` e pode ser expurgado por `purpose`.
- Denúncia única por par, motivo em lista fixa, sem esconder o comentário;
  rejeitar/excluir resolve as pendentes.
- Comentário aprovado e resposta aprovada notificam uma vez, sem o corpo do
  comentário; silenciar a paleta corta curtida, comentário e resposta nos dois
  canais.
- Flag desligada: thread ausente e endpoints de comentário/denúncia/mute em 404
  uniforme.
- Exportação da conta inclui o bloco `colors` completo (paletas, biblioteca,
  favoritos de paleta, curtidas e follows ativos, comentários inclusive
  excluídos, denúncias feitas, preferências e paletas silenciadas) sem
  `moderation_reason`, sem `notified_at` e sem seguidores.
- Anonimização apaga biblioteca, presets, favoritos, curtidas da conta, follows
  de saída e tags; paletas, comentários e denúncias ficam com autoria `NULL` e
  nenhuma paleta é excluída (FKs em cascata).
- Auditoria final registra a matriz requisito → implementação → teste e as
  pendências por gravidade no aceite e na ADR-144; não há quebra de regressão
  em BadgeForge, AI ou demais ferramentas.
