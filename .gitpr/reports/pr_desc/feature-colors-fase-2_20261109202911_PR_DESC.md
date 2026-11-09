# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona tools de cores com namespace de URL agrupado
```

---

## 🎯 Resumo

Introduz a trilha Colors com duas ferramentas novas — **HTML Color Picker** e **Color Palette Generator** — e o mecanismo de **agrupamento de URL por tool (ADR-116)** que permite um namespace `/{locale}/tools/colors/<slug>` sem quebrar as rotas planas já existentes. Toda a matemática de cor roda no navegador (nada é enviado ao servidor) e a única persistência é o `localStorage` local, sob chaves versionadas. O agrupamento é puramente de URL: não cria landing nova nem altera categoria, e o par `grupo+slug` é validado no registro para evitar URLs ambíguas.

## 🛠️ Mudanças Técnicas

- **Novas tools**: `html-color-picker` (parser/formatador HEX/RGB/HSL/OKLCH/OKLab, mapeamento de gamut, contraste WCAG, shades/tints, amostrador por imagem, conta-gotas e histórico/favoritos locais) e `palette-generator` (escala de 11 tons em OKLCH com exportação CSS/SCSS/JSON/Tailwind v3/v4).
- **Núcleo de cor** (`resources/js/lib/color/`): módulos independentes de Vue/i18n para types, convert, named, parse, format, gamut, contrast, mix e scale.
- **Componentes compartilhados** (`resources/js/components/colors/`): `ColorArea`, `HueSlider`, `AlphaSlider`, `ChannelSliders`, `ColorInputField`, `ColorPickerControls`, `FormatsList`, `SwatchButton`.
- **Composable** `useColorState`: fonte única de escrita do estado, com matiz-âncora e finalize (histórico só após pointerup/blur/Enter).
- **Agrupamento de URL (ADR-116)**: nova rota `tools.show.grouped`, métodos `Tools::findForUrl`, `Tools::groups` e `Tools::routeFor`; `ToolController`, `Seo`, `Sitemap` e `StructuredData` passam a resolver o par slug+grupo.
- **Registro**: novo `ToolGroupId`, campo opcional `group` no `ToolManifest`/manifest JSON, `toolPath` monta URL aninhada quando existe grupo, nova categoria `colors` e ícones `palette`/`swatch-book`.
- **Internacionalização**: chaves de `html-color-picker`, `palette-generator` e bloco `shared.color_picker` em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **Testes**: cobertura unitária do núcleo de cor e dos componentes, além de testes Feature para o agrupamento, sitemap, SEO e structured data.
- **`.gitignore`**: adiciona `.gitpr/conf/issue.yml`.

## ⚠️ Impacto/Avisos

- **Contrato de URL**: tools que declaram `group` respondem **apenas** na URL aninhada; a rota plana agora retorna 404 para elas. Tools sem grupo continuam na URL plana (e a aninhada com grupo errado também 404).
- **SEO**: `tools.show.grouped` foi incluído na lista de rotas indexáveis; o sitemap passa a emitir as URLs aninhadas para as tools agrupadas.
- **Sem banco de dados e sem novas dependências de runtime**: a matemática de cor é implementada do zero.
- **Persistência local apenas**: histórico/favoritos/última cor ficam em `localStorage` sob `colors.picker.*.v1` (limite de 50 cada). Nada trafega para o servidor.
- **Versionamento de algoritmo**: `PALETTE_ALGORITHM_VERSION = 1`; alterar a tabela de tons exige bump da versão, já que os testes verificam os literais.

close #139

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_9_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)