# Plano aprovado — Colors Fase 8 (auditoria final e relatório de aceite)

Gerado em 2026-11-15 a partir do plano aprovado no plan mode. Execução seguiu as etapas 0 a 9.

# Fase 8 — Auditoria final e relatório de aceite (Colors)

## Contexto

As Fases 1 a 7 do grupo Colors estão implementadas em
`feature/colors-fase-8` (branch já criada pelo usuário). A F8 é o fechamento do
track: auditar o conjunto inteiro contra `docs/colors/PROMPT-Fase8-auditoria-final.md`,
montar a matriz requisito → implementação → teste, classificar pendências por
gravidade, corrigir só **bloqueantes e altas**, provar o funcionamento no
navegador e entregar aceite, relatório e ADR. Nada de commit, push ou deploy: o
usuário commita e abre o PR pelo gitpr.

Lacunas reais encontradas no levantamento (todas verificadas em código/doc):

- não existe `docs/acceptance/COLORS-*`; ADR-144 não existe (última é 143);
- `ExportUserData` não exporta **nenhum** dado de Colors, embora o ADR-028 dê
  esse direito e o BadgeForge já exporte; a F7 deixou o ponto explicitamente
  para esta auditoria;
- `AnonymizeUser` apaga preferências e mutes de Colors, mas deixa histórico,
  favoritos de cor, presets, favoritos de paleta, curtidas e follows ligados à
  conta anonimizada;
- `README.md`, `docs/ARQUITETURA.md`, `CHANGELOG.md` e `docs/SPEC.md` não citam
  Colors; `docs/RUNBOOK.md` não tem seção de operação de Colors;
- linhas desatualizadas em `docs/colors/` (sino "inexistente", faixa de ADRs,
  cabeçalhos "Fora desta fase", SPEC com F8 em aberto);
- `npm run format:check` falha em 267 arquivos (pré-existente);
- `CommentReports.vue` não tem teste Vitest (só `CommentModeration.vue` tem).

## Decisões fechadas

| # | Tema | Decisão |
|---|------|---------|
| 1 | Escopo da correção | Corrigir só achados **bloqueantes e altos**; médios/baixos viram tabela classificada no aceite e em ADR-144. |
| 2 | Flag em produção | `COLORS_COMMENTS_ENABLED` continua `false`; a F8 documenta o procedimento de ligar (RUNBOOK §11) e testa com a flag ligada só no ambiente descartável. |
| 3 | Formatação | Rodar `npm run format` no repositório inteiro, aceitar o diff (~267 arquivos), deixar `format:check` verde. |
| 4 | Checklist manual | Executado por mim com Playwright contra **SQLite descartável** em `%TEMP%`, com dados semeados; evidências em `docs/claude-code/reports/feature_colors-fase-8/evidence/`; banco apagado no fim; o MySQL de desenvolvimento não é tocado. |
| 5 | Entregáveis | `docs/acceptance/COLORS-FASE-8.md` + relatório da fase + ADR-144 (auditoria, exportação/anonimização e itens deferidos), no molde da ADR-090/aceite da F9 do BadgeForge. |
| 6 | Exportação (ADR-028) | Ampliar `ExportUserData` com bloco `colors` — classificado **alto**. |
| 7 | Anonimização | Apagar dados pessoais da biblioteca e relações de saída, mantendo paletas, comentários e denúncias; `user_id`/`resolved_by` viram `NULL` — **alto**. |
| 8 | Docs raiz | Incluir Colors em `README.md`, `docs/ARQUITETURA.md`, `CHANGELOG.md`, `docs/SPEC.md` e nova seção §11 do RUNBOOK. `docs/API.md` fica fora (não há API pública de Colors). |
| 9 | Teste do admin | Adicionar `data-test` em `CommentReports.vue` e criar `admin-comment-reports.test.ts`. |
| 10 | Survey/plan do grill | Salvar `docs/survey/20261115_colors_fase8_surveyfacts.md` e `docs/plans/feature_colors-fase-8/20261115_colors_fase8_plansfacts.md`; cópia do plano em `docs/claude-code/plans/feature_colors-fase-8/`. |

**Sem decisão nova de produto**: a F8 não liga a flag, não muda schema, não
cria superfície. As escolhas acima vão para a seção "Decisões não aprovadas"
do aceite para o usuário confirmar.

