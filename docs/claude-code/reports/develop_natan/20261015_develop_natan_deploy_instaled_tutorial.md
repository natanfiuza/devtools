# Relatorio - tutorial do `deploy-instaled.sh` e as docs do caminho instalado

Branch: `develop_natan`
Data: 2026-10-15
Tarefa: "No servidor eu utilizo o `deploy\deploy.sh` com outro nome `deploy-instaled.sh`
para evitar erros no git por arquivo alterado. Crie um tutorial para fazer o update deste
arquivo manualmente no servidor quando eu receber um novo arquivo `deploy.sh` em `deploy\`"
(+ "salve o tutorial em `deploy/`")
Plano aprovado em plan mode (arquivo fora do repo:
`C:\Users\nataniel\.claude\plans\execute-a-proxima-fase-partitioned-charm.md`)

**Nada foi commitado pelo assistente.** O commit e o PR sao do usuario, pelo gitpr.

## 1. O que foi pedido

O VPS **nao executa o `deploy/deploy.sh` do repo**: o cron roda uma copia,
`/var/www/devtools/deploy/deploy-instaled.sh` (byte a byte igual ao arquivo do repo, so
renomeada - resposta do usuario). O nome trocado existe para o `git pull --ff-only` do
proprio deploy nunca esbarrar em arquivo tracked modificado. O pedido: documentar o
procedimento manual de troca dessa copia quando o `deploy.sh` muda no repo.

**Por que isso importa agora:** a mudanca de hoje no script (hash gate do composer/npm e
`--force`) **nao esta em producao** - ela so vale depois que a copia instalada for trocada.
Esse procedimento nao existia em lugar nenhum.

## 2. O que o levantamento achou

Dois sub-agentes em paralelo (repo + historico; e o projeto de referencia Cultivve):

- **Zero rastros do nome da copia** (nas duas grafias, hifen e underscore) no working tree e
  nos 19 commits de todas as refs (`git log --all -S`, `find`, grep recursivo): nem doc, nem
  comentario, nem nome de arquivo.
- **Nenhuma instrucao de instalacao/atualizacao** do script no repo: nenhum `cp`/`chmod`/
  `crontab` para o script de deploy. O projeto Cultivve tambem nao tem essa convencao (o
  `.server/script_deploy.sh` de la e versionado, sem passo de instalacao documentado).
- O **RUNBOOK estava errado em 4 pontos** (todos dizendo que o cron executa
  `deploy/deploy.sh`): a linha do cron (:73), a prosa da secao 2 (:78-79), o bloco de
  comandos manuais (:82-84) e o `--force` do conserto de falha-no-meio (:143). O `SPEC.md:9`
  repetia o mesmo, e o `ADR-014:77` mandava o operador rodar `deploy/deploy.sh --force` no
  servidor.

## 3. O tutorial - `deploy/deploy-instaled.md` (novo)

Portugues sem acentos (convencao das docs do repo), com blocos copy-paste. Estrutura:

1. **Os dois arquivos** (tabela) e o porque do nome trocado; o aviso que da o tom: "mudanca
   no `deploy.sh` nao chega sozinha em producao".
2. **Regras de ouro** - o `deploy.sh` do repo no servidor **nunca** e editado; a copia
   **nunca** e editada no lugar (o bash le o script enquanto executa; arquivo reescrito no
   meio de uma execucao roda lixo) - a troca e sempre `.novo` + `mv -T`.
3. **Tem versao nova?** - o sinal e uma linha: `diff -q deploy/deploy-instaled.sh
   deploy/deploy.sh` (sem saida = nada a fazer).
4. **Instalar** - backup datado (`cp -p`), `.novo` + `bash -n` + `diff -q` (confirma que o
   arquivo que vai entrar e identico ao do repo), `chown`/`chmod --reference` (copia dono e
   modo do arquivo em uso, sem afirmar quem e), `chmod +x` explicito (o cron executa o
   caminho do arquivo direto: sem o bit, o script nao roda) e `mv -T` (troca o inode:
   execucao do cron em andamento termina intacta com o arquivo antigo).
5. **Conferir** - `deploy-instaled.sh --force` + `tail` no log; o aviso da trava ocupada; e o
   exemplo real de 2026-10-15 (a troca ativa o hash gate + `--force`).
6. **Rollback** - `mv -T` do backup de volta.
7. **Problemas comuns** - tabela: o pull reclamando de "local changes would be overwritten"
   (conserto: `git restore deploy/deploy.sh`, a copia nao e afetada), "troquei e nada mudou"
   (confira o nome no `crontab -l`), `bash -n` acusando erro (nao instale), `--force`
   recusado pela trava.
8. **Primeira instalacao** (VPS novo) - `cp` + `chown` + `chmod` + a linha de cron.

## 4. Docs vizinhas corrigidas

| Arquivo | Mudanca |
|---|---|
| `docs/RUNBOOK.md` | linha do cron, prosa da secao 2, bloco manual e o `--force` do conserto -> caminho da copia instalada; ponteiro para o tutorial |
| `docs/SPEC.md:9` | a linha de infra passa a citar a copia instalada (e as dependencias sob gatilho) |
| `docs/adr/ADR-014-deploy.md` | bullet novo nas Consequencias (mudanca no script e a excecao ao "commit e o cron leva") + a linha 77 trocada para `deploy-instaled.sh --force` |
| `.gitignore` | `/deploy/deploy-instaled.sh`, `.sh.novo` e `.bak-*` - o `git status` do VPS fica limpo |
| `CHANGELOG.md` | secao de 2026-10-15 estendida (tutorial em Adicionado; `.gitignore` em Alterado; RUNBOOK/SPEC/ADR em Corrigido) |

Ficaram de fora, de proposito: registros historicos (relatorios, planos, `FASE-0.md`, a
secao antiga do CHANGELOG) e as mencoes em que "o `deploy.sh`" nomeia o script em si, nao o
arquivo que o cron executa (RUNBOOK:116 e :295).

## 5. Verificacao

### 5.1 A sequencia do tutorial, rodada de verdade

Em `%TEMP%\di` (fora do repo), com um script de teste: as edicoes do Git Bash confirmaram
cada passo - `diff -q` acusou "differ"; `cp -p` guardou o backup; `bash -n` deu exit 0 no
valido e **exit 2** no arquivo quebrado de proposito (`syntax error: unexpected end of
file`); `diff -q` apos a copia ficou vazio (identico ao repo); `mv -T` publicou a versao 2;
`diff -q` pos-troca vazio (o invariante); o rollback com `mv -T` do backup devolveu a versao
1. A versao final do bloco (ja com o `chmod +x`) foi rodada de novo num harness proprio:
`chmod +x` + `mv -T` deixaram o arquivo em `-rwxr-xr-x`, a execucao direta (`./inst.sh
teste`) rodou a versao nova e o rollback devolveu a antiga. Duas ressalvas honestas: o
`chown --reference` deu exit 0, mas no Git Bash ele e emulado (o efeito real de dono:grupo so
existe no Linux); os `sudo`/`crontab` sao do VPS e foram conferidos por leitura.

### 5.2 Docs

- `grep -rn 'deploy/deploy\.sh'` nas docs: so sobraram registros historicos e as mencoes
  legitimas (o script em si, o bullet novo do ADR).
- Acentos nos arquivos novos: `deploy/deploy-instaled.md` 0; `.gitignore` 0 (convencao
  respeitada).
- Os alvos dos links relativos existem (`../deploy/deploy-instaled.md` a partir de
  `docs/RUNBOOK.md`; `../../deploy/deploy-instaled.md` a partir de `docs/adr/`).
- `git check-ignore -v` com a grafia final: os tres padroes casam (`.gitignore:49/50/51`), e
  nem `deploy/deploy.sh` nem `deploy/deploy-instaled.md` sao ignorados.
- Varredura final (`rg --no-ignore --hidden`, incluindo `.gitpr/`): nenhuma grafia com
  underscore sobrou no repo.

### 5.3 O que NAO foi rodado, e por que

`composer test` / `npm run test`: nada aqui toca PHP ou JS (so docs, `.gitignore` e um
`.md` novo). O script em si ja estava verificado pelo harness de 50 assercoes da tarefa
anterior.

## 6. Nota de percurso

O primeiro rewrap do ADR-014 (linha 77) comeu um `nao` da frase - o parametro
`chown --reference` ficou correto, mas o texto virou "refaz as instalacoes que terminaram"
no lugar de "que nao terminaram". Percebido na leitura de conferencia e corrigido na hora
(`nao terminaram) ou entrar commit novo`).

Duas correcoes vieram do usuario depois de o tutorial estar escrito: (a) faltava o comando
explicito para deixar a copia executavel - entrou `chmod +x` antes do `mv -T`, com a razao no
texto (o cron executa o caminho do arquivo direto); (b) o nome real no servidor e com
**hifen** (`deploy-instaled.sh`), e nao com underscore - a grafia foi unificada em
`.gitignore`, `CHANGELOG.md`, `docs/RUNBOOK.md`, `docs/SPEC.md`, `docs/adr/ADR-014-deploy.md`
e neste relatorio, e o tutorial foi para `deploy/deploy-instaled.md` (links corrigidos
junto). O nome deste relatorio segue a convencao de taskname do CLAUDE.md (underscore entre
palavras), entao ficou como estava.

Terceira correcao, essa vinda de um teste real no VPS (nao de leitura): o usuario rodou
`sudo -u www-data /var/www/devtools/deploy/deploy-instaled.sh --force` e o script morreu no
`git fetch` com `error: insufficient permission for adding an object to repository database
.git/objects`. A causa: o repositorio pertence a quem fez o clone (a secao 1 do RUNBOOK
clona como `root`) e `www-data` so tem `storage`/`bootstrap/cache` - o `-u www-data` vinha do
RUNBOOK original e foi mantido no tutorial sem nunca ter sido exercitado. Conserto nos dois:
os comandos manuais rodam como o dono do repositorio (o usuario do cron), com a deteccao no
texto (`stat -c '%U' /var/www/devtools/.git` e `sudo crontab -l | grep deploy`), uma linha
nova na tabela de problemas comuns do tutorial e o aviso, no RUNBOOK e no tutorial, de que o
cron e do mesmo usuario do clone. Nada foi aplicado pela execucao que falhou: o `set -e`
abortou no `git fetch`, antes do pull.

Quarta correcao, tambem de execucao real: `./deploy/deploy-instaled.sh` morreu com `fatal:
'origin/main' does not appear to be a git repository`. E um defeito do **repositorio**, na
linha 72 desde `172aea0` (o commit que criou o script - conferido em todas as versoes do
arquivo no git): `git pull -q --ff-only "origin/$BRANCH"` passa um argumento unico, e o git o
le como repositorio; o `git fetch -q origin "$BRANCH"` da linha 64 esta na forma certa, e por
isso o log "Deploy iniciado" chegava a sair antes do erro. Provado em harness: num repo de
teste a forma atual falha com exatamente essa mensagem, e `git pull -q --ff-only origin main`
funciona (fast-forward aplicado). Corrigido no repo. Consequencia operacional: como o `git
pull` e justamente o que esta quebrado, o servidor nao puxa a correcao pelo cron - precisa de
um `git pull --ff-only origin main` manual (dois argumentos) e da troca da copia de novo.
Inferencia a confirmar com o backup do VPS: a copia so rodava porque tinha ajuste local nessa
linha, e a troca de hoje (byte a byte o repo) expos o defeito - por isso o tutorial ganhou, na
secao 2, o aviso de que ajuste local da copia velha se perde na troca.

## 7. Estado do working tree

```
 M .gitignore
 M CHANGELOG.md
 M docs/RUNBOOK.md
 M docs/SPEC.md
 M docs/adr/ADR-014-deploy.md
?? .gitpr/reports/pr_desc/develop_natan_20261015103553_PR_DESC.md
?? deploy/deploy-instaled.md
?? docs/claude-code/reports/develop_natan/20261015_develop_natan_deploy_instaled_tutorial.md
```

A tarefa anterior (deploy gate) foi **commitada pelo usuario** como `680ab1c` ("feat:
adiciona cache de dependencias e flag --force no deploy"), com os docs daquela sessao e o
`deploy/deploy.sh` - o working tree nao tem mais nada dela (conferido: `git diff HEAD --
deploy/deploy.sh` vazio).

## 8. Pendente

- Commit e PR do usuario (gitpr).
- **A troca no VPS** (o proprio tema do tutorial): `diff -q deploy/deploy-instaled.sh
  deploy/deploy.sh` no servidor, e o passo a passo de `deploy/deploy-instaled.md`. Sem ela, o
  cron segue rodando o script antigo.
- No VPS: repetir o teste do passo 3 **como `root`** (o que falhou rodou como `www-data`) e
  conferir o `storage/logs/deploy.log`.
- No VPS: `diff -u deploy/deploy.sh deploy/deploy-instaled.sh.bak-2026-10-15` - mostra todo
  ajuste local que a copia antiga tinha (a troca descarta; so o que estiver no commit sobrevive).
- No VPS, depois do fix commitado: `sudo git -C /var/www/devtools pull --ff-only origin main`
  (manual, dois argumentos - o pull do script e que esta quebrado), trocar a copia pela secao 2
  do tutorial e rodar o teste `--force` para o deploy pendente completar (build, migrate, SSR).
