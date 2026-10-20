# RUNBOOK - devtools.natanfiuza.dev.br

Operacao do servidor. Tudo aqui e para rodar **no VPS**, como root ou com
`sudo`. O ambiente local esta no [README](../README.md).

Caminhos usados em todo o documento:

| O que | Onde |
|---|---|
| Aplicacao | `/var/www/devtools` |
| Usuario do PHP e do supervisor | `www-data` |
| Releases de assets | `/var/www/devtools/storage/app/builds/<AAAAMMDDHHMMSS>` |
| Logs | `/var/www/devtools/storage/logs/` |
| Config do supervisor | `/etc/supervisor/conf.d/` |

## 1. Instalacao inicial (uma vez)

Pacotes: PHP 8.3 (+ `pdo_mysql`, `mbstring`, `intl`, `fpm`), Composer 2.10+,
Node 22+ com npm, MariaDB 11.x, nginx, supervisor, git, cron, `flock` (pacote
`util-linux`, ja vem em qualquer distro).

```bash
git clone <repo> /var/www/devtools
cd /var/www/devtools

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Banco (no MariaDB 11.x a collation e `utf8mb4_unicode_ci`; `utf8mb4_0900_ai_ci`
e do MySQL 8 e nao existe aqui):

```sql
CREATE DATABASE devtools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'devtools'@'localhost' IDENTIFIED BY 'senha-forte';
GRANT ALL PRIVILEGES ON devtools.* TO 'devtools'@'localhost';
FLUSH PRIVILEGES;
```

`.env` de producao, no minimo:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://devtools.natanfiuza.dev.br

DB_CONNECTION=mysql
DB_DATABASE=devtools
DB_USERNAME=devtools
DB_PASSWORD=senha-forte

# O processo do SSR sobe pelo supervisor (secao 4). Sem ele ligado, as
# paginas continuam funcionando, mas sem HTML renderizado no servidor.
INERTIA_SSR_ENABLED=true

# Medicao (GA4). Vazio desliga a medicao - e o que se quer em local e em teste:
# so este .env carrega o id. Passo a passo na secao 9.
ANALYTICS_GA4_ID=
```

Permissoes e a primeira carga:

```bash
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
```

Nginx: o `root` aponta para `public`, com `try_files $uri $uri/ /index.php?$query_string`.
Os arquivos de `/build` sao servidos direto do disco (com `expires 1y` e
`immutable` para `/build/assets/`, porque o Vite poe hash no nome), e o
`index.php` vai para o FPM. O dominio deve estar com TLS (certbot).

Cron do deploy, a cada minuto, no crontab do **mesmo usuario que fez o clone** (secao 1:
`root`) - o script faz `git fetch`, que escreve em `.git/objects`:

```cron
* * * * * /var/www/devtools/deploy/deploy-instaled.sh >> /var/www/devtools/storage/logs/deploy.log 2>&1
```

## 2. Deploy

Automatico: o cron chama a **copia instalada** (`deploy-instaled.sh`) a cada
minuto e ela sai na hora quando o `main` do remoto nao mudou. Trocar essa copia
quando o `deploy.sh` do repo muda e um passo manual: o passo a passo esta em
[deploy/deploy-instaled.md](../deploy/deploy-instaled.md).

```bash
sudo /var/www/devtools/deploy/deploy-instaled.sh          # manual (mesmo usuario do cron)
sudo /var/www/devtools/deploy/deploy-instaled.sh --force  # refaz sem commit novo
tail -f /var/www/devtools/storage/logs/deploy.log         # acompanhar
```

O que ele faz, na ordem: `git fetch` + `pull --ff-only`; dependencias **apenas
quando o manifesto muda** — `composer install --no-dev` (com queda para
`composer update` se o lock do servidor nao servir mais) e `npm install`, cada
um com o hash do manifesto guardado em `storage/framework/<nome>-deps.sha256`;
build do Vite e do bundle de SSR dentro de uma release nova; troca dos symlinks
`public/build` e `bootstrap/ssr`; limpeza das releases antigas; `migrate
--force`; `optimize`; `queue:restart`; reinicio do SSR. Com `--force` (uso
manual) o corpo roda mesmo sem commit novo.

Nenhum lockfile e versionado (`package-lock.json` e `composer.lock` estao no
`.gitignore`, ver ADR-014): o servidor tem os locks do ultimo install, e nenhum
deles acompanha o manifesto. Por isso o gatilho e o hash do `package.json` /
`composer.json` — nao ha lock confiavel para comparar —, e por isso o
`composer install` **recusa o lock** (exit 4, sem instalar nada) quando o
manifesto pede um pacote que ele nao tem, caindo para `composer update`. O hash
so e gravado depois de uma instalacao que terminou bem: um deploy que morreu no
meio deixa o hash para tras, e o proximo deploy refaz o que faltou.

