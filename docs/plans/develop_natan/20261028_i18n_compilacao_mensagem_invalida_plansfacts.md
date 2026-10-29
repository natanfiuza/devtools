# Plano — Home em branco: compilacao do vue-i18n em runtime (2026-10-28)

> Plano de execucao da sessao de grill (`/grill-with-docs`), aprovado pelo usuario. Branch `develop_natan`.

## Objetivo

Restaurar o render da Home em producao corrigindo a causa raiz (`home.api.preview_code` com chaves JSON literais nos cinco idiomas), impedir recorrencia com um teste de guarda e impedir que uma traducao invalida derrube a pagina inteira.

## Fase 1 — Teste de guarda primeiro (vermelho)

- Novo `resources/js/__tests__/i18n-messages.test.ts`.
- Le `config/locales.php` e `lang/{locale}/{ns}.php` com `execFileSync('php', ['-r', ...])` (mesma leitura do `HandleInertiaRequests`), sem bootstrap do Laravel.
- Cria **`createI18n` puro** (sem o compilador tolerante), `fallbackLocale: false`, `warnHtmlMessage: false` e chama `t()` em todas as folhas.
- Asserts: locales `['en','es','fr','pt_BR','pt_PT']`, `total > 0`, `failures` vazio.
- Rodar e confirmar **vermelho**: 5 falhas, todas `*/home.api.preview_code`.

## Fase 2 — Escape das mensagens

- `lang/{en,es,fr,pt_BR,pt_PT}/home.php`, linha 33: `{` → `{'{'}` e `}` → `{'}'}`, apenas no JSON do exemplo cURL.
- Nao mexer em `\n`, `\"`, token (`SEU_TOKEN` etc.) nem nas `description` traduzidas.
- Validar que o valor renderizado e byte a byte igual ao original.
- Rodar o teste de guarda → verde.

## Fase 3 — Rede de seguranca em runtime

- `resources/js/lib/i18n.ts`: `safeMessageCompiler: MessageCompiler` que chama `compile()` do `@intlify/core-base` em `try/catch`.
- Em falha: funcao que devolve o texto cru, cache por `locale + chave + mensagem`, aviso uma vez por `locale:chave` **sem o conteudo da traducao**.
- Passar `messageCompiler` em `createI18n(...)`; `app.ts` e `ssr.ts` herdam.
- `package.json`: `"@intlify/core-base": "^11.4.14"` (alinhada ao `vue-i18n`); `npm install`; `npm ls @intlify/core-base` deve mostrar uma copia so.

## Fase 4 — Testes da rede de seguranca

- `resources/js/__tests__/i18n.test.ts`: mensagem invalida (cURL com `{...}` cru) → `t()` nao lanca e devolve o texto cru; mensagem valida com `{count}` continua interpolando.

## Fase 5 — Documentacao

- ADR-060 (compilacao em runtime, escape literal, compilador tolerante, guarda estrita, dependencia direta).
- `docs/SPEC.md` secao 3 e secao 11.1; `docs/ARQUITETURA.md` linha de i18n; `docs/glossario.md` (escape e compilador tolerante).
- Survey/planfacts da skill e relatorio obrigatorio em `docs/claude-code/reports/develop_natan/`.

## Verificacao

- Guarda vermelho → verde; `npm run test`; `npm run types:check`; `npm run lint:check`; `npx prettier --check` nos arquivos tocados; `composer test`.
- Render real: `npm run build:ssr`, SSR em `127.0.0.1:13714`, `INERTIA_SSR_ENABLED=true php artisan serve` → `/pt-br`, `/en`, `/es`, `/fr`, `/pt-pt` com corpo renderizado, chaves literais e `description` traduzida, sem `SyntaxError: 2`.
- Pos-deploy: mesma checagem em producao; o deploy roda `npm install` e rebuild cliente/SSR (hash do `package.json` mudou). Sem migration, sem downtime.

## Riscos

- `npm run test` passa a exigir PHP no PATH (teste de guarda).
- O compilador customizado e marcado como experimental pelo vue-i18n; revisar no upgrade de major.
- Mensagem invalida futura aparece crua na tela (aviso no console), nao derruba a pagina.

## Fora do escopo

- CSP/`SecurityHeaders`/Google Ads: sem alteracao de codigo; a conversao `AW-998597367` e desvinculada no painel do Google pelo usuario.
- `deploy.sh`, `deploy-instaled.sh`, migrations, rotas, controllers.

## Restricoes

- **Nao commitar nem pushar**; tudo na working tree de `develop_natan`.
- Nenhum conteudo de traducao/schema em log.
