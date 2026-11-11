# ADR-126 - Protótipos vetoriais, papéis e exportação
Status: Aceito
Data: 2026-11-11
Relacionados: ADR-094 (protótipos), ADR-100 (exportação PNG, emenda), ADR-120 (escala), ADR-121 (harmonias), ADR-123 (papéis persistidos), ADR-125 (OG usa o mesmo renderer)

## Contexto
A ADR-094 pediu protótipos de site e mobile para visualizar a paleta aplicada; a ADR-100 pediu PNG 1x/2x e SVG. A F4 precisou decidir **como** desenhar (captura de tela ou árvore própria), como os papéis semânticos funcionam nos dois temas, quais papéis cada elemento usa, como o PNG se relaciona com o SVG, como a fonte aparece nos dois formatos e como garantir fidelidade sem transformar o teste em comparação de pixels.

As restrições continuam: tudo no navegador, sem dependência de captura (`html2canvas` etc.), sem texto de interface hardcoded (o conteúdo de exemplo vem do i18n) e papéis persistidos junto da paleta.

## Decisão

### 1. SVG é o formato canônico; o PNG é o mesmo SVG rasterizado

O protótipo não é uma captura do app: é uma **árvore de primitivas** (`rect`, `circle`, `text`) com posição, tamanho, cor e metadados, serializada por `renderSvg.ts` num SVG puro e determinístico (ordem fixa de atributos, números com 2 casas, `-0` normalizado, texto e atributos escapados). O `Preview` injeta esse SVG; a exportação SVG usa a mesma string; o PNG é rasterizado **do mesmo SVG** (`svg` → `Image` → canvas → `toBlob`), em 1x e 2x, revogando a object URL sempre. SVG e PNG nunca divergem porque não existem dois desenhos.

Cada nó leva `data-role` (o papel que o pinta) e `data-section` (a região: `canvas`, `header`, `appbar`, `tabs`, `hero`, `buttons`, `links`, `cards`, `content`, `form`, `footer`, `palette`). Isso torna o SVG auditável e permite conferir papel → elemento nos testes e nos snapshots.

### 2. Dois templates fixos e um tamanho de referência

| Template | Tamanho | Regiões |
| --- | --- | --- |
| site | 1200×900 | header (marca, 3 links, CTA), hero, botões primário/secundário, links, 3 cards, formulário, footer |
| mobile | 390×844 | appbar, **tabs no topo**, hero, 2 cards, formulário, footer — **sem bottom nav** |
| og | 1200×630 | marca, barra primária, título, tagline e faixa da paleta (ADR-125) |

Só site e mobile são oferecidos ao usuário; `og` é interno, usado pela imagem de compartilhamento. A regra "mobile usa apenas tabs" da SPEC vira a ausência de navegação inferior e a presença de tabs.

### 3. Papéis semânticos, oito por tema, editáveis e persistidos

Os oito papéis fixos (`primary`, `secondary`, `accent`, `background`, `surface`, `text`, `text_muted`, `border`) valem para site e mobile e existem **por tema** (`light`/`dark`). Cada elemento aponta para um papel; trocar o papel repinta o protótipo inteiro sem tocar nos modelos.

- Defaults da escala: tonalidade por papel (claro: 600/700/400/50/100/950/600/200; escuro: 600/700/400/950/900/50/400/800), em `lib/palettePayload.ts`.
- Defaults da extraída: as três primeiras cores por peso em primary/secondary/accent; neutros derivados da base em OKLCH (matiz preservado, croma proporcional), para uma imagem cinza gerar neutros cinza.
- Os papéis são **persistidos** com a paleta (ADR-123) e editados no editor; "Restaurar padrões" recalcula a partir das cores gravadas. A aba Protótipo vive no `GeneratorWorkspace` por slots (`tabs-extra`/`panels-extra`), sem duplicar o gerador.
- Texto sobre `primary`/`secondary` mantém o `data-role` mas usa `readableOn()` (preto ou branco por luminância relativa), nunca a cor do papel — legibilidade acima da fidelidade cromática.

### 4. Conteúdo de exemplo vem do i18n

O renderizador é puro e não traduz nada: `PrototypeCopy` carrega os textos (marca, navegação, hero, botões, links, cards, formulário, rodapé, tabs e tagline) resolvidos por `colors.prototype.sample` nos cinco idiomas. O preview não embute fonte (a Libre Franklin já vem do `app.css`); a exportação — SVG e PNG — embute Regular e Bold como data URI, carregada por import dinâmico num chunk próprio (~290 kB), com `font-display: block` para o rasterizador não pintar o fallback.

### 5. Fidelidade verificada por estrutura e snapshots, não por pixels

- Snapshots determinísticos do SVG (site/mobile × claro/escuro) + asserções papel → elemento (`data-role` e `fill` no DOM).
- `prototype-templates` confere regiões pedidas, ausência de bottom nav e uso de papéis em botões.
- `prototype-rasterize` confere dimensões 1x/2x, origem SVG, tipo do blob e revogação da URL em sucesso e em todas as falhas.
- A matriz manual (relatório da fase) confere a leitura visual; não há comparação binária de PNG no CI.

### 6. Exportação é download local

`exportPrototype.ts` gera nome estável, SVG com fonte e PNG do mesmo SVG e entrega via `downloadBlob`; nada é enviado ao servidor. A OG reusa `prototypePngFromDocument` para não reconstruir o documento em dois modos.

## Consequências
- O resultado é vetorial de verdade: zoom sem perda no SVG e PNG idêntico à composição, com fonte embarcada fora do app.
- Papéis persistidos fazem o protótipo fazer parte da paleta: reabrir a paleta mantém os ajustes, e a OG usa o tema claro como decisão editorial.
- Testes determinísticos (sem canvas na renderização e sem pixels no snapshot) pegam regressão de layout, cor e escaping.
- O custo é manter duas tabelas de defaults (escala e extraída) em sincronia com o backend, um chunk de fontes de ~290 kB e a regra de que o conteúdo de exemplo precisa existir nos cinco idiomas.

## Alternativas rejeitadas
- **Captura de tela (`html2canvas`/`dom-to-image`)**: dependência pesada, saída raster desde o início, sem SVG real e sujeita a diferenças de renderização.
- **Um desenho para o preview e outro para o export**: divergência garantida; a fonte única SVG elimina isso (ADR-100, emenda).
- **Papéis globais (sem tema)**: claro e escuro exigem contrastes opostos; um único conjunto não serve aos dois.
- **Papéis calculados só no servidor**: o gerador precisa deles antes de salvar e a edição é local; o backend só valida e persiste o que chegou.
- **Embutir a fonte no preview**: pagaria ~290 kB por abertura; o app já tem a fonte e a exportação paga o custo uma vez.
- **Comparar PNG byte a byte no CI**: frágil entre versões de navegador/canvas; estrutura + snapshots + matriz manual cobrem melhor.
