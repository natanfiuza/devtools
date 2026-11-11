# ADR-123 - Persistência de paletas
Status: Aceito
Data: 2026-11-11
Relacionados: ADR-002 (processamento local), ADR-096 (limites, emenda), ADR-101/ADR-120 (escala de 11 tons), ADR-103 (tags, emenda), ADR-104 (Minhas Paletas, emenda), ADR-119 (arquivamento, emenda), ADR-122 (extração, emenda), ADR-124 (página pública), ADR-126 (papéis e protótipos)

## Contexto
As Fases 1–3 rodaram 100% no navegador e sem persistência. A F4 transforma o gerador em produto: contas salvam, organizam, publicam e prototipam paletas. Era preciso decidir **o que** é gravado de uma paleta gerada no cliente — e como ela reabre idêntica —, como a cota Free convive com arquivamento e exclusão, como as tags viram entidade, e o que acontece com o que a pessoa digitou quando ela ainda não tem conta.

As regras do projeto continuam valendo: nenhum limite numérico no código (tudo em `config/colors.php` com override por `.env`), toda string via i18n nos cinco idiomas, e nenhum conteúdo de paleta em log.

## Decisão

### 1. Tabelas aditivas, sem tocar no que existe

Três migrations novas e nenhuma alteração em tabelas existentes:

- `color_palettes`: `id` ULID, `user_id` (cascade), `duplicated_from_id` (self-FK, `nullOnDelete`), `name` (80), `description` (500), `visibility` (`private|public`), `kind` (`scale|extracted`), `algorithm_version`, `colors` (JSON), `roles` (JSON), `source` (JSON nullable), `colors_count`, `duplications_count` (default 0), `slug` (60, unique global), `archived_slug`, `archived_at`, `og_version` (nullable), timestamps.
- `color_tags`: `id`, `user_id`, `name` (30), `key` (normalização `lower(squish(name))`), `position`; unique `(user_id, key)`.
- Pivô `color_palette_color_tag`: ULIDs com cascade, PK composta e **sem posição** — a ordem das tags é a global do usuário.

Índices cobrem a lista (`user_id` com `archived_at`, `updated_at`, `created_at`, `name`, `colors_count`, `duplications_count`) e o sitemap (`visibility, archived_at, updated_at`). `colors_count` e `duplications_count` são colunas denormalizadas justamente para ordenar sem ordenar JSON.

### 2. Cores efetivas, nunca recalculadas

O que a tela mostra é o que o banco guarda:

- `colors` = `{ base, items[] }`; na escala, os 11 itens com `{hex, tone}`; na extraída, os 5/7/9/11 itens com `{hex}`, na ordem de peso. `colors_count = items.length`.
- HEX6 ou HEX8, alpha preservado; a base é sempre um dos itens da extraída.
- `algorithm_version` registra quem produziu (escala ADR-120 = 1, extração ADR-122 = 1) e é **informativo**: abrir a paleta reconstrói a escala a partir dos HEX gravados (`frozenScale`), sem chamar o algoritmo. A primeira troca de base descongela e recalcula, como no gerador.
- `roles` = `{ light: {8 papéis}, dark: {8 papéis} }`, persistido. Os defaults são calculados no cliente (ADR-126) e gravados junto; editar papéis não desloca cores.
- `source` existe só na extraída e guarda **apenas** `{kind:'file',label}` ou `{kind:'url',host}` — nunca a URL completa, o blob ou a imagem.

O servidor valida o shape por `kind` (`PaletteRules`): contagem permitida, base presente, `tone` só na escala, `source` proibida na escala e `roles` com os oito papéis nos dois temas.

### 3. Cota sob lock da linha do usuário

`PaletteQuota::used()` conta **todas** as paletas do usuário, inclusive arquivadas; `limit()` é `colors.palettes.free_limit` (25) para Free e `paid_limit` (null = ilimitado) para pago. Criar e duplicar rodam em `DB::transaction` com `User::lockForUpdate()` antes da contagem — o padrão de `StoreCustomLogo` — e respondem com erro de validação no campo `palette` ao estourar. Sem o lock, duas abas simultâneas passariam do teto; o teste de concorrência real em MySQL é opt-in, e a suíte normal cobre o limite sequencial (25 → 26ª recusada, arquivadas contando, excluir liberando).

