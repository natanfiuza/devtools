# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: envia exportacao de dados por email antes do expurgo de contas
```

---

## 🎯 Resumo

Garante conformidade com LGPD/GDPR ao assegurar que o usuário receba uma cópia completa dos seus dados pessoais em JSON antes de sua conta ser definitivamente anonimizada após o período de carência de 30 dias. Antes desta mudança, a anonimização era irreversível e o usuário perdia acesso aos próprios dados sem qualquer devolutiva formal.

## 🛠️ Mudanças Técnicas

- Adiciona `App\Notifications\AccountPurgedNotification` que envia e-mail com anexo JSON (`application/json`) contendo o export completo da conta, respeitando o `locale` do usuário.
- Altera o comando `accounts:purge-deleted` para invocar `ExportUserData` e disparar a notificação **de forma síncrona antes** da chamada de `AnonymizeUser`.
- Envolve cada iteração do loop em `try/catch`, registrando falhas em `Log::error` e reportando via `$this->error()` sem interromper o processamento dos demais usuários.
- Adiciona traduções de `mail.account_purged` em `en`, `es`, `fr`, `pt_BR` e `pt_PT`.
- Cria testes de feature (`PurgeDeletedUsersTest`) cobrindo o caminho feliz (envio + anonimização), o caso de conta fora da carência e a falha de envio (conta preservada).

## ⚠️ Impacto/Avisos

- **Comportamento transacional:** se o envio do e-mail falhar, a conta **não é anonimizada** e permanecerá elegível para nova tentativa na próxima execução do comando — decisão intencional para não destruir dados sem devolutiva ao titular.
- **Envio síncrono:** o e-mail é disparado dentro do loop do comando; em grandes volumes, isso pode aumentar o tempo de execução do `artisan`. Monitorar tempo de cron.
- **Tamanho do anexo:** exports muito grandes podem ultrapassar limites do provedor SMTP/serviço de e-mail. Recomenda-se monitorar o tamanho médio do JSON em produção.
- **Banco de dados:** sem novas migrations ou alterações de schema.
- **Env/dependências:** nenhuma nova variável de ambiente ou dependência de Composer adicionada.

close #64

---

[![GitPR](https://img.shields.io/badge/GitPR-0_errors_%C2%B7_4_warnings-yellow)](https://gitpr.natanfiuza.dev.br/)