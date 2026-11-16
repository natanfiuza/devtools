# Plano de execução — Layout Harmonizer F0 (auditoria e plano)

Data: 2026-11-15
Branch: `feature/layout-harmonizer-fase-1`
Plano completo: `docs/claude-code/plans/feature_layout-harmonizer-fase-1/20261115_feature_layout-harmonizer-fase-1_f0_auditoria.md`
Survey: `docs/survey/20261115_layout_harmonizer_f0_surveyfacts.md`

## Objetivo

Executar **somente a F0** do `PROMPT-INICIAL-CLAUDE.md`: auditar o Colors real,
documentar diferenças entre código e requisitos aprovados, registrar arquivos,
dependências, riscos e testes por fase, e propor a tabela numérica a calibrar na
F1. Nenhum código, migration, dependência ou commit nesta sessão.

## Decisões que dirigem o plano

16 decisões em 4 rodadas (tabelas completas no survey), resumidas:

| Tema | Decisão |
| --- | --- |
| Escopo | F0 sem código; parada para revisão |
| Persistência | Nova tabela `layout_systems` + pivô `color_tag_layout_system` |
| Superfícies | Tool em `/{locale}/tools/colors/layout-harmonizer`, gestão em `/dashboard/colors/layout-systems`, público em `/{locale}/colors/s/{slug}` |
| Protótipos | Promover `palette-generator/prototype/` a `resources/js/lib/prototype/` |
| Dependências | Só o núcleo interno; `fflate` direto apenas na F5, com autorização |
| Escopo social | Fora (curtidas, comentários, OG dinâmica, perfil, API JSON…) |
| i18n | Namespace `layout_harmonizer` + `tools.layout-harmonizer.*`, 5 idiomas |
| Limites | Nome 80 / descrição 500, configuráveis |
| ADRs | Global após `ADR-144`, arquivo `ADR-145-colors-lh-<escopo>.md`, cópia por fase |
| Rascunho | `localStorage` versionado (`devtools.layout-harmonizer.draft.v1`) |
| Docs | `docs/SPEC.md`, `docs/glossario.md`, `docs/colors/README.md` |

## Etapas executadas na F0

| Etapa | Conteúdo | Resultado |
| --- | --- | --- |
| 1 | Leitura integral do pacote LH (12 documentos + 22 ADRs) e das decisões de produto | Escopo, tokens (33), contrato técnico e fases mapeados |
| 2 | Inspeção do Colors real (registro de tools, picker, núcleo de cor, protótipos, exports, persistência, cota, slug, viewer, sitemap, i18n, rascunho) | Fatos verificados no código, sem presumir arquivos antigos |
| 3 | Montagem da tabela de diferenças código × requisitos | 15 lacunas identificadas |
| 4 | Proposta de schema aditivo (`layout_systems` + pivô) | Migration única, sem tocar `color_palettes` |
| 5 | Proposta de calibração (eixos + tabela provisória + fixtures) | Marcada como candidata, sujeita à aprovação da F1 |
| 6 | Plano de arquivos por fase (F1–F6) | Diretórios, classes, páginas e testes por fase |
| 7 | Riscos, testes e verificação | 12 riscos com mitigação; matriz de testes por fase |
| 8 | Artefatos da F0 e atualização das três docs globais | Relatório, plano, plansfacts, survey, mapa de ADRs e docs |
| 9 | Conferência de estado e parada | `git status` só com docs; nenhum código alterado |

## Entregáveis da F0

1. `docs/claude-code/reports/.../20261115_..._f0_auditoria.md` — relatório detalhado.
2. `docs/claude-code/plans/.../20261115_..._f0_auditoria.md` — este plano.
3. `docs/plans/.../20261115_f0_auditoria_plansfacts.md` — este arquivo.
4. `docs/survey/20261115_layout_harmonizer_f0_surveyfacts.md` — levantamento.
5. `docs/colors/layout-harmonizer/ADR-MAPEAMENTO.md` — LH-001..LH-022 → ADR-145..ADR-166.
6. Atualizações: `docs/SPEC.md` (§5 e §9), `docs/glossario.md`, `docs/colors/README.md`.

## O que NÃO foi feito (por decisão de escopo)

- Nenhum arquivo em `app/`, `resources/`, `routes/`, `lang/`, `database/` ou `config/`.
- Nenhuma migration, nenhum `npm install`/`composer require`.
- Nenhum ADR copiado para `docs/adr/` — a cópia física começa na F1, junto da
  primeira fase implementada (ver observação no relatório sobre LH-001/LH-006).
- Nenhum commit, push ou deploy.

## Confirmação de parada

A F0 termina com os artefatos escritos e o repositório sem código novo. A F1 só
começa com aprovação explícita da tabela numérica de parâmetros.
