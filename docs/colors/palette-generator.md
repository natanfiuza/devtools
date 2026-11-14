# Color Palette Generator

## URL
/{locale}/tools/colors/palette-generator

## Estado
**Fase 5 implementada** (2026-11-14): o gerador mantém a escala determinística de 11 tons, as harmonias, a extração de imagem e o salvamento da F2–F4 e ganha a **biblioteca pessoal de cores** compartilhada com o picker (histórico e favoritos da conta; anônimo com 20 histórico/50 favoritos e importação explícita — ADR-127, ADR-131, ADR-132), **presets** como entrada da geração (nome + base, sem consumir cota — ADR-129) e **tokens W3C DTCG, Style Dictionary e TypeScript**, com primitivas e, no editor, semânticas claro/escuro (ADR-130). A **importação validada** de JSON nativo, DTCG e Style Dictionary vale no gerador e no editor, sem executar o arquivo e sem descartar o estado em caso de erro (ADR-133). O anônimo continua com rascunho em `sessionStorage` por 24 h, agora podendo carregar escala e papéis importados (ADR-096, ADR-123, emenda de 2026-11-14).

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

## Critérios de aceite da Fase 4
1. “Salvar” autenticado abre o modal (nome, descrição, visibilidade, tags) e cria a paleta; anônimo grava o rascunho e vai ao login, retomando de onde parou.
2. Escala salva grava a base e os 11 tons efetivos; extraída salva a base escolhida e as 5/7/9/11 cores na ordem de peso, com a origem reduzida a `{kind,label|host}` — nunca imagem, URL completa, blob ou percentual.
3. Reabrir a paleta mostra exatamente as cores gravadas (escala congelada); a primeira troca de base descongela e recalcula.
4. O editor tem as mesmas abas do gerador e a aba Protótipo (site/mobile × claro/escuro), com os 8 papéis persistidos e editáveis.
5. Falhar a geração da OG não desfaz o salvamento; o aviso e o botão “Gerar imagem” aparecem.
6. Paleta arquivada abre somente leitura até ser restaurada.
7. Toda string visível vem do i18n nos 5 idiomas e o único tráfego de ferramenta é o das ações de conta (salvar, OG, tags).

## Critérios de aceite da Fase 5
1. Para quem está logado, histórico e favoritos de cor vêm da conta — sem limite funcional, paginados por cursor — e são a **mesma lista** mostrada no HTML Color Picker; favoritar aqui aparece lá e vice-versa (ADR-127, ADR-131).
2. O anônimo mantém 20 no histórico e 50 nos favoritos no `localStorage`, sem deduplicar por formatação (a chave é a cor canônica) e sem nenhuma requisição ao servidor (ADR-132).
3. O painel da biblioteca importa o histórico local para a conta só por ação explícita, em lotes; nada é importado no login e o `localStorage` só é limpo depois de todos os lotes passarem (ADR-132).
4. Presets guardam apenas nome + cor base, pertencem à conta e não consomem cota de paleta; aplicar um preset troca a base e recalcula a escala (ADR-129).
5. A exportação entrega os três formatos de tokens — W3C DTCG, Style Dictionary e TypeScript — além de CSS, SCSS e JSON nativo, sempre com as 11 primitivas e, no editor, com os 8 papéis × claro/escuro quando existem; a versão do formato é 1 e o alpha sai em HEX8 onde o formato aceita (ADR-130).
6. A importação validada aceita JSON nativo, DTCG e Style Dictionary, mostra a prévia e só altera o estado ao clicar em **Aplicar**; erro de estrutura, tamanho, profundidade, alias ou ciclo vira diagnóstico e **nunca** descarta a escala ou os papéis atuais (ADR-133).
7. A ida e volta (exportar e importar de novo) preserva base, 11 tons e papéis no editor; no gerador puro só as primitivas voltam (ADR-130, ADR-133).
8. O rascunho anônimo continua em `sessionStorage` por 24 h e, desde a F5, pode carregar escala e papéis importados, descartando os papéis quando a assinatura do snapshot muda (ADR-096, ADR-123, ADR-133).
9. Toda string visível vem do i18n nos cinco idiomas e o tráfego de rede da ferramenta segue restrito às ações de conta.

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

