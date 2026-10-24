# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: expõe status de IA e pagamentos no artisan about
```

---

## 🎯 Resumo

Facilita o diagnóstico de ambiente ao expor, diretamente no `php artisan about`, se a IA e o gateway de pagamentos estão configurados. Antes, era necessário inspecionar `.env` e logs para descobrir por que recursos de IA ou doações falhavam em um ambiente — e o modo de doação (Mercado Pago dinâmico, Pix estático ou indisponível) não era visível em lugar nenhum. A mudança entrega observabilidade operacional sem jamais imprimir tokens ou segredos de webhook.

## 🛠️ Mudanças Técnicas

- Adiciona bloco `AI` ao `AboutCommand` no `AiServiceProvider`, informando se o provider está configurado e qual modelo está ativo (ou `not configured`).
- Adiciona bloco `Payments` ao `AboutCommand` no `PaymentsServiceProvider`, exibindo gateway, status do token do Mercado Pago, status do segredo de webhook e o modo de doação efetivo (`dynamic (mercadopago)`, `static pix` ou `unavailable`).
- Usa `PaymentGateway::isAvailable()` e `PixPayload::isConfigured()` como fonte da verdade, evitando duplicar regras de configuração nos providers.
- Cobre o comportamento com teste de feature em `CheckoutTest`, validando a saída JSON do `about` nos cenários "tudo configurado" e "fallback para Pix estático", incluindo asserções negativas que garantem que token e webhook secret não vazam na saída.
- Atualiza a documentação de Mercado Pago, além dos artefatos de plano, relatório e survey do diagnóstico.

## ⚠️ Impacto/Avisos

- **Sem migrations de banco de dados.**
- **Sem novas variáveis de ambiente ou dependências.** A mudança apenas lê chaves já existentes (`ai.model`, `payments.gateway`, `payments.mercadopago.access_token`, `payments.mercadopago.webhook_secret`, `donations.static_pix.*`).
- **Saída do `artisan about` alterada:** novos blocos `AI` e `Payments`; consumidores que fazem parsing do `--json` passam a ver as novas chaves (`mercado_pago_token`, `mercado_pago_webhook_secret`, `donation_mode`).
- **Segurança:** apenas rótulos `configured`/`not configured` são expostos — nenhum valor sensível é renderizado, comportamento protegido por teste.
- **Operacional:** a ausência de configuração deixa de ser silenciosa; "Donation Mode: unavailable" indica que nem Mercado Pago nem Pix estático estão prontos para uso.

close #88

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)