# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige interpolacao da data na janela de cota do dashboard
```

---

## 🎯 Resumo

O dashboard de conta exibia o texto cru `:date` (ou `até :date`) no card de cota, pois as traduções usavam o padrão de placeholder do Laravel (`:date`) enquanto o front-end Vue interpola com chaves (`{date}`). Esta correção alinha os arquivos de idioma ao formato consumido pela interface e adiciona testes que travam o comportamento, garantindo que o usuário veja a data real de vencimento da cota ou o selo "Vitalício" quando não houver janela definida.

## 🛠️ Mudanças Técnicas

- Atualiza `quota_until` de `:date` para `{date}` em todos os locales suportados: `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Adiciona suíte de testes `resources/js/__tests__/dashboard-overview.test.ts` cobrindo o componente `Dashboard.vue` com Vitest + Vue Test Utils.
- Mocka `@inertiajs/vue3` (`Head`, `Link`, `usePage`) para permitir a montagem isolada do componente.
- Cobre três cenários da janela de cota: data interpolada corretamente, ausência de janela (exibe `Vitalício`) e cota esgotada (mensagem de aviso com classe `text-destructive`).
- Valida explicitamente que o texto renderizado não contém mais o token `:date`.

## ⚠️ Impacto/Avisos

- **Sem impacto em banco de dados ou envs**: alteração restrita a arquivos de tradução e testes.
- **Sem novas dependências**: utiliza `vitest`, `@vue/test-utils` e utilitários de i18n já existentes no projeto.
- **Contrato de placeholder**: a partir de agora as chaves de tradução de cota devem usar `{date}`; textos com `:date` voltarão a quebrar a renderização no front-end.
- **Seletores de teste**: os testes dependem de `data-test="conta-janela"` no `Dashboard.vue`; renomear esse atributo exige atualização da suíte.
- **Cobertura parcial de locale**: os testes exercitam apenas `pt_BR`; os demais idiomas foram ajustados mas não têm asserção dedicada.

close #52

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)