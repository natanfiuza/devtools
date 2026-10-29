# Relatório — Home em branco em produção: compilação do vue-i18n em runtime

- **Branch:** `develop_natan`
- **Data:** 2026-10-28
- **Origem:** sessão `/grill-with-docs` + plano aprovado em `docs/claude-code/plans/develop_natan/20261028_develop_natan_i18n_compilacao_mensagem_invalida.md`
- **Decisão:** ADR-060 (`docs/adr/ADR-060-i18n-compilacao-em-runtime-e-mensagem-invalida.md`)

## 1. Resumo

Em produção a Home renderizava o layout com o **corpo vazio** e o console repetia
`SyntaxError: 2` do `vue-i18n`. O `npm run build` passava, porque as traduções não
entram no bundle: chegam do PHP em runtime. A causa raiz era uma única chave —
`home.api.preview_code` — com um objeto JSON literal (`{ ... }`) nos cinco
idiomas; o `{` abria uma interpolação inválida, o compilador do vue-i18n lançava
de dentro de `t()` e a exceção abortava o `app.mount()`.

A entrega escapa os literais (`{'{'}` / `{'}'}`), adiciona um `messageCompiler`
tolerante que degrada mensagem inválida para texto cru e cria um teste de guarda
que compila **todas** as folhas dos cinco idiomas com o compilador estrito. O CSP
e o ruído do Google Ads não foram alterados: os hosts de anúncio são negados por
design (ADR-032) e o CSP apenas reflete isso (ADR-052).

## 2. Diagnóstico

### 2.1 Sintoma e evidência

`docs/fixtures/20261028_console.txt` mostrava, repetidamente:

```text
vue-i18n-CaoWPIKs.js:1 SyntaxError: 2
```

O `2` é `CompileErrorCodes.INVALID_TOKEN_IN_PLACEHOLDER`; o build de produção
imprime apenas o código. O layout (header/footer) aparecia porque o throw
acontecia durante o render da página, depois de o shell já estar montado.

### 2.2 Cadeia da causa raiz

1. `home.api.preview_code` (linha 33 de `lang/{en,es,fr,pt_BR,pt_PT}/home.php`)
   continha `-d '{\n  "schema_source": ...\n}'`.
2. O vue-i18n compila a mensagem e encontra `{` seguido de quebra de linha —
   placeholder inválido (código 2).
3. O `onError` de `@intlify/core-base` reporta o erro e **lança** `SyntaxError`
   incondicionalmente.
4. A exceção escapa de `t('home.api.preview_code')` em `Home.vue` e aborta o
   `app.mount()` → layout sem corpo.

### 2.3 Por que `npm run build` passou

As traduções são **dados de runtime**: `lang/{locale}/{namespace}.php` →
`HandleInertiaRequests::translations()` → prop do Inertia → `setLocaleMessage` →
compilação no navegador (e no SSR). O Vite não vê as mensagens; nenhum passo do
build as compila.

### 2.4 Ruído de CSP

Os avisos de `Content-Security-Policy` para hosts do Google Ads são o CSP
bloqueando o que `resources/js/lib/analytics.ts` já nega por padrão
(`DENIED_BY_DEFAULT`, `NO_ADS` — ADR-032). Não têm relação com a Home em branco.
O código não foi alterado; a conversão `AW-998597367` deve ser desvinculada no
painel do Google pelo usuário.

### 2.5 Auditoria

7.930 folhas expostas (16 namespaces × 5 idiomas) compiladas com o compilador
estrito: **exatamente 5 falhas**, todas `*/home.api.preview_code`. Paridade de
chaves entre idiomas correta.

## 3. O que foi implementado

### 3.1 Traduções

- `lang/{en,es,fr,pt_BR,pt_PT}/home.php`: apenas os literais do JSON do exemplo
  cURL passaram a `{'{'}` e `{'}'}`. `\n`, `\"`, o rótulo do token
  (`SEU_TOKEN`, `TU_TOKEN`, `VOTRE_JETON`, `YOUR_TOKEN`) e as `description`
  traduzidas ficaram intactos.
- Validado que a mensagem escapada renderiza **byte a byte** o mesmo texto que o
  original pretendia exibir.

### 3.2 `resources/js/lib/i18n.ts`

- `safeMessageCompiler: MessageCompiler` chama `compile(message, context)` do
  `@intlify/core-base` em `try/catch`.
