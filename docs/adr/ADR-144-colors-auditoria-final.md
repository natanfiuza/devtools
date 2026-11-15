# ADR-144 - Auditoria final e aceite do grupo Colors
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-028 (exportação de dados da conta), ADR-091 a ADR-143 (grupo Colors), ADR-090 (molde do aceite e do relatório de fase)

## Contexto

As Fases 1 a 7 do grupo Colors estão implementadas na branch
`feature/colors-fase-8`: duas tools 100% no navegador (HTML Color Picker e
Palette Generator), catálogo, biblioteca pessoal, paletas salvas e públicas,
protótipos, imagem tipográfica, social anônimo, comentários com moderação por
IA, denúncias, notificações e admin. A Fase 8 fecha o track: auditar o conjunto
inteiro contra `docs/colors/SPEC-COLORS.md` v1.5 e o contrato
`docs/colors/PROMPT-Fase8-auditoria-final.md`, montar a matriz
requisito → implementação → teste, classificar pendências por gravidade,
corrigir só bloqueantes e altas, provar o funcionamento no navegador e publicar
aceite, relatório e esta ADR.

A auditoria (inventário das suítes, matriz por família de requisito e triagem
dos arquivos sem âncora de teste) encontrou duas lacunas de direito do titular
e dois defeitos altos, que já foram corrigidos nesta fase:

1. **`ExportUserData` não exportava nenhum dado de Colors**, embora o ADR-028
   dê ao titular o direito à exportação e o BadgeForge já a tivesse. O ponto
   foi deixado explicitamente pela F7 para esta auditoria.
2. **`AnonymizeUser` apagava preferências e mutes de Colors, mas mantinha
   histórico, favoritos de cor, presets, favoritos de paleta, curtidas, follows
   de saída e tags ligados à conta anonimizada**, além de manter `user_id` e
   `resolved_by` em comentários e denúncias.
3. **A aba de extração do Palette Generator quebrava nos dois idiomas
   portugueses**: `ExtractionPanel.vue` tinha um `formatBytes` local que
   chamava `new Intl.NumberFormat('pt_BR')`. Tag BCP 47 usa hífen, então
   `pt_BR`/`pt_PT` lançam `RangeError: Invalid language tag` e o painel inteiro
   deixa de renderizar.
4. **O sino de notificações mostrava `:actor` e `:palette` literais**: os
   textos de `notifications.kinds.*` usavam a interpolação do Laravel
   (`:nome`), mas são consumidos pelo `t()` do vue-i18n, que interpola com
   `{nome}`. O defeito passava silencioso porque o teste da página fingia as
   traduções já na sintaxe do vue-i18n.

## Decisão

### 1. Escopo da correção: só bloqueante e alta

O grill da fase decidiu corrigir apenas achados **bloqueantes e altos** —
requisito quebrado, direito do titular, vazamento ou perda de dado. Médios e
baixos entram classificados no aceite (`docs/acceptance/COLORS-FASE-8.md`) e
nesta ADR, com gravidade, impacto e ação, para decisão futura. Nenhuma mudança
estrutural fora do plano foi necessária.

### 2. Exportação da conta ganha o bloco `colors`

`ExportUserData` passa a devolver, entre `badgeforge` e `usage`, um bloco
`colors` com datas ISO 8601 e ordem determinística:

- `palettes` (metadados, cores, papéis, origem e contadores);
- `tags` (nome, chave, posição);
- `history` e `favorites` de cor;
- `presets` do gerador;
- `palette_favorites`;
- `likes` e `following` **somente ativos** (`liked_at`/`followed_at` não nulos);
- `comments` com `withTrashed()`, porque o texto continua sendo do autor;
- `reports_filed` (denúncias feitas pela conta);
- `notification_preferences` (defaults reais) e `muted_palettes`.

Ficam de fora, por decisão:

- `moderation_reason` — nota interna da moderação; não é dado do autor;
- `notified_at` — metadado operacional interno;
- **seguidores** — a linha é ação de outra conta (`follower_id` de terceiro);
  o titular recebe os follows que *ele* iniciou, não quem o segue;
- segredos e `anonymous_id` de visitante.

A leitura não derruba a exportação: `colors` e `roles` são casts `array`, que
devolvem `null` em vez de lançar quando o JSON está corrompido (mesmo espírito
da tolerância do `canonical_payload` do BadgeForge). A conta vazia devolve
arrays vazios, não `null`.

### 3. Anonimização apaga o pessoal e preserva o publicado

Dentro da transação existente, antes do `forceFill` da conta:

- apaga `color_history_entries`, `color_favorites`, `color_presets`,
  `color_palette_favorites`, `color_tags` e as curtidas e follows **de saída**
  (`follower_id` da conta);
- `UPDATE color_palette_comments SET user_id = NULL` (inclusive excluídos);
- `UPDATE color_comment_reports SET user_id = NULL, resolved_by = NULL`;
- **mantém** paletas, comentários, denúncias, imagem OG, preferências e mutes
  (as duas últimas já apagadas pela F7);
- **mantém** os follows de entrada: são ação de terceiros e o perfil
  anonimizado responde 404.

Nenhuma paleta é apagada. As FKs de comentários, curtidas e favoritos de
paleta são `cascadeOnDelete`; apagar a paleta destruiria dados de outras
contas.

### 4. `COLORS_COMMENTS_ENABLED` continua `false` em produção

