# Plano — BadgeForge: correções de UI e auditoria das fases

- **Branch:** `feature/badgeforge-fase-1`
- **Data:** 2026-11-01
- **Plano técnico completo:**
  `docs/claude-code/plans/feature_badgeforge-fase-1/20261101_feature_badgeforge-fase-1_badgeforge_ui_fixes.md`

## Escopo

1. Remover a introdução duplicada do `Tool.vue`.
2. Impedir que os campos numéricos derrubem o formulário.
3. Ordenar o select de logo alfabeticamente pelo nome.
4. Aumentar para 3px o espaço entre logo e texto.
5. Auditar as fases F1–F8 do `docs/SPEC-BADGEFORGE.md`.

## Decisões da sessão de grill

- **Gap**: `gap = 3` nos cinco estilos de `resources/badgeforge/styles.json`,
  mantendo a centralização por `intdiv`; regenerar só as 7 fixtures com logo
  (o vão visível fica 2,5–3,5px conforme a paridade da largura do texto).
- **Ordenação**: no servidor (`BadgeForgeToolProps::for()`), `strcasecmp` pelo
  nome e desempate por slug; fixture reordenado.
- **Números**: `integer()` e `BadgeFormState.logoSize/cacheSeconds` aceitam
  `string | number`; `NaN`/`Infinity`/inválido caem no default.
- **Introdução**: sai do `Tool.vue`; a chave `tools.badgeforge.intro` continua
  usada pelo cabeçalho genérico de `Show.vue`.
- **Fases**: apenas relatar o status; F2+ fora de escopo.

## Execução

1. `Tool.vue`: remover o `<p>` da intro.
2. `builder.ts`: ampliar tipos e normalizar número em `integer()`.
3. `BadgeForgeToolProps.php`: método `logosByName()` com `usort`.
4. `tool-props.json`: logos em ordem alfabética.
5. `styles.json`: `gap` 2 → 3.
6. Script temporário em `storage/framework/testing/` para regravar as 7
   fixtures com logo; apagar depois.
7. Testes: componente (intro ausente, campos numéricos), builder (números,
   `NaN`, `Infinity`), Pest (ordem dos logos).
8. Plano, survey, relatório; gates completos; sem commit/push.
