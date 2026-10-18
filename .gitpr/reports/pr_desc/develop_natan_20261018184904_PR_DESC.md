# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: corrige IntlException e padroniza placeholders i18n
```

---

## 🎯 Resumo

Corrige falhas de formatação de datas com fusos em formato de offset (ex.: `+00:00`, `-03:00`), que disparavam `IntlException` no `IntlDateFormatter`. A solução normaliza o timezone para identificadores aceitos pelo ICU. Também padroniza placeholders de tradução de `:param` para `{param}`, ajusta chaves de i18n do painel admin e substitui o texto do logo administrativo pelo componente `AppLogo`.

## 🛠️ Mudanças Técnicas

- Adiciona `Format::timezone()` para normalizar `Z`, `+00:00`, `-00:00` e `+0000` para `UTC`; offsets numéricos para `GMT±HH:MM`; validação via `IntlTimeZone` com fallback para `UTC`.
- Cria `tests/Feature/FormatTest.php` cobrindo datas em UTC, offsets positivos/negativos, timezone nomeado, valores nulos, números, moeda e bytes.
- Altera placeholders de tradução de `:param` para `{param}` nos arquivos `account.php` e `admin.php` de `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Renomeia chaves de i18n em `Subscriptions.vue`: `admin.intents.filter_all` para `admin.intents.status_all`, `admin.payments.filter_all` para `admin.payments.status_all`, `admin.payments.register_cta` para `admin.payments.register` e `admin.payments.account_email` para `admin.payments.email`.
- Substitui o texto fixo do logo no `AdminLayout.vue` pelo componente `AppLogo` e adiciona teste de renderização do logo com link para `/admin`.

## ⚠️ Impacto/Avisos

- Nenhuma migração de banco, variável de ambiente ou dependência nova foi adicionada.
- Código que ainda utilize placeholders no formato `:param` ou as chaves antigas de i18n deixará de resolver corretamente as traduções; é necessário atualizar as referências.
- A normalização de timezone evita erro na formatação de datas com offsets, mas altera o identificador efetivamente usado para `UTC` ou `GMT...` nesses casos.
- Sem alteração de contrato de API ou de banco de dados.

close #35

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_1_warning-yellow)](https://gitpr.natanfiuza.dev.br/)