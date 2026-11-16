# Colors — índice da documentação

Track do grupo Colors do devtools: HTML Color Picker e Color Palette Generator.
Estado atual: **Fases 1 a 8 implementadas — track concluído**. A Fase 1 entregou o HTML Color Picker completo (área 2D, matiz, alpha e sliders RGB/RGBA e HSL/HSLA — a lacuna de canais foi fechada antes da F2). A **Fase 2** entregou o Color Palette Generator em `/{locale}/tools/colors/palette-generator`: escala determinística de 11 tons em OKLCH (ADR-120), contraste por tom, base intacta, cópia e exportações CSS/SCSS/JSON/Tailwind v3/v4. A **Fase 3** acrescentou as abas de harmonias — cinco tipos em OKLCH e HSL (ADR-121) — e de extração de 5/7/9/11 cores dominantes de imagem local ou URL HTTPS, em worker e com limites configuráveis (ADR-122). A **Fase 4** transformou o gerador em produto: paletas salvas com cota sob lock em `/dashboard/colors/palettes` (ADR-123), página pública com canonical/sitemap/OG dinâmica e duplicação (ADR-124, ADR-125), e protótipos vetoriais de site e mobile com papéis claro/escuro exportáveis em SVG e PNG 1x/2x (ADR-126). A **Fase 5** acrescentou a biblioteca pessoal de cores — histórico e favoritos da conta, sem limite funcional e compartilhados pelo Picker e pelo Generator, com o anônimo reduzido a 20 de histórico e 50 de favoritos e importação explícita para a conta (ADR-127, ADR-131, ADR-132) —, favoritos de paletas públicas sem duplicata (ADR-128), presets do gerador como entrada da geração (ADR-129) e exportação e importação validadas de tokens W3C DTCG, Style Dictionary e JSON nativo (ADR-130, ADR-133). A **Fase 6** abriu o social — curtir paleta pública inclusive anônimo por identidade first-party reivindicada no login (ADR-135), seguir contas e perfil público por username (ADR-134, ADR-136), notificações só no canal `database` (ADR-137) — e a exportação da paleta como imagem tipográfica horizontal, vertical e card (ADR-138). A **Fase 7** abriu a última frente de produto antes da auditoria final: comentários em thread de dois níveis nas paletas públicas, com selo "em análise", edição e exclusão com tombstone (ADR-139), moderação em dois estágios — filtro local por idioma e DeepSeek com cache por `purpose` e sentinela permanente (ADR-140) —, denúncias com deduplicação e fila administrativa (ADR-141), notificações com e-mail, sino, preferências e silenciar por paleta (ADR-142) e a flag `COLORS_COMMENTS_ENABLED` desligada por padrão (ADR-143). A **Fase 8** é a auditoria final do grupo: matriz requisito → implementação → teste sobre F1–F7, correção dos dois achados altos — bloco `colors` na exportação da conta (ADR-028) e limpeza dos dados pessoais da biblioteca e das relações de saída na anonimização —, pendências classificadas por gravidade e aceite em `docs/acceptance/COLORS-FASE-8.md` (ADR-144). Bloqueio entre contas segue não implementado. O processamento das ferramentas continua 100% no navegador; só as ações de conta falam com o servidor.

## Layout Harmonizer (feature nova, em F1)

O pacote `layout-harmonizer/` especifica uma nova tool do grupo: uma cor vira um sistema semântico de layout, com quadro nos temas claro e escuro, contraste AA/AAA, protótipos de site e mobile e exportações Markdown, CSS e ZIP. A **F0** (auditoria e plano, sem código) foi concluída em 2026-11-15. A tabela numérica de parâmetros foi aprovada em 2026-11-16 e a **F1** entregou o motor puro (catálogo de 33 tokens, quatro estratégias, gamut, alpha, contraste e fixtures) em `resources/js/tools/layout-harmonizer/engine/`, com a tabela versionada em `layout-harmonizer/PARAMETROS.md`. O relatório e o plano estão em `docs/claude-code/`, e o mapa de transferência dos 22 ADRs para `docs/adr/ADR-145-colors-lh-*` a `ADR-166-colors-lh-*` está em `layout-harmonizer/ADR-MAPEAMENTO.md`.

