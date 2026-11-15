# Critérios de aceite — Colors FASE 8 (auditoria final)

Gerado em 2026-11-15.
Fonte: `docs/colors/SPEC-COLORS.md` v1.5, ADR-091 a ADR-144, plano da sessão
(`docs/claude-code/plans/feature_colors-fase-8/20261115_feature_colors-fase-8_auditoria_final.md`).

## Escopo da fase

- **Auditoria do track inteiro** (F1 a F7) contra a SPEC-COLORS v1.5: matriz
  requisito → implementação → teste, comandos reais e verificação funcional no
  navegador.
- **Direito do titular**: `ExportUserData` ganha o bloco `colors`;
  `AnonymizeUser` apaga o dado pessoal de Colors e preserva o conteúdo
  publicado sem autoria (ADR-028, ADR-144).
- **Dois achados altos corrigidos**: aba de extração quebrando em `pt_BR`/
  `pt_PT` (`Intl` com tag inválida) e interpolação do sino com `:actor`/
  `:palette` literais (vue-i18n usa `{nome}`).
- **Cobertura nova**: teste Vitest da fila de denúncias do admin
  (`CommentReports.vue`).
- **Documentação e operação**: `README.md`, `docs/ARQUITETURA.md`,
  `CHANGELOG.md`, `docs/SPEC.md`, `docs/colors/*`, `RUNBOOK` §11 e
  `.env.example`.
- **Formatação**: `npm run format` no repositório inteiro para deixar
  `format:check` verde.
- **Nenhum deploy, push ou commit**; a flag `COLORS_COMMENTS_ENABLED` continua
  `false` por padrão.

Fora da fase: ligar comentários em produção; streaming/zip da exportação;
TTL do cache de moderação; landing do grupo `/{locale}/tools/colors`;
eventos de analytics; mudanças de schema; API pública de Colors (não existe);
`docs/API.md` (intocado).

## Critérios

### Catálogo e tools

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Duas tools do grupo com cards e navegação (§2) | `resources/js/pages/Home.vue`, `app/Support/Tools/*`, `resources/js/tools/{html-color-picker,palette-generator}` | `html-color-picker.test.ts`, `palette-generator.test.ts`; checklist 1 | auto + manual | OK |

### HTML Color Picker

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 2 | Entrada HEX/RGB/HSL/OKLCH/OKLab, alpha e HEX8 ida e volta (§3) | `resources/js/lib/color*`, `tools/html-color-picker/Tool.vue` | `color-parse`, `color-format`, `color-convert`, `color-channels`, `color-mix`, `color-named`; checklist 2 | auto + manual | OK |
| 3 | Gamut e conversão honestas (§3) | `lib/color/gamut*` | `color-gamut.test.ts` | auto | OK |
| 4 | Contraste WCAG contra preto e branco (§3) | `components/colors/ContrastPanel.vue` | `color-contrast.test.ts` | auto | OK |
| 5 | EyeDropper com fallback quando o navegador não suporta (§3) | controles do picker | `color-eyedropper.test.ts`; checklist 3 | auto + manual | OK |
| 6 | Teclado, foco visível e tema claro/escuro (§3/§14) | `resources/css/app.css`, componentes | `design-tokens.test.ts`; checklist 2 e 14 | auto + manual | OK |

### Palette Generator

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 7 | Escala fixa de 11 tons 50–950 e escala congelada (§4) | `tools/palette-generator/lib/*`, `components/ToneScale.vue` | `color-scale`, `palette-frozen-scale`, `palette-generator` | auto | OK |
| 8 | Harmonias e cores-base (§4) | `lib/harmony*`, `components/HarmonyPanel.vue` | `color-harmony.test.ts` | auto | OK |
| 9 | Extração 5/7/9/11 de imagem local e URL, 100% no navegador (§5) | `components/ExtractionPanel.vue`, `lib/extract.worker.ts`, `lib/fetchImage.ts` | `palette-extraction`, `palette-extraction-worker`, `palette-image-url`; checklist 4 e 5-rede | auto + manual | OK (defeito `Intl` corrigido) |