A F8 não liga a flag. `config/colors.php` e `.env.example` mantêm o default
desligado. O procedimento de ligar fica documentado no `docs/RUNBOOK.md` §11
(worker obrigatório, chave de IA, cache de moderação, expurgo) e a verificação
funcional com a flag ligada foi feita **somente** no ambiente descartável
(SQLite em `%TEMP%`, semeadura local, banco apagado no fim). A decisão de ligar
é operacional e não precisa de deploy de código.

### 5. Achados altos corrigidos nesta fase

- `ExtractionPanel.vue` passa a usar `formatBytes` de `resources/js/lib/bytes.ts`,
  que converte o código de pasta do Laravel (`pt_BR`) para tag BCP 47
  (`pt-BR`) antes do `Intl.NumberFormat`.
- `lang/{pt_BR,pt_PT,en,es,fr}/notifications.php` passam a usar `{actor}` e
  `{palette}` no bloco `kinds`, com comentário explicando a sintaxe, e
  `resources/js/__tests__/i18n-messages.test.ts` ganha uma guarda que traduz
  as chaves reais nos cinco idiomas e falha se sobrar `:actor`/`:palette` ou se
  faltar o valor interpolado.

### 6. Documentação e operação entram no aceite

`README.md`, `docs/ARQUITETURA.md`, `CHANGELOG.md`, `docs/SPEC.md`,
`docs/colors/*` e o `RUNBOOK` §11 passam a refletir o track fechado. O
`.env.example` ganha as variáveis de Colors que `config/colors.php` lê. O
`docs/API.md` fica intocado: não existe API pública de Colors.

### 7. Formatação única do repositório

O `npm run format:check` falhava em ~267 arquivos por causa de código
pré-existente ao track. A F8 roda `npm run format` no repositório inteiro para
deixar o gate verde e impedir que o diff do track carregue formatação alheia.
O `.prettierignore` continua excluindo `docs`, `.gitpr`, artefatos gerados,
`components/ui`, views de e-mail, `storage`, `deploy`, `.env*`,
`*_plansfacts.md` e `*_surveyfacts.md`.

## Pendências deferidas (gravidade e ação)

| ID | Pendência | Gravidade | Ação sugerida |
| --- | --- | --- | --- |
| P1 | 404 de rotas do dashboard renderizadas por `$exceptions->respond()` perdem as props compartilhadas e mostram chaves cruas de i18n | Média | Renderizar o `Error` dentro do pipeline ou passar o mínimo das props |
| P2 | `lockForUpdate()` é no-op no SQLite; a garantia da cota só vale em MySQL | Média (desvio do aceite) | Manter a garantia em código + MySQL; registrar no aceite |
| P3 | Cache de moderação permanente guarda o texto do comentário | Média (privacidade) | TTL em produção + expurgo `ai:prune-cache --purpose=colors.comment_moderation --all` |
| P4 | Exportação da conta é montada em memória, sem teto para conta paga | Média | Streaming/zip em fase futura |
| P5 | Worker é obrigatório para moderação e notificações; sem ele o comentário fica pendente e o sino não toca | Média (operacional) | RUNBOOK §11 documenta o worker e o monitoramento |
| P6 | SPEC §2 documenta a landing do grupo (`/{locale}/tools/colors`) que não tem rota web | Média (documentação) | Corrigir a SPEC na próxima edição ou abrir a landing |
| P7 | Cache de moderação sem TTL no código de produção (decisão da F7) | Baixa (redundante com P3) | Igual a P3 |

## Consequências

- O titular passa a receber os dados de Colors no export da conta e a
  anonimização deixa de manter dado pessoal ligado à conta.
- Paletas, comentários e denúncias sobrevivem à anonimização sem autoria, como
  a política de conteúdo já fazia no restante do site.
- Nenhuma paleta é perdida por engano de cascade, o que preserva dados de
  terceiros.
- O track Colors fica fechado com matriz, pendências classificadas e aceite,
  no mesmo molde do BadgeForge F9.
- O defeito de interpolação das notificações passa a ser detectado pela suíte
  do front, não por inspeção visual.
- A verificação funcional com a flag ligada não contaminou o MySQL de
  desenvolvimento nem a produção.
- O repositório fica formatado por inteiro; o diff do track mistura formatação
  alheia, que é aceita como custo único.
- Ligar comentários em produção continua sendo um passo operacional
  reversível, sem migration.

## Alternativas rejeitadas

- **Apagar as paletas na anonimização**: destruiria comentários, curtidas e
  favoritos de outras contas pelas FKs `cascadeOnDelete`.
- **`DELETE` de comentários e denúncias em vez de `user_id = NULL`**: apagaria
  conteúdo publicado e a denúncia de terceiros; a política é manter o fato e
  remover a autoria.
- **Exportar os seguidores**: são ação de outra conta; o direito do titular
  cobre as relações que ele iniciou, não quem o segue.
- **Exportar `moderation_reason` e `notified_at`**: notas internas de
  moderação e metadado operacional, não dados do autor.
- **Ligar `COLORS_COMMENTS_ENABLED` agora**: a cadeia de IA/fila/worker exige
  decisão operacional explícita e reversível; a F8 não a liga por padrão.
- **Exportação em streaming já**: o volume atual não justifica; fica como P4.
- **Bloquear a fase por pendências médias**: o grill limitou a correção a
  bloqueante/alto; as médias ficam classificadas e visíveis.
- **Formatar só os arquivos do track**: deixaria `format:check` quebrado e
  perpetuaria a dívida de formatação.