## Documentos

| Documento | Conteúdo |
|---|---|
| `SPEC-COLORS.md` | escopo geral, URLs, limites, fases F1–F8 e critérios de aceite (fonte de verdade) |
| `glossario.md` | termos do grupo Colors |
| `html-color-picker.md` | escopo da tool HTML Color Picker |
| `palette-generator.md` | escopo do Color Palette Generator |
| `import-export.md` | importação de imagem/JSON e exportação |
| `export-formats.md` | CSS, Tailwind, W3C DTCG e Style Dictionary |
| `minhas-paletas.md` | dashboard de paletas salvas |
| `paleta-publica.md` | página pública da paleta, SEO e OG |
| `perfil-publico.md` | perfil público por username, visibilidade e SEO |
| `og-image.md` | OG estática de tool versus OG dinâmica de paleta |
| `moderacao.md` | moderação de comentários |
| `social.md` | curtidas, comentários, follows e notificações |
| `layout-harmonizer/` | nova feature do grupo: especificação, 33 tokens, contrato técnico, dados, exportações, testes, design, parâmetros versionados, 22 ADRs (10 já em `docs/adr/`) e o mapa de transferência |
| `PROMPT-INICIAL-CLAUDE-CODE.md` | prompt da Fase 1 |
| `PROMPT-Fase2-gerador_paletas.md` … `PROMPT-Fase8-auditoria-final.md` | prompts das fases seguintes |
| `docs/adr/ADR-0NN-colors-*.md` | decisões do grupo |

## ADRs de Colors

Numeração vigente: **ADR-091 a ADR-144**, em `docs/adr/` com o prefixo `colors` no nome do arquivo (ex.: `ADR-091-colors-espacos-de-cor.md`), como o BadgeForge.
Os ADRs do Layout Harmonizer (**ADR-145 a ADR-166**, `ADR-XXX-colors-lh-<escopo>.md`) entram em `docs/adr/` um por fase, conforme `layout-harmonizer/ADR-MAPEAMENTO.md`. A F1 transferiu 145, 146, 147, 149, 150, 156, 157, 158, 163 e 166; os demais seguem locais em `layout-harmonizer/adr/` até a fase correspondente.
Os números 057–086 que a especificação original citava já estavam ocupados no repositório (`docs/adr/`), por isso os 25 ADRs de Colors foram renumerados em 2026-11-07, sem mudar o conteúdo além das emendas da Fase 1.

| antigo | novo | | antigo | novo | | antigo | novo |
|---|---|---|---|---|---|---|---|
| 057 | 091 | | 063 | 097 | | 077 | 106 |
| 058 | 092 | | 064 | 098 | | 078 | 107 |
| 059 | 093 | | 065 | 099 | | 079 | 108 |
| 060 | 094 | | 066 | 100 | | 080 | 109 |
| 061 | 095 | | 067 | 101 | | 081 | 110 |
| 062 | 096 | | 068 | 102 | | 082 | 111 |
|  |  | | 069 | 103 | | 083 | 112 |
|  |  | | 070 | 104 | | 084 | 113 |
|  |  | | 076 | 105 | | 085 | 114 |
|  |  | |  |  | | 086 | 115 |

ADRs do grupo:

