# Parâmetros do motor — Layout Harmonizer

Data: 2026-11-16
Status: aprovado (tabela candidata da F0 aprovada integralmente no grill da F1)
Versão: **1** (`LAYOUT_PARAMS_VERSION` e `LAYOUT_ALGORITHM_VERSION`)
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
| `VARIANT_DELTA` | claro `-0.10`, escuro `+0.10` | passo de L da variante de marca |
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
| hairline | 0.900 / 0.012 | 0.330 / 0.022 |
| on-background | 0.220 / 0.015 | 0.955 / 0.006 |
| on-surface | 0.260 / 0.015 | 0.930 / 0.008 |
| on-surface-variant | 0.480 / 0.020 | 0.740 / 0.018 |
| primary | seed exata (L/C/H) | seed exata (L/C/H) |
| primary-variant | L − 0.10 (piso 0.30), C × 0.90 | L + 0.10 (teto 0.85), C × 0.90 |
| primary-hover / active | L − 0.05 / − 0.10 | L + 0.05 / + 0.10 |
| secondary (offset da estratégia) | L seed / C × 0.85 | idem |
| accent (triádica) | L seed / C seed, H + 120° | idem |
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
   ao C da secundária (passo ±0.10 com piso/teto, C × 0.90).
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
  C ≤ 0.012 / ≤ 0.020" não bate com três tons de texto explícitos no claro
  (0.015 e 0.020) nem com `hairline` no escuro (0.022). O motor usa os valores
  por token, que são os aprovados, e não aplica um teto separado; a
  inconsistência fica registrada para a revisão da F2/F6.
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

## Versionamento

| Versão | Data | Mudança |
|---|---|---|
| 1 | 2026-11-16 | primeira tabela aprovada e implementada na F1 |