### Exportação e importação

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 10 | CSS/SCSS/JSON/Tailwind v3/v4 com alpha e gamut (§4) | `lib/exporters.ts` | `palette-exporters`, `palette-export-panel` | auto | OK |
| 11 | Tokens W3C DTCG/Style Dictionary (primitivo e semântico) (§4) | `lib/tokenExporters.ts`, `lib/tokenImport.ts` | `palette-token-exporters`, `palette-token-import`, `palette-token-import-dialog`, `token-import-i18n` | auto | OK |
| 12 | Importação de JSON sem executar o arquivo, com diagnóstico e sem descartar o estado (§4) | `lib/tokenImport.ts` | `palette-token-import`; fixtures | auto | OK |

### Protótipos e imagem tipográfica

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 13 | Protótipo de site/mobile exportável em SVG e PNG 1×/2× (§8) | `tools/palette-generator/prototype/*` | `prototype-render`, `prototype-rasterize`, `prototype-templates`, `prototype-defaults`; checklist 12 | auto + manual | OK |
| 14 | Paleta exportável como imagem tipográfica em três formatos, marca fixa, fonte embutida, sem referência externa (§10) | `tools/palette-generator/image/*` | `palette-image-export`, `palette-image-panel`, `palette-image-limits`; checklist 12 | auto + manual | OK |

### Biblioteca pessoal e presets

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 15 | Histórico e favoritos da conta iguais nas duas tools; anônimo com 20/50 e importação explícita (§2) | `Api/Colors/Color{History,Favorite}Controller`, `lib/colors/api.ts` | `color-library`, `ColorLibraryHttpTest`, `ColorsToolPropsTest` | auto | OK |
| 16 | Presets do gerador são entrada (nome + base) e não consomem cota de paleta (§4) | `components/colors/PresetDialog.vue`, `ColorPresetQuota` | `palette-presets`, `ColorPresetHttpTest` | auto | OK |

### Paletas salvas e página pública

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 17 | Criar/editar/arquivar/duplicar/excluir; cota 25 no Free; slug único (§6) | `Actions/Colors/*`, `Support/Colors/PaletteQuota`, `routes/colors.php` | `PaletteActionsTest`, `PaletteHttpTest`, `PaletteSlugTest`; checklist 5 e 6 | auto + manual | OK (cota exercitada 25/26 no SQLite) |
| 18 | Página pública com SEO, 404 uniforme para privada/arquivada/inexistente e OG dinâmica (§7) | `PublicPaletteController`, `PaletteOgController`, `Sitemap` | `PalettePublicHttpTest`, `PaletteSeoTest`, `palette-og`, `palette-public`; checklist 6 e 7 | auto + manual | OK |
| 19 | OG estática das tools versionada; OG de paleta fora do versionamento (§7) | `public/og/*`, `storage/app/data/og_paleta_image` | inspeção; checklist 7 | manual | OK |

### Social

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 20 | Curtir anônimo com cookie opaco e reivindicação no login, sem duplicata nem segundo aviso (§11) | `AnonymousIdentity`, `PaletteLikeController`, listeners | `PaletteLikeHttpTest`, `ClaimAnonymousLikesTest`, `AnonymousIdentityTest`; checklist 10 | auto + manual | OK |
| 21 | Seguir e perfil público, com username inexistente 404 (§11) | `UserFollowController`, `PublicProfileController` | `UserFollowHttpTest`, `PublicProfileHttpTest`, `public-profile`; checklist 6 | auto + manual | OK |