Duas garantias que valem entender:

- **Nada de HTML novo com assets ausentes.** O build inteiro acontece numa
  release propria e a publicacao e o `mv -T` de um symlink, que e atomico.
  Quem carregou a pagina antes da troca continua encontrando o JS antigo,
  porque a release anterior segue no disco (as 3 ultimas ficam).
- **Uma execucao por vez.** Um `flock` em
  `storage/framework/locks/deploy.lock` faz a segunda chamada sair em
  silencio (com `--force`, ela avisa e sai com codigo 1, para o operador nao
  achar que rodou); deploy demorado nao empilha.

O `deploy.sh` precisa de `php`, `composer`, `node`, `npm` e `git` no PATH; ele
monta um PATH explicito porque o cron nao herda o do shell de login. Se faltar
algum, o log diz qual.

### Quando o deploy falha no meio

**Ele nao e retentado.** O `set -euo pipefail` aborta na primeira falha e o
`git pull` ja moveu o HEAD; a execucao seguinte do cron ve
`HEAD == origin/main` e sai na hora. Se a falha for antes da troca do symlink,
o site fica com **o PHP novo e o JS velho** — o sintoma classico e chave de
traducao crua na tela, porque o texto vem do servidor (idioma atual) e o
bundle servido e o anterior. `migrate`, `optimize`, `queue:restart` e o
reinicio do SSR tambem nao rodam nesse caso.

Comece pelo log: ele diz em que passo parou.

```bash
tail -150 /var/www/devtools/storage/logs/deploy.log
ls -l /var/www/devtools/public/build /var/www/devtools/bootstrap/ssr
ls -1dt /var/www/devtools/storage/app/builds/* | head -5
```

Se `public/build` **nao** for um symlink (ou apontar para uma release antiga),
a publicacao nunca aconteceu. O conserto e rodar o script **com `--force`**
depois de resolver a causa:

```bash
sudo /var/www/devtools/deploy/deploy-instaled.sh --force
```

Sem a flag ele sai na hora (`HEAD == origin/main`), entao uma execucao manual
sem `--force` nao conserta nada aqui. Qualquer commit no `main` tambem dispara
o deploy inteiro pelo cron — e o gatilho por hash garante que uma instalacao
que nao terminou seja refeita junto.

Isso e decisao consciente, nao descuido: as alternativas (retomar do ponto de
falha, desfazer o pull automaticamente, publicar assets antes das dependencias)
custam mais do que o problema que evitam. Ver ADR-014.

### Rollback

O codigo volta com `git revert` (ou `git checkout` do commit bom) e o cron
refaz o deploy — os assets vao junto, na release nova. Se o problema for so o
asset publicado, de para apontar o symlink para a release anterior:

```bash
ls -1dt /var/www/devtools/storage/app/builds/*   # releases, da mais nova para a mais velha
ln -sfn /var/www/devtools/storage/app/builds/<release-antiga>/build /var/www/devtools/public/build.tmp
mv -T /var/www/devtools/public/build.tmp /var/www/devtools/public/build
sudo supervisorctl restart devtools-ssr
```

O proximo deploy desfaz isso (ele sempre republica a release que acabou de
construir), entao trate como paliativo.

## 3. Migrations

O deploy roda `php artisan migrate --force` **sozinho**, no meio de um site no
ar. Regra da casa: **migration tem que ser retrocompativel** — o codigo velho
precisa continuar funcionando com o schema novo, porque as duas versoes
convivem por alguns segundos (FPM com PHP ja carregado, fila, SSR).

Na pratica: adicionar coluna com default ou nullable; renomear em dois passos
(coluna nova + copia, depois remocao numa fase seguinte); nunca `DROP` no
mesmo deploy que para de usar a coluna.

## 4. Supervisor (fila e SSR)

```bash
sudo cp /var/www/devtools/deploy/supervisor/*.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status
```

Sao dois programas:

- `devtools-worker` — `queue:work`, 2 processos (`numprocs=2`), log em
  `storage/logs/worker.log`.
- `devtools-ssr` — `artisan inertia:start-ssr`, um processo, log em
  `storage/logs/ssr.log`. `stopasgroup`/`killasgroup` estao ligados porque o
  artisan e so o pai: o servidor de verdade e um processo node filho, e sem
  matar o grupo o node fica orfao segurando a 13714.