## Método da auditoria

Três passes, sem reler o repositório inteiro:

1. **Inventário** — `php artisan test --list-tests`/`vendor/bin/pest --list-tests`,
   `npx vitest list`, listas de `tests/Feature/Colors`, `tests/Unit/Colors` e
   `resources/js/__tests__` de colors/palette/comment/notification.
2. **Matriz** — uma linha por família de requisito:
   `# | Área | Requisito (SPEC §) | Implementação (arquivo) | Teste (arquivo::nome) | Tipo (auto/manual) | Status`.
3. **Triagem** — abre só o arquivo de implementação da família sem âncora de
   teste; classifica em **Bloqueante** (quebra requisito/segurança/dado),
   **Alta** (direito do titular, vazamento, perda de dado), **Média**
   (operacional/documentação relevante), **Baixa** (cosmético). Só bloqueante e
   alta são corrigidos nesta fase.

Âncoras já mapeadas para a matriz (validar/expandir na execução):

| Área | Implementação | Teste |
|---|---|---|
| Picker, alpha, OKLCH/OKLab, gamut | `resources/js/tools/html-color-picker/*`, `components/colors/*` | `color-parse`, `color-format`, `color-convert`, `color-gamut`, `color-channels`, `html-color-picker` |
| EyeDropper + fallback | controles do picker | `color-eyedropper.test.ts` + manual (stub `window.EyeDropper`) |
| Contraste, foco, teclado | `ContrastPanel.vue`, `resources/css/app.css` | `color-contrast`, `design-tokens` + manual |
| Escala 11 tons, harmonias | `palette-generator/lib`, `components/colors` | `color-scale`, `palette-frozen-scale`, `color-harmony`, `palette-generator` |
| Exportação/importação (CSS/SCSS/JSON/Tailwind/tokens) | `exporters.ts`, `tokenExporters.ts`, `tokenImport.ts` | `palette-exporters`, `palette-token-exporters`, `palette-token-import`, `TokenImportFixturesTest` |
| Imagem local/URL sem upload | `image/*`, `extract.worker.ts`, `fetchImage.ts` | `palette-extraction*`, `palette-image-url` + rede no manual |
| Paletas salvas, cota 25, arquivar, slug | `CreatePalette`, `PaletteQuota`, `routes/colors.php` | `PaletteActionsTest`, `PaletteHttpTest`, `PaletteSlugTest` |
| Página pública, 404 uniforme, SEO/OG | `PublicPaletteController`, `PaletteOg*`, `Sitemap` | `PaletteSeoTest`, `PalettePublicHttpTest`, `palette-og` |
| Protótipos SVG/PNG | `palette-generator/prototype/*` | `prototype-render`, `prototype-rasterize`, `prototype-*` |
| Curtir anônimo, cookie, claim, seguir, perfil | `AnonymousIdentity`, listeners, controllers | `PaletteLikeHttpTest`, `ClaimAnonymousLikesTest`, `UserFollowHttpTest`, `PublicProfileHttpTest` |
| Comentários, revisão, tombstone, flag | comentários F7 | `CommentHttpTest`, `CommentVisibilityTest`, `CommentsFeatureFlagTest` |
| Moderação + `ai_cache` sentinela | `ModerateColorComment`, `ModerationCacheRepository` | `CommentModerationTest`, `CommentModerationCacheTest`, `WordFilterTest` |
| Denúncias + admin | `ReportColorComment`, controllers admin | `CommentReportTest`, `AdminCommentsTest`; **falta** Vitest de `CommentReports.vue` |
| Notificações, sino, preferências, mute | F7 | `CommentNotificationTest`, `NotificationPreferencesTest`, `notification-bell`, `notifications-page` |
| Exportação/anonimização | `ExportUserData`, `AnonymizeUser` | **sem teste** — corrigir nesta fase |
| i18n 5 idiomas | `lang/*`, `config/locales.php` | `TranslationsParityTest`, `i18n-messages.test.ts` |
| Sem regressão BadgeForge/AI | — | suíte completa (`composer ci:check`) |
| Docs raiz/operação | README, ARQUITETURA, CHANGELOG, SPEC, RUNBOOK | revisão manual |

