# Relatório — Colors Fase 4, Etapa 9: protótipos vetoriais e exportações

- Data: 2026-11-10
- Branch: `feature/colors-fase-4`
- Plano: `docs/claude-code/plans/feature_colors-fase-4/20261110_feature_colors-fase-4_colors_fase4_persistencia_prototipos.md` (§4.6 e item 9 da sequência)
- Etapas anteriores da fase: 0–8 concluídas e verificadas
- Observação: checkpoint de etapa; **nada foi commitado** (CLAUDE.md — o usuário commita via gitpr)

## Contexto

A F4 já entrega persistência, Minhas Paletas, editor e página pública (etapas 1–8). A Etapa 9 fecha o protótipo: uma aba no editor que desenha a paleta salva aplicada a duas telas de exemplo (site e celular), nos dois temas, com papéis semânticos editáveis e exportação SVG vetorial e PNG 1x/2x.

Tudo continua 100% no navegador. Nenhuma dependência nova de captura (`html2canvas` etc.): o SVG é o formato canônico e o PNG é rasterização do mesmo SVG.

## O que foi feito

### 1. Módulo de protótipo (`resources/js/tools/palette-generator/prototype/`)

| Arquivo | Papel |
| --- | --- |
| `types.ts` | árvore de nós (`rect`/`circle`/`text`), seções (`canvas`, `header`, `appbar`, `tabs`, `hero`, `buttons`, `links`, `cards`, `content`, `form`, `footer`, `palette`), `PrototypeCopy`, `PrototypeDocument`, `RenderOptions` |
| `renderSvg.ts` | serializador puro e determinístico: ordem fixa de atributos, `num()` com 2 casas e normalização de `-0`, `escapeXml` em texto e atributos, `readableOn` (preto/branco por luminância relativa), `roleStrip` |
| `templates/site.ts` | 1200×900: cabeçalho (marca, 3 links, CTA), hero, botões primário/secundário, links, 3 cards, formulário, rodapé |
| `templates/mobile.ts` | 390×844: barra do app, **tabs no topo**, hero, 2 cards, formulário, rodapé — **sem bottom nav** |
| `templates/og.ts` | 1200×630: marca, barra primária, título, tagline e faixa da paleta (11 cores quando recebidas; senão os 6 papéis principais) |
| `defaults.ts` | `PROTOTYPE_TEMPLATES`, `PROTOTYPE_SIZES` e `buildPrototype(input)` → `PrototypeDocument` |
| `fonts.ts` | Libre Franklin Regular/Bold como data URI via `?inline`, memoizada; só entra no bundle por import dinâmico |
| `rasterize.ts` | SVG → `Image` → canvas → PNG, em 1x/2x; revoga a URL do blob sempre (inclusive em erro) |
| `exportPrototype.ts` | nome estável, SVG com fonte embutida, PNG do mesmo SVG, downloads via `downloadBlob` |
| `PrototypePreview.vue` | SVG inline (`v-html` só da saída do renderizador), `role="img"` + rótulo traduzido |
| `RoleColorField.vue` | `input[type=color]` (HEX6) + campo de texto (parser aceita qualquer formato, normaliza para HEX6/HEX8 e reverte inválido) |
| `RoleEditor.vue` | os 8 papéis do tema em edição + "Restaurar padrões" |
| `PrototypePanel.vue` | site/celular × claro/escuro, preview, exportar SVG/PNG 1x/2x, copiar SVG, erro de PNG e o editor de papéis |

### 2. Integração com o editor

`PaletteEditor.vue` passou a:

- guardar `roles` como estado do editor (`cloneRoles` rasa por tema, nunca mutando no lugar);
- adicionar a aba Protótipo pelos slots `tabs-extra`/`panels-extra` do `GeneratorWorkspace` (`TabsTrigger value="prototype"` + `TabsContent`); `?tab=prototype` já é aceito por `initialTab()`;
- sobrescrever `payload.roles` com os papéis editados no PATCH, depois do payload calculado pelo snapshot;
- calcular os padrões de "Restaurar padrões" a partir do workspace vivo (`payloadFromSnapshot(current.snapshot()).roles`), com fallback para os papéis do servidor.

### 3. Identidade visual

