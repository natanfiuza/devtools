# Critérios de aceite — Layout Harmonizer FASE 6 (auditoria final e entrega)

Gerado em 2026-11-22.
Fonte: `docs/colors/layout-harmonizer/SPEC.md`, `TOKENS.md`,
`CONTRATO-TECNICO.md`, `DADOS.md`, `TESTES.md`, `PARAMETROS.md` e os ADRs
145–178; plano da sessão em
`docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261122_feature_layout-hamonizer-fase-4_f6_auditoria.md`.

## Escopo da fase

- **Auditoria do track inteiro (F1 a F5)** contra a SPEC, os 33 tokens, os
  ADRs e a matriz de `TESTES.md`, com a matriz abaixo preenchida.
- **Correções da auditoria** (todas aplicadas nesta fase):
  - motor: teto de croma do F0 nos quatro tokens que o excediam e passo da
    variante de marca em 0.12, com `LAYOUT_PARAMS_VERSION`/
    `LAYOUT_ALGORITHM_VERSION` = 2 e fixtures regeneradas (ADR-178);
  - secundária manual com alpha e fundo de composição explícito;
  - rejeição explícita de alpha no fundo da seed;
  - UI/acessibilidade: confirmação de regeneração e de restaurar, descarte
    explícito do rascunho, id único no editor, ARIA nos campos de exportação,
    texto acessível nos selos;
  - servidor: validação do estado com os campos novos, i18n dos cinco
    locales, slug e locale da rota pública conferidos.
- **Cobertura nova**: as quatro lacunas de `TESTES.md`, o teto de croma nas
  fixtures, alpha da secundária/fundo da seed, regeneração do quadro congelado,
  rascunho e o teste de concorrência MySQL opt-in.
- **Documentação e operação**: ADR-178, `CHANGELOG.md`, `docs/SPEC.md`,
  `docs/colors/README.md`, `ADR-MAPEAMENTO.md`, `PARAMETROS.md`,
  `CONTRATO-TECNICO.md`, `DADOS.md`, `TESTES.md`, glossário, `RUNBOOK.md` §12
  e `.env.example`; remoção dos 22 ADRs originais locais.
- **Gates reais** e verificação do CSS no navegador; **nenhum** commit, push
  ou deploy.

Fora da fase (decisões já registradas): protótipo editável/exportável,
exportação pela página pública/API, mudanças de quota/produto, novas
migrations, novas dependências e deploy.

## Matriz requisito → implementação → teste → situação

