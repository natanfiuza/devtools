# ADR-060: Compilacao do vue-i18n em Runtime e Mensagem Invalida

Status: Aceito
Data: 2026-10-28
Relacionados: ADR-010 (i18n), ADR-014 (build no servidor), ADR-032 (analytics negado por padrao), ADR-052 (CSP)

## Contexto

Em producao a Home renderizava o layout (header, footer e demais telas) mas o **corpo ficava vazio**. O console do navegador mostrava, repetidamente:

```
vue-i18n-CaoWPIKs.js:1 SyntaxError: 2
```

O build (`npm run build` e `vite build --ssr`) terminava sem erro, porque as traducoes **nao entram no bundle**: sao dados de runtime. O PHP le `lang/{locale}/{namespace}.php`, `HandleInertiaRequests::translations()` manda os namespaces do locale ativo como prop do Inertia, e o front os aplica com `setLocaleMessage`. Quem compila a sintaxe das mensagens e o vue-i18n, no navegador e no SSR — depois do deploy.

A causa raiz estava em `home.api.preview_code` (linha 33 de **todos** os `lang/{locale}/home.php`): um exemplo de cURL com um objeto JSON literal,

```text
-d '{
    "schema_source": "ddl",
    ...
  }'
```

O vue-i18n le `{` como inicio de interpolacao. O `{` seguido de quebra de linha cai em `CompileErrorCodes.INVALID_TOKEN_IN_PLACEHOLDER` (codigo **2**, que o build de producao imprime sem a mensagem). O `onError` interno do `@intlify/core-base` **lanca** `SyntaxError` depois de reportar o erro, entao a excecao escapa de `t()` durante o render raiz e aborta o `app.mount()` — daí o layout sem corpo.

O ruido de `Content-Security-Policy` / Google Ads no mesmo console **nao tem relacao** com a falha: os hosts de anuncio sao negados por design (ADR-032) e o CSP apenas reflete isso (ADR-052).

Uma varredura das **7.930** folhas de traducao expostas (16 namespaces x 5 idiomas) com o compilador estrito encontrou **exatamente 5 falhas**, todas nessa mesma chave. A paridade de chaves entre idiomas estava correta.

## Decisao

### 1. Escapar os literais com a interpoliacao literal do vue-i18n

Chaves e fechaves que devem aparecer como texto sao escritas como `{'{'}` e `{'}'}` — mesma convencao ja usada para o arroba em `{'@'}`. A mensagem compilada renderiza o mesmo valor byte a byte que o original pretendia exibir.

### 2. Compilador de mensagens tolerante

`createI18nInstance()` passa um `messageCompiler` proprio (`resources/js/lib/i18n.ts`) que chama o `compile()` do `@intlify/core-base` dentro de `try/catch`. Se a mensagem nao compila:

- devolve uma funcao que exibe o **texto cru** da traducao;
- guarda esse fallback em cache por `locale + chave + mensagem` (o core-base nao cacheia mensagem com erro, e cada navegacao substitui o namespace inteiro — sem cache, todo `t()` da mesma chave recompilaria);
- registra **um** aviso por `locale:chave`, **sem o conteudo da traducao** (a mensagem de erro do compilador em dev carrega um code frame com o texto).

O objetivo e que uma traducao invalida degrade uma string, nunca a pagina inteira. `ssr.ts` e `app.ts` herdam o compilador porque ambos criam a instancia por `createI18nInstance()`.

### 3. Teste de guarda com o compilador estrito

`resources/js/__tests__/i18n-messages.test.ts` le `config/locales.php` e `lang/` com `php -r`, monta um `createI18n` **sem** o compilador tolerante e chama `t()` em todas as folhas dos cinco idiomas. Qualquer traducao nova com sintaxe quebrada falha o `npm run test` antes de chegar em producao.

O teste usa a instancia pura de proposito: com o compilador tolerante ele passaria com o bug presente.

### 4. Dependencia direta de `@intlify/core-base`

`@intlify/core-base` entra no `package.json` com a mesma versao do `vue-i18n` (`^11.4.14`). Hoje e hoisted por ele; declarar a dependencia evita que um `npm dedupe` ou uma mudanca de resolucao quebre o import. `npm ls @intlify/core-base` deve mostrar uma copia so; ao subir `vue-i18n` de major, subir as duas juntas.

## Consequencias

### Positivas

- Home (e qualquer tela) deixa de sumir por causa de uma traducao malformada; a falha fica visivel em uma string e registrada no console.
- O teste de guarda transforma um erro que so apareceria em producao num teste deterministico de desenvolvimento.
- A convencao de escape existente (`{'@'}`) foi estendida, sem sintaxe nova.

### Negativas / Custos

- `npm run test` passa a exigir o PHP no PATH (o teste de guarda le `lang/` pelo PHP, como o servidor faz).
- O compilador customizado e marcado como **experimental** pelo vue-i18n (aviso `[intlify] This project is using Custom Message Compiler...` em dev). Revisar no upgrade de major.
- Mensagem invalida aparece crua na tela ate alguem corrigir a traducao; o console/aviso e a unica sinalizacao.

## Alternativas descartadas

- **Pre-compilar as traducoes no build**: impossivel sem mudar a fonte da verdade. As mensagens sao PHP e chegam por prop; o `vite build` nao as ve (foi por isso que o build saiu verde).
- **`vite-plugin-vue-i18n` / build runtime-only**: exigiria gerar os bundles de mensagens no build, contrariando a ADR-010 (fonte unica em `lang/`) e o fallback por chave que o PHP ja resolve.
- **`try/catch` em volta do `app.mount()`**: esconde a falha e deixa a pagina inteira sem renderizar.
- **Recompilar sem o compilador customizado no SSR**: o problema nao e o SSR; navegador e SSR compilam as mesmas mensagens.
