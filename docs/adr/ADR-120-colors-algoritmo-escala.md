# ADR-120 - Algoritmo da escala de 11 tons
Status: Aceito
Data: 2026-11-09

## Contexto
O Color Palette Generator (F2) precisa derivar 11 tons (`50–950`) de uma cor base de forma determinística, verificável e estável entre navegador, exportação e documentação. A escolha da cor base não pode ser restringida: o usuário informa qualquer sRGB com alpha, e essa cor precisa aparecer intacta no resultado. Sem uma regra explícita, cada implementação ajustaria luminosidade e croma de um jeito, e a escala exportada deixaria de bater com a exibida.

## Decisão
A escada é fixa em OKLCH com valores literais; o croma é **relativo à base** e normalizado pelo tom mais próximo.

| Tom | L | Fator de croma |
| ---: | ---: | ---: |
| 50 | 0.97 | 0.18 |
| 100 | 0.94 | 0.30 |
| 200 | 0.88 | 0.50 |
| 300 | 0.81 | 0.72 |
| 400 | 0.74 | 0.88 |
| 500 | 0.66 | 1.00 |
| 600 | 0.58 | 0.92 |
| 700 | 0.50 | 0.78 |
| 800 | 0.42 | 0.62 |
| 900 | 0.34 | 0.46 |
| 950 | 0.22 | 0.30 |

```
baseOklch = rgbToOklch(base)
nearest   = tom com menor |L_fixo - L_base|   (empate -> tom mais claro)
c(t)      = baseOklch.c * fator[t] / fator[nearest]
tom(t)    = mapOklchToSrgb({ l: L[t], c: c(t), h: baseOklch.h })
rgba(t)   = { ...tom(t).rgb, a: base.a }
```

- **Base intacta**: a base é a entrada sem arredondar; nunca é movida para o tom 500. Se a base não coincidir com o tom 500, a interface marca o tom mais próximo e diz, de forma neutra, que a base foi preservada.
- **Croma normalizado**: dividir pelo fator do tom mais próximo faz esse tom carregar exatamente o croma da base; os demais tons escalam proporcionalmente, o que mantém a cor escolhida coerente com a escala.
- **Acromático**: `baseOklch.c < 1e-4` produz `c = 0` e `h = 0` em todos os tons — variação de matiz em cinza seria ruído de conversão.
- **Gamut**: onde o croma pedido não cabe no sRGB, `mapOklchToSrgb` reduz o croma por busca binária preservando L e H e marca o tom como `clipped`; a interface lista os tons cortados. O corte não é "consertado" reduzindo a escala.
- **Alpha**: os 11 tons herdam o alpha da base; nada é composto na geração. O contraste é calculado por opção de texto (texto preto sobre o tom composto no fundo branco; texto branco sobre o tom composto no fundo preto); com `a = 1` as duas leituras coincidem, e a interface avisa quando a composição importa.
- **Arredondamento**: o resultado guarda floats; HEX e exportações arredondam na formatação.
- **Versão**: `PALETTE_ALGORITHM_VERSION = 1` acompanha a tabela. Mudar L, fatores, normalização ou composição de contraste exige bump da versão, porque muda toda paleta já exportada e aparece no JSON e na interface.

## Consequências
Escala determinística e idêntica entre UI, cópia e exportações; a base escolhida sempre aparece sem alteração; o JSON se autodescreve com `algorithmVersion`. Como o croma é normalizado, tons extremos com base saturada podem ser marcados como `clipped` — comportamento esperado, sinalizado na tela. A tabela é contrato: ajustá-la informalmente quebra a estabilidade dos exports, então qualquer mudança é emenda desta ADR com nova versão.

## Alternativas rejeitadas
- **Escada de luminosidade do Tailwind**: copia a estética de uma paleta específica em vez de definir um modelo próprio e verificável.
- **Escada em HSL**: HSL não é perceptualmente uniforme; os degraus de L não correspondem ao mesmo salto visual.
- **Forçar a base no tom 500** (fator 1.0 só no 500): alteraria a cor escolhida ou mentiria sobre ela; a base deixaria de ser a entrada.
- **Croma absoluto para todas as bases**: cinzas ganhariam cor e bases saturadas ficariam iguais, destruindo a relação com a cor informada.
- **Contraste sobre tom opaco quando há alpha**: mediria outra cor, não a que o usuário escolheu.
