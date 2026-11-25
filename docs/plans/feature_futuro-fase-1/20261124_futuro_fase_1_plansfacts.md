# Plano — Programa Futuro F1: conversor de formatos e diff

Data: 2026-11-24
Branch: `feature/futuro-fase-1`
Task: `futuro_fase_1`
Fonte: `docs/survey/20261124_futuro_fase_1_surveyfacts.md` e grill de 2026-11-24 (3 rodadas).

## Decisões

| Tema | Decisão |
| --- | --- |
| Entrega | Duas tools de cliente, registradas, i18n nos 5 idiomas, testadas, com ADR, aceite e relatório |
| Conversor | Matriz 4×4 via árvore intermediária; seleção explícita de entrada/saída |
| Dependências | `yaml` + `smol-toml`; XML por `DOMParser`; diff escrito à mão; sem pacote de linguagem |
| Diff | Lado a lado no `ToolShell` (rótulos Original/Modificado), linhas com realce e navegação; JSON por caminho, arrays por índice |
| Perdas | Converte com aviso visível do que o formato de destino não representa |
| Slugs | `data-format-converter` (ordem 130) e `diff` (ordem 140), categoria `text` |
| Docs | ADR-180, `docs/acceptance/FUTURO-FASE-1.md`, ROADMAP/SPEC/CHANGELOG/glossário, relatório |

## Entregáveis

1. `package.json`/`package-lock.json`: `yaml` e `smol-toml`.
2. `ToolShell.vue`: `paneLabels` opcional; `CodeEditor.vue`: `lineHighlights` + `view` exposto;
   `createEditor.ts`/tema: campo de realce e `setLineHighlights`.
3. `resources/js/tools/data-format-converter/`: manifesto, `Tool.vue`, motor (parse/serialize/árvore/perdas),
   worker, exemplos.
4. `resources/js/tools/diff/`: manifesto, `Tool.vue`, diff de linhas/JSON, relatório, worker, exemplos.
5. Registro/i18n/SEO: `icons.ts`, 5 arquivos de tradução, pins de `ToolsRegistryTest`, OG.
6. Testes Vitest (motor + fluxos críticos + componentes compartilhados) e Pest de registro.
7. Docs: ADR-180, aceite, ROADMAP §3.2/§7, SPEC §9, CHANGELOG, glossário, relatório.

## Etapas

| # | Conteúdo | Verificação |
| --- | --- | --- |
| 0 | Artefatos da sessão (surveyfacts, plansfacts, cópia do plano) | arquivos nos caminhos exatos |
| 1 | `npm install yaml smol-toml` | `npm ls`; registro da decisão |
| 2 | Componentes compartilhados + testes | testes novos e os 163 existentes verdes |
| 3 | Conversor completo | `npm run test` |
| 4 | Diff completo | `npm run test` |
| 5 | Registro, i18n, pins, OG | `composer test`; PNGs 1200×1200 |
| 6 | Docs e relatório | links e consistência |
| 7 | Gates finais | resultados reais no aceite |

## Verificação

`composer test`, `npm run test`, `npm run types:check`, `npm run format:check`, `npm run lint:check`,
`npm run build`, `php artisan seo:og-image`; Playwright manual (conversões, perdas, XML, diff texto/JSON,
mobile, 5 idiomas, temas); escopo do diff só em código/registro/i18n/testes/public/og/docs.

## Riscos

- Peso das libs: medir no build; se explodir, parar e reportar.
- Myers patológico: orçamento + fallback grosseiro avisado.
- `parsererror` do DOMParser difere entre navegador e jsdom: não testar o texto.
- Adições aos componentes compartilhados mantêm defaults; pins de registro atualizados em conjunto.

## Parada

Sem commit, push ou deploy. Status da F1 no roadmap só muda após gates reais.
