# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige formato de expiração PIX e detalha erros do Mercado Pago
```

---

## 🎯 Resumo

O Mercado Pago rejeitava cobranças PIX e preferências com expiração (HTTP 400, `error code 23`) porque as datas eram serializadas via `toIso8601String()`, que em UTC gera sufixo `Z` sem milissegundos — formato não aceito pela API, que exige offset explícito (ex.: `-03:00`) e precisão de milissegundos. Além disso, o diagnóstico retornado ao usuário era pobre: apenas o `error` curto do gateway era propagado, perdendo a `message` explicativa (ex.: "Collector cannot be the same as payer"), o que dificultava identificar a causa raiz do erro 400 em produção.

## 🛠️ Mudanças Técnicas

- Adiciona helper privado `iso8601(CarbonInterface $date)` que formata com `Y-m-d\TH:i:s.vP` (milissegundos + offset explícito), centralizando o formato exigido pelo gateway.
- Substitui `toIso8601String()` por `iso8601()` em `expiration_date_from`, `expiration_date_to` (preferências) e `date_of_expiration` (PIX).
- `extractErrorDiagnostics()` passa a concatenar `error` + `message` (quando distintos), com limite ampliado de 80 para 160 caracteres.
- Fallback de `cause.description` para `cause.message` ao montar as causas do erro, evitando descrições vazias.
- Testes: valida o formato da data de expiração via regex (`...T...:...:....000-03:00`) e atualiza as asserções de `gatewayError`/`gateway_error` para o novo texto enriquecido.

## ⚠️ Impacto/Avisos

- **Comportamento alterado:** `gatewayError` e `gatewayCauses[].gateway_error` agora podem conter `error: message`. Consumidores que comparam esses valores por igualdade exata precisam ser revisados (logs, dashboards, testes de contrato).
- **Payload alterado:** requisições de PIX e preferências com expiração mudam o formato de data; clientes/parceiros que repliquem o payload devem seguir o mesmo padrão.
- Sem mudanças em banco de dados, variáveis de ambiente ou dependências.

close #92

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)