| # | Requisito | Implementação | Teste | Tipo | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Motor determinístico, parâmetros e versões | `engine/generate.ts`, `engine/params.ts`, `engine/types.ts` | `layout-harmonizer-engine.test.ts`, `layout-harmonizer-params.test.ts`, `layout-harmonizer-fixtures.test.ts` | auto | OK |
| 2 | Catálogo de 33 tokens e semântica | `engine/tokens.ts`, `docs/colors/layout-harmonizer/TOKENS.md` | `layout-harmonizer-engine.test.ts`, `layout-harmonizer-board-view.test.ts` | auto | OK |
| 3 | Contraste AA/AAA (16 pares por tema, sem arredondar), avisos e sugestões que não alteram cor | `engine/contrast.ts`, `lib/suggestions.ts` | `layout-harmonizer-contrast.test.ts`, `layout-harmonizer-suggestions.test.ts`, `layout-harmonizer-suggestions-panel.test.ts` | auto | OK |
| 4 | Quatro estratégias × dois temas | `engine/generate.ts`, `params.ts` | `layout-harmonizer-strategies.test.ts`, `layout-harmonizer-engine.test.ts` | auto | OK |
| 5 | Seed opaca e com alpha sobre fundo; alpha no fundo da seed recusado | `engine/generate.ts`, `engine/alpha.ts`, `lib/inputs.ts`, `components/SeedPicker.vue` | `layout-harmonizer-alpha.test.ts`, `layout-harmonizer-engine.test.ts`, `layout-harmonizer-state.test.ts` | auto | OK |
| 6 | Secundária automática/manual e derivadas; manual com alpha e fundo de composição explícito | `engine/generate.ts`, `components/SecondaryField.vue`, `composables/useLayoutSystem.ts` | `layout-harmonizer-engine.test.ts`, `layout-harmonizer-system.test.ts`, `layout-system-editor.test.ts` | auto | OK |
| 7 | Sugestões e aceite (Primary idêntico à seed até aceite, ajuste por tema/ambos, lock retido) | `lib/editState.ts`, `lib/controller.ts`, `components/SuggestionsPanel.vue` | `layout-harmonizer-engine.test.ts`, `layout-harmonizer-overrides.test.ts`, `layout-harmonizer-edits.test.ts` | auto | OK |
| 8 | Overrides e bloqueios (origem edit/accept/lock, restaurar por escopo, sem cascata) | `lib/editState.ts`, `lib/controller.ts`, `components/TokenCell.vue`, `TokenEditorPopover.vue` | `layout-harmonizer-overrides.test.ts`, `layout-harmonizer-edits.test.ts`, `layout-harmonizer-token-editor.test.ts` | auto | OK |
| 9 | Entradas e picker compartilhado | `components/SeedPicker.vue`, `components/SecondaryField.vue`, `components/colors/ColorInputField.vue` | `layout-harmonizer-workspace.test.ts`, `layout-harmonizer-system.test.ts` | auto | OK |
| 10 | Quadro/painel e acessibilidade (teclado, foco, ARIA, texto além do `title`) | `LayoutWorkspace.vue`, `TokenCell.vue`, `TokenEditorPopover.vue`, `LayoutExportPanel.vue` | `layout-harmonizer-workspace.test.ts`, `layout-harmonizer-token-cell.test.ts`, `layout-harmonizer-token-editor.test.ts`, `layout-harmonizer-export-panel.test.ts`, `layout-harmonizer-regeneration.test.ts` | auto + manual (checklist 3) | OK |
| 11 | Rascunho anônimo (sem TTL, importação consentida, descarte só explícito) | `lib/draft.ts`, `composables/useLayoutDraft.ts`, `components/ImportDraftDialog.vue`, `LayoutHarmonizerPanel.vue` | `layout-harmonizer-draft.test.ts`, `layout-harmonizer-draft-ui.test.ts`, `layout-harmonizer-save.test.ts` | auto | OK |
| 12 | Persistência, cota conjunta (25 com arquivadas), ownership/404, read-only | `Actions/Colors/*`, `LayoutSystemController`, `Support/Colors/LayoutSystemState` | Pest `LayoutSystemHttpTest`, `LayoutSystemActionsTest`, `LayoutSystemQuotaTest`, `LayoutSystemStateTest` | auto | OK |
| 13 | Página pública, SEO/sitemap e crédito de origem | `PublicLayoutSystemController`, `SitemapController`, `LayoutSystemPresenter` | Pest `LayoutSystemPublicHttpTest`, `LayoutSystemSeoTest`, `LayoutSystemSlugTest`; `layout-system-public.test.ts` | auto | OK |
| 14 | Downgrade, arquivamento, restauração e exclusão (slug nunca reemitido) | `Actions/Colors/*`, `LayoutSystemSlugTest` | Pest `LayoutSystemActionsTest`, `LayoutSystemSlugTest`, `LayoutSystemQuotaTest` | auto | OK |
| 15 | Concorrência e integridade da cota (lock de linha) | `Actions/Colors/Concerns/*`, `Users` lock | Pest `LayoutSystemQuotaTest`; `LayoutSystemConcurrencyTest` (opt-in MySQL) | auto + opt-in | OK (skip sem MySQL de teste) |
| 16 | Exportações Markdown/CSS/ZIP no navegador, mesmo snapshot | `export/markdown.ts`, `export/css.ts`, `export/zip.ts`, `LayoutExportPanel.vue` | `layout-harmonizer-export-markdown.test.ts`, `-css`, `-zip`, `-options`, `-consistency`, `export-panel` | auto + manual (checklist 14) | OK |
| 17 | Protótipos somente leitura no workspace | `lib/prototype/*`, `components/PrototypePanel.vue` | `layout-harmonizer-prototype.test.ts` | auto | OK |
| 18 | Privacidade/logs/i18n/rede: cinco idiomas, nada de estado/schema em log, exports sem requisição | `lang/*/tools.php`, `lang/*/colors.php`, i18n subtree do servidor | `layout-harmonizer-i18n.test.ts`, Pest `LayoutSystemExportMessagesTest`, `TranslationsParityTest` | auto | OK |
| 19 | Migrations e efeitos (aditivas, sem backfill) | `database/migrations/2026_11_19_00000{1,2,3}_*` | suíte Pest inteira em SQLite; revisão manual das migrations | auto + manual | OK |
| 20 | Regressões nas outras tools | suíte inteira (Pest e Vitest), `types:check`, `build` | `composer test`, `npm run test`, gates | auto | OK |

