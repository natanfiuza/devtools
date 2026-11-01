# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
feat: adiciona logo GitPR ao catálogo do BadgeForge
```

---

## 🎯 Resumo

Amplia o catálogo de logos embutidos do BadgeForge com a inclusão do logo **GitPR**, permitindo que badges gerados referenciem essa ferramenta de forma nativa. A mudança mantém o catálogo alinhado ao conjunto de ferramentas suportadas e garante que os testes de contrato reflitam o novo total de logos disponíveis.

## 🛠️ Mudanças Técnicas

- Adiciona a entrada `gitpr` (`name: GitPR`, `file: gitpr.svg`) ao catálogo em `config/badgeforge.php`.
- Atualiza o fixture `tests/Fixtures/badgeforge/tool-props.json` com o novo item `{ "slug": "gitpr", "name": "GitPR" }`.
- Ajusta o teste `LogoCatalogTest` para esperar 25 logos embutidos (antes 24), incluindo a descrição do caso de teste.
- Atualiza documentação de aceitação, plano e relatório da fase 1 do BadgeForge, além dos relatórios de linter e PR desc.

## ⚠️ Impacto/Avisos

- **Asset obrigatório:** a entrada referencia `gitpr.svg`, que precisa existir no diretório de logos; sua ausência quebrará a geração de badges com esse slug.
- **Contrato de contagem:** qualquer consumidor ou teste que assuma 24 logos precisará ser atualizado para 25.
- **Banco de dados:** sem migrations ou alterações de schema.
- **Envs/dependências:** nenhuma nova variável de ambiente ou dependência adicionada.

close #119

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)