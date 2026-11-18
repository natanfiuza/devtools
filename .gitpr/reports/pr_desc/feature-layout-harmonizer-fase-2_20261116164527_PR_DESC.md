# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona interface do Layout Harmonizer com board de tokens
```

---

## 🎯 Resumo

Entrega a interface completa (fase 2) da ferramenta **Layout Harmonizer**, que gera os 33 tokens semânticos de cor para os temas claro e escuro a partir de uma única cor seed. O objetivo é dar ao usuário um quadro de design tokens pronto para uso, com diagnóstico de contraste WCAG AA e valores copiáveis (HEX e OKLCH), sem sair do navegador.

A ferramenta foi pensada para o fluxo "último valor válido": entradas inválidas nunca apagam o quadro já gerado, apenas exibem um aviso — o usuário mantém o contexto do que estava vendo enquanto corrige a seed.

## 🛠️ Mudanças Técnicas

- Novo módulo `resources/js/tools/layout-harmonizer/` com `Tool.vue`, `LayoutWorkspace.vue` e componentes de apoio (`SeedPicker`, `SeedSummary`, `StrategySelect`, `ThemeBoard`, `ThemeComparison`, `TokenGroup`, `TokenCell`, `ViewModeSwitch`, `ContrastSummary`, `FeedbackPreview`).
- Composable `useLayoutSystem` centraliza o estado (seed, estratégia, fundo de composição para cores com alpha, último resultado válido) e delega o cálculo ao motor existente — sem storage, sem requisições e sem tocar no tema do site.
- Camada de apresentação pura em `lib/boardView.ts`: agrupamento por família na ordem fixa do catálogo, índice de pares de contraste por token e formatação de razão por locale.
- Três modos de visualização (Claro, Escuro, Comparar) com `aria-pressed`, e suporte a seed com transparência exigindo fundo de composição explícito.
- Traduções completas do bloco `tools.layout-harmonizer` em 5 idiomas (en, es, fr, pt_BR, pt_PT), incluindo chaves dinâmicas por token e por estratégia.
- Ajuste discreto em `ColorPickerControls.vue` para exibir estado vazio e adição do ícone `layout-panel-left` ao registro.
- Registro da tool via `manifest.json` (categoria/cores, ordem 120) e atualização dos testes de registry (front e back), sitemap e página da ferramenta em `/{locale}/tools/colors/layout-harmonizer`.
- Suíte de testes cobrindo agrupamento, i18n dos 5 idiomas, comportamento de estado (validação, alpha, estratégia inválida), células de token, workspace e ausência de persistência em `Storage`.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migração ou alteração de schema.
- **Env/dependências:** nenhuma variável de ambiente nova e nenhuma dependência adicionada; reaproveita componentes de UI, `@/lib/color` e `@/lib/clipboard` existentes.
- **Rota pública nova:** a ferramenta passa a responder em `/pt-br/tools/colors/layout-harmonizer` (e demais locales) e entra no sitemap como página indexável — vale revisar SEO e links internos após o deploy.
- **Privacidade:** todo o processamento ocorre no navegador; nenhum dado é persistido localmente ou enviado ao backend (comportamento coberto por teste).
- **UX:** entradas inválidas mantêm o último quadro na tela com aviso explícito — comportamento intencional, mas que deve ser comunicado em release notes para não ser confundido com "não atualizou".

close #166

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_7_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)