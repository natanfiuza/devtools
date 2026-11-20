# Layout Harmonizer — F5 (exportações Markdown, CSS e ZIP)

## Context

F1–F4 entregaram o motor, o quadro, a edição manual e a persistência (relatório em
`docs/claude-code/reports/feature-layout-hamonizer-fase-4/20261119_feature_layout-hamonizer-fase-4_f4_persistencia.md`).
A F5 fecha o contrato de exportação de `docs/colors/layout-harmonizer/PROMPT-Fase5-markdown_css_zip.md` e
`EXPORTACOES.md`: gerar localmente, a partir do snapshot exibido, um documento Markdown de instruções para IA,
um arquivo CSS com fallback HEX/OKLCH e um ZIP com os dois — sem servidor de exportação, sem chamada de IA e
sem regenerar nenhuma cor.

Data: 2026-11-20. Branch: `feature/layout-hamonizer-fase-4` (grafia do repositório). As decisões da F4 não são
reabertas; a exportação lê exclusivamente `LayoutSystemStateV1.snapshot`.

Restrições em vigor: sem commit/push (gitpr do usuário); sem dependências além do `fflate` (autorizado no grill);
sem migration nem endpoint de exportação; toda string via i18n nos 5 idiomas; tools do cliente 100% no navegador;
nunca registrar conteúdo de snapshot em log.

## Decisões fechadas no grill

| Tema | Decisão |
| --- | --- |
| Biblioteca ZIP | `fflate` (MIT, 0.8.3) declarado como dependência direta; árvore pequena e auditável |
| Visitante anônimo | Card de exportação visível e bloqueado, com CTA de login (não escondido) |
| Página pública | Nenhuma exportação para ninguém (`colors/s/{slug}` continua só leitura + duplicação) |
| Fallback CSS | Base HEX/rgb + bloco `@supports (color: oklch(0 0 0))` redeclarando em OKLCH |
| Estados | Exporta o snapshot exibido em live/compatível/readonly/arquivado, com versões e avisos; bloqueado só em `pendingRegeneration` |
| Temas | Valores claros no escopo base (default sem atributo) + `[data-theme="light"]`/`[data-theme="dark"]` |
| Origem no MD | Só quando o crédito congelado é de paleta pública, em texto, sem link nem dados privados |
| Nomes | Dentro do ZIP sempre `design-instructions.md` e `colors.css`; avulsos `design-instructions.{locale}.md` e `colors.css`; ZIP `{slug}-layout.zip` |
| UI | Card abaixo do quadro (padrão `ExportPanel` das paletas), só download (sem copiar arquivo integral) |
| Idioma | Um seletor (default = idioma atual) para prosa do MD e comentários do CSS; nomes técnicos em inglês |
| Nome não salvo | Nome sugerido do salvamento (`suggestLayoutSystemName`); editor usa o nome salvo |
| Validação | Prefixo `^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$` ≤32; escopo `:root` ou `^\.[A-Za-z_][A-Za-z0-9_-]*$` ≤64 |

## Contratos

### Entrada comum

`resources/js/tools/layout-harmonizer/export/types.ts`:

```ts
interface LayoutExportInput {
    state: LayoutSystemStateV1;      // único, já exibido; nunca regenerado
    name: string;                    // salvo ou sugerido
    language: LayoutExportLanguage;  // en | pt_BR | pt_PT | fr | es
    messages: LayoutExportMessages;  // subtree do idioma escolhido (vem do servidor)
    origin: { name: string; public: boolean } | null;
}
interface LayoutExportFile { format: 'markdown' | 'css'; filename: string; mime: string; contents: string }
```

`messages` é o subtree `tools.layout-harmonizer` do idioma escolhido, entregue pelo servidor para todos os
locales (o cliente só recebe o locale ativo hoje — ver §Backend). Interpolação `{chave}` feita no exportador.

### Markdown (`export/markdown.ts` → `buildLayoutMarkdown(input)`)

Documento UTF-8 autossuficiente, nesta ordem:

1. `# {nome}` + parágrafo de finalidade.
2. Metadados: idioma, estratégia, meta (AA/AAA), seed original/efetiva/fundo de composição, secundária
   auto/manual, versões (`state`, `schema`, `algorithm`, `suggestions`, `snapshot`).
