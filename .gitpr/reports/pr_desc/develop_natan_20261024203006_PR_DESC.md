# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige fuso horário na expiração do PIX
```

---

## 🎯 Resumo
Corrige o tratamento do `date_of_expiration` retornado pelo Mercado Pago. A data chega com offset `-04:00`; ao persistir em `DATETIME`, o offset podia ser ignorado e a doação nascia expirada, causando erro 400 na geração do PIX. A conversão agora é normalizada para `app.timezone` antes do uso.

## 🛠️ Mudanças Técnicas
- Ajusta `MercadoPagoGateway::date()` para aplicar `setTimezone(config('app.timezone', 'UTC'))` após `Date::parse()`.
- Atualiza `DonationTest` para simular `date_of_expiration` com offset `-04:00`, reproduzindo o comportamento do gateway.
- Adiciona asserção de que a doação permanece com status efetivo `Pending` após refresh.

## ⚠️ Impacto/Avisos
- Corrige o fluxo de doação via PIX que podia falhar com HTTP 400 por expiração incorreta.
- Não há alterações em banco de dados, variáveis de ambiente ou dependências.
- O teste de feature cobre o cenário de fuso horário do Mercado Pago.


close #94

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)