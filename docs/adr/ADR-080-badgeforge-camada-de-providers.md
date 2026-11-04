# ADR-080: Camada de providers do BadgeForge

Status: Aceito
Data: 2026-11-03
Relacionados: ADR-002 (processamento local), ADR-068 (acesso a providers), ADR-069 (refresh), ADR-071 (documentação pública), ADR-076 (cache e endpoint stateless), ADR-081 (badges pessoais e PAT)

## Contexto

A F4 transforma a badge estática em badge **dinâmica**: o conteúdo vem da API
pública de um serviço externo (GitHub, npm, Docker Hub, Packagist) e a imagem
continua sendo consumida por `<img>` em README, sem sessão e sem JavaScript.

O SPEC §8 já previa as tabelas `badge_providers` e
`badge_provider_definitions`, mas deixava três perguntas em aberto:

1. **Quem define o comportamento de cada provider** — colunas no banco, código
   ou uma mistura. O campo `parser_class` do SPEC sugere executar uma classe
   vinda do banco, o que é um risco óbvio de execução arbitrária.
2. **O que fica no cache** — o corpo inteiro da resposta do provider ou só os
   valores que a badge exibe. Guardar o corpo cru de um repositório privado
   deixa descrição e README no cache por um TTL inteiro.
3. **O que a imagem responde quando o provider falha** — um 500 é inaceitável
   numa badge de README; um 200 com o último valor bom é o comportamento
   esperado por quem integra.

Além disso, a rota nova precisa conviver com `/api/badges/static/...` sem
sombreá-la, e a chamada pública precisa continuar anônima (ADR-068): nenhum
token global em `.env`, `config/` ou banco.

## Decisão

### 1. Metadados no banco, comportamento no código

`badge_providers` e `badge_provider_definitions` guardam o **catálogo**:
slug, nome, label, `path_pattern`, params obrigatórios/opcionais,
`url_template`, `example_path`, `supports_private`, `documentation_url`,
`cache_ttl` opcional e ordem. As 9 definições da F4 entram por migração
(`insertOrIgnore`, como o catálogo de planos), porque o deploy roda
`migrate --force` e nunca `db:seed`.

O comportamento é **código**: `client_key` identifica o cliente HTTP
(`github`, `npm`, `packagist`, `docker`) e `extractor_key` a métrica
(`stars`, `forks`, `issues`, `license`, `v`, `dm`, `dt`, `pulls`). As chaves
são validadas contra o registry; uma chave desconhecida não vira badge — o
client não é encontrado e a resposta é fallback. `parser_class` existe na
tabela e nasceu `NULL`; **nunca é lida nem instanciada**. O mapa
`client_key => client` é a autoridade.

### 2. Item público é anônimo; privado só em badge pessoal

O endpoint `/api/badges/{provider}/{definition}/{path}` não recebe credencial
nenhuma. `Authorization` só é enviado quando o `ProviderFetchRequest` traz um
`ProviderCredential`, e essa rota nunca o monta — ele só existe no caminho da
badge pessoal (ADR-081).

### 3. Cache por URL resolvida, com payload projetado

Cada cliente devolve um `ProviderPayload` com **apenas** as métricas que a
badge usa; descrição, README, owner e o resto do corpo não são persistidos. A
chave é
`badgeforge:provider:sha256(provider|URL resolvida|public|fingerprint)`; o
token nunca entra na chave (no modo privado entra o HMAC-SHA256 da credencial).
Como a URL canônica do GitHub é a mesma para stars/forks/issues/license, as
quatro definições compartilham **uma** entrada — e uma chamada.

Fluxo de `ProviderMetrics`:

- entrada fresca (`fetched_at + effectiveCacheTtl()`) → `HIT`;
- vencida → `Cache::lock` por chave, requisição condicional com
  `If-None-Match`; `304` renova `fetched_at`; sucesso grava o payload;
- falha com entrada ainda utilizável → `STALE` (cabeçalho
  `Warning: 110 - "Response is Stale"`);