- O preview não embute fonte: a Libre Franklin já é carregada pelo `app.css` do app.
- A exportação (SVG e PNG) embute Regular e Bold; `font-display: block` evita a primeira pintura com fallback no rasterizador.
- Os TTFs ficam num chunk próprio (`fonts-*.js`, ~290 KB) carregado só ao exportar — o preview e o restante do editor não pagam esse custo.
- Cada elemento leva `data-role` (o papel que o pinta) e `data-section` (a região), o que torna o SVG auditável e alimentou os testes.
- Texto sobre `primary`/`secondary` mantém o `data-role` mas usa `readableOn()` (preto ou branco), nunca a cor do papel.

### 4. i18n

Namespace `colors.prototype` nos cinco idiomas: 27 chaves de UI + bloco `sample` com 24 chaves de texto de exemplo. O placeholder de e-mail do sample usa o escape do vue-i18n (`voce{'@'}empresa.com`), exigido pelo compilador estrito.

## Testes

### Vitest (novos)

| Suíte | Cobre |
| --- | --- |
| `prototype-defaults.test.ts` | tamanhos exatos dos três documentos, lista de templates, nó raiz por tipo, casos de `readableOn` (incluindo entrada inválida) |
| `prototype-templates.test.ts` | site com as 7 regiões pedidas e nenhum elemento fora da tela; botões com papéis `primary`/`secondary`; celular 390×844 com tabs no topo e **sem** `bottom_nav` nem rótulo de tab repetido; OG com 11 cores ou 6 papéis |
| `prototype-render.test.ts` | determinismo; 6 snapshots (site/celular/OG × claro/escuro); `data-role` → `fill` conferido no DOM; escaping de `<script>`, `&` e aspas; fonte embutida só quando pedida |
| `prototype-rasterize.test.ts` | dimensões 1x/2x, SVG como origem, tipo do blob, revogação da URL em sucesso e em todas as falhas (canvas nulo, PNG nulo, SVG que não carrega) |
| `palette-editor.test.ts` (ampliado) | stub do workspace agora monta os slots num `Tabs` real; `?tab=prototype` abre o painel; papel editado chega no PATCH e não vaza para o outro tema; "Restaurar padrões" devolve o tom 600 |

Os snapshots somam ~28 KB e não contêm `NaN`/`undefined`/`Infinity`.

### Ajustes necessários

- `filter` com narrowing de nó de texto precisou de type predicate (`node is SvgTextNode`) para o `vue-tsc`.
- O aviso de `v-html` no `PrototypePreview` foi suprimido no padrão do repositório (`eslint-disable`/`eslint-enable` com motivo), como em `LogoPreview`/`Tool` do BadgeForge.
- Pint formatou as cinco traduções (`single_quote` que manteve aspas duplas por causa do `{'@'}` interno).

## Verificação

| Comando | Resultado |
| --- | --- |
| `npx vitest run` das 4 suítes novas | 26 testes, 6 snapshots gerados |
| `npm run test` | 109 arquivos, 1346 testes — verdes |
| `composer test` | pint + phpstan + pest — 1534 testes, 27301 asserções — verdes |
| `npm run types:check` | sem erros |
| `npm run lint:check` | 0 erros (restam avisos pré-existentes do repositório) |
| `npm run build` | sucesso; `fonts-*.js` isolado (~290 KB), confirmando que a fonte não entra no bundle principal |

## Pendências

- **Etapa 10:** renderer OG no cliente, regeneração pós-save e upload autenticado (reusa `templates/og.ts` com `colors`).
- **Etapa 11:** ADRs 123–126 (a 126 cobre os protótipos), emendas, docs/glossário/README, relatório final e matriz manual.
- Matriz manual pendente: exportar SVG e PNG 1x/2x nos dois templates e temas, abrir o SVG fora do app (fonte embutida), conferir fidelidade visual e teclado nos 4 tamanhos de tela.

## Arquivos

Novos: `resources/js/tools/palette-generator/prototype/` (12 arquivos de produção, 4 suítes + 1 snapshot).

Alterados: `resources/js/pages/dashboard/colors/PaletteEditor.vue`, `resources/js/__tests__/palette-editor.test.ts`, `lang/{en,es,fr,pt_BR,pt_PT}/colors.php`.
