# SPEC - Grupo Colors (v1.2)

## 1. Visão
Grupo Colors do devtools com HTML Color Picker e Color Palette Generator.
Processamento local no navegador. Backend para contas, paletas, presets, favoritos, OG images, social, moderação e analytics.

## 2. URLs
As rotas públicas do site são prefixadas por `{locale}`. Os códigos de idioma aparecem como `pt-BR`/`pt-PT` na documentação, `pt_BR`/`pt_PT` no código e `pt-br`/`pt-pt` no slug da URL.

- `/{locale}/tools/colors` — landing do grupo (fora da Fase 1)
- `/{locale}/tools/colors/html-color-picker`
- `/{locale}/tools/colors/palette-generator`
- `/{locale}/colors/p/{slug}` (paleta pública)
- `/dashboard/colors/palettes` (Minhas Paletas) — o dashboard **não** tem prefixo de locale (ADR-022)

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
- Seguir usuário.
- Curtir paleta pública, inclusive anônimo, por identificador anônimo assinado de primeira parte; IP só como sinal antiabuso (ADR-112).
- Comentar paleta pública (threads de no máximo 2 níveis, apenas logados; ADR-111).

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
- Formato tipográfico.
- PNG e SVG.
- Cliente gera, usuário baixa.
- Marca d'água fixa do devtools.
- Layouts: horizontal, vertical, card.

## 11. Social
- Curtir paleta pública, inclusive anônimo, por identificador anônimo assinado de primeira parte (ADR-112).
- Comentar paleta pública (threads de no máximo 2 níveis, apenas logados; ADR-111).
- Seguir usuário (login obrigatório).
- Favoritar paleta.
- Denunciar comentário (login).
- Editar e excluir próprio comentário.
- Notificações: curtida, comentário, resposta, follow.
- Notificação imediata por e-mail e no app.
- Silenciar notificações de paleta específica.

## 12. Moderação
- Filtro de palavras local.
- IA moderadora: DeepSeek.
- Cache em `ai_cache` (singular; a tabela real do projeto). A decisão de cache "permanente" exige definir na Fase 7 um sentinela de `expires_at`, pois a coluna é obrigatória.
- Fila assíncrona.
- Status: pending, approved, rejected, flagged.
- Admin revisa flagged.
- Limite: 255 caracteres por comentário.

## 13. Analytics
- GA na interface.
- Banco registra: criação, salvamento, exportação, visualização pública, curtida, comentário, follow, denúncia.

## 14. Frontend
Componentes: ColorSwatch, ContrastBadge, ColorInput, ShadeScale, PaletteCard, PrototypeSite, PrototypeMobile, ExportPanel, TagInput, CommentThread, FollowButton, LikeButton, ReportButton, NotificationBell.

## 15. Testes
- Vitest: conversões, contraste, escalas, harmonias, exportadores, importação de imagem e de tokens (parser, diálogo, workspace, rascunho), biblioteca pessoal, favoritos de paletas, presets, extração, OG image, export paleta imagem.
- Pest: auth, CRUD, limites, visibilidade, slug, archive, biblioteca (histórico/favoritos/importação explícita), favoritos de paletas, presets, fixtures de tokens validadas contra `PaletteRules`, likes, comments, follows, reports, moderação.

## 16. Fases
F1: grupo + HTML Color Picker completo (histórico e favoritos locais).
F2: Palette Generator — escala fixa de 11 tons, exportações CSS/SCSS/JSON/Tailwind v3/v4.
F3: harmonias + importação de imagem e de URL com extração de 5/7/9/11 cores.
F4: paletas salvas + protótipos + compartilhamento + OG dinâmica — **implementada em 2026-11-11** (ADR-123 a ADR-126).
F5: organização pessoal (histórico e favoritos no backend, biblioteca compartilhada, presets) + tokens W3C DTCG/Style Dictionary + exportação e importação validadas — **implementada em 2026-11-14** (ADR-127 a ADR-133).
F6: social (curtir anônimo, seguir) + exportação de paleta como imagem.
F7: comentários + moderação IA + denúncias + notificações.
F8: auditoria final e relatório de aceite.

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
- Paleta exportável como imagem tipográfica (PNG/SVG).
- Curtir, comentar (threads de 2 níveis), seguir funcionando.
- Moderação DeepSeek com cache em `ai_cache`.
- Notificações e-mail + app imediatas.
