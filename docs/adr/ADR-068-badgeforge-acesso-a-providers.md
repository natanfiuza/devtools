# ADR-068: Acesso a providers — público anônimo e PAT do usuário para item privado

Status: Aceito
Data: 2026-10-31
Relacionados: ADR-064 (badges pessoais), ADR-067 (PNG), ADR-071 (documentação pública)

## Contexto

A primeira redação deste ADR (ex-ADR-047, "Tokens por provider") previa um token
global por provider em `.env` — uma variável `BADGEFORGE_*` para cada um de
GitHub, npm, Docker e Packagist — e dizia que o usuário pago poderia informar o
próprio token do GitHub para repositórios privados.

Esse desenho confunde duas necessidades diferentes:

1. **Item público** (repositório, pacote ou imagem pública). A API pública do
   provider responde anonimamente; nenhuma credencial é necessária. Um token
   global nosso só serviria para economizar o rate limit anônimo — e colocaria
   um segredo de plataforma no caminho de toda requisição pública, com risco de
   vazamento e de bloqueio da conta inteira se a cota estourasse.
2. **Item privado**. Só existe quando o **usuário** tem acesso a ele. O token
   precisa ser do usuário, com o escopo mínimo dele, e não da plataforma.

Além disso, Packagist não hospeda pacote privado: o acesso é sempre público.
Prometer um token de Packagist seria prometer um recurso impossível.

A Fase 1 (núcleo SVG estático) não chama provider nenhum e não lê token; a
decisão vale para a F4 em diante e foi registrada agora para não deixar os
documentos descrevendo um modelo abandonado.

## Decisão

### 1. Item público: API pública do provider, anonimamente

Os providers de item público (GitHub REST, registry do npm, Docker Hub,
Packagist) são chamados **sem credencial**. Não existe token global por provider
em `.env`, `config/` ou banco. O rate limit anônimo (GitHub: 60 req/h/IP) é
absorvido por cache por provider (TTL em config), requisição condicional quando
o provider suportar (`If-None-Match`/`ETag`) e `stale-if-error`: um 429 do
provider nunca vira 500 na imagem — o último valor bom continua sendo servido.

### 2. Item privado: PAT do usuário pagador, só para o badge pessoal

- Apenas contas pagas podem criar badge sobre item privado.
- O PAT é criado **pelo usuário** no próprio provider, com o **escopo mínimo**
  (ex.: GitHub fine-grained, `Contents: read-only` e `Metadata: read-only`,
  restrito aos repositórios usados).
- O PAT é informado na UI da conta, fica **cifrado em repouso** (cast
  `encrypted`, chave `APP_KEY`) e **vinculado à definição do badge pessoal** —
  não é um cofre global do usuário.
- A URL pública do badge leva **somente o ULID opaco** do badge
  (`/b/{username}/{ulid}.svg`), nunca o token.
- O token nunca aparece em URL, log de aplicação, payload de debug, mensagem de
  erro, resposta da API ou exportação de dados. Nos logs só entram IDs e classes
  de exceção.
- O usuário pode revogar/rotacionar o token a qualquer momento; apagar o badge
  apaga a credencial. A anonimização/exclusão da conta apaga o token junto.
- Um teste precisa provar a ausência do token em log e em payload de erro.

### 3. Packagist é sempre público

Não há tutorial de PAT do Packagist nem suporte a pacote privado: a
documentação diz explicitamente que o provider é anônimo e público.

## Consequências

### Positivas
- Nenhum segredo de plataforma no caminho de uma imagem pública.
- O token é do dono do dado, com escopo e validade sob controle dele.
- Vazamento de um PAT de usuário atinge um badge, não a plataforma inteira.
- O modelo é honesto: só promete privacidade onde o provider oferece.

### Negativas / Trade-offs
- O rate limit anônimo do provider é o teto da F4; a mitigação é cache, não
  credencial.
- Badge privado depende de ação do usuário na UI e de um plano pago; a tela
  precisa explicar revogação e apagar o token quando o badge sai.
- O suporte a PAT precisa de coluna cifrada, rota de gestão e testes de
  não-vazamento em cada provider que o aceitar.

## Alternativas descartadas

- **Token global por provider em `.env`** (redação anterior): centraliza o
  risco, mistura segredo da plataforma com dado de usuário e não serve a item
  privado, que exige autorização do dono.
- **Token em query string do badge**: apareceria em access log, histórico do
  navegador e cache do proxy. Inaceitável.
- **Badge privado público por obscuridade de URL**: a URL é pública por
  natureza (vai num README); só o ULID não é autorização suficiente para o
  provider, por isso o segredo fica no servidor e o badge é do usuário.
