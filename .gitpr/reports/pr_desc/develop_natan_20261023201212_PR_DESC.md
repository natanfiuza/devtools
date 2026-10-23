# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: adiciona defaults resilientes nas props do base64-image
```

---

## 🎯 Resumo

A ferramenta **Base64 Image** quebrava (ou renderizava valores indefinidos) quando montada sem que todas as props de plano/limite fossem informadas pelo backend — cenário comum em visitantes não autenticados, previews e testes isolados de componente. Esta mudança torna o contrato de props tolerante a ausências, aplicando o perfil Free (2 MB / 24h) como fallback seguro, e adiciona cobertura de testes para os cenários Free, Pro e montagem sem props.

## 🛠️ Mudanças Técnicas

- `Tool.vue` passa a declarar as props de forma *flat* (`isAuthenticated`, `isPaidPlan`, `maxUploadBytes`, `downloadThresholdBytes`, `retentionHours`) com `withDefaults`, eliminando a obrigatoriedade do objeto `toolProps`.
- Adicionado `defineOptions({ inheritAttrs: false })` no componente raiz para evitar vazamento de atributos ao elemento raiz.
- `EncoderPanel.vue` e `HistoryPanel.vue` agora aceitam `toolProps?: Partial<Base64ImageToolProps>` com objeto default completo, e usam *optional chaining* + fallback (`?? 2 MB`, `?? 24h`) nos pontos de leitura.
- `Tool.vue` repassa `props` (já normalizadas) aos painéis filhos, garantindo consistência entre raiz e filhos.
- Removido import não utilizado (`FileText`) em `HistoryPanel.vue`.
- Novo teste `base64-image-component.test.ts` (Vitest + Vue Test Utils) cobrindo: perfil Free (badge Pro ausente, limite 2 MB), perfil Pro (badge e limite 25 MB) e resiliência ao montar sem props explícitas.

## ⚠️ Impacto/Avisos

- **Breaking change de contrato de props**: quem consumia `<Tool :tool-props="{...}" />` precisa migrar para props individuais (`:is-authenticated`, `:max-upload-bytes`, etc.). Verificar todos os pontos de montagem no Blade/Inertia.
- **Sem mudanças em banco de dados, migrations ou variáveis de ambiente.**
- **Sem novas dependências**; apenas uso de recursos já disponíveis (`withDefaults`, Vitest).
- Os valores default refletem o plano Free — se a regra de negócio de limites mudar, os defaults precisam ser atualizados em `Tool.vue`, `EncoderPanel.vue` e `HistoryPanel.vue` (duplicação intencional de fallback, candidata a constante compartilhada em refactor futuro).

close #78

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)