| ADR | Tema |
|---|---|
| 116 | grupo de tools na URL |
| 117 | núcleo de cor próprio e gamut |
| 118 | persistência local do picker |
| 119 | arquivamento de paleta |
| 120 | algoritmo da escala de 11 tons (escada OKLCH literal, croma relativo e base intacta) |
| 121 | harmonias (ângulos, monocromática relativa, OKLCH padrão, auxiliares fora dos exports) |
| 122 | extração de cores de imagem local/URL (color-thief/MMCQ, worker, limites, CORS sem proxy, memória) |
| 123 | persistência de paletas (schema, cores efetivas, cota sob lock, arquivamento × exclusão, tags, rascunho, slug) |
| 124 | página pública, canonical/SEO/sitemap, duplicação e “uso” |
| 125 | OG dinâmica (cliente, upload validado, storage, `og_version`, serving) |
| 126 | protótipos vetoriais (papéis/temas, renderer SVG, PNG do mesmo SVG, fidelidade) |
| 127 | biblioteca pessoal de cores (histórico e favoritos da conta, chave canônica, sem limite funcional) |
| 128 | favoritos de paletas públicas (vínculo sem cópia, 403/404, aba Favoritas) |
| 129 | presets como entrada da geração (nome + base, sem cota de paleta) |
| 130 | exportação de tokens v1 (primitivas + semânticas, versão, alpha/gamut, DTCG/SD/TS) |
| 131 | biblioteca de cores compartilhada entre Picker e Generator (conta × local, escrita otimista) |
| 132 | histórico anônimo reduzido (20/50) e importação explícita do histórico local |
| 133 | importação validada de tokens (parser puro, limites, diagnóstico, rascunho estendido) |
| 134 | perfil público por username (404 uniforme, paletas públicas, contadores, SEO fora do sitemap) |
| 135 | curtidas e identidade anônima (linha permanente, cookie first-party, claim no login, API sem identificador) |
| 136 | seguir contas (linha permanente por par, login, 404 uniforme, contagem ativa) |
| 137 | notificações no canal database (AfterCommit, `notified_at` atômico, sem PII) |
| 138 | imagem tipográfica da paleta (três formatos fixos, marca fixa, SVG sanitizado, fonte só na exportação) |
| 139 | comentários e threads (2 níveis no servidor, visibilidade com selo, `approved_body`, `revision`, tombstone) |
| 140 | moderação e cache (filtro local por n-gramas, DeepSeek, `purpose` + sentinela NULL, falha vira `flagged`) |
| 141 | denúncias (uma por par, motivo fixo, dedupe, resolução automática, sem notificação) |
| 142 | notificações e-mail/sino/silêncio (preferências por tipo, mute no `via()`, `notified_at`) |
| 143 | kill switch de comentários (`COLORS_COMMENTS_ENABLED`, 404 uniforme) |
| 144 | auditoria final (matriz de requisitos, exportação e anonimização de Colors, pendências deferidas) |

## Emendas de 2026-11-07

As decisões antigas foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-07":

- escala é sempre de 11 tons; 5/7/9/11 é só extração de imagem (ADR-092, ADR-099, ADR-101);
- OG estática em `public/og` via `seo:og-image`; OG dinâmica de paleta em `storage/app/data/og_paleta_image`, nunca versionada (ADR-105);
- Free 25 paletas; pago sem limite funcional (ADR-096);
- exportação Figma em W3C DTCG JSON, sem API do Figma (ADR-095);
- cache de moderação em `ai_cache` (ADR-110);
- curtida anônima por identificador assinado de primeira parte (ADR-112);
- threads de comentários em no máximo 2 níveis (ADR-111).

## Emendas de 2026-11-11 (Fase 4)

As decisões anteriores foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-11":

- rascunho anônimo em `sessionStorage` (distinto de "paleta anônima") e arquivadas contando na cota (ADR-096);
- slug global, 404 uniforme, canonical sem query e sitemap só com públicas ativas (ADR-098);
- protótipo vetorial: SVG é o formato canônico e o PNG rasteriza o mesmo SVG (ADR-100);
- tags como entidade por usuário, com `key` de comparação e reordenação da conta (ADR-103);
- Minhas Paletas sem prefixo de locale, com busca/filtros/ordenação no servidor (ADR-104, ADR-109);
- OG dinâmica: upload validado, `og_version`, invalidação visual e serving só para pública ativa (ADR-105);
- slug liberado no arquivamento e reivindicado na restauração quando livre (ADR-119);
- shape persistido da extração: cores efetivas, papéis e origem reduzida, sem re-quantizar (ADR-122).

## Emendas de 2026-11-14 (Fase 5)

As decisões anteriores foram preservadas; cada ADR afetado ganhou uma seção "Emenda 2026-11-14":