### SSR

```bash
curl -s http://127.0.0.1:13714/health          # {"status":"OK",...}
sudo supervisorctl restart devtools-ssr
tail -n 50 /var/www/devtools/storage/logs/ssr.log
```

Se o processo cair, **o site nao cai junto**: o Laravel tenta o SSR e, ao
falhar, entrega a pagina renderizada no cliente (com um aviso em log). O que
se perde e o HTML pronto (SEO e primeira pintura). Por isso o gate
`INERTIA_SSR_ENABLED`: `false` no local, para ninguem precisar de node rodando
para desenvolver.

O bundle do SSR fica em `bootstrap/ssr` (symlink para a release atual) e e
carregado no boot do processo — dai o `supervisorctl restart` no fim do deploy.

## 5. Fila

`QUEUE_CONNECTION=database`. A fila e reiniciada pelo deploy (`queue:restart`),
o que faz os workers terminarem o job atual e sairem para pegar codigo novo.

```bash
php artisan queue:failed            # jobs que falharam (tabela failed_jobs)
php artisan queue:retry all
php artisan queue:forget <id>
php artisan queue:flush             # limpa os falhados
tail -f storage/logs/worker.log
```

### Fila do AI SQL Builder

O job `ProcessAiQuery` leva so o ULID; o schema fica comprimido no banco e e
apagado quando a solicitacao termina. O worker e o mesmo `devtools-worker` da
F0 (`queue:work --tries=2 --timeout=120`), mas os tempos precisam estar nesta
ordem:

| Etapa | Valor | Onde |
| ----- | ----- | ---- |
| Requisicao ao provedor | 60 s | `AI_TIMEOUT` |
| Job | 90 s | `AI_JOB_TIMEOUT` |
| Worker | 120 s | supervisor (`--timeout=120`) |
| `retry_after` da fila | **150 s** | `DB_QUEUE_RETRY_AFTER` |

**O `.env` de producao precisa de `DB_QUEUE_RETRY_AFTER=150`.** Se ficar o
valor antigo (menor que o timeout do job), o mesmo job pode ser entregue a
outro worker enquanto o primeiro ainda espera o provedor - duas chamadas e dois
debitos. Depois de mudar, reinicie os workers:

```bash
php artisan queue:restart
sudo supervisorctl restart devtools-worker
```

Operacao dos comandos (rodam no cron, nao no scheduler):

```bash
# Antes da primeira execucao em producao, conferir o volume:
php artisan ai:prune-cache --dry-run
php artisan ai:reconcile --dry-run

# Limpeza diaria do cache expirado (TTL de 30 dias):
5 4 * * * cd /var/www/devtools && flock -n /tmp/ai-prune.lock php artisan ai:prune-cache >> storage/logs/ai.log 2>&1

# Solicitacoes presas (worker morto, deploy no meio de um job):
*/10 * * * * cd /var/www/devtools && flock -n /tmp/ai-reconcile.lock php artisan ai:reconcile >> storage/logs/ai.log 2>&1
```

- `ai:prune-cache` remove as linhas de `ai_cache` com `expires_at` no passado.
  A leitura ja as ignora; o comando so libera espaco.
- `ai:reconcile` fecha `queued|processing` mais antigas que `AI_JOB_TIMEOUT` +
  `AI_RECONCILE_GRACE_SECONDS` (90 s + 30 s por padrao), marca `timeout` e
  **estorna a reserva**. Sem ele, um worker morto deixaria a cota debitada para
  sempre e o dedupe bloquearia o mesmo pedido ate o TTL.

Conferir o estado sem abrir o banco:

```bash
php artisan tinker --execute="dump(App\\Models\\AiRequest::query()->whereIn('status', ['queued','processing'])->count());"
grep 'ai_request_id' storage/logs/laravel.log | tail -n 20
grep 'AI request failed' storage/logs/laravel.log | tail -n 20
```

Os logs trazem `ai_request_id`, `error_code`, `tokens_in/out` e a classe da
excecao. **Nunca** trazem schema, descricao ou prompt - se aparecer texto de
schema num log, e bug e vazamento: abra incidente e limpe o arquivo.

## 6. Backup

Banco (diario, 3h da manha, guardando 14 dias):

```cron
0 3 * * * mysqldump --single-transaction --quick --default-character-set=utf8mb4 devtools | gzip > /var/backups/devtools-$(date +\%F).sql.gz && find /var/backups -name 'devtools-*.sql.gz' -mtime +14 -delete
```

