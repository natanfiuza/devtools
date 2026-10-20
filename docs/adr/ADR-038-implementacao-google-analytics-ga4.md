# ADR-038: Arquitetura e Implementacao da Medicao com Google Analytics (GA4) sob Consentimento

Status: Aceito
Data: 2026-10-20
Relacionados: ADR-013 (consentimento), ADR-032 (medicao com cookies)

## Contexto

A coleta de metricas de acesso e utilizacao do devtools.natanfiuza.dev.br (paginas mais visitadas, ferramentas mais utilizadas e comportamento de navegacao) e necessaria para direcionar o desenvolvimento do produto. Entretanto, a solucao de medicao precisa operar sob rigoroso cumprimento a legislacao de protecao de dados (LGPD e GDPR) e as particularidades da stack do projeto (Laravel, Inertia.js 3 com SSR, Vue 3 e TypeScript estrito).

O modelo tradicional de integracao promovido pelo Google consiste em copiar e colar um bloco estatico de script (`gtag.js`) diretamente no `<head>` do HTML principal (`resources/views/app.blade.php`). Esse modelo padrao e inaceitavel neste projeto por quatro razoes estruturais:

1. **Violacao do Principio de Zero Requisicoes Antes do Consentimento**: A simples presenca da tag `<script src="https://www.googletagmanager.com/gtag/js?id=G-...">` no HTML inicial forcaria o navegador do visitante a disparar uma requisicao HTTP aos servidores do Google antes mesmo da exibicao ou interacao com o banner de cookies. Mesmo o Consent Mode v2 operando em modo `denied` emite pings sem cookies aos servidores do Google, desrespeitando o compromisso de privacidade firmado no site.
2. **Poluicao do Server-Side Rendering (SSR) e HTML do Servidor**: Como a aplicacao utiliza pre-renderizacao SSR via Node.js (`bootstrap/ssr`), injetar scripts de terceiros no HTML base gerado pelo Laravel causaria poluicao estatica e potenciais erros de hidratacao no cliente, alem de violar a garantia de que o servidor nao expoe dependencias de rastreamento de terceiros.
3. **Navegacao em Single Page Application (SPA com Inertia.js)**: A navegacao entre ferramentas e telas autenticadas ocorre via requisicoes XHR manipulando `history.pushState`, sem recarregamento completo do documento HTML. O carregamento de analytics precisa ser compativel com o ciclo de vida reativo do Vue 3, persistindo o estado de consentimento sem disparar tags duplicadas.
4. **O Incidente do Contrato da Fila do `gtag.js` (2026-10-16)**: Durante a homologacao inicial com ID real de producao, observou-se que a tag era carregada normalmente no navegador, mas o Google Analytics registrava zero visitas (zero hits em `/g/collect`). A investigacao detalhada no codigo-fonte minificado da biblioteca oficial do Google (`gtag.js`) revelou que o despachante interno analisa estritamente os itens adicionados a `window.dataLayer`. O motor so despacha comandos quando o item e um objeto `arguments` nativo (`[object Arguments]`). Quando comandos sao empurrados como Arrays JavaScript comuns (`dataLayer.push([...])`), o motor os interpreta silenciosamente como atualizacoes de campos de modelo ("model fields") e descarta a execucao do comando sem emitir nenhum aviso ou erro no console.

## Decisao

Adotou-se uma arquitetura de medicao sob demanda 100% executada no cliente, com resolucao de estado no servidor, protecao de anuncios em camada dupla e tipagem estrita de comandos.

### 1. Resolucao do Estado de Consentimento no Servidor (Zero-Flash)
- A decisao do visitante vive no cookie `consent` (validade de 180 dias, configurada em `config/analytics.php` via `ANALYTICS_CONSENT_DAYS`).
- Os valores permitidos sao unicamente `granted` ou `denied` (`App\Support\Consent`).
- Caso o cookie nao exista, o estado e resolvido como nulo (`null`), disparando a exibicao do banner.
- Se o cookie contiver valor desconhecido, editado manualmente ou corrompido, `App\Support\Consent::isCorrupt()` detecta a anomalia, assume estado nulo por seguranca e o middleware `App\Http\Middleware\HandleConsent` remove o cookie corrompido atraves do cabecalho `Set-Cookie` de expiracao.
- O middleware `App\Http\Middleware\HandleInertiaRequests` compartilha o estado sob a prop global `analytics: { ga4Id, consent, cookie, days }`. Isso garante que paginas carregadas por usuarios que ja decidiram nunca apresentem o banner piscando na tela (zero layout shift / zero flash).

