# ADR-121 - Harmonias de cor do gerador de paletas
Status: Aceito
Data: 2026-11-09
Relacionados: ADR-117 (núcleo de cor e gamut), ADR-120 (algoritmo da escala), ADR-101 (escala de 11 tons)

## Contexto
A F2 do Color Palette Generator derivou uma escala de 11 tons de uma cor base (ADR-120), mas não havia como descobrir cores que combinassem com ela. A F3 (`SPEC-COLORS.md` §5) pede as harmonias complementar, análoga, triádica, tetrádica e monocromática, em HSL e OKLCH. A decisão precisa fixar ângulos, o que varia em cada método, o que acontece com cinzas e com cores fora do sRGB, e — sobretudo — se as harmonias entram ou não nas exportações da escala.

Sem uma regra explícita, cada implementação escolheria ângulos diferentes (tetrádica quadrada ou retangular), outro método como padrão, e a mesma base mostraria harmonias diferentes entre tela, documentação e eventuais integrações.

## Decisão

### 1. Cinco tipos com ângulos literais, base incluída

| Tipo | Deslocamentos de matiz (graus) |
| --- | --- |
| Complementar | `0, 180` |
| Análoga | `-30, 0, +30` |
| Triádica | `0, +120, +240` |
| Tetrádica (retangular) | `0, +60, +180, +240` |
| Monocromática | sem rotação; varia a luminosidade |

- A **base faz parte do conjunto** e é a primeira cor na ordem da definição; recebe o selo "Base" na interface.
- Tetrádica é a **retangular** (`0/60/180/240`), que contém a base e forma dois pares complementares; a quadrada (`0/90/180/270`) não contém a relação com a base no mesmo sentido.
- A monocromática mantém H (e S) da base e anda em L: OKLCH `-0.28, -0.14, 0, +0.14, +0.28` preso em `0.08–0.99`; HSL `-28, -14, 0, +14, +28` pontos presos em `0–100`. A ordem vai do mais escuro ao mais claro.

### 2. Método OKLCH por padrão, HSL como alternativa

- **OKLCH**: converte a base com `rgbToOklch`, gira apenas `h` e mantém `L` e `c`; mapeia de volta com `mapOklchToSrgb`, que reduz o croma por busca binária preservando L e H quando a cor não cabe no sRGB e marca `clipped`.
- **HSL**: converte com `rgbToHsl`, gira apenas `h` e mantém `s` e `l`; reconstrói com `hslToRgb`. Em HSL nada é marcado como cortado — a própria representação cabe no sRGB por construção.
- OKLCH é o padrão porque é o espaço do núcleo (ADR-117) e o mesmo da escala (ADR-120); HSL existe para quem raciocina em matiz/saturação/luminosidade e para comparar com outras ferramentas.
- `HarmonyColor.oklch` guarda a leitura OKLCH de cada cor nos dois métodos, para exibição e testes.

### 3. Alpha herdado, dedupe e acromático

- Todas as cores herdam o alpha da base; nada é composto. O HEX vira HEX8 quando a base tem alpha.
- O conjunto é **deduplicado pela chave canônica** (ADR-118), preservando a primeira ocorrência. Em cinzas, as rotações de matiz colapsam na mesma cor; no monocromático, o clamp pode repetir o limite. `HarmonySet.expected` guarda quantas cores a definição pede e a interface avisa quando `colors.length < expected`.
- `isBase` é calculado pela chave canônica, não pelo índice: a base continua marcada mesmo que outra cor tenha caído no mesmo sRGB depois do gamut mapping.

### 4. Harmonias são auxiliares, não entram nas exportações

- As harmonias **não** fazem parte dos cinco exports da F2 (CSS, SCSS, JSON, Tailwind v3/v4). Clicar numa cor a define como **nova base** da página; a escala de 11 tons é recalculada a partir dela e é a escala que se exporta.
- O cálculo é puro (`buildHarmony`), sem Vue, DOM ou i18n: mesma base, mesmo tipo e mesmo método produzem sempre as mesmas cores. Os rótulos vêm do i18n e os controles do componente `HarmonyPanel`.

## Consequências

- Uma única função determina todas as harmonias, e o resultado é idêntico entre a tela e os testes.
- A pessoa pode partir da base e chegar a uma cor complementar em dois cliques, sem sair da página e sem criar um segundo conceito de paleta.
- Como as harmonias não são exportadas, a escala da F2 continua com um contrato estável; incluir harmonias nos exports seria uma decisão nova (emenda ou ADR próprio).
- A monocromática em bases muito claras ou escuras repete o limite de L e o conjunto fica menor; a interface avisa em vez de inventar tons.
- Em bases acromáticas a harmonia fica reduzida a uma cor; a nota explica o motivo (girar a matiz de um cinza não cria cor).

## Alternativas rejeitadas
- **Tetrádica quadrada (`0/90/180/270`)**: é uma definição válida, mas a retangular mantém a base como um dos cantos e a relação de pares complementares com o mesmo modelo das outras harmonias.
- **Harmonias calculadas em OKLab e exibidas em HSL**: misturaria o espaço de cálculo com o de exibição e tornaria o resultado dependente da conversão de exibição.
- **HSL como padrão**: HSL não é perceptualmente uniforme e a rotação de matiz com S/L fixos produz saltos visuais diferentes; OKLCH é o espaço do núcleo.
- **Passos fixos de L para a monocromática**: a cor base deixaria de estar no centro do conjunto e bases muito claras ficariam sem tons úteis.
- **Compor sobre fundo para resolver alpha**: mostraria outra cor, não a escolhida.
- **Exportar as harmonias junto com a escala**: ampliaria o contrato dos exports da F2 sem decisão de produto que peça isso.