### Comentários e moderação

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 22 | Thread de dois níveis, revisão concorrente, tombstone e edição rejeitada revertida (§12) | `Api/Colors/ColorPaletteCommentController`, policy | `CommentHttpTest`, `CommentVisibilityTest`, `comment-thread` | auto | OK |
| 23 | Filtro local antes da IA; falha da IA nunca aprova; cache sentinela (`expires_at = NULL`) expurgável por `purpose` (§12) | `ModerateColorComment`, `ModerationCacheRepository`, `WordFilter` | `CommentModerationTest`, `CommentModerationCacheTest`, `ModerationContractValidatorTest`, `WordFilterTest` | auto | OK |
| 24 | Denúncia única por par, motivo em lista fixa, resolver/excluir resolve pendentes; admin com auditoria sem corpo (§12/§15) | `ReportColorComment`, `Admin/CommentReportController`, `RecordsAdminActions` | `CommentReportTest`, `AdminCommentsTest`, `admin-comment-reports.test.ts`, `admin-comments.test.ts`; checklist 8 | auto + manual | OK |
| 25 | Flag desligada: thread ausente e endpoints em 404 uniforme (§12) | middleware `colors.comments` | `CommentsFeatureFlagTest` | auto | OK |

### Notificações

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 26 | Curtida, follow, comentário aprovado e resposta aprovada notificam uma vez, sem o corpo do comentário (§11/§12) | quatro jobs `ShouldQueueAfterCommit` | `CommentNotificationTest`; checklist 9 | auto + manual | OK (interpolação corrigida) |
| 27 | Sino com contador, página única, marcar lida/todas, preferências de e-mail e silenciar (§11) | `NotificationBell.vue`, `pages/dashboard/Notifications.vue`, `MutePaletteButton.vue` | `notification-bell`, `notifications-page`, `NotificationPreferencesTest`; checklist 9 | auto + manual | OK |

### Direito do titular (novo na F8)

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 28 | Exportação da conta com o bloco `colors` completo, sem `moderation_reason`, sem `notified_at` e sem seguidores (§17; ADR-028) | `ExportUserData::colors()` | `AccountPanelTest`, `PurgeDeletedUsersTest` | auto | OK |
| 29 | Anonimização apaga biblioteca, presets, favoritos, curtidas da conta, follows de saída e tags; autoria `NULL`; nenhuma paleta excluída (§17) | `AnonymizeUser` | `Settings/ProfileUpdateTest`, `PurgeDeletedUsersTest` | auto | OK |

### i18n, segurança e regressão

| # | Requisito (SPEC §) | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 30 | Cinco idiomas sem texto hardcoded e sem chave crua (§1) | `lang/*`, `config/locales.php` | `TranslationsParityTest`, `i18n-messages.test.ts`; checklist 11 | auto + manual | OK |
| 31 | Imagem e JSON nunca vão ao servidor; nenhuma requisição durante extração, protótipo, imagem e OG (§5/§10) | worker no navegador, blobs, OG `POST` só do PNG final | checklist 4 e 12; evidências `05-rede-extracao.txt` e `12-privacidade-rede.txt` | manual | OK |
| 32 | SSR com o bundle de produção e metadados no HTML do servidor | `config/inertia.php`, `bootstrap/ssr` | checklist 13; evidência `13-ssr.html.txt` | manual | OK |
| 33 | Sem regressão em BadgeForge, AI e demais tools | — | `composer ci:check` completo (Pest + Vitest) | auto | OK |
| 34 | Docs raiz, de Colors e de operação refletem o track | README, ARQUITETURA, CHANGELOG, SPEC, `docs/colors/*`, RUNBOOK §11 | inspeção | manual | OK |
| 35 | Nenhum commit/push/deploy feito pelo assistente | — | `git status --short` | manual | OK |

## Resultado em 2026-11-15