### 2. Zero Rastreamento no HTML do Servidor e no SSR
- O arquivo de visualizacao base (`resources/views/app.blade.php`) e o bundle SSR (`bootstrap/ssr`) nao contem nenhuma mencao ou script de `googletagmanager`, `dataLayer` ou `gtag`.
- O teste funcional `tests/Feature/ConsentTest.php` valida em pipeline automatizado que nenhum fragmento HTML do servidor emite marcadores de medicao, mesmo quando a requisicao possui o cookie de consentimento concedido.

### 3. Injecao Reativa no Cliente via Componente Invisivel (`AnalyticsScript.vue`)
- O componente `resources/js/components/AnalyticsScript.vue` nao possui marcacao no template.
- Ele executa exclusivamente no cliente durante o hook `onMounted` e escuta mudancas no consentimento atraves de `watch(consent)`. No SSR o hook `onMounted` nao executa, mantendo isolamento absoluto.
- A injecao so e solicitada se `consent === 'granted'` e se `analytics.ga4Id` for uma string nao-vazia.

### 4. Inicializacao com Consent Mode v2 em Estado Negado
- Antes de qualquer inclusao de script ou comando de medicao, a funcao `loadAnalytics(id)` em `resources/js/lib/analytics.ts` enfileira obrigatoriamente a diretiva de padrao negado:
  ```javascript
  gtag('consent', 'default', {
      ad_storage: 'denied',
      ad_user_data: 'denied',
      ad_personalization: 'denied',
      analytics_storage: 'denied',
  });
  ```
- Imediatamente em seguida, o consentimento do usuario libera apenas a medicao:
  ```javascript
  gtag('consent', 'update', { analytics_storage: 'granted' });
  ```
- As permissoes relacionadas a publicidade permanecem terminantemente em `denied`.

### 5. Blindagem Ativa contra Publicidade (`NO_ADS`)
- Para sustentar de forma factual a promessa do banner ("Nada de anuncios") e da Politica de Privacidade, os sinais de publicidade sao desligados no nivel da configuracao da propriedade, independentemente do Consent Mode (ADR-032):
  ```javascript
  gtag('config', id, {
      allow_google_signals: false,
      allow_ad_personalization_signals: false,
  });
  ```

### 6. O Contrato Obrigatorio da Fila `window.dataLayer`: `arguments`, Nunca Array
- Para garantir que o motor do `gtag.js` processe os comandos, o shim utilitario `gtag()` foi estruturado para empurrar unicamente o objeto nativo `arguments`:
  ```typescript
  type GtagQueueItem = IArguments | Record<string, unknown>;

  declare global {
      interface Window {
          dataLayer?: GtagQueueItem[];
      }
  }

  function gtag(...command: unknown[]): void;
  function gtag(): void {
      window.dataLayer = window.dataLayer ?? [];
      // eslint-disable-next-line prefer-rest-params
      window.dataLayer.push(arguments);
  }
  ```
- **Tipagem em Tempo de Compilacao**: A definicao de `GtagQueueItem` nao aceita Arrays. Qualquer tentativa de simplificar a funcao utilizando rest parameters (`...command` e `push(command)`) resulta em erro imediato do TypeScript (`TS2345`).
- **Validacao em Testes**: O teste unitario `consent-banner.test.ts` inspeciona cada elemento de `window.dataLayer`, afirmando que `Array.isArray(item)` e falso e que `Object.prototype.toString.call(item)` retorna `[object Arguments]`.

### 7. Idempotencia, Marcacao no DOM e Revogacao Dinamica
- O script `<script src="https://www.googletagmanager.com/gtag/js?id=G-...">` e injetado dinamicamente com o atributo identificador `data-analytics="G-XXXXXXXXXX"`.
- Se o visitante reabrir as configuracoes de cookies pelo link no rodape e clicar em "Aceitar" novamente, a funcao `loadAnalytics` detecta que a tag ja existe no DOM e apenas dispara um novo `gtag('consent', 'update', { analytics_storage: 'granted' })`, sem recriar elementos `<script>`.
- Caso o visitante decida revogar o consentimento ("Recusar"), a funcao `denyAnalytics()` nao remove o `<script>` (acao ineficaz, pois o codigo ja estaria em memoria na engine JavaScript), mas emite `gtag('consent', 'update', { analytics_storage: 'denied' })`, instruindo o motor do GA4 a interromper imediatamente qualquer despacho de eventos.

