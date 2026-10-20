# Plano: ADR da Implementacao do Google Analytics (GA4)

- **Data**: 2026-10-20
- **Branch**: `develop_natan`
- **Contexto**: Elaboracao do Architecture Decision Record (ADR-038) consolidando a arquitetura, desenho tecnico, solucoes de contorno de incidentes reais (contrato arguments vs array no dataLayer) e funcionamento ponta a ponta da medicao com Google Analytics (GA4) sob consentimento LGPD/GDPR no devtools.natanfiuza.dev.br.
- **Referencias**: `docs/SPEC.md`, `docs/RUNBOOK.md`, `docs/guides/google-analytics.md`, `docs/adr/ADR-013-consentimento.md`, `docs/adr/ADR-032-medicao-com-cookies.md`, `docs/claude-code/reports/develop_natan/20261015_develop_natan_ativacao_ga4.md`.

---

## 1. Motivacao e Diagnostico

A medicao de audiencia no projeto devtools.natanfiuza.dev.br foi construida e refinada atraves de tres momentos arquiteturais cruciais:
1. **Fase 1 (ADR-013)**: Definicao do banner de consentimento de duas categorias, resolucao do cookie no servidor para eliminar piscar de interface (zero-flash), e principio inegociavel de zero requisicoes ao Google antes do aceite explicito do usuario.
2. **Emenda Factual (ADR-032)**: Substituicao da declaracao antiga ("sem dado pessoal") pela afirmacao factual de uso de cookies de medicao padrao do GA4 (`_ga`), inclusao da area da conta autenticada (`AccountLayout`), desativacao de sinais de anuncio (`NO_ADS`) no codigo e mencao ao Google como medidor na Politica de Privacidade.
3. **Incidente de 2026-10-16 e Contrato da Fila**: Descoberta e correcao da falha silenciosa de envio de hits no GA4, provocada pela passagem de arrays no lugar de objetos `arguments` na fila `window.dataLayer`, adicionando protecao de tipos TypeScript estrita e testes automatizados de regressao.

Embora o guia operacional (`docs/guides/google-analytics.md`) e os ADRs 013 e 032 cubram partes do consentimento e da declaracao legal, faltava um ADR especifico e unificado documentando a **arquitetura tecnica e funcionamento da implementacao do Google Analytics**, seus requisitos de integracao SPA (Inertia + Vue 3), o ciclo de vida reativo e as decisoes de isolamento.

---

## 2. Escopo do ADR-038

O novo registro sera criado como `docs/adr/ADR-038-implementacao-google-analytics-ga4.md`, estruturado em:

1. **Contexto e Problema**:
   - Requisitos legais (LGPD/GDPR) e a proibicao de disparar chamadas externas antes do opt-in.
   - Incompatibilidade do snippet HTML padrao do Google (`app.blade.php` ou SSR) com SPA e SSR.
   - Problema da navegacao sem recarregamento de pagina (Inertia SPA).
   - O risco de rejeicao silenciosa de comandos pelo motor do `gtag.js`.

2. **Decisao Arquitetural**:
   - **Resolucao no servidor (State Resolution)**:
     - `config/analytics.php`, `App\Support\Analytics` e `App\Support\Consent`.
     - Cookie `consent` (180 dias) com tres estados (`granted`, `denied`, `null`).
     - Middleware `HandleConsent` (higienizacao de cookies corrompidos).
     - Compartilhamento de props no `HandleInertiaRequests`.
   - **Zero Tag no Servidor / SSR**:
     - Blade e SSR limpos de tags externas.
   - **Ciclo de Vida no Cliente (`AnalyticsScript.vue` + `analytics.ts`)**:
     - Componente invisivel sem marcacao HTML.
     - Ativacao apenas no navegador (`onMounted` e `watch(consent)`).
   - **Consent Mode v2 e Blindagem de Anuncios**:
     - `DENIED_BY_DEFAULT` antes de qualquer comando.
     - `NO_ADS`: `allow_google_signals: false` e `allow_ad_personalization_signals: false` no `gtag('config')`.
   - **O Contrato `arguments` na Fila `dataLayer`**:
     - Detalhamento tecnico do despachante interno do `gtag.js` (`Qb(a)` / `arguments` vs Array).
     - Tipagem estrita: `(IArguments | Record<string, unknown>)[]`.
     - Justificativa do `/* eslint-disable prefer-rest-params */`.
   - **Idempotencia e Revogacao Dinamica**:
     - Marcador `data-analytics` no elemento `<script>`.
     - Revogacao via `gtag('consent', 'update', { analytics_storage: 'denied' })` sem remocao inutil do script.
   - **Fronteiras de Layout**:
     - Inclusao: `PublicLayout`, `AuthLayout`, `AccountLayout`.
     - Exclusao estrita: `AdminLayout`.
   - **Isolamento de Ambientes**:
     - `ANALYTICS_GA4_ID` no `.env` exclusivo de producao.
   - **Configuracao do Enhanced Measurement na Console**:
     - Apenas "Page changes based on browser history events" ativo; os outros 5 desligados.

3. **Consequencias e Decisoes Alternativas Descartadas**:
   - Insercao no Blade/HTML (descartado por violar LGPD e poluir SSR).
   - Despacho com rest parameters / Arrays (descartado por causar 0 hits silenciosos).
   - Carregamento incondicional com Consent Mode denied (descartado por gerar requisicoes nao consentidas).
   - Remocao do elemento `<script>` na recusa posterior (descartado por ineficacia no runtime do browser).

---

## 3. Plano de Acao

1. **Criacao do ADR-038**:
   - Criar `docs/adr/ADR-038-implementacao-google-analytics-ga4.md` em portugues sem acentos.
2. **Atualizacao de Documentacao Cruzada**:
   - Atualizar `docs/glossario.md` se necessario ou referenciar o termo `Medicao`.
   - Referenciar ADR-038 em `docs/guides/google-analytics.md` e `docs/RUNBOOK.md` na secao de medicao.
3. **Verificacao e Testes**:
   - Executar `composer test` (Pint, PHPStan, Pest).
   - Executar `npm run test` (Vitest).
   - Executar `npm run types:check` (vue-tsc).
4. **Relatorio Final**:
   - Gerar `docs/gemini/reports/develop_natan/20261020_develop_natan_adr_google_analytics.md`.
