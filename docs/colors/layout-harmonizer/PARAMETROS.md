# Parâmetros do motor — Layout Harmonizer

Data: 2026-11-16 (emenda da auditoria F6 em 2026-11-22)
Status: aprovado (tabela candidata da F0 aprovada integralmente no grill da F1)
Versão: **2** (`LAYOUT_PARAMS_VERSION` e `LAYOUT_ALGORITHM_VERSION`; a emenda
da F6 está na seção **Emenda da auditoria (F6)**)
Implementação: `resources/js/tools/layout-harmonizer/engine/params.ts`
Fixtures: `resources/js/__tests__/layout-harmonizer-fixtures.test.ts` (+ snapshot)

## Escopo

Esta é a tabela numérica que o motor puro da F1 usa para transformar uma seed
em um sistema de layout nos temas claro e escuro. Mudar qualquer número daqui
muda o resultado do algoritmo: exige subir `LAYOUT_ALGORITHM_VERSION`,
atualizar as fixtures e registrar a mudança.

Nenhum parâmetro foi escolhido silenciosamente: os números abaixo vêm da tabela
aprovada na F0; o que ela não listava individualmente está na seção **Regras
derivadas**, com a regra explícita.

## Constantes globais

| Constante | Valor | Uso |
|---|---|---|
| `ACHROMATIC_CHROMA` | `0.02` | abaixo disto a seed é acromática: H 0° e C 0 nas derivações |
| `SECONDARY_CHROMA_FACTOR` | `0.85` | croma da secundária derivada |
| `VARIANT_DELTA` | claro `-0.12`, escuro `+0.12` | passo de L da variante de marca (emenda F6) |
| `VARIANT_CHROMA_FACTOR` | `0.90` | croma da variante de marca |
| `VARIANT_LIMITS` | claro piso `0.30`; escuro teto `0.85` | limites do L da variante |
| `STATE_DELTA.hover` | claro `-0.05`, escuro `+0.05` | passo de L do hover |
| `STATE_DELTA.active` | claro `-0.10`, escuro `+0.10` | passo de L do active |
| `CONTENT_CANDIDATES` | L `1.0` (branco) e L `0.18` (escuro), C 0 | conteúdo sobre marca/feedback |
| `STRATEGY_HUES.analogous` | secundária `+30°`, Accent `+120°` | ADR-156/ADR-163 |
| `STRATEGY_HUES.complementary` | secundária `+180°`, Accent `+120°` | ADR-156/ADR-163 |
| `STRATEGY_HUES.triadic` | secundária `+240°`, Accent `+120°` | ADR-156/ADR-163 |
| `STRATEGY_HUES.monochromatic` | secundária `0°`, Accent `+120°` | ADR-156/ADR-163 |
| `FOCUS_RING_PARAMS` | claro `0.55 / 0.15`, escuro `0.75 / 0.15` | foco |

## Tabela aprovada (L / C em OKLCH)

Matiz: da seed nos papéis derivados; `H seed` nas superfícies e textos;
`0°` quando a seed é acromática. Feedback tem matiz própria e não segue a seed.

| Papel | Claro L / C | Escuro L / C |
|---|---|---|
| background | 0.985 / 0.006 | 0.160 / 0.015 |
| surface | 1.000 / 0.003 | 0.200 / 0.018 |
| surface-variant | 0.955 / 0.010 | 0.245 / 0.020 |
| hairline | 0.900 / 0.012 | 0.330 / 0.020 |
| on-background | 0.220 / 0.012 | 0.955 / 0.006 |
| on-surface | 0.260 / 0.012 | 0.930 / 0.008 |
| on-surface-variant | 0.480 / 0.012 | 0.740 / 0.018 |
| primary | seed exata (L/C/H) | seed exata (L/C/H) |
| primary-variant | L − 0.12 (piso 0.30), C × 0.90 | L + 0.12 (teto 0.85), C × 0.90 |
| primary-hover / active | L − 0.05 / − 0.10 | L + 0.05 / + 0.10 |
| secondary (offset da estratégia) | L seed / C × 0.85 | idem |
| accent (todas as estratégias) | L seed / C seed, H + 120° | idem |
| link / hover / active | L seed / −0.05 / −0.10 | L seed / +0.05 / +0.10 |
| focus-ring | 0.55 / 0.15 | 0.75 / 0.15 |
| error (H 25°) | 0.55 / 0.19 | 0.68 / 0.18 |
| success (H 145°) | 0.55 / 0.15 | 0.72 / 0.15 |
| warning (H 75°) | 0.75 / 0.16 | 0.80 / 0.15 |
| info (H 245°) | 0.55 / 0.14 | 0.72 / 0.14 |
| on-primary / on-secondary / on-accent / on-error / on-success / on-warning / on-info | branco (L 1.0) ou escuro (L 0.18), o de maior razão | idem |