## Resultados reais dos gates (2026-11-22)

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formatação PHP | `composer test` (Pint) | verde, sem alteração pendente |
| Análise estática PHP | `composer test` (PHPStan) | verde, sem erro |
| Suíte PHP | `php artisan test` | **1856 testes, 1854 passaram, 2 skipped, 33306 asserções**, exit 0 |
| Suíte JS | `npx vitest run` | **163 arquivos, 1947 testes passaram**, 311,77 s, exit 0 |
| Tipos | `npm run types:check` | verde (Wayfinder + `vue-tsc --noEmit`) |
| Formatação | `npm run format:check` | “All matched files use Prettier code style!” |
| Lint | `npm run lint:check` | exit 0 — **0 erros**, 27259 avisos (indentação Vue pré-existente em todo o repositório) |
| Build | `npm run build` | exit 0, `✓ built in 20.52s`, manifesto gerado |
| Concorrência (opt-in) | `php artisan test --filter=LayoutSystemConcurrencyTest` | **2 skipped** — sem `LAYOUT_CONCURRENCY_TEST=1` e sem MySQL `_test`; ver pendência P2 |

Os 2 skips da suíte PHP são exatamente os dois casos de
`LayoutSystemConcurrencyTest`.

Observações reais dos gates:

- `composer test` roda Pint e PHPStan e depois a suíte PHP com timeout de
  processo de 300 s do Composer; nesta máquina a suíte passa disso e o
  Composer aborta após o Pint/PHPStan. Por isso a suíte foi rodada pelo comando
  equivalente `php artisan test` (mesmo `artisan test` do script), com o
  resultado acima. Nenhum teste foi pulado ou filtrado.
- No primeiro `npx vitest run` dois casos estouraram o default de 5 s (um
  spawn de PHP e uma montagem de componente) sob a carga dos 163 ambientes
  jsdom, e na repetição seguinte um outro caso; isolados, todos passam em menos
  de 1 s. Correções aplicadas: cache da leitura de `lang/` em
  `__tests__/support/langMessages.ts` (uma leitura por arquivo de teste) e
  `testTimeout: 20000` no `vitest.config.ts` — o limite maior não mascara
  asserção, só cobre a carga da máquina. Com isso o suite cheio ficou verde
  (1947/1947).

## Verificação do CSS no navegador (Playwright)

Feita com o Chromium real do Playwright, aplicando o CSS exportado de uma
fixture (seed `#3b82f6`, `analogous`, alvo AA, prefixo `lh`, escopo `:root`)
numa página `about:blank`/`setContent`. O CSS e o ZIP foram gerados por um
teste temporário (removido ao fim; nada versionado) e o ZIP foi aberto com
`Expand-Archive`.

| Verificação | Resultado |
| --- | --- |
| Formato OKLCH suportado | `CSS.supports('color', 'oklch(0.6 0.1 250)')` = `true` |
| 33 tokens declarados | 33 custom properties `--lh-*` |
| Claro sem atributo = `data-theme="light"` | idêntico, token a token |
| Escuro em `data-theme="dark"` | difere do claro |
| Bloco base em HEX/rgba | todas as 33 variáveis em `#rrggbb`/`rgba()` |
| `@supports` em OKLCH | todas as 33 variáveis em `oklch(...)` |
| OKLCH × fallback produzem a mesma cor | pixel a pixel: delta máximo 0 (claro) e 1 (escuro, arredondamento para 8 bits) |
| ZIP exportado | abre com `colors.css` (6.794 B) e `design-instructions.md` (12.319 B) |

Exemplos lidos pelo navegador: `--lh-background` claro =
`oklch(98.5% 0.006 259.81)` = `#f8fafe` = `rgb(248, 250, 254)`; escuro =
`oklch(16% 0.015 259.81)` = `#090d14` = `rgb(9, 13, 20)`.

## Checklist manual

Executar depois do deploy candidato (ou build local servido), com conta Free e
uma conta paga quando houver; marcar cada item.

1. Anônimo: abrir `/{locale}/tools/colors/layout-harmonizer`; o card de
   exportação mostra o cadeado e o CTA leva ao login preservando a paleta; a
   cópia individual de HEX/OKLCH funciona.
2. Quadro: seed sem valor padrão, quatro estratégias, temas claro/escuro e
   comparar; os 33 tokens aparecem com finalidade, proveniência, AA medido e
   valores HEX/OKLCH.