3. Origem (condicional, crédito público).
4. Instruções para IA: usar só os tokens semânticos, não inventar cores, respeitar bloqueios/manuais,
   mapear fundos/textos/botões/links/feedback/foco.
5. Temas claro/escuro.
6. Tokens por tema em tabela: `Token | Finalidade | HEX | OKLCH | Proveniência | Bloqueado`
   (33 × 2 linhas; finalidade de `tools.layout-harmonizer.tokens.*`; proveniência de `provenance.*`).
7. Regras de aplicação (fundos, textos, botões, links, feedback, foco).
8. Contrastes por tema: `Frente | Fundo | Razão | AA | AAA` (16 pares; razão com `formatRatio` no locale).
9. Avisos sempre presentes: falhas de contraste **medidas contra `snapshot.target`** (AA usa `aaNormal`,
   AAA usa `aaaNormal`; `diagnostics` é só AA e não serve para o alvo AAA), bloqueios, overrides manuais,
   ajustes aceitos, tokens clipados, modo compatível/readonly e limitações; “nenhum” quando vazio. Ajustes
   aceitos não removem aviso.
10. Nota de geração: snapshot exibido, gerado localmente, sem IA.

Texto do usuário (nome, origem) escapado para título e células de tabela: `|`, backticks, `*`/`_`, `#` no
início, `<`/`>` e quebras de linha. Origem: no tool só quando o `origin.public` do servidor for `true`; no
editor só quando `credit.palette_public` for `true` — nunca a partir de nome de paleta ao vivo.

### CSS (`export/css.ts` → `buildLayoutCss(input, { prefix, scope })`)

```css
/* Layout Harmonizer — {nome} */
/* {idioma} · Estado 1 · Schema 1 · Algoritmo 1 · Sugestões 1 · Snapshot 1 */
/* Tema padrão: claro. Use <html data-theme="dark"> para o escuro. */

:root, :root[data-theme="light"] { --lh-primary: #3b82f6; ... }   /* fallback sRGB */
:root[data-theme="dark"] { --lh-primary: #60a5fa; ... }

@supports (color: oklch(0 0 0)) {
    :root, :root[data-theme="light"] { --lh-primary: oklch(62.3% 0.214 259.8); ... }
    :root[data-theme="dark"] { ... }
}

/* Avisos
   - {falhas de contraste}
   - {bloqueios/manuais}
   - {nota de versão compatível/readonly}
*/
```

- Escopo `:root` (default) ou classe `.app`: os mesmos seletores combinados (`{scope}`, `{scope}[data-theme=...]`).
- Alpha preservado: fallback `rgba(r, g, b, a)` e `oklch(... / a)`; nunca compor de novo.
- Comentários e avisos sanitizados: remover `*/`, CR/LF, controles e colapsar espaços; texto do usuário só
  no cabeçalho sanitizado. Os trechos técnicos (`@supports`, seletores) são emitidos pelo código, nunca
  vindos de `lang/` (o teste de i18n continua proibindo `@`/`|` crus).
- Sem `@media` automática; sem seletor arbitrário; validação em `export/options.ts` (prefix/escopo do grill).

### ZIP (`export/zip.ts` → `buildLayoutZip(markdown, css, name)`)

- `fflate.zipSync({ 'design-instructions.md': strToU8(md), 'colors.css': strToU8(css) })`; `Blob` tipo
  `application/zip;charset=utf-8`, nome `{slug}-layout.zip` (sanitização local NFD/regex, fallback
  `layout-harmonizer`, máx. 32 como nos exportadores de paleta).
- Timestamp fixo e `level` fixo (`mtime` explícito; sem ele o `zipSync` usa `Date.now()`); os dois arquivos
  saem do **mesmo** `LayoutExportInput`. Determinismo testado gerando duas vezes no mesmo processo e
  comparando bytes + conteúdo descompactado — não com golden byte a byte, que depende de fuso (DOS date).

## Backend (pequeno, sem endpoint de exportação)

