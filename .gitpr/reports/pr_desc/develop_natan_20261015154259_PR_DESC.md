# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix(deploy): corrige sintaxe do git pull e ignora scripts locais
```

---

## 🎯 Resumo

Corrige a sintaxe do comando de atualização de código no script de deploy, garantindo que o `git pull` resolva corretamente a branch remota a partir da referência `origin`. Além disso, remove do versionamento os scripts de deploy local (`deploy-instaled.sh` e variações de backup), evitando que arquivos gerados por ambiente poluam o repositório e causem conflitos entre máquinas.

## 🛠️ Mudanças Técnicas

- Ajusta o comando em `deploy/deploy.sh` de `git pull -q --ff-only "origin/$BRANCH"` para `git pull -q --ff-only origin "$BRANCH"`, forma explícita e mais previsível de separar remoto e branch.
- Adiciona entradas ao `.gitignore` para `/deploy/deploy-instaled.sh`, `/deploy/deploy-instaled.sh.novo` e `/deploy/deploy-instaled.sh.bak-*`.
- Atualiza documentação de suporte: `CHANGELOG.md`, `docs/RUNBOOK.md`, `docs/SPEC.md`, `docs/adr/ADR-014-deploy.md`, `deploy/deploy-instaled.md` e relatórios em `.gitpr/reports/` e `docs/claude-code/reports/`.

## ⚠️ Impacto/Avisos

- Sem alterações em banco de dados, variáveis de ambiente ou dependências.
- A mudança no `git pull` altera a forma de resolução da branch remota: validar em ambientes que utilizam nomes de branch com barras (ex.: `feature/x`) para garantir compatibilidade.
- Scripts locais de deploy passam a ser ignorados pelo Git; arquivos já rastreados precisam de `git rm --cached` para deixarem de aparecer no status — nenhum arquivo rastreado foi removido neste diff.

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)