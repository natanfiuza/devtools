# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: conclui auditoria da fase 8 do Colors com anonimização LGPD
```

---

## 🎯 Resumo

Fecha a Fase 8 do módulo Colors entregando as pendências de conformidade (LGPD), corrigindo um bug real de i18n nas notificações e documentando a configuração do módulo, além de normalizar a base de código com Prettier. O objetivo é deixar o Colors pronto para produção: dados pessoais exportáveis e anonimizáveis, placeholders de tradução renderizando corretamente nos cinco idiomas e diff hygiene para as próximas fases.

## 🛠️ Mudanças Técnicas

- `AnonymizeUser` passa a apagar dados pessoais do Colors (histórico, favoritos, presets, curtidas, follows, tags) e a desvincular autoria de comentários/denúncias, preservando o conteúdo publicado.
- `ExportUserData` inclui o bloco `colors` (paletas, tags, histórico, favoritos, presets, likes, follows, comentários — inclusive excluídos —, denúncias, preferências de notificação e paletas silenciadas), sem vazar `moderation_reason` (ADR-028/ADR-144).
- Corrige as notificações: chaves migradas de `:actor`/`:palette` (interpolação Laravel) para `{actor}`/`{palette}` (vue-i18n) nos cinco locales, com teste de contrato que varre todos os arquivos de tradução.
- `.env.example` documenta as novas variáveis do Colors (cotas Free/Pago, limites de nome/descrição, paginação, throttle por conta, importação de tokens, OG image, biblioteca anônima).
- Testes: `AccountPanelTest` cobre export completo de Colors e cenário vazio; `PurgeDeletedUsersTest` valida o export antes da anonimização; `ProfileUpdateTest` valida a política de anonimização; novo `admin-comment-reports.test.ts`; cobertura extra em `palette-generator.test.ts` para o locale `pt_BR` (underscore vs BCP-47).
- `ExtractionPanel.vue` passa a usar o `formatBytes` compartilhado de `@/lib/bytes` via `formatLimit(bytes)`, unificando o formato do limite de upload exibido ao usuário.
- `admin/CommentReports.vue` recebe `data-test="comment-report-resolve"` e `data-test="comment-report-dismiss"` para seletores estáveis em testes E2E.
- Reformatação Prettier/ESLint em massa: componentes Vue (layouts, auth, admin, tools, BadgeForge, palette-generator), módulos TypeScript, fixtures JSON do BadgeForge e workflow de CI — sem mudança de comportamento.
- `font-metrics.json` adicionado ao `.prettierignore`.

## ⚠️ Impacto/Avisos

- **Privacidade/LGPD:** a anonimização agora remove dados de biblioteca e relações de saída do Colors; conteúdo publicado permanece sem autoria (`user_id` nulo). Curtidas anônimas (`anonymous_id`), seguidores de terceiros e o motivo de denúncias são preservados.
- **i18n:** os cinco locales mudam o formato dos placeholders; chaves novas devem usar `{...}` e nunca `:...`.
- **Env:** dezenas de novas variáveis em `.env.example` — revisar `config/colors.php` e atualizar os ambientes antes do deploy (cotas, limites, throttle, OG image).
- **Banco de dados:** nenhuma migration nova.
- **Dependências:** nenhuma adição ou remoção.
- **Diff volumoso:** a maior parte das linhas é reformatação puramente estética (risco funcional nulo); a revisão deve focar nas Actions, testes, arquivos de locale e `.env.example`.

close #162

---

[![GitPR](https://img.shields.io/badge/GitPR-2_errors_%C2%B7_5_warnings-red)](https://gitpr.natanfiuza.dev.br/)