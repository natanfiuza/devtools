# Color Palette Generator

## URL
/{locale}/tools/colors/palette-generator

## Estado
**Fase 3 implementada** (2026-11-09): escala determinística de 11 tons a partir de uma cor base, contraste por tom, identificação da base, valores convertidos, cópia e exportações (F2); harmonias complementar, análoga, triádica, tetrádica e monocromática em OKLCH e HSL (ADR-121); e extração de 5/7/9/11 cores dominantes de imagem local ou URL HTTPS com worker, cancelamento e limites configuráveis (ADR-122). Tudo no navegador e sem persistência. Tokens W3C DTCG/Style Dictionary, protótipos e salvar paleta continuam nas fases seguintes.

## Entrada
A **cor base** (HEX/HEX8 e demais formatos do picker compacto, com alpha), que alimenta a escala e as harmonias; e, na aba de extração, uma **imagem local** ou uma **URL HTTPS**. A escala é sempre a de 11 tons fixos (ADR-101); 5/7/9/11 pertence só à extração de imagem.

## Abas da Fase 3
- **Escala**: o conteúdo da F2 (estado vazio/inválido, linha da base, 11 tons e exportações).
- **Harmonias**: cinco tipos, dois métodos, chips com HEX, cópia e "usar como base"; auxiliares, não entram nos exports.
- **Extração**: imagem local ou URL, quantidade 5/7/9/11 (padrão 11), prévia, status com cancelar, erros tipados e cores dominantes com percentual.

O picker compacto fica sempre na coluna esquerda; clicar numa cor de harmonia ou de imagem troca a base e a escala acompanha. O picker não tem mais o seletor nativo do navegador: a cor vem do campo, da área 2D, dos sliders e do **conta-gotas da tela** (API `EyeDropper`, exibido só onde existe), que aplica a cor escolhida preservando o alpha da base. A paleta extraída vive em memória na página (sobrevive à troca de abas, é descartada ao recarregar) e está pronta para a F4.

Todo botão de copiar (formato, base, tom, escala, exportação, harmonia e cor extraída) troca o ícone por um check verde por alguns segundos e volta ao ícone de copiar; o recado continua no rodapé `role="status"`.

## Escala da Fase 2
- Algoritmo determinístico em OKLCH, tabulado literalmente na ADR-120, com `PALETTE_ALGORITHM_VERSION = 1`.
- Croma relativo à base, normalizado pelo tom mais próximo; cinzas permanecem acromáticos.
- Alpha preservado nos 11 tons; contraste compõe por polaridade do texto.
- A base nunca é alterada. Quando ela não coincide com o tom 500, a tela marca o tom mais próximo e avisa que a base foi mantida intacta.
- Tons cujo croma não cabe no sRGB são marcados como cortados, sem forçar a escala.

## Critérios de aceite da Fase 2
1. `/{locale}/tools/colors/palette-generator` abre nos 5 idiomas; a URL plana `/tools/palette-generator` responde 404.
2. Com `#6366f1`, a tela mostra 11 cartões, a linha da base com os formatos convertidos, o selo no tom 600 e a nota de que a base não é o 500.
3. Cada cartão exibe HEX (ou HEX8), duas linhas de contraste com razão e veredito AA/AAA, e copia o próprio tom; o ícone do botão clicado vira um check verde por alguns segundos e volta.
4. “Copiar escala” entrega as 11 linhas `tom: hex`; o feedback aparece em `role="status"` e o ícone confirma a cópia.
5. Limpar o campo mostra o estado vazio com “restaurar exemplo”; texto inválido mantém a escala anterior e exibe o aviso.
6. As abas CSS, SCSS, JSON, Tailwind v3 e Tailwind v4 mostram conteúdo gerado na hora e permitem copiar e baixar; o nome inválido é normalizado e avisado.
7. O conta-gotas da tela (quando o navegador tem `EyeDropper`) define a base preservando o alpha nos 11 tons; onde a API não existe, o botão não aparece e o campo, a área 2D e os sliders continuam funcionando.
8. Nenhuma requisição ao servidor e nenhuma persistência.

## Critérios de aceite da Fase 3
1. As abas Escala, Harmonias e Extração funcionam nos 5 idiomas, com o picker compartilhado e foco/teclado visíveis.
2. Os cinco tipos de harmonia aparecem nos dois métodos; a base é marcada; clicar em "usar como base" muda a escala; o resultado é determinístico e as harmonias não entram nos exports.
3. Base acromática reduz a harmonia e a nota explica; monocromática respeita o clamp e avisa quando o conjunto encolhe.
4. Extração de arquivo PNG/JPEG/WebP/GIF/AVIF mostra a prévia, a origem, a versão do algoritmo e as cores com percentual; 5/7/9/11 é respeitado (padrão 11).
5. Imagem transparente, minúscula, uniforme ou acima dos limites resulta em aviso tipado, sem cores inventadas.
6. URL HTTPS com CORS liberado extrai; URL bloqueada mostra o erro de CORS e o atalho para o arquivo local; HTTP, URL inválida, SVG e tipo errado são recusados.
7. Cancelar interrompe o trabalho e volta ao estado ocioso; trocar de imagem rápido descarta o resultado antigo; desmontar libera worker, aborta o fetch e revoga a prévia.
8. A paleta extraída sobrevive à troca de abas e ao "usar como base" e some ao recarregar (só memória).
9. Nenhuma imagem ou cor sai do navegador e nenhum limite numérico está escrito no cliente.

## Escalas
- Sempre os 11 tons fixos: `50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950` (ADR-101).
- A escolha de 5, 7, 9 ou 11 cores pertence **somente** à extração de imagem (`import-export.md`, ADR-099), nunca à escala do gerador.

## Harmonias (Fase 3)
- Cinco tipos com a base incluída e marcada: complementar (`0, 180`), análoga (`-30, 0, +30`), triádica (`0, +120, +240`), tetrádica retangular (`0, +60, +180, +240`) e monocromática (sem rotação; L da base `-28, -14, 0, +14, +28` em OKLCH `0.08–0.99`, ou `-28, -14, 0, +14, +28` em HSL `0–100`).
- Método **OKLCH** por padrão (gira `h`, mantém `L`/`c`, gamut por redução de croma com selo "fora do sRGB") e **HSL** como alternativa (gira `h`, mantém `s`/`l`).
- Alpha herdado da base; cinzas e clamp de L deduplicam o conjunto, e a tela avisa quando reduzir.
- Auxiliares: clique define a nova base e a escala de 11 tons é recalculada; as harmonias **não** entram nos exports da F2 (ADR-121).

## Extração de imagem (Fase 3)
- Arquivo local (JPEG, PNG, WebP, GIF, AVIF; SVG recusado) ou URL **HTTPS** com CORS direto na origem — nunca proxy, nunca upload (ADR-122).
- 5, 7, 9 ou 11 cores dominantes (padrão 11), separadas da escala 50–950; color-thief 3.5.0 (MMCQ/OKLCH) num worker.
- Pixels com alpha < 0,5 são ignorados (nada de compor sobre fundo); imagem toda transparente é erro `empty_image`.
- Pesos e percentuais vêm dos pixels visíveis atribuídos à cor mais próxima em OKLab; ordenação por peso decrescente.
- Poucas cores distintas → devolve as existentes com aviso (`sufficient: false`), sem inventar.
- Limites de bytes, lado, pixels, análise e timeout vêm de `config/tools.php` (`limits.colors_image`), entregues por `ToolProps`.
- Cancelar, trocar de imagem rápido (resultado antigo descartado), prévia com object URL revogado e worker encerrado ao desmontar.

## Exportações
HEX, RGB, HSL, OKLCH, OKLab, CSS, SCSS, JSON, Tailwind v3/v4, tokens W3C DTCG (importáveis por Tokens Studio e Figma Variables), Style Dictionary, código.

## Protótipos
Site e mobile, light/dark, papéis editáveis, exportar PNG 1x/2x e SVG.
