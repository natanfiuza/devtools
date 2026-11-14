# ADR-133 - Importação validada de design tokens
Status: Aceito
Data: 2026-11-14
Relacionados: ADR-095 (exportação de tokens), ADR-123 (rascunho de paleta, emenda), ADR-130 (exportação v1), ADR-131 (biblioteca compartilhada)

## Contexto
A F5 passou a exportar tokens (ADR-130), mas o caminho de volta não existia: um JSON nativo, DTCG ou Style Dictionary exportado não podia ser reaberto no gerador ou no editor. Arquivos de terceiros chegam com formatos misturados, árvores profundas, aliases circulares, cores em formatos inesperados e tamanhos arbitrários. O requisito foi importar esses três formatos com validação de estrutura, tamanho, profundidade, referências e ciclos, **sem executar nada**, com diagnóstico que nunca descarte o estado atual.

A importação fica disponível no gerador (sempre) e no editor (quando a paleta não está arquivada); não existe endpoint de importação no servidor.

## Decisão

### 1. Três formatos, detecção estrutural
- **JSON nativo**: objeto com `colors` e `formatVersion`/`algorithmVersion`/`base`/`clippedTones`.
- **W3C DTCG**: tokens com `$value` (e `$type`, possivelmente herdado do grupo).
- **Style Dictionary**: tokens com `value`/`type`.

A detecção é feita pela estrutura, não pela extensão. Misturar estilos no mesmo grupo é `ambiguous_format`; nenhum token de cor reconhecido é `unsupported_format`. TypeScript, CSS, SCSS e Tailwind continuam apenas exportação.

### 2. Nada é executado
O arquivo é lido como texto por `readTextFile`, com tipo `.json`/`application/json` e tamanho conferido antes da leitura, e interpretado apenas com `JSON.parse` via `analyzeJson` (que descarta BOM e calcula linha/coluna). Nunca há `eval`, `Function`, import dinâmico ou interpretação de código. Aliases `{caminho}` são resolvidos como dados, com limite de encadeamento e detecção de ciclos. O `label` de origem é o nome do arquivo truncado por `palettes.source_label_max_length`.

### 3. Limites vêm do servidor
`config/colors.php` (`colors.tokens.import`) entrega, via `ColorsToolProps::tokenImportLimits()`:

| Limite | Default |
|---|---|
| tamanho do arquivo | 512 KiB |
| tokens/nós | 5000 |
| profundidade da árvore | 40 |
| profundidade de referências | 10 |
| diagnósticos exibidos | 20 |

Nenhum desses números é fixado no cliente. A travessia é iterativa (pilha), conta nós e profundidade, e chaves proibidas (`__proto__`, `constructor`, `prototype`) são recusadas.

### 4. Seleção determinística de paleta
- Um grupo com os 11 tons vira **escala**.
- Um grupo com 5/7/9/11 cores fora de `semantic` vira **extraída**.
- Dois candidatos válidos (`ambiguous_palette`), contagem inválida (`unsupported_color_count`), tons faltando (`missing_tones`) ou nenhum grupo (`no_colors`) viram erro.

Nunca “pega o primeiro”: a escolha precisa ser inequívoca. Cores fora do grupo escolhido não bloqueiam a importação; são avisos quando relevantes.

### 5. Papéis semânticos só quando completos
Os papéis são lidos apenas da estrutura exportada `semantic.{light,dark}.{papel}` com os 8 papéis × 2 temas em HEX6/HEX8. Bloco incompleto gera `semantic_incomplete` (aviso) e os papéis atuais são mantidos. No editor, papéis importados completos substituem a cópia editável e entram no próximo **Salvar**; sem papéis no arquivo, os atuais permanecem.

### 6. Metadados validados, nunca presumidos
- `formatVersion` presente e diferente de `COLOR_TOKEN_FORMAT_VERSION` (1) é erro.
- `algorithmVersion` diferente vira aviso (`algorithm_version_mismatch`); a escala é regravada com a versão atual porque as cores são literais.
- `base` vem do JSON nativo ou do DTCG (`$extensions["devtools.colors"].base`); Style Dictionary não tem slot e usa o tom 500; a extraída usa o tom 500 quando existe, senão a primeira cor.
- `clippedTones` é preservado.

### 7. Diagnóstico antes de mutação; aplicação explícita
O diálogo só lê e valida. Ele mostra formato, tipo, base, cores, papéis, nome e os diagnósticos (erros primeiro; no máximo `maxDiagnostics`, com contagem dos ocultos). O botão **Aplicar** só habilita quando não há erro. O workspace e o editor só mudam no clique; importar não salva — persistir continua sendo o botão **Salvar**. Erro nunca descarta a paleta atual.

### 8. Assinatura do snapshot e rascunho estendido
`paletteSignature()` identifica tipo + base + tons/cores. Os papéis importados valem enquanto a assinatura bater: trocar a base, aplicar preset ou editar a extração os descarta. O rascunho anônimo (`PaletteDraft`) ganha `scale?` e `roles?` opcionais, validados de forma tolerante — campo inválido é descartado sem invalidar o rascunho —, preservando uma escala importada e suas semânticas através do login. Isso **emenda o §7 do ADR-123** sem transformar rascunho em paleta salva e mantém TTL de 24 h e a ausência de imagem/URL completa.

### 9. Privacidade e fixtures
O conteúdo do arquivo não sai do navegador; não há endpoint de importação. `tests/Fixtures/colors/tokens/**` são compartilhados por Vitest e Pest; o Pest valida os payloads esperados contra `PaletteRules`, sem reimplementar o parser em PHP.

## Consequências
- O usuário consegue fechar o ciclo exportar → editar fora → importar, comparando base, 11 tons e papéis — não bytes.
- Arquivos hostis ou quebrados não travam a UI, não executam código e não apagam o estado atual.
- A seleção determinística evita importações surpresa em arquivos com várias paletas.
- O formato nativo, o DTCG e o Style Dictionary têm regras de base diferentes, documentadas; Style Dictionary não preserva a base pela ausência de slot.
- O rascunho cresce um pouco (escala + papéis), mas continua com leitura tolerante e TTL curto.
- A importação não substitui o salvamento: é uma edição de estado, não uma ação de conta.

## Alternativas rejeitadas
- **Aceitar qualquer JSON e pegar o primeiro grupo**: resultados imprevisíveis e perda silenciosa de dados.
- **Interpretar TypeScript/CSS na importação**: exigiria avaliação de código, contrariando o requisito de não executar nada.
- **Importar no servidor**: o arquivo sairia do navegador e criaria um endpoint de alto risco; a validação é local.
- **Aplicar automaticamente ao escolher o arquivo**: o usuário deve ver o diagnóstico e confirmar; cancelar precisa preservar o estado.
- **Exigir todos os metadados**: arquivos exportados por terceiros podem não ter `base`/`algorithmVersion`; tratar como aviso ou fallback é mais útil.
- **Mesclar papéis incompletos com os atuais**: mistura origens e esconde qual veio do arquivo; bloco incompleto é aviso e não é aplicado.
- **Reimplementar o parser em PHP para validar fixtures**: duas implementações divergiriam; o Pest valida o payload esperado contra as regras do servidor.
