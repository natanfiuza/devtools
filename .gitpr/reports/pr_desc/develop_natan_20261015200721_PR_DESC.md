# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
chore: altera fuso horário padrão para America/Recife
```

---

## 🎯 Resumo

O fuso horário padrão da aplicação estava fixado em `UTC`, o que fazia com que datas, logs e agendamentos fossem exibidos fora do horário local dos usuários (Recife/Brasília). Esta alteração alinha o comportamento da aplicação ao fuso operacional real do time, eliminando conversões manuais e reduzindo erros de interpretação de horários em relatórios e telas de histórico.

## 🛠️ Mudanças Técnicas

- Altera `config/app.php`: `timezone` de `UTC` para `America/Recife`.
- O `APP_TIMEZONE` (ou valor default) passa a ser o fuso usado por `Carbon`, `now()` e `Illuminate\Support\Date`.
- Impacta a serialização de `created_at`/`updated_at` do Eloquent quando `$dateFormat` não é customizado.
- Afeta a avaliação de expressões cron do scheduler (`Kernel::schedule`) e o cálculo de janelas de `retry_after`/`expires_at` em filas e jobs.
- Não há alteração de código de negócio, apenas o valor de configuração.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migração, alteração de schema ou de dados é necessária. Os registros existentes permanecem gravados com o offset anterior; horários já persistidos podem parecer deslocados em ~3h em relação ao novo fuso até que sejam interpretados novamente.
- **Ambientes/Variáveis:** recomenda-se revisar o `APP_TIMEZONE` em `.env` de cada ambiente (local, staging, produção), pois, se definido explicitamente, ele sobrescreve o valor do arquivo de configuração. Ambientes fora da região de Recife devem alinhar o valor conforme a operação.
- **Dependências:** nenhuma adição, remoção ou atualização de pacotes.
- **Agendamentos:** tarefas scheduladas e cron externos devem ser revisados para evitar execução em horário diferente do esperado após a mudança.
- **Logs e monitoramento:** timestamps de log passarão a refletir o novo fuso; ajustar filtros e dashboards que assumem UTC.

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)