# ADR-130 - Exportação de design tokens v1
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-095 (exportação de tokens, emenda), ADR-096 (paletas), ADR-097 (formato de token), ADR-108 (CORS e importação), ADR-117 (núcleo de cor e gamut), ADR-120 (escala), ADR-123 (papéis persistidos), ADR-126 (papéis e temas), ADR-133 (importação validada)

## Contexto
A F2 exportava CSS, SCSS, JSON, Tailwind v3/v4 a partir dos 11 tons. A ADR-095 já citava DTCG, Style Dictionary e CSS/SCSS/TS, mas faltava definir o contrato: o que é primitiva, o que é semântica, como a versão do formato é registrada, como alpha e gamut aparecem e o que cada arquivo contém. A F5 também introduziu papéis claro/escuro no editor (ADR-126) e precisava levá-los para os tokens sem inventar papéis no gerador puro.

## Decisão

### 1. Duas camadas: 11 primitivas e, quando existirem, 8 papéis × 2 temas
- **Primitivas**: sempre os 11 tons `50–950` da escala, com o nome sanitizado da paleta (`primary` por padrão).
- **Semântica**: opcional, apenas quando o painel recebe `roles` (o editor de paleta). São os 8 papéis (`primary`, `secondary`, `accent`, `background`, `surface`, `text`, `text_muted`, `border`) em `light` e `dark`, como valores literais — não aliases das primitivas, porque o usuário pode editar qualquer papel.
- O gerador puro não tem papéis (ADR-126): ali a exportação é só primitiva. Sem `roles`, os formatos da F2 continuam byte a byte iguais; Tailwind v3/v4 nunca ganham a camada semântica porque ela não cabe em `theme.colors`.

### 2. Formatos e nomes
| Formato | Arquivo | Nomes |
|---|---|---|
| W3C DTCG | `{name}.tokens.json` | `color.{name}.{tom}` e `color.{name}.semantic.{tema}.{papel}` |
| Style Dictionary | `{name}.style-dictionary.json` | mesma árvore, com `value`/`type` |
| TypeScript | `{name}.tokens.ts` | `{camelCase(name)}Tokens`, `as const` |
| CSS/SCSS | `{name}.css` / `{name}.scss` | `--color-{name}-{tom}` / `$color-{name}-{tom}`; papéis `--color-{tema}-{papel}` |
| JSON nativo | `{name}.json` | `name`, `formatVersion`, `algorithmVersion`, `base`, `clippedTones`, `colors`, `semantic?` |

O arquivo é sempre determinístico e termina com quebra de linha; o download usa `downloadBlob` no navegador. Não há endpoint de exportação no servidor.

### 3. Versão do formato é um contrato explícito
`COLOR_TOKEN_FORMAT_VERSION = 1` é registrado no JSON nativo (`formatVersion`), no DTCG (`$extensions["devtools.colors"].formatVersion`) e no cabeçalho de CSS/SCSS/TypeScript. O JSON do Style Dictionary fica limpo: o schema clássico não tem slot de metadados e um campo extra poderia virar token ou quebrar o v3; a versão aparece no painel. A importação valida esse número (ADR-133).

### 4. Metadados no namespace próprio
Além da versão, o DTCG leva em `$extensions["devtools.colors"]`: `algorithmVersion`, `name`, `base`, `clippedTones` e a nota de gamut. Tokens Studio e Figma Variables ignoram extensões que não conhecem. O JSON nativo carrega os mesmos dados em campos de primeiro nível, o que o torna o formato de ida e volta.

### 5. Alpha e gamut são explícitos
- Onde o formato suporta, alpha fica em `#rrggbbaa` (DTCG, Style Dictionary, TypeScript, JSON nativo, Tailwind v4); CSS, SCSS e Tailwind v3 usam `rgba(r, g, b, a)` com até 3 casas. Consumidores que não suportam alpha descartam o canal.
- Os tons vêm de OKLCH mapeado para sRGB. Croma que não cabe é reduzido preservando L e H (ADR-117); os tons afetados aparecem em `clippedTones` (JSON/DTCG) e na nota de gamut (DTCG/TypeScript).

### 6. Sem integração com a API do Figma
O fluxo é baixar o JSON e importar pelo plugin Tokens Studio ou pelas Figma Variables. Não há token, chamada ou promessa de compatibilidade universal com plugins.

## Consequências
- O mesmo painel serve primitivas e semânticas sem duplicar exportadores: `buildPaletteExport` recebe `roles?` e repassa aos formatos que as suportam.
- O formato tem versão verificável e ida e volta real para JSON nativo, DTCG e Style Dictionary (ADR-133).
- O gerador continua com saída estável e os arquivos da F2 não mudam quando não há papéis.
- Style Dictionary fica sem metadados: quem precisa de versão/base usa DTCG ou o JSON nativo.
- CSS/SCSS não dependem de `prefers-color-scheme` nem de classe `.dark`; os dois temas são variáveis explícitas.

## Alternativas rejeitadas
- **Aliases semânticos apontando para primitivas**: o editor permite editar cada papel; um alias impediria valores fora da escala.
- **Semântica no gerador puro**: não existem papéis fora do editor (ADR-126); inventá-los criaria uma segunda fonte de defaults.
- **Tailwind com papéis**: `theme.colors` não distingue claro/escuro e misturaria as camadas.
- **Metadados no Style Dictionary**: viraria token ou quebraria o schema clássico.
- **Formato sem versão**: a importação não teria como recusar um contrato incompatível.
- **API do Figma**: exigiria credenciais e rede, contrariando o processamento local e o requisito explícito.
