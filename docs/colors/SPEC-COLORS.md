# SPEC - Grupo Colors (v1.0)

## 1. Visão
Grupo Colors do devtools com HTML Color Picker e Color Palette Generator.
Processamento local no navegador. Backend para contas, paletas, presets, favoritos, OG images, social, moderação e analytics.

## 2. URLs
- /tools/colors
- /tools/colors/html-color-picker
- /tools/colors/palette-generator
- /colors/p/{slug} (paleta pública)
- /dashboard/colors/palettes (Minhas Paletas)

## 3. Formatos
HEX, RGB/RGBA, HSL/HSLA, OKLCH, OKLab, nomes CSS. Conversões bidirecionais.

## 4. HTML Color Picker
- Input color.
- EyeDropper (lupa) com fallback.
- Preview.
- Sliders.
- Contraste WCAG.
- Shades/tints.
- Histórico ilimitado.
- Favoritos.
- Copiar.
- Alpha.

## 5. Palette Generator
- Cor base.
- Escala: anônimo 50-950; cadastrado 5,7,9,11 tons.
- Harmonias HSL e OKLCH.
- Exportações: HEX, RGB, HSL, OKLCH, OKLab, CSS, SCSS, JSON, Tailwind v3/v4, Figma Tokens, Style Dictionary, código.
- Importação de imagem e extração de paleta (5,7,9,11 cores).
- Importação de JSON (Figma/Style Dictionary).
- Importação por URL de imagem.
- Presets.
- Favoritos.

## 6. Paletas salvas
- Apenas cadastrados.
- Nome, descrição opcional, tags, visibilidade.
- Limite: free 25, pago ilimitado.
- Versão do algoritmo registrada.
- Duplicar paleta pública.
- Renomear e reordenar tags.
- Arquivar paleta (soft delete).
- Favoritar paleta de outro usuário.
- Seguir usuário.
- Curtir paleta pública (anônimo por IP hash).
- Comentar paleta pública (threads, apenas logados).

## 7. Compartilhamento
- Paleta pública: /colors/p/{slug}
- SEO: title, description, OG image.
- Duplicar para editar.
- Compartilhar por URL.
- OG image gerada no cliente, enviada e salva em public/og.
- OG image dinâmica por paleta.
- Regenerar OG image manualmente.

## 8. Protótipos
- Site e mobile genéricos.
- Light/Dark.
- Papéis editáveis.
- Exportar PNG 1x e 2x.
- Exportar SVG.
- Apenas cadastrados.
- Mobile usa apenas tabs.
- Site inclui apenas: header, hero, botões, links, cards, formulário, footer.

## 9. Importação de imagem
- Upload local apenas (Canvas).
- Usuário escolhe 5, 7, 9 ou 11 cores.
- Median cut com opção OKLab.
- Sem envio ao servidor.
- Permitir salvar paleta extraída diretamente.
- Importar paleta a partir de URL de imagem.

## 10. Exportação de paleta como imagem
- Formato tipográfico.
- PNG e SVG.
- Cliente gera, usuário baixa.
- Marca d'água fixa do devtools.
- Layouts: horizontal, vertical, card.

## 11. Social
- Curtir paleta pública (anônimo por IP hash).
- Comentar paleta pública (threads, apenas logados).
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
- Cache permanente em ai_caches (nunca expira).
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
- Vitest: conversões, contraste, escalas, harmonias, exportadores, importação, extração, OG image, export paleta imagem.
- Pest: auth, CRUD, limites, visibilidade, slug, archive, likes, comments, follows, reports, moderação.

## 16. Fases
F1: grupo + HTML Color Picker completo.
F2: Palette Generator básico + escalas + exportações.
F3: harmonias + contraste + importação/exportação imagem.
F4: salvamento + protótipos + compartilhamento + OG image.
F5: presets, favoritos, tokens, exportação avançada.
F6: social (curtir, comentar, seguir) + exportação paleta como imagem.
F7: moderação IA + denúncias + notificações.

## 17. Critérios de aceite
- EyeDropper com fallback.
- Contraste correto.
- Escala correta.
- Exportações válidas.
- i18n completo.
- Testes verdes.
- Limites aplicados.
- SEO da paleta pública.
- OG image gerada no cliente e salva em public/og.
- Protótipo exportável em PNG e SVG.
- Paleta exportável como imagem tipográfica (PNG/SVG).
- Curtir, comentar (threads), seguir funcionando.
- Moderação DeepSeek com cache permanente.
- Notificações e-mail + app imediatas.
