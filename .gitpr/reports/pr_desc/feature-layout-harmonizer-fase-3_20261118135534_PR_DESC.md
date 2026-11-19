# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona edição, bloqueios e sugestões ao layout harmonizer
```

---

## 🎯 Resumo

Entrega a Fase 3 do Layout Harmonizer: o quadro gerado deixa de ser somente leitura e passa a aceitar ajustes manuais controlados. O usuário pode editar um token, congelá-lo com bloqueio, definir uma cor secundária manual e aceitar sugestões de contraste — sempre com ação explícita e sem alterar o tema do site.

O motor continua determinístico: overrides entram depois da derivação e antes da medição de contraste, de modo que os checks reflitam exatamente o que a tela mostra. Sem edições, o resultado permanece idêntico ao da F2.

## 🛠️ Mudanças Técnicas

- **Motor**: novo parâmetro `overrides` por tema/token aplicado após a derivação e antes do contraste; precedência `override > secundária manual > derivação`, sem recalcular dependentes e sem tocar o outro tema.
- **Estado de edição puro** (`lib/editState.ts`): overrides com origem (`edit`, `accept`, `lock`), bloqueios por token/tema, aceites registrados, descarte apenas do que não está bloqueado, restauração por token/tema/sistema e assinatura de entradas (seed efetiva + estratégia + secundária).
- **Sugestões de contraste** (`lib/suggestions.ts`): menor variação de luminosidade em OKLCH que atinge a meta AA/AAA contra **todos** os fundos do token, preservando matiz e croma; casos sem solução são marcados explicitamente como inviáveis, sem proposta.
- **Snapshot** (`lib/snapshot.ts`): congela tokens, checks, overrides, bloqueios, aceites e diagnósticos AA com versionamento de snapshot, schema, algoritmo e sugestões; cópia profunda sem regenerar o quadro.
- **Composable** `useLayoutSystem`: regeneração única disparada por assinatura, descarte de overrides não bloqueados com aviso dedicado (`edit_notice.discarded`), separação entre aviso de edição e estado obsoleto e bloqueio de edição quando há entrada inválida.
- **UI**: novos componentes `SecondaryField`, `RestoreControls` (com diálogo de confirmação por escopo), `SuggestionsPanel`, `TokenEditorPopover`; `TokenCell` ganha bloqueio, editor em popover e selo de sugestão, com repasse de props por `TokenGroup`, `ThemeBoard` e `ThemeComparison`.
- **Base**: wrappers de `Popover` (reka-ui) e `ColorInputField` com `id`/`testId` configuráveis para permitir múltiplos campos na mesma página sem ids duplicados.
- **i18n**: novas chaves em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- **Testes**: seis novas suítes (edits, overrides, snapshot, suggestions, suggestions-panel, token-editor) e ampliação de `layout-harmonizer-system` e `layout-harmonizer-workspace`.

## ⚠️ Impacto/Avisos

- **Sem migração de banco, sem novas variáveis de ambiente e sem novas dependências de runtime** — os wrappers de popover reutilizam `reka-ui`, já presente no projeto.
- **Mudança de comportamento**: valores manuais e aceites **sem bloqueio** são descartados quando seed, estratégia ou secundária mudam; valores bloqueados persistem (ADR-151). O descarte é comunicado por aviso próprio, sem marcar o quadro como obsoleto.
- **Tokens são opacos**: o alpha escolhido no editor é ignorado de propósito, com aviso na interface.
- **Nada é aplicado automaticamente**: sugestões exigem aceite explícito, e nenhuma edição é persistida (sem storage, servidor ou alteração do tema do site).
- **Compatibilidade**: `algorithmVersion` e `schemaVersion` permanecem em 1; com `overrides` ausente o resultado é idêntico ao da F2.
- **Documentação**: `SPEC.md`, ADR-151/158/167, `docs/colors` e glossário atualizados para refletir as novas regras de bloqueio, contraste e estado efetivo.

close #168

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_70_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)