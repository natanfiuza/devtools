# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona exportações locais no Layout Harmonizer
```

---

## 🎯 Resumo

Habilita a exportação local (F5) do Layout Harmonizer: o usuário baixa o quadro exibido como **instruções em Markdown para IA**, **variáveis CSS** ou **ZIP com os dois arquivos**, sem regenerar cores e sem chamada ao servidor. O objetivo é fechar o ciclo de uso — o sistema de cores deixa de ser apenas visual e passa a sair pronto para colar no projeto ou entregar a um agente de IA, mantendo as versões e os avisos do snapshot exibido.

## 🛠️ Mudanças Técnicas

- **Card de exportação** (`LayoutExportPanel.vue`) no editor e no tool, com seletor de idioma (5 locales), prefixo e escopo CSS, e botões de download; renderiza apenas quando o subtree de traduções chega do servidor.
- **Geradores no cliente** (`export/markdown.ts`, `export/css.ts`, `export/zip.ts`): consomem exclusivamente o snapshot já exibido, com formatos determinísticos (HEX/rgba + OKLCH em `@supports`, `mtime` e nível fixos no ZIP).
- **Validação de prefixo/escopo** (`export/options.ts`): identificador ASCII e `:root`/classe simples, sem injeção de CSS livre; limites vêm do servidor.
- **Traduções de exportação no payload**: novo `LayoutSystemExportMessages` envia apenas o subtree usado (`export`, tokens, estratégias, proveniência, famílias, view, contraste e resumo da seed) para os 5 idiomas, injetado em `toolProps` e no editor.
- **Handoff de login para exportação**: `LayoutSystemIntentController` passa a interpretar `export=1` (abre o tool com o card liberado, sem modal de salvar), preservando a paleta de origem quando acessível.
- **Configuração**: novas chaves `colors.layout_systems.export.prefix_max_length` e `scope_max_length`, expostas em `exportLimits`.
- **Cobertura de testes**: unitários de Markdown, CSS, ZIP, opções e mensagens; testes de componente do card; paridade de placeholders i18n; integração HTTP do intent e do payload.
- **Documentação**: ADRs 148/155/175/176/177, SPEC, parâmetros, glossário e planos de fase.

## ⚠️ Impacto/Avisos

- **Nova dependência**: `fflate` (~leve) adicionada ao `package.json` para geração do ZIP no navegador; requer `npm install`.
- **Novas variáveis de ambiente**: `COLORS_LAYOUT_SYSTEM_EXPORT_PREFIX_MAX_LENGTH` (padrão 32) e `COLORS_LAYOUT_SYSTEM_EXPORT_SCOPE_MAX_LENGTH` (padrão 64) — não obrigatórias, mas devem entrar no `.env.example`/deploy.
- **Payload maior do tool/editor**: `exportMessages` adiciona o subtree de 5 idiomas à resposta Inertia; o recorte foi deliberado para não crescer a cada nova chave de tela, mas convém monitorar o peso da página.
- **Sem mudanças de banco de dados**: nenhuma migration; o estado exportado é o mesmo já persistido.
- **Segurança**: nenhum valor de cor é recalculado no caminho de exportação e o texto livre (nome/origem) passa por escapes de Markdown e de comentário CSS — testes cobrem os casos.

close #172

---

[![GitPR](https://devtools.natanfiuza.dev.br/api/badges/static/GitPR/0_errors_%C2%B7_8_warnings.svg?color=yellow&logo=gitpr)](https://gitpr.natanfiuza.dev.br/)