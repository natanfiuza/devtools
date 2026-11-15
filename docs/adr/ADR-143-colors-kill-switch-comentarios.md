# ADR-143 - Kill switch dos comentários
Status: Aceito
Data: 2026-11-15
Relacionados: ADR-139 (comentários), ADR-140 (moderação), ADR-141 (denúncias), ADR-142 (notificações)

## Contexto
A F7 entrega comentários, moderação, denúncias e e-mail. Nenhuma dessas peças existia em produção e a cadeia envolve IA externa, fila e worker. Ligar tudo por padrão faria a primeira falha de worker/provedor aparecer como tela pública nova e possivelmente vazia, sem caminho de desligamento sem deploy.

## Decisão

### 1. Desligado por padrão
`COLORS_COMMENTS_ENABLED=false` é o default em `config/colors.php` e no `.env.example`. Ela não é ligada por migration nem por seed; a produção só liga por decisão explícita do usuário, depois do aceite local.

### 2. O que a flag desliga
- a thread não é consultada nem enviada nas props da página pública (`comments = null`, sem formulário);
- `POST palettes/{palette}/comments`, `PATCH/DELETE comments/{comment}`, `POST comments/{comment}/report` e `POST/DELETE palettes/{palette}/mute` respondem **404 uniforme** pelo middleware `colors.comments`;
- a página pública continua funcionando com curtida, follow, favorito, imagem e OG: nenhuma outra superfície muda.

### 3. 404 uniforme, não 403 nem banner
Desligar não é erro de permissão nem manutenção; para o visitante a superfície simplesmente não existe naquele ambiente. O 404 uniforme evita revelar a funcionalidade, combina com as regras de visibilidade já usadas nas paletas e não exige tratamento especial no cliente.

### 4. Liga só com a cadeia verde
O aceite da fase exige os testes de moderação, autoria, denúncia, notificação e admin verdes antes de ligar qualquer ambiente. Em local, a verificação funcional descrita na SPEC-COLORS é feita com a flag ligada; em produção, ligar é passo operacional explícito e reversível.

### 5. Fontes de verdade
`COLORS_COMMENTS_ENABLED` é a única chave; cotas, páginas e limites (tamanho, página, respostas, modelo, TTL, retries) continuam em `config/colors.php` e `.env`, nunca fixos no código.

## Consequências
- Um deploy sem worker ou sem provedor não cria comentários inacessíveis: a feature nem aparece.
- Desligar em produção é uma variável de ambiente, sem migration e sem remover dados.
- Dados já gravados permanecem no banco e voltam a aparecer quando a flag religar.
- A fase pode ser aceita em local sem expor comentários a visitantes.
- O frontend precisa tratar `comments === null` como “sem thread”, não como erro.

## Alternativas rejeitadas
- **Ligado por padrão**: expõe superfície nova a falhas de worker/IA antes do aceite.
- **403 ou página de manutenção**: revela a funcionalidade e rompe o 404 uniforme da paleta.
- **Flag por conta ou por paleta**: não existe necessidade de rollout parcial nesta fase e multiplicaria a regra de visibilidade.
- **Ligar só na migration**: mistura dado de ambiente com decisão operacional e dificulta desligar.