- `composer ci:check` — **exit 0**:
  - `lint:check`: **0 erros**, 24.385 avisos históricos de indentação
    (`vue/html-indent`), nenhum introduzido pelo track;
  - `format:check`: **All matched files use Prettier code style!**;
  - `types:check`: limpo (Wayfinder + `vue-tsc --noEmit`);
  - `npm run test`: **128 arquivos / 1.546 testes** (inclui
    `admin-comment-reports.test.ts` e a guarda de interpolação do sino);
  - Pint: passed; PHPStan: **0 erros**;
  - Pest: **1.737 testes / 30.326 asserções**, 1 deprecation pré-existente do
    próprio Pest.
- `npm run build` — **exit 0**; `npm run build:ssr` — **exit 0**
  (`bootstrap/ssr/ssr.js` gerado).
- Checklist manual (Playwright, SQLite descartável em `%TEMP%`, semeadura
  local, MySQL de desenvolvimento intocado, banco e artefatos apagados):
  1. catálogo com as duas tools — `01-catalogo-home-colors.png`;
  2. HEX8/alpha e foco por teclado — `02-picker-hex8-alpha-foco.png` e
     `03-picker-tons-tab-foco-teclado.png`;
  3. fallback sem `window.EyeDropper`, sem erro no console —
     `04-eyedropper-fallback-sem-EyeDropper.png`;
  4. extração de 11 cores por upload, **zero requisição ao servidor** —
     `05-gerador-extracao-11-cores-sem-upload.png` e `05-rede-extracao.txt`;
  5. cota 25/26 com erro na 26ª — `06-cota-25-paletas-limite.png`;
  6. pública, privada, arquivada e slug inexistente com 404 uniforme; seguir,
     favoritar, denunciar e silenciar;
  7. OG de paleta 200 e de privada 404;
  8. aprovação, resolução/dispensa de denúncia e `/admin/audit` sem o corpo do
     comentário — `08-admin-denuncias-auditoria.txt`;
  9. três notificações sem corpo, marcar uma/todas, preferências e e-mail no
     idioma do destinatário sem o texto do comentário —
     `09-notificacoes.txt`, `09-notificacoes-tres-novas.png` e
     `09-email-notificacao-sem-corpo.txt`;
  10. curtida anônima reivindicada no login, sem duplicata nem segundo aviso;
  11. cinco idiomas na paleta pública e em Minhas Paletas, sem chave crua —
      `11-i18n-cinco-idiomas.txt`;
  12. rede só com módulos do Vite, `blob:` e o `POST` do PNG do OG —
      `12-privacidade-rede.txt`;
  13. SSR com `<title>`, description, canonical e `og:*` no HTML do servidor —
      `13-ssr.html.txt`;
  14. tema escuro no picker e na paleta pública, `html.dark` e fundo
      `rgb(11, 12, 15)` — `14-tema-escuro-picker.png`,
      `14-tema-escuro-paleta-publica.png` e `14-tema-escuro.txt`.
- Teardown: worker, serve e SSR parados; SQLite, script de seed, PNG de OG
  regerado, `.playwright-mcp/` e cache de bootstrap removidos;
  `php artisan optimize:clear` executado; `git status --short` só com as
  mudanças intencionais.

### Desvios registrados

- A verificação funcional rodou em **SQLite**, onde `lockForUpdate()` é no-op;
  a concorrência real da cota e o comportamento de transação do MySQL não são
  exercitados ali. A garantia da cota é código + MySQL; nenhuma correção foi
  feita por causa do SQLite (P2).
- Sem `AI_API_KEY`, o job de moderação classifica como `flagged`; a aprovação
  manual (UPDATE condicional) foi o caminho exercitado e é a garantia real.
- O tema escuro foi verificado por `prefers-color-scheme` (sem opt-in salvo),
  que é o caminho padrão de quem não escolheu tema.

## Pendências