`--single-transaction` evita travar a tabela (InnoDB). Restaurar:

```bash
gunzip -c /var/backups/devtools-2026-10-13.sql.gz | mysql devtools
```

**O que o backup do banco nao cobre:** o `.env` (com a `APP_KEY`) e o
`storage/app` (arquivos enviados). Guarde o `.env` em lugar seguro fora do
servidor: **sem a `APP_KEY` original, os dados criptografados do banco
(sessoes, colunas `encrypted`) viram lixo**. Trocar a `APP_KEY` e uma operacao
de risco, nao rotina — se precisar, veja a secao 7.

## 7. Rotacao de chaves

**`APP_KEY`** — so troque se houver suspeita de vazamento. A troca invalida
sessoes, cookies e tudo que estiver criptografado com a chave antiga. Antes,
leia o paragrafo acima e combine a janela:

```bash
php artisan down --secret="<token>"     # mantenha o acesso pelo /<token>
cp .env .env.bak-$(date +%F)
php artisan key:generate
php artisan optimize:clear && php artisan optimize
php artisan session:table 2>/dev/null; # se usar sessoes no banco: limpe a tabela
php artisan up
```

Se houver coluna criptografada, o caminho e descriptografar com a chave antiga
e recriptografar com a nova (comando proprio, nao improvisar em producao).

**Credenciais de terceiros** (banco, IA, e-mail): trocar no `.env` e rodar
`php artisan optimize:clear && php artisan optimize` (o deploy seguinte faz
isso sozinho). `AI_API_KEY` e `AI_MODEL` vivem no `.env`/tabelas por regra do
projeto — nunca no codigo.

**Chave do provedor de IA (DeepSeek)** — o provedor e o modelo vem de
`AI_BASE_URL` e `AI_MODEL`; a chave, de `AI_API_KEY`. Para rotacionar:

```bash
# 1. Gere a chave nova no painel do DeepSeek e guarde-a fora do servidor.
# 2. Troque APENAS essa linha no .env (nao imprima o arquivo no terminal):
sed -i 's|^AI_API_KEY=.*|AI_API_KEY=<chave-nova>|' .env

# 3. Recarregue a config e reinicie o worker para o novo processo nascer com ela:
php artisan optimize:clear && php artisan optimize
php artisan queue:restart

# 4. Confirme que o worker voltou e nao ha job preso:
sudo supervisorctl status devtools-worker
php artisan queue:monitor database:default --max=50
```

- O `queue:restart` e obrigatorio: o worker em memoria continua com a chave
  antiga ate terminar o job atual e sair.
- A chave antiga deve ser **revogada no painel** logo depois do smoke da nova.
  Se a antiga tiver vazado, revogue primeiro e rotacione depois: as
  solicitacoes em voo falham com `provider_unavailable` e sao estornadas.
- Nunca cole a chave em ticket, chat, log ou PR. Se aparecer em log, trate como
  vazamento: revogue, rotacione e limpe o arquivo.
- Trocar `AI_MODEL` **muda os hashes do cache** (o modelo entra no payload
  canonico): as respostas antigas continuam validas para os pedidos antigos,
  mas os novos chamam o provedor. Nao e preciso limpar o cache.

**Senha do banco**: `ALTER USER 'devtools'@'localhost' IDENTIFIED BY '...'`,
atualiza o `.env` e `optimize:clear`.

**Login social (Google e GitHub)**: criar os apps OAuth nos consoles de cada
provedor, preencher as credenciais no `.env` e rodar `php artisan optimize:clear && php artisan optimize`.
O passo a passo completo esta em [guides/google-auth.md](guides/google-auth.md) e
[guides/github-auth.md](guides/github-auth.md).

**Medicao (GA4)**: criar a propriedade nova no Google, trocar
`ANALYTICS_GA4_ID` no `.env` e rodar `php artisan optimize:clear && php artisan optimize`.
Sem redeploy e sem migracao: o historico fica na propriedade antiga. O passo a
passo esta em [guides/google-analytics.md](guides/google-analytics.md#5-rotacao-do-id).

## 8. Logs e diagnostico

```bash
tail -f storage/logs/laravel.log     # aplicacao
tail -f storage/logs/deploy.log      # cron do deploy
tail -f storage/logs/worker.log      # fila
tail -f storage/logs/ssr.log         # SSR
php artisan pail                     # log ao vivo, com filtro (local ou VPS)
php artisan about                    # versoes, drivers, cache
```

Problemas que ja sabemos diagnosticar:

| Sintoma | Causa provavel |
|---|---|
| 502 no nginx | php-fpm fora do ar (`systemctl status php8.3-fpm`) |
| 500 em toda pagina | `.env` errado ou `storage` sem permissao de escrita |
| CSS/JS 404 apos deploy | build falhou ou o symlink nao trocou: `ls -l public/build` e o `deploy.log` |
| Chave de traducao crua na tela | PHP novo com JS velho: o deploy morreu antes do build e o cron **nao retenta sozinho** — `deploy.sh --force` refaz (secao 2, "Quando o deploy falha no meio") |
| Pagina sem HTML no servidor | SSR desligado ou processo morto (`curl 127.0.0.1:13714/health`) |
| "command not found" no cron | binario fora do PATH do `deploy.sh` (o log diz qual) |
| Deploy nao roda | `flock` preso: `fuser storage/framework/locks/deploy.lock` |
| Assets antigos servidos | release ainda na janela das 3 guardadas: normal; a limpeza e por data |
| Medicao nao aparece no GA | id vazio no `.env` (medicao desligada), banner sem aceite, ou config em cache: `optimize:clear && optimize` (secao 9) |
| Teste do Google ("Teste sua tag", Tag Assistant) acusa tag ausente | **Esperado** quando o banner nao foi aceito naquela sessao: o teste abre o site sem o cookie `consent`, e sem aceite nao ha tag (ADR-013). Aceite o banner na sessao do teste; a prova real e o Tempo real (secao 9) |
| AI SQL Builder responde 503 `ai_unavailable` | Falta `AI_BASE_URL`, `AI_MODEL` ou `AI_API_KEY` no `.env`, ou a config esta em cache: `php artisan about` mostra o provedor? `optimize:clear && optimize` (secao 7) |
| Solicitacao fica em `queued` e nunca sai | Worker parado (`supervisorctl status devtools-worker`), fila errada (`QUEUE_CONNECTION`) ou `retry_after` menor que o timeout do job; veja `tail -f storage/logs/worker.log` e `php artisan queue:failed` |
| Solicitacao presa em `queued\|processing` por mais de ~3 min | Worker morreu no meio: `ai:reconcile --dry-run` primeiro, depois sem `--dry-run`; a reserva e estornada e a solicitacao vira `timeout`. Antes, veja se o job esta em `failed_jobs` |
| `provider_unavailable` em massa | Chave revogada/expirada, `AI_BASE_URL` errado ou provedor fora. Teste o provedor a parte; a resposta HTTP nao vai para o log - use a mensagem da excecao (sem corpo) e o status |
| `unsafe_sql` com SQL que parece leitura | O gate lexical (ADR-041) barrou: `;` interno, DDL/DML misturado ou funcao perigosa. Confira o SQL gerado na resposta; nao afrouxe o gate sem ADR |
| Cache cresce sem parar | `ai:prune-cache` nao esta no cron (secao 5); confira `SELECT COUNT(*) FROM ai_cache WHERE expires_at < NOW()` (nunca apague na mao com o worker rodando) |
| Resposta veio do cache para outro schema | Nao deve acontecer: o hit compara o payload inteiro (ADR-016), nao so o MD5. Se acontecer, e bug - guarde o `prompt_hash` e abra incidente; a linha expira em 30 dias |

Depois de mexer em permissao ou arquivo na mao, o par canonico e:

```bash
chown -R www-data:www-data storage bootstrap/cache
php artisan optimize:clear && php artisan optimize
```

## 9. Medicao (Google Analytics)

O carregamento ja existe no codigo (ADR-013, ADR-032 e ADR-038): ativar e preencher uma
variavel. **O passo a passo completo - criar a propriedade, conferir e o que nao
ligar na console - esta em [guides/google-analytics.md](guides/google-analytics.md)**.
O que segue e a parte de operacao.

- `ANALYTICS_GA4_ID` **vazio desliga a medicao**, mesmo com aceite. So o `.env`
  de producao carrega o id - em local, em teste e em CI fica vazio de proposito.
- Sem aceite no banner, **nenhuma** requisicao sai para o Google: a tag e
  injetada no cliente, uma vez, e o HTML do servidor nunca tem GA, nem no SSR.
- Depois de mexer no `.env`:
  `php artisan optimize:clear && php artisan optimize`. O `deploy.sh` nao toca
  no `.env` (ADR-014).
- A area da conta e medida junto com o site; o painel administrativo (`/admin`)
  **nao** e - la nao ha banner nem tag, nem com aceite.
- Desligar e o mesmo caminho ao contrario: esvaziar a variavel e rodar os dois
  comandos.
