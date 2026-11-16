# Survey — Layout Harmonizer F2 (interface do quadro e registro)

Data: 2026-11-16
Branch: `feature/layout-harmonizer-fase-2`
Contrato: `docs/colors/layout-harmonizer/PROMPT-Fase2-interface_quadro_cores.md`
Base: F1 entregue (motor puro, 33 tokens, 4 estratégias, 2 temas, fixtures, 10 ADRs).

## Contexto da tarefa

A F1 entregou o motor puro; a F2 é a primeira fase visível ao usuário. O prompt
empacotado pede: registro no grupo Colors com rota/locale existentes, reuso do
Color Picker, quatro estratégias, claro/escuro com alternância e comparação lado
a lado, quadro semântico completo, valor/finalidade/proveniência/diagnóstico de
contraste por token, texto separado da demonstração real de cor, cópia
individual inclusive para anônimo, último estado válido preservado em entrada
inválida, tema da tool independente do tema gerado, estados vazios, mensagens,
responsividade, acessibilidade e texto nos 5 idiomas. Fora da F2: edição
manual, bloqueios completos, persistência, exportação e protótipos novos; sem
botões funcionais para o que não existe.

Regras em vigor: GIT só pelo usuário (gitpr); toda string visível via i18n;
tools do cliente 100% no navegador; não reabrir decisões aprovadas; não inventar
defaults silenciosos.

## Decisões do grill

| # | Tema | Decisão |
| --- | --- | --- |
| 1 | Escopo | Alinhar ao prompt F2; ADR-153 e ADR-162 entram, ADR-161 (rascunho) vai para a F4 |
| 2 | Entrada | Picker completo reutilizado; alpha exige fundo explícito; abre vazia, sem seed padrão |
| 3 | Estratégia inicial | Análoga (`analogous`) |
| 4 | Diagnóstico | AA por token + resumo dos pares que falham; sem AAA nem sugestões |
| 5 | Cópia | HEX e OKLCH por token; anônimo copia |
| 6 | i18n | Só `tools.layout-harmonizer.*` nos 5 idiomas |
| 7 | Modos | Claro/Escuro/Comparar, padrão Comparar; grid no desktop, empilhado no mobile |
| 8 | Célula | Chave + finalidade + HEX + OKLCH + proveniência + AA |
| 9 | Recursos futuros | Sem botões/CTA de salvar, exportar, protótipos ou login |

## Fatos levantados (verificados no código)

- Registro: manifest com exatamente `{slug,status,category,group,icon,order,keywords}`;
  rota `/{locale}/tools/{group}/{slug}` nomeada `tools.show.grouped` (ADR-116);
  `Show.vue` fornece cabeçalho/SEO e `toolProps` vazio para esta tool.
- Listas fixas que precisam mudar juntas: `MVP` (JS), `mvpToolSlugs()` e
  `Tools::available()` (PHP), asserções de `findForUrl`, página agrupada em
  `ToolPageTest` e loop de slugs agrupados em `SitemapTest`.
- Picker: `ColorPickerControls` recebe `state: ColorState`; o `error` do picker
  pode ficar obsoleto ao mexer nos sliders, então a validade é julgada por
  `parseColor(text)`.
- Cópia: `useCopyFeedback()` devolve `{message, copy(value, success?)}`;
  `writeClipboard` lança em falha, então a confirmação só aparece após sucesso.
- reka-ui `Primitive` tem `inheritAttrs: false`: `title` em `Badge` não chega ao
  DOM; tooltips de AA/croma foram movidos para elementos envoltórios.
- Motor: `generateLayoutSystem` devolve `{seed,strategy,themes}`; quadro com 33
  tokens em 5 famílias (11/4/4/10/4) e 16 checks atribuídos ao foreground;
  accent existe em todas as estratégias (ADR-163).
- i18n: paridade folha a folha nos 5 arquivos e compilação estrita; `@`/`|` crus
  proibidos; nenhum namespace novo nesta fase.

## Relatório de testes da fase

- `npm run test` — 139 arquivos / 1693 testes, todos verdes (inclui os 5 arquivos
  `layout-harmonizer-*` e sem regressão no palette-generator).
- `npm run types:check` — limpo.
- `npm run format:check` — limpo.
- `npm run lint:check` — 0 erros (24.833 warnings de estilo pré-existentes no repo).
- `composer test` — pint ok, phpstan 0 erros, Pest **1737 testes / 30.924
  asserções**, verde.
- Achado das gates: registrar a tool quebrou `SeoMetaTest` ("falta o PNG de cada
  tool que funciona"). O fluxo documentado é rodar `php artisan seo:og-image`,
  que desenha a imagem de cada tool `available`; rodado, gerou
  `public/og/layout-harmonizer.png` e atualizou `public/og-default.png` (lista
  de tools), mantendo os demais PNGs idênticos.