1. `app/Support/Colors/LayoutSystemExportMessages.php`: `all()` devolve, para as 5 locales
   (`Locales::all()`), somente o subtree necessário: `export` (novo), `tokens`, `strategy`, `provenance`,
   `families`, `view`, `contrast` e `seed.summary`. Fora `save`, `guard`, `a11y`, `prototype` e o resto de
   `seed` — o payload fica enxuto e o teste de paridade garante que as chaves existem nos 5 idiomas.
2. `config/colors.php` ganha `layout_systems.export` (`prefix_max_length` 32, `scope_max_length` 64,
   documentados no CONTRATO-TECNICO); `LayoutSystemToolProps::for()` passa `'exportLimits'` e
   `'exportMessages' => LayoutSystemExportMessages::all()` (tool anônimo e autenticado via `ToolProps`).
3. `LayoutSystemController::edit()` passa `'exportMessages'` e `'exportLimits'` iguais.
4. `LayoutSystemIntentController::create()` aceita `export=1` validado como booleano “presença”: quando
   presente, redirect ao tool leva `export=1` e **não** `save=1` (CTA de exportação do anônimo volta logado
   sem abrir o modal de salvar; `palette` continua validada/preservada). `save=1` segue intocado; teste cobre
   anônimo (vai ao login) e autenticado (redirect direto) sem loop.
5. Tipos em `resources/js/types/layoutSystem.ts`: `LayoutSystemExportMessages = Record<string, Record<string, unknown>>`.
6. Pest: `LayoutSystemExportMessagesTest` (5 locales, mesmas folhas, 33 tokens, 4 estratégias, grupo export);
   teste do intent com `export=1`; ajuste dos testes de Inertia das páginas tool/editor para a nova prop.

## Frontend

Criar em `resources/js/tools/layout-harmonizer/export/`:

| Arquivo | Responsabilidade |
| --- | --- |
| `types.ts` | `LayoutExportInput`, `LayoutExportFile`, `LayoutExportLanguage`, opções |
| `options.ts` | defaults (`lh`, `:root`), validação do prefixo/escopo, mensagens de erro por chave i18n |
| `filename.ts` | `sanitizeExportName`, `zipFileName`, `markdownFileName(locale)` |
| `messages.ts` | lookup no subtree, interpolação `{chave}`, escape Markdown/célula/comentário CSS |
| `css.ts` | `buildLayoutCss` (tokens/checks do snapshot, `@supports`, avisos) |
| `markdown.ts` | `buildLayoutMarkdown` (seções do contrato, tabelas, avisos) |
| `zip.ts` | `buildLayoutZip` com `fflate` |

Componente `components/LayoutExportPanel.vue` (card):

- Props: `state`, `name`, `authenticated`, `pendingRegeneration`, `mode`, `readOnly`, `origin`,
  `exportMessages`, `exportLimits` — todas opcionais com defaults inertes (sem mensagens, o card não
  renderiza), para não quebrar mounts diretos existentes em teste.
- Anônimo: card com cadeado + CTA de login via intent (`systems.create.url({ query: { export: '1', ...(palette) } })`).
- Autenticado: seletor de idioma (chaves de `exportMessages`, rótulos de `page.props.locales`), campo de
  prefixo e escopo; três botões (MD, CSS, ZIP). Prefixo/escopo inválidos → erro inline e downloads desabilitados.
- `pendingRegeneration` → aviso e downloads desabilitados; compatível/readonly/arquivado → aviso informativo
  com versões. `readOnly` vem do editor (`archived || mode === 'readonly'`) — o card não infere.
- Downloads: `downloadText` para MD/CSS e `downloadBlob` para ZIP, ambos de `@/lib/download` (existente e testado).
- O card monta ao fim do corpo do painel, depois de sugestões/protótipo (DESIGN: “sugestões e exportação
  abaixo”). Como o `Tool.vue` remonta o painel com `:key` na regeneração, idioma/prefixo voltam ao default
  nesse evento — comportamento aceito e documentado (regeneração é ação explícita).

Integração:

- `LayoutHarmonizerPanel.vue`: novas props `exportMessages`/`exportLimits`; renderiza o card ao fim do
  corpo com `currentState()`, `suggestedName`, `controller.mode`, `controller.pendingRegeneration` e origem
  (`origin.public` **do servidor** no tool; `credit.palette_public` no editor — nunca o nome da paleta ao vivo).