3. Teclado e leitor: percorrer o quadro e o editor só com `Tab`/`Enter`/`Esc`,
   conferir foco visível, `aria-pressed` do bloqueio, ids únicos com dois
   editores abertos e o texto dos selos lido sem depender do `title`.
4. Estratégia/tema: trocar estratégia e tema sem perder manual; o Accent
   acompanha θ + 120° em todas as estratégias.
5. Sugestões e aceite: aceitar por tema e nos dois; o Primary alterado aceite
   não reconstrói outros tokens (sem cascata) e um token bloqueado continua
   bloqueado depois do aceite.
6. Secundária: manual opaca; depois com alpha e fundo de composição — o quadro
   mostra a cor efetiva composta; sem fundo ou com fundo alpha aparece o erro
   traduzido e o quadro não é gerado.
7. Fundo da seed: colar `rgba(59,130,246,0.6)` no fundo; erro explícito, nada
   é descartado em silêncio.
8. Rascunho: como anônimo, gerar um quadro, recarregar (hidrata), abrir o
   diálogo e fechar com `Esc` (não apaga), importar; depois usar "Descartar
   rascunho" (pede confirmação) e conferir o quadro vazio e o storage limpo.
9. Salvar (logado): nome sugerido, descrição/tags, privado por padrão; salvar
   não consome cota em duplicata; criar até a cota e conferir o limite
   conjunto com as paletas.
10. Editor: reabrir o sistema salvo sem regenerar; editar metadados, arquivar
    e restaurar; a URL pública responde 404 quando privado/arquivado e volta
    quando restaurado com o slug livre.
11. Downgrade: simular conta acima da cota; visualizar/editar/duplicar
    conforme a regra, sem exclusão automática.
12. Sistema salvo em `algorithmVersion` 1: abre no modo compatível, mostra as
    versões, edita/bloqueia; "Regenerar com a versão atual" pede confirmação,
    retém bloqueios e descarta edições sem bloqueio só após o aceite.
13. Página pública: canonical, metas, sitemap e OG estática; crédito só da
    origem pública; exportação não aparece.
14. Exportação: nos cinco idiomas, baixar Markdown (conteúdo e nome), CSS
    (`:root` e classe, com fallback HEX/`rgba()` + `@supports`) e ZIP (abre com
    os dois arquivos); prefixo/escopo inválidos bloqueiam com erro ligado ao
    campo.
15. CSS aplicado: colar o CSS baixado numa página de teste e conferir as
    variáveis no claro (sem atributo e `data-theme="light"`) e no escuro
    (`data-theme="dark"`); num navegador sem OKLCH, o fallback HEX/rgba vale.

## Desvios registrados e correções

| ID | Achado | Correção |
| --- | --- | --- |
| M1 | Teto de croma do F0 excedido por 4 tokens | croma corrigido; versão 2; fixtures regeneradas (ADR-178) |
| M2 | `primary-variant` colidia com `primary-active` em várias seeds | `VARIANT_DELTA` 0.12; regra da variante aplicada à secundária |
| M3 | Alpha na secundária descartado em silêncio | alpha suportado com fundo de composição explícito |
| M4 | Alpha no fundo da seed descartado em silêncio | rejeição explícita com i18n próprio |
| S1 | Estado/validação sem os campos novos | `LayoutSystemState` valida `secondary` RGBA e `secondaryBackground` |
| S2 | Chaves i18n faltando para regeneração e descarte | chaves novas nos cinco locales e paridade |
| S3 | Redirect da rota pública usava o código do locale, não o slug | teste corrigido; `Locales::slug()` coberto |
| U1 | `PARAMETROS.md` publicava a tabela antiga | corrigido, com emenda datada |
| U2 | Compatível não oferecia regenerar sem trocar entrada | banner + diálogo de confirmação |
| U3 | Readonly não oferecia regenerar | oferta presente com confirmação |
| U4 | Restaurar apagava override/bloqueio no primeiro clique | confirmação dentro do popover |
| U5 | `CONTRATO-TECNICO.md` descrevia cascata | corrigido para a decisão sem cascata (ADR-167) |
| U6 | Não havia como descartar o rascunho anônimo salvo | ação com confirmação limpa quadro e storage |
| U7 | Fechar o diálogo de importação apagava o rascunho | só o botão explícito descarta |
| U8 | Dois editores abertos repetiam o id do campo | `useId()` por editor |
| U9 | Erros de exportação não ligados ao campo para leitor de tela | `aria-describedby`, `aria-invalid` e `role="alert"` |
| U10 | Selos dependiam do `title` (hover) | texto acessível (`sr-only`) junto do selo |
| D1 | Glossário dizia que o Accent não existia fora da triádica | entrada corrigida (θ + 120° em todas) |
| D2 | `CONTRATO-TECNICO.md` citava nome 120/descrição 2000 | 80/500, com o teto das colunas |
| D3 | `DADOS.md` propunha discriminador e não a tabela própria | alinhado à ADR-168 |
| T1 | Quatro requisitos de `TESTES.md` sem teste direto | casos criados |
| T2 | Concorrência só coberta por HTTP | teste MySQL opt-in |
| A1 | `testTimeout` default (5 s) e um spawn de PHP por asserção deixavam o suite cheio vermelho sob carga | cache em `langMessages.ts` e `testTimeout: 20000` |
| A2 | Três erros de tipo e 14 arquivos fora do Prettier | corrigidos; `types:check` e `format:check` verdes |

## Pendências

| ID | Pendência | Gravidade | Impacto | Evidência | Ação |
| --- | --- | --- | --- | --- | --- |
| P1 | `ADR-171` cita um auxiliar `isRetired` que não existe no código (o comportamento é garantido por `archived_slug`/`layout_system_retired_slugs`) | Baixa | Documental; não afeta o comportamento | busca por `isRetired` em `app/` e `resources/js/` retorna vazio | Emenda datada em ADR futuro; o ADR não foi reescrito nesta fase |
| P2 | Varredura de concorrência real exige MySQL descartável e não rodou neste ambiente | Baixa | Sem prova do `FOR UPDATE` aqui; o teste está pronto e guardado | `LayoutSystemConcurrencyTest` fica `skipped` sem `LAYOUT_CONCURRENCY_TEST=1` + banco `_test` | Rodar no CI/ambiente com MySQL de teste: `DB_CONNECTION=mysql DB_DATABASE=devtools_test DB_USERNAME=... DB_PASSWORD=... LAYOUT_CONCURRENCY_TEST=1 php artisan test --filter=LayoutSystemConcurrencyTest` |
| P3 | `(int) ''` no `.env`: variável vazia vira 0, não o default | Baixa | Configuração errada derruba limites a zero | `config/colors.php` usa `(int) env(...)` | Documentado no `.env.example` e no RUNBOOK §12; remover a linha para usar o default |
| P4 | Sem MySQL, o lock real da cota conjunta só é provado em CI | Baixa | Mesmo efeito de P2 | suíte SQLite não tem lock de linha | Mesma ação de P2 |

Nenhuma pendência de gravidade média/alta ficou aberta.

## Deploy e rollback

Instruções completas no `docs/RUNBOOK.md` §12 (resumo):

- **Deploy**: caminho normal do cron (seção 2); as três migrations rodam uma
  vez com `migrate --force` e são só criação de tabela/pivot. Não há
  backfill, job nem `.env` obrigatório (todos os limites têm default).
- **Migrations**:
  - `2026_11_19_000001_create_layout_systems_table` — cria `layout_systems`;
  - `2026_11_19_000002_create_layout_system_retired_slugs_table` — cria o
    ledger de slugs aposentados;
  - `2026_11_19_000003_create_color_tag_layout_system_table` — cria o pivot
    sistema↔tag.
- **Efeito do `algorithmVersion` 2**: sistemas salvos em 1 abrem no modo
  compatível e regeneram para 2 pela tela; nada é reescrito em massa.
- **Rollback**: só de aplicação (voltar a release); os dados ficam. Um sistema
  salvo em 2 abre somente leitura no código antigo (versão desconhecida,
  ADR-169) e volta ao normal com a release nova. Reverter as migrations só é
  seguro se a intenção for descartar os dados do track.
- **Desligar/limitar sem migration**: baixar `COLORS_FREE_LIMIT`, zerar
  `COLORS_LAYOUT_SYSTEMS_SITEMAP_LIMIT`, privar/arquivar sistemas; remover a
  superfície inteira exige deploy de código (não há flag).

## Decisões não aprovadas (para o pedido)

Nenhuma decisão de produto precisou ser reaberta: a auditoria encontrou
desvios de implementação e documentação, corrigidos dentro do que já estava
aprovado (ADR-178 registra as emendas). O protótipo editável/exportável, a
exportação pela página pública/API e mudanças de quota continuam fora de
escopo, como aprovado.
