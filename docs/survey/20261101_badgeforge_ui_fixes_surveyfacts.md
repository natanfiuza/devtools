# Survey — BadgeForge: correções de UI e auditoria das fases

- **Branch:** `feature/badgeforge-fase-1`
- **Data:** 2026-11-01
- **Origem:** pedido do usuário com quatro defeitos + verificação das fases do
  `docs/SPEC-BADGEFORGE.md`

## Contexto da tarefa

Depois da F1 entregue, o usuário relatou:

1. a introdução da página aparece duas vezes;
2. alterar `badge-logo-size` esvazia a tela de inputs;
3. o select de logo deveria estar em ordem alfabética;
4. o texto do badge está colado ao logo; quer 3px de espaço à direita.

E pediu para verificar se todas as etapas planejadas no SPEC já foram
concluídas.

## Fatos levantados

### Introdução duplicada

- `resources/js/pages/tools/Show.vue:80,117-119` renderiza
  `tools.<slug>.intro` para toda tool, a partir de `toolKeys` em
  `resources/js/tools/registry.ts:61-70`.
- `resources/js/tools/badgeforge/Tool.vue:116-118` renderizava a mesma chave;
  é a única tool do repositório que repete a intro (grep por `.intro` em
  `resources/js/tools` só achou o BadgeForge).
- O manifest não carrega texto (`resources/js/tools/badgeforge/manifest.json`);
  a `description` é uma frase diferente e só aparece no cabeçalho.
- Nenhum teste afirmava ocorrência única; `tool-page.test.ts` usa `toContain`.

### Inputs somem ao mudar o tamanho do logo

- `Tool.vue:361-374`: `<Input v-model="form.logoSize" type="number">`.
- O `Input` do shadcn-vue não declara `type` e repassa o atributo nativo; o
  `vModelText` do Vue 3.5 converte para `number` em `type="number"`.
- `builder.ts:125-135` (`integer`) fazia `raw.trim()` e lançava
  `TypeError: raw.trim is not a function` dentro do computed `spec`,
  derrubando o render do componente.
- `badge-cache` (Tool.vue:408-414 → `builder.ts:96-101`) tinha o mesmo defeito.
- Cobertura existente só usava strings.

### Ordem do select de logo

- `Tool.vue:303-309` itera `props.logos` na ordem recebida.
- `BadgeForgeToolProps::for()` usava `LogoCatalog::metadata()`, que percorre
  `config/badgeforge.php` na ordem declarada (github, gitlab, npm, composer...).
- O fixture `tests/Fixtures/badgeforge/tool-props.json` é comparado por
  igualdade exata (ordem incluída) em `BadgeForgeToolPropsTest` e
  `BadgeForgePageTest`.

### Gap logo ↔ texto

- `resources/badgeforge/styles.json` define `gap: 2` nos cinco estilos.
- `StaticBadgeRenderer.php:21-56` e o espelho `lib/render.ts:15-56` reservam
  `logoSize + gap` e centralizam o texto com `intdiv`, que perde 0,5px quando a
  largura do texto é ímpar (`flat_logo_left.svg` mostrava 1,5px).
- Sete fixtures têm logo: `flat_logo_left`, `flat_logo_right`,
  `flat_square_logo_left`, `plastic_logo_right`, `for_the_badge_logo_left`,
  `social_logo_right`, `logo_color_transparent`.

### Fases do SPEC (§16)

| Fase | Escopo | Status no repositório |
|---|---|---|
| F1 | Núcleo SVG + builder + snippets + cache | Entregue (2026-10-31) |
| F2 | PNG/Imagick/download/docs | Não iniciada |
| F3 | Logos personalizados + presets + contas | Não iniciada |
| F4 | Providers GitHub/npm/Packagist/Docker | Não iniciada |
| F5 | Endpoint JSON + SSRF | Não iniciada |
| F6 | Providers Codecov/Coveralls/Laravel/PHP/Python | Não iniciada |
| F7 | Providers customizados + analytics + admin | Não iniciada |
| F8 | OpenAPI + docs + hardening | Parcial: só docs da F1 |

Evidências: única rota de badges é `GET api/badges/static/{label}/{message}.{ext}`
com `where('ext','svg')`; não há migrations, models, controllers, config ou
testes de F2+; `CLAUDE.md` diz "Fase atual: F1".

## Decisões

| # | Questão | Decisão |
|---|---|---|
| Q1 | Como chegar aos 3px | **gap = 3** nos cinco estilos, sem mexer na centralização (escolha do usuário). Visual 2,5–3,0px com logo à esquerda e 3,0–3,5px à direita; regenera só as 7 fixtures com logo. |
| Q2 | Onde ordenar os logos | No servidor, em `BadgeForgeToolProps::for()`, com `strcasecmp` por nome e desempate por slug. |
| Q3 | Como corrigir o crash numérico | `integer()` e o estado do formulário aceitam `string | number`; inválidos caem no fallback. |
| Q4 | Intro duplicada | Remover o parágrafo do `Tool.vue`; a chave continua no cabeçalho da página. |
| Q5 | Fases do SPEC | Só relatar; nenhuma fase nova entra nesta tarefa. |

## Resultado

Implementado em 2026-11-01; detalhes no relatório
`docs/claude-code/reports/feature_badgeforge-fase-1/20261101_feature_badgeforge-fase-1_badgeforge_ui_fixes.md`.