- `Tool.vue`: repassa `toolProps.exportMessages`, `exportLimits` e `origin`.
- `LayoutSystemEditor.vue`: repassa `exportMessages`/`exportLimits` ao painel; no caminho arquivado/readonly
  (que não monta o painel) monta o card diretamente com `system.state`, `system.name`, `readOnly` e
  `system.credit` — exportar é leitura e não usa o controller.
- `PublicLayoutSystem.vue`: sem mudanças (nenhuma exportação).

Reuso: `formatHex`/`formatHex8`, `formatRatio` (`boardView.ts`), `strategyMessageKey`,
`suggestLayoutSystemName`, `downloadText`/`downloadBlob`, padrão de testes de `download.test.ts`.

## i18n

- Novo grupo `tools.layout-harmonizer.export` (5 locales), ~70 chaves: `title`, `description`, `login_cta`,
  `login_hint`, `language_label`, `prefix_label/hint/invalid`, `scope_label`, `scope_root`, `scope_class`,
  `scope_class_label`, `scope_invalid`, `download_markdown/css/zip`, `pending_regeneration`,
  `compatible_notice`, `readonly_notice`, e subgrupos `md.*` (títulos de seção, cabeçalhos de tabela,
  instruções para IA, regras de aplicação, avisos, limitações, nota de geração) e `css.*` (cabeçalho,
  comentários de tema/versões, avisos).
- Reuso (sem duplicar): `tokens.*` (33), `strategy.*.name`, `view.light/dark`, `families.*`, `provenance.*`,
  `contrast.*`, `seed.summary.*`.
- Os 5 idiomas com as mesmas folhas (`TranslationsParityTest`); `layout-harmonizer-i18n.test.ts` passa a
  verificar também o grupo `export` (chaves usadas, sem `@`/`|` crus, placeholders coerentes).
- O documento é gerado no idioma escolhido a partir de `exportMessages` (não do vue-i18n ativo); os rótulos
  do card usam o idioma ativo normalmente.

## Testes (Vitest, em `resources/js/__tests__/`)

| Arquivo | Casos |
| --- | --- |
| `layout-harmonizer-export-css.test.ts` | estrutura por tema/escopo, `@supports` e ordem, alpha em rgba/oklch, prefixo/escopo válidos e inválidos, comentários sanitizados, versões, avisos, determinismo |
| `layout-harmonizer-export-markdown.test.ts` | seções do contrato, 33 tokens × 2 temas, mesma origem pública/privada, instruções para IA, avisos sempre presentes, escape de nome, cada um dos 5 idiomas, determinismo |
| `layout-harmonizer-export-zip.test.ts` | `unzipSync` do fflate: dois arquivos, nomes fixos, UTF-8 (acentos), conteúdo igual ao gerado, nome do ZIP sanitizado, bytes determinísticos |
| `layout-harmonizer-export-panel.test.ts` | anônimo com CTA e sem downloads; autenticado com três botões; erro de prefixo/escopo desabilita; `pendingRegeneration` bloqueia; troca de idioma muda o documento; aviso compatível |
| `layout-harmonizer-export-consistency.test.ts` | snapshot de fixture do motor: valores do quadro === valores do MD/CSS; congelar snapshot sentinela prova que não regenera |
| `layout-harmonizer-export-options.test.ts` | limites 32/64 e regex do grill |

Atualizar: `layout-system-editor.test.ts` (arquivado mostra o card; painel recebe `exportMessages`),
`layout-system-public.test.ts` (nenhum card), `layout-harmonizer-i18n.test.ts`; testes Pest de página
(tool/editor) pela prop nova.

## ADRs e documentação

- Copiar `adr/ADR-LH-004.md` → `docs/adr/ADR-148-colors-lh-exportacoes.md` e
  `adr/ADR-LH-011.md` → `docs/adr/ADR-155-colors-lh-css.md`, com a seção “Implementação (F5)”.
- Novos: `ADR-175-colors-lh-exportacao-local.md` (contrato, `fflate`, nomes, permissões, snapshot único),
  `ADR-176-colors-lh-css-fallback.md` (prefixo/escopo validados, `data-theme`, `@supports`, alpha,
  comentários sanitizados) e `ADR-177-colors-lh-markdown-ia.md` (estrutura, idioma, avisos, origem pública).
