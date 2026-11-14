# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona biblioteca de cores compartilhada com favoritos da conta
```

---

## 🎯 Resumo

Antes, histórico e favoritos do HTML Color Picker viviam apenas no `localStorage` de cada navegador, isolados entre as tools e perdidos ao trocar de dispositivo. Esta fase (F5, ADR-127/ADR-128/ADR-132) move essas listas para a conta de quem está logado, compartilhando-as entre o Color Picker e o Color Palette Generator, e adiciona favoritos de paletas públicas com aba dedicada em Minhas Paletas. O anônimo continua sem tocar no servidor (ADR-118) e passa a ter a opção explícita de importar o histórico local ao entrar.

## 🛠️ Mudanças Técnicas

- Novos models `ColorHistoryEntry`, `ColorFavorite` e `ColorPaletteFavorite`, com relações `colorHistoryEntries`, `colorFavorites` e `favoritePalettes` em `User`.
- Actions idempotentes (`RecordColorHistory`, `FavoriteColor`, `FavoritePalette`, e as contrapartes `Unfavorite*`) com tratamento de corrida via `UniqueConstraintViolationException`; `ImportColorHistory` usa `upsert` e tolera lotes repetidos.
- Controllers JSON em `App\Http\Controllers\Api\Colors` para histórico, favoritos de cores e de paletas, todos protegidos por `auth/verified/active` e por um limiter `colors-library` dedicado.
- FormRequests normalizam a entrada para a chave canônica `#rrggbbaa` ou `transparent` via `ColorKey::normalize`.
- `PaletteController@index` aceita `?tab=favorites` e devolve o paginador `favorites` (só paletas públicas/ativas via `publiclyVisible()`), enquanto `PublicPaletteController@show` expõe `favorited` e `canFavorite`.
- Frontend extrai `useColorLibrary` (`lib/colors/library.ts`) e o cliente `colorsApi` (`lib/colors/api.ts`), com escrita otimista e reconciliação na falha; componentes `FavoritesPanel` e `HistoryPanel` migram para `components/colors/` e passam a ser usados pelas duas tools.
- Novo `ImportLocalHistoryDialog` e botão de importação explícita, com envio em lotes por `importBatch` e limpeza do `localStorage` só após sucesso.
- Página pública de paleta ganha botão de estrela otimista; Minhas Paletas ganha abas "Minhas" / "Favoritas" com card próprio (`FavoritePaletteCard`).
- `ToolProps` passa a entregar o bloco `library` ao `html-color-picker`, e `colors.storage` aceita limites configuráveis; o histórico anônimo cai de 50 para 20.
- Traduções pt_BR, pt_PT, en, es e fr ajustadas, incluindo `tools.shared.library.*` e `colors.favorites.*`.
- Cobertura: testes de feature HTTP (`ColorLibraryHttpTest`, `PaletteFavoriteHttpTest`), unit dos props e suítes Vitest (`color-library`, `palette-public`, `palette-list`, `palette-generator`).

## ⚠️ Impacto/Avisos

- **Banco de dados**: três novas migrations (`color_history_entries`, `color_favorites`, `color_palette_favorites`). Nenhuma tabela existente é alterada; todas as FKs usam `cascadeOnDelete`.
- **Variáveis de ambiente / config**: novas chaves em `config/colors.php` — `COLORS_HISTORY_PER_PAGE`, `COLORS_FAVORITES_PER_PAGE`, `COLORS_HISTORY_IMPORT_BATCH`, `COLORS_ANONYMOUS_HISTORY_LIMIT`, `COLORS_ANONYMOUS_FAVORITES_LIMIT` e `COLORS_LIBRARY_RATE_LIMIT`. Há defaults seguros, mas o `.env.example` deve ser atualizado nos ambientes.
- **Comportamento para o anônimo**: o teto do histórico local cai de 50 para 20 (ADR-132); usuários com listas maiores verão truncamento silencioso até importarem para a conta após o login.
- **Novos endpoints**: `GET/POST/DELETE /api/tools/colors/history`, `POST /api/tools/colors/history/import`, `GET/POST/DELETE /api/tools/colors/favorites` e `POST/DELETE /api/tools/colors/palettes/{ulid}/favorite`. Consomem sessão (`credentials: same-origin`) e `X-XSRF-TOKEN` e sofrem rate limit por conta.
- **Dependências**: nenhuma nova dependência de Composer ou npm; reutiliza Vue, Inertia e shadcn existentes.

close #149

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_54_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)