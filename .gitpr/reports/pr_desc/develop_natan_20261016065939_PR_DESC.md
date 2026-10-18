# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
fix: enfileira comandos do gtag como arguments para o gtag.js
```

---

## 🎯 Resumo

Restaura o envio de eventos para o GA4, que estava silenciosamente quebrado desde 2026-10-16. O gtag.js só despacha itens da fila (`dataLayer`) que sejam objetos `arguments` — o formato que o snippet oficial do Google produz (`dataLayer.push(arguments)`). O código enfileirava Arrays comuns, que caem no ramo de "campos de modelo" do gtag.js e são ignorados sem erro no console: a tag era detectada e a fila parecia correta, mas nenhum hit chegava ao GA4.

A correção troca o `push(command)` por `push(arguments)` e ajusta a tipagem da fila para refletir o contrato real do gtag.js, de modo que um Array volte a ser erro de tipo — barrando a regressão ainda em tempo de compilação, não só em teste.

## 🛠️ Mudanças Técnicas

- `resources/js/lib/analytics.ts`
    - Adiciona o tipo `GtagQueueItem = IArguments | Record<string, unknown>`, cobrindo tanto comandos (`arguments`) quanto eventos simples que o próprio gtag.js enfileira (ex.: `gtm.dom`).
    - Altera `window.dataLayer` de `unknown[][]` para `GtagQueueItem[]`.
    - `gtag()` passa a enfileirar o objeto `arguments` em vez do rest parameter `command`, com `eslint-disable-next-line prefer-rest-params` justificado em comentário (o rest seria exatamente o Array que o gtag.js ignora).
    - Documenta a causa raiz e o sintoma (falha silenciosa, sem erro no console e sem hits) em docblock.
- `resources/js/__tests__/consent-banner.test.ts`
    - Adiciona os helpers `comando(indice)` e `comandos()`, que normalizam itens `arguments` em listas para comparação, substituindo os acessos diretos a `window.dataLayer[i]`.
    - Novo teste que executa o ciclo completo de consentimento (aceite, reabertura e recusa) e verifica que **todos** os itens da fila são `[object Arguments]` e não Arrays — a asserção que falharia no defeito original.

## ⚠️ Impacto/Avisos

- **Sem mudanças de banco de dados, variáveis de ambiente ou dependências.**
- **Impacto em negócio:** a medição de consentimento no GA4 volta a funcionar. Como a falha era silenciosa, os dados coletados entre 2026-10-16 e este fix estão subestimados e não são recuperáveis retroativamente.
- **Compatibilidade:** a assinatura pública de `gtag()` não muda; o comportamento observável externo passa a ser o correto. Nenhum consumidor existente precisa de ajuste.
- **Regressão:** coberta em dois níveis — o tipo `GtagQueueItem` rejeita Arrays em tempo de compilação e o novo teste valida o formato real dos itens em runtime.
- **Atenção em revisão:** o uso de `arguments` é intencional e não deve ser "modernizado" para rest parameters sem revalidar o comportamento do gtag.js.

close #21

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)