### 4. Arquivamento e exclusão são ações distintas

- **Arquivar** (ADR-119): `archived_slug = slug; slug = null; archived_at = now()`, em transação. A URL pública passa a 404, o slug fica livre para outra paleta e a paleta continua na cota e restaurável. Restaurar reivindica `archived_slug` se ainda estiver livre (`claimOrGenerate`); se não, gera outro slug do nome atual.
- **Excluir**: permanente, exige o nome exato no corpo (`confirmName === name`); apaga a linha e depois o arquivo OG. Falha ao apagar arquivo é logada só com o id e **não** desfaz a exclusão. Libera a cota e o slug.
- Paleta arquivada é somente leitura: editar devolve erro de validação até restaurar; arquivar é idempotente.

### 5. Slug nasce na publicação e não muda ao renomear

`Str::slug` limitado a `slug.max_length` (60), fallback `palette`, reservados (`p`, `og`, `create`, `dashboard`, `colors`, `tools` etc. em config) recebem o sufixo `-palette`, e colisão tenta `-2..-5`. A unique index é a garantia final: criação/restauração repetem a transação (até 3 vezes) ao capturar violação. Publicar uma paleta privada gera o slug; renomear **não** o altera; despublicar mantém o slug (a visibilidade é que responde 404).

### 6. Tags como entidade do usuário

A tag deixa de ser string solta (ADR-103, emenda): `color_tags` pertence ao usuário, o mesmo nome é a mesma tag (`key` normalizada), e o pivô liga muitas paletas à mesma tag. Renomear reflete em todas as paletas; reordenar é por usuário e vale para a lista. Limites: `tags.per_palette` (10) e `tags.name_max_length` (30); tag que passa do teto da paleta é recusada na validação, e duplicar faz upsert nas tags **do duplicador**.

### 7. Rascunho anônimo em `sessionStorage`, nunca paleta anônima

Quem salvou sem conta não perde o trabalho: o cliente guarda `colors.palette.draft.v1` em `sessionStorage` (base, tipo, cores extraídas, aba), com TTL de 24 h, e navega ao intent de criar; depois do login/cadastro o modal reabre com o rascunho. Nunca se guarda imagem, URL completa, papéis ou metadados, e o rascunho some após salvar ou expirar. Não existe "paleta anônima" no banco — a cota e o slug pressupõem dono.

### 8. Ações de conta são a exceção documentada ao "nada ao servidor"

Gerar, converter, harmonizar e extrair continuam **100% no navegador**. Salvar, listar, publicar, duplicar e enviar a OG são ações de conta explícitas: só rodam quando a pessoa pede, sobre os dados que ela escolheu salvar, e exigem sessão. Nenhum dado de paleta vai ao servidor por navegação ou por ferramenta em si.

## Consequências
- A paleta reabre idêntica ao que foi salvo, mesmo que o algoritmo mude de versão; a versão fica registrada para auditoria e reescolha.
- A cota é honesta: arquivar não burla o Free, e o lock impede corrida entre abas e entre contas.
- Arquivar e excluir têm efeitos distintos e revertibilidade clara; a URL pública nunca "vaza" paleta arquivada.
- O usuário organiza com tags de verdade — renomear alcança tudo — e o rascunho remove a fricção do login sem criar um modo anônimo de persistência.
- O custo é schema novo, actions transacionais, validação de shape duplicada no cliente e no servidor, e um teste de concorrência opt-in em MySQL.

## Alternativas rejeitadas
- **Guardar só a base e recalcular ao abrir**: mudaria paletas antigas quando o algoritmo evoluísse; a F4 exige fidelidade.
- **Contar só paletas ativas na cota**: incentivaria arquivar para burlar o Free (ADR-096).
- **Soft delete como "arquivar"**: misturava ocultação com exclusão (ADR-119).
- **Tags como texto no JSON da paleta**: não permitiria renomear em massa nem ordenar por usuário (ADR-103).
- **Paleta anônima no backend**: cota, slug e dono não fariam sentido; o rascunho local cobre a intenção.
- **Sincronizar a escala no servidor**: contraria o processamento local e não há necessidade — o cliente já tem o resultado.