Regras de evidência: resultado real dos comandos (contagens/exit code), captura
de tela e rede do Playwright, e nenhum item marcado como coberto só por
inspeção sem apontar o arquivo. Detalhes que o aceite deve registrar mesmo sem
correção: `lockForUpdate()` é no-op no SQLite (garantia da cota é MySQL + código),
cache de moderação permanente guarda o texto, exportação em memória cresce sem
teto para conta paga.

## Correções (somente bloqueante/alto)

### A. `app/Actions/Account/ExportUserData.php` — bloco `colors`

Inserir entre `badgeforge` e `usage`, com datas ISO 8601 e ordem determinística:

- `palettes`: `id`, `name`, `slug`, `kind`, `visibility`, `archived_at`,
  `colors`, `roles`, `source`, `colors_count`, `duplications_count`,
  `algorithm_version`, `created_at`, `updated_at`;
- `tags`: `name`, `key`, `position` (tabela `color_tags`, dono da conta);
- `history` (`color_history_entries`: `color`, `created_at`) e `favorites`
  (`color_favorites`: `color`, `created_at`);
- `presets` (`color_presets`: `name`, `base`, `created_at`);
- `palette_favorites` (`color_palette_favorites`: paleta `id`/`slug`/`name`,
  `created_at`);
- `likes`: só `liked_at IS NOT NULL`, com `slug`/`name`/`liked_at`;
- `following`: só `followed_at IS NOT NULL`, com `username`/`followed_at`
  (**não** exportar seguidores: a linha é ação de outra conta — registrar na
  ADR-144);
- `comments`: `withTrashed()`, com `id`, paleta `id`/`slug`, `body`,
  `approved_body`, `status`, `locale`, `revision`, `created_at`, `edited_at`,
  `deleted_at`; **sem** `moderation_reason`/`notified_at`;
- `reports_filed`: `comment_id`, paleta `slug`, `reason`, `status`,
  `reported_at`, `resolved_at`;
- `notification_preferences`: os quatro toggles (defaults via
  `ColorNotificationPreference::for`) e `muted_palettes` (`slug`/`name`);
- leitura tolerante a JSON corrompido, como o `canonical_payload` já faz.

Testes: ampliar `tests/Feature/AccountPanelTest.php` (shape completo e conta
vazia com arrays vazios) e `tests/Feature/PurgeDeletedUsersTest.php` (o JSON
enviado antes da anonimização contém `colors`).

### B. `app/Actions/Account/AnonymizeUser.php` — limpeza de Colors

Dentro da transação existente, antes do `forceFill`:

- apagar `color_history_entries`, `color_favorites`, `color_presets`,
  `color_palette_favorites`, `color_tags` (o pivô cai por cascade);
- apagar `color_palette_likes` do `user_id` (nunca tocar em `anonymous_id`);
- apagar `user_follows` com `follower_id = user` (relações de saída); **manter**
  as de entrada — são ação de terceiros e o perfil anonimizado responde 404;
- `UPDATE color_palette_comments SET user_id = NULL`;
  `UPDATE color_comment_reports SET user_id = NULL, resolved_by = NULL`;
- **manter** paletas (inclusive arquivadas), comentários, denúncias, OG e
  preferências/mutes já tratados na F7. Apagar paleta é proibido: as FKs de
  comentários, curtidas e favoritos de paleta são `cascadeOnDelete` e
  destruiriam dados de outras contas.

Testes: ampliar `tests/Feature/Settings/ProfileUpdateTest.php` (linhas pessoais
somem, tag some, follow de saída some, follow de entrada fica, curtida de conta
some, curtida anônima fica, paletas/comentários/denúncias ficam com autoria
`NULL`, `resolved_by` `NULL`) e `PurgeDeletedUsersTest.php`.

### C. `CommentReports.vue` + Vitest

Adicionar `data-test="comment-report-resolve"` e
`data-test="comment-report-dismiss"` nos botões e criar
`resources/js/__tests__/admin-comment-reports.test.ts` no molde de
`admin-comments.test.ts`: linhas (corpo, motivo, denunciante, status, data),
resolve/dismiss batendo nas URLs, botões só em `pending`, selo de excluído.
O stub de i18n precisa de `admin.comment_reports.*` **e**
`colors.reports.reasons.*`.

### D. Documentação

- `docs/colors/README.md`: status até F8; faixa "ADR-091 a ADR-144"; renomear
  "ADRs criados nas Fases 1–5" para "ADRs do grupo" + linha 144; novos
  "Emendas de 2026-11-15 (Fase 8)" apontando o que a F7 superou e o que a
  auditoria corrigiu — sem reescrever as emendas datadas antigas.
- `docs/colors/SPEC-COLORS.md` v1.5: §15 com os testes novos, §16 com F8
  "implementada em 2026-11-15 (ADR-144)", §17 com os critérios de aceite finais.
- `docs/colors/perfil-publico.md:39`, `glossario.md:60`, `social.md:117` e
  `paleta-publica.md:52`: remover/corrigir o que a F7 tornou falso; cabeçalhos
  "Fora desta fase" → "Fora do escopo (F7)".
- `docs/SPEC.md` §9/§10: bullet do grupo Colors concluído e ponteiro para
  `docs/colors/` e para o aceite.
- `README.md` e `docs/ARQUITETURA.md`: Colors na lista de tools, no mapa de
  superfícies servidor × navegador e um ponteiro curto.
- `CHANGELOG.md`: entrada datada do track Colors (F1–F8), `### Adicionado`.
- `docs/RUNBOOK.md`: **§11 Colors** — flag e procedimento de ligar, worker
  obrigatório, chaves/modelo da moderação, caminho do OG, `storage:link`,
  limite do sitemap, expurgo `ai:prune-cache --purpose=colors.comment_moderation --all`,
  impacto da rotação de `APP_KEY`, rollback funcional pela flag e referência ao
  §2/§3 para deploy/migrations.
- `.env.example`: completar as variáveis de Colors que `config/colors.php` lê e
  hoje não aparecem (cota, paginação, OG, cookie anônimo, presets, biblioteca,
  importação de tokens, limites de taxa). Só documentação, defaults do config.
- **Não** tocar em `docs/API.md`.

### E. Formatação

`npm run format` no repositório: o `.prettierignore` já exclui `docs`, `.gitpr`,
rotas/actions geradas, `components/ui`, views de e-mail, `storage`, `deploy`,
`.env*` e `*_plansfacts.md`/`*_surveyfacts.md`. O diff esperado é só código
(~267 arquivos). Conferir `git status --short` para alterações inesperadas e
nunca usar `git checkout/stash/clean/reset`.

Achados bloqueantes/altos que aparecerem além destes entram na execução; se
exigirem mudança estrutural fora deste plano, **parar e relatar** antes de
seguir.

## Entregáveis

1. **Aceite** `docs/acceptance/COLORS-FASE-8.md`, no molde de
   `BADGEFORGE-FASE-9.md`: título, `Gerado em 2026-11-15`, `Fonte: SPEC-COLORS
   v1.5, ADR-091 a ADR-144`, `## Escopo da fase` + `Fora da fase`, `## Critérios`
   (tabela agrupada por área — picker, gerador, exportação/importação,
   biblioteca, paletas, página pública, protótipos, social, comentários,
   moderação, denúncias/admin, notificações, exportação/anonimização, i18n,
   segurança, regressão, docs/operação), `## Resultado em 2026-11-15` com os
   comandos reais, tabela de pendências
   `| ID | Pendência | Gravidade | Impacto | Evidência | Ação |`, seção de
   **deploy e rollback** (cron `deploy/deploy-instaled.sh`, RUNBOOK §2/§3/§4/§5,
   rollback funcional pela flag, sem migration obrigatória) e
   **Decisões não aprovadas**.
2. **ADR-144** `docs/adr/ADR-144-colors-auditoria-final.md`: contexto, decisão
   (escopo/eixos da auditoria, bloco `colors` da exportação com exclusões,
   política de anonimização, flag continua `false`, itens deferidos com
   gravidade), consequências, alternativas rejeitadas (apagar paletas,
   `NULL` em vez de delete, exportar seguidores, ligar a flag agora, exportação
   em streaming já).
3. **Relatório**
   `docs/claude-code/reports/feature_colors-fase-8/20261115_feature_colors-fase-8_colors_fase8_fechamento.md`
   com o que entrou, tabela de gates com resultado real, pendências por
   gravidade, evidências do Playwright e seção Git (sem commit/push).
4. **Grill** `docs/survey/20261115_colors_fase8_surveyfacts.md` e
   `docs/plans/feature_colors-fase-8/20261115_colors_fase8_plansfacts.md`;
   cópia do plano conforme CLAUDE.md em
   `docs/claude-code/plans/feature_colors-fase-8/20261115_feature_colors-fase-8_auditoria_final.md`.
5. **Evidências** em `.../reports/feature_colors-fase-8/evidence/`.

## Etapas e gates

Scripts reais (conferidos): `composer test`, `composer ci:check`
(= npm lint:check + format:check + types:check + test + `composer test`),
`npm run test`, `npm run types:check`, `npm run lint:check`,
`npm run format:check`, `npm run build`, `npm run build:ssr`. Testes pontuais:
`php artisan test <arquivos>` e `npx vitest run <arquivos>`.

**Etapa 0 — linha de base.** `git branch --show-current` (`feature/colors-fase-8`),
`git status --short`, `composer ci:check` e `npm run build` uma vez, registrando
contagens/exit codes (o `format:check` deve falhar em ~267 arquivos). Nenhuma
edição.

**Etapa 1 — exportação (A).** Gate:
`php artisan test tests/Feature/AccountPanelTest.php tests/Feature/PurgeDeletedUsersTest.php`.

**Etapa 2 — anonimização (B).** Gate:
`php artisan test tests/Feature/Settings/ProfileUpdateTest.php tests/Feature/PurgeDeletedUsersTest.php tests/Feature/AccountPanelTest.php`.
**Parada para revisão** (única mudança destrutiva da fase).

**Etapa 3 — admin (C).** Gate:
`npx vitest run resources/js/__tests__/admin-comment-reports.test.ts resources/js/__tests__/admin-comments.test.ts`.

**Etapa 4 — docs (D).** Gate:
`rg -n "F1–F7|091–143|sem e-mail, sino|não há sino" docs/colors` sem sobras
não intencionais + revisão de markdown; `php artisan test --filter=TranslationsParityTest`.

**Etapa 5 — Prettier (E).** `npm run format`; gate `npm run format:check`,
`npm run lint:check` e inspeção de `git status --short`.

**Etapa 6 — gate estático completo.** `composer ci:check`, `npm run build`,
`npm run build:ssr`; guardar a saída real.

**Etapa 7 — checklist manual Playwright** (abaixo). Evidências salvas.

**Etapa 8 — entregáveis** (aceite, ADR-144, relatório, survey/plan).

**Etapa 9 — gate final e parada.** Repetir `composer ci:check` e `npm run build`;
`git status --short`; parar para revisão. Sem commit, push ou deploy.

## Checklist manual (Playwright, SQLite descartável)

### Subida

Env em processo (nunca editar `.env`), tudo em Git Bash com caminhos absolutos:

```bash
export AUDIT_DB="$TEMP/colors-f8-audit.sqlite"
export DB_CONNECTION=sqlite DB_DATABASE="$AUDIT_DB" DB_FOREIGN_KEYS=true
export APP_URL=http://127.0.0.1:8000 APP_ENV=local
export COLORS_COMMENTS_ENABLED=true QUEUE_CONNECTION=database
export CACHE_STORE=file SESSION_DRIVER=file MAIL_MAILER=log
export INERTIA_SSR_ENABLED=false AI_API_KEY=
```

1. Guarda obrigatória: `php artisan tinker --execute="echo DB::connection()->getDatabaseName();"`
   tem de imprimir o caminho em `%TEMP%`; se imprimir o banco de dev, parar.
2. `php artisan optimize:clear` (config em cache apontaria para MySQL).
3. Criar o arquivo SQLite vazio, `php artisan migrate --force --no-interaction`
   (sem `fullText`/`DB::statement` nas migrations; suíte já roda tudo em
   `:memory:`), depois `php artisan storage:link` se avatar/byline 404.
4. Semeadura por script temporário fora do repo via
   `php artisan tinker --execute="require '...'"`: admin (`devtools:admin`),
   autor `ana`, visitante `bruno`, paleta pública `oceano` com tags, uma privada,
   uma arquivada, comentário aprovado, um pendente, uma denúncia, 24 paletas
   para exercitar 25/26 e preferências nos defaults.
5. `npm run dev` em background e `php artisan serve --host=127.0.0.1 --port=8000`
   com o mesmo bloco de env.
6. Worker só quando for testar aprovação/notificações:
   `php artisan queue:work --stop-when-empty --tries=1 --timeout=60` (sem chave
   de IA o job vira `flagged`; aprovar manualmente antes continua valendo pelo
   UPDATE condicional — registrar isso no aceite).