- sem entrada e falha → **fallback 200** com a mensagem de
  `providers.unavailable_message` e TTL curto (`fallback_ttl`, ou o
  `Retry-After` respeitado dentro do limite);
- store de cache fora do ar → fetch direto e, se falhar, fallback; nunca 500.

O TTL de resposta é `min(provider->effectiveCacheTtl(), personal_badges.max_cache_seconds)`,
para que uma badge privada não fique presa mais de 1 h depois de excluída.
`?cacheSeconds=` e `?refresh=1` do cliente são **ignorados** nesta rota: o
TTL é do servidor, e refresh forçado é ação autenticada do dono (ADR-069 e
ADR-081).

### 4. Rota de provider depois da estática

Em `routes/api.php`, a rota
`{provider}/{definition}/{path}` é registrada **depois** de
`static/{label}/{message}.{ext}`. O Symfony casa na ordem de registro, então
`/api/badges/static/...` continua vencendo sem lookahead frágil. `{path}` aceita
`/` (pacote npm com escopo, `vendor/package`); o controller separa a extensão
e o parser valida o restante. `static` é slug reservado: um provider com esse
slug é ignorado pelo catálogo.

Provider/definição desconhecidos ou desabilitados, path fora do
`path_pattern` e URL que não passe no `ProviderUrlBuilder` (HTTPS, host do
próprio `url_template`, sem userinfo/porta, sem redirect) respondem **404** no
envelope `ApiError`.

### 5. Nada de número hardcoded

TTL, stale, fallback, timeouts, tamanho máximo de corpo, comprimento de param,
rate limit de refresh e mensagem de indisponibilidade vêm de
`config/badgeforge.php`. Nenhum controller, cliente ou componente Vue fixa
esses valores.

O `url_template` também precisa respeitar `providers.max_body_bytes`. O
endpoint de downloads do Packagist é `/{vendor}/{package}/stats.json` (poucos
KB), e não o JSON completo do pacote, que carrega todas as versões e passa de
7 MB em pacotes grandes — muito acima do teto de 1 MiB. O cliente aceita
`downloads` no topo (formato do `stats.json`) e mantém o fallback para
`package.downloads` para não quebrar se o provider mudar de forma.

## Consequências

### Positivas

- Um provider novo é uma linha no catálogo + um cliente registrado; nenhuma
  classe vinda do banco é executada.
- O cache não guarda conteúdo de repositório privado além do número exibido.
- Rate limit anônimo do provider é absorvido por cache, condicional, stale e
  fallback — a imagem nunca responde 500 por causa do provider.
- A rota estática continua intacta, com teste de conflito.

### Negativas / Trade-offs

- Quatro clientes e um formatador a manter quando a API do provider mudar;
  validação estrita de shape transforma mudança em fallback, não em badge
  errada.
- O primeiro `GET` de uma badge pessoal de provider faz a chamada (a criação
  não busca), então o primeiro leitor pode ver fallback se o provider estiver
  fora.
- `open_issues_count` do GitHub inclui pull requests; isso é documentado na
  página pública, não "corrigido" com uma segunda chamada.
- Cache em `database` (default) exige a tabela de cache existir; a camada
  degrada para fetch direto quando o store não responde.

## Alternativas descartadas

- **Executar `parser_class` do banco**: execução arbitrária a partir de dado
  de catálogo. Recusado; `client_key` + registry é o mapa.
- **Token global por provider em `.env`**: já descartado na ADR-068; não
  resolve item privado e centraliza o risco.
- **Guardar o corpo cru no cache**: deixaria descrição/README de repo privado
  em storage compartilhado. O payload é projetado na origem.
- **500 quando o provider falha**: quebraria READMEs por indisponibilidade de
  terceiro; stale/fallback 200 é o contrato.
- **Registrar a rota de provider antes da estática**: exigiria lookahead ou
  reserva de slug; a ordem de registro resolve.
