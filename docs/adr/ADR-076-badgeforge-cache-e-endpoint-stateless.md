# ADR-076: Cache HTTP e endpoint stateless

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-050 (API pública v1), ADR-052 (CSP e proxies), ADR-073 (formatos)

## Contexto

Uma imagem de badge é carregada por todo visitante de um README externo, muitas
vezes por CDN e proxy de terceiros. A resposta precisa ser barata, determinística
e cacheável sem sessão. O contrato da API v1 (`/api/v1`, Sanctum, cota por plano,
rate limit por token) é para integrações autenticadas e não serve a um `<img>`.

O endpoint também não pode registrar o conteúdo do badge: label e message são
texto do usuário e vão na URL, como em qualquer badge; logar o texto seria
duplicar conteúdo de terceiros fora de contexto.

## Decisão

1. **Rota fora da v1 e stateless**:
   `GET /api/badges/static/{label}/{message}.svg`, nome `badges.static`, no grupo
   `api`, **fora** do prefixo `v1`, sem `auth:sanctum`, sem sessão/CSRF e sem
   throttle próprio na F1 (a proteção global de infraestrutura continua).
2. **Parâmetros de rota explícitos**: `where('label', '[^/]+')`,
   `where('message', '[^/]+')` e `where('ext', 'svg')`. O `where` em `message` é
   obrigatório: sem ele o compilador de rota exclui o ponto do parâmetro e
   `1.0.0.svg` viraria `message=1` + `ext=0.0.svg`.
3. **Resposta 200**: `Content-Type: image/svg+xml; charset=utf-8`,
   `Cache-Control: public, max-age=<ttl>` e `ETag: "<sha256 do SVG>"`.
4. **304**: `If-None-Match` igual ao ETag responde 304 sem corpo e sem
   `Content-Type` (comportamento de `Response::isNotModified()` do Symfony).
5. **TTL**: `cacheSeconds` com padrão, mínimo e máximo em
   `config/badgeforge.php` (86400, 300 e 86400). Inteiro fora da faixa sofre
   clamp; valor inválido cai no padrão. TTL nunca vem do código.
6. **Erros**: segmento vazio ou extensão diferente de `svg` responde 404 no
   envelope `ApiError`; entrada cosmética inválida (estilo, cor, logo, posição)
   cai no padrão. O endpoint não devolve 500 por entrada do usuário.
7. **Sem log de conteúdo**: nenhuma linha de log carrega label, message ou o SVG;
   o que pode aparecer é ID, status e classe de exceção.
8. **Sanitização por construção**: o documento só tem as tags e atributos da
   allowlist (`svg`, `title`, `a`, `defs`, `linearGradient`, `stop`, `clipPath`,
   `g`, `rect`, `path`, `text`), todo texto passa por escape XML e nenhum markup
   do usuário entra no documento. `link` só aceita `http`/`https` e vira
   `<a target="_blank" rel="noopener noreferrer">`.
9. **Acessibilidade**: `role="img"`, `aria-label="label: message"` e `<title>`
   com o mesmo texto; o logo interno é `aria-hidden` e `focusable="false"`.
10. **F2 em diante** acrescenta `png` ao `where('ext')` e `?refresh=` só para
    badge própria (ADR-069); nada disso muda o contrato acima.

## Consequências

### Positivas
- CDN e proxy podem servir a imagem sem tocar a aplicação.
- ETag estável entre requisições iguais (hash do documento) permite revalidação
  barata.
- O endpoint é testável sem banco e não depende de sessão, fila ou disco.
- Nenhum conteúdo de usuário em log.

### Negativas / Trade-offs
- Sem throttle próprio na F1, o custo por requisição recai na infraestrutura;
  a proteção é global e a resposta é cacheável.
- O texto continua visível na URL e no access log do proxy (natureza de badge);
  a regra de não logar é do aplicativo.
- O `where` em `message` precisa ser mantido: removê-lo quebra textos com ponto
  de forma silenciosa.
- O comportamento 304 remove `Content-Type`; consumidores que exigem o header
  no 304 precisam de ajuste futuro, se algum aparecer.
