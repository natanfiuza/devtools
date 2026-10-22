# Guia - Cron em producao

Como o site se atualiza, executa as rotinas de manutencao e processa a fila em
producao. Sao **tres pecas independentes**: o cron do deploy, o cron do
agendador e o supervisor da fila. Falta de qualquer uma delas nao derruba a
pagina, mas quebra uma parte silenciosa do sistema.

```text
crontab (usuario root)
├── a cada minuto  /var/www/devtools/deploy/deploy-instaled.sh
└── a cada minuto  cd /var/www/devtools && php artisan schedule:run

supervisor (usuario www-data)
└── devtools-worker  -> 2 x php artisan queue:work database
```

## 1. Visao geral

| Peca | O que executa | Sem ela |
| ---- | ------------- | ------- |
| Deploy | `deploy/deploy-instaled.sh` a cada minuto | Nenhum codigo novo chega ao servidor |
| Agendador | `php artisan schedule:run` a cada minuto | `payments:expire`, `webhooks:prune`, `accounts:purge-deleted` e `history:prune` nunca rodam |
| Worker | supervisor `devtools-worker` com 2 processos | Consultas de IA e comandos agendados ficam parados na fila |

O deploy e o agendador sao linhas de `crontab`; o worker e um programa do
`supervisor`, que sobe sozinho no boot e reinicia se cair. Os tres rodam no
servidor (`/var/www/devtools`), nao na maquina de desenvolvimento.

## 2. O cron de deploy

```cron
* * * * * /var/www/devtools/deploy/deploy-instaled.sh >> /var/www/devtools/storage/logs/deploy.log 2>&1
```

O cron do VPS **nao roda o `deploy/deploy.sh` do repositorio**. Ele roda uma
copia instalada, `deploy/deploy-instaled.sh`, que nao esta no git. O motivo e o
`git pull --ff-only`: se o arquivo executado fosse o do repositorio e alguem o
editasse no servidor, o pull seguinte abortaria com "local changes would be
overwritten". A copia fora do versionamento mantem o arquivo do repo intocado.

Consequencia pratica: **mudanca no `deploy.sh` nao chega sozinha em producao**.
O pull traz o arquivo novo, mas quem roda continua sendo a copia antiga ate a
troca manual descrita em [deploy/deploy-instaled.md](../../deploy/deploy-instaled.md).

O que a copia faz, na ordem:

1. `git fetch` e `git pull --ff-only` no `main`;
2. dependencias **somente quando o manifesto muda** - `composer install --no-dev`
   (com queda para `composer update` se o lock do servidor nao servir mais) e
   `npm install`, cada um com o hash em `storage/framework/<nome>-deps.sha256`;
3. build do Vite e do bundle de SSR dentro de uma release nova em
   `storage/app/builds/`;
4. troca atomica dos symlinks `public/build` e `bootstrap/ssr` (`mv -T`);
5. limpeza das releases antigas (as 3 ultimas ficam);
6. `php artisan migrate --force`;
7. `php artisan optimize`;
8. `php artisan queue:restart` e reinicio do processo de SSR.

Duas garantias:

- **Nada de HTML novo com assets ausentes.** O build inteiro acontece numa
  release propria e a publicacao e a troca de um symlink. Quem carregou a
  pagina antes da troca continua encontrando o JS antigo.
- **Uma execucao por vez.** Um `flock` em
  `storage/framework/locks/deploy.lock` faz a segunda chamada sair em
  silencio. Deploy demorado nao empilha.

Por que a cada minuto: quando o `main` nao mudou, o script faz `git fetch`, ve
`HEAD == origin/main` e sai em segundos. E o mesmo desenho "commitou, publicou"
sem pipeline externo.

O script monta um PATH explicito porque o cron nao herda o PATH do shell de
login. Ele precisa de `php`, `composer`, `node`, `npm` e `git`; se faltar algum,
o log diz qual.

## 3. O cron do agendador

