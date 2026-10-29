# Survey — Home em branco: compilacao do vue-i18n em runtime (2026-10-28)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa

- **Pedido original:** "Ocorreu um erro e a tela home nao esta sendo exibida em producao, o console esta retornando: docs\fixtures\20261028_console.txt. eu rodei npm run build em producao e nao retornou nenhum erro."
- **Sintoma:** Home renderiza o layout (header/footer) com **corpo vazio**; console repetindo `SyntaxError: 2` do `vue-i18n`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 3 rodadas (3 + 4 + 1 decisoes), investigacao autonoma do erro no codigo e nas dependencias.
- **Arquivos-chave lidos:**
  - `docs/fixtures/20261028_console.txt`
  - `node_modules/@intlify/message-compiler/dist/message-compiler.cjs`
  - `node_modules/@intlify/core-base/dist/core-base.cjs`
  - `resources/js/lib/i18n.ts`, `resources/js/app.ts`, `resources/js/ssr.ts`
  - `resources/js/pages/Home.vue`
  - `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Middleware/SecurityHeaders.php`
  - `resources/js/lib/analytics.ts`
  - `lang/{en,es,fr,pt_BR,pt_PT}/home.php`
  - `config/locales.php`
  - `docs/adr/ADR-010`, `ADR-014`, `ADR-032`, `ADR-052`

## 2. Decisoes das rodadas

### Rodada 1 — Sintoma, reproducao e escopo
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | O que aparece na tela | **Layout renderiza, corpo vazio** — confirma a hipotese de excecao durante o render raiz (nao erro de rede/404). |
| Q2 | Reproducao local | **Ainda nao testada localmente** — a investigacao partiu do console e do codigo. |
| Q3 | Escopo da entrega | **Corrigir a mensagem i18n + teste de guarda dos locales + auditar todos os locales**. CSP do Google Ads ficou como item separado. |
| Q4 | Entrega | **Direto em `develop_natan`**, sem commit/push (usuario aprova pelo gitpr). |

### Rodada 2 — Forma da correcao
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Correcao da mensagem | **Escapar chaves literais** com a interpoliacao literal do vue-i18n (`{'{'}`/`{'}'}`), mantendo o texto renderizado identico. |
| Q2 | Rede de seguranca em runtime | **Sim, degradar para texto cru** com `messageCompiler` tolerante, em vez de derrubar a pagina. |
| Q3 | Teste de guarda | **Vitest com o `t()` real** e o compilador estrito, lendo `lang/` pelo PHP. |
| Q4 | CSP do Google Ads | **Liberar hosts do Ads** — hipotese inicial para o ruido de CSP. |

### Rodada 3 — CSP
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | CSP x privacidade | **Manter o CSP e desvincular a conversao `AW-998597367`** no painel do Google Analytics/Ads (acao do usuario, sem deploy). O CSP nao muda no codigo. |

### Defaults assumidos
- CSP/`SecurityHeaders` fora do escopo de codigo (ADR-032: anuncios negados por design).
- Nenhum conteudo de traducao/schema em log.
- Documentacao obrigatoria: ADR-060, SPEC secao 3, ARQUITETURA, glossario, survey/plano/relatorio.

## 3. Relatorio de fatos levantados

### Causa raiz
- `home.api.preview_code` (linha 33 dos 5 `lang/*/home.php`) continha um exemplo de cURL com objeto JSON literal (`{ ... }`).
- O vue-i18n interpreta `{` como inicio de interpolacao; a quebra de linha logo depois gera `CompileErrorCodes.INVALID_TOKEN_IN_PLACEHOLDER` (codigo **2**).
- O `getCompileContext().onError` do `@intlify/core-base` reporta o erro e **lanca** `SyntaxError` incondicionalmente; a excecao escapa de `t()` e aborta `app.mount()`.
- A chave e renderizada em `resources/js/pages/Home.vue` (`t('home.api.preview_code')`).

### Por que o build passou
- As traducoes nao entram no bundle: o PHP le `lang/{locale}/*.php`, `HandleInertiaRequests` envia os namespaces do locale ativo como prop do Inertia e o front aplica com `setLocaleMessage`.
- A compilacao acontece no navegador (e no SSR) em runtime; `vite build` e `vite build --ssr` nao veem as mensagens.

### Auditoria
- **7.930** folhas expostas (16 namespaces x 5 idiomas) compiladas com o compilador estrito: **5 falhas**, todas `*/home.api.preview_code`.
- Paridade de chaves entre idiomas correta; nenhum outro `{`, `}` ou `|` cru relevante.
- CSP: hosts de Ads negados por `DENIED_BY_DEFAULT`/`NO_ADS` em `resources/js/lib/analytics.ts` (ADR-032) — o CSP apenas reflete a negacao.

### Verificacao manual (SSR)
- `npm run build:ssr` (cliente + SSR) verde.
- Servidor SSR (`node bootstrap/ssr/ssr.js`) + `php artisan serve` com `INERTIA_SSR_ENABLED=true`.
- `/pt-br`, `/en`, `/es`, `/fr`, `/pt-pt` → 200; HTML SSR com o corpo da Home renderizado, card da API exibindo `{` e `}` literais (`-d '{ ... }'`) e a `description` traduzida em cada idioma; nenhum `falha ao compilar` no log.
