# Fase 5 — Etapa 7: ADRs 127–133, documentos e relatório final

## Contexto

As Etapas 1–6 da Fase 5 já estão implementadas e verdes: biblioteca pessoal de
cores (histórico e favoritos da conta), biblioteca compartilhada entre HTML
Color Picker e Palette Generator, favoritos de paletas públicas, presets do
gerador, exportação de tokens e importação validada de tokens. O código já cita
ADR-127, ADR-128, ADR-132 e ADR-133, mas **nenhum ADR acima de 126 existe** em
`docs/adr/`; `docs/colors/` ainda descreve a F4 como estado atual; e não há
relatório final da fase.

A Etapa 7 é exclusivamente de fechamento documental: registrar as decisões em
ADRs datados, emendar sem reescrever o que já foi decidido, atualizar a
especificação e o glossário do grupo, produzir o relatório final com matriz
manual de aceite e rodar os portões. Nenhuma mudança de comportamento entra
aqui. Ao final, parar sem commit/push, conforme a regra do projeto.

Estado de partida (Etapa 6): `npm run test` 120 arquivos/1471 testes;
`composer test` Pint ok, PHPStan 0 erros, Pest 1585 testes/28338 asserções;
`types:check`, `lint:check` e `build` verdes.

## Decisões desta etapa

### 1. ADRs novos (127–133)

Todos em `docs/adr/ADR-0NN-colors-<slug>.md`, no formato vigente
(`# ADR-0NN - Título`, `Status: Aceito`, `Data: 2026-11-14`, `Relacionados`,
`## Contexto`, `## Decisão` em `###`, `## Consequências`,
`## Alternativas rejeitadas`). Não reutilizar número já ocupado.

| ADR | Arquivo | Decisão central | Relacionados |
|---|---|---|---|
| 127 | `ADR-127-colors-biblioteca-pessoal-de-cores.md` | Histórico e favoritos de cores passam a ser dados da conta: tabelas `color_history_entries` e `color_favorites`, unicidade por `(user, chave canônica)`, sem limite funcional, leitura paginada por cursor e escrita otimista; anônimo continua local e não faz requisição | 096, 118, 131, 132 |
| 128 | `ADR-128-colors-favoritos-de-paletas-publicas.md` | Favoritar paleta pública é vínculo `(user, palette)` único e idempotente na tabela `color_palette_favorites`, sem copiar a paleta nem contar “Uso”; só pública e ativa; própria paleta 403; privada/arquivada/desconhecida 404 uniforme; aba **Favoritas** oculta a que deixou de ser pública e a traz de volta se voltar | 096, 098, 124 |
| 129 | `ADR-129-colors-presets-como-entrada-da-geracao.md` | Preset do gerador é a entrada da geração — `{name, base}` —, nunca as cores derivadas, papéis ou tema; pertence à conta, não consome cota de paleta, tem CRUD e teto operacional opcional em `config/colors.php` (default `null`) | 093 (emenda), 096, 123 |
| 130 | `ADR-130-colors-exportacao-de-tokens-v1.md` | Exportação de tokens v1: 11 primitivas e, quando há papéis, 8 papéis × claro/escuro; DTCG/Style Dictionary/TypeScript além de CSS/SCSS/JSON nativo; `COLOR_TOKEN_FORMAT_VERSION = 1`; alpha em HEX8 onde o formato suporta; gamut documentado; metadados no DTCG (`$extensions["devtools.colors"]`) e no JSON nativo, Style Dictionary limpo; sem API do Figma; gerador puro exporta só primitivas | 095 (emenda), 096, 097, 108, 117, 120, 123, 126 |
| 131 | `ADR-131-colors-biblioteca-compartilhada-entre-ferramentas.md` | Uma única biblioteca no cliente, usada pelo picker e pelo gerador: mesmo `useColorLibrary`, mesmos painéis e mesma chave canônica; conta é a fonte da verdade para quem está logado, `localStorage` para anônimo; escrita otimista com reversão em falha; login não mescla listas automaticamente | 118, 127, 132 |
| 132 | `ADR-132-colors-historico-anonimo-e-importacao-explicita.md` | O anônimo mantém histórico local reduzido a 20 e favoritos em 50 (tetos em `config/colors.php`); o histórico da conta não tem cota funcional; o histórico local só entra na conta por ação explícita no painel, em lote (`import_batch`, 200), nunca em segundo plano | 096, 118, 127, 131 |
| 133 | `ADR-133-colors-importacao-validada-de-tokens.md` | Importação validada de JSON nativo, DTCG e Style Dictionary: somente `JSON.parse`, com limites de tamanho/tokens/profundidade/referências; detecção estrutural; seleção determinística de grupo; papéis só completos; metadados validados; diagnóstico que nunca descarta o estado; aplicação só no botão **Aplicar**; nenhum endpoint de importação; rascunho anônimo pode carregar escala e papéis pela assinatura do snapshot | 095, 123 (emenda), 130, 131 |

Conteúdo mínimo de cada ADR:

- Contexto com o requisito do prompt e o que existia antes.
- Decisão em itens numerados, citando tabelas, rotas, configs e módulos reais
  (`config/colors.php`, `routes/colors.php`, `useColorLibrary`,
  `tokenExporters.ts`, `tokenImport.ts`).
- Consequências incluindo o que **não** foi feito (sem API do Figma, sem
  requisição ao servidor nas tools, sem paleta anônima).
- Alternativas rejeitadas de forma concreta (ex.: paleta anônima no backend;
  guardar cores derivadas no preset; favoritar copiando a paleta; importar em
  segundo plano; `eval`/interpretação de código).

### 2. Emendas datadas, sem reescrever decisões antigas

Anexar `## Emenda 2026-11-14` ao final dos ADRs afetados, preservando o texto
anterior:

- **ADR-093**: o preset da F5 é apenas nome + base; não guarda escala, harmonia,
  papéis nem tema, para não renascer como “paleta disfarçada”. A decisão antiga
  permanece registrada.
- **ADR-095**: formato v1, camada semântica opcional, alpha/gamut, metadados por
  formato, Style Dictionary usando o tom 500 como base, ida e volta e o que
  continua apenas exportação.
- **ADR-096**: o anônimo continua apenas com `localStorage` (20 histórico/50
  favoritos); o rascunho de paleta não vira paleta anônima e, na F5, pode
  carregar escala e papéis opcionais.
- **ADR-118**: histórico/favoritos locais continuam sendo o modo anônimo; para
  quem está logado passam a vir da conta, e o histórico anônimo cai de 50 para
  20.
- **ADR-123 §7**: o rascunho em `sessionStorage` ganha `scale?` e `roles?`
  opcionais, validados de forma tolerante, com assinatura que descarta papéis
  obsoletos; continua distinto de paleta anônima, TTL de 24 h e sem imagem ou
  URL completa.

### 3. Documentos de `docs/colors/`

- **`SPEC-COLORS.md`** (v1.1 → v1.2):
  - §4: histórico/favoritos de cadastrado implementados na F5; anônimo 20/50.
  - §5: exportação e importação de tokens detalhadas; presets e favoritos
    marcados como implementados.
  - §6: rascunho com escala/papéis opcionais; favoritar paleta implementado.
  - §15: testes de importação validada, fixtures compartilhadas e biblioteca.
  - §16: **F5 implementada em 2026-11-14 (ADR-127 a ADR-133)**.
  - §17: critérios de aceite da F5 (biblioteca logado/anon, importação
    explícita, favoritos sem duplicata, presets, exportação e importação de
    tokens, i18n, nada ao servidor nas tools).
- **`README.md`**: estado “Fases 1–5 implementadas” com resumo da F5; numeração
  vigente **ADR-091 a ADR-133**; sete linhas novas na tabela de ADRs; seção
  **Emendas de 2026-11-14 (Fase 5)** listando 093, 095, 096, 118 e 123.
- **`glossario.md`**: atualizar termos desatualizados (Histórico local,
  Favorito, Preset, Token, W3C DTCG, Style Dictionary, Rascunho de paleta) e
  acrescentar: Biblioteca pessoal, Histórico da conta, Favorito de paleta, Aba
  Favoritas, Preset do gerador, Token primitivo, Token semântico, Versão do
  formato, Importação validada, Diagnóstico, Prévia/Aplicar, Alias, Ciclo,
  Assinatura do snapshot, Ida e volta, Importação explícita do histórico,
  Histórico anônimo reduzido.
- **`html-color-picker.md`**: substituir “histórico/favoritos locais de 50” pela
  regra da F5 (conta para logado; 20/50 para anônimo; biblioteca compartilhada
  com o gerador; importação explícita).
- **`palette-generator.md`**: atualizar `## Estado` para F5; acrescentar
  “Organização pessoal e tokens (Fase 5)” (Biblioteca, presets, importação de
  tokens) e **Critérios de aceite da Fase 5**.
- **`minhas-paletas.md`**: aba **Favoritas** (paletas públicas favoritadas,
  ocultação quando deixam de ser públicas).
- **`paleta-publica.md`**: estrela de favorito com `canFavorite`/`favorited`,
  regras 403/404 e ausência de cópia/“Uso”.
- **`import-export.md` / `export-formats.md`**: apenas referências cruzadas aos
  ADRs 130 e 133; o conteúdo funcional já foi atualizado nas Etapas 5 e 6.

Não criar `docs/acceptance/COLORS-FASE-5.md`: o precedente da F4 é a matriz
manual dentro do relatório final. Não criar docs novos em `docs/colors/`; a
biblioteca e os presets ficam descritos nas tools e no glossário.

### 4. Relatório final e matriz manual

`docs/claude-code/reports/feature_colors-fase-5/20261114_feature_colors-fase-5_colors_fase5_relatorio_final.md`,
seguindo o molde do relatório final da F4:

1. Título e metadados (data, branch, plano, prompt, etapas, “nada commitado”).
2. Visão geral da fase.
3. Backend (dados, support/actions, HTTP).
4. Frontend (tabela área/arquivos).
5. i18n.
6. ADRs e documentação (tabela 127–133 + emendas + docs atualizados).
7. Testes (suítes e contagens).
8. Verificação (comandos e resultados reais desta etapa).
9. **Matriz manual de aceite** (`# | Cenário | Passos | Esperado | Status`),
   com status `pendente` — a Etapa 7 não abre navegador.
