# Survey — Colors Fase 4: persistência, Minhas Paletas, compartilhamento e protótipos

- Data: 2026-11-10
- Branch: `feature/colors-fase-4`
- Task: executar `docs/colors/PROMPT-Fase4-persistencia_prototipos.md`

## 1. Contexto da tarefa

A F3 (commit `95fe717`, merge `9881cb4`, PR #144) entregou harmonias e extração de imagem no Color Palette Generator, tudo no navegador e sem persistência. A F4 transforma o gerador em produto: CRUD de paletas por usuário autenticado (Free 25 / pago ilimitado), Minhas Paletas com busca no servidor, página pública `/{locale}/colors/p/{slug}` com SEO/OG, duplicação com cota e protótipos de site/mobile em temas claro/escuro com exportação SVG vetorial e PNG 1x/2x.

O prompt exige: verificar a fase anterior, listar o que falta, apresentar arquivos/dependências/riscos/testes e parar para aprovação; implementar só a F4; não sobrescrever mudanças locais; não fazer commit/push; reutilizar auth/planos/permissões/notificações/componentes; 5 idiomas; ADRs em `docs/adr` com prefixo `colors`; não reutilizar números; não apagar decisões anteriores; não inventar comandos de teste.

## 2. Verificação da fase anterior

| Item | Resultado |
| --- | --- |
| Árvore de trabalho | limpa na `feature/colors-fase-4`, criada a partir do `main` com a F3 |
| Escala | `buildPaletteScale`, `PALETTE_TONES`, `PALETTE_ALGORITHM_VERSION = 1` |
| Harmonias | 5 tipos × 2 métodos, auxiliares, fora dos exports |
| Extração | color-thief 3.5.0 em worker, arquivo/URL HTTPS, 5/7/9/11, memória da página |
| Persistência | nenhuma; sem model, migration, rota ou controller de paleta |
| i18n | namespace `tools.palette-generator.*` nos 5 idiomas |
| ADRs | última é a 122; F4 não tem código |

## 3. Rodadas do grill

### Rodada 1 — cota e ciclo de vida

| Pergunta | Decisão |
| --- | --- |
| Arquivadas contam na cota | Sim; só a exclusão definitiva libera vaga |
| “Uso” | Duplicações feitas por outros usuários, cumulativo |
| Exclusão | Permanente, com confirmação digitando o nome |

### Rodada 2 — visibilidade e modelo

| Pergunta | Decisão |
| --- | --- |
| Privada/arquivada na URL pública | 404; arquivar libera o slug |
| Modelo salvo | Escala **ou** extraída (não as duas no mesmo registro) |
| Cores | Dois conjuntos persistidos: cores efetivas e papéis semânticos |
| Protótipo | Renderizador SVG vetorial próprio |

### Rodada 3 — slug, OG e duplicação

| Pergunta | Decisão |
| --- | --- |
| Slug | Imutável após publicar |
| OG | Gerada automaticamente após salvar/editar, com botão de regenerar |
| Duplicação | Nasce privada, guarda proveniência, copia cores/papéis/tags |
| Página pública | Vitrine + experimento local de nova base |

### Rodada 4 — editor e infraestrutura

| Pergunta | Decisão |
| --- | --- |
| Onde editar | Gerador + aba Protótipo no editor |
| Tags | Entidade por usuário |
| Fluxo de criação | Salvar no gerador + modal; anônimo vai ao login |
| Concorrência | Lock no MySQL + teste opt-in; suíte normal cobre o limite sequencial |

### Rodada 5 — defaults e rascunho

| Pergunta | Decisão |
| --- | --- |
| Papéis padrão da extraída | Mapa pela escala a partir da base |
| Rascunho anônimo | `sessionStorage`, sem imagem |
| Fidelidade do protótipo | Snapshot do SVG + asserções papel→elemento |
| Sitemap | Paletas públicas incluídas, com limite |

### Rodada 6 — URLs e autor

| Pergunta | Decisão |
| --- | --- |
| URL do dashboard | Sem prefixo de locale (ADR-022 e prompt) |
| Autor na página pública | Não aparece |

## 4. Fatos levantados no código

### Backend

- `User::effectivePlan()` decide Free/pago; cota por ação segue o padrão de `StoreCustomLogo` (`DB::transaction` + `User::lockForUpdate()`), com `BadgeForgeQuota` como referência de support.
- Controllers de ferramenta usam `abort_unless($resource->user_id === $request->user()->id, 404)`; não há policies para recursos do usuário.
- FormRequests com `authorize()` checando autenticação; toasts via `Inertia::flash('toast')`.
- Rate limiters são registrados em service providers de feature (`BadgeForgeServiceProvider`), não em `AppServiceProvider`.
- `app/Support/Seo.php` monta metas por nome de rota; `image()` fixa o OG estático 1200×1200; `NOINDEX` cobre `dashboard`.
- `app/Support/Sitemap.php` monta URLs por locale; `StructuredData.php` e metas são renderizados no servidor em `resources/views/app.blade.php`.
- `app/Support/Locales.php::alternates()` propaga a query string para canonical/hreflang.
- Storage bruto em `storage_path('app/data/...')` com `File::replace`; não há disco S3 para esses dados.
- `phpunit.xml`: SQLite `:memory:` e cache array → `lockForUpdate` é inerte no SQLite.

### Frontend

- `resources/js/lib/inertiaApp.ts` resolve layout: `tools/*` → `PublicLayout`, `admin/*` → `AdminLayout`, padrão `AccountLayout`.
- `Tool.vue` concentra o gerador; `useAccountNav.ts` lista o menu da conta; não há paginação compartilhada.
- `PaletteScale` em `lib/color/scale.ts`; `ExtractedPalette` tipada; exportadores em `lib/color/exporters.ts`.
- Dependências atuais: `colorthief`, `html2canvas-pro`, `jspdf`; fontes TTF self-hosted em `resources/badgeforge/fonts`.
- `package.json`: `test`, `types:check`, `lint:check`, `format:check`, `build`, `build:ssr`.

### Contradições resolvidas

- Prompt da F4 e ADR-022 dizem dashboard sem prefixo; `SPEC-COLORS.md` e `minhas-paletas.md` dizem `/{locale}/dashboard/...`. O usuário decidiu manter ADR-022 (sem prefixo) e corrigir os dois docs.

## 5. Riscos registrados

F3 ausente no main (resolvido: merge `9881cb4`); lock inerte no SQLite; corrida de slug; OG obsoleta/cache; fonte embutida; fidelidade SVG×PNG; `whereLike` e acentos; sitemap pesado; paridade i18n; CSRF no upload; query no canonical; payload grande em flash.
