# deploy-instaled.sh - como atualizar no servidor

O cron do VPS **nao executa o `deploy.sh` do repositorio**: ele executa uma copia,
`/var/www/devtools/deploy/deploy-instaled.sh`. Este e o passo a passo para trocar essa copia
quando o arquivo do repo muda.

| Arquivo | O que e |
|---|---|
| `deploy/deploy.sh` (no repo) | A fonte, versionada. Chega ao servidor pelo `git pull` do proprio cron, junto com o resto do codigo. |
| `deploy/deploy-instaled.sh` (no VPS) | A copia que o cron roda. Nao esta no git, so no servidor. |

O nome trocado existe por um motivo pratico: o deploy faz `git pull --ff-only` no
repositorio. Se o arquivo executado fosse o do repo e alguem o editasse no servidor, o
proximo pull abortaria com "Your local changes would be overwritten". Com a copia fora do
versionamento, o arquivo do repo fica sempre intocado e o pull nunca conflita.

**O que isto implica:** mudanca no `deploy.sh` **nao chega sozinha em producao**. O pull traz
o arquivo novo, mas quem roda continua sendo a copia antiga ate a troca manual daqui.

## Regras de ouro

- `deploy/deploy.sh` no servidor **nunca** e editado. Toda edicao local (se um dia houver)
  vive na copia.
- `deploy-instaled.sh` **nunca** e editado no lugar (com `nano`, `vim` ou `cp` por cima). O
  bash le o script enquanto executa: um arquivo reescrito no meio de uma execucao do cron
  roda lixo. A troca e sempre por um arquivo `.novo` + `mv -T`, que substitui o inode - a
  execucao em andamento termina com o arquivo antigo, intacta.

## 1. Tem versao nova?

```bash
cd /var/www/devtools
diff -q deploy/deploy-instaled.sh deploy/deploy.sh
```

- **Sem saida**: a copia e o arquivo do repo sao identicos - nada a fazer.
- **`Files ... differ`**: ha o que instalar. Veja o que mudou antes de trocar:

```bash
git log --oneline -5 -- deploy/deploy.sh
diff -u deploy/deploy-instaled.sh deploy/deploy.sh
```

Se o diff mostrar linhas que voce **nao** reconhece como mudanca do repo, a copia tem ajuste
local: salve essas linhas antes (elas nao existem em lugar nenhum do git).

## 2. Instalar

A copia nova e byte a byte o arquivo do repo: **ajuste local que a copia velha tenha e o repo
nao tenha se perde na troca**. Confira o `diff -u` da secao 1 e, se o ajuste for correcao de
verdade, leve-o para o `deploy/deploy.sh` (commit) antes de instalar.

```bash
cd /var/www/devtools

# backup datado da copia em uso (o rollback usa ele)
sudo cp -p deploy/deploy-instaled.sh "deploy/deploy-instaled.sh.bak-$(date +%F)"

# monta o arquivo novo num nome temporario e confere
sudo cp deploy/deploy.sh deploy/deploy-instaled.sh.novo
bash -n deploy/deploy-instaled.sh.novo                    # sintaxe: sem saida = ok
diff -q deploy/deploy.sh deploy/deploy-instaled.sh.novo   # sem saida = identico ao repo

# mesmas permissoes e dono da copia atual, e a troca atomica
sudo chown --reference=deploy/deploy-instaled.sh deploy/deploy-instaled.sh.novo
sudo chmod --reference=deploy/deploy-instaled.sh deploy/deploy-instaled.sh.novo
sudo chmod +x deploy/deploy-instaled.sh.novo              # o cron executa o arquivo direto
sudo mv -T deploy/deploy-instaled.sh.novo deploy/deploy-instaled.sh
```

`--reference` copia dono e modo do arquivo em uso (nao importa se e `root`, `www-data` ou
outro). O `chmod +x` deixa o bit de execucao explicito: o cron chama o caminho do arquivo
direto, e um script sem `+x` simplesmente nao roda. Rodando como root, os `sudo` do bloco
sao dispensaveis.

O `mv -T` e a troca em si: atomica, sem risco para uma execucao do cron em andamento. E o
cron nao precisa de nada - a linha dele aponta para o nome `deploy-instaled.sh`, que nao mudou.

## 3. Conferir

O teste e rodar agora, sem esperar commit novo - e para isso que o `--force` existe:

```bash
sudo /var/www/devtools/deploy/deploy-instaled.sh --force
tail -n 20 /var/www/devtools/storage/logs/deploy.log
```

Rode como **o mesmo usuario do cron** - o dono do repositorio, que e quem consegue escrever
em `.git/objects`. No clone feito como `root` (RUNBOOK secao 1) isso e `sudo` puro; como
`www-data` o script morre logo no `git fetch`, com
`error: insufficient permission for adding an object to repository database .git/objects`.
Na duvida, `stat -c '%U' /var/www/devtools/.git` diz o dono e
`sudo crontab -l | grep deploy` mostra o cron.

Isso e um deploy inteiro (build, `migrate --force`, reinicio do SSR), com as instalacoes de
dependencia se os manifestos mudaram desde a ultima vez - pode levar minutos. Espere
"Deploy iniciado" ... "Deploy concluido". Se a trava estiver ocupada (uma execucao do cron em
andamento), ele avisa `Outra execucao em andamento: o --force nao rodou.` e sai com codigo 1:
espere o minuto virar e repita.

**Exemplo (2026-10-15).** A troca daquele dia ativou o gatilho por hash das dependencias e a
flag `--force`. Ate a copia ser trocada, o cron continuava rodando a versao antiga - que
instalava dependencia em todo deploy e nao tinha a flag. O `deploy/deploy.sh` novo no disco
nao muda nada sozinho.

## 4. Rollback

```bash
cd /var/www/devtools
sudo mv -T deploy/deploy-instaled.sh.bak-<data> deploy/deploy-instaled.sh
```

O `<data>` e o sufixo do backup (ex.: `2026-10-15`). A troca vale para a proxima execucao;
uma execucao em andamento termina com a versao com que comecou.

## 5. Problemas comuns

| Sintoma | Causa provavel |
|---|---|
| `git pull` falha com "Your local changes ... would be overwritten" | Editaram `deploy/deploy.sh` no servidor (o que as regras de ouro proibem). `git status --short` mostra o `M`; conserte com `git restore deploy/deploy.sh` - a copia em uso nao e afetada - e o proximo minuto do cron refaz o pull. |
| Troquei a copia e "nada mudou" | O cron aponta para outro arquivo: veja `sudo crontab -l \| grep deploy` (a linha esperada esta abaixo). |
| `insufficient permission for adding an object to repository database` / `fatal: failed to write object` | O script rodou como um usuario que nao escreve no `.git` (tipico: `sudo -u www-data` num repositorio do `root`). Rode como o dono (`sudo ...`); nada foi aplicado - o `set -e` aborta no `git fetch`, antes do pull. |
| `bash -n` acusou erro antes da troca | O arquivo do repo chegou quebrado. **Nao instale**: conserte o `deploy/deploy.sh` no repo (commit), espere o pull e repita. |
| `--force` recusado | A trava `storage/framework/locks/deploy.lock` esta com outra execucao (RUNBOOK secao 8). |

## 6. Primeira instalacao (VPS novo)

```bash
cd /var/www/devtools
cp deploy/deploy.sh deploy/deploy-instaled.sh
chmod 755 deploy/deploy-instaled.sh
```

A copia pertence ao usuario do cron (quem fez o clone - o `git fetch` do script escreve no
`.git`); o `cp` rodando como ele ja deixa isso certo. A linha de cron, a cada minuto, no
crontab **desse mesmo usuario**:

```cron
* * * * * /var/www/devtools/deploy/deploy-instaled.sh >> /var/www/devtools/storage/logs/deploy.log 2>&1
```

Operacao do servidor em geral (instalacao, supervisor, backup, rotacao de chaves,
diagnostico): [docs/RUNBOOK.md](../docs/RUNBOOK.md).
