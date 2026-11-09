# Survey — Colors Fase 2: Color Palette Generator

- Data: 2026-11-09
- Branch: `feature/colors-fase-2` (survey iniciado em `feature/colors-fase-1`; a F1 foi commitada em `4bd143e`)
- Task: verificar a Fase 1 e iniciar a Fase 2 (`docs/colors/PROMPT-Fase2-gerador_paletas.md`)

## 1. Contexto da tarefa

O pedido foi (a) verificar se a Fase 1 do track Colors estava totalmente concluída e (b) iniciar a Fase 2 (Color Palette Generator). A verificação encontrou os gates verdes, mas uma lacuna funcional na F1: `docs/colors/html-color-picker.md:13` exige sliders RGB/RGBA e HSL/HSLA, e a implementação tinha apenas área 2D, matiz e alpha. No grill, o usuário decidiu corrigir a lacuna **antes** da F2.

A lacuna foi fechada na branch `feature/colors-fase-1` (setters RGB/HSL, `ChannelSliders`, `ColorPickerControls` e extração da superfície compartilhada), commitada pelo usuário em `4bd143e` e enviada. A Fase 2 segue na branch `feature/colors-fase-2`, criada a partir da F1. Nada é commitado/pushado por mim; o usuário aprova via gitpr. A árvore de decisões da F2 foi esgotada na rodada anterior; nenhuma decisão ficou em aberto.

## 2. Verificação da Fase 1

| Item | Resultado |
| --- | --- |
| Vitest direcionado (`color-*`, `html-color-picker`) | 111 testes, 11 arquivos, todos verdes |
| Pest direcionado (registry, página, sitemap, SEO, structured data, paridade i18n) | 54 testes, 14.562 asserções, todos verdes |
| Núcleo `resources/js/lib/color/` | ADR-117 cumprido: tipos, conversões, parse, format, gamut, contraste, mix |
| Picker | Tool, estado único, histórico/favoritos, EyeDropper com fallback, área 2D, matiz, alpha, contraste |
| Catálogo/rota/SEO | grupo `colors`, URL aninhada, OG estática `public/og/html-color-picker.png` |
| **Lacuna** | faltam sliders de canais RGB/RGBA e HSL/HSLA (`docs/colors/html-color-picker.md:13`) |
| **Doc desatualizada** | `docs/colors/README.md:4` ainda dizia “Fase 1 em preparação” |

## 3. Rodadas do grill

### Rodada 1

| Pergunta | Decisão |
| --- | --- |
| Modelo da escala | Escada fixa em OKLCH (11 tons, L fixa) |
| Forma do picker na F2 | Picker compacto reutilizando os componentes da F1 |
| Exportações | Nome + abas + copiar/baixar por formato |
| Estado inicial | Exemplo preenchido; limpar o campo mostra estado vazio com “restaurar exemplo” |

### Rodada 2

| Pergunta | Decisão |
| --- | --- |
| Lacuna dos sliders | Corrigir agora, antes da F2 |
| Croma | Relativa à cor base |
| Alpha | Preservado nos 11 tons |
| JSON | Autodescritivo |

### Rodada final

| Pergunta | Decisão |
| --- | --- |
| Contraste com alpha | Compor por opção de texto: preto = tom composto sobre branco; branco = tom composto sobre preto |
| Onde ficam os componentes compartilhados | Extrair para `resources/js/components/colors/` + i18n `tools.shared.color_picker.*` |

## 4. Fatos levantados no código

### Núcleo de cor (`resources/js/lib/color/`)

- Estado autoritativo `Rgba`; OKLCH é derivado; `mapOklchToSrgb` preserva L/H e marca `clipped`; `contrastReport` classifica no valor cru; `formatHex`/`formatHex8` arredondam só na exibição; `canonicalKey` normaliza alpha.
- `lib/color/index.ts` reexporta todos os módulos — qualquer módulo novo em `lib/color/` fica disponível por `@/lib/color`.

### Picker (`resources/js/tools/html-color-picker/`)

- `useColorState` é a porta única de escrita, com matiz âncora (cinza não perde o matiz), rascunho de texto e `finalize()`.
- Componentes reutilizáveis: `ColorArea`, `HueSlider`, `AlphaSlider`, `ColorInputField`, `ColorPreview`, `FormatsList`, `SwatchButton`; painéis específicos: `ContrastPanel`, `ShadesTintsPanel`, `HistoryPanel`, `FavoritesPanel`, `ImageSampler`.
- `ColorPreview` depende de EyeDropper, `ImageSampler` e aviso de gamut — não é um componente leve para compartilhar inteiro.
- `FormatsList` é autocontido e usa `formatAll`; o gerador precisa dos valores convertidos da base.
- `lib/download.ts` já expõe `downloadText(filename, text, type)`.
- `useCopyFeedback` aceita mensagem de sucesso própria.

### Catálogo, rota e SEO

- Manifest com `group: colors` gera a URL `/{locale}/tools/colors/<slug>` via `tools.show.grouped`; `Tools::findForUrl`, `groups()` e `routeFor` já suportam mais de uma tool no grupo.
- `Seo`, `Sitemap` e `StructuredData` leem `Tools::available()`/`routeFor` — nenhuma mudança de backend necessária.
- `SeoMetaTest` exige `public/og/<slug>.png` para toda tool `available`; `php artisan seo:og-image` gera o PNG.
- Listas exatas a atualizar: `resources/js/__tests__/tools-registry.test.ts` (`MVP`) e `tests/Feature/Tools/ToolsRegistryTest.php` (`mvpToolSlugs` + lista de `available`).
- `ToolPageTest`, `SitemapTest` e `StructuredDataTest` cobrem a URL agrupada.
- `home.test.ts` usa `availableTools.length` e espera 9 chips de categoria — como `palette-generator` entra na categoria `colors`, a contagem não muda.
- `command-palette.test.ts` usa contagens dinâmicas.

### i18n

- Bloco `tools.<slug>.*` nos cinco arquivos (`en`, `es`, `fr`, `pt_BR`, `pt_PT`).
- `TranslationsParityTest` exige conjuntos de folhas idênticos; `i18n-messages.test.ts` compila as mensagens (nada de `{`/`}` literais fora de interpolação).
- Slugs de idioma: `pt-br`, `pt-pt`; códigos: `pt_BR`, `pt_PT`.

### Ícones

- `resources/js/tools/icons.ts` é um mapa nome → componente; `SwatchBook` existe em `@lucide/vue` (verificado em `node_modules`).

## 5. Decisões técnicas derivadas

- Algoritmo em `resources/js/lib/color/scale.ts` (núcleo puro), reutilizável na F4/F6.
- Escala: L e fator de croma literais; `c(t) = c_base × fator[t] / fator[tomMaisPróximo]`; acromático zera croma; alpha da base é herdado; gamut por `mapOklchToSrgb`.
- Base nunca é alterada; `nearestTone` por menor distância de L; `baseIsTone500` por chave canônica.
- Exportadores em `resources/js/tools/palette-generator/lib/exporters.ts`; download via `downloadText`.
- Picker compacto novo (`ColorPickerControls`) usando as primitivas extraídas; o picker atual continua com sua composição (EyeDropper/formatos/painéis) para não regredir.
- Sem persistência na F2: nenhum acesso a `localStorage`.

## 6. Pendências fora do escopo da F2

Harmonias, 5/7/9/11 tons (só extração de imagem), importação de imagem/URL/JSON, DTCG/Style Dictionary, protótipos, OG dinâmica, compartilhamento, social/moderação e Figma API.