- Atualizar `EXPORTACOES.md` (contrato implementado), `ADR-MAPEAMENTO.md` (status F5; contagem),
  `docs/colors/README.md`, `docs/SPEC.md` §5/§9 (“F5 concluída em 2026-11-20”), `docs/glossario.md`
  (Exportação local, Prefixo CSS, Escopo CSS) e `PARAMETROS.md` (seção “Parâmetros das exportações (F5)”:
  prefixo ≤32, classe ≤64, nomes fixos do ZIP).

## Etapas e gates

| Etapa | Conteúdo | Gate ao fim |
| --- | --- | --- |
| 1 | `npm install fflate@^0.8.3`; `export/{types,options,filename,messages}.ts` | Vitest de options/mensagens |
| 2 | `export/css.ts` | Vitest de CSS |
| 3 | `export/markdown.ts` | Vitest de Markdown |
| 4 | `export/zip.ts` | Vitest de ZIP |
| 5 | Backend: `LayoutSystemExportMessages`, ToolProps, editor, intent `export=1` | Pest e `composer test` |
| 6 | `LayoutExportPanel.vue` + integração tool/editor (incl. arquivado) | Vitest de painel/editor/pública |
| 7 | i18n nos 5 idiomas e paridade | `TranslationsParityTest`, `layout-harmonizer-i18n`, i18n de export |
| 8 | ADRs, docs, gates completos e relatório | todos os gates |

Gates reais (sem resultados inventados):

```bash
composer test
npm run test
npm run types:check
npm run format:check
npm run lint:check
npm run build
```

## Verificação manual

1. Anônimo: card bloqueado com CTA → login pelo intent `export=1` → volta ao tool com card liberado
   (e rascunho pendente oferecido antes).
2. Autenticado sem salvar: exportar MD/CSS/ZIP; conferir título sugerido e arquivos idênticos ao quadro.
3. Editor salvo: exportar; arquivar e exportar de novo (readonly) com versões gravadas.
4. Falha de contraste não bloqueia e aparece nos dois arquivos; bloqueio/manual/ajuste aceito também aparecem.
5. `pendingRegeneration` no modo compatível bloqueia a exportação com o mesmo aviso do salvamento.
6. Prefixo/escopo inválidos rejeitados; `:root` e `.app` geram seletores corretos; tema claro sem atributo,
   escuro com `data-theme="dark"`; CSS válido aplicado num HTML de teste; ZIP abre com os dois arquivos.
7. Período nos 5 idiomas do documento e da UI; nenhuma requisição de rede no painel de exportação.

## Riscos e mitigação

1. **Cliente só tem o locale ativo** → `exportMessages` com o subtree nos 5 idiomas, montado no servidor.
2. **`fflate` só como transitiva do `jspdf`** → declarar direta `^0.8.3` (MIT, autorizada no grill).
3. **Fallback em custom properties** → `@supports` validado por estrutura e manualmente; nada de declaração dupla.
4. **Determinismo** → sem data no texto, timestamp fixo no ZIP, testes de saída byte a byte.
5. **Markdown quebrado por texto do usuário** → escape de título/célula e grupo i18n sem `|`/`@`.
6. **Regressão no painel F4** → props novas opcionais, testes existentes atualizados na mesma etapa.
7. **Payload das mensagens** → envio só do subtree necessário (`Arr::only`), não do arquivo `tools` inteiro.

## Parada

Salvar `docs/survey/20261120_layout_harmonizer_f5_surveyfacts.md` e
`docs/plans/feature_layout-hamonizer-fase-4/20261120_f5_exportacoes_plansfacts.md` ao iniciar a implementação;
plano em `docs/claude-code/plans/feature_layout-hamonizer-fase-4/20261120_feature_layout-hamonizer-fase-4_f5_exportacoes.md`;
relatório em `docs/claude-code/reports/feature-layout-hamonizer-fase-4/20261120_feature-layout-hamonizer-fase-4_f5_exportacoes.md`.
Ao fim: **parar para revisão**, sem commit/push e sem iniciar a F6.
