# Survey — Suporte a primitivas SVG e erro detalhado no upload de logo personalizado do BadgeForge (2026-11-20)

> Levantamento automatico da sessao de grill (`/grill-with-docs`) — fronteira esgotada e entendimento compartilhado confirmado pelo usuario.

## 1. Contexto da tarefa
- **Pedido original:** Verificar por que no dashboard do usuário ao enviar um arquivo SVG do BadgeForge é retornada a mensagem de erro `"O SVG usa um elemento não permitido."` ao tentar adicionar um logo personalizado.
- **Tela / Modulo:** Dashboard BadgeForge (`/dashboard/badgeforge`, [BadgeForge.vue](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/pages/dashboard/BadgeForge.vue)) e upload compartilhado ([LogoUploadForm.vue](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/resources/js/tools/badgeforge/LogoUploadForm.vue)) → `POST /badgeforge/logos` e `POST /api/v1/badgeforge/logos` → [ParseCustomLogoUpload.php](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Actions/BadgeForge/ParseCustomLogoUpload.php) → [CustomLogoSvgParser.php](file:///c:/Users/nataniel/projetos/pessoal/devtools_natanfiuza/app/Support/BadgeForge/CustomLogoSvgParser.php).
- **Skill:** `/grill-with-docs` (grilling + domain-modeling) · branch `feature/layout-harmonizer-fase-6`
- **Metodo:** 2 rodadas de grilling (5 decisões), inspeção direta de parser, testes, traduções, ADR-078 e tutorial.
- **Arquivos-chave lidos:**
  - `app/Support/BadgeForge/CustomLogoSvgParser.php`
  - `app/Support/BadgeForge/InvalidCustomLogoException.php`
  - `app/Actions/BadgeForge/ParseCustomLogoUpload.php`
  - `app/Support/BadgeForge/StaticBadgeRenderer.php`
  - `app/Support/BadgeForge/Svg.php`
  - `config/badgeforge.php`
  - `resources/js/tools/badgeforge/LogoUploadForm.vue`
  - `resources/js/pages/dashboard/BadgeForge.vue`
  - `lang/{en,pt_BR,pt_PT,fr,es}/validation.php` e `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`
  - `tests/Unit/BadgeForge/CustomLogoSvgParserTest.php`
  - `tests/Feature/BadgeForge/BadgeCustomLogoTest.php`
  - `tests/Feature/BadgeForge/ManagementApi/LogoWriteApiTest.php`
  - `docs/adr/ADR-078-badgeforge-logos-personalizados.md`
  - `docs/tutorial/badgeforge.md`

## 2. Decisoes das rodadas

### Rodada 1 — Diagnóstico, escopo de elementos e UX de erro
| # | Decisao | Resolucao |
|---|---|---|
| Q1 | Origem do arquivo SVG enviado | **Logo/ícone `.svg` externo enviado na seção do BadgeForge no dashboard (`/dashboard/badgeforge`)** |
| Q2 | Suporte a primitivas SVG e metadados benignos | **Converter primitivas geométricas seguras (`<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`) em `LogoPath` (`d="..."`) e ignorar metadados benignos (`<title>`, `<desc>`, `<metadata>`)** |
| Q3 | Clareza da mensagem de erro e dica na UI | **Incluir o nome do elemento recusado na mensagem (`:element`) nos 5 idiomas e detalhar as regras de formato em `LogoUploadForm.vue`** |

### Rodada 2 — Contorno de atributos e documentação
| # | Decisao | Resolucao |
|---|---|---|
| Q4 | Primitivas abertas e atributos cosméticos de editores | **Converter as 6 primitivas (`rect`, `circle`, `ellipse`, `polygon`, `polyline`, `line`), ignorar `<title>`/`<desc>`/`<metadata>` sem descer nos filhos, aceitar e ignorar atributos cosméticos inofensivos (`fill`, `stroke`, `stroke-width`, `stroke-linecap`, `stroke-linejoin`, `opacity`, `class`, `id`), mantendo bloqueados `style`, `transform`, `filter`, `clip-path`, `mask`, `href`, `on*` e `url(...)`** |
| Q5 | Registro na documentação | **Atualizar `docs/adr/ADR-078-badgeforge-logos-personalizados.md` e `docs/tutorial/badgeforge.md` (sem novo ADR)** |

### Defaults assumidos (nao contestados)
- O contrato de persistência da ADR-078 permanece inalterado: `badge_custom_logos` grava apenas `view_box`, `paths` normalizados (`[{d, fillRule?, clipRule?}]`), `path_count` e `size_bytes`; o arquivo bruto nunca é salvo nem servido.
- Os limites de `config/badgeforge.php` (`max_bytes`, `max_paths`, `max_path_length`, `max_total_path_length`, `max_viewbox_extent`, `max_document_depth`) continuam valendo também para os paths gerados a partir de primitivas.
- Todas as 5 traduções (`en`, `pt_BR`, `pt_PT`, `fr`, `es`) devem ser atualizadas com acentuação correta em português.

## 3. Relatorio de fatos levantados
### Causa raiz de `"O SVG usa um elemento não permitido."`
Em `CustomLogoSvgParser::walk()` (`app/Support/BadgeForge/CustomLogoSvgParser.php#L27`), `ALLOWED_ELEMENTS` contém exclusivamente `['g', 'path']`. Qualquer SVG exportado de editores/bibliotecas que utilize formas básicas (`<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`) ou contenha tags de acessibilidade/metadados (`<title>`, `<desc>`, `<metadata>`) é rejeitado com `InvalidCustomLogoException('unsupported_element')`, mapeado em `lang/*/validation.php` para `"O SVG usa um elemento não permitido."` sem indicar qual tag foi recusada.

### Restrições e armadilhas identificadas
1. **Tags de metadados com filhos/texto (`<title>`, `<desc>`, `<metadata>`)**: `<title>Logo</title>` contém nó texto e `<metadata>` do Inkscape/Illustrator contém árvores `<rdf:RDF>`. Se `walk()` descer nesses elementos, disparará `text_not_allowed` ou `unsupported_element`. O parser deve ignorar o elemento inteiro (`continue;` antes de recursão).
2. **Validação de `<rect>` com `rx`/`ry`**: Quando `rx`/`ry` estão presentes e `> 0`, devem ser limitados a `min(rx, width/2)` e `min(ry, height/2)` (com `rx` herdando `ry` e vice-versa quando apenas um é informado, conforme especificação SVG) e convertidos usando comandos de arco `A` no `d`.
3. **Limites de `max_paths` e `max_total_path_length`**: Cada primitiva convertida gera um `LogoPath` e deve passar exatamente pelo mesmo funil de validação (`isSafePathData`, `max_path_length`, `max_total_path_length`, `max_paths`).
4. **Testes existentes com placeholder `:element`**: `tests/Feature/BadgeForge/BadgeCustomLogoTest.php:118` e `tests/Feature/BadgeForge/ManagementApi/LogoWriteApiTest.php:67` comparam com `__('validation.custom_logo.unsupported_element')` sem parâmetros; precisarão passar `['element' => '<script>']`.