| ID | Pendência | Gravidade | Impacto | Evidência | Ação |
| --- | --- | --- | --- | --- | --- |
| P1 | 404 de rotas do dashboard perde as props compartilhadas e mostra chaves cruas (`app.actions.skip_to_content`, `errors.not_found.title`) | Média | Cosmético/UX em rota inexistente do painel; páginas públicas localizadas não são afetadas | `/dashboard/colors/palettes/{ULID em caixa errada}/edit` renderiza `Error` sem `translations` | Renderizar `Error` dentro do pipeline ou passar o mínimo das props |
| P2 | `lockForUpdate()` é no-op no SQLite | Média (desvio) | A cota 25 tem concorrência real garantida só em MySQL | Suíte e checklist rodam em SQLite | Manter garantia em código + MySQL; documentado |
| P3 | Cache de moderação permanente guarda o texto do comentário | Média | Texto do autor fica no cache além do necessário | `ModerationCacheRepository`: `expires_at = NULL` | TTL em produção + expurgo `php artisan ai:prune-cache --purpose=colors.comment_moderation --all` |
| P4 | Exportação da conta em memória, sem teto para conta paga | Média | Crescimento de RAM com muitas paletas/comentários | `ExportUserData` monta o array inteiro | Streaming/zip em fase futura |
| P5 | Worker obrigatório para moderação e notificações | Média (operacional) | Sem worker, comentário fica pendente e o sino não toca | RUNBOOK §11 | Supervisor + monitoramento da fila |
| P6 | SPEC §2 documenta `/{locale}/tools/colors` (landing do grupo) que não tem rota web | Média (documentação) | Leitor da SPEC pode esperar URL que responde 404 | `route:list` sem rota para a landing | Corrigir a SPEC ou abrir a landing |
| P7 | Cache de moderação sem TTL no código | Baixa (redundante com P3) | Igual a P3 | — | Igual a P3 |

## Deploy e rollback

- **Deploy**: o cron executa `deploy/deploy-instaled.sh` (fonte
  `deploy/deploy.sh`) a cada minuto; a F8 não introduz migration nem schema
  novo, então o deploy é só código + `npm run build`/`build:ssr` conforme o
  script. Acompanhar `docs/RUNBOOK.md` §2 (deploy), §3 (migrations), §4
  (Supervisor/SSR) e §5 (fila).
- **Rollback de aplicação**: `docs/RUNBOOK.md` §2 → "Rollback", reimplantando
  o release anterior pelo mesmo script.
- **Rollback funcional dos comentários**: `COLORS_COMMENTS_ENABLED=false` no
  `.env` de produção e reload do FPM (RUNBOOK §11 "Desligar"). Não apaga dados;
  a thread some e os endpoints de comentário/denúncia/mute voltam a 404.
- **Sem migration obrigatória**: não há tabela ou coluna nova na F8; o rollback
  não precisa de passos de banco.
- **Dados de OG**: a imagem dinâmica regerada fica em
  `storage/app/data/og_paleta_image` (não versionada); apagar um PNG é seguro e
  ele é regerado pelo botão do dono.

## Decisões não aprovadas (para o pedido)

Confirmação de fechamento em **2026-11-15**: a fase foi aceita como está, com as
pendências médias P1 a P6 deferidas como backlog, e a flag dos comentários
permanece desligada em produção. As demais decisões abaixo seguem abertas.

- **`COLORS_COMMENTS_ENABLED`**: decidido manter `false` em produção neste
  fechamento. Ligar é passo operacional futuro, documentado no RUNBOOK §11, sem
  deploy de código nem migration.
- **Formatação do repositório inteiro** (~267 arquivos) foi aceita no grill
  para deixar `format:check` verde; se o revisor preferir, o diff de formatação
  pode ser separado em um commit próprio.
- **Escopo da correção limitado a bloqueante/alto**: as pendências médias P1 a
  P6 ficam registradas, sem correção nesta fase.
- **Exclusões da exportação** (`moderation_reason`, `notified_at`, seguidores)
  seguem a leitura do ADR-028 feita nesta fase; qualquer divergência é decisão
  do titular do produto.
- **Landing do grupo** (`/{locale}/tools/colors`): corrigir a SPEC ou abrir a
  página fica para a próxima decisão de produto.
