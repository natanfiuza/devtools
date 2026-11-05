# ADR-081: Badges pessoais e PAT por badge

Status: Aceito
Data: 2026-11-03
Relacionados: ADR-002 (processamento local), ADR-064 (badges pessoais), ADR-068 (acesso a providers), ADR-069 (refresh), ADR-072 (badge como imagem), ADR-076 (cache e endpoint stateless), ADR-080 (camada de providers)

## Contexto

A ADR-064 registrou o contorno de badges pessoais — só pagos criam, URL
`/b/{username}/{ulid}.svg`, todas públicas — e a ADR-068 a regra de
credencial: item público lido anonimamente, item privado com PAT do usuário
pagador, cifrado e vinculado ao badge. Faltava decidir como isso vira
tabela, rota, arquivo e ciclo de vida.

As perguntas abertas eram:

1. **Onde ficam os arquivos e o que o banco guarda** — o SPEC previa
   `storage/app/data/tools/badgeforge/{username}/{ulid}.svg` e uma coluna
   `source_path`. Essa forma colide com `static/` e `png/` do cache do
   renderer e permitiria traversal se o username viesse cru.
2. **O SPEC previa `user_id` nullable e `slug`** — não existe badge anônima
   e não existe alias público: a URL já é username + ULID.
3. **Como atualizar sem sessão** — a imagem é stateless, então
   `?refresh=1` na URL pública não pode ser a forma de atualizar.
4. **O que acontece no downgrade** — quem era pago e deixou de ser precisa
   continuar servindo e gerenciando o que já criou.

## Decisão

### 1. Schema (desvio consciente do SPEC)

`badge_assets`: `id` ULID, **`user_id` obrigatório** com FK em cascata,
`name`, `type` (`static|provider`), `private`, `provider_slug`,
`definition_slug`, `params` (JSON), `form` (JSON — snapshot cosmético),
`credential` (texto cifrado), `credential_username`,
`credential_fingerprint`, `credential_set_at`, `svg_path`, `png_path`,
`etag`, `bytes`, `last_generated_at`, `expires_at`, timestamps; único por
`(user_id, name)` e índice `(user_id, created_at)`.

Não há coluna `slug`: o identificador público é o ULID. O `source_path` do
SPEC corresponde a `svg_path`/`png_path`. Esses desvios ficam registrados
aqui e no SPEC §7.

F3/F4 anteriores (`badge_custom_logos`, `badge_presets`) continuam como estão.
As três migrações são aditivas e retrocompatíveis (o deploy roda
`migrate --force` com o site no ar).

### 2. URL e storage

Rota pública em `routes/public.php`, registrada fora do grupo `web`:
`GET /b/{username}/{badgeAsset}.{svg|png}`, nomes `badges.personal`. Sem
sessão, sem CSRF, sem auth; o controller resolve o ULID **junto do username
exato** do dono. Username errado, badge excluída ou banco fora → corpo vazio
`404` direto, nunca a página Inertia de erro (uma URL de imagem não pode
devolver HTML).

Arquivos sob `storage/app/data/tools/badgeforge/personal/{username}/{ulid}.{svg|png}`.
O banco guarda o caminho **relativo** (`personal/...`), não absoluto; toda
leitura confere o prefixo `personal/` antes de tocar no disco. A escrita é
atômica (tmp + rename), o mesmo padrão do `PngCache`. O subdiretório `personal/`
evita colisão com `static/` e `png/` e o username é validado
(`\A[A-Za-z0-9_][A-Za-z0-9._-]{0,39}\z`, nunca `.`/`..`).

### 3. PAT: cifrado, por badge, fora da URL

- `credential` usa o cast `encrypted` (chave `APP_KEY`); o cast já entrega
  `DecryptException` quando a chave mudou.
- O PAT é da badge, não um cofre da conta. Trocar a credencial recalcula o
  HMAC, apaga SVG/PNG e força nova geração.
- A URL pública leva somente username + ULID. O token nunca aparece em URL,
  log, props, flash, mensagem de erro, cache ou exportação. Os FormRequests
  declaram `$dontFlash = ['credential']`; a UI limpa o campo ao fechar.
- `credential_fingerprint` = HMAC-SHA256 do token com `APP_KEY`, prefixado
  pelo provider; é o que entra na chave de cache (ADR-080). A coluna é
  `hidden` junto com `credential`.
- `DecryptException` no serving nunca vira 500: o renderer tenta sem
  credencial, usa o último arquivo bom (stale) ou fallback.
- Docker Hub exige `credential_username` (non-secret) no corpo; GitHub e npm
  usam Bearer. Packagist e npm `dm` têm `supports_private = false` e recusam
  `private=1` com `private_unsupported`; uma credencial com `private=0` é
  recusada com `credential_not_allowed`.
- Não há "remover credencial" sem recriar: uma badge privada nunca fica
  órfã de segredo; revogar é excluir a badge.

### 4. Ciclo de vida

- **Criar** (`paid_only`): static grava o snapshot normalizado e renderiza o
  SVG inicial; provider grava provider/params/credencial **sem fetch** — o
  primeiro `GET` público gera a imagem, então um provider fora do ar não
  bloqueia a criação.
- **Renomear**: só `name`; provider/params/form são imutáveis (recriar é a
  saída).
- **Atualizar**: `POST /badgeforge/badges/{id}/refresh` autenticado do dono,
  com `throttle:badgeforge-refresh`; responde `{url, pngUrl, generatedAt}`
  para a UI cache-bustar localmente. `?refresh=1` na URL pública é ignorado —
  é a diferença entre a ADR-069 (badge própria com sessão) e a imagem
  stateless.
- **Excluir**: apaga linha e arquivos; a URL antiga passa a 404.
- **Downgrade**: as badges existentes continuam servindo, aparecem na conta
  e podem ser copiadas/excluídas; criar, renomear, trocar PAT e refresh
  respondem `422 paid_only`. Nenhuma badge some por causa de plano.
- **Prune**: `badgeforge:prune` remove arquivos sob `personal/` que não são
  referenciados por nenhuma linha e têm mtime acima de
  `prune_after_hours`, com `--dry-run`; nunca apaga arquivo referenciado.
- **Exportação/anonimização**: exporta metadados (nome, tipo, provider,
  definition, params, URLs, datas) **sem** `credential` nem fingerprint; a
  anonimização apaga arquivos e linhas.

### 5. Renderização stateless

`PersonalBadgeController` serve o arquivo em disco quando `expires_at` está no
futuro; senão regenera sob lock por asset, regrava e serve. Se a regeneração
falhar e houver arquivo anterior, serve o anterior; sem arquivo, renderiza
fallback e grava com `expires_at = now + fallback_ttl`. `ETag`/`304`,
`Cache-Control: public, max-age=min(provider TTL, max_cache_seconds)` e
`download=1` são os mesmos da badge estática, via `BadgeImageResponder`. Um
`Warning: 110` sinaliza stale.

## Consequências

### Positivas

- A URL pública carrega só o ULID, nunca credencial; o caminho do arquivo é
  congelado na criação, então mudar o username não quebra o armazenamento.
- Vazamento de um PAT atinge um badge, não a plataforma.
- Downgrade não destrói dado do usuário.
- O banco fora do ar não derruba READMEs: a imagem responde 404 direto (não
  HTML) ou serve o arquivo em disco.

### Negativas / Trade-offs

- Trocar `APP_KEY` invalida os PATs (não os logos nem os badges estáticas);
  o fluxo é re-credencializar, documentado no RUNBOOK e na página pública.
- CDN pode segurar uma badge excluída até o teto de 1 h; por isso
  `max_cache_seconds` não acompanha o teto de 86400 da estática.
- Provider privado é síncrono no primeiro acesso; um provider lento aparece
  como latência da imagem até o fallback.
- Não há rota para remover credencial mantendo a badge; recriar é a saída.

## Alternativas descartadas

- **Badge anônima (`user_id` nullable) e coluna `slug`**: não há caso de uso;
  `user_id` obrigatório e ULID como identificador é mais simples e mais
  seguro.
- **`storage/.../personal/{username}/{ulid}` na raiz**: colidiria com
  `static/`/`png/` e permitiria traversal com username cru.
- **Token em query string para o provider**: apareceria em access log,
  histórico e CDN. Recusado pela ADR-068.
- **`?refresh=1` público para atualizar badge pessoal**: qualquer leitor
  poderia forçar fetch externo. Refresh é POST autenticado do dono.
- **Bloquear badges no downgrade**: tiraria do usuário o que ele já publicou;
  a regra é servir e permitir copiar/excluir, bloquear só a edição.
