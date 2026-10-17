# 🚀 Sugestão de Pull Request

**Mensagem de Commit Recomendada:**
```text
refactor(analytics): ajusta assinatura de gtag sem rest param
```

---

".gitpr.commit.md": "gitpr.commit.md"

## 🎯 Resumo
Ajusta a assinatura da função `gtag` para manter a checagem de tipos nas chamadas e evitar a emissão de parâmetro rest (`...`) no JavaScript minificado. Não há mudança de comportamento em runtime; o objetivo é melhorar o artefato gerado pelo build e preservar a segurança de tipos.

## 🛠️ Mudanças Técnicas
- Adiciona sobrecarga de tipo `function gtag(...command: unknown[]): void;` para chamadas com argumentos.
- Altera a implementação para `function gtag(): void`, sem parâmetros rest no código emitido.
- Atualiza comentário explicando a estratégia de sobrecarga.
- Mantém a compatibilidade da API existente e do `window.dataLayer`.

## ⚠️ Impacto/Avisos
- Sem mudanças em banco de dados, variáveis de ambiente ou dependências.
- Sem alteração funcional para o usuário final.
- Exige recompilação/rebuild do bundle para refletir o JS minificado atualizado.

---

[![GitPR](https://img.shields.io/badge/GitPR-no_issues-brightgreen)](https://gitpr.natanfiuza.dev.br/)