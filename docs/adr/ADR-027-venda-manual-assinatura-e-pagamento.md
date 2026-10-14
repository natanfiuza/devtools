# ADR-027-venda-manual-assinatura-e-pagamento
Status: Aceito
Data: 2026-10-14

## Decisao
**O modelo comercial entra agora, mas a venda e manual: o admin concede a
assinatura e registra o pagamento a mao, fora de qualquer gateway. Sao quatro
tabelas, cada uma respondendo a uma pergunta diferente.**

- `plans`: o catalogo. Preco (`price_cents`, `currency`) e limites
  (`monthly_tokens`, `max_schema_bytes`, `max_prompt_chars`,
  `lifetime_free_uses`) vivem **na tabela**, nunca no codigo - a regra do
  CLAUDE.md - e o admin edita pela tela de Planos. A carga inicial e uma
  **migracao** com `insertOrIgnore` (`2026_10_14_000004_seed_plans_catalog`):
  roda de novo sem duplicar e sem sobrescrever o preco que o admin ja editou.
  Catalogo inicial: Free (R$ 0, sem intervalo, 8 KB, 500 caracteres, 5 usos
  vitalicios), Pro mensal (R$ 29,90, 2.000.000 tokens, 64 KB, 4.000 caracteres)
  e Pro anual (R$ 299,00, mesmos limites).
- `subscriptions`: o que da acesso. `gateway` (default `manual`) e `gateway_ref`
  ja existem para a F6; hoje so recebem `manual`.
- `payments`: o que foi cobrado. `gateway = manual`, sem `gateway_ref` - este
  pagamento nao veio de lugar nenhum, foi quem recebeu o dinheiro que contou
  aqui.
- `usage_ledger`: o consumo (a Cota do glossario). **Nao existe coluna de
  saldo**: o que sobra e a franquia do plano menos os debitos do periodo, mais
  estornos e concessoes. Saldo em coluna e o que se perde quando duas escritas
  concorrem.
- Conceder e registrar sao **dois atos** (`GrantPlan` e `RegisterPayment`), e
  conceder junto com o registro e opcional de proposito: as vezes so um dos dois
  acontece - cortesia sem dinheiro, ou o mes pago por quem ja tinha acesso.
- Conceder plano tem tres casos: **mesmo plano estende o prazo** a partir do fim
  vigente, sem mexer no inicio do periodo (reiniciar a janela no meio daria uma
  franquia nova em cima de um periodo que ainda nao acabou); **plano diferente**
  encerra a vigente e abre a nova; **o Free e downgrade** - encerra a vigente e
  nao cria nada, porque o Free nao e uma assinatura de preco zero, e a ausencia
  de assinatura.
- Vigencia resolvida **na leitura** (`isCurrent()`, `scopeCurrent()`): o deploy
  nao roda `schedule:run`, entao `expired` nao e um estado gravado - uma
  assinatura ativa com periodo vencido simplesmente deixa de ser corrente.
- Estorno (`RefundPayment`): o pagamento vira `refunded` **e** a assinatura que
  ele abriu e encerrada, na mesma acao. A linha nao e apagada - estorno e um
  estado, e o que foi cobrado continua no historico. O valor nunca e negativo.
- Estorno de dinheiro **nao** entra no livro-razao: devolver consumo e
  `GrantQuota` (uma linha `grant`), que e outro ato.
- `config/billing.php` carrega so a moeda (`BILLING_CURRENCY`), para o pagamento
  que nao aponta para plano nenhum.
- O painel do usuario **nao tem botao de assinar nem de cancelar**: quem vende e
  o admin. Faturas e Historico sao leitura.

## Por que
A ferramenta que consome token e a F5. Vender antes de poder entregar seria
cobrar por algo que ainda nao funciona - mas o acesso precisa existir antes
disso, para o beta, para a cortesia e para a venda combinada por pix fora do
site. E o dinheiro que entra por fora precisa de registro: fatura sem lastro e
o tipo de coisa que se descobre na contabilidade, nao no produto.

Gateway (Mercado Pago, F6) e integracao com terceiro: webhook, conciliacao,
idempotencia, ambiente de teste. O modelo de dados nao muda por causa disso -
`gateway` e `gateway_ref` ja estao nas duas tabelas, e o gateway vai escrever
assinaturas e pagamentos do mesmo jeito, mudando so esses campos.

O catalogo em tabela, e nao em `config/`, e o que permite ao admin mudar o preco
sem deploy - e o que faz o site nao saber o preco sem consultar o banco, o que e
o preco certo a pagar por isso.

## Consequencias
- `payments.user_id` e `restrictOnDelete`: o banco recusa apagar a conta de quem
  tem historico de pagamento, em vez de perder o registro em silencio. E o que
  sustenta a anonimizacao da ADR-028.
- `payments.plan_id` tambem e `restrictOnDelete`: plano com historico nao se
  apaga, desativa-se (`plans.active`).
- Nao ha renovacao automatica: acabou o periodo, acabou o acesso ate alguem
  conceder de novo. Franquia nova a cada periodo entra com o gateway (F6).
- A unidade da cota e do plano, nao da tela: conceder "1000" para quem esta no
  Free concede 1000 usos, e nao 1000 tokens (`Quota::unit()`).
- `remaining()` nunca e negativo: consumo acima da franquia aparece como
  esgotado, nao como saldo devedor.
- Toda venda manual deixa rastro em `admin_actions`
  (`payment.registered`, `payment.refunded`, `user.plan_granted`,
  `user.quota_granted`, `plan.updated`).
- A extracao do consumo real (`debit`) e da F5 - aqui mora so a leitura do
  estado (`App\Support\Quota`).

## Alternativas descartadas
- **Preco no codigo ou em `config/`**: viola a regra do CLAUDE.md e faria de
  cada mudanca de preco um deploy.
- **Seeder para o catalogo**: o deploy roda `migrate --force` e nunca
  `db:seed`; o site subiria sem plano nenhum.
- **Saldo em coluna** (`users.tokens_left`): duas escritas concorrentes perdem
  uma; o livro-razao e a unica forma de o numero bater com a historia.
- **Assinatura `expired` gravada por rotina agendada**: o deploy nao roda
  `schedule:run`; resolver na leitura da o mesmo resultado sem depender de cron.
- **Estorno como lancamento negativo em `payments`**: quebraria somas e
  relatorios; estorno e um `status`, e o valor cobrado continua sendo o que foi.
- **Estorno devolvendo cota automaticamente**: são dois atos diferentes - o
  dinheiro voltou (fato fiscal) e o consumo sera devolvido ou nao (decisao
  comercial). Quem devolve consumo e `GrantQuota`, com sua propria auditoria.
- **Conceder e registrar numa acao so**: os dois casos que existem e que nao
  andam juntos (cortesia; pagamento de quem ja tinha acesso) mostrariam que a
  acao unica teria que ser desfeita no dia seguinte.
- **Botao de assinar no painel do usuario ja agora**: nao ha o que cobrar nem o
  que entregar ainda; o botao existiria para dar erro.