Superfícies, textos e foco não herdam o croma da seed: o C é o literal da
tabela (ou 0 quando a seed é acromática), e a matiz é a da seed.

## Regras derivadas

A tabela da F0 listava individualmente só os papéis acima. Os valores que o
catálogo exige e a tabela não trazia foram derivados por regra explícita, não
por número inventado:

1. **`secondary-variant`**: a mesma regra da variante de marca, aplicada ao L e
   ao C da secundária (passo ±0.12 com piso/teto, C × 0.90).
2. **`secondary-hover` / `secondary-active`**: os mesmos passos de hover/active
   da marca, aplicados ao L da secundária (C preservado).
3. **`accent-hover` / `accent-active`**: os mesmos passos de hover/active,
   aplicados ao L do Accent (C preservado).
4. **`link`**: L/C/H exatos da seed (o link é a cor da marca); hover/active
   seguem os passos da tabela de link (claro para baixo, escuro para cima).
5. **Accent fora da triádica**: mantém a derivação θ + 120° em todas as
   estratégias, para o catálogo não mudar de composição entre estratégias
   (recomendação do TOKENS.md, ADR-163). Na triádica ele é uma cor da harmonia;
   nas demais é o mesmo papel de destaque.
6. **Matiz do focus-ring**: a da seed (0° e C 0 quando acromática).
7. **Conteúdo `on-*`**: `on-background`, `on-surface` e `on-surface-variant`
   usam os tons explícitos da tabela; os `on-*` de marca e de feedback escolhem
   o candidato de maior razão de contraste sobre a cor base (empate fica com o
   branco, por ordem da tabela). O conteúdo é medido sobre a cor base e
   reutilizado nos estados; os pares com hover/active também entram nos checks.
8. **Pares de contraste**: cada `on-*` contra o fundo de mesmo nome; nas
   famílias de marca e no Accent, também contra `-hover` e `-active`;
   `on-surface-variant` é medido contra `surface-variant`.
9. **Seed acromática** (C < 0.02): superfícies, textos, foco, secundária e
   Accent perdem o croma (C 0, H 0). Feedback continua colorido e independente.

## Notas de calibração

- **`surface` claro**: L 1.0 com C 0.003 é impossível em sRGB; o mapeamento de
  gamut mantém L 1.0 e reduz o croma ao branco (`clipped = true`). É o
  comportamento esperado, registrado nas fixtures, não uma divergência.
- **Teto de croma da última linha da tabela da F0**: a linha "superfícies/textos
  C ≤ 0.012 / ≤ 0.020" agora vale para todo o catálogo. A emenda da F6
  (2026-11-22) corrigiu os quatro tokens que a excediam — `on-background`,
  `on-surface` e `on-surface-variant` no claro (de 0.015/0.015/0.020 para
  0.012) e `hairline` no escuro (de 0.022 para 0.020) — sem mudar matiz,
  claridade nem nenhum outro parâmetro.
- **Estados podem reprovar AA**: o conteúdo é escolhido contra a cor base;
  sobre hover/active a razão pode cair (ex.: `on-primary` sobre
  `primary-hover` = 4.16 para `#3b82f6`). O motor só reporta; a F3 exibe o
  aviso e nunca reescreve a cor automaticamente (ADR-158).
- **AAA é opcional**: o motor classifica AA e AAA sem arredondar, mas não altera
  nenhum token para atingir a meta.
- **Fixtures**: `#3b82f6`, `#10b981`, `#ef4444`, `#f59e0b`, `#7c3aed`,
  `#000000`, `#ffffff`, `#808080`, `oklch(0.70 0.35 20)` e
  `rgba(59,130,246,0.6)` sobre branco, cada uma nas quatro estratégias e nos
  dois temas (80 snapshots).

## Parâmetros das sugestões (F3)

As sugestões de correção de contraste têm constantes próprias, em
`resources/js/tools/layout-harmonizer/lib/suggestions.ts`, versionadas por
`LAYOUT_SUGGESTIONS_VERSION`. Elas não alteram nenhum token sozinhas: só
descrevem a menor mudança que faria um token de frente passar na meta contra os
fundos dele.

