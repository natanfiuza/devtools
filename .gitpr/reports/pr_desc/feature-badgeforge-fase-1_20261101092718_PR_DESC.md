# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige campos numéricos e ordena logos no BadgeForge
```

---

## 🎯 Resumo

Corrige falhas de usabilidade que quebravam a geração de badges no BadgeForge. O formulário usava `<input type="number">` com `v-model`, que entrega `number` ao invés de `string`, fazendo a prévia e o snippet falharem ao digitar valores como `logoSize` e `cacheSeconds`. Como o problema era invisível na tipagem original (campos declarados como `string`), a validação passou a ser tolerante a ambos os tipos, com `NaN`/`Infinity` caindo no default — mesmo comportamento tolerante do servidor.

Também elimina a duplicação da introdução (a página já exibe o texto, o componente repetia), ordena a lista de logos alfabeticamente sem diferenciar maiúsculas para facilitar a busca visual, e ajusta o espaçamento (`gap`) dos estilos para melhorar a legibilidade dos badges gerados.

## 🛠️ Mudanças Técnicas

- `builder.ts`: `logoSize` e `cacheSeconds` passam a ser `string | number`; helper `integer()` normaliza `number` via `String()` antes de validar, preservando fallback para entradas inválidas, `NaN` e `Infinity`.
- `BadgeForgeToolProps.php`: novo método privado `logosByName()` ordena o payload por `name` (case-insensitive) usando `slug` como desempate, mantendo o catálogo lendo a config na ordem declarada.
- `Tool.vue`: remove o parágrafo de introdução duplicado no topo do componente.
- `styles.json`: `gap` de `2` para `3` nos cinco estilos disponíveis.
- Ampliação do fixture `tool-props.json` com novos logos (Pest, PHPUnit, GitPR, Markdown, etc.) já em ordem alfabética.
- Novos testes: unitário PHP para ordenação de logos; Vitest cobrindo aceitação de números crus, `NaN`/`Infinity`, campos numéricos sem quebrar a prévia e ausência da introdução duplicada.
- Novo asset binário `docs/assets/img/composer_ico`.

## ⚠️ Impacto/Avisos

- **Sem mudança de banco de dados, variáveis de ambiente ou dependências.**
- O contrato do payload de props muda na ordenação dos logos: consumidores que dependam da ordem original de `styles.json`/catálogo devem se ajustar.
- Testes de snapshot/fixtures que usem a ordem antiga dos logos precisam ser atualizados.
- Mudança visual no `gap` dos estilos altera a renderização dos badges já compartilhados quando re-renderizados.

close #121

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)