10. Pendências e observações (ex.: concorrência real opt-in em MySQL herdada da
    F4, se ainda valer).
11. Arquivos novos/alterados.

Cenários da matriz: histórico e favoritos anônimos (20/50, dedupe); biblioteca
da conta e ausência de requisição para visitante; compartilhamento Picker ↔
Generator; importação explícita do histórico local; favoritar/desfavoritar
paleta pública; própria paleta 403 e privada/arquivada 404; aba Favoritas e
reaparecimento; presets (criar, renomear, aplicar, excluir, teto, sem consumir
cota); exportação DTCG/SD/TS/CSS com e sem papéis; alpha/gamut; importação dos
três formatos; erros (JSON inválido, formato ambíguo, tamanho, ciclo, papéis
incompletos) sem perder o estado; ida e volta; rascunho com escala/papéis;
i18n nos cinco idiomas; responsivo/tema; nenhuma requisição indevida.

## Etapas de implementação

1. Salvar cópia deste plano em
   `docs/claude-code/plans/feature_colors-fase-5/20261114_feature_colors-fase-5_stage7_docs_adr_relatorio_final.md`.
2. Criar ADR-127 a ADR-133 (conferir antes que 127 não existe).
3. Anexar as emendas de 2026-11-14 a 093, 095, 096, 118 e 123 §7.
4. Atualizar `SPEC-COLORS.md` para v1.2.
5. Atualizar `README.md` (estado, numeração, tabela, emendas).
6. Atualizar `glossario.md`.
7. Atualizar os quatro docs de tool; referências cruzadas em import/export.
8. Escrever o relatório final com a matriz manual.
9. Rodar os portões, preencher o relatório com resultados reais e parar.

## Arquivos

### Arquivos novos

- `docs/adr/ADR-127-colors-biblioteca-pessoal-de-cores.md` … 
  `docs/adr/ADR-133-colors-importacao-validada-de-tokens.md` (7 arquivos)
- `docs/claude-code/plans/feature_colors-fase-5/20261114_feature_colors-fase-5_stage7_docs_adr_relatorio_final.md`
- `docs/claude-code/reports/feature_colors-fase-5/20261114_feature_colors-fase-5_colors_fase5_relatorio_final.md`

### Arquivos alterados

- `docs/adr/ADR-093-colors-presets.md`, `ADR-095-colors-exportacao-tokens.md`,
  `ADR-096-colors-limites-paletas.md`,
  `ADR-118-colors-persistencia-local-do-picker.md`,
  `ADR-123-colors-persistencia-de-paletas.md`
- `docs/colors/SPEC-COLORS.md`, `README.md`, `glossario.md`,
  `html-color-picker.md`, `palette-generator.md`, `minhas-paletas.md`,
  `paleta-publica.md`, `import-export.md`, `export-formats.md`

## Verificação

Nenhum código muda, mas os portões são rodados para comprovar que o fechamento
documental não introduziu regressão:

| Comando | Esperado |
|---|---|
| `composer test` | Pint ok, PHPStan 0 erros, Pest verde (1585 testes na Etapa 6) |
| `npm run test` | Vitest verde (1471 testes na Etapa 6) |
| `npm run types:check` | Wayfinder + `vue-tsc --noEmit` sem erros |
| `npm run lint:check` | 0 erros (avisos preexistentes) |
| `npm run build` | build de produção concluído |
| `npm run format:check` | Prettier sem diferenças nos arquivos tocados |
| `npm run build:ssr` | build SSR concluído (mantém o padrão do relatório da F4) |

Os resultados reais entram no relatório final; divergência é relatada, não
mascarada.

## Riscos e mitigação

- **Número de ADR ocupado ou tema divergente**: conferir `docs/adr/` antes de
  criar; os temas 127/128/132/133 vêm das citações no código e dos relatórios
  das etapas; 129–131 são presets, exportação v1 e biblioteca compartilhada.
- **Reescrever decisão antiga**: apenas anexar emendas datadas; o texto original
  fica intacto.
- **Documentar comportamento inexistente**: usar os relatórios das Etapas 1–6 e
  os arquivos citados como fonte; não prometer telas ou formatos que o código
  não tem.
- **Spec e ADRs divergentes**: atualizar SPEC, README e glossário na mesma
  unidade e revisar as referências cruzadas.
- **Matriz de aceite parecer “aprovada”**: status `pendente` em todas as linhas
  até validação manual do usuário.
- **Regressão por engano**: só docs nesta etapa; portões completos ao final.

## Fora de escopo

- Alterar código, comportamento, rotas, configs ou testes.
- Criar `docs/acceptance/COLORS-FASE-5.md` ou entradas de CHANGELOG.
- Validar os cenários manualmente em navegador (ficam pendentes na matriz).
- Avançar para a F6.
- Commit ou push: o usuário commita e abre o PR pelo fluxo habitual.