| Constante | Valor | Uso |
|---|---|---|
| `SUGGESTION_TARGETS.aa` | `4.5` | meta AA |
| `SUGGESTION_TARGETS.aaa` | `7` | meta AAA |
| `SUGGESTION_STEP` | `0.005` | passo de L OKLCH entre candidatos |
| `SUGGESTION_MAX_STEPS` | `200` | teto de passos em cada direção (L de 0 a 1) |
| `SUGGESTION_EPSILON` | `0.001` | folga acima da meta (razão ≥ meta + épsilon) |

Regras fixas da busca (não são números novos, mas definem o resultado):

1. Uma sugestão por **token de frente × tema**, agrupando todos os checks
   daquele token (ex.: `on-primary` contra `primary`, `primary-hover` e
   `primary-active`); um token que já passa na meta não gera sugestão.
2. Move só a luminosidade (`L0 ± k·0,005`), preservando matiz e croma, com o
   sRGB mapeado; o candidato preso no clamp de 0/1 não é repetido.
3. A razão projetada é medida no sRGB mapeado e precisa atingir a meta em
   **todos** os fundos; sem candidato até 200 passos, `proposal: null`
   (inviável, sem ação de aceite).
4. Desempate no mesmo passo: maior razão mínima; persistindo, a direção mais
   clara. A primeira cor viável no menor passo vence.
5. **AA não é universalmente alcançável** para tokens multi-fundo: se base,
   hover e active cruzam a faixa de luminância média, nenhum foreground único
   atende 4,5 nos três (caso real `#3b82f6`, `on-*` no tema claro). É o efeito
   esperado da regra 3, coberto por testes; a meta não muda nenhuma cor.
6. Trocar entre AA e AAA não regenera o quadro: a meta só filtra as sugestões.

## Parâmetros do sistema salvo (F4)

A F4 acrescentou limites e versões da persistência, todos em
`config/colors.php` (`layout_systems`, `tags` e `slug`) e espelhados nas props
do tool. Nenhum destes números altera a tabela do motor.

| Chave | Valor | Uso |
|---|---|---|
| `layout_systems.name_max_length` | `80` | nome do sistema (obrigatório, sugerido e editável) |
| `layout_systems.description_max_length` | `500` | descrição opcional |
| `layout_systems.input_max_length` | `120` | cada texto cru de `state.inputs` (`seedText`, `backgroundText`, `secondaryText`, `secondaryBackgroundText`, `strategy`) |
| `layout_systems.state_max_bytes` | `262144` (256 KB) | teto técnico do JSON de `state`; o caso real fica em ~20–25 KB |
| `layout_systems.per_page` | `20` | paginação da lista de sistemas |
| `layout_systems.preview_colors` | `6` | HEX exibidos no card do sistema |
| `layout_systems.sitemap_limit` | `500` | sistemas públicos ativos no sitemap |
| `tags.per_layout_system` | `10` | tags por sistema (o mesmo limite de nome de tag das paletas) |
| `palettes.free_limit` / `paid_limit` | `25` / `null` | cota conjunta (ADR-152/ADR-170) |

### Versões do estado

| Campo | Valor | Regra |
|---|---|---|
| `LAYOUT_STATE_VERSION` / `snapshotVersion` | `1` | envelope e snapshot suportados; shape diferente é somente leitura |
| `schemaVersion` | `1` | catálogo de tokens e checks |
| `algorithmVersion` | `2` (≥ 1) | versões menores que a atual abrem no modo compatível; maiores/desconhecidas ficam somente leitura |
| `suggestionsVersion` | `1` (≥ 1) | idem; sugestões ficam desabilitadas no modo compatível |

O servidor valida o shape v1, aceita `algorithmVersion`/`suggestionsVersion`
entre 1 e 999 e **deriva** `schema_version`, `algorithm_version`,
`suggestions_version`, `snapshot_version` e `tokens_count` do snapshot; o
cliente nunca envia essas colunas separadas (ADR-168).

### Modo compatível

Abre quando `state`/`snapshot`/`schema` são os atuais e `algorithm`/`suggestions`
são menores. Edita `rgba`/`hex`/`oklch` e os 16 checks do tema com o
`evaluateContrast` do motor, preservando as versões; restaurar e sugestões
ficam desabilitados; mudar seed/estratégia/secundária apenas marca
`pendingRegeneration` e bloqueia o salvamento até a regeneração explícita
(ADR-169). `readonly` é shape/versão mais nova ou desconhecida: quadro sem
edição, com ciclo de vida e duplicação.

