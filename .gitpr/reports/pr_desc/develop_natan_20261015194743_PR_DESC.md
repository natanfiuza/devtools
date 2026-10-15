# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: estende medição consentida à área da conta
```

---

## 🎯 Resumo

A medição com consentimento (GA4) existia apenas nas páginas públicas, deixando a área da conta — onde o usuário passa a maior parte do tempo autenticado — fora de qualquer leitura de uso. Esta mudança estende a mesma política de medição (ADR-032) para as telas de conta, mantendo o painel administrativo deliberadamente fora do escopo.

Além disso, reforça a promessa feita ao usuário no banner ("Nada de anúncios") e na Política de Privacidade: os sinais de publicidade agora vão desligados na própria configuração do GA, e não apenas por dependência do estado de consentimento. Os textos legais e o aviso de medição nas 5 localidades foram atualizados para refletir exatamente esse comportamento — sem prometer mais do que o código entrega.

## 🛠️ Mudanças Técnicas

- `resources/js/lib/analytics.ts`: adiciona a constante `NO_ADS` (`allow_google_signals` e `allow_ad_personalization_signals` como `false`) e a aplica no `gtag('config', id, NO_ADS)`, garantindo que os sinais de publicidade fiquem desativados independentemente do consentimento.
- `resources/js/layouts/account/AccountLayout.vue`: monta `ConsentBanner` e `AnalyticsScript` no layout da conta, reaproveitando a decisão de consentimento vinda do servidor via props compartilhadas do Inertia.
- `resources/js/__tests__/consent-layouts.test.ts` (novo): cobre o comportamento por layout — banner sem tag antes da decisão, injeção da tag após o aceite, ausência total no painel administrativo mesmo com `consent: granted` — com mocks de Inertia, i18n real e verificação direta das tags no `<head>`.
- `resources/js/__tests__/consent-banner.test.ts`: valida que o `config` enviado ao `dataLayer` carrega `allow_google_signals: false` e `allow_ad_personalization_signals: false`.
- `lang/{pt_BR,pt_PT,en,es,fr}/app.php`: ajusta `consent.analytics_body` para mencionar explicitamente cookies de medição e a dependência do aceite.
- `lang/{pt_BR,pt_PT,en,es,fr}/legal.php`: complementa a seção "Com quem compartilhamos" informando o repasse de páginas vistas ao Google Analytics quando houver aceite.
- `config/legal.php`: atualiza `LEGAL_UPDATED_AT` para `2026-10-15`, sinalizando a nova revisão dos textos legais.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migração ou alteração de schema.
- **Variáveis de ambiente:** nenhuma variável nova. O default de `LEGAL_UPDATED_AT` foi alterado para `2026-10-15`; ambientes que sobrescrevem essa env continuarão exibindo o valor configurado.
- **Dependências:** nenhuma adição ou atualização de pacotes.
- **Privacidade/Compliance:** a área da conta passa a ser medida sob as mesmas regras do site — somente após aceite e sem cookies de publicidade. O painel administrativo permanece sem banner e sem tag, inclusive com aceite prévio.
- **Rollback:** reversível apenas com código; como os textos legais são versionados por `LEGAL_UPDATED_AT`, um rollback deve considerar também a data de revisão exibida ao usuário.

> Observação: as alterações de documentação (ADRs, SPEC, RUNBOOK, guias e relatórios) acompanham este PR fora do diff apresentado.

close #18

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)