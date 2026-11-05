# ADR-086: BadgeForge F7 — analytics das imagens com dono

Status: Aceito
Data: 2026-11-04
Relacionados: ADR-063 (substituido), ADR-081 (badges pessoais), ADR-083 (endpoint JSON), ADR-087 (providers customizados)

## Contexto

O ADR-063 deixou no papel `badge_asset_views`: "uma linha por acesso da badge
pessoal". A F7 do SPEC §16 pede analytics completo, e três fatos mudaram o
desenho original:

1. **Há três tipos de imagem com dono** — pessoal (`/b/...`), endpoint JSON
   (`/api/badges/endpoint/{ulid}`) e provider customizado
   (`/api/badges/custom/{ulid}/...`) —, não só a badge pessoal.
2. O endpoint e o provider customizado já respondem **304** quando o ETag não
   mudou; um acesso que termina em 304 continua sendo visualização.
3. Guardar IP e User-Agent crus é desnecessário e arriscado; o painel precisa
   de visitante único, não de identificar a pessoa.

## Decisão

### 1. Duas tabelas polimórficas, com `definition`

- `badge_views` (bruto): `subject_type` (`personal|endpoint|custom`),
  `subject_id` (ULID), `user_id`, `definition` (nulo em pessoal/endpoint,
  preenchido em custom), `ip_hash`, `user_agent_hash`, `user_agent` truncado,
  `referer_domain`, `country`, `format`, `style`, `cache_hit`, `viewed_at`.
- `badge_view_dailies` (agregado): mesmas dimensões de sujeito + `day`,
  `views`, `unique_visitors`, `cache_hits`.

`definition` entra nas duas tabelas porque um provider customizado tem várias
métricas no mesmo ULID; sem ela o painel misturaria `stars` e `forks`. Em
pessoal e endpoint a coluna fica nula e é agregada como string vazia nas
dailies (NULL quebraria o `unique` no MySQL).

O ADR-063 fica substituído: a tabela `badge_asset_views` nunca foi criada.

### 2. Só imagem com dono; 200 e 304 contam

Gravam `PersonalBadgeController`, `EndpointBadgeController` e
`CustomProviderBadgeController`. Ficam de fora `StaticBadgeController` e
`ProviderBadgeController` — não têm dono e o volume global deles é do catálogo,
não de uma conta.

O registro é **síncrono e best-effort** na própria requisição, dentro de
`try/catch(Throwable)`; falha de analytics (banco fora, migração pendente,
GeoIP ilegível) nunca impede a imagem. Conta **200 e 304**, uma linha por
acesso, sem deduplicação por sessão. Visitante único é
`COUNT(DISTINCT ip_hash)`; `cache_hit` é `null` na badge pessoal (o arquivo é
estático por geração) e `true` quando o endpoint/custom respondeu de HIT/STALE.

### 3. Privacidade por construção

- `ip_hash` e `user_agent_hash` são HMAC-SHA256 com `APP_KEY`, o mesmo padrão
  do `fingerprint` de credencial: irreversível sem a chave.
- `user_agent` cru é truncado em `analytics.user_agent_max_length` (512) e
  nunca é exportado.
- `referer_domain` guarda só o host em minúsculas; ausência vira `null`.
- `country` vem do **GeoLite2 local opcional** (`maxmind-db/reader`, caminho em
  `analytics.geoip_path`). Arquivo ausente, corrompido, extensão sem suporte ou
  IP privado devolvem `null`; nada mais muda na imagem.
- O CSV do dono não tem `ip_hash`, `user_agent_hash` nem `user_agent`.

### 4. Janelas por plano

| Plano | Janela | O que vê |
|---|---|---|
| Free / cadastrado | 30 dias fixos | totais e únicos por imagem + totais da conta |
| Pago | 7, 30 ou 90 dias | série diária, top referrers, países, formatos, taxa de cache, tabela por sujeito e CSV |
| Admin | 7, 30, 90 ou 400 dias | volume global a partir das dailies |

O parâmetro de janela nunca gera 422: valor fora da lista cai na janela padrão.
A série do dono lê o **bruto** (até 90 dias); o admin lê o **agregado diário**
(até 400), e a tela avisa que únicos não são somáveis entre dias/dimensões.

### 5. Retenção e agregação

- Bruto: 90 dias (`analytics.raw_retention_days`).
- Agregado diário: 400 dias (`analytics.daily_retention_days`).
- `badgeforge:analytics:aggregate` (`13 * * * *`) reconstrói os últimos 7 dias:
  por dia, apaga as dailies daquele dia e reinsere o `GROUP BY` do bruto. É
  idempotente e tolera execução repetida.
- `badgeforge:analytics:prune` (`17 4 * * *`) remove bruto e dailies fora da
  retenção (`--dry-run` disponível). A agregação roda antes da poda, então o
  dia prestes a expirar já está agregado.
- Os dois comandos ficam em `task_schedules` por `insertOrIgnore`, como os
  demais agendamentos do projeto; dependem do worker.

### 6. Só web e CSV (sem API JSON)

A F7 não expõe endpoint JSON de analytics. O dono lê na própria
`/dashboard/badgeforge` e exporta por `GET /badgeforge/analytics/export`, com
gate pago (403 no Free). O CSV é streamado por chunk e tem as colunas
`viewed_at`, `subject_name`, `subject_type`, `definition`, `format`, `style`,
`cache`, `country`, `referer_domain`.

## Consequências

### Positivas

- Um modelo só serve os três tipos de imagem e o painel não precisa de tabela
  por feature.
- A imagem nunca cai por causa de analytics, e 304 continua contando.
- Nenhum dado pessoal cru é persistido; o CSV é seguro para compartilhar.
- O bruto serve o dono com precisão e o agregado sustenta 400 dias sem crescer
  sem limite.

### Negativas / Trade-offs

- Um `INSERT` síncrono no caminho da imagem; o `try/catch` protege a resposta,
  mas o custo existe e é contido por índice e rate limit.
- A agregação horária pode rodar duas vezes seguidas sem erro justamente porque
  apaga e reinsere; leitura concorrente do dia em curso pode ver o intervalo
  vazio por milissegundos.
- Únicos do admin são por dia/dimensão: não há como somar visitantes entre
  dias sem contar a mesma pessoa mais de uma vez.
- Pais depende de um arquivo GeoIP opcional; sem ele, a dimensão fica vazia.

## Alternativas descartadas

- **Uma tabela por tipo de imagem**: triplicaria migrations, queries e painéis.
- **Assíncrono por fila**: o worker pode atrasar ou parar; a imagem não pode
  depender dele para analytics.
- **Deduplicar por sessão/cookie**: o badge é carregado por `<img>`, sem
  cookie próprio, e o objetivo é volume, não sessão.
- **Guardar IP cru**: não há necessidade de identificar a pessoa e aumenta o
  risco em dump/log.
- **GeoIP remoto**: adiciona dependência de rede no caminho da imagem.
- **API JSON de analytics na F7**: a superfície web + CSV cobre o caso e evita
  mais um contrato público antes da F8.
- **Ler o agregado para a série do dono**: perderia a precisão e a janela de
  90 dias do bruto.