- preset é apenas nome + base, sem cores derivadas, papéis ou tema (ADR-093);
- exportação de tokens v1: primitivas + semânticas opcionais, versão do formato, alpha/gamut e metadados por formato (ADR-095);
- o anônimo continua só com `localStorage` (20 histórico/50 favoritos) e o rascunho pode carregar escala e papéis opcionais (ADR-096);
- histórico e favoritos locais continuam sendo o modo anônimo; para logado passam a vir da conta e o histórico anônimo cai para 20 (ADR-118);
- rascunho em `sessionStorage` ganha `scale?`/`roles?` com assinatura, sem virar paleta anônima (ADR-123 §7).

## Emendas de 2026-11-14 (Fase 6)

As decisões anteriores foram preservadas; cada ADR afetado ganhou uma seção
"Emenda 2026-11-14":

- metas de página resolvidas no servidor passam a cobrir o perfil público (ADR-033);
- a paleta pública mostra autor, curtida, seguir e imagem; comentários e
  denúncias continuam fora (ADR-098, ADR-124);
- o social entregue é curtir/seguir/perfil, com notificação só no banco; moderação
  e anti-spam além do throttle seguem pendentes (ADR-106);
- a imagem da paleta é implementada nos três formatos, no cliente e sem
  referência externa (ADR-107, ADR-115);
- identidade anônima first-party, reivindicação no login e dedupe transacional
  ficam implementados (ADR-112);
- notificações passam a ser só `database`, uma por relação, sem e-mail/sino
  (ADR-113);
- a imagem reusa o serializador do protótipo, mas não o catálogo de medidas nem o
  tipo de documento (ADR-126);
- favorito e curtida são relações distintas que coexistem (ADR-128).

## Emendas de 2026-11-15 (Fase 7)

As decisões anteriores foram preservadas; cada ADR afetado ganhou uma seção
"Emenda 2026-11-15":

- o social fecha o grupo com comentários, moderação, denúncia e notificações
  completas; anti-spam segue só com throttle e filtro de palavras (ADR-106);
- "permanente" no cache vira `expires_at = NULL`, o cache ganha `purpose` e o
  filtro local roda antes da IA (ADR-110);
- thread de 2 níveis implementada, com selo "em análise" e `approved_body` para
  reverter edição rejeitada (ADR-111);
- `database` continua sempre ligado e o `mail` entra por preferência, com mute
  por paleta avaliado no envio (ADR-113);
- denúncia com motivo fixo, dedupe e resolução automática ao rejeitar/excluir
  (ADR-114);
- o formato gravado em `notifications` não muda; a F7 só acrescenta remetentes
  e leitores (ADR-137).

## Emendas de 2026-11-15 (Fase 8)

A auditoria final corrigiu o que a F7 deixou em aberto; as decisões anteriores
foram preservadas e a ADR-144 registra o resultado:

- a exportação da conta (ADR-028) passa a ter o bloco `colors`: paletas, tags,
  histórico, favoritos, presets, favoritos de paleta, curtidas ativas,
  follows de saída, comentários (inclusive excluídos), denúncias feitas,
  preferências e paletas silenciadas — sem `moderation_reason`, sem
  `notified_at` e sem seguidores (ação de outra conta);
- a anonimização apaga histórico, favoritos de cor, presets, favoritos de
  paleta, curtidas da conta, follows de saída e tags; paletas, comentários e
  denúncias ficam com autoria `NULL`, porque apagar paleta destruiria dados de
  outras contas pelas FKs em cascata;
- `COLORS_COMMENTS_ENABLED` continua `false` em produção; ligar é decisão
  operacional documentada na §11 do RUNBOOK, não uma mudança de código;
- pendências médias/baixas (TTL do cache de moderação, exportação sem teto
  para conta paga, `lockForUpdate` no MySQL, worker obrigatório) ficam
  classificadas no aceite e na ADR-144, sem correção nesta fase.

## Regras de manutenção

- Arquivo novo só entra aqui depois de ser listado neste índice.
- Decisão nova relevante vira ADR em `docs/adr/ADR-0NN-colors-<slug>.md` sem reutilizar número ocupado.
- Toda correção de decisão anterior é emenda datada, não reescrita silenciosa.
