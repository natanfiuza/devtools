# Plano de Implementação — Suporte a Primitivas SVG e Erro Detalhado no Upload de Logo do BadgeForge

## Goal Description
Resolver a recusa de arquivos SVG vetoriais válidos no upload de logos personalizados do BadgeForge (`/dashboard/badgeforge` e construtor da tool), onde o `CustomLogoSvgParser` atualmente aceita apenas `<g>` e `<path>` e exibe a mensagem genérica `"O SVG usa um elemento não permitido."`.

A mudança mantém intacto o contrato de segurança da **ADR-078** (persistir apenas `viewBox` + lista de `<path>` normalizados, sem markup arbitrário):
1. Converter primitivas geométricas SVG seguras (`<rect>`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`) em `LogoPath` (`d="..."`) durante o parse.
2. Ignorar tags de metadados benignos (`<title>`, `<desc>`, `<metadata>`).
3. Aceitar e ignorar atributos cosméticos inofensivos comuns em editores (`stroke`, `stroke-width`, `stroke-linecap`, `stroke-linejoin`, `opacity`), mantendo bloqueados `style`, `transform`, `filter`, `clip-path`, `mask`, `href`, `on*` e `url(...)`.
4. Informar qual elemento foi recusado na mensagem de erro (`unsupported_element`) nos 5 idiomas e melhorar a dica de formato na UI (`resources/js/tools/badgeforge/LogoUploadForm.vue`).

---

## Proposed Changes

### Backend — Parser e Validação (`app/Support/BadgeForge` & `app/Actions/BadgeForge`)

#### [MODIFY] `app/Support/BadgeForge/InvalidCustomLogoException.php`
Adicionar parâmetro opcional `public readonly array $context = []` ao construtor para transportar metadados seguros do erro (ex.: `['element' => '<script>']`).

#### [MODIFY] `app/Actions/BadgeForge/ParseCustomLogoUpload.php`
Repassar `$exception->context` para `__()` ao construir a `ValidationException`.

#### [MODIFY] `app/Support/BadgeForge/CustomLogoSvgParser.php`
1. **Elementos ignorados (`IGNORED_ELEMENTS`)**: `['title', 'desc', 'metadata']`. Quando encontrados em `walk()`, checar namespace (`assertNamespace`) e pular imediatamente (`continue;`) sem processar filhos ou texto interno.
2. **Primitivas convertidas (`PRIMITIVE_ELEMENTS`)**: `['rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon']`.
   - Verificar que não possuem filhos não-whitespace (mesma regra de `<path>`).
   - Validar atributos permitidos por primitiva + atributos cosméticos inofensivos (`fill`, `stroke`, `stroke-width`, `stroke-linecap`, `stroke-linejoin`, `opacity`, `class`, `id`, `fill-rule`, `clip-rule`) sem `url(...)` e sem namespace estranho.
   - Converter cada primitiva para string `d` (`<rect>` incluindo `rx`/`ry`, `<circle>`, `<ellipse>`, `<line>`, `<polyline>`, `<polygon>`).
   - Atributos geométricos inválidos ou ausentes lançam `InvalidCustomLogoException('invalid_path')`.
   - Submeter o `d` gerado ao mesmo fluxo de `appendPathData()` (`isSafePathData`, `max_path_length`, `max_total_path_length`, `max_paths` e herança de `fill-rule`/`clip-rule`).
3. **Atributos cosméticos em `<g>` e `<path>`**: adicionar `stroke`, `stroke-width`, `stroke-linecap`, `stroke-linejoin`, `opacity` à lista de atributos aceitos (e ignorados) em `readRules()`.
4. **Contexto em `unsupported_element`**: ao recusar elemento fora da allowlist em `walk()`, lançar `InvalidCustomLogoException('unsupported_element', ['element' => '<'.$child->localName.'>'])`.

---

### Internacionalização (`lang/*/validation.php` & `lang/*/tools.php`)

#### [MODIFY] `lang/{en,pt_BR,pt_PT,fr,es}/validation.php`
Atualizar `custom_logo.unsupported_element` nos 5 idiomas para exibir `:element`.

#### [MODIFY] `lang/{en,pt_BR,pt_PT,fr,es}/tools.php`
Atualizar `tools.badgeforge.management.upload_hint` nos 5 idiomas para explicar de forma concisa os elementos aceitos e restrições.

---

### Documentação (`docs/`)

#### [MODIFY] `docs/adr/ADR-078-badgeforge-logos-personalizados.md`
Atualizar a seção `Parser e persistência (CustomLogoSvgParser)` registrando a conversão das 6 primitivas vetoriais para `<path>`, a ignorância de `<title>`/`<desc>`/`<metadata>` e os atributos cosméticos ignorados.

#### [MODIFY] `docs/tutorial/badgeforge.md`
Atualizar a seção `4.1 Logos personalizados (Meus logos)` para refletir o suporte a primitivas vetoriais básicas e metadados.

---

### Testes (`tests/`)

#### [MODIFY] `tests/Unit/BadgeForge/CustomLogoSvgParserTest.php`
- Testes unitários de conversão de `<rect>` (simples e com `rx`/`ry`), `<circle>`, `<ellipse>`, `<line>`, `<polyline>` e `<polygon>`.
- Testes para ignorar `<title>`, `<desc>` e `<metadata>` (inclusive com filhos/texto).
- Testes para atributos cosméticos ignorados (`stroke`, `stroke-width`, `opacity`, etc.) mantendo a recusa de `transform`, `style`, `on*`, `url(...)`.
- Testes de primitivas inválidas (`invalid_path`) e contexto `['element' => '<script>']` em `unsupported_element`.

#### [MODIFY] `tests/Feature/BadgeForge/BadgeCustomLogoTest.php` & `tests/Feature/BadgeForge/ManagementApi/LogoWriteApiTest.php`
- Atualizar asserções de `unsupported_element` para incluir `['element' => '<script>']`.
- Adicionar caso de upload de SVG contendo `<rect>`, `<circle>` e `<title>` verificando persistência normalizada em `paths`.

---

## Verification Plan

### Automated Tests
1. `php artisan test tests/Unit/BadgeForge/CustomLogoSvgParserTest.php tests/Feature/BadgeForge/BadgeCustomLogoTest.php tests/Feature/BadgeForge/ManagementApi/LogoWriteApiTest.php`
2. `composer test`
3. `npm run test` e `npm run types:check`

### Manual Verification
- Em `/dashboard/badgeforge`, enviar um arquivo SVG de ícone contendo `<rect>`, `<circle>`, `<title>` e `stroke` e confirmar que o logo é salvo e renderizado na prévia.
- Enviar um SVG contendo `<text>` ou `<defs>` e confirmar que a mensagem exibe o nome do elemento recusado (ex.: `O SVG usa um elemento não permitido (<text>).`).
