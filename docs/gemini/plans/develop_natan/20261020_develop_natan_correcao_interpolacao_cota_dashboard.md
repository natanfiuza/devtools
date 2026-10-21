# Plano de Implementacao — Correção da Interpolação de Data na Cota do Dashboard

- **Data:** 2026-10-20
- **Branch:** `develop_natan`
- **Contexto:** Na tela de Visão Geral do painel de usuário (`/dashboard`), o card de cota renderizava o texto "até :date" sem substituir o placeholder, porque a chave de tradução `account.overview.quota_until` usava a sintaxe do Laravel (`:date`) em vez da sintaxe do vue-i18n (`{date}`).

---

## 1. Objetivos

1. Atualizar a chave `quota_until` nos 5 arquivos de idioma (`lang/{en,es,fr,pt_BR,pt_PT}/account.php`) para utilizar a sintaxe de interpolação `{date}`.
2. Criar uma suite de testes unitários no Vitest em `resources/js/__tests__/dashboard-overview.test.ts` cobrindo a renderização do componente `Dashboard.vue`, garantindo que:
   - A data seja interpolada corretamente em `data-test="conta-janela"` quando houver `windowEnd`.
   - O texto "Vitalício" (`quota_lifetime`) seja exibido quando `windowStart === null`.
   - O aviso de franquia esgotada (`quota_exhausted`) seja exibido quando `quota.exhausted === true`.
3. Validar a integridade de testes e tipagem com os comandos `npm run test`, `npm run types:check`, `composer test`.
4. Gerar o relatório final conforme exigido pelo `GEMINI.md`.

---

## 2. Etapas Detalhadas

### Etapa 1: Atualização dos arquivos de idioma
Alterar a chave `'quota_until'` nos 5 arquivos:
- `lang/en/account.php`: de `'until :date'` para `'until {date}'`
- `lang/es/account.php`: de `'hasta :date'` para `'hasta {date}'`
- `lang/fr/account.php`: de `'jusqu’au :date'` para `'jusqu’au {date}'`
- `lang/pt_BR/account.php`: de `'até :date'` para `'até {date}'`
- `lang/pt_PT/account.php`: de `'até :date'` para `'até {date}'`

### Etapa 2: Criação de testes unitários de frontend (Vitest)
Criar `resources/js/__tests__/dashboard-overview.test.ts` usando o utilitário de teste do vue-i18n e montagem do Vue Test Utils:
- Testar interpolação de `{date}` com valor real (ex: `"20/11/2026"`).
- Testar caso vitalício (`windowStart: null`).
- Testar caso esgotado (`exhausted: true`).

### Etapa 3: Execução de testes e verificação
- Executar `npm run test` para validar os testes do frontend.
- Executar `npm run types:check` para validar os tipos do TypeScript / Vue.
- Executar `composer test` para validar o Pint, PHPStan e Pest.

### Etapa 4: Relatório final
- Criar o relatório em `docs/gemini/reports/develop_natan/20261020_develop_natan_correcao_interpolacao_cota_dashboard.md`.
