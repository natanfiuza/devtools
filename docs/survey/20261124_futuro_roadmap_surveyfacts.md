# Survey — Programa Futuro (planejamento da seção Futuro)

- Data: 2026-11-24
- Branch: `feature/futuro-roadmap`
- Pedido: "Inicie o planejamento da seção Futuro em docs/SPEC.md#L101"
- Skills: `grill-with-docs` (grilling + domain-modeling)
- Entrega: planejamento docs-only (roadmap, emendas na SPEC, ADR-179); sem código, sem commit.

## 1. Contexto

`docs/SPEC.md` §5 `### Futuro` (linha 101) lista doze candidatos sem fase, escopo, porte ou prioridade:
Diff texto/JSON; Cron explainer; YAML/JSON/TOML/XML; DDL -> migration Laravel; explicador de EXPLAIN;
mock data/seeder/factory a partir do schema; cURL -> Http/fetch; gerador .env.example; cheatsheets; API
pública v2; histórico compartilhável; anúncios. Não existe documento de roadmap no repositório; a SPEC §9
faz esse papel. O registro de tools já suporta `soon`, mas todos os 15 manifestos são `available`.

## 2. Fatos levantados (3 explorações)

### 2.1 Registro de tools e página genérica

- Catálogo: `resources/js/tools/<slug>/manifest.json` (um por tool), lido por TS (`import.meta.glob` em
  `resources/js/tools/registry.ts`) e PHP (`app/Support/Tools.php`); ADR-007.
- Status: `soon | available | hidden` (`resources/js/tools/types.ts`); `soon` aparece na home com selo, no
  Cmd+K não navega e `ToolController` devolve 404. Hoje zero `soon` (teste afirma isso em
  `resources/js/__tests__/tools-registry.test.ts`).
- Página genérica: `resources/js/pages/tools/Show.vue`, rota `{locale}/tools/{slug}` (`routes/web.php`),
  componente por convenção (`tools/<slug>/Tool.vue`, `resources/js/tools/components.ts`).
- Registrar tool exige: manifesto, `Tool.vue`, ícone em `resources/js/tools/icons.ts`, categoria em
  `types.ts`/`categories.ts`, chaves i18n `tools.<slug>.name/description/keywords/intro` nos 5 idiomas,
  slug pinado em `tests/Feature/Tools/ToolsRegistryTest.php`, OG via `php artisan seo:og-image` (ADR-030),
  `ToolProps.php` só com dado de servidor (ADR-043), `group` só para namespace de URL (ADR-116).
- Superfícies: home (`ToolGrid`/`ToolCard`), Cmd+K (`CommandPalette`), sitemap (`app/Support/Sitemap.php`),
  SEO/OG (`app/Support/Seo.php`, `StructuredData.php`). Nenhuma página de docs lê o registro.
- `share-text` é a única tool de texto com servidor (rota `tools/share-text/{id}` reusando `ToolController`)
  — precedente para histórico compartilhável.

### 2.2 Infra client-side reutilizável

- `components/tool/`: `ToolShell.vue`, `ToolActionBar.vue`, `ToolButton.vue`, `CodeEditor.vue`
  (CodeMirror com extensões por tool), `FileDropzone.vue`.
- `lib/`: `worker/client.ts` + `worker/serve.ts` (worker genérico, descarte de resposta velha, fallback),
  `file.ts` (limite por bytes/extensão/tipo), `download.ts`, `clipboard.ts`, `bytes.ts`, `editor/`.
- `composables/`: `useCopyFeedback`, `useFileError`, `useSplitPane`, `useLocale`.
- Limites em `config/tools.php` (input 5 MB, `virtualize_from`, timeouts de regex) enviados pelo
  `ToolController` como props; enforcement client-side.
- i18n: `translations.tools` inteiro vem em `HandleInertiaRequests`; `lib/i18n.ts` troca o namespace.
- Testes: Vitest por arquivo em `resources/js/__tests__/`; worker tests com FakeWorker; helper de leitura
  de `lang/` em `__tests__/support/langMessages.ts`.

### 2.3 Convenções de documentação

- Tracks: `docs/SPEC-BADGEFORGE.md` (fases §16, encerramento §17, aceites `BADGEFORGE-FASE-N.md`,
  ADRs 062–090) e `docs/colors/` (`SPEC-COLORS.md`, docs por área, aceites `COLORS-FASE-N.md`,
  ADRs 091–144; Layout Harmonizer 145–178).
- ADR mais alto: **178**; blocos por track documentados em `docs/colors/README.md`.
- Aceite: `docs/acceptance/{TRACK}-FASE-N.md`; plano: `docs/plans/{branch}/{YYYYMMDD}_{slug}_plansfacts.md`;
  survey: `docs/survey/{YYYYMMDD}_{slug}_surveyfacts.md`; plano e relatório completos em
  `docs/claude-code/{plans,reports}/{branch}/` (CLAUDE.md).
- `docs` está no `.prettierignore`; `*_plansfacts.md` e `*_surveyfacts.md` também.
- SPEC: §5 Futuro em :101, §8 (privacidade; frase sobre sinais de anúncio desligados), §9 em :226,
  §10 em :241, §11.1 em :249, §11.2 em :299.

## 3. Rodadas do grill

### Rodada 1

1. Entrega: roadmap + SPEC, sem código. **(aceito)**
2. Critérios: reuso de infra, demanda, custo client-only, sinergia, risco; esforço desempata. **(aceito)**
3. Empacotamento: ondas temáticas com 2–4 tools por fase, 1–2 ADRs por fase. **(aceito)**
4. Escopo: **incluir tudo** — client-side + histórico, API v2 e anúncios (não o recorte recomendado).

### Rodada 2

5. Roadmap mora em `docs/ROADMAP.md`; SPEC aponta; sem manifestos `soon` agora. **(aceito)**
6. Dependência nova: por fase, com ADR (peso, alternativa nativa, manutenção). **(aceito)**
7. Ondas: 1 conversores+diff; 2 cron/cURL/`.env`; 3 DDL/seeder/EXPLAIN; 4 cheatsheets; 5 produto. **(aceito)**
8. F5–F7 condicionadas a grill de produto + ADR de entrada. **(aceito)**

### Rodada 3

9. Onda 1: um conversor (YAML/JSON/TOML/XML) + um diff (texto/JSON). **(aceito)**
10. URLs sempre planas; sem `group`. **(aceito)**
11. Sem datas; ordem + porte S/M/L. **(aceito)**
12. Protocolo de fase escrito no ROADMAP. **(aceito)**

### Rodada 4

13. Sete fases `FUTURO-FASE-N`: F1 conversor+diff; F2 cron/cURL/`.env`; F3 DDL/seeder/EXPLAIN; F4
    cheatsheets; F5 histórico; F6 API v2; F7 anúncios. **(aceito)**
14. ADR-179 registra o programa; fases usam 180+. **(aceito)**
15. Branch nova (o usuário criou `feature/futuro-roadmap`; a F6 segue sem commit em outra branch).
    **(aceito)**
16. Ordem é recomendação, não bloqueio; só F5–F7 têm gate. **(aceito)**

## 4. Fronteira esgotada

Todas as decisões do design tree estão fechadas; nenhum candidato ficou sem onda/fase; as três frentes de
produto ficaram com critérios de entrada e perguntas abertas (SPEC §11.2), sem detalhe técnico.
