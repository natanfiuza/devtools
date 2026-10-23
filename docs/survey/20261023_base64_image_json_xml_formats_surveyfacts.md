# Survey — Formatos JSON e XML no Base64 Image Encoder (2026-10-23)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Na tools Base64 Image Encoder / Decoder adicione mais duas formas de saida do base64 em json e xml.
- **Tela / Modulo:** Rota publica `/{locale}/tools/base64-image`, manifest `resources/js/tools/base64-image/manifest.json`, componente `resources/js/tools/base64-image/EncoderPanel.vue`, tipos em `resources/js/tools/base64-image/types.ts` e arquivos de traducao em `lang/{locale}/tools.php`.
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `develop_natan`
- **Metodo:** 1 rodada de interrogacao tecnica com 4 decisoes resolvidas e aceitas pelo usuario.
- **Arquivos-chave lidos:**
  - `resources/js/tools/base64-image/EncoderPanel.vue`
  - `resources/js/tools/base64-image/types.ts`
  - `resources/js/tools/base64-image/manifest.json`
  - `lang/pt_BR/tools.php`
  - `lang/en/tools.php`
  - `lang/pt_PT/tools.php`
  - `lang/es/tools.php`
  - `lang/fr/tools.php`
  - `docs/SPEC.md`
  - `docs/glossario.md`

## 2. Decisoes das rodadas

### Rodada 1 — Esquemas de Saida, Download e Abas
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Estrutura e propriedades do payload JSON | **JSON formatado com 2 espacos** contendo metadados completos (`filename`, `mime_type`, `file_size_bytes`, `base64_size_bytes`), alem do `data_uri` e a string `base64` pura extraida. |
| Q2 | Estrutura e sintaxe do documento XML | **Documento XML completo** com declaracao `<?xml version="1.0" encoding="UTF-8"?>`, no raiz `<image>`, tags filhas com metadados e o `data_uri` e `base64` com escape seguro de caracteres especiais. |
| Q3 | Comportamento do botao de Download | **Botao adaptativo e contextual**: exibe "Baixar .txt" para Data URI, Img e CSS (url do servidor), e "Baixar .json" ou "Baixar .xml" nas respectivas abas, disparando download nativo via Blob no cliente com o nome `{filename}.json` ou `{filename}.xml`. |
| Q4 | Nomes e icones das novas abas | **5 abas no `TabsList`**: `Data URI`, `Tag <img>`, `CSS`, `JSON`, `XML`. Icones Lucide: `Braces` para JSON e `CodeXml` (ou `FileCode`) para XML. |

### Defaults assumidos (nao contestados)
- As novas saidas rodam 100% no cliente (navegador), aproveitando os dados do `ConversionItem` ja retornados pelo endpoint de conversao, sem sobrecarga ou chamadas adicionais ao servidor.
- Para conversoes que acionam Direct Download (> 2 MB no plano pago), a regra de protecao de memoria permanece: a interface nao renderiza textos gigantes na tela nem cria elementos pesados no DOM, mantendo o download direto como unico meio.
- Caracteres especiais em nomes de arquivos sao sanitizados com funcao auxiliar `escapeXml` para evitar XML malformado.
- Adicao das chaves i18n nos 5 idiomas suportados (`en`, `pt-BR`, `pt-PT`, `es`, `fr`).

## 3. Relatorio de fatos levantados
### Composicao e Formatacao no Cliente
- A computacao `formattedOutput` em `EncoderPanel.vue` ja gerencia os formatos existentes (`data-uri`, `img`, `css`). A inclusao de `json` e `xml` pode ser extraida para uma funcao pura em `resources/js/tools/base64-image/lib/formatters.ts` para facilitar testes unitarios no Vitest.
- O helper de download via Blob ja e um padrao estabelecido no projeto (`downloadBlob` ou similar criado com `URL.createObjectURL`), permitindo exportar os arquivos `.json` e `.xml` sem requisicao ao servidor.
