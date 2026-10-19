# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: torna documento opcional e avisa intenção em análise
```

---

## 🎯 Resumo

A captação de intenções de assinatura Pro deixou de exigir CPF/CNPJ, removendo a principal barreira de conversão para leads fora do Brasil. O formulário de assinatura passou a coletar o código de país (DDI) separadamente, permitindo números internacionais, e o campo de documento virou opcional.

Além disso, o usuário que já enviou um pedido e ainda aguarda análise passa a receber retorno visível: um banner na aba **Faturas** informando o status e um atalho para revisar a solicitação, mais um aviso com contagem regressiva na tela de assinatura após o envio. O objetivo é reduzir pedidos duplicados e o suporte manual de "não sei se meu pedido foi recebido".

## 🛠️ Mudanças Técnicas

- Torna `document` nullable na tabela `subscription_intents` via migration com `change()` (retrocompatível, zero downtime) e ajusta o model, o request e o controller para persistir `null` quando o campo vem vazio.
- Adiciona validação e normalização de `country_code` no `StoreSubscriptionIntentRequest`, concatenando o DDI ao telefone quando ausente e limpando espaços em branco.
- Inclui `pendingIntent` (plano, status, forma de pagamento e data) no payload Inertia da tela de Faturas, carregando a intenção pendente mais recente do usuário com eager loading do plano.
- Cria o banner `intencao-pendente` em `dashboard/Billing.vue` com CTA para `/subscribe?plan={slug}`.
- Reescreve o campo de telefone em `Subscribe.vue` com select de DDI (22 países), detecção automática do DDI já salvo na intenção e DDI padrão por locale.
- Adiciona contador de 30s com botões "Ir agora" e "Permanecer nesta página" após submissão, cancelável também ao focar nos campos do formulário e limpo no `onUnmounted`.
- Atualiza traduções de `account` e `home` em `pt_BR`, `pt_PT`, `en`, `es` e `fr`, com placeholders de telefone e rótulos de documento regionais.
- Substitui o fallback vazio por `—` na notificação por e-mail e na listagem admin quando o documento não é informado.
- Adiciona testes de feature (billing sem/com intenção pendente, submissão sem documento com DDI) e testes de componente para o select de DDI, o banner e o fluxo de contagem regressiva.
- Reformata `admin/Intents.vue` (indentação e exibição de `row.document || '—'`), sem mudança funcional.

## ⚠️ Impacto/Avisos

- **Banco de dados**: nova migration altera `subscription_intents.document` para nullable. O `down()` restaura `NOT NULL` e pode falhar caso existam registros sem documento.
- **Contrato de API/tipos**: `document` passa a ser `string | null` no backend, no tipo `PendingSubscriptionIntent` e no e-mail de notificação.
- **Compatibilidade**: chamadas antigas ao endpoint de assinatura continuam válidas — `document` e `country_code` são opcionais.
- **UX/i18n**: placeholders e rótulos de documento mudaram em todos os locales; revisar textos legais caso o documento seja exigido em alguma jurisdição específica.
- **Sem novas dependências ou variáveis de ambiente.**

close #39

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_2_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)