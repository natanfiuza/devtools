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

Cron do deploy, a cada minuto:

```cron
* * * * * /var/www/devtools/deploy/deploy.sh >> /var/www/devtools/storage/logs/deploy.log 2>&1
```

## 2. Deploy

Automatico: o cron chama o [`deploy/deploy.sh`](../deploy/deploy.sh) a cada
minuto e ele sai na hora quando o `main` do remoto nao mudou.

```bash
sudo -u www-data /var/www/devtools/deploy/deploy.sh   # manual, se precisar
tail -f /var/www/devtools/storage/logs/deploy.log     # acompanhar
```

O que ele faz, na ordem: `git fetch` + `pull --ff-only`; `composer install
--no-dev`; `npm ci` **apenas quando o `package-lock.json` muda** (o hash fica
em `storage/framework/npm-lock.sha256`, porque `npm ci` leva minutos); build
do Vite e do bundle de SSR dentro de uma release nova; troca dos symlinks
`public/build` e `bootstrap/ssr`; limpeza das releases antigas; `migrate
--force`; `optimize`; `queue:restart`; reinicio do SSR.

Duas garantias que valem entender:

- **Nada de HTML novo com assets ausentes.** O build inteiro acontece numa
  release propria e a publicacao e o `mv -T` de um symlink, que e atomico.
  Quem carregou a pagina antes da troca continua encontrando o JS antigo,
  porque a release anterior segue no disco (as 3 ultimas ficam).
- **Uma execucao por vez.** Um `flock` em `/tmp/devtools-deploy.lock` faz a
  segunda chamada sair em silencio; deploy demorado nao empilha.

O `deploy.sh` precisa de `php`, `composer`, `node`, `npm` e `git` no PATH; ele
monta um PATH explicito porque o cron nao herda o do shell de login. Se faltar
algum, o log diz qual.

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

**Credenciais de terceiros** (a partir da F5: banco, IA, e-mail): trocar no
`.env` e rodar `php artisan optimize:clear && php artisan optimize` (o deploy
seguinte faz isso sozinho). `AI_API_KEY` e `AI_MODEL` vivem no `.env`/tabelas
por regra do projeto — nunca no codigo.

**Senha do banco**: `ALTER USER 'devtools'@'localhost' IDENTIFIED BY '...'`,
atualiza o `.env` e `optimize:clear`.

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
| Pagina sem HTML no servidor | SSR desligado ou processo morto (`curl 127.0.0.1:13714/health`) |
| "command not found" no cron | binario fora do PATH do `deploy.sh` (o log diz qual) |
| Deploy nao roda | `flock` preso: `fuser /tmp/devtools-deploy.lock` |
| Assets antigos servidos | release ainda na janela das 3 guardadas: normal; a limpeza e por data |

Depois de mexer em permissao ou arquivo na mao, o par canonico e:

```bash
chown -R www-data:www-data storage bootstrap/cache
php artisan optimize:clear && php artisan optimize
```
