# Plano de execução — Layout Harmonizer F4 (persistência, compartilhamento e protótipos)

Data: 2026-11-19
Branch: `feature/layout-hamonizer-fase-4` (grafia da branch como está no repositório)
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase4-salvamento_compartilhamento_prototipos.md`
Plano completo: `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261119_feature_layout-hamonizer-fase-4_f4_persistencia.md`
Survey: `docs/survey/20261119_layout_harmonizer_f4_surveyfacts.md`

## Objetivo

Persistir o sistema de layout exatamente como a tela o mostra, com nome,
descrição, tags e visibilidade; reabrir sem recalcular; compartilhar por slug
público; cota conjunta com as paletas; rascunho anônimo com importação explícita
no login; duplicação; protótipos reutilizados no workspace autenticado. Ao fim:
gates reais, relatório e parada para revisão, sem F5 e sem commit/push.

## Decisões que dirigem o plano (grill de 2026-11-18/19)

| Tema | Decisão |
| --- | --- |
| Armazenamento | Tabela própria `layout_systems` (sem discriminador em `color_palettes`) |
| Cota | Conjunta paletas + sistemas, Free 25, arquivados contam, pago ilimitado |
| Concorrência | Mesmo lock da linha `users` de `CreatePalette`/`DuplicatePalette` |
| Rascunho | Só anônimo, último rascunho local até limpar, chave própria, sem TTL |
| Login | Pergunta antes de importar; importar hidrata sem salvar nem consumir cota |
| Salvar | Cria item e abre o editor vinculado; editar não consome; “salvar como novo” consome e vincula ao novo |
| Protótipos | Só no workspace autenticado, site/mobile existentes, mapa fixo, sem editor de papéis/exportação |
| Limites | Nome 80, descrição 500, configuráveis; tags por sistema configurável |
| Origem | FK para a paleta + crédito congelado no salvamento |
| Origem privada | Crédito público omitido; texto público permanece se a paleta sumir; link some |
| Slug | Nasce na primeira publicação; arquivar reserva; excluir aposenta para sempre |
| URLs antigas | 404 permanente para arquivado/excluído |
| SEO | Sitemap + OG estática `public/og/layout-harmonizer.png` |
| Entrada pela paleta | CTA no editor e na página pública, seed = cor base, editável |
| Nome sugerido | Origem ou estratégia + hex, sempre editável |
| Versões antigas | Edita se suportada; mais novas/desconhecidas são somente leitura |

## Arquitetura

```text
LayoutWorkspace.vue                     (escolhe o controller)
├─ useLayoutSystem  (live)              hidrata sem regenerar (latch skipNext)
├─ useFrozenLayout  (compatible/readonly)
│  └─ lib/frozen.ts + engine/contrast.ts
├─ Tool.vue            rascunho, origem, save dialog, importar/descartar
├─ LayoutSystemPrototypePanel.vue       site/mobile + tema, mapa fixo
└─ LayoutSystemEditor.vue               PATCH baseline + guarda de alterações

Backend: LayoutSystem (ULID) ─ state JSON ─ snapshot F3
         cota conjunta (PaletteQuota) ─ slug/aposentadoria ─ crédito congelado
```

## Etapas

| Etapa | Conteúdo | Gate ao fim |
| --- | --- | --- |
| 1 | Migrations, config, model, relações, enum `LayoutStrategy` | `php artisan migrate` local e testes de model |
| 2 | Catálogo/validador de estado, DTO, cota conjunta, slug, crédito, presenter, tool props | Pest de cota/slug/estado |
| 3 | Actions, requests, controllers, rotas, SEO/sitemap | Pest de HTTP/actions/público/SEO |
| 4 | `lib/state.ts`, hidratação, `useFrozenLayout`, tipos TS | Vitest de estado/hidratação/congelado |
| 5 | Draft, importação, save dialog, editor vinculado, guarda | Vitest de draft/salvamento |
| 6 | Lista, editor, pública, CTA nas paletas, duplicação | Pest/Vitest das telas |
| 7 | Protótipos em `resources/js/lib/prototype/`, mapa fixo, painel | Vitest de protótipo |
| 8 | i18n nos 5 idiomas | Paridade |
| 9 | ADRs, docs, gates completos, relatório | `composer test`, `npm run test`, types/format/lint/build |

## Entregáveis

1. Três migrations novas (tabela, slugs aposentados, pivot de tags), model,
   enum, supports, actions, requests e controllers.
2. Rotas de conta e públicas, cota conjunta, slug com aposentadoria e crédito
   de origem congelado.
3. Frontend: estado/hidratação, modo compatível/congelado, rascunho, save
   dialog, editor, lista, página pública, protótipos e CTA nas paletas.
4. i18n nas 5 línguas, ADRs 152/154/159/160/161/164/165 e 168–174, docs.
5. Plano, survey, plansfacts e relatório.

## O que NÃO foi feito (por decisão de escopo)

- Exportação MD/CSS/ZIP (F5), OG dinâmica, curtidas/comentários/favoritos/
  seguir no sistema, perfil público próprio, API JSON.
- Alterações no viewer de paleta além do CTA.
- Dependências novas, sobrescrita de CLAUDE.md/ADRs, migration em produção,
  commit/push e início da F5.