### Roteiro (cada item: ação → asserção → evidência)

1. `/{locale}/tools/colors`: dois cards para picker e gerador.
2. Picker: entrada, sliders, alpha, contraste, tons, HEX8 ida e volta; Tab/
   Enter com foco visível.
3. EyeDropper: com `window.EyeDropper` removido via `browser_evaluate`, o
   fallback aparece e não há erro no console.
4. Gerador: escala 50–950, harmonias, upload por `browser_file_upload` de um PNG
   de `public/og/`; **zero requisições ao servidor** durante a extração.
5. Salvar paleta, criar a 25ª e a 26ª para `ana`: a 26ª mostra o erro de cota.
6. Paleta pública `oceano`: tags, byline, curtida anônima, seguir logado,
   favoritar, thread, denúncia, silenciar (dono). 404 idêntico para privada,
   arquivada e slug inexistente; username inexistente 404.
7. OG: `/{locale}/colors/p/oceano/og.png` 200; OG de privada 404; `/og/*.png`
   das tools 200.
8. Admin: aprovar o pendente, resolver/descartar a denúncia; auditoria sem o
   corpo do comentário.
9. Notificações: sino após aprovação, `/dashboard/notifications`, marcar lida,
   toggles, nenhum corpo de comentário; `MAIL_MAILER=log` para conferir o e-mail
   no locale e sem o texto.
10. Claim anônimo: curtir como visitante, logar como `bruno`, curtida
    reivindicada, sem duplicata nem segundo aviso.
11. i18n: os cinco locales na página pública e em Minhas Paletas, sem chave crua.
12. Privacidade: rede sem requisições ao servidor na extração, nos protótipos,
    na imagem tipográfica e na geração de OG.
13. SSR: uma passada com `npm run build:ssr` + `npm run ssr` e
    `INERTIA_SSR_ENABLED=true`; `<title>`/meta no HTML do servidor.
14. Tema escuro: `browser_emulate_media({colorScheme:"dark"})` no picker e na
    página pública, com captura.

### Encerramento

Parar worker/dev/serve/ssr; apagar o SQLite, o script de seed, os arquivos
criados em `storage/app/data/og_paleta_image` (comparar antes/depois) e
`bootstrap/cache/config.php` se reaparecer; `php artisan optimize:clear`.
`git status --short` só com as mudanças intencionais.

## Riscos e cuidados

- **FK em cascata**: apagar paleta destrói comentários, curtidas e favoritos de
  outras contas. A anonimização nunca apaga paletas.
- **Banco de dev**: nunca editar `.env`; guarda da Etapa 7 é obrigatória;
  variáveis só no processo.
- **SQLite ≠ MySQL**: `lockForUpdate()` é no-op e `after()` é ignorado; não
  "consertar" cota por causa disso — registrar como desvio do aceite.
- **SSR**: com `INERTIA_SSR_ENABLED=true` e sem `bootstrap/ssr`, as páginas
  falham; manter desligado na interação e fazer uma passada dedicada.
- **Cache de moderação permanente**: guarda o texto do comentário; a F8 não
  muda a decisão da F7 — recomenda TTL em produção e o expurgo por `purpose`,
  registrando como pendência média.
- **Exportação sem teto** para conta paga: manter em memória e registrar
  streaming/zip como deferido médio.
- **Prettier**: só código por causa do `.prettierignore`; nunca `git reset`,
  `git checkout -- .`, `git stash` ou `git clean`.
- **Emendas datadas**: não reescrever o histórico; corrigir com bloco novo.
- **Worker**: sem ele a thread fica "em análise" e o sino não toca; RUNBOOK §11
  diz isso explicitamente.
- **Fase**: parar ao fim da F8, sem commit/push/deploy e sem avançar para
  outra fase.

## Verificação final

```bash
composer ci:check      # lint, format, types, Vitest, Pint, PHPStan, Pest
npm run build
npm run build:ssr
git status --short
```

Esperado: tudo verde, inclusive `format:check`; suíte Pest ≥ 1737 testes /
30275 asserções + os casos novos; Vitest ≥ 1541 testes + `admin-comment-reports`;
relatório, aceite e ADR-144 escritos com os resultados reais; somente alterações
intencionais no working tree; nenhum commit.
