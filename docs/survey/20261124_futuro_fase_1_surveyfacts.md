# Survey — Programa Futuro F1: conversor de formatos e diff

Data: 2026-11-24
Branch: `feature/futuro-fase-1`
Task: `futuro_fase_1`
Origem: `/grill-with-docs` — "Verificação e commit realizados, pode iniciar a primeira fase".

## 1. Contexto

A F1 do Programa Futuro (`docs/ROADMAP.md` §3.3, ADR-179) é a fase de porte L que entrega duas tools
100% no navegador: `data-format-converter` (JSON, YAML, TOML e XML, com formato de entrada e de saída
escolhidos na tela) e `diff` (texto e JSON). O roadmap foi commitado em `feature/futuro-roadmap`
(commit 35d7dbc) e o usuário criou `feature/futuro-fase-1` para a fase.

O repositório não tinha nenhum parser de YAML/TOML, nenhum algoritmo de diff, nenhum realce externo no
`CodeEditor` e nenhum rótulo configurável nos painéis do `ToolShell`. A fase precisa decidir dependências,
modelo de conversão, convenção de XML, semântica do diff e o que muda nos componentes compartilhados.

## 2. Fatos levantados

### 2.1 Infraestrutura de cliente

- `ToolShell.vue`: dois painéis (entrada | saída) com divisor (`useSplitPane`, persistido por `splitKey`) e
  abas no mobile com `force-mount`. Rótulos fixos `tools.ui.input`/`tools.ui.output` no desktop e nas abas;
  `inheritAttrs: false` descarta props não declaradas.
- `CodeEditor.vue`: CodeMirror 6; `extensions` entram **uma vez na montagem**; observa `modelValue` e
  `error`; não expõe a `EditorView`.
- `createEditor.ts`: monta extensões item a item; `setEditorDoc` e `setEditorError`; o realce de resultados
  que muda durante a vida do editor entra "por dentro da extensão".
- `lib/worker/client.ts`: `createWorkerClient(spawn, run)` com latest-wins, fallback para a main thread e
  `dispose`; `serve.ts` responde `{ id, result }`. Payload precisa ser cloneável; o cliente genérico não
  aceita transferables.
- `lib/file.ts` (`readTextFile`, `acceptAttribute`, regras de extensão/MIME), `download.ts`
  (`downloadText`), `clipboard.ts`, `bytes.ts`; `useCopyFeedback`, `useFileError`, `useSplitPane`.
- Limite de entrada: `config/tools.php` `limits.input_bytes` (5 MB), entregue por `ToolController` como
  `maxInputBytes`; texto limitado em bytes UTF-8 (`byteLength`).
- Nenhuma dependência de YAML, TOML, XML ou diff no `package.json`. `yaml` aparece só como peer opcional do
  Vite; `js-yaml` só transitivo do ESLint.

### 2.2 Registro, i18n, SEO e testes PHP

- `manifest.json`: `slug`, `status`, `category`, `icon`, `order`, `keywords`; `Tool.vue` resolvido por glob
  lazy em `components.ts`; página genérica `tools/Show.vue`; `ToolProps` vazio para tools de navegador.
- 15 slugs `available`, categorias `json|sql|encode|security|time|text|badges|colors`; `text` já existe.
- i18n: `tools.<slug>.name/description/keywords/intro` + strings de UI no mesmo bloco, nos 5 idiomas;
  `ToolsRegistryTest` cobra as quatro chaves por locale e o ícone no mapa de `icons.ts`.
- `ToolsRegistryTest` pina a lista exata de slugs, a ordem, a contagem e o sitemap; `SeoMetaTest` exige PNG
  1200×1200 por tool `available`; `php artisan seo:og-image` gera em `public/og/` (GD e fontes presentes).
- `json-viewer` já tem `analyzeJson` (`lib/json/parse.ts`) com linha/coluna e `prettyJson`.
- Nenhuma tool cobre YAML/TOML/XML nem diff.

### 2.3 Convenções de docs e testes

- Aceite: `docs/acceptance/LAYOUT-HARMONIZER-FASE-6.md` — escopo, fora de escopo, matriz requisito →
  implementação → teste, resultados reais dos gates, checklist manual, desvios, pendências, deploy/rollback.
- Vitest em `jsdom` (DOMParser nativo), `testTimeout: 20000`, testes em `resources/js/__tests__/*.test.ts`;
  helper `support/langMessages.ts` carrega as traduções reais; `docs` no `.prettierignore`.
- ADR mais alto: 179; 180 livre. CHANGELOG: uma entrada por track/fase com "Adicionado".

## 3. Grill — rodadas e decisões

### Rodada 1

| Pergunta | Decisão |
| --- | --- |
| Branch da fase | Nova `feature/futuro-fase-1`, criada pelo usuário |
| Modelo de conversão | Árvore intermediária comum (4 parsers + 4 serializadores) |
| Escolha do formato de entrada | Seleção explícita; sem auto-detecção |
| Layout do diff | Lado a lado |

### Rodada 2

| Pergunta | Decisão |
| --- | --- |
| Dependências de parser | Duas: `yaml` (eemeli) e `smol-toml`; XML por `DOMParser`; diff à mão |
| Convenção de XML | Atributos `@nome`, texto `#text`, irmãos repetidos em array, declaração/comentários descartados |
| Arrays no diff JSON | Comparação por índice |
| Slugs e categoria | `data-format-converter` e `diff`, categoria `text`, ordem 130 e 140 |

### Rodada 3

| Pergunta | Decisão |
| --- | --- |
| Motor do diff | Diff de linhas próprio; `ToolShell` ganha rótulos opcionais; `CodeEditor` ganha realce |
| Realce de sintaxe | Sem pacote novo de linguagem; YAML/TOML/XML em texto simples |
| Perda de conversão | Converte com aviso visível listando o que o destino não representa |
| Testes | Motor + fluxos críticos (Vitest) + Pest de registro; verificação manual no navegador |

**Fronteira esgotada.** Toda decisão de produto e de dependência está fechada; os detalhes restantes
(ordem das etapas, limites de worker, formato do relatório) são de implementação e estão no plano.

## 4. Plano da sessão

`docs/plans/feature_futuro-fase-1/20261124_futuro_fase_1_plansfacts.md`, cópia em
`docs/claude-code/plans/feature_futuro-fase-1/`.