## Parâmetros das exportações (F5)

| Parâmetro | Valor | Regra |
|---|---|---|
| `layout_systems.export.prefix_max_length` | `32` (`COLORS_LAYOUT_SYSTEM_EXPORT_PREFIX_MAX_LENGTH`) | teto do prefixo das variáveis; o padrão é `lh` |
| `layout_systems.export.scope_max_length` | `64` (`COLORS_LAYOUT_SYSTEM_EXPORT_SCOPE_MAX_LENGTH`) | teto do nome da classe de escopo; o padrão é `:root` |
| Prefixo | `^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$` | identificador ASCII em kebab-case; gera `--{prefixo}-{token}` |
| Escopo | `:root` ou `^\.[A-Za-z_][A-Za-z0-9_-]*$` | seletor arbitrário é recusado antes de gerar o arquivo |
| Temas no CSS | `{escopo}`, `{escopo}[data-theme="light"]`, `{escopo}[data-theme="dark"]` | claro é o default; sem `@media` automática |
| Fallback | HEX/`rgba()` + `@supports (color: oklch(0 0 0))` | alpha preservado, sem recompor |
| Nomes no ZIP | `design-instructions.md`, `colors.css` | fixos; avulsos `design-instructions.{locale}.md` e `colors.css` |
| Nome do ZIP | `{slug}-layout.zip` | sanitizado (NFD, hífen, máx. 32), fallback `layout-harmonizer` |
| Determinismo do ZIP | `mtime` fixo em `2026-01-01T00:00:00Z`, nível `6` | mesma entrada gera os mesmos bytes no mesmo fuso |
| Idiomas do documento | `en`, `pt_BR`, `pt_PT`, `fr`, `es` | seletor com default no locale ativo; subtree vem do servidor |
| Biblioteca ZIP | `fflate ^0.8.3` | dependência direta (MIT), auditada no grill |

## Emenda da auditoria (F6)

A auditoria da F6 (2026-11-22) encontrou dois desvios numéricos entre esta
tabela e o motor; ambos foram corrigidos aqui e no código, com
`LAYOUT_PARAMS_VERSION`/`LAYOUT_ALGORITHM_VERSION` em **2**:

1. **Teto de croma do F0**: a linha "superfícies/textos C ≤ 0.012 (claro) /
   ≤ 0.020 (escuro)" passa a valer sem exceção. Mudaram só o croma dos quatro
   tokens que a excediam: `on-background`, `on-surface` e `on-surface-variant`
   no claro (0.012) e `hairline` no escuro (0.020). Matte e claridade intactos.
2. **Passo da variante de marca**: `VARIANT_DELTA` era 0.10, igual ao de
   `active`; com o croma fora do gamut em várias seeds, o corte do mapeamento
   igualava `primary-variant` e `primary-active` no mesmo HEX. O passo 0.12
   mantém a direção e os pisos/tetos aprovados e garante L próprio para a
   variante em relação aos estados. A regra vale também para
   `secondary-variant`.

Consequências: as 80 fixtures douradas foram regeneradas (o diff foi revisado:
mudam apenas os tokens afetados e as razões/pares que os usam), e os sistemas
salvos com `algorithmVersion` 1 passam a abrir no modo **compatível**
(ADR-169), regeneráveis para 2 pela própria tela. Nenhuma migration: o
`algorithmVersion` fica no JSON do `state` e a validação do servidor aceita
1..999.

O Accent não mudou: o catálogo continua com 33 tokens e θ + 120° em todas as
estratégias (ADR-163), como recomenda `TOKENS.md`; a nota "revisável" da F1
fica fechada no ADR-178.

## Versionamento

| Versão | Data | Mudança |
|---|---|---|
| 1 | 2026-11-16 | primeira tabela aprovada e implementada na F1 |
| 2 | 2026-11-22 | emenda da auditoria F6: teto de croma dos 4 tokens, `VARIANT_DELTA` 0.12, fixtures regeneradas |
| Sugestões 1 | 2026-11-18 | parâmetros aprovados da F3 (meta, passo, teto e épsilon) |
| Estado 1 | 2026-11-19 | envelope `state` da F4 (`stateVersion`/`snapshotVersion` = 1) e limites de persistência |
| Exportações 1 | 2026-11-20 | prefixo/escopo, nomes fixos, ZIP determinístico e idiomas da F5 |
