# Plano de Implementacao: Formatos JSON e XML no Base64 Image Encoder

> **Data:** 2026-10-23  
> **Branch:** `develop_natan`  
> **Arquivo:** `docs/gemini/plans/develop_natan/20261023_develop_natan_base64_image_json_xml_formats.md`  
> **Referencia:** `docs/survey/20261023_base64_image_json_xml_formats_surveyfacts.md`

---

## 1. Objetivo

Adicionar os formatos de saida **JSON** e **XML** na ferramenta **Base64 Image Encoder / Decoder** (`base64-image`), permitindo ao desenvolvedor visualizar o resultado formatado com metadados completos, copiar o conteudo para o clipboard e efetuar o download direto dos arquivos `.json` e `.xml` gerados no cliente (navegador).

---

## 2. Mudancas Estruturadas

### 2.1. Tipagem (`resources/js/tools/base64-image/types.ts`)
- Expandir a uniao `OutputFormat`:
  ```ts
  export type OutputFormat = 'data-uri' | 'img' | 'css' | 'json' | 'xml';
  ```

### 2.2. Modulo de Formatadores (`resources/js/tools/base64-image/lib/formatters.ts`)
Criar funcoes puras isoladas para formatacao e sanitizacao:
- `escapeXml(value: string): string`: Sanitiza caracteres reservados (`&`, `<`, `>`, `"`, `'`).
- `extractRawBase64(dataUri: string): string`: Extrai o payload Base64 sem o prefixo `data:...;base64,`.
- `formatJsonOutput(item: ConversionItem): string`: Gera objeto JSON formatado (2 espacos):
  ```json
  {
    "filename": "...",
    "mime_type": "...",
    "file_size_bytes": 1234,
    "base64_size_bytes": 5678,
    "data_uri": "data:image/...;base64,...",
    "base64": "..."
  }
  ```
- `formatXmlOutput(item: ConversionItem): string`: Gera documento XML valido:
  ```xml
  <?xml version="1.0" encoding="UTF-8"?>
  <image>
    <filename>...</filename>
    <mime_type>...</mime_type>
    <file_size_bytes>1234</file_size_bytes>
    <base64_size_bytes>5678</base64_size_bytes>
    <data_uri>data:image/...;base64,...</data_uri>
    <base64>...</base64>
  </image>
  ```
- `formatSnippet(format: OutputFormat, item: ConversionItem): string`: Centraliza a resolucao do snippet para qualquer formato.

### 2.3. Interface do Codificador (`resources/js/tools/base64-image/EncoderPanel.vue`)
- Adicionar os icones `Braces` e `CodeXml` de `@lucide/vue`.
- Adicionar as abas `json` e `xml` no `TabsList`.
- Atualizar a computacao `formattedOutput` chamando `formatSnippet`.
- Atualizar a acao de download:
  - Se `selectedFormat === 'json'`, dispara download local via `Blob` de `formattedOutput` com nome `${item.filename}.json`.
  - Se `selectedFormat === 'xml'`, dispara download local via `Blob` de `formattedOutput` com nome `${item.filename}.xml`.
  - Se `selectedFormat` for `data-uri`, `img` ou `css`, mantem o link ou download do arquivo `.txt` do servidor.
- Rotulo contextual no botao de download:
  - `download_txt` para Data URI / Img / CSS
  - `download_json` para JSON
  - `download_xml` para XML

### 2.4. Internacionalizacao (`lang/{locale}/tools.php`)
Adicionar as novas chaves nos 5 idiomas suportados (`pt_BR`, `pt_PT`, `en`, `es`, `fr`):
- `tools.base64-image.formats.json`:
  - `pt_BR` / `pt_PT`: "JSON"
  - `en` / `es` / `fr`: "JSON"
- `tools.base64-image.formats.xml`:
  - `pt_BR` / `pt_PT`: "XML"
  - `en` / `es` / `fr`: "XML"
- `tools.base64-image.download_json`:
  - `pt_BR`: "Baixar .json"
  - `pt_PT`: "Descarregar .json"
  - `en`: "Download .json"
  - `es`: "Descargar .json"
  - `fr`: "Télécharger .json"
- `tools.base64-image.download_xml`:
  - `pt_BR`: "Baixar .xml"
  - `pt_PT`: "Descarregar .xml"
  - `en`: "Download .xml"
  - `es`: "Descargar .xml"
  - `fr`: "Télécharger .xml"

### 2.5. Testes Unitarios (`resources/js/__tests__/base64-image.test.ts`)
- Adicionar suite de testes para `formatters.ts`:
  - Validacao da estrutura do JSON emitido e parse via `JSON.parse()`.
  - Validacao do documento XML emitido (declaracao, tags e fechamento).
  - Validacao de escape de caracteres especiais em nomes de arquivos com XML (`<`, `>`, `&`, quotes).
  - Validacao da extracao correta de Base64 raw sem o prefixo data-uri.

---

## 3. Criterios de Aceite e Validacao

1. **Testes do Frontend:** `npm run test` executado com 100% de sucesso.
2. **Checagem de Tipos:** `npm run types:check` sem erros de TypeScript ou Wayfinder.
3. **Analise e Estilo:** `composer test` (Pint, PHPStan e Pest) sem violacoes.
4. **Sem Regressoes:** Nenhum impacto nas funcionalidades de Direct Download, Decodificador ou Historico.