```cron
* * * * * cd /var/www/devtools && php artisan schedule:run >> storage/logs/schedule.log 2>&1
```

O `bootstrap/app.php` le a tabela `task_schedules` e registra cada linha ativa
como um `ExecuteCommand` na fila `database`. A expressao da linha vira a
frequencia do evento:

| Expressao | Como e interpretada |
| --------- | ------------------- |
| `dailyAt:03:00` | todos os dias naquele horario |
| `hourly` | de hora em hora |
| `everyMinute` | a cada minuto |
| qualquer outra | `cron($expression)` - sintaxe cron padrao |

As linhas semeadas pelas migrations:

| Comando | Expressao | Para que |
| ------- | --------- | -------- |
| `accounts:purge-deleted --days=30` | `dailyAt:03:00` | expurga contas com 30 dias de carencia |
| `payments:expire` | `*/5 * * * *` | marca `pending -> expired` em checkout e doacao vencidos |
| `webhooks:prune --days=30` | `30 3 * * *` | apaga eventos de webhook com mais de 30 dias |
| `history:prune` | `45 3 * * *` | apaga o historico de consultas com mais de 90 dias (F7) |

O fuso do agendador e `America/Recife` (`config/app.php`, `APP_TIMEZONE`). O
`schedule:list` mostra os horarios ja convertidos.

Nem toda rotina esta em `task_schedules`: `ai:prune-cache` e `ai:reconcile` sao
**linhas de crontab proprias** (secao 5 do RUNBOOK), com `flock` para nao
empilhar. Elas nao dependem do agendador.

Dois detalhes que confundem na operacao:

- **`last_run_at` existe na tabela, mas nunca e atualizado.** Nao use a coluna
  para saber se a rotina rodou; olhe o `schedule.log`/`worker.log` ou o efeito
  no banco.
- **Nao ha tela de agendamentos.** Mudar horario e uma migration (ou SQL no
  banco); a lista e lida a cada `schedule:run`.
- Se uma linha estiver quebrada (expressao invalida, por exemplo), o loop de
  registro engole a excecao e escreve `[Scheduler] Agendamento ignorado: ...`
  no `laravel.log`; as outras linhas continuam.

## 4. A fila e o supervisor

```ini
[program:devtools-worker]
command=php /var/www/devtools/artisan queue:work database --sleep=3 --tries=2 --timeout=120 --max-time=3600
user=www-data
numprocs=2
```

O agendador **nao executa o comando**: ele enfileira um `ExecuteCommand`. Quem
executa e o worker. Sem worker, os jobs de agendamento e as consultas de IA
acumulam em `jobs` sem sair do lugar.

O `queue:restart` no fim do deploy e o que faz os workers recarregarem o codigo
novo: o processo atual termina o job em andamento e morre; o supervisor sobe
outro com o codigo recem-baixado.

As propriedades da classe do job vencem os limites do command line:

| Job | `tries` | `timeout` |
| --- | ------- | --------- |
| `ExecuteCommand` (agendador) | 1 | 0 (sem timeout) |
| `ProcessAiQuery` | 2 | 90 s |
| `ProcessWebhookEvent` | 3 | 60 s |

`ExecuteCommand` com `tries=1` e proposital: repetir um comando de manutencao
pode duplicar efeito. Uma execucao perdida so volta no proximo horario.

`DB_QUEUE_RETRY_AFTER` fica em **150 s** - maior que o maior `timeout` de job,
para o job nao ser devolvido a fila enquanto ainda esta rodando.

## 5. Por que isso e obrigatorio em producao

Sem o **cron de deploy**, o servidor continua na versao em que foi instalado:
paginas, correcoes e migrations novas nunca chegam.

Sem o **cron do agendador**, a leitura das telas ate se defende (a expiracao de
checkout e de doacao e preguicosa), mas o sistema acumula sujeira:

- checkouts e doacoes `pending` ficam pendurados para sempre no banco;
- `webhook_events` cresce sem poda;
- contas excluidas com 30 dias de carencia nunca sao anonimizadas de verdade;
- o historico de consultas cresce sem respeitar a retencao de 90 dias.

Sem o **worker**, consultas de IA ficam eternamente `queued`, `ExecuteCommand`
nunca roda e o usuario ve "processando" ate o reconciliador marcar timeout.

No local, `composer dev` (ou `php artisan dev`) sobe o servidor, o
`queue:listen` e o Vite - **nao sobe o agendador**. Para testar uma rotina, rode
em outro terminal:

```bash
php artisan schedule:work
```

Isso executa o `schedule:run` a cada minuto enquanto o processo estiver aberto.
Tambem vale chamar o comando direto (`php artisan history:prune --dry-run`).

## 6. Instalacao e verificacao

O crontab e do **mesmo usuario que fez o clone** (normalmente `root`), porque o
deploy escreve em `.git/objects`. Para conferir:

```bash
crontab -l
```

Deve listar as duas linhas (deploy e agendador). Depois:

```bash
supervisorctl status devtools-worker:*
php artisan schedule:list
php artisan payments:expire --dry-run
php artisan webhooks:prune --dry-run
php artisan history:prune --dry-run
```

Logs:

| Arquivo | O que mostra |
| ------- | ------------ |
| `storage/logs/deploy.log` | cada execucao do deploy, do fetch ao `queue:restart` |
| `storage/logs/schedule.log` | cada `schedule:run` (pouco verboso) |
| `storage/logs/worker.log` | jobs executados, falhas e reinicios |
| `storage/logs/laravel.log` | `[ExecuteCommand]`, `[Scheduler]` e erros da aplicacao |

## 7. Problemas comuns

| Sintoma | Causa provavel | Como conferir/consertar |
| ------- | -------------- | ----------------------- |
| Nada e deployado | cron de outro usuario, trava presa ou `php` fora do PATH | `crontab -l`, `tail deploy.log`, `ps aux \| grep deploy` |
| Rotina nao rodou no horario | worker parado ou `ExecuteCommand` falhou | `supervisorctl status`, `grep ExecuteCommand storage/logs/laravel.log` |
| Rotina rodou duas vezes | linha duplicada em `task_schedules` | `select command, count(*) from task_schedules group by command;` |
| `last_run_at` sempre vazio | a coluna nao e escrita (por desenho) | use os logs; nao e defeito |
| Deploy aborta no `git pull` | arquivo do repo editado no servidor | resolva o conflito e rode `deploy-instaled.sh --force` |
| Deploy aborta no meio | falha em build, migrate ou permissao | `tail -150 deploy.log`; o proximo cron nao retenta sem commit novo - use `--force` |
| Fila cresce sem parar | worker morto ou com erro | `supervisorctl restart devtools-worker:*`, `tail worker.log` |

## 8. Mapa de arquivos

| Arquivo | Papel |
| ------- | ----- |
| `deploy/deploy.sh` | fonte versionada do deploy |
| `deploy/deploy-instaled.sh` (VPS) | copia que o cron realmente executa |
| [deploy/deploy-instaled.md](../../deploy/deploy-instaled.md) | como trocar a copia instalada |
| [deploy/supervisor/devtools-worker.conf](../../deploy/supervisor/devtools-worker.conf) | configuracao do worker |
| [bootstrap/app.php](../../bootstrap/app.php) | le `task_schedules` e registra o agendador |
| [app/Models/TaskSchedule.php](../../app/Models/TaskSchedule.php) | modelo da tabela de rotinas |
| [app/Jobs/ExecuteCommand.php](../../app/Jobs/ExecuteCommand.php) | job que executa o comando agendado |
| `database/migrations/*_seed_*_schedules.php` | seed das rotinas |
| [app/Console/Commands/](../../app/Console/Commands/) | comandos de manutencao |
| [docs/RUNBOOK.md](../RUNBOOK.md) | operacao, backup e rotacao de chaves |
