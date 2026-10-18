# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**

```text
feat: entrega Fase 3 com seis novas tools e infra compartilhada
```

---

## 🎯 Resumo

Esta entrega tira seis ferramentas do estado "em breve" e as coloca em uso real: **base64-url-hash, json-to-code, jwt-decoder, regex-tester, sql-formatter e timestamp-uuid**. Junto com o JSON Viewer da Fase 2, o catálogo público passa de 1 para 7 páginas indexáveis, mantendo apenas o AI SQL Builder no ciclo "soon" por depender de servidor.

O ganho não é só de vitrine: cada tool nova expôs necessidades que eram privadas do JSON Viewer (leitura de arquivo, editor, download, clipboard, worker). Em vez de copiar esse código seis vezes, a infraestrutura foi promovida a `resources/js/lib/`, o que reduz duplicação agora e encurta o custo das próximas fases.

## 🛠️ Mudanças Técnicas

- **Servidor:** `config/tools.php` e `.env.example` ganham `regex_timeout_ms` (2000) e `regex_max_matches` (1000); apenas os `manifest.json` das tools trocam de status.
- **ToolController:** passa a enviar todos os limites de `config/tools.php`, não só os da tool corrente (ADR-029) — a página resolve o componente pelo slug e cada tool usa o que lhe diz respeito.
- **Registro de tools:** `Tools::all()` filtra `status === 'hidden'`; itens ocultos desaparecem da home, do sitemap e do cartão de compartilhamento, caindo no mesmo 404 de slug desconhecido.
- **Núcleo compartilhado (`resources/js/lib/`):** `bytes`, `download`, `file` (ordem tamanho-antes-de-tipo), `position`, `encoding` (Base64 RFC 4648 nas duas variantes e URL componente/formulário com `TextEncoder`/`TextDecoder`), `json/{parse,types}` e `editor/{createEditor,theme}` — o editor agora é genérico, recebendo linguagem via `extensions` em vez de fixar `@codemirror/lang-json`; tokens `--json-*` foram reaproveitados para qualquer linguagem.
- **Worker genérico:** `worker/client.ts` + `worker/serve.ts` com descarte de resposta velha e queda silenciosa para a thread principal.
- **UI compartilhada:** novos `CodeEditor.vue` e `FileDropzone.vue`; `ToolButton` ganha `ariaLabel` (alternador de flags do Regex Tester); `ToolShell` recebe `inheritAttrs: false` para não espalhar props de limite não consumidas no DOM; novos composables `useCopyFeedback` e `useFileError`.
- **json-to-code:** inferência de tipos própria (`unionOf`/`merge` — objetos fundem, campos ausentes viram opcionais, int+float colapsa em float), deduplicação de nomes case-insensitive e três emissores: TypeScript, PHP (DTO final readonly, opcionais ao fim, docblock `@var` só quando agrega) e Pydantic (ordenação filho-antes-do-pai, import de `Any` condicional).
- **base64-url-hash:** MD5 implementado à mão conforme RFC 1321 (a Web Crypto não o expõe), `crypto.subtle` para SHA-1/256/512 e degradação para `hex: null` fora de contexto seguro.
- **jwt-decoder:** apenas decodifica, nunca verifica assinatura; separa falhas em shape/base64/json, extrai `iat`/`nbf`/`exp` com datas via `Intl` e mantém faixa de aviso fixa sobre a ausência de verificação.
- **regex-tester:** roda em worker com timeout configurável (proteção contra retrocesso catastrófico), avança `lastIndex` após match vazio respeitando pares substitutos com flag `u` e exclui `y` mantendo `g` sempre ligado.
- **sql-formatter:** encapsula `sql-formatter` + `@codemirror/lang-sql` com mapa de dialetos (mysql/pgsql/sqlsrv/sqlite → mysql/postgresql/transactsql/sqlite) e extrai linha/coluna da mensagem de erro.
- **timestamp-uuid:** aceita Unix em s/ms, ISO e UUIDv7/ULID, decidindo a unidade pelo limiar `1e11`, e gera UUID v4/v7 (RFC 9562) e ULID (Crockford base32) via `crypto.getRandomValues`, com fallback quando `randomUUID` não existe.
- **json-viewer migrado:** consome os módulos novos; `editor/JsonEditor.vue` e `lib/types.ts` foram deletados e o drag-and-drop caseiro foi substituído por `FileDropzone`.
- **I18n:** `lang/{en,es,fr,pt_BR,pt_PT}/tools.php` ganham blocos completos (intro, alvos/dialetos, rótulos, estados de erro, grupos) para as seis tools; chaves compartilhadas foram renomeadas para vocabulário neutro (`parsing` → "Processando/Processing", `stale_tree` → `stale_output`, `file_type` agora interpola `{formats}`).
- **Testes:** suíte nova cobrindo cada conta pura (encoding-base64, encoding-url, md5 contra vetores do RFC 1321, hash, ids, time, position, file, regex-run, regex-worker, sql-format, sql-dialects, json-to-code-infer/emit, jwt-decode, worker-client) e cada tela; `AdminPanelTest` fixa o e-mail do admin para eliminar flakiness (busca por nome/e-mail devolvia duas contas com "ana"); `ToolsRegistryTest` espera as sete tools e cobre o filtro de `hidden`; `SitemapTest` e `SeoMetaTest` iteram sobre `Tools::available()`, garantindo que nenhuma tool vire `available` sem PNG de OG image; `ToolPageTest` valida `regexTimeoutMs` e `regexMaxMatches`.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migração. **Sem novas variáveis de ambiente obrigatórias**, mas `regex_timeout_ms` e `regex_max_matches` passam a ser configuráveis (valores padrão já definidos em `config/tools.php`).
- **Dependências novas:** `@codemirror/lang-sql` e `sql-formatter` no `package.json` — requer `npm install`. Seis novas imagens OG em `public/og/`.
- **Contrato de servidor:** a mudança em `Tools::all()` (filtro de `hidden`) altera o que a home, o sitemap e o compartilhamento enxergam; vale confirmar que nenhuma outra tela depende hoje de listar tools ocultas.
- **SEO:** o sitemap passa a ter 7 URLs indexáveis; o AI SQL Builder permanece fora por depender de servidor.
- **Documentação:** o diff toca CHANGELOG, SPEC, ADRs (007, 029, 031) e DESIGN, mas o conteúdo foi excluído do diff e não foi auditado nesta revisão.
- **Performance:** a formatação SQL tem custo medido de ~9 s por MB, o que justifica o uso de worker com `terminate` por relógio — em arquivos muito grandes a UI cairá para processamento em background.

close #13

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)