- Em falha: devolve uma `MessageFunction` que exibe o texto cru; cache por
  `locale\0chave\0mensagem` (o core-base não cacheia mensagem com erro e o
  namespace é substituído inteiro a cada navegação); aviso **uma vez** por
  `locale:chave`, **sem o conteúdo da tradução**.
- `messageCompiler: safeMessageCompiler` em `createI18n(...)`. `app.ts` e
  `ssr.ts` herdam via `createI18nInstance()`.

### 3.3 Dependência

- `package.json`: `"@intlify/core-base": "^11.4.14"` em `dependencies`.
- `npm ls @intlify/core-base` → uma única cópia deduplicada, `11.4.14`.
- `package-lock.json` é ignorado pelo git neste repositório; o deploy resolve a
  dependência pelo `package.json`.

### 3.4 Testes

- Novo `resources/js/__tests__/i18n-messages.test.ts` (guarda): lê
  `config/locales.php` + `lang/` com `php -r`, cria `createI18n` **puro** (sem o
  compilador tolerante) por idioma e chama `t()` em todas as folhas; falha com
  `locale/namespace.chave: <erro>`.
- `resources/js/__tests__/i18n.test.ts`: mensagem inválida não lança e devolve
  texto cru; `{count}` continua interpolando.

## 4. Verificações

| Comando | Resultado |
| --- | --- |
| Guarda **antes** do fix | Vermelho: 5 falhas, todas `*/home.api.preview_code` |
| Guarda **depois** do fix | `1 file / 1 test` verde (~2 s) |
| `npx vitest run i18n.test.ts i18n-messages.test.ts` | `2 files / 17 tests` verdes |
| `npm run test` | `64 files / 819 tests` verdes (64,1 s) |
| `npm run types:check` | verde |
| `npm run lint:check` | 0 erros (1.173 avisos pré-existentes de `vue/html-indent`) |
| `npx prettier --check` nos 3 arquivos tocados | verde (repo tem 116 arquivos fora do padrão, pré-existentes) |
| `composer test` | Pint, PHPStan e Pest verdes (750 testes / 16.584 asserções) |
| `npm run build:ssr` | cliente + SSR verdes; `bootstrap/ssr/ssr.js` 517,38 kB |

### Render SSR nas cinco locales

Servidor SSR (`node bootstrap/ssr/ssr.js`, porta 13714) + `php artisan serve`
com `INERTIA_SSR_ENABLED=true`:

| Rota | HTTP | Corpo | Card da API |
| --- | --- | --- | --- |
| `/pt-br` | 200 | renderizado | `{`/`}` literais; `Listar usuários ativos...` |
| `/en` | 200 | renderizado | `{`/`}` literais; `List active users...` |
| `/es` | 200 | renderizado | `{`/`}` literais; `Listar usuarios activos...` |
| `/fr` | 200 | renderizado | `{`/`}` literais; `Lister les utilisateurs...` |
| `/pt-pt` | 200 | renderizado | `{`/`}` literais; `Listar utilizadores ativos...` |

Sem `falha ao compilar` no console do SSR e sem `SyntaxError: 2`.

## 5. Deploy

- Sem migration e sem downtime. O deploy detecta o `package.json` alterado,
  roda `npm install` e reconstrói cliente e SSR; o swap atômico de
  `public/build` e `bootstrap/ssr` vem do script instalado.
- Após o deploy, repetir a checagem da Home nos cinco idiomas.

## 6. Ação pendente do usuário

- Desvincular a conversão `AW-998597367` no painel do Google Analytics/Ads (o
  código não muda; o CSP continua bloqueando os hosts de anúncio).
- Revisar e commitar pelo gitpr (nenhum commit/push foi feito por esta sessão).

## 7. Referências

- ADR-060: `docs/adr/ADR-060-i18n-compilacao-em-runtime-e-mensagem-invalida.md`
- ADR-010 (i18n), ADR-014 (build no servidor), ADR-032 (analytics negado),
  ADR-052 (CSP)
- SPEC §3 (Idiomas) e §11.1; ARQUITETURA (linha de i18n); glossário
  ("Escape de sintaxe do vue-i18n", "Compilador de mensagens tolerante")
- Survey: `docs/survey/20261028_i18n_compilacao_mensagem_invalida_surveyfacts.md`
- Plano: `docs/plans/develop_natan/20261028_i18n_compilacao_mensagem_invalida_plansfacts.md`
