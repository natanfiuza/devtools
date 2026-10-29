# Plano — Home em branco em producao (erro de compilacao do vue-i18n)

- **Branch:** `develop_natan`
- **Data:** 2026-10-28
- **Origem:** sessao `/grill-with-docs`
- **Decisao:** ADR-060

## Contexto

Em producao a Home renderiza o layout com o **corpo vazio** e o console mostra `SyntaxError: 2` do vue-i18n 11.4.14. A causa e `home.api.preview_code` (linha 33 de todos os `lang/{en,es,fr,pt_BR,pt_PT}/home.php`), que guarda um exemplo cURL com um objeto JSON literal. O `{` abre interpolacao, o compilador falha com `INVALID_TOKEN_IN_PLACEHOLDER` (codigo 2) e o `onError` do `@intlify/core-base` lanca `SyntaxError` de dentro de `t()`, abortando `app.mount()`. As traducoes sao dados de runtime (PHP -> prop do Inertia -> `setLocaleMessage`), por isso `npm run build` passa sem erro.

## Decisoes

1. Escapar os literais com `{'{'}` / `{'}'}` (convencao do `{'@'}`).
2. `messageCompiler` tolerante: traducao invalida degrada para texto cru em vez de derrubar a pagina.
3. Teste de guarda em Vitest com o compilador estrito e o `t()` real.
4. CSP nao muda; hosts de Ads negados por design (ADR-032). A conversao `AW-998597367` e desvinculada no painel do Google pelo usuario.
5. Entrega em `develop_natan`, **sem commit/push**.

## Passos

1. **Guarda primeiro (vermelho):** `resources/js/__tests__/i18n-messages.test.ts` le `config/locales.php` + `lang/` via `php -r`, cria `createI18n` **puro** por locale e chama `t()` em todas as folhas; asserts de locales, `total > 0` e `failures` vazio. Confirmar 5 falhas em `*/home.api.preview_code`.
2. **Escape:** trocar apenas os literais JSON da linha 33 dos 5 arquivos; validar render byte a byte identico; guarda verde.
3. **Rede de seguranca:** em `resources/js/lib/i18n.ts`, `safeMessageCompiler` que chama `compile()` do `@intlify/core-base` em `try/catch`, devolve texto cru, cacheia por `locale + chave + mensagem` e avisa uma vez por `locale:chave` sem o conteudo. Passar em `createI18n`. Adicionar `@intlify/core-base` as dependencias.
4. **Testes:** em `i18n.test.ts`, mensagem invalida nao lanca e devolve texto cru; `{count}` continua interpolando.
5. **Documentacao:** ADR-060, SPEC secao 3/11.1, ARQUITETURA, glossario, survey/planfacts e relatorio.
6. **Verificacao:** `npm run test`, `types:check`, `lint:check`, prettier nos arquivos tocados, `composer test`; render SSR dos 5 idiomas.

## Fora do escopo

CSP, `SecurityHeaders`, Google Ads, deploy, migrations, rotas e controllers.

## Restricoes

Nao commitar nem pushar; nenhum conteudo de traducao/schema em log.
