# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: libera seis tools simples e extrai libs compartilhadas
```

---

## 🎯 Resumo

A F3 tira seis ferramentas do estado "em breve" e as coloca no ar: JSON to Code, SQL Formatter, Base64/URL/Hash, JWT Decoder, Timestamp/UUID e Regex Tester. Com isso, o catálogo sai de uma ferramenta funcional (JSON Viewer) para sete, e apenas o AI SQL Builder — que depende de servidor — continua com selo no índice.

O trabalho revelou código que só existia dentro do JSON Viewer e que as novas tools também precisavam (editor CodeMirror, posição linha/coluna, formatação de bytes, download de arquivo, tipos de JSON, dropzone de arquivo, recado de cópia e erro de arquivo). Esse código foi promovido para `resources/js/lib` e `resources/js/composables`, evitando uma segunda cópia divergente em cada tool nova.

O Regex Tester, por poder ser escrito de forma catastrófica (backtracking), ganhou limites configuráveis no servidor: teto de tempo de execução e quantidade máxima de resultados — a busca nunca pode travar a aba de quem usa.

## 🛠️ Mudanças Técnicas

- Seis manifests passam de `"status": "soon"` para `"available"`: `json-to-code`, `sql-formatter`, `base64-url-hash`, `jwt-decoder`, `timestamp-uuid`, `regex-tester`.
- Extração de código compartilhado para fora do JSON Viewer: `lib/json/{parse,types}`, `lib/position`, `lib/bytes`, `lib/download`, `lib/file`, `lib/editor/*`, `components/tool/CodeEditor.vue`, `components/tool/FileDropzone.vue`, `composables/useCopyFeedback` e `composables/useFileError`.
- `JsonEditor.vue`, `editor/createEditor.ts`, `editor/theme.ts`, `lib/bytes.ts`, `lib/download.ts` e `lib/types.ts` deixam de existir no diretório da tool.
- Novos limites em `config/tools.php`: `regex_timeout_ms` (padrão 2000 ms) e `regex_max_matches` (padrão 1000), expostos ao cliente via `ToolController` e propagados como props por `Show.vue` até cada tool.
- `ToolShell.vue` passa a usar `inheritAttrs: false`, impedindo que limites não consumidos por uma tool virem atributos soltos no elemento raiz.
- `App\Support\Tools` passa a descartar manifests com status `hidden`, alinhando o leitor PHP ao leitor TypeScript: tool escondida não aparece em home, sitemap nem cartão de compartilhamento, e resolve no mesmo 404 de slug desconhecido.
- `ToolButton.vue` ganha `ariaLabel` opcional, usado pelos alternadores de flag de regex (`i`, `m`, `s`, `u`).
- Traduções novas para todas as tools nos cinco idiomas, incluindo a generalização das mensagens de estado do JSON Viewer (`stale_tree` → `stale_output`, `invalid` genérico) e `file_types` como placeholder.
- Dependências novas: `@codemirror/lang-sql` e `sql-formatter`.
- Testes ajustados/ampliados: home linka sete cards, registro reflete os sete `available`, sitemap lista cada página, metadados OG cobrem cada tool `available`, e o teste de busca do admin passa a fixar o e-mail do admin (evitava flake quando o Faker sorteava um e-mail contendo o termo buscado).

## ⚠️ Impacto/Avisos

- **Variáveis de ambiente novas**: `TOOLS_REGEX_TIMEOUT_MS` e `TOOLS_REGEX_MAX_MATCHES` (ambas com padrão no `config/tools.php`, portanto opcionais). Ambientes que já definem limites devem revisar valores conforme a carga esperada do Regex Tester.
- **Dependências novas de front-end**: `@codemirror/lang-sql` e `sql-formatter` exigem `npm install` e novo build de assets.
- **Mudança de comportamento no registro de tools**: manifests com status `hidden` agora desaparecem também no back-end (sitemap, cards e rota retornando 404). Qualquer manifest marcado como `hidden` deixa de ser acessível por URL.
- **Sem migrações de banco de dados.**
- As seis ferramentas rodam inteiramente no navegador; nenhum dado colado é enviado ao servidor — o único tráfego novo é o carregamento dos assets.
- Sitemap e imagens Open Graph passam a incluir sete páginas: reprocessar `seo:og-image` em deploy para garantir os PNGs por slug.