## Protótipos (Fase 4)

- Aba no gerador (e no editor de paleta salva): site 1200×900 (header, hero, botões, links, cards, formulário, footer) e mobile 390×844 (tabs no topo, **sem** bottom nav), em tema claro e escuro.
- Renderizador SVG vetorial próprio e determinístico; o PNG 1x/2x é a rasterização do **mesmo** SVG, com a fonte embutida só na exportação (ADR-126).
- 8 papéis fixos (`primary`, `secondary`, `accent`, `background`, `surface`, `text`, `text_muted`, `border`) por tema, editáveis com “Restaurar padrões” e persistidos na paleta; site e mobile compartilham os papéis. Padrões da escala: 600/700/400/50/100/950/600/200 (claro) e 600/700/400/950/900/50/400/800 (escuro); da extraída: as 3 primeiras cores por peso e neutros em OKLCH a partir da base.
- Exportar SVG e PNG 1x/2x baixa localmente; nenhuma captura de tela e nenhuma dependência nova.

## Organização pessoal e tokens (Fase 5)

### Biblioteca de cores
- Uma única biblioteca no cliente (`useColorLibrary`), usada pelo picker e pelo gerador: mesmo histórico, mesmos favoritos, mesma chave canônica e mesmos painéis (ADR-131).
- Logado: histórico e favoritos são dados da conta, sem limite funcional, lidos por cursor e gravados de forma otimista com reversão em falha (ADR-127). Anônimo: 20 no histórico e 50 nos favoritos, tudo no `localStorage` e sem requisição (ADR-132).
- O histórico local entra na conta apenas pela ação explícita do painel, em lotes de até 200; o navegador só é limpo depois que todos os lotes passam (ADR-132).

### Presets
- Entrada da geração: nome (até 60 caracteres, único por conta) + cor base (ADR-129). Nada de escala, papéis ou tema — o preset não é paleta e não consome cota.
- Criar, renomear, aplicar e excluir no painel do gerador; aplicar troca a base e a escala acompanha. O teto por conta é opcional em `config/colors.php` (`presets.limit`, default `null` = sem teto).

### Exportação de tokens
- Formatos: W3C DTCG JSON, Style Dictionary, TypeScript (`const`), CSS, SCSS e JSON nativo — todos com `COLOR_TOKEN_FORMAT_VERSION = 1` no cabeçalho/metadados (ADR-130).
- Primitivas: os 11 tons `50–950`. Semânticas: os 8 papéis × `light`/`dark`, só quando a paleta tem os papéis (editor); o gerador puro exporta apenas primitivas.
- DTCG leva metadados em `$extensions["devtools.colors"]`; Style Dictionary sai limpo (`value`/`type`), usando o tom 500 como base na volta; o JSON nativo carrega os metadados. Alpha preservado em HEX8 onde o formato aceita e o gamut fora do sRGB é sinalizado (ADR-117, ADR-130). Sem API do Figma.

### Importação validada de tokens
- Aceita JSON nativo, W3C DTCG e Style Dictionary; a detecção é estrutural e arquivo ambíguo é recusado com diagnóstico (ADR-133).
- Só `JSON.parse`: nada é executado. Limites de arquivo (512 KiB), tokens/nós (5000), profundidade da árvore (40), encadeamento de aliases (10) e diagnósticos exibidos (20) vêm de `config/colors.php` e chegam por `ToolProps`.
- A prévia mostra base, tons e papéis sem tocar na página; **Aplicar** só habilita sem erro. Alias é resolvido como dado, ciclo é recusado, papéis incompletos são descartados com aviso e o snapshot assinado decide se os papéis ainda valem.
- Não existe endpoint de importação: o arquivo nunca sai do navegador.