### 8. Delimitacao Rigida de Layouts
- **Areas Monitoradas**: `PublicLayout.vue` (paginas publicas e ferramentas), `AuthLayout.vue` (telas de autenticacao) e `AccountLayout.vue` (area autenticada do usuario, painel pessoal, faturas e configuracoes - ADR-032).
- **Area Excluida**: `AdminLayout.vue` (painel administrativo em `/admin`). O painel interno nao monta nem o `ConsentBanner` nem o `AnalyticsScript`. Nao ha medicao de operacoes administrativas, mantendo o tráfego do proprietario fora das metricas e protegendo a privacidade dos dados de gestao.

### 9. Isolamento por Ambiente
- O Measurement ID do GA4 e fornecido exclusivamente pela variavel de ambiente `ANALYTICS_GA4_ID`.
- Em ambiente de desenvolvimento local, testes automatizados e integracao continua (CI), a variavel permanece vazia.
- `App\Support\Analytics::ga4Id()` normaliza valores vazios ou espacos em branco para `null`. Com ID nulo, nenhuma tag e carregada, mesmo que o desenvolvedor clique em "Aceitar" no banner local.
- A medicao so e habilitada mediante configuracao manual no `.env` do VPS de producao.

### 10. Alinhamento com Enhanced Measurement na Console do GA4
- Na console do Google Analytics (Admin -> Data streams -> Enhanced measurement), o rastreamento aprimorado deve permanecer ativado **apenas com "Page views" ("Page changes based on browser history events") habilitado**.
- Os demais 5 interruptores (scrolls, cliques de saida, busca no site, engajamento com video e downloads) devem ser desativados na console. Isso garante a captura nativa de mudancas de rota do Inertia.js sem duplicacao manual de eventos e cumpre rigorosamente o texto do banner ("apenas paginas vistas e cookies de medicao").

## Consequencias

### Positivas
- **Conformidade Legal**: Zero requisicoes HTTP externas sao emitidas antes do opt-in explícito do visitante.
- **Transparencia Factual**: O banner declara o uso de cookies de medicao padrao (`_ga`), e os sinais de anuncio sao desligados programaticamente.
- **Robustez Tecnica**: A tipagem estrita de `window.dataLayer` e a presenca de testes de regressao impedem o retorno da falha de envio silencioso de hits.
- **Desempenho**: Sem scripts de terceiros bloqueando a renderizacao inicial ou atrasando o Time to First Byte (TTFB). O SSR permanece puro e veloz.
- **Experiencia do Usuario**: Ausencia de flickering do banner para visitantes recorrentes devido a resolucao do cookie no servidor.

### Negativas / Trade-offs
- **Complexidade de Depuracao em Testes Oficiais**: Ferramentas automatizadas como o Google Tag Assistant falham ao testar o site sem interacao humana, pois carregam a aplicacao em uma nova sessao limpa sem o cookie `consent` (e, em iframes, cookies `SameSite=Lax` nao sao trafegados). O procedimento de conferencia deve ser manual (observando o trafego no DevTools e na aba Realtime do GA4).
- **Manutencao de Excecao no ESLint**: Necessidade de manter a regra `eslint-disable prefer-rest-params` devidamente comentada no shim `gtag()`.

## Alternativas Descartadas

- **Inserir o script padrao do Google no `app.blade.php`**: Descartado por disparar requisicoes antes do consentimento do usuario, violando LGPD/GDPR e poluindo o SSR.
- **Disparar `dataLayer.push([...])` usando rest parameters**: Descartado porque a engine interna do `gtag.js` ignora arrays e nao despacha hits ao coletor do GA4.
- **Carregar a tag incondicionalmente com `Consent Mode v2` negado**: Descartado porque a tag ainda emite pings para o Google, contrariando a promessa de "zero chamadas sem aceite".
- **Remover a tag `<script>` do DOM ao recusar o banner**: Descartado porque remover o elemento do DOM nao descarrega o script ja interpretado na memoria do navegador. O mecanismo correto de cancelamento e o envio de `consent update { analytics_storage: 'denied' }`.
- **Utilizar ferramentas analiticas sem cookies (ex. Plausible, Umami)**: Descartado no ADR-032 para evitar custos de infraestrutura e perda de visibilidade na atribuicao de jornadas entre paginas do produto.
