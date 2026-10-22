# ADR-045: Gateway Mercado Pago, Checkout Pro e Preapproval sem plano

Status: Aceito
Data: 2026-10-21
Relaciona: ADR-017 (PaymentGateway), ADR-019 (filas), ADR-027 (venda manual), ADR-034 (intencao de assinatura), ADR-044 (e-mails de pagamento)

## Contexto

A F6 abriu o pagamento automatico. Ate aqui o dinheiro so entrava por venda manual (ADR-027): o admin registrava a cobranca e concedia o plano. A intencao de assinatura (`/subscribe`) existia como formulario de contato e notificava o admin, sem cobranca online.

O provedor escolhido foi a Mercado Pago, por Pix, cartao e boleto em Checkout Pro. O contrato `PaymentGateway` ja estava previsto no ADR-017, mas nao implementado. O projeto nao usa SDK: o cliente HTTP fino do Laravel ja resolve autenticacao, timeout e idempotencia, e um SDK grande traria superficie e dependencia que o dominio nao precisa.

A decisao de produto foi: **Pagar agora** (avulso, Checkout Pro) ou **Renovacao automatica no cartao** (Preapproval), na mesma pagina `/subscribe`, com o formulario manual preservado para quem prefere atendimento.

## Decisao

1. **Contrato unico** `App\Contracts\Payments\PaymentGateway` com `isAvailable`, `createPreference`, `createPreapproval`, `createPixDonation`, `payment`, `preapproval`, `authorizedPayment`, `refund` e `cancelPreapproval`. A implementacao `MercadoPagoGateway` fala HTTP direto, com token do `.env`, timeouts de conexao e resposta, e `X-Idempotency-Key` estavel por operacao.
2. **Checkout avulso por Checkout Pro**: `POST /checkout/preferences` com itens, valor e moeda capturados na hora, `external_reference = chk_<ULID da checkout_session>`, `notification_url` e `back_urls`; o navegador vai para `init_point`. Pix, cartao e boleto sao escolhidos na tela do provedor, nao na nossa.
3. **Recorrencia por Preapproval sem plano associado**: `POST /preapproval` com `auto_recurring` (mensal ou 12 meses), cartao apenas. Nao existe plano pre-aprovado no provedor: o valor e o periodo sao nossos, e o ciclo de cobranca e lido do `subscription_authorized_payment`.
4. **`/subscribe` e uma pagina de decisao unica**: Pagar agora, Renovacao automatica no cartao e Prefiro atendimento manual. Os dois caminhos online exigem `auth` + `active`; sem credenciais do provedor eles ficam desabilitados com aviso e o manual continua.
5. **Checkout pendente em `checkout_sessions`**: ULID publico, dono, plano, modo (`one_time`/`recurring`), valor e moeda congelados, `init_point`, `expires_at` e estados (`pending`, `approved`, `failed`, `expired`, `canceled`). `payments` continua fiscal: so pagamento confirmado vira linha.
6. **Regras de inicio** (a tabela completa esta na secao 7 do SPEC):
   - recorrencia ativa no mesmo plano + Pagar agora: cancela a preapproval antes de abrir o checkout avulso;
   - recorrencia ativa no mesmo plano + nova recorrencia: bloqueia, sem segunda preapproval;
   - recorrencia ativa em outro plano: cancela a anterior antes de abrir o novo checkout;
   - sem recorrencia: abre normalmente e o periodo novo empilha ou comeca agora (ADR-046).
7. **Preapproval autorizada nao libera acesso**: a tela mostra "pagamento em processamento" ate o primeiro `subscription_authorized_payment` aprovado, que pode levar cerca de uma hora. O acesso nasce do pagamento, nao da autorizacao.
8. **Sem credenciais**: `isAvailable()` falso esconde os caminhos online; a venda manual e a doacao estatica continuam funcionando. Nenhuma tela quebra por falta de gateway.
9. **Erros seguros**: `PaymentGatewayException` nao carrega corpo de resposta nem token; o report vai para o log sem credencial e a tela mostra mensagem generica traduzida.

## Por que

- **Checkout Pro**: entrega os tres meios sem UI propria de cartao e sem PCI no nosso servidor. A alternativa (Bricks/transparente) exigiria formulario de cartao, tokenizacao no cliente e mais superficie de seguranca.
- **Preapproval sem plano**: planos no provedor engessariam preco e periodo em dois lugares; aqui a fonte continua sendo `plans`, e o valor cobrado fica registrado no pagamento.
- **Sessao de checkout separada de `payments`**: a sessao pode existir sem dinheiro (abandonada, expirada, falha). Misturar as duas faria fatura mentir.
- **Portal do provedor para o meio de pagamento**: Pix, cartao e boleto tem regras e telas proprias; repeti-las seria pior para quem paga.
- **Autorizacao sem acesso**: e a mesma regra do pagamento manual - cobranca e acesso sao duas coisas (ADR-027) -, aplicada ao ciclo do provedor.
- **Cliente HTTP fino**: o SDK oficial adicionaria dependencia, versao e excecoes proprias sem resolver nada que o Laravel nao resolva.

## Consequencias

- O `.env` ganha `MERCADO_PAGO_ACCESS_TOKEN`, `MERCADO_PAGO_WEBHOOK_SECRET`, `MERCADO_PAGO_BASE_URL` e `PAYMENTS_CHECKOUT_EXPIRES_MINUTES`; sem eles o checkout online nao aparece.
- `checkout_sessions` e `payments` precisam de limpeza: `payments:expire` marca sessoes vencidas (a leitura tambem expira de forma preguicosa).
- O retorno do Checkout Pro (`/subscribe/checkout/{ulid}/return`) nao confirma nada: quem confirma e o webhook (ADR-047). A tela diz "em processamento" e o usuário pode voltar.
- Trocar de plano com recorrencia ativa e uma operacao com efeito no provedor: se o cancelamento falhar, nada muda localmente.
- O plano desativado depois do checkout nao impede o pagamento ja iniciado; o periodo novo nao e concedido e a anomalia fica registrada.
- Os testes usam `Http::fake()`; o smoke real do provedor fica no aceite da F6 como criterio do dono.

## Alternativas descartadas

- **Checkout transparente (Bricks)**: mais controle visual, mas colocaria formulario de cartao e tokenizacao no nosso codigo e exigiria PCI; fora do escopo.
- **Planos pre-aprovados no provedor**: preco e periodo em dois lugares, com sincronizacao manual e risco de divergencia.
- **Gravar a cobranca em `payments` no inicio do checkout**: transformaria intencao em receita e faria fatura e cota mentirem.
- **Liberar acesso na autorizacao da preapproval**: cobraria sem dinheiro confirmado e contrariaria a regra de que pagamento e acesso sao coisas separadas.
- **SDK oficial**: dependencia grande, pouco ganho e excecoes proprias para tratar.
- **Recorrencia por assinatura com plano no provedor**: mesmo problema da duplicacao de preco/periodo, com menos flexibilidade para trocar de plano.
