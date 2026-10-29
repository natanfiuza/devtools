# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: escapa chaves literais no i18n e adiciona compilador tolerante
```

---

## 🎯 Resumo

O bloco de exemplo cURL exibido na home quebrava o render das páginas quando o idioma era diferente (ou igual) ao padrão: as traduções vivem em `lang/{locale}/home.php` e são compiladas em runtime pelo `vue-i18n` no navegador/SSR, então as chaves `{` e `}` literais do JSON de exemplo eram interpretadas como sintaxe de interpolação. O compilador padrão lança `SyntaxError` de dentro de `t()`, e o throw derrubava a árvore de componentes inteira — o usuário via tela em branco em vez de apenas um trecho de código mal formatado.

A correção atua em duas camadas: escapar as chaves literais nas cinco locales com a sintaxe de literal do `vue-i18n` (`{'{'}` / `{'}'}`) e instalar um `messageCompiler` tolerante como rede de segurança, para que qualquer tradução inválida futura degrade para texto cru em vez de derrubar a página. Uma guarda de teste passa a compilar **todas** as mensagens dos cinco idiomas com o compilador estrito, impedindo regressão silenciosa em novas traduções.

## 🛠️ Mudanças Técnicas

- **Correção das traduções** (`lang/en|es|fr|pt_BR|pt_PT/home.php`): chaves `{` e `}` do exemplo cURL escapadas com literais de interpolação (`{'{'}` / `{'}'}`), preservando o texto exibido ao usuário.
- **Compilador tolerante** (`resources/js/lib/i18n.ts`): novo `safeMessageCompiler` que envolve `compile` do `@intlify/core-base` em `try/catch`, retornando a mensagem crua em caso de falha.
- **Cache de fallback e de log**: `rawMessageCache` evita recompilar a mesma mensagem inválida a cada `t()` (o core-base não cacheia mensagens com erro), e `loggedCompileFailures` limita o `console.error` a uma ocorrência por `locale:key`, sem vazar o conteúdo da tradução no log.
- **Nova dependência** (`package.json`): `@intlify/core-base ^11.4.14`, usada diretamente para importar `compile`, `MessageCompiler` e `MessageFunction`.
- **Teste de guarda** (`resources/js/__tests__/i18n-messages.test.ts`): exporta `lang/` via `php -r` lendo `config/locales.php`, compila cada folha de cada namespace nos cinco idiomas com `createI18n` **puro** (sem o compilador tolerante) e falha listando `locale/namespace.chave` + detalhe do erro.
- **Testes do fallback** (`resources/js/__tests__/i18n.test.ts`): cobrem a degradação para texto cru sem lançar e garantem que a interpolação válida (`{count}`) continua funcionando.

## ⚠️ Impacto/Avisos

- **Dependências**: adiciona `@intlify/core-base` como dependência direta. A versão **precisa** acompanhar a do `vue-i18n`; valide com `npm ls @intlify/core-base` (deve haver uma única cópia). Ao subir o `vue-i18n`, suba as duas juntas.
- **Banco de dados / variáveis de ambiente**: nenhuma migração, seed ou nova env. Sem ação de deploy além do `npm install`.
- **Pré-requisito do CI**: o novo teste invoca o binário `php` via `execFileSync`, portanto o runner precisa ter PHP no `PATH` e o repositório completo (lê `config/locales.php` e `lang/`).
- **Comportamento em produção**: traduções inválidas passam a renderizar o texto cru com um `console.error` único, em vez de derrubar a página. Erros derivados de `{`/`}` literais ainda são bugs de conteúdo — a guarda de teste é quem deve capturá-los antes do merge.
- **Log**: as mensagens de erro do i18n são omitidas do `console.error` de propósito (o erro original do compilador carrega um code frame com a tradução), então o diagnóstico em produção depende do par `locale:key`.

close #109

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)