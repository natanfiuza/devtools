# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige erro 400 na doacao Pix e melhora diagnostico do gateway
```

---

## 🎯 Resumo

Doações Pix estavam falhando com HTTP 400 no Mercado Pago sem qualquer rastro útil do motivo. As causas eram duas: (1) o `notification_url` podia ser montado com host de loopback (`localhost`, `127.0.0.1`, `::1`, `0.0.0.0`) quando o app roda em dev local sem túnel, e o gateway recusa esse valor; (2) o e-mail do pagador podia cair em um fallback inconsistente com o remetente de e-mail configurado, cenário em que o Mercado Pago rejeita a cobrança quando pagador e recebedor coincidem. Além de corrigir o comportamento, a exceção do gateway agora carrega o diagnóstico estruturado do provedor, permitindo identificar a causa raiz em produção sem expor corpo bruto ou dados de pagador nos logs.

## 🛠️ Mudanças Técnicas

- `CreateDonation` e `StartCheckout`: `webhookUrl()` passa a retornar `null` quando a rota resolve para host de loopback (exceto em testes unitários), evitando enviar `notification_url` inválido ao Mercado Pago; URLs configuradas são normalizadas com `trim()`.
- Novo helper `isLoopbackUrl()` (duplicado nas duas actions) que valida o host via `parse_url` e trata host ausente/malformado como loopback.
- `PaymentGatewayException`: novos campos read-only `gatewayError` e `gatewayCauses`, parâmetros opcionais em `fromStatus()` e novo método `context()` que devolve apenas dados estruturados para log (operação, status, erro e causas).
- `MercadoPagoGateway::send()`: em respostas não-2xx, extrai `error` e `cause[].code`/`cause[].description` do JSON com limites de tamanho (80/40/120 caracteres, no máximo 5 causas) e falha silenciosamente para corpo vazio ou JSON inválido.
- `config/donations.php`: `payer_email` passa a usar `env('DONATIONS_PAYER_EMAIL') ?: env('MAIL_FROM_ADDRESS')`, removendo a dependência de `config('mail.from.address')`.
- Teste de feature cobrindo o cenário de 400 com `cause`, garantindo que os códigos aparecem em `context()` sem vazar na mensagem da exceção.

## ⚠️ Impacto/Avisos

- **Envs**: `DONATIONS_PAYER_EMAIL` agora recorre diretamente a `MAIL_FROM_ADDRESS`. Ambientes que só definiam o remetente via `config/mail.php` (sem a env) podem passar a gerar cobranças com e-mail vazio — revisar a configuração antes do deploy.
- **Produção/dev local**: sem túnel público, o webhook deixa de ser enviado (antes era enviado e rejeitado com 400). Configure uma URL pública em `services.mercadopago.notification_url` (ou equivalente) para manter a confirmação de pagamento em desenvolvimento.
- **Logs**: novas chaves `gateway_error` e `gateway_causes` no contexto da exceção — atualizar dashboards/alertas que parseiam logs de pagamento.
- **Sem migrações de banco** e sem novas dependências.

close #90

---

[![GitPR](https://img.shields.io/badge/GitPR-2_errors_%C2%B7_5_warnings-red)](https://gitpr.natanfiuza.dev.br/)