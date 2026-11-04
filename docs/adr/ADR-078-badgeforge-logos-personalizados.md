# ADR-078: Logos personalizados multi-path

Status: Aceito
Data: 2026-11-02
Relacionados: ADR-002 (processamento local), ADR-074 (estilos e logos), ADR-075 (render PHP e espelho TS), ADR-076 (cache e endpoint stateless), ADR-079 (presets e gestão autenticada)

## Contexto

A F1/F2 entregou 25 logos embutidos, todos com um único `<path d>`. A F3 pede
que a conta use o próprio logo no badge. Logo de usuário é markup arbitrário:
servir o arquivo cru ou aceitar tags/atributos fora de um allowlist é abrir XSS
armazenado no endpoint público, que é consumido por `<img>` de terceiros e por
download direto.

Havia ainda três perguntas de contrato:

1. **O que persistir** — o SVG original ou apenas a geometria normalizada.
2. **Como referenciar o logo na URL pública** — o endpoint é stateless e não
   tem sessão.
3. **O que acontece com um badge já colado quando o logo é excluído** — a URL
   é pública e não pode passar a responder erro.

## Decisão

### Parser e persistência (`CustomLogoSvgParser`)

1. O upload é um arquivo SVG de até `custom_logos.max_bytes` (256 KB),
   UTF-8 válido.
2. `<!DOCTYPE`, `<!ENTITY` e qualquer processing instruction que não seja a
   declaração `<?xml ...?>` são recusados antes do parse.
3. O documento é lido com `DOMDocument::loadXML(..., LIBXML_NONET | LIBXML_NOBLANKS)`
   — nunca `LIBXML_NOENT` e nunca resolução de DTD.
4. A raiz é `<svg>` (namespace vazio ou SVG) e só `<g>` e `<path>` podem
   aparecer abaixo dela. Texto só é aceito se for whitespace. Qualquer outra
   tag — `script`, `style`, `image`, `use`, `foreignObject`, `defs`, `text`,
   animações — vira erro.
5. Atributos são um allowlist: `viewBox` obrigatório na raiz; `d` obrigatório
   em `path`; `fill-rule`/`clip-rule` só com `nonzero`/`evenodd` (herdados de
   `<g>`); `on*`, `href`, `xlink:href`, `style`, `transform`, `opacity`,
   `filter`, `clip-path`, `mask`, `marker-*` e valores com `url(` são
   recusados. `fill`/`width`/`height`/`class`/`id` são ignorados: o logo é
   recolorido por `logoColor`.
6. O `d` segue `^[MmLlHhVvCcSsQqTtAaZz0-9+\-.,eE\s]+$`, começa por `M`/`m` e
   respeita `max_path_length`, `max_total_path_length` e `max_paths`. O
   `viewBox` tem quatro números com `w > 0`, `h > 0` e extensão limitada por
   `max_viewbox_extent`. A profundidade do documento é limitada por
   `max_document_depth`.
7. **Só o normalizado é gravado**: `badge_custom_logos` guarda `view_box`,
   `paths` (`[{d, fillRule?, clipRule?}]`), `path_count` e `size_bytes`. O
   arquivo enviado não vai para banco, disco ou log. O erro de parse vira 422
   com código estável (`validation.custom_logo.<code>`).
8. `Svg::assertSafe` continua como segunda barreira no documento final do
   badge; o renderer não muda a ordem dos atributos dos logos embutidos.

### Referência pública e resolução

1. O logo é referenciado como **`?logo=u_<ULID>`**. O prefixo `u_` é um
   namespace reservado: o catálogo embutido nunca tem slug começando por
   `u_`. O ULID é opaco; não há nome de usuário nem caminho de conta na URL.
2. `BadgeRequest` resolve `u_*` pelo `CustomLogoRepository` e os demais pelo
   `LogoCatalog`. O repositório aceita o slug em qualquer caixa, memoiza o
   resultado por request (inclusive “não encontrado”) e revalida o JSON na
   leitura.
3. **Ausência nunca vira 4xx**: slug malformado, ULID inexistente, logo
   apagado ou banco indisponível (`QueryException`) resolvem para `null` e o
   badge responde 200 sem logo. Badges já colados continuam funcionando.
4. O endpoint de imagem continua sem autenticação e sem consulta extra para
   logos embutidos.

### Limites, cache e gestão

1. Limites em `config/badgeforge.php`: Free `custom_logos.free_limit = 2`;
   pago `paid_limit = null` (ilimitado). Nenhum número é fixado em controller,
   FormRequest, parser ou Vue.
2. O `StoreCustomLogo` roda em transação e trava a linha do usuário
   (`lockForUpdate`) na checagem do limite: duas abas simultâneas não passam do
   teto.
3. Badge com logo personalizado tem **teto de cache** `custom_logos.max_cache_seconds`
   (3600 s), aplicado sobre o `cacheSeconds` da URL. Assim, exclusão e
   renomeação aparecem em no máximo 1 h, sem reescrever URLs existentes.
4. Os paths **não viajam nas props da tool**: as props levam só metadados
   (`id`, `slug`, `name`, `viewBox`, `pathCount`, `sizeBytes`). Ao escolher um
   logo (ou aplicar um preset que o use), o cliente faz
   `GET /badgeforge/logos/{id}` — autenticado, só para o dono, 404 para os
   demais, `Cache-Control: private, no-store` — e memoiza na sessão da página.
   A URL/snippet já é montada com `logo=u_...` mesmo antes de os paths
   chegarem.
5. As rotas de escrita ficam sob `throttle:badgeforge-management`
   (`management.rate_limit` / `rate_decay_seconds`) e o grupo
   `auth, verified, active`; dono errado recebe 404, nunca 403.

## Alternativas consideradas

- **Servir o SVG enviado por uma URL própria**: além de expor markup do
  usuário, criaria um segundo documento para manter seguro e cachear.
- **Persistir o arquivo cru e sanitizar na leitura**: o banco guardaria dados
  que nunca serão servidos e toda leitura dependeria do parser; a validação
  única no upload é mais simples e auditável.
- **Usar o id numérico/slug do nome na URL**: previsível e enumerável; o ULID
  mantém a referência opaca e estável mesmo com renomeação.
- **Recusar o badge quando o logo sumiu**: quebraria imagens já embutidas em
  READMEs de terceiros; o fallback para “sem logo” preserva o contrato do
  endpoint stateless.
- **Sem teto de cache para logos custom**: renomear/excluir demoraria até
  24 h para refletir; 1 h é o equilíbrio entre frescor e carga.

## Consequências

### Positivas

- Logo do usuário entra no badge sem markup arbitrário no documento final.
- Uma única fonte de verdade (paths normalizados) para PHP, TS e exportação.
- URLs públicas continuam stateless e tolerantes a logo ausente.
- Plano pago não infla as props da página: os paths chegam sob demanda.

### Negativas / Trade-offs

- O parser é uma allowlist conservadora: SVGs com `transform`, gradientes,
  `defs` ou `style` são recusados, e o usuário precisa simplificar o arquivo.
- Exclusão de logo não invalida caches de terceiros na hora; o teto de 1 h é
  o limite superior.
- O preview depende de um segundo GET autenticado quando o logo é escolhido.
- A paridade PHP/TS agora inclui múltiplos paths com `fill-rule`; qualquer
  mudança no loop exige regravar fixtures dos dois lados (ADR-075).
