# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
fix: limpa cache de rotas antes de gerar rotas do Wayfinder
```

---

## 🎯 Resumo

O build de produção gerava rotas TypeScript desatualizadas: como o `php artisan optimize` de um deploy anterior deixa a tabela de rotas em cache (`bootstrap/cache/routes-v7.php`), o `wayfinder:generate` executado no `vite build` lia o cache antigo e ignorava rotas novas declaradas em `routes/*.php`. Essa correção garante que o cache de rotas seja limpo antes da geração, tanto no pipeline de deploy quanto em builds disparados diretamente pelo Vite.

## 🛠️ Mudanças Técnicas

- `deploy/deploy.sh`: adiciona `php artisan route:clear` na etapa de build, antes de gerar a nova release, com comentário explicando o motivo.
- `vite.config.ts`: configura o plugin Wayfinder com `command: 'php artisan route:clear && php artisan wayfinder:generate'`, garantindo a limpeza do cache também fora do script de deploy.
- `.gitignore`: ignora `storage/framework/locks/` para evitar versionamento de arquivos de lock gerados em runtime.
- Atualização de relatórios e documentação interna em `.gitpr/reports/` e `docs/claude-code/reports/`.

## ⚠️ Impacto/Avisos

- **Banco de dados:** nenhuma migration ou alteração de schema.
- **Variáveis de ambiente:** nenhuma variável nova ou removida.
- **Dependências:** nenhuma dependência adicionada ou atualizada.
- **Deploy:** o passo `route:clear` executa em cada build; o impacto é apenas o custo de regenerar o cache de rotas, sem downtime, já que ocorre antes da troca atômica da release.
- **Ordem de execução:** o comando customizado do Wayfinder substitui o comando padrão do plugin — qualquer ajuste futuro na geração de rotas deve ser feito nessa string.

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)