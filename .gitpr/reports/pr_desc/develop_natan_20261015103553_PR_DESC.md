# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona --force e install condicional de dependências no deploy
```

---

## 🎯 Resumo

Torna o deploy mais resiliente e eficiente: evita reinstalações desnecessárias de dependências com base no hash do manifesto, permite reexecução manual via `--force` após falhas e garante que o `composer install` seja executado apenas quando o `composer.json` mudar. Também trata o caso de lock desatualizado no servidor, caindo para `composer update`.

## 🛠️ Mudanças Técnicas

- Adiciona flag `--force` para refazer o deploy completo, ignorando checagem de HEAD e a saída silenciosa por lock.
- Substitui a instalação incondicional do Composer por verificação de hash SHA-256 do `composer.json`, armazenado em `storage/framework/composer-deps.sha256`.
- Inclui fallback para `composer update` quando o `install` recusa o lock do servidor (exit 4).
- Loga quando `composer install` ou `npm install` são pulados por falta de mudanças no manifesto.
- Adiciona `*.sha256` ao `.gitignore` de `storage/framework`.

## ⚠️ Impacto/Avisos

- **Dependências**: o `composer install` deixa de rodar sempre; agora é condicional ao hash do `composer.json`. A primeira execução após o deploy criará o arquivo de hash. O fallback para `composer update` pode alterar versões não fixadas.
- **Banco de dados**: nenhuma alteração.
- **Variáveis de ambiente**: nenhuma alteração.
- **Operação**: a flag `--force` é manual e não deve ser usada pelo cron; ela força o deploy mesmo sem commit novo e falha explicitamente se outra execução estiver em andamento.
- Os arquivos de hash não são versionados (adicionados ao `.gitignore`).


close #